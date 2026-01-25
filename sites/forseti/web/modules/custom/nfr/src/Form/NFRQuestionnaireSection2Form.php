<?php

declare(strict_types=1);

namespace Drupal\nfr\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Section 2: Work History.
 */
class NFRQuestionnaireSection2Form extends FormBase {

  use QuestionnaireFormTrait;

  public function __construct(
    private readonly Connection $database,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('current_user'),
    );
  }

  public function getFormId(): string {
    return 'nfr_questionnaire_section_2';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);
    
    $form['#tree'] = TRUE;
    
    // Add navigation menu
    $form['navigation'] = $this->buildNavigationMenu(2);

    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 2: Work History</h2><p>Please tell us about your entire firefighting career, including all departments where you worked.</p>',
    ];

    $work_history = $existing['work_history'] ?? [];
    
    // Merge current form values with saved data to preserve user input during AJAX
    if ($form_state->hasValue('work_history')) {
      $current_values = $form_state->getValue('work_history');
      $work_history = array_replace_recursive($work_history, $current_values);
    }
    
    // Handle num_departments - check if just changed via AJAX
    $triggering_element = $form_state->getTriggeringElement();
    if (($triggering_element['#name'] ?? '') == 'work_history[num_departments]') {
      $num_departments = (int) $triggering_element['#value'];
      $form_state->set('num_departments', $num_departments);
    }
    else {
      $num_departments = $form_state->get('num_departments') ?? $work_history['num_departments'] ?? 1;
    }
    
    $form['work_history'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Work History'),
      '#tree' => TRUE,
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
      '#limit_validation_errors' => [],
    ];

    $form['work_history']['departments'] = [
      '#type' => 'container',
      '#prefix' => '<div id="departments-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

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
      // Check if num_jobs was just changed via AJAX for this department
      $triggering_element = $form_state->getTriggeringElement();
      if (($triggering_element['#name'] ?? '') == "work_history[departments][{$i}][num_jobs]") {
        $num_jobs = (int) $triggering_element['#value'];
        $form_state->set("num_jobs_dept_{$i}", $num_jobs);
      }
      else {
        $num_jobs = $form_state->get("num_jobs_dept_{$i}") ?? $dept_data['num_jobs'] ?? 1;
      }

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
        '#limit_validation_errors' => [],
      ];

      $form['work_history']['departments'][$i]['jobs'] = [
        '#type' => 'container',
        '#prefix' => "<div id=\"jobs-wrapper-{$i}\">",
        '#suffix' => '</div>',
        '#tree' => TRUE,
      ];

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
          '#default_value' => $job_data['responded_incidents'] ?? NULL,
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

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['previous'] = [
      '#type' => 'submit',
      '#value' => $this->t('← Previous'),
      '#submit' => ['::previousSection'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];
    $form['actions']['save_exit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save & Exit'),
      '#submit' => ['::saveAndExit'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save & Continue to Section 3 →'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  /**
   * AJAX callback for department fields.
   */
  public function updateDepartmentFields(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#departments-wrapper', $form['work_history']['departments']));
    // Focus first department name field after AJAX completes.
    $response->addCommand(new InvokeCommand('input[name="work_history[departments][0][department_name]"]', 'focus'));
    return $response;
  }

  /**
   * AJAX callback for job fields.
   */
  public function updateJobFields(array &$form, FormStateInterface $form_state): AjaxResponse {
    $triggering_element = $form_state->getTriggeringElement();
    $dept_index = $triggering_element['#parents'][2];
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#jobs-wrapper-' . $dept_index, $form['work_history']['departments'][$dept_index]['jobs']));
    // Focus first job title field after AJAX completes.
    $response->addCommand(new InvokeCommand('input[name="work_history[departments][' . $dept_index . '][jobs][0][title]"]', 'focus'));
    return $response;
  }

  /**
   * Get US state options.
   */
  private function getStateOptions(): array {
    return [
      'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
      'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
      'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
      'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
      'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
      'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
      'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
      'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
      'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
      'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'PR' => 'Puerto Rico',
      'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee',
      'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VI' => 'Virgin Islands',
      'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin',
      'WY' => 'Wyoming', 'GU' => 'Guam',
    ];
  }

  /**
   * Previous section handler.
   */
  public function previousSection(array &$form, FormStateInterface $form_state): void {
    $this->saveSection($form_state);
    $form_state->setRedirect('nfr.questionnaire.section1');
  }

  /**
   * Save and exit handler.
   */
  public function saveAndExit(array &$form, FormStateInterface $form_state): void {
    $this->saveSection($form_state);
    $this->messenger()->addStatus($this->t('Work history saved. You can continue later from your dashboard.'));
    $form_state->setRedirect('nfr.my_dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->saveSection($form_state);
    
    // Mark section as completed
    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);
    $existing['section_completion'][2] = TRUE;
    $this->saveData($uid, $existing);
    
    // Update progress
    $database = $this->getDatabase();
    $database->update('nfr_questionnaire')
      ->fields(['last_section_completed' => 2])
      ->condition('uid', $uid)
      ->execute();
    
    $this->messenger()->addStatus($this->t('Section 2 saved.'));
    $form_state->setRedirect('nfr.questionnaire.section3');
  }

  /**
   * Save section data.
   */
  private function saveSection(FormStateInterface $form_state): void {
    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);
    $existing['work_history'] = $form_state->getValue('work_history');
    $this->saveData($uid, $existing);
  }

}
