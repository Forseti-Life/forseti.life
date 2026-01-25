<?php

declare(strict_types=1);

namespace Drupal\nfr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * NFR Questionnaire Section 6: PPE Practices.
 */
class NFRQuestionnaireSection6Form extends FormBase {

  use QuestionnaireFormTrait;

  /**
   * Constructs a new NFRQuestionnaireSection6Form.
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
    return 'nfr_questionnaire_section6_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'nfr/enrollment';

    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);
    $ppe = $existing['ppe'] ?? [];

    // Add navigation menu
    $form['navigation'] = $this->buildNavigationMenu(6);

    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 6: Personal Protective Equipment (PPE)</h2><p>These questions help us understand the equipment you used and when you started using it.</p>',
    ];

    $form['ppe'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('PPE Practices'),
      '#tree' => TRUE,
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
        '#default_value' => $ppe[$type_key]['ever_used'] ?? NULL,
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);

    $existing['ppe'] = $form_state->getValue('ppe');
    $existing['section_completion'][6] = TRUE;
    $this->saveData($uid, $existing);

    // Update progress
    $database = $this->getDatabase();
    $database->update('nfr_questionnaire')
      ->fields(['last_section_completed' => 6])
      ->condition('uid', $uid)
      ->execute();

    $form_state->setRedirect('nfr.questionnaire.section7');
  }

  /**
   * Submit handler for previous button.
   */
  public function previousSection(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('nfr.questionnaire.section5');
  }

  /**
   * Submit handler for save and exit button.
   */
  public function saveAndExit(array &$form, FormStateInterface $form_state): void {
    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);

    $existing['ppe'] = $form_state->getValue('ppe');
    $this->saveData($uid, $existing);

    $this->messenger()->addStatus($this->t('Your progress has been saved.'));
    $form_state->setRedirect('nfr.dashboard');
  }

}
