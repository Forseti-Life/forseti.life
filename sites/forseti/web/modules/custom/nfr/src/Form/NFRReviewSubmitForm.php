<?php

declare(strict_types=1);

namespace Drupal\nfr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Review and submit enrollment form.
 */
class NFRReviewSubmitForm extends FormBase {

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
    return 'nfr_review_submit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = (int) $this->currentUser->id();
    
    // Load all enrollment data
    $consent_data = $this->loadConsentData($uid);
    $profile_data = $this->loadProfileData($uid) ?? [];
    $questionnaire_data = $this->loadQuestionnaireData($uid);

    $form['#attached']['library'][] = 'nfr/enrollment';

    // Header
    $form['header'] = [
      '#type' => 'markup',
      '#markup' => '<div class="review-header"><h1>' . $this->t('Review Your Responses') . '</h1><p>' . $this->t('Please review your responses before submitting. Click any section to edit.') . '</p></div>',
      '#weight' => -100,
    ];

    // Completeness check
    $incomplete_sections = $this->checkCompleteness($consent_data, $profile_data, $questionnaire_data);
    if (!empty($incomplete_sections)) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => '<div class="review-warning">' . 
          $this->t('Some sections are incomplete. You can still submit, but complete data helps our research.') . 
          '<ul><li>' . implode('</li><li>', $incomplete_sections) . '</li></ul></div>',
        '#weight' => -90,
      ];
    }

    // Consent section
    $form['consent_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✓ Informed Consent'),
      '#open' => FALSE,
    ];

    $form['consent_section']['content'] = [
      '#markup' => $this->renderConsentSummary($consent_data),
    ];

    $form['consent_section']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Consent'),
      '#url' => \Drupal\Core\Url::fromRoute('nfr.consent'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // Profile section
    $form['profile_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✓ User Profile'),
      '#open' => FALSE,
    ];

    $form['profile_section']['content'] = [
      '#markup' => $this->renderProfileSummary($profile_data),
    ];

    $form['profile_section']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Profile'),
      '#url' => \Drupal\Core\Url::fromRoute('nfr.user_profile'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // Demographics section
    $demographics = $questionnaire_data['demographics'] ?? [];
    $form['demographics_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✓ Demographics'),
      '#open' => FALSE,
    ];

    $form['demographics_section']['content'] = [
      '#markup' => $this->renderDemographicsSummary($demographics),
    ];

    $form['demographics_section']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Demographics'),
      '#url' => \Drupal\Core\Url::fromRoute('nfr.enrollment_questionnaire'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // Work History section
    $work_history = $questionnaire_data['work_history'] ?? [];
    $form['work_history_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✓ Work History'),
      '#open' => FALSE,
    ];

    $form['work_history_section']['content'] = [
      '#markup' => $this->renderWorkHistorySummary($work_history),
    ];

    $form['work_history_section']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Work History'),
      '#url' => \Drupal\Core\Url::fromRoute('nfr.enrollment_questionnaire'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // Exposure Information section
    $exposure = $questionnaire_data['exposure'] ?? [];
    $form['exposure_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✓ Exposure Information'),
      '#open' => FALSE,
    ];

    $form['exposure_section']['content'] = [
      '#markup' => $this->renderExposureSummary($exposure),
    ];

    $form['exposure_section']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Exposure Information'),
      '#url' => \Drupal\Core\Url::fromRoute('nfr.enrollment_questionnaire'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // Military Service section
    $military = $questionnaire_data['military'] ?? [];
    $form['military_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✓ Military Service'),
      '#open' => FALSE,
    ];

    $form['military_section']['content'] = [
      '#markup' => $this->renderMilitarySummary($military),
    ];

    $form['military_section']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Military Service'),
      '#url' => \Drupal\Core\Url::fromRoute('nfr.enrollment_questionnaire'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // Other Employment section
    $other_employment = $questionnaire_data['other_employment'] ?? [];
    $form['other_employment_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✓ Other Employment'),
      '#open' => FALSE,
    ];

    $form['other_employment_section']['content'] = [
      '#markup' => $this->renderOtherEmploymentSummary($other_employment),
    ];

    $form['other_employment_section']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Other Employment'),
      '#url' => \Drupal\Core\Url::fromRoute('nfr.enrollment_questionnaire'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // PPE Practices section
    $ppe = $questionnaire_data['ppe'] ?? [];
    $form['ppe_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✓ PPE Practices'),
      '#open' => FALSE,
    ];

    $form['ppe_section']['content'] = [
      '#markup' => $this->renderPPESummary($ppe),
    ];

    $form['ppe_section']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit PPE Practices'),
      '#url' => \Drupal\Core\Url::fromRoute('nfr.enrollment_questionnaire'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // Decontamination Practices section
    $decon = $questionnaire_data['decontamination'] ?? [];
    $form['decon_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✓ Decontamination Practices'),
      '#open' => FALSE,
    ];

    $form['decon_section']['content'] = [
      '#markup' => $this->renderDeconSummary($decon),
    ];

    $form['decon_section']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Decontamination Practices'),
      '#url' => \Drupal\Core\Url::fromRoute('nfr.enrollment_questionnaire'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // Health Information section
    $health = $questionnaire_data['health'] ?? [];
    $form['health_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✓ Health Information'),
      '#open' => FALSE,
    ];

    $form['health_section']['content'] = [
      '#markup' => $this->renderHealthSummary($health),
    ];

    $form['health_section']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Health Information'),
      '#url' => \Drupal\Core\Url::fromRoute('nfr.enrollment_questionnaire'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // Lifestyle Factors section
    $lifestyle = $questionnaire_data['lifestyle'] ?? [];
    $form['lifestyle_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✓ Lifestyle Factors'),
      '#open' => FALSE,
    ];

    $form['lifestyle_section']['content'] = [
      '#markup' => $this->renderLifestyleSummary($lifestyle),
    ];

    $form['lifestyle_section']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Lifestyle Factors'),
      '#url' => \Drupal\Core\Url::fromRoute('nfr.enrollment_questionnaire'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    // Final confirmation
    $form['confirmation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Final Confirmation'),
    ];

    $form['confirmation']['accuracy_confirmed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I confirm that my responses are accurate to the best of my knowledge'),
      '#required' => TRUE,
    ];

    // Actions
    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['save_draft'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save as Draft'),
      '#submit' => ['::saveDraft'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit Questionnaire'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  /**
   * Load consent data.
   */
  private function loadConsentData(int $uid): ?array {
    return $this->database->select('nfr_consent', 'c')
      ->fields('c')
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc() ?: NULL;
  }

  /**
   * Load profile data.
   */
  private function loadProfileData(int $uid): ?array {
    return $this->database->select('nfr_user_profile', 'p')
      ->fields('p')
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc() ?: NULL;
  }

  /**
   * Load questionnaire data.
   */
  private function loadQuestionnaireData(int $uid): array {
    $q = $this->database->select('nfr_questionnaire', 'q')
      ->fields('q')
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc();

    if (!$q) {
      return [];
    }

    // Load work history from normalized tables
    $work_history = $this->loadWorkHistory($uid);
    
    return [
      'demographics' => [
        'race_ethnicity' => json_decode($q['race_ethnicity'] ?? '{}', TRUE),
        'race_other' => $q['race_other'] ?? '',
        'education_level' => $q['education_level'] ?? '',
        'marital_status' => $q['marital_status'] ?? '',
        'height_inches' => $q['height_inches'] ?? '',
        'weight_pounds' => $q['weight_pounds'] ?? '',
      ],
      'work_history' => $work_history,
      'exposure' => [], // TODO: Load from exposure data when implemented
      'military' => [
        'served' => $q['military_service'] ? 'yes' : 'no',
        'branch' => $q['military_branch'] ?? '',
        'years' => $q['military_years'] ?? '',
      ],
      'other_employment' => json_decode($q['other_employment_data'] ?? '{}', TRUE),
      'ppe' => json_decode($q['ppe_practices'] ?? '{}', TRUE),
      'decontamination' => json_decode($q['decon_practices'] ?? '{}', TRUE),
      'health' => [
        'cancer_diagnosed' => $q['cancer_diagnosis'] ? 'yes' : 'no',
        'cancer_details' => json_decode($q['cancer_details'] ?? '[]', TRUE),
        'family_history' => json_decode($q['family_cancer_history'] ?? '[]', TRUE),
      ],
      'lifestyle' => [
        'smoking_history' => json_decode($q['smoking_history'] ?? '{}', TRUE),
        'alcohol_use' => $q['alcohol_use'] ?? '',
      ],
    ];
  }

  /**
   * Load work history from normalized tables.
   */
  private function loadWorkHistory(int $uid): array {
    $work_history = [
      'num_departments' => 0,
      'departments' => [],
    ];
    
    // Load departments
    $departments = $this->database->select('nfr_work_history', 'wh')
      ->fields('wh')
      ->condition('uid', $uid)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    
    $work_history['num_departments'] = count($departments);
    
    foreach ($departments as $dept) {
      $dept_data = [
        'department_name' => $dept['department_name'] ?? '',
        'fdid' => $dept['department_fdid'] ?? '',
        'state' => $dept['department_state'] ?? '',
        'city' => $dept['department_city'] ?? '',
        'start_date' => $dept['start_date'] ?? '',
        'end_date' => $dept['end_date'] ?? '',
        'currently_employed' => $dept['is_current'] ? TRUE : FALSE,
        'jobs' => [],
      ];
      
      // Load jobs for this department
      $jobs = $this->database->select('nfr_job_titles', 'jt')
        ->fields('jt')
        ->condition('work_history_id', $dept['id'])
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
      
      foreach ($jobs as $job) {
        $dept_data['jobs'][] = [
          'title' => $job['job_title'] ?? '',
          'employment_type' => $job['employment_type'] ?? '',
          'responded_incidents' => $job['responded_to_incidents'] ? 'yes' : 'no',
        ];
      }
      
      $work_history['departments'][] = $dept_data;
    }
    
    return $work_history;
  }

  /**
   * Check completeness of enrollment data.
   */
  private function checkCompleteness(?array $consent, ?array $profile, array $questionnaire): array {
    $incomplete = [];

    if (!$consent || empty($consent['consented_to_participate'])) {
      $incomplete[] = $this->t('Informed Consent');
    }

    if (!$profile || empty($profile['profile_completed'])) {
      $incomplete[] = $this->t('User Profile');
    }

    if (empty($questionnaire['demographics'])) {
      $incomplete[] = $this->t('Demographics');
    }

    if (empty($questionnaire['work_history'])) {
      $incomplete[] = $this->t('Work History');
    }

    return $incomplete;
  }

  /**
   * Render consent summary.
   */
  private function renderConsentSummary(?array $data): string {
    if (!$data) {
      return '<p class="incomplete">' . $this->t('Not completed') . '</p>';
    }

    $html = '<div class="summary-content">';
    $html .= '<p><strong>' . $this->t('Consent to Participate:') . '</strong> ' . 
      ($data['consented_to_participate'] ? $this->t('Yes') : $this->t('No')) . '</p>';
    $html .= '<p><strong>' . $this->t('Consent to Registry Linkage:') . '</strong> ' . 
      ($data['consented_to_registry_linkage'] ? $this->t('Yes') : $this->t('No')) . '</p>';
    $html .= '<p><strong>' . $this->t('Electronic Signature:') . '</strong> ' . 
      htmlspecialchars($data['electronic_signature']) . '</p>';
    $html .= '<p><strong>' . $this->t('Date:') . '</strong> ' . 
      date('F j, Y', (int) $data['consent_timestamp']) . '</p>';
    $html .= '</div>';

    return $html;
  }

  /**
   * Render profile summary.
   */
  private function renderProfileSummary(array $data): string {
    if (empty($data)) {
      return '<p class="incomplete">' . $this->t('Not completed') . '</p>';
    }

    $html = '<div class="summary-content">';
    $html .= '<h4>' . $this->t('Personal Information') . '</h4>';
    $html .= '<p><strong>' . $this->t('Name:') . '</strong> ' . 
      htmlspecialchars(($data['first_name'] ?? '') . ' ' . ($data['middle_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) . '</p>';
    $html .= '<p><strong>' . $this->t('Date of Birth:') . '</strong> ' . 
      htmlspecialchars($data['date_of_birth'] ?? '') . '</p>';
    $html .= '<p><strong>' . $this->t('Sex:') . '</strong> ' . 
      htmlspecialchars(ucfirst($data['sex'] ?? '')) . '</p>';
    
    $html .= '<h4>' . $this->t('Contact Information') . '</h4>';
    $html .= '<p><strong>' . $this->t('Address:') . '</strong> ' . 
      htmlspecialchars($data['address_line1'] ?? '') . 
      (!empty($data['address_line2']) ? ', ' . htmlspecialchars($data['address_line2']) : '') . '<br>' .
      htmlspecialchars(($data['city'] ?? '') . ', ' . ($data['state'] ?? '') . ' ' . ($data['zip_code'] ?? '')) . '</p>';
    $html .= '<p><strong>' . $this->t('Email:') . '</strong> ' . 
      htmlspecialchars($data['primary_email'] ?? '') . '</p>';
    if (!empty($data['mobile_phone'])) {
      $html .= '<p><strong>' . $this->t('Phone:') . '</strong> ' . 
        htmlspecialchars($data['mobile_phone']) . '</p>';
    }

    $html .= '<h4>' . $this->t('Work Status') . '</h4>';
    $html .= '<p>' . htmlspecialchars(ucwords(str_replace('_', ' ', $data['current_work_status'] ?? ''))) . '</p>';
    
    $html .= '</div>';

    return $html;
  }

  /**
   * Render demographics summary.
   */
  private function renderDemographicsSummary(array $data): string {
    if (empty($data)) {
      return '<p class="incomplete">' . $this->t('Not completed') . '</p>';
    }

    $html = '<div class="summary-content">';
    
    if (!empty($data['race_ethnicity'])) {
      $races = array_filter($data['race_ethnicity']);
      if (!empty($races)) {
        $html .= '<p><strong>' . $this->t('Race/Ethnicity:') . '</strong> ' . 
          implode(', ', array_map('ucwords', array_map(function($r) {
            return str_replace('_', ' ', $r);
          }, array_keys($races)))) . '</p>';
      }
    }

    if (!empty($data['education_level'])) {
      $html .= '<p><strong>' . $this->t('Education:') . '</strong> ' . 
        htmlspecialchars(ucwords(str_replace('_', ' ', $data['education_level']))) . '</p>';
    }

    if (!empty($data['marital_status'])) {
      $html .= '<p><strong>' . $this->t('Marital Status:') . '</strong> ' . 
        htmlspecialchars(ucfirst($data['marital_status'])) . '</p>';
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render work history summary.
   */
  private function renderWorkHistorySummary(array $data): string {
    if (empty($data['departments'])) {
      return '<p class="incomplete">' . $this->t('Not completed') . '</p>';
    }

    $html = '<div class="summary-content">';
    $html .= '<p><strong>' . $this->t('Number of Departments:') . '</strong> ' . 
      count($data['departments']) . '</p>';

    foreach ($data['departments'] as $i => $dept) {
      $html .= '<h4>' . $this->t('Department @num', ['@num' => $i + 1]) . '</h4>';
      $html .= '<p><strong>' . htmlspecialchars($dept['department_name'] ?? '') . '</strong><br>';
      $html .= htmlspecialchars($dept['city'] ?? '') . ', ' . htmlspecialchars($dept['state'] ?? '') . '</p>';
      $html .= '<p>' . htmlspecialchars($dept['start_date'] ?? '') . ' - ' . 
        ($dept['currently_employed'] ? $this->t('Present') : htmlspecialchars($dept['end_date'] ?? '')) . '</p>';
      
      if (!empty($dept['jobs'])) {
        $html .= '<p><strong>' . $this->t('Positions:') . '</strong> ';
        $titles = array_map(fn($j) => htmlspecialchars($j['title'] ?? ''), $dept['jobs']);
        $html .= implode(', ', $titles) . '</p>';
      }
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render exposure summary.
   */
  private function renderExposureSummary(array $data): string {
    if (empty($data)) {
      return '<p class="incomplete">' . $this->t('Not completed') . '</p>';
    }

    $html = '<div class="summary-content">';
    
    if (!empty($data['afff_used'])) {
      $html .= '<p><strong>' . $this->t('AFFF Usage:') . '</strong> ' . 
        ucfirst($data['afff_used']) . '</p>';
      if ($data['afff_used'] === 'yes' && !empty($data['afff_times'])) {
        $html .= '<p>' . $this->t('Used approximately @count times', ['@count' => $data['afff_times']]) . '</p>';
      }
    }

    if (!empty($data['diesel_exhaust'])) {
      $html .= '<p><strong>' . $this->t('Diesel Exhaust Exposure:') . '</strong> ' . 
        ucfirst(str_replace('_', ' ', $data['diesel_exhaust'])) . '</p>';
    }

    if (!empty($data['major_incidents']) && $data['major_incidents'] === 'yes') {
      $html .= '<p><strong>' . $this->t('Major Incidents:') . '</strong> Yes</p>';
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render military summary.
   */
  private function renderMilitarySummary(array $data): string {
    if (empty($data)) {
      return '<p class="incomplete">' . $this->t('Not completed') . '</p>';
    }

    $html = '<div class="summary-content">';
    
    if (!empty($data['served'])) {
      $html .= '<p><strong>' . $this->t('Military Service:') . '</strong> ' . 
        ucfirst($data['served']) . '</p>';
      
      if ($data['served'] === 'yes') {
        if (!empty($data['branch'])) {
          $html .= '<p><strong>' . $this->t('Branch:') . '</strong> ' . 
            ucwords(str_replace('_', ' ', $data['branch'])) . '</p>';
        }
        if (!empty($data['was_firefighter'])) {
          $html .= '<p><strong>' . $this->t('Military Firefighter:') . '</strong> ' . 
            ucfirst($data['was_firefighter']) . '</p>';
        }
      }
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render other employment summary.
   */
  private function renderOtherEmploymentSummary(array $data): string {
    if (empty($data)) {
      return '<p class="incomplete">' . $this->t('Not completed') . '</p>';
    }

    $html = '<div class="summary-content">';
    
    if (!empty($data['had_other_jobs'])) {
      $html .= '<p><strong>' . $this->t('Other Jobs:') . '</strong> ' . 
        ucfirst($data['had_other_jobs']) . '</p>';
      
      if ($data['had_other_jobs'] === 'yes' && !empty($data['jobs'])) {
        $html .= '<p>' . count($data['jobs']) . ' ' . $this->t('other jobs reported') . '</p>';
      }
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render PPE summary.
   */
  private function renderPPESummary(array $data): string {
    if (empty($data)) {
      return '<p class="incomplete">' . $this->t('Not completed') . '</p>';
    }

    $html = '<div class="summary-content">';
    
    if (!empty($data['scba_during_suppression'])) {
      $html .= '<p><strong>' . $this->t('SCBA During Suppression:') . '</strong> ' . 
        ucwords(str_replace('_', ' ', $data['scba_during_suppression'])) . '</p>';
    }

    if (!empty($data['scba_during_overhaul'])) {
      $html .= '<p><strong>' . $this->t('SCBA During Overhaul:') . '</strong> ' . 
        ucwords(str_replace('_', ' ', $data['scba_during_overhaul'])) . '</p>';
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render decontamination summary.
   */
  private function renderDeconSummary(array $data): string {
    if (empty($data)) {
      return '<p class="incomplete">' . $this->t('Not completed') . '</p>';
    }

    $html = '<div class="summary-content">';
    
    if (!empty($data['department_had_sops'])) {
      $html .= '<p><strong>' . $this->t('Department Had SOPs:') . '</strong> ' . 
        ucfirst($data['department_had_sops']) . '</p>';
    }

    $html .= '<p>' . $this->t('Decontamination practices recorded') . '</p>';

    $html .= '</div>';

    return $html;
  }

  /**
   * Render health summary.
   */
  private function renderHealthSummary(array $data): string {
    if (empty($data)) {
      return '<p class="incomplete">' . $this->t('Not completed') . '</p>';
    }

    $html = '<div class="summary-content">';
    
    if (!empty($data['cancer_diagnosed'])) {
      $html .= '<p><strong>' . $this->t('Cancer Diagnosis:') . '</strong> ' . 
        ucfirst($data['cancer_diagnosed']) . '</p>';
      
      if ($data['cancer_diagnosed'] === 'yes' && !empty($data['cancers'])) {
        $html .= '<p>' . count($data['cancers']) . ' ' . $this->t('cancer diagnoses reported') . '</p>';
      }
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render lifestyle summary.
   */
  private function renderLifestyleSummary(array $data): string {
    if (empty($data)) {
      return '<p class="incomplete">' . $this->t('Not completed') . '</p>';
    }

    $html = '<div class="summary-content">';
    
    if (!empty($data['smoking_status'])) {
      $html .= '<p><strong>' . $this->t('Smoking Status:') . '</strong> ' . 
        ucwords($data['smoking_status']) . '</p>';
    }

    if (!empty($data['alcohol_frequency'])) {
      $html .= '<p><strong>' . $this->t('Alcohol Use:') . '</strong> ' . 
        ucwords(str_replace('_', ' ', $data['alcohol_frequency'])) . '</p>';
    }

    if (isset($data['physical_activity_days'])) {
      $html .= '<p><strong>' . $this->t('Physical Activity:') . '</strong> ' . 
        $data['physical_activity_days'] . ' ' . $this->t('days per week') . '</p>';
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Save draft submit handler.
   */
  public function saveDraft(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('Your progress has been saved as a draft.'));
    $form_state->setRedirect('nfr.my_dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Validation handled by required checkbox
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $this->currentUser->id();
    
    // Mark everything as complete
    $this->database->update('nfr_questionnaire')
      ->fields([
        'questionnaire_completed' => 1,
        'updated' => time(),
      ])
      ->condition('uid', $uid)
      ->execute();

    $this->database->update('nfr_user_profile')
      ->fields([
        'profile_completed' => 1,
        'updated' => time(),
      ])
      ->condition('uid', $uid)
      ->execute();

    $this->messenger()->addStatus($this->t('Thank you for completing your enrollment!'));
    $form_state->setRedirect('nfr.confirmation');
  }

}
