<?php

declare(strict_types=1);

namespace Drupal\nfr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * NFR Questionnaire Section 8: Health Information.
 */
class NFRQuestionnaireSection8Form extends FormBase {

  use QuestionnaireFormTrait;

  /**
   * Constructs a new NFRQuestionnaireSection8Form.
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
    return 'nfr_questionnaire_section8_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'nfr/enrollment';

    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);
    $health = $existing['health'] ?? [];

    // Add navigation menu
    $form['navigation'] = $this->buildNavigationMenu(8);

    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 8: Health Information</h2><p>These questions help us understand health outcomes. All information is confidential.</p>',
    ];

    $form['health'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Health Information'),
      '#tree' => TRUE,
    ];

    $form['health']['cancer_diagnosed'] = [
      '#type' => 'radios',
      '#title' => $this->t('Have you ever been diagnosed with cancer?'),
      '#required' => TRUE,
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => $health['cancer_diagnosed'] ?? NULL,
      '#ajax' => [
        'callback' => '::updateCancerFields',
        'wrapper' => 'cancers-wrapper',
      ],
    ];

    // Cancer diagnoses repeating fields
    $num_cancers = $form_state->get('num_cancers') ?? count($health['cancers'] ?? []);
    
    // If user selected "yes" to cancer but has no cancers yet, default to 1
    $cancer_diagnosed = $form_state->getValue(['health', 'cancer_diagnosed']) ?? $health['cancer_diagnosed'] ?? NULL;
    if ($cancer_diagnosed === 'yes' && $num_cancers === 0) {
      $num_cancers = 1;
    }
    
    // Store the current count in form_state so addCancer can increment from the right number
    $form_state->set('num_cancers', $num_cancers);

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
      '#value' => $this->t('Continue →'),
    ];

    return $form;
  }

  /**
   * AJAX callback to update cancer fields.
   */
  public function updateCancerFields(array &$form, FormStateInterface $form_state): array {
    return $form['health']['cancers'];
  }

  /**
   * Submit handler to add another cancer diagnosis.
   */
  public function addCancer(array &$form, FormStateInterface $form_state): void {
    $num_cancers = $form_state->get('num_cancers') ?? 0;
    $form_state->set('num_cancers', $num_cancers + 1);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);

    $existing['health'] = $form_state->getValue('health');
    $existing['section_completion'][8] = TRUE;
    $this->saveData($uid, $existing);

    // Update progress
    $database = $this->getDatabase();
    $database->update('nfr_questionnaire')
      ->fields(['last_section_completed' => 8])
      ->condition('uid', $uid)
      ->execute();

    $form_state->setRedirect('nfr.questionnaire.section9');
  }

  /**
   * Submit handler for previous button.
   */
  public function previousSection(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('nfr.questionnaire.section7');
  }

  /**
   * Submit handler for save and exit button.
   */
  public function saveAndExit(array &$form, FormStateInterface $form_state): void {
    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);

    $existing['health'] = $form_state->getValue('health');
    $this->saveData($uid, $existing);

    $this->messenger()->addStatus($this->t('Your progress has been saved.'));
    $form_state->setRedirect('nfr.dashboard');
  }

}
