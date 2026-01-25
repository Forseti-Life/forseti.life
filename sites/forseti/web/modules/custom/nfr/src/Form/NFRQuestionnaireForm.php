<?php

declare(strict_types=1);

namespace Drupal\nfr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enrollment questionnaire form (30-minute comprehensive survey).
 */
class NFRQuestionnaireForm extends FormBase {

  /**
   * Constructs the form.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'nfr_questionnaire_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = (int) $this->currentUser->id();
    
    // Load existing data if available
    $existing = $this->loadQuestionnaire($uid);
    
    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'nfr/enrollment';

    // Determine current section: resume from last completed + 1, or start at 1
    $last_completed = $this->getLastCompletedSection($uid);
    $resume_section = min($last_completed + 1, 9); // Resume at next incomplete section
    
    // Current section tracking (for multi-step)
    $current_section = $form_state->get('current_section') ?? $resume_section;
    $form_state->set('current_section', $current_section);
    
    // Calculate progress percentage
    $progress_percent = ($current_section - 1) / 9 * 100;

    // Progress indicator
    $form['progress'] = [
      '#type' => 'markup',
      '#markup' => '<div class="questionnaire-progress"><div class="progress-text">Enrollment Questionnaire - Section ' . $current_section . ' of 9 (' . round($progress_percent) . '% complete)</div><div class="progress-bar"><div class="progress-fill" style="width: ' . $progress_percent . '%"></div></div></div>',
      '#weight' => -100,
    ];
    
    // Section navigation menu (if user has started the questionnaire)
    if ($last_completed > 0 || $current_section > 1) {
      $form['section_nav'] = [
        '#type' => 'details',
        '#title' => $this->t('Jump to Section'),
        '#open' => FALSE,
        '#weight' => -99,
      ];
      
      $section_names = [
        1 => 'Demographics',
        2 => 'Work History',
        3 => 'Exposure Information',
        4 => 'Military Service',
        5 => 'Other Employment',
        6 => 'PPE Practices',
        7 => 'Decontamination',
        8 => 'Health Information',
        9 => 'Lifestyle Factors',
      ];
      
      $form['section_nav']['current_note'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('Click any section to jump to it. Your current section data will be saved automatically.') . '</p>',
      ];
      
      foreach ($section_names as $section_num => $section_name) {
        $status = '';
        if ($section_num <= $last_completed) {
          $status = ' ✓'; // Completed
        }
        if ($section_num == $current_section) {
          $status .= ' (current)';
        }
        
        $form['section_nav']['jump_' . $section_num] = [
          '#type' => 'submit',
          '#value' => $this->t('Section @num: @name@status', [
            '@num' => $section_num,
            '@name' => $section_name,
            '@status' => $status,
          ]),
          '#submit' => ['::jumpToSection'],
          '#section_number' => $section_num,
          '#limit_validation_errors' => [],
          '#attributes' => [
            'class' => [
              $section_num == $current_section ? 'section-nav-current' : 'section-nav-link',
              $section_num <= $last_completed ? 'section-nav-completed' : '',
            ],
          ],
        ];
      }
    }

    // Section 1: Demographics
    if ($current_section == 1) {
      $this->buildDemographicsSection($form, $form_state, $existing);
    }
    // Section 2: Work History
    elseif ($current_section == 2) {
      $this->buildWorkHistorySection($form, $form_state, $existing);
    }
    // Section 3: Exposure Information
    elseif ($current_section == 3) {
      $this->buildExposureSection($form, $form_state, $existing);
    }
    // Section 4: Military Service
    elseif ($current_section == 4) {
      $this->buildMilitaryServiceSection($form, $form_state, $existing);
    }
    // Section 5: Other Employment
    elseif ($current_section == 5) {
      $this->buildOtherEmploymentSection($form, $form_state, $existing);
    }
    // Section 6: PPE Practices
    elseif ($current_section == 6) {
      $this->buildPPESection($form, $form_state, $existing);
    }
    // Section 7: Decontamination Practices
    elseif ($current_section == 7) {
      $this->buildDecontaminationSection($form, $form_state, $existing);
    }
    // Section 8: Health Information
    elseif ($current_section == 8) {
      $this->buildHealthSection($form, $form_state, $existing);
    }
    // Section 9: Lifestyle Factors
    elseif ($current_section == 9) {
      $this->buildLifestyleSection($form, $form_state, $existing);
    }

    // Navigation buttons
    $form['actions'] = ['#type' => 'actions'];
    
    // Previous button (except first section)
    if ($current_section > 1) {
      $form['actions']['previous'] = [
        '#type' => 'submit',
        '#value' => $this->t('Previous'),
        '#submit' => ['::previousSubmit'],
        '#limit_validation_errors' => [],
        '#attributes' => ['class' => ['button', 'button--secondary']],
      ];
    }

    // Save & Exit button (always visible)
    $form['actions']['save_exit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save & Exit'),
      '#submit' => ['::saveAndExit'],
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // Next/Submit button
    if ($current_section < 9) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#submit' => ['::nextSubmit'],
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }
    else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save & Continue to Review'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }

    return $form;
  }

  /**
   * Build demographics section.
   */
  private function buildDemographicsSection(array &$form, FormStateInterface $form_state, ?array $existing): void {
    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 1: Demographics</h2>',
    ];

    $demographics = $existing['demographics'] ?? [];

    $form['demographics'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Demographics'),
    ];

    $form['demographics']['race_ethnicity'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Race/Ethnicity (select all that apply)'),
      '#options' => [
        'american_indian' => $this->t('American Indian or Alaska Native'),
        'asian' => $this->t('Asian'),
        'black' => $this->t('Black or African American'),
        'hispanic' => $this->t('Hispanic or Latino'),
        'pacific_islander' => $this->t('Native Hawaiian or Other Pacific Islander'),
        'white' => $this->t('White'),
        'other' => $this->t('Other'),
      ],
      '#default_value' => $demographics['race_ethnicity'] ?? [],
    ];

    $form['demographics']['race_other'] = [
      '#type' => 'textfield',
      '#title' => $this->t('If other, please specify'),
      '#default_value' => $demographics['race_other'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="demographics[race_ethnicity][other]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['demographics']['education_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Highest Education Level'),
      '#required' => TRUE,
      '#options' => [
        '' => $this->t('- Select -'),
        'less_than_hs' => $this->t('Less than high school'),
        'hs_ged' => $this->t('High school or GED'),
        'some_college' => $this->t('Some college'),
        'associate' => $this->t('Associate degree'),
        'bachelor' => $this->t("Bachelor's degree"),
        'graduate' => $this->t('Graduate degree'),
      ],
      '#default_value' => $demographics['education_level'] ?? '',
    ];

    $form['demographics']['marital_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Marital Status'),
      '#required' => TRUE,
      '#options' => [
        '' => $this->t('- Select -'),
        'single' => $this->t('Single, never married'),
        'married' => $this->t('Married'),
        'divorced' => $this->t('Divorced'),
        'widowed' => $this->t('Widowed'),
        'separated' => $this->t('Separated'),
      ],
      '#default_value' => $demographics['marital_status'] ?? '',
    ];
  }

  /**
   * Build work history section.
   */
  private function buildWorkHistorySection(array &$form, FormStateInterface $form_state, ?array $existing): void {
    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 2: Work History</h2><p>Please tell us about your entire firefighting career, including all departments where you worked.</p>',
    ];

    $work_history = $existing['work_history'] ?? [];
    
    // Number of departments
    $num_departments = $form_state->get('num_departments') ?? $work_history['num_departments'] ?? 1;
    
    $form['work_history'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Work History'),
    ];

    $form['work_history']['num_departments'] = [
      '#type' => 'number',
      '#title' => $this->t('How many fire departments or agencies have you worked at during your career?'),
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 20,
      '#default_value' => $num_departments,
      '#ajax' => [
        'callback' => '::updateDepartmentFields',
        'wrapper' => 'departments-wrapper',
      ],
    ];

    $form['work_history']['departments'] = [
      '#type' => 'container',
      '#prefix' => '<div id="departments-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    // Store for next rebuild
    if ($form_state->getTriggeringElement()['#name'] ?? '' == 'work_history[num_departments]') {
      $num_departments = (int) $form_state->getValue(['work_history', 'num_departments']);
      $form_state->set('num_departments', $num_departments);
    }

    // Create department fieldsets
    for ($i = 0; $i < $num_departments; $i++) {
      $dept_data = $work_history['departments'][$i] ?? [];
      $dept_label = $i == 0 ? $this->t('Department @num (Most Recent)', ['@num' => $i + 1]) : $this->t('Department @num', ['@num' => $i + 1]);
      
      $form['work_history']['departments'][$i] = [
        '#type' => 'details',
        '#title' => $dept_label,
        '#open' => $i == 0,
      ];

      $form['work_history']['departments'][$i]['department_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Fire Department Name'),
        '#required' => TRUE,
        '#default_value' => $dept_data['department_name'] ?? '',
      ];

      $form['work_history']['departments'][$i]['state'] = [
        '#type' => 'select',
        '#title' => $this->t('State'),
        '#required' => TRUE,
        '#options' => ['' => $this->t('- Select -')] + $this->getStateOptions(),
        '#default_value' => $dept_data['state'] ?? '',
      ];

      $form['work_history']['departments'][$i]['city'] = [
        '#type' => 'textfield',
        '#title' => $this->t('City'),
        '#required' => TRUE,
        '#default_value' => $dept_data['city'] ?? '',
      ];

      $form['work_history']['departments'][$i]['fdid'] = [
        '#type' => 'textfield',
        '#title' => $this->t('FDID (if known)'),
        '#default_value' => $dept_data['fdid'] ?? '',
        '#description' => $this->t('Fire Department Identification Number'),
      ];

      $form['work_history']['departments'][$i]['start_date'] = [
        '#type' => 'date',
        '#title' => $this->t('Start Date'),
        '#required' => TRUE,
        '#default_value' => $dept_data['start_date'] ?? '',
      ];

      $form['work_history']['departments'][$i]['currently_employed'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Currently employed here'),
        '#default_value' => $dept_data['currently_employed'] ?? FALSE,
      ];

      $form['work_history']['departments'][$i]['end_date'] = [
        '#type' => 'date',
        '#title' => $this->t('End Date'),
        '#default_value' => $dept_data['end_date'] ?? '',
        '#states' => [
          'visible' => [
            ':input[name="work_history[departments][' . $i . '][currently_employed]"]' => ['checked' => FALSE],
          ],
          'required' => [
            ':input[name="work_history[departments][' . $i . '][currently_employed]"]' => ['checked' => FALSE],
          ],
        ],
      ];

      // Job titles at this department
      $num_jobs = $form_state->get("num_jobs_dept_{$i}") ?? $dept_data['num_jobs'] ?? 1;

      $form['work_history']['departments'][$i]['num_jobs'] = [
        '#type' => 'number',
        '#title' => $this->t('How many different job titles or positions did you hold at this department?'),
        '#required' => TRUE,
        '#min' => 1,
        '#max' => 10,
        '#default_value' => $num_jobs,
        '#ajax' => [
          'callback' => '::updateJobFields',
          'wrapper' => "jobs-wrapper-{$i}",
        ],
      ];

      $form['work_history']['departments'][$i]['jobs'] = [
        '#type' => 'container',
        '#prefix' => "<div id=\"jobs-wrapper-{$i}\">",
        '#suffix' => '</div>',
        '#tree' => TRUE,
      ];

      if ($form_state->getTriggeringElement()['#name'] ?? '' == "work_history[departments][{$i}][num_jobs]") {
        $num_jobs = (int) $form_state->getValue(['work_history', 'departments', $i, 'num_jobs']);
        $form_state->set("num_jobs_dept_{$i}", $num_jobs);
      }

      // Create job fieldsets
      for ($j = 0; $j < $num_jobs; $j++) {
        $job_data = $dept_data['jobs'][$j] ?? [];
        
        $form['work_history']['departments'][$i]['jobs'][$j] = [
          '#type' => 'details',
          '#title' => $this->t('Job Title @num', ['@num' => $j + 1]),
          '#open' => $j == 0,
        ];

        $form['work_history']['departments'][$i]['jobs'][$j]['title'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Job Title or Rank'),
          '#required' => TRUE,
          '#default_value' => $job_data['title'] ?? '',
          '#description' => $this->t('e.g., Firefighter, Engineer, Lieutenant, Captain, etc.'),
        ];

        $form['work_history']['departments'][$i]['jobs'][$j]['employment_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Employment Type'),
          '#required' => TRUE,
          '#options' => [
            '' => $this->t('- Select -'),
            'career' => $this->t('Career/Full-time'),
            'volunteer' => $this->t('Volunteer'),
            'paid_on_call' => $this->t('Paid-on-call'),
            'seasonal' => $this->t('Seasonal'),
            'wildland' => $this->t('Wildland firefighter'),
            'military' => $this->t('Military firefighter'),
            'other' => $this->t('Other'),
          ],
          '#default_value' => $job_data['employment_type'] ?? '',
        ];

        $form['work_history']['departments'][$i]['jobs'][$j]['responded_incidents'] = [
          '#type' => 'radios',
          '#title' => $this->t('Did you respond to fires or emergency incidents in this position?'),
          '#required' => TRUE,
          '#options' => [
            'yes' => $this->t('Yes'),
            'no' => $this->t('No'),
          ],
          '#default_value' => $job_data['responded_incidents'] ?? '',
        ];

        // Incident frequency table
        $form['work_history']['departments'][$i]['jobs'][$j]['incident_types'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Incident Response Frequency'),
          '#description' => $this->t('For each type of incident, select how often you responded on average per year in this position.'),
          '#states' => [
            'visible' => [
              ':input[name="work_history[departments][' . $i . '][jobs][' . $j . '][responded_incidents]"]' => ['value' => 'yes'],
            ],
          ],
        ];

        $incident_types = [
          'structure_residential' => 'Structure fires (residential)',
          'structure_commercial' => 'Structure fires (commercial/industrial)',
          'vehicle' => 'Vehicle fires',
          'wildland' => 'Wildland fires',
          'medical_ems' => 'Medical/EMS calls',
          'hazmat' => 'Hazardous materials incidents',
          'technical_rescue' => 'Technical rescue',
          'arff' => 'Aircraft rescue firefighting (ARFF)',
          'marine' => 'Marine firefighting',
          'prescribed_burns' => 'Prescribed burns',
          'training_fires' => 'Training fires (live fire)',
          'other' => 'Other fire-related activities',
        ];

        $frequency_options = [
          '' => $this->t('- Select -'),
          'never' => $this->t('Never'),
          'less_than_1' => $this->t('Less than once per year'),
          '1_5' => $this->t('1-5 per year'),
          '6_20' => $this->t('6-20 per year'),
          '21_50' => $this->t('21-50 per year'),
          'more_than_50' => $this->t('More than 50 per year'),
        ];

        foreach ($incident_types as $type_key => $type_label) {
          $form['work_history']['departments'][$i]['jobs'][$j]['incident_types'][$type_key] = [
            '#type' => 'select',
            '#title' => $this->t($type_label),
            '#options' => $frequency_options,
            '#default_value' => $job_data['incident_types'][$type_key] ?? '',
          ];
        }
      }
    }
  }

  /**
   * Build exposure information section.
   */
  private function buildExposureSection(array &$form, FormStateInterface $form_state, ?array $existing): void {
    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 3: Exposure Information</h2><p>These questions help us understand your exposures to substances that may affect firefighter health.</p>',
    ];

    $exposure = $existing['exposure'] ?? [];

    $form['exposure'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Exposure Information'),
    ];

    $form['exposure']['afff_used'] = [
      '#type' => 'radios',
      '#title' => $this->t('Have you ever used Aqueous Film-Forming Foam (AFFF), also known as firefighting foam?'),
      '#required' => TRUE,
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
        'unknown' => $this->t("Don't Know"),
      ],
      '#default_value' => $exposure['afff_used'] ?? '',
    ];

    $form['exposure']['afff_times'] = [
      '#type' => 'number',
      '#title' => $this->t('Approximately how many times did you use AFFF?'),
      '#min' => 1,
      '#default_value' => $exposure['afff_times'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="exposure[afff_used]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    $form['exposure']['afff_first_year'] = [
      '#type' => 'number',
      '#title' => $this->t('In what year did you first use AFFF?'),
      '#min' => 1950,
      '#max' => (int) date('Y'),
      '#default_value' => $exposure['afff_first_year'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="exposure[afff_used]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    $form['exposure']['diesel_exhaust'] = [
      '#type' => 'radios',
      '#title' => $this->t('Were you regularly exposed to diesel exhaust from fire apparatus?'),
      '#required' => TRUE,
      '#options' => [
        'regularly' => $this->t('Yes, regularly'),
        'sometimes' => $this->t('Sometimes'),
        'rarely' => $this->t('Rarely'),
        'never' => $this->t('Never'),
      ],
      '#default_value' => $exposure['diesel_exhaust'] ?? '',
    ];

    $form['exposure']['chemical_activities'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Were you involved in any of these activities that may involve chemical exposure?'),
      '#options' => [
        'fire_investigation' => $this->t('Fire investigation'),
        'overhaul' => $this->t('Overhaul operations'),
        'salvage' => $this->t('Salvage operations'),
        'vehicle_maintenance' => $this->t('Vehicle maintenance/apparatus cleaning'),
        'station_maintenance' => $this->t('Station maintenance'),
        'none' => $this->t('None of the above'),
      ],
      '#default_value' => $exposure['chemical_activities'] ?? [],
    ];

    $form['exposure']['major_incidents'] = [
      '#type' => 'radios',
      '#title' => $this->t('Were you involved in any major incidents or events with prolonged or intense exposure?'),
      '#required' => TRUE,
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => $exposure['major_incidents'] ?? '',
    ];

    // Major incidents repeating fields
    $num_incidents = $form_state->get('num_major_incidents') ?? count($exposure['incidents'] ?? []) ?: 0;
    
    $form['exposure']['incidents'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="exposure[major_incidents]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    $form['exposure']['add_incident'] = [
      '#type' => 'submit',
      '#value' => $this->t('+ Add Incident'),
      '#submit' => ['::addIncident'],
      '#ajax' => [
        'callback' => '::updateIncidentFields',
        'wrapper' => 'incidents-wrapper',
      ],
      '#limit_validation_errors' => [],
      '#states' => [
        'visible' => [
          ':input[name="exposure[major_incidents]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    $form['exposure']['incidents_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="incidents-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < $num_incidents; $i++) {
      $incident_data = $exposure['incidents'][$i] ?? [];
      
      $form['exposure']['incidents_wrapper'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Incident @num', ['@num' => $i + 1]),
      ];

      $form['exposure']['incidents_wrapper'][$i]['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Event Description'),
        '#rows' => 3,
        '#default_value' => $incident_data['description'] ?? '',
      ];

      $form['exposure']['incidents_wrapper'][$i]['date'] = [
        '#type' => 'date',
        '#title' => $this->t('Date (approximate)'),
        '#default_value' => $incident_data['date'] ?? '',
      ];

      $form['exposure']['incidents_wrapper'][$i]['duration'] = [
        '#type' => 'select',
        '#title' => $this->t('Duration of Involvement'),
        '#options' => [
          '' => $this->t('- Select -'),
          'hours' => $this->t('Hours'),
          'days' => $this->t('Days'),
          'weeks' => $this->t('Weeks'),
          'months' => $this->t('Months'),
        ],
        '#default_value' => $incident_data['duration'] ?? '',
      ];
    }
  }

  /**
   * Build military service section.
   */
  private function buildMilitaryServiceSection(array &$form, FormStateInterface $form_state, ?array $existing): void {
    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 4: Military Service</h2>',
    ];

    $military = $existing['military'] ?? [];

    $form['military'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Military Service'),
    ];

    $form['military']['served'] = [
      '#type' => 'radios',
      '#title' => $this->t('Have you ever served in the military?'),
      '#required' => TRUE,
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => $military['served'] ?? '',
    ];

    $form['military']['branch'] = [
      '#type' => 'select',
      '#title' => $this->t('Branch'),
      '#options' => [
        '' => $this->t('- Select -'),
        'army' => $this->t('Army'),
        'navy' => $this->t('Navy'),
        'air_force' => $this->t('Air Force'),
        'marines' => $this->t('Marines'),
        'coast_guard' => $this->t('Coast Guard'),
        'national_guard' => $this->t('National Guard'),
        'reserves' => $this->t('Reserves'),
      ],
      '#default_value' => $military['branch'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="military[served]"]' => ['value' => 'yes'],
        ],
        'required' => [
          ':input[name="military[served]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    $form['military']['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
      '#default_value' => $military['start_date'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="military[served]"]' => ['value' => 'yes'],
        ],
        'required' => [
          ':input[name="military[served]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    $form['military']['currently_serving'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Currently serving'),
      '#default_value' => $military['currently_serving'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="military[served]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    $form['military']['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      '#default_value' => $military['end_date'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="military[served]"]' => ['value' => 'yes'],
          ':input[name="military[currently_serving]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['military']['was_firefighter'] = [
      '#type' => 'radios',
      '#title' => $this->t('Were you a military firefighter?'),
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => $military['was_firefighter'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="military[served]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    $form['military']['firefighting_duties'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Please describe your military firefighting duties'),
      '#rows' => 4,
      '#default_value' => $military['firefighting_duties'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="military[was_firefighter]"]' => ['value' => 'yes'],
        ],
      ],
    ];
  }

  /**
   * Build other employment section.
   */
  private function buildOtherEmploymentSection(array &$form, FormStateInterface $form_state, ?array $existing): void {
    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 5: Other Employment</h2>',
    ];

    $other_employment = $existing['other_employment'] ?? [];

    $form['other_employment'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Other Employment'),
    ];

    $form['other_employment']['had_other_jobs'] = [
      '#type' => 'radios',
      '#title' => $this->t('Have you worked in any other jobs outside of firefighting for more than 1 year?'),
      '#required' => TRUE,
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => $other_employment['had_other_jobs'] ?? '',
    ];

    // Other jobs repeating fields
    $num_other_jobs = $form_state->get('num_other_jobs') ?? count($other_employment['jobs'] ?? []) ?: 0;

    $form['other_employment']['jobs'] = [
      '#type' => 'container',
      '#prefix' => '<div id="other-jobs-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="other_employment[had_other_jobs]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    $form['other_employment']['add_job'] = [
      '#type' => 'submit',
      '#value' => $this->t('+ Add Another Job'),
      '#submit' => ['::addOtherJob'],
      '#ajax' => [
        'callback' => '::updateOtherJobFields',
        'wrapper' => 'other-jobs-wrapper',
      ],
      '#limit_validation_errors' => [],
      '#states' => [
        'visible' => [
          ':input[name="other_employment[had_other_jobs]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    for ($i = 0; $i < $num_other_jobs; $i++) {
      $job_data = $other_employment['jobs'][$i] ?? [];
      
      $form['other_employment']['jobs'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Job @num', ['@num' => $i + 1]),
      ];

      $form['other_employment']['jobs'][$i]['occupation'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Job/Occupation'),
        '#default_value' => $job_data['occupation'] ?? '',
      ];

      $form['other_employment']['jobs'][$i]['industry'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Industry'),
        '#default_value' => $job_data['industry'] ?? '',
      ];

      $form['other_employment']['jobs'][$i]['start_year'] = [
        '#type' => 'number',
        '#title' => $this->t('Start Year'),
        '#min' => 1950,
        '#max' => (int) date('Y'),
        '#default_value' => $job_data['start_year'] ?? '',
      ];

      $form['other_employment']['jobs'][$i]['end_year'] = [
        '#type' => 'number',
        '#title' => $this->t('End Year'),
        '#min' => 1950,
        '#max' => (int) date('Y'),
        '#default_value' => $job_data['end_year'] ?? '',
      ];

      $form['other_employment']['jobs'][$i]['exposures'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Potential Hazardous Exposures'),
        '#options' => [
          'chemicals' => $this->t('Chemicals'),
          'radiation' => $this->t('Radiation'),
          'asbestos' => $this->t('Asbestos'),
          'heavy_metals' => $this->t('Heavy metals'),
          'other' => $this->t('Other'),
        ],
        '#default_value' => $job_data['exposures'] ?? [],
      ];

      $form['other_employment']['jobs'][$i]['exposures_other'] = [
        '#type' => 'textfield',
        '#title' => $this->t('If other, please specify'),
        '#default_value' => $job_data['exposures_other'] ?? '',
        '#states' => [
          'visible' => [
            ':input[name="other_employment[jobs][' . $i . '][exposures][other]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
  }

  /**
   * Build PPE practices section.
   */
  private function buildPPESection(array &$form, FormStateInterface $form_state, ?array $existing): void {
    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 6: Personal Protective Equipment (PPE)</h2><p>These questions help us understand the equipment you used and when you started using it.</p>',
    ];

    $ppe = $existing['ppe'] ?? [];

    $form['ppe'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('PPE Practices'),
    ];

    $ppe_types = [
      'scba' => 'Self-Contained Breathing Apparatus (SCBA)',
      'turnout_coat' => 'Structural firefighting coat (turnout coat)',
      'turnout_pants' => 'Structural firefighting pants (turnout pants)',
      'gloves' => 'Firefighting gloves',
      'helmet' => 'Firefighting helmet',
      'boots' => 'Firefighting boots',
      'nomex_hood' => 'Nomex hood (particulate-blocking)',
      'wildland_clothing' => 'Wildland firefighting clothing',
    ];

    $form['ppe']['equipment_table'] = [
      '#type' => 'markup',
      '#markup' => '<div class="ppe-intro">' . $this->t('For each type of equipment, indicate if you used it and when you started.') . '</div>',
    ];

    foreach ($ppe_types as $type_key => $type_label) {
      $form['ppe'][$type_key] = [
        '#type' => 'fieldset',
        '#title' => $this->t($type_label),
      ];

      $form['ppe'][$type_key]['ever_used'] = [
        '#type' => 'radios',
        '#title' => $this->t('Ever used?'),
        '#options' => [
          'yes' => $this->t('Yes'),
          'no' => $this->t('No'),
        ],
        '#default_value' => $ppe[$type_key]['ever_used'] ?? '',
      ];

      $form['ppe'][$type_key]['year_started'] = [
        '#type' => 'number',
        '#title' => $this->t('Year Started Using'),
        '#min' => 1950,
        '#max' => (int) date('Y'),
        '#default_value' => $ppe[$type_key]['year_started'] ?? '',
        '#states' => [
          'visible' => [
            ':input[name="ppe[' . $type_key . '][ever_used]"]' => ['value' => 'yes'],
          ],
        ],
      ];
    }

    // SCBA usage follow-up
    $form['ppe']['scba_during_suppression'] = [
      '#type' => 'select',
      '#title' => $this->t('During fire suppression activities, how often did you wear SCBA?'),
      '#options' => [
        '' => $this->t('- Select -'),
        'always' => $this->t('Always (100%)'),
        'usually' => $this->t('Usually (75-99%)'),
        'sometimes' => $this->t('Sometimes (25-74%)'),
        'rarely' => $this->t('Rarely (<25%)'),
        'never' => $this->t('Never'),
      ],
      '#default_value' => $ppe['scba_during_suppression'] ?? '',
    ];

    $form['ppe']['scba_during_overhaul'] = [
      '#type' => 'select',
      '#title' => $this->t('During overhaul operations, how often did you wear SCBA?'),
      '#options' => [
        '' => $this->t('- Select -'),
        'always' => $this->t('Always (100%)'),
        'usually' => $this->t('Usually (75-99%)'),
        'sometimes' => $this->t('Sometimes (25-74%)'),
        'rarely' => $this->t('Rarely (<25%)'),
        'never' => $this->t('Never'),
      ],
      '#default_value' => $ppe['scba_during_overhaul'] ?? '',
    ];
  }

  /**
   * Build decontamination practices section.
   */
  private function buildDecontaminationSection(array &$form, FormStateInterface $form_state, ?array $existing): void {
    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 7: Decontamination Practices</h2><p>These questions are about cleaning practices after fires or other exposures.</p>',
    ];

    $decon = $existing['decontamination'] ?? [];

    $form['decontamination'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Decontamination Practices'),
    ];

    $form['decontamination']['intro'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('After fire suppression or other emergency operations, how often did you do the following?') . '</p>',
    ];

    $practices = [
      'washed_hands_face' => 'Washed hands and face at scene',
      'changed_gear_at_scene' => 'Changed out of contaminated gear at scene',
      'showered_at_station' => 'Showered soon after returning to station',
      'laundered_gear' => 'Laundered turnout gear regularly',
      'used_wet_wipes' => 'Used wet wipes to clean skin after fire',
    ];

    $frequency_options = [
      '' => $this->t('- Select -'),
      'always' => $this->t('Always'),
      'usually' => $this->t('Usually'),
      'sometimes' => $this->t('Sometimes'),
      'rarely' => $this->t('Rarely'),
      'never' => $this->t('Never'),
    ];

    foreach ($practices as $practice_key => $practice_label) {
      $form['decontamination'][$practice_key] = [
        '#type' => 'select',
        '#title' => $this->t($practice_label),
        '#options' => $frequency_options,
        '#default_value' => $decon[$practice_key] ?? '',
      ];
    }

    $form['decontamination']['department_had_sops'] = [
      '#type' => 'radios',
      '#title' => $this->t('Did your department have decontamination SOPs/SOGs?'),
      '#required' => TRUE,
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
        'unknown' => $this->t("Don't Know"),
      ],
      '#default_value' => $decon['department_had_sops'] ?? '',
    ];

    $form['decontamination']['sops_year_implemented'] = [
      '#type' => 'number',
      '#title' => $this->t('In what year were they implemented?'),
      '#min' => 1950,
      '#max' => (int) date('Y'),
      '#default_value' => $decon['sops_year_implemented'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="decontamination[department_had_sops]"]' => ['value' => 'yes'],
        ],
      ],
    ];
  }

  /**
   * Build health information section.
   */
  private function buildHealthSection(array &$form, FormStateInterface $form_state, ?array $existing): void {
    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 8: Health Information</h2><p>These questions help us understand health outcomes. All information is confidential.</p>',
    ];

    $health = $existing['health'] ?? [];

    $form['health'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Health Information'),
    ];

    $form['health']['cancer_diagnosed'] = [
      '#type' => 'radios',
      '#title' => $this->t('Have you ever been diagnosed with cancer?'),
      '#required' => TRUE,
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => $health['cancer_diagnosed'] ?? '',
    ];

    // Cancer diagnoses repeating fields
    $num_cancers = $form_state->get('num_cancers') ?? count($health['cancers'] ?? []) ?: 0;

    $form['health']['cancers'] = [
      '#type' => 'container',
      '#prefix' => '<div id="cancers-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="health[cancer_diagnosed]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    $form['health']['add_cancer'] = [
      '#type' => 'submit',
      '#value' => $this->t('+ Add Another Cancer Diagnosis'),
      '#submit' => ['::addCancer'],
      '#ajax' => [
        'callback' => '::updateCancerFields',
        'wrapper' => 'cancers-wrapper',
      ],
      '#limit_validation_errors' => [],
      '#states' => [
        'visible' => [
          ':input[name="health[cancer_diagnosed]"]' => ['value' => 'yes'],
        ],
      ],
    ];

    for ($i = 0; $i < $num_cancers; $i++) {
      $cancer_data = $health['cancers'][$i] ?? [];
      
      $form['health']['cancers'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Cancer Diagnosis @num', ['@num' => $i + 1]),
      ];

      $form['health']['cancers'][$i]['type'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Type of Cancer'),
        '#default_value' => $cancer_data['type'] ?? '',
        '#description' => $this->t('e.g., Bladder, Lung, Melanoma, Prostate, etc.'),
      ];

      $form['health']['cancers'][$i]['year_diagnosed'] = [
        '#type' => 'number',
        '#title' => $this->t('Year of Diagnosis'),
        '#min' => 1950,
        '#max' => (int) date('Y'),
        '#default_value' => $cancer_data['year_diagnosed'] ?? '',
      ];
    }

    $form['health']['other_conditions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Have you been diagnosed with any of these conditions?'),
      '#options' => [
        'heart_disease' => $this->t('Heart disease'),
        'copd' => $this->t('COPD/Chronic bronchitis'),
        'asthma' => $this->t('Asthma'),
        'diabetes' => $this->t('Diabetes'),
        'none' => $this->t('None of the above'),
      ],
      '#default_value' => $health['other_conditions'] ?? [],
    ];
  }

  /**
   * Build lifestyle factors section.
   */
  private function buildLifestyleSection(array &$form, FormStateInterface $form_state, ?array $existing): void {
    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 9: Lifestyle Factors</h2><p>These questions help us account for other factors that may affect health.</p>',
    ];

    $lifestyle = $existing['lifestyle'] ?? [];

    $form['lifestyle'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Lifestyle Factors'),
    ];

    $form['lifestyle']['smoking_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Have you ever smoked cigarettes?'),
      '#required' => TRUE,
      '#options' => [
        'never' => $this->t('Never'),
        'former' => $this->t('Former smoker'),
        'current' => $this->t('Current smoker'),
      ],
      '#default_value' => $lifestyle['smoking_status'] ?? '',
    ];

    $form['lifestyle']['smoking_age_started'] = [
      '#type' => 'number',
      '#title' => $this->t('Age started smoking'),
      '#min' => 1,
      '#max' => 100,
      '#default_value' => $lifestyle['smoking_age_started'] ?? '',
      '#states' => [
        'visible' => [
          [':input[name="lifestyle[smoking_status]"]' => ['value' => 'former']],
          'or',
          [':input[name="lifestyle[smoking_status]"]' => ['value' => 'current']],
        ],
      ],
    ];

    $form['lifestyle']['smoking_age_stopped'] = [
      '#type' => 'number',
      '#title' => $this->t('Age stopped smoking'),
      '#min' => 1,
      '#max' => 100,
      '#default_value' => $lifestyle['smoking_age_stopped'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="lifestyle[smoking_status]"]' => ['value' => 'former'],
        ],
      ],
    ];

    $form['lifestyle']['cigarettes_per_day'] = [
      '#type' => 'select',
      '#title' => $this->t('Cigarettes per day'),
      '#options' => [
        '' => $this->t('- Select -'),
        'less_half_pack' => $this->t('Less than 1/2 pack (< 10)'),
        'half_to_one_pack' => $this->t('1/2 to 1 pack (10-20)'),
        'one_to_two_packs' => $this->t('1 to 2 packs (20-40)'),
        'more_than_two_packs' => $this->t('More than 2 packs (> 40)'),
      ],
      '#default_value' => $lifestyle['cigarettes_per_day'] ?? '',
      '#states' => [
        'visible' => [
          [':input[name="lifestyle[smoking_status]"]' => ['value' => 'former']],
          'or',
          [':input[name="lifestyle[smoking_status]"]' => ['value' => 'current']],
        ],
      ],
    ];

    $form['lifestyle']['alcohol_frequency'] = [
      '#type' => 'select',
      '#title' => $this->t('How often do you drink alcoholic beverages?'),
      '#required' => TRUE,
      '#options' => [
        '' => $this->t('- Select -'),
        'never' => $this->t('Never'),
        'less_than_monthly' => $this->t('Less than once a month'),
        '1_3_per_month' => $this->t('1-3 times per month'),
        '1_2_per_week' => $this->t('1-2 times per week'),
        '3_4_per_week' => $this->t('3-4 times per week'),
        '5_plus_per_week' => $this->t('5+ times per week'),
      ],
      '#default_value' => $lifestyle['alcohol_frequency'] ?? '',
    ];

    $form['lifestyle']['physical_activity_days'] = [
      '#type' => 'number',
      '#title' => $this->t('On average, how many days per week do you engage in moderate or vigorous physical activity for at least 30 minutes?'),
      '#required' => TRUE,
      '#min' => 0,
      '#max' => 7,
      '#default_value' => $lifestyle['physical_activity_days'] ?? '',
      '#description' => $this->t('0-7 days'),
    ];
  }

  /**
   * Check if questionnaire is complete.
   */
  private function isQuestionnaireComplete(): bool {
    $uid = (int) $this->currentUser->id();
    
    $result = $this->database->select('nfr_questionnaire', 'q')
      ->fields('q', ['questionnaire_completed'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchField();
    
    return (bool) $result;
  }

  /**
   * Get last completed section for user.
   */
  private function getLastCompletedSection(int $uid): int {
    $result = $this->database->select('nfr_questionnaire', 'q')
      ->fields('q', ['last_section_completed'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchField();
    
    return $result !== FALSE ? (int) $result : 0;
  }

  /**
   * Update last completed section.
   */
  private function updateLastCompletedSection(int $section): void {
    $uid = (int) $this->currentUser->id();
    
    // Only update if this section is greater than current last_section_completed
    $current_last = $this->getLastCompletedSection($uid);
    
    if ($section > $current_last) {
      $this->database->update('nfr_questionnaire')
        ->fields(['last_section_completed' => $section])
        ->condition('uid', $uid)
        ->execute();
    }
  }

  /**
   * Load existing questionnaire data.
   */
  /**
   * Load existing questionnaire data.
   */
  private function loadQuestionnaire(int $uid): ?array {
    $query = $this->database->select('nfr_questionnaire', 'q');
    $query->fields('q');
    $query->condition('uid', $uid);
    $result = $query->execute()->fetchAssoc();

    if (!$result) {
      return NULL;
    }

    // Map database columns to form structure
    $smoking_history = json_decode($result['smoking_history'] ?? '{}', TRUE);
    
    return [
      'demographics' => [
        'race_ethnicity' => json_decode($result['race_ethnicity'] ?? '{}', TRUE),
        'race_other' => $result['race_other'] ?? '',
        'education_level' => $result['education_level'] ?? '',
        'marital_status' => $result['marital_status'] ?? '',
      ],
      'work_history' => [], // Loaded from nfr_work_history table separately
      'exposure' => json_decode($result['exposure_data'] ?? '{}', TRUE),
      'military' => [
        'served' => $result['military_service'] ? 'yes' : 'no',
        'branch' => $result['military_branch'] ?? '',
        'start_date' => '', // Would need separate date columns
        'end_date' => '',
        'currently_serving' => FALSE,
        'was_firefighter' => '',
        'firefighting_duties' => '',
      ],
      'other_employment' => json_decode($result['other_employment_data'] ?? '{}', TRUE),
      'ppe' => json_decode($result['ppe_practices'] ?? '{}', TRUE),
      'decontamination' => json_decode($result['decon_practices'] ?? '{}', TRUE),
      'health' => [
        'cancer_diagnosed' => $result['cancer_diagnosis'] ? 'yes' : 'no',
        'cancers' => json_decode($result['cancer_details'] ?? '[]', TRUE),
        'other_conditions' => json_decode($result['family_cancer_history'] ?? '[]', TRUE),
      ],
      'lifestyle' => [
        'smoking_status' => $smoking_history['status'] ?? '',
        'smoking_age_started' => $smoking_history['age_started'] ?? '',
        'smoking_age_stopped' => $smoking_history['age_stopped'] ?? '',
        'cigarettes_per_day' => $smoking_history['cigarettes_per_day'] ?? '',
        'alcohol_frequency' => $result['alcohol_use'] ?? '',
        'physical_activity_days' => '',  // Not in current schema
      ],
    ];
  }

  /**
   * Get state options.
   */
  private function getStateOptions(): array {
    return [
      'AL' => 'Alabama',
      'AK' => 'Alaska',
      'AZ' => 'Arizona',
      'AR' => 'Arkansas',
      'CA' => 'California',
      'CO' => 'Colorado',
      'CT' => 'Connecticut',
      'DE' => 'Delaware',
      'DC' => 'District of Columbia',
      'FL' => 'Florida',
      'GA' => 'Georgia',
      'HI' => 'Hawaii',
      'ID' => 'Idaho',
      'IL' => 'Illinois',
      'IN' => 'Indiana',
      'IA' => 'Iowa',
      'KS' => 'Kansas',
      'KY' => 'Kentucky',
      'LA' => 'Louisiana',
      'ME' => 'Maine',
      'MD' => 'Maryland',
      'MA' => 'Massachusetts',
      'MI' => 'Michigan',
      'MN' => 'Minnesota',
      'MS' => 'Mississippi',
      'MO' => 'Missouri',
      'MT' => 'Montana',
      'NE' => 'Nebraska',
      'NV' => 'Nevada',
      'NH' => 'New Hampshire',
      'NJ' => 'New Jersey',
      'NM' => 'New Mexico',
      'NY' => 'New York',
      'NC' => 'North Carolina',
      'ND' => 'North Dakota',
      'OH' => 'Ohio',
      'OK' => 'Oklahoma',
      'OR' => 'Oregon',
      'PA' => 'Pennsylvania',
      'PR' => 'Puerto Rico',
      'RI' => 'Rhode Island',
      'SC' => 'South Carolina',
      'SD' => 'South Dakota',
      'TN' => 'Tennessee',
      'TX' => 'Texas',
      'UT' => 'Utah',
      'VT' => 'Vermont',
      'VI' => 'Virgin Islands',
      'VA' => 'Virginia',
      'WA' => 'Washington',
      'WV' => 'West Virginia',
      'WI' => 'Wisconsin',
      'WY' => 'Wyoming',
      'GU' => 'Guam',
    ];
  }

  /**
   * AJAX callback for department fields.
   */
  public function updateDepartmentFields(array &$form, FormStateInterface $form_state): array {
    return $form['work_history']['departments'];
  }

  /**
   * AJAX callback for job fields.
   */
  public function updateJobFields(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $dept_index = $triggering_element['#parents'][2];
    return $form['work_history']['departments'][$dept_index]['jobs'];
  }

  /**
   * AJAX callback for incident fields.
   */
  public function updateIncidentFields(array &$form, FormStateInterface $form_state): array {
    return $form['exposure']['incidents_wrapper'];
  }

  /**
   * Add incident submit handler.
   */
  public function addIncident(array &$form, FormStateInterface $form_state): void {
    $num_incidents = $form_state->get('num_major_incidents') ?? 0;
    $form_state->set('num_major_incidents', $num_incidents + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for other job fields.
   */
  public function updateOtherJobFields(array &$form, FormStateInterface $form_state): array {
    return $form['other_employment']['jobs'];
  }

  /**
   * Add other job submit handler.
   */
  public function addOtherJob(array &$form, FormStateInterface $form_state): void {
    $num_jobs = $form_state->get('num_other_jobs') ?? 0;
    $form_state->set('num_other_jobs', $num_jobs + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for cancer fields.
   */
  public function updateCancerFields(array &$form, FormStateInterface $form_state): array {
    return $form['health']['cancers'];
  }

  /**
   * Add cancer submit handler.
   */
  public function addCancer(array &$form, FormStateInterface $form_state): void {
    $num_cancers = $form_state->get('num_cancers') ?? 0;
    $form_state->set('num_cancers', $num_cancers + 1);
    $form_state->setRebuild();
  }

  /**
   * Jump to section submit handler.
   */
  public function jumpToSection(array &$form, FormStateInterface $form_state): void {
    $current_section = $form_state->get('current_section');
    
    // Save current section data before jumping
    $this->saveCurrentSection($form_state, $current_section);
    
    // Update last completed section
    $this->updateLastCompletedSection($current_section);
    
    // Get target section from button
    $triggering_element = $form_state->getTriggeringElement();
    $target_section = $triggering_element['#section_number'];
    
    // Jump to target section
    $form_state->set('current_section', $target_section);
    $form_state->setRebuild();
  }

  /**
   * Previous button submit handler.
   */
  public function previousSubmit(array &$form, FormStateInterface $form_state): void {
    $current_section = $form_state->get('current_section');
    
    // Save current section data before going back
    $this->saveCurrentSection($form_state, $current_section);
    
    // Update last completed section if moving forward in progress
    $this->updateLastCompletedSection($current_section);
    
    // Move to previous section
    $form_state->set('current_section', $current_section - 1);
    $form_state->setRebuild();
  }

  /**
   * Next button submit handler.
   */
  public function nextSubmit(array &$form, FormStateInterface $form_state): void {
    $current_section = $form_state->get('current_section');
    
    // Save current section data
    $this->saveCurrentSection($form_state, $current_section);
    
    // Update last completed section
    $this->updateLastCompletedSection($current_section);
    
    // Move to next section
    $form_state->set('current_section', $current_section + 1);
    $form_state->setRebuild();
  }

  /**
   * Save and exit submit handler.
   */
  public function saveAndExit(array &$form, FormStateInterface $form_state): void {
    $current_section = $form_state->get('current_section');
    $this->saveCurrentSection($form_state, $current_section);
    $this->updateLastCompletedSection($current_section);
    
    $is_complete = $this->isQuestionnaireComplete();
    
    if ($is_complete) {
      $this->messenger()->addStatus($this->t('Questionnaire saved. You can return anytime to review or update your responses.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Progress saved at Section @section. You can continue later from your dashboard.', ['@section' => $current_section]));
    }
    
    $form_state->setRedirect('nfr.my_dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Section-specific validation can go here
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $current_section = $form_state->get('current_section');
    $this->saveCurrentSection($form_state, $current_section);
    
    // Mark questionnaire as complete
    $this->markQuestionnaireComplete();
    
    $this->messenger()->addStatus($this->t('Questionnaire complete! Please review your responses before final submission.'));
    $form_state->setRedirect('nfr.review');
  }

  /**
   * Save current section data.
   */
  private function saveCurrentSection(FormStateInterface $form_state, int $section): void {
    $uid = (int) $this->currentUser->id();
    $values = $form_state->getValues();

    // Check if record exists
    $exists = $this->database->select('nfr_questionnaire', 'q')
      ->fields('q', ['id'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchField();

    // Build field data based on section
    $field_data = $this->prepareSectionData($section, $values);
    
    if (empty($field_data)) {
      return;
    }

    $field_data['updated'] = time();

    if ($exists) {
      // Update existing record
      $this->database->update('nfr_questionnaire')
        ->fields($field_data)
        ->condition('uid', $uid)
        ->execute();
    }
    else {
      // Insert new record
      $field_data['uid'] = $uid;
      $field_data['questionnaire_completed'] = 0;
      $field_data['created'] = time();
      
      $this->database->insert('nfr_questionnaire')
        ->fields($field_data)
        ->execute();
    }
  }

  /**
   * Prepare section data for database storage.
   */
  private function prepareSectionData(int $section, array $values): array {
    $field_data = [];
    
    switch ($section) {
      case 1: // Demographics
        if (isset($values['demographics'])) {
          $demo = $values['demographics'];
          if (isset($demo['race_ethnicity'])) {
            $field_data['race_ethnicity'] = json_encode(array_filter($demo['race_ethnicity']));
          }
          if (isset($demo['race_other'])) {
            $field_data['race_other'] = $demo['race_other'];
          }
          if (isset($demo['education_level'])) {
            $field_data['education_level'] = $demo['education_level'];
          }
          if (isset($demo['marital_status'])) {
            $field_data['marital_status'] = $demo['marital_status'];
          }
        }
        break;

      case 2: // Work History
        // Work history stored in separate nfr_work_history table
        // This section doesn't update nfr_questionnaire
        break;

      case 3: // Exposure
        // Store exposure data as JSON for complex nested structure
        if (isset($values['exposure'])) {
          // Exposure section has complex nested data (AFFF, diesel, incidents)
          // Store in JSON column when added to schema
          // TODO: Add exposure_data column to nfr_questionnaire schema
        }
        break;

      case 4: // Military Service
        if (isset($values['military'])) {
          $military = $values['military'];
          // Check if 'served' is 'yes' (radio value)
          if (isset($military['served'])) {
            $field_data['military_service'] = ($military['served'] === 'yes') ? 1 : 0;
          }
          if (isset($military['branch']) && $military['served'] === 'yes') {
            $field_data['military_branch'] = $military['branch'];
          }
          // Calculate years from dates if provided
          if (isset($military['start_date']) && isset($military['end_date'])) {
            $start = new \DateTime($military['start_date']);
            $end = new \DateTime($military['end_date']);
            $field_data['military_years'] = $end->diff($start)->y;
          }
        }
        break;

      case 5: // Other Employment
        if (isset($values['other_employment'])) {
          $field_data['other_employment_data'] = json_encode($values['other_employment']);
        }
        break;

      case 6: // PPE Practices
        if (isset($values['ppe'])) {
          $field_data['ppe_practices'] = json_encode($values['ppe']);
        }
        break;

      case 7: // Decontamination
        if (isset($values['decontamination'])) {
          $field_data['decon_practices'] = json_encode($values['decontamination']);
        }
        break;

      case 8: // Health
        if (isset($values['health'])) {
          $health = $values['health'];
          // Field name is 'cancer_diagnosed' in form, 'cancer_diagnosis' in DB
          if (isset($health['cancer_diagnosed'])) {
            $field_data['cancer_diagnosis'] = ($health['cancer_diagnosed'] === 'yes') ? 1 : 0;
          }
          // Cancer details from repeating 'cancers' array
          if (isset($health['cancers'])) {
            $field_data['cancer_details'] = json_encode($health['cancers']);
          }
          // Other conditions as family history
          if (isset($health['other_conditions'])) {
            $field_data['family_cancer_history'] = json_encode($health['other_conditions']);
          }
        }
        break;

      case 9: // Lifestyle
        if (isset($values['lifestyle'])) {
          $lifestyle = $values['lifestyle'];
          // Build smoking history object from individual fields
          $smoking_data = [
            'status' => $lifestyle['smoking_status'] ?? '',
            'age_started' => $lifestyle['smoking_age_started'] ?? null,
            'age_stopped' => $lifestyle['smoking_age_stopped'] ?? null,
            'cigarettes_per_day' => $lifestyle['cigarettes_per_day'] ?? '',
          ];
          $field_data['smoking_history'] = json_encode($smoking_data);
          
          // Alcohol frequency
          if (isset($lifestyle['alcohol_frequency'])) {
            $field_data['alcohol_use'] = $lifestyle['alcohol_frequency'];
          }
        }
        break;
    }
    
    return $field_data;
  }

  /**
   * Get database column name for field.
   */
  private function getFieldColumnName(string $field_name): string {
    $map = [
      'demographics' => 'demographics',
      'work_history' => 'work_history',
      'exposure' => 'exposure',
      'military' => 'military_service',
      'other_employment' => 'other_employment',
      'ppe' => 'ppe_practices',
      'decontamination' => 'decon_practices',
      'health' => 'health_information',
      'lifestyle' => 'lifestyle_factors',
    ];
    return $map[$field_name] ?? $field_name;
  }

  /**
   * Mark questionnaire as complete.
   */
  private function markQuestionnaireComplete(): void {
    $uid = (int) $this->currentUser->id();
    
    $this->database->update('nfr_questionnaire')
      ->fields([
        'questionnaire_completed' => 1,
        'completed_date' => time(),
        'updated' => time(),
      ])
      ->condition('uid', $uid)
      ->execute();
  }

}
