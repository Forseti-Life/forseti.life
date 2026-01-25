<?php

declare(strict_types=1);

namespace Drupal\nfr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Section 1: Demographics.
 */
class NFRQuestionnaireSection1Form extends FormBase {

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
    return 'nfr_questionnaire_section_1';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = (int) $this->currentUser->id();
    
    // Load demographics from database columns
    $database = $this->getDatabase();
    $questionnaire = $database->select('nfr_questionnaire', 'q')
      ->fields('q', ['race_ethnicity', 'race_other', 'education_level', 'marital_status'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc();
    
    $demographics = [];
    if ($questionnaire) {
      $demographics['race_ethnicity'] = $questionnaire['race_ethnicity'] ? json_decode($questionnaire['race_ethnicity'], TRUE) : [];
      $demographics['race_other'] = $questionnaire['race_other'] ?? '';
      $demographics['education_level'] = $questionnaire['education_level'] ?? '';
      $demographics['marital_status'] = $questionnaire['marital_status'] ?? '';
    }

    // Add navigation menu
    $form['navigation'] = $this->buildNavigationMenu(1);

    $form['section_title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>Section 1: Demographics</h2>',
    ];

    $form['demographics'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Demographics'),
      '#tree' => TRUE,
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

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save & Continue to Section 2'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = $this->getCurrentUserId();
    $demographics = $form_state->getValue('demographics');

    // Prepare race_ethnicity as JSON array (only checked values)
    $race_values = array_filter($demographics['race_ethnicity']);
    $race_json = !empty($race_values) ? json_encode(array_values($race_values)) : NULL;

    // Save demographics to specific columns
    $database = $this->getDatabase();
    $database->update('nfr_questionnaire')
      ->fields([
        'race_ethnicity' => $race_json,
        'race_other' => $demographics['race_other'] ?: NULL,
        'education_level' => $demographics['education_level'] ?: NULL,
        'marital_status' => $demographics['marital_status'] ?: NULL,
        'last_section_completed' => 1,
      ])
      ->condition('uid', $uid)
      ->execute();

    $this->messenger()->addStatus($this->t('Section 1 saved successfully.'));
    $form_state->setRedirect('nfr.questionnaire.section2');
  }

}
