<?php

declare(strict_types=1);

namespace Drupal\nfr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Section 4: Military Service.
 */
class NFRQuestionnaireSection4Form extends FormBase {

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
    return 'nfr_questionnaire_section_4';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = (int) $this->currentUser->id();
    $existing = $this->loadData($uid);
    
    $form['#tree'] = TRUE;
    
    $form['navigation'] = $this->buildNavigationMenu(4);

    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 4: Military Service</h2>',
    ];

    $military = $existing['military'] ?? [];

    $form['military'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Military Service'),
      '#tree' => TRUE,
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
      '#value' => $this->t('Save & Continue to Section 5 →'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  public function previousSection(array &$form, FormStateInterface $form_state): void {
    $this->saveSection($form_state);
    $form_state->setRedirect('nfr.questionnaire.section3');
  }

  public function saveAndExit(array &$form, FormStateInterface $form_state): void {
    $this->saveSection($form_state);
    $this->messenger()->addStatus($this->t('Military service information saved.'));
    $form_state->setRedirect('nfr.my_dashboard');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->saveSection($form_state);
    
    // Mark section as completed
    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);
    $existing['section_completion'][4] = TRUE;
    $this->saveData($uid, $existing);
    
    // Update progress
    $database = $this->getDatabase();
    $database->update('nfr_questionnaire')
      ->fields(['last_section_completed' => 4])
      ->condition('uid', $uid)
      ->execute();
    
    $this->messenger()->addStatus($this->t('Section 4 saved.'));
    $form_state->setRedirect('nfr.questionnaire.section5');
  }

  private function saveSection(FormStateInterface $form_state): void {
    $uid = (int) $this->currentUser->id();
    $existing = $this->loadData($uid);
    $existing['military'] = $form_state->getValue('military');
    $this->saveData($uid, $existing);
  }

}
