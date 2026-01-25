<?php

declare(strict_types=1);

namespace Drupal\nfr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * NFR Questionnaire Section 9: Lifestyle Factors.
 */
class NFRQuestionnaireSection9Form extends FormBase {

  use QuestionnaireFormTrait;

  /**
   * Constructs a new NFRQuestionnaireSection9Form.
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
    return 'nfr_questionnaire_section9_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'nfr/enrollment';

    $uid = $this->getCurrentUserId();
    
    // Load lifestyle data from database columns
    $database = $this->getDatabase();
    $questionnaire = $database->select('nfr_questionnaire', 'q')
      ->fields('q', ['smoking_history', 'alcohol_use'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc();
    
    $lifestyle = [];
    if ($questionnaire) {
      if ($questionnaire['smoking_history']) {
        $smoking = json_decode($questionnaire['smoking_history'], TRUE) ?? [];
        $lifestyle = array_merge($lifestyle, $smoking);
      }
      $lifestyle['alcohol_frequency'] = $questionnaire['alcohol_use'] ?? '';
    }

    // Add navigation menu
    $form['navigation'] = $this->buildNavigationMenu(9);

    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 9: Lifestyle Factors</h2><p>These questions help us account for other factors that may affect health.</p>',
    ];

    $form['lifestyle'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Lifestyle Factors'),
      '#tree' => TRUE,
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
      '#default_value' => $lifestyle['smoking_status'] ?? NULL,
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

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['previous'] = [
      '#type' => 'submit',
      '#value' => $this->t('← Previous'),
      '#submit' => ['::previousSection'],
      '#limit_validation_errors' => [],
    ];

    $form['actions']['save_exit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save & Exit'),
      '#submit' => ['::saveAndExit'],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Complete Questionnaire →'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = $this->getCurrentUserId();
    $lifestyle = $form_state->getValue('lifestyle');

    // Separate smoking data from alcohol data
    $smoking_data = [
      'smoking_status' => $lifestyle['smoking_status'] ?? '',
      'smoking_age_started' => $lifestyle['smoking_age_started'] ?? '',
      'smoking_age_stopped' => $lifestyle['smoking_age_stopped'] ?? '',
      'cigarettes_per_day' => $lifestyle['cigarettes_per_day'] ?? '',
    ];

    // Save lifestyle data to database columns
    $database = $this->getDatabase();
    $database->update('nfr_questionnaire')
      ->fields([
        'smoking_history' => json_encode($smoking_data),
        'alcohol_use' => $lifestyle['alcohol_frequency'] ?? NULL,
        'last_section_completed' => 9,
        'questionnaire_completed' => 1,
      ])
      ->condition('uid', $uid)
      ->execute();

    $this->messenger()->addStatus($this->t('Congratulations! You have completed the questionnaire.'));
    $form_state->setRedirect('nfr.review_submit');
  }

  /**
   * Submit handler for previous button.
   */
  public function previousSection(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('nfr.questionnaire.section8');
  }

  /**
   * Submit handler for save and exit button.
   */
  public function saveAndExit(array &$form, FormStateInterface $form_state): void {
    $uid = $this->getCurrentUserId();
    $lifestyle = $form_state->getValue('lifestyle');

    // Separate smoking data from alcohol data
    $smoking_data = [
      'smoking_status' => $lifestyle['smoking_status'] ?? '',
      'smoking_age_started' => $lifestyle['smoking_age_started'] ?? '',
      'smoking_age_stopped' => $lifestyle['smoking_age_stopped'] ?? '',
      'cigarettes_per_day' => $lifestyle['cigarettes_per_day'] ?? '',
    ];

    // Save lifestyle data to database columns
    $database = $this->getDatabase();
    $database->update('nfr_questionnaire')
      ->fields([
        'smoking_history' => json_encode($smoking_data),
        'alcohol_use' => $lifestyle['alcohol_frequency'] ?? NULL,
      ])
      ->condition('uid', $uid)
      ->execute();

    $this->messenger()->addStatus($this->t('Your progress has been saved.'));
    $form_state->setRedirect('nfr.dashboard');
  }

}
