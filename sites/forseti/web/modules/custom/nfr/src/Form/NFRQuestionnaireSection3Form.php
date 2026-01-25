<?php

declare(strict_types=1);

namespace Drupal\nfr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Section 3: Exposure Information.
 */
class NFRQuestionnaireSection3Form extends FormBase {

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
    return 'nfr_questionnaire_section_3';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);
    
    $form['#tree'] = TRUE;
    
    // Add navigation menu
    $form['navigation'] = $this->buildNavigationMenu(3);

    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 3: Exposure Information</h2><p>These questions help us understand your exposures to substances that may affect firefighter health.</p>',
    ];

    $exposure = $existing['exposure'] ?? [];

    $form['exposure'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Exposure Information'),
      '#tree' => TRUE,
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

    // Add incident button at the bottom, after all incidents
    $form['exposure']['incidents_wrapper']['add_incident'] = [
      '#type' => 'submit',
      '#value' => $this->t('+ Add Incident'),
      '#submit' => ['::addIncident'],
      '#ajax' => [
        'callback' => '::updateIncidentFields',
        'wrapper' => 'incidents-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

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
      '#value' => $this->t('Save & Continue to Section 4 →'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
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
   * Previous section handler.
   */
  public function previousSection(array &$form, FormStateInterface $form_state): void {
    $this->saveSection($form_state);
    $form_state->setRedirect('nfr.questionnaire.section2');
  }

  /**
   * Save and exit handler.
   */
  public function saveAndExit(array &$form, FormStateInterface $form_state): void {
    $this->saveSection($form_state);
    $this->messenger()->addStatus($this->t('Exposure information saved. You can continue later from your dashboard.'));
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
    $existing['section_completion'][3] = TRUE;
    $this->saveData($uid, $existing);
    
    // Update progress
    $database = $this->getDatabase();
    $database->update('nfr_questionnaire')
      ->fields(['last_section_completed' => 3])
      ->condition('uid', $uid)
      ->execute();
    
    $this->messenger()->addStatus($this->t('Section 3 saved.'));
    $form_state->setRedirect('nfr.questionnaire.section4');
  }

  /**
   * Save section data.
   */
  private function saveSection(FormStateInterface $form_state): void {
    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);
    $existing['exposure'] = $form_state->getValue('exposure');
    $this->saveData($uid, $existing);
  }

}
