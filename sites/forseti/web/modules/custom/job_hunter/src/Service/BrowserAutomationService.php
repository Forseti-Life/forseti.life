<?php

namespace Drupal\job_hunter\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Browser automation service for automated job application submission.
 *
 * Phase 1: URL classification, credential check, structured routing.
 *   - Aggregator / unknown → manual_required with best available apply URL
 *   - Known ATS without credentials → manual_required with direct ATS URL
 *   - Known ATS with credentials → ready_for_automation (Phase 2: actual form fill)
 *
 * Phase 2 (future): Playwright/Puppeteer form fill per ATS platform.
 *
 * All attempts are logged to jobhunter_application_attempts for audit and retry.
 */
class BrowserAutomationService {

  /**
   * ATS platforms supported for Phase 2 automation (future).
   */
  const AUTOMATABLE_PLATFORMS = [
    'greenhouse',
    'lever',
    'ashby',
    'smartrecruiters',
    'workable',
  ];

  /**
   * ATS platforms that require account login (credentials needed).
   */
  const LOGIN_REQUIRED_PLATFORMS = [
    'workday',
    'taleo',
    'icims',
    'successfactors',
    'ultipro',
    'paylocity',
    'usajobs',
    'bamboohr',
  ];

  /**
   * Human-readable ATS platform names for user-facing messages.
   */
  const PLATFORM_LABELS = [
    'workday'         => 'Workday',
    'greenhouse'      => 'Greenhouse',
    'lever'           => 'Lever',
    'taleo'           => 'Oracle Taleo',
    'icims'           => 'iCIMS',
    'successfactors'  => 'SAP SuccessFactors',
    'ultipro'         => 'UKG Pro (UltiPro)',
    'paylocity'       => 'Paylocity',
    'bamboohr'        => 'BambooHR',
    'smartrecruiters' => 'SmartRecruiters',
    'jobvite'         => 'Jobvite',
    'ashby'           => 'Ashby',
    'workable'        => 'Workable',
    'breezy'          => 'Breezy HR',
    'rippling'        => 'Rippling',
    'usajobs'         => 'USAJobs.gov',
    'wellfound'       => 'Wellfound (AngelList)',
    'y_combinator'    => 'Y Combinator Work at a Startup',
    'aggregator'      => 'Job Aggregator',
    'custom'          => 'Company Career Page',
    'unknown'         => 'Unknown',
  ];

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * @var \Drupal\job_hunter\Service\ApplyUrlResolverService
   */
  protected $urlResolver;

  /**
   * @var \Drupal\job_hunter\Service\ApplicationFormMapper
   */
  protected $formMapper;

  /**
   * @var \Drupal\job_hunter\Service\JobSeekerService
   */
  protected $jobSeekerService;

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    ApplyUrlResolverService $url_resolver,
    ApplicationFormMapper $form_mapper,
    JobSeekerService $job_seeker_service
  ) {
    $this->database       = $database;
    $this->loggerFactory  = $logger_factory;
    $this->urlResolver    = $url_resolver;
    $this->formMapper     = $form_mapper;
    $this->jobSeekerService = $job_seeker_service;
  }

  /**
   * Process an application submission attempt.
   *
   * Returns a structured result the queue worker uses to update application
   * status and log the attempt.
   *
   * @param array $app_data
   *   Application data from ApplicationSubmissionService::prepareApplicationData().
   *   Must include: uid, job_id, job_url, apply_options, company_name, job_title,
   *   personal_info, tailored_resume.
   * @param int $application_id
   *   The jobhunter_applications.id record being processed.
   *
   * @return array{
   *   success: bool,
   *   outcome: string,
   *   apply_url: string,
   *   ats_platform: string,
   *   ats_label: string,
   *   confirmation: string,
   *   error: string,
   *   reason: string,
   *   instructions: string,
   *   field_map: array,
   *   requires_credentials: bool,
   *   has_credentials: bool,
   * }
   */
  public function processApplication(array $app_data, int $application_id): array {
    $start_ms = (int) (microtime(TRUE) * 1000);
    $uid      = $app_data['uid'];
    $job_id   = $app_data['job_id'];
    $logger   = $this->loggerFactory->get('job_hunter');

    // Build a synthetic job array for the resolver.
    $job = [
      'apply_options' => $app_data['apply_options'] ?? '[]',
      'job_url'       => $app_data['job_url'] ?? '',
    ];

    // Check if application record already has a resolved URL (set by applyToJob controller).
    $app_record = $this->database->select('jobhunter_applications', 'a')
      ->fields('a', ['apply_url', 'ats_platform', 'selected_apply_option'])
      ->condition('id', $application_id)
      ->execute()
      ->fetchAssoc();

    if (!empty($app_record['apply_url']) && !empty($app_record['ats_platform'])) {
      // Already resolved by the controller — reuse it.
      $resolved = [
        'url'             => $app_record['apply_url'],
        'ats_platform'    => $app_record['ats_platform'],
        'selected_option' => $app_record['selected_apply_option'] ?? '',
        'confidence'      => 'high',
      ];
    } else {
      // Resolve now (queue worker path without prior controller resolution).
      $resolved = $this->urlResolver->resolve($job);
      // Persist to application record.
      $this->database->update('jobhunter_applications')
        ->fields([
          'apply_url'             => $resolved['url'],
          'ats_platform'          => $resolved['ats_platform'],
          'selected_apply_option' => $resolved['selected_option'],
          'changed'               => date('Y-m-d H:i:s'),
        ])
        ->condition('id', $application_id)
        ->execute();
    }

    $apply_url    = $resolved['url'];
    $ats_platform = $resolved['ats_platform'];
    $ats_label    = self::PLATFORM_LABELS[$ats_platform] ?? ucfirst($ats_platform);

    $logger->info('BrowserAutomation: job @job_id, platform @platform, url @url', [
      '@job_id'   => $job_id,
      '@platform' => $ats_platform,
      '@url'      => $apply_url,
    ]);

    // Route by platform.
    $result = $this->routeByPlatform($uid, $job_id, $application_id, $app_data, $apply_url, $ats_platform, $ats_label);

    // Log the attempt.
    $duration_ms = (int) (microtime(TRUE) * 1000) - $start_ms;
    $this->logAttempt($application_id, $uid, $apply_url, $ats_platform, $result['outcome'], $duration_ms, $result['error'] ?? NULL, [
      'ats_label' => $ats_label,
      'reason'    => $result['reason'] ?? '',
    ]);

    // Increment attempt_count on the application record.
    $this->database->query(
      'UPDATE {jobhunter_applications} SET attempt_count = attempt_count + 1, changed = :changed WHERE id = :id',
      [':changed' => date('Y-m-d H:i:s'), ':id' => $application_id]
    );

    return $result;
  }

  /**
   * Route the submission attempt based on ATS platform.
   */
  protected function routeByPlatform(int $uid, int $job_id, int $application_id, array $app_data, string $apply_url, string $ats_platform, string $ats_label): array {
    // Aggregator or unresolvable → manual required.
    if (in_array($ats_platform, ['aggregator', 'unknown', ''])) {
      return $this->buildManualResult(
        $apply_url,
        $ats_platform,
        $ats_label,
        'This job was sourced from a job aggregator. Please apply directly via the link below.',
        'no_direct_ats'
      );
    }

    // Check if this ATS requires credentials for automation.
    $requires_credentials = in_array($ats_platform, self::LOGIN_REQUIRED_PLATFORMS);
    $has_credentials = FALSE;

    if ($requires_credentials) {
      $company_id = $this->resolveCompanyId($job_id);
      if ($company_id) {
        $has_credentials = $this->checkCredentials($uid, $company_id);
      }
    }

    // Phase 1: For automatable platforms (Greenhouse, Lever, Ashby, SmartRecruiters, Workable)
    // these don't require login for the public apply form.
    if (in_array($ats_platform, self::AUTOMATABLE_PLATFORMS)) {
      // Build form field map so the user knows exactly what to fill.
      $field_map = $this->buildFieldMapForJob($uid, $job_id, $ats_platform);
      return [
        'success'              => FALSE, // Phase 2 will set TRUE when form fill works.
        'outcome'              => 'manual_required',
        'apply_url'            => $apply_url,
        'ats_platform'         => $ats_platform,
        'ats_label'            => $ats_label,
        'confirmation'         => '',
        'error'                => 'Browser automation Phase 2 not yet active for ' . $ats_label,
        'reason'               => 'phase2_pending',
        'instructions'         => 'Your tailored resume and profile data are ready. Click the link to apply on ' . $ats_label . '. Your profile fields are pre-mapped below.',
        'field_map'            => $field_map,
        'requires_credentials' => FALSE,
        'has_credentials'      => FALSE,
      ];
    }

    // Login-required ATS without credentials.
    if ($requires_credentials && !$has_credentials) {
      $login_url = $this->getAtsLoginUrl($ats_platform, $apply_url);
      return [
        'success'              => FALSE,
        'outcome'              => 'manual_required',
        'apply_url'            => $apply_url,
        'ats_platform'         => $ats_platform,
        'ats_label'            => $ats_label,
        'confirmation'         => '',
        'error'                => 'No credentials stored for ' . $ats_label,
        'reason'               => 'no_credentials',
        'instructions'         => 'To enable auto-submission on ' . $ats_label . ', store your credentials via Job Hunter → Settings → Credentials. Then re-queue this application.',
        'field_map'            => [],
        'requires_credentials' => TRUE,
        'has_credentials'      => FALSE,
        'login_url'            => $login_url,
      ];
    }

    // Login-required ATS WITH credentials → ready for Phase 2 automation.
    if ($requires_credentials && $has_credentials) {
      $field_map = $this->buildFieldMapForJob($uid, $job_id, $ats_platform);
      return [
        'success'              => FALSE, // Phase 2 will do actual submit.
        'outcome'              => 'manual_required',
        'apply_url'            => $apply_url,
        'ats_platform'         => $ats_platform,
        'ats_label'            => $ats_label,
        'confirmation'         => '',
        'error'                => 'Automated login form fill not yet active (Phase 2)',
        'reason'               => 'phase2_pending',
        'instructions'         => 'Credentials found for ' . $ats_label . '. Automated submission will be available in Phase 2. Apply manually using your stored credentials.',
        'field_map'            => $field_map,
        'requires_credentials' => TRUE,
        'has_credentials'      => TRUE,
      ];
    }

    // Custom / unknown company career page.
    $field_map = $this->buildFieldMapForJob($uid, $job_id, 'custom');
    return $this->buildManualResult(
      $apply_url,
      $ats_platform,
      $ats_label,
      'This job uses a custom career page. Apply via the link below.',
      'custom_page',
      $field_map
    );
  }

  /**
   * Build a standard manual_required result.
   */
  protected function buildManualResult(string $apply_url, string $ats_platform, string $ats_label, string $instructions, string $reason, array $field_map = []): array {
    return [
      'success'              => FALSE,
      'outcome'              => 'manual_required',
      'apply_url'            => $apply_url,
      'ats_platform'         => $ats_platform,
      'ats_label'            => $ats_label,
      'confirmation'         => '',
      'error'                => $instructions,
      'reason'               => $reason,
      'instructions'         => $instructions,
      'field_map'            => $field_map,
      'requires_credentials' => FALSE,
      'has_credentials'      => FALSE,
    ];
  }

  /**
   * Build ATS form field map from user profile for a given job.
   */
  protected function buildFieldMapForJob(int $uid, int $job_id, string $ats_platform): array {
    try {
      $seeker = $this->database->select('jobhunter_job_seeker', 'js')
        ->fields('js')
        ->condition('uid', $uid)
        ->execute()
        ->fetchAssoc() ?: [];

      $consolidated = $this->jobSeekerService->getConsolidatedProfile($uid);

      $job = $this->database->select('jobhunter_job_requirements', 'j')
        ->fields('j', ['job_title', 'company_name'])
        ->condition('id', $job_id)
        ->execute()
        ->fetchAssoc() ?: [];

      return $this->formMapper->buildFieldMap($seeker, $consolidated, $job, $ats_platform);
    }
    catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Check if the user has credentials stored for a company.
   */
  protected function checkCredentials(int $uid, int $company_id): bool {
    return (bool) $this->database->select('jobhunter_employer_credentials', 'c')
      ->condition('uid', $uid)
      ->condition('company_id', $company_id)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Resolve company_id from job_id.
   */
  protected function resolveCompanyId(int $job_id): ?int {
    $company_id = $this->database->select('jobhunter_job_requirements', 'j')
      ->fields('j', ['company_id'])
      ->condition('id', $job_id)
      ->execute()
      ->fetchField();
    return $company_id ? (int) $company_id : NULL;
  }

  /**
   * Get the ATS login page URL.
   */
  protected function getAtsLoginUrl(string $ats_platform, string $apply_url): string {
    $known_login_urls = [
      'usajobs'        => 'https://login.usajobs.gov',
      'workday'        => '', // Workday login is per-company subdomain — use apply URL domain.
      'successfactors' => 'https://performancemanager.successfactors.com/login',
    ];

    if (!empty($known_login_urls[$ats_platform])) {
      return $known_login_urls[$ats_platform];
    }

    // For Workday and others: derive login from apply URL domain.
    $parsed = parse_url($apply_url);
    if ($parsed && isset($parsed['scheme'], $parsed['host'])) {
      return $parsed['scheme'] . '://' . $parsed['host'];
    }

    return $apply_url;
  }

  /**
   * Log a submission attempt to jobhunter_application_attempts.
   */
  protected function logAttempt(int $application_id, int $uid, string $url_tried, string $ats_detected, string $outcome, int $duration_ms, ?string $error_message, array $metadata = []): void {
    try {
      $this->database->insert('jobhunter_application_attempts')
        ->fields([
          'application_id' => $application_id,
          'uid'            => $uid,
          'attempted_at'   => date('Y-m-d H:i:s'),
          'url_tried'      => substr($url_tried, 0, 1000),
          'ats_detected'   => $ats_detected,
          'outcome'        => $outcome,
          'duration_ms'    => $duration_ms,
          'error_message'  => $error_message ? substr($error_message, 0, 500) : NULL,
          'metadata'       => json_encode($metadata),
        ])
        ->execute();
    }
    catch (\Throwable $e) {
      // Log attempt failures silently — don't break the main flow.
      $this->loggerFactory->get('job_hunter')->error('Failed to log application attempt: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

}
