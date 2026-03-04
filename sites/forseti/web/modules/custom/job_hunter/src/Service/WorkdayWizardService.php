<?php

namespace Drupal\job_hunter\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;

/**
 * Advances through Workday application wizard steps (2-7) via Playwright.
 *
 * Wraps playwright/workday-wizard-advance.js which handles:
 *   - My Information (verify/fill personal info)
 *   - My Experience (verify resume-parsed data)
 *   - Application Questions (screenshot + common Q&A)
 *   - Voluntary Disclosures (EEO)
 *   - Self-Identify (disability)
 *   - Review & Submit (click Submit)
 *
 * Follows the same subprocess pattern as ResumeUploadService:
 *   temp payload file (0600) → proc_open → output file → hard timeout cap.
 */
class WorkdayWizardService {

  protected Connection $database;
  protected FileSystemInterface $fileSystem;

  public function __construct(Connection $database, FileSystemInterface $file_system) {
    $this->database = $database;
    $this->fileSystem = $file_system;
  }

  // ── Valid step keys ─────────────────────────────────────────────────────────

  private const VALID_STEPS = [
    'my_information',
    'my_experience',
    'application_questions',
    'voluntary_disclosures',
    'self_identify',
    'review_submit',
  ];

  // ── Public API ──────────────────────────────────────────────────────────────

  /**
   * Advance a specific Workday wizard step for a job application.
   *
   * @param int    $job_id
   *   The jobhunter_job_requirements.id.
   * @param int    $uid
   *   The Drupal user ID.
   * @param string $step_key
   *   One of: my_information, my_experience, application_questions,
   *           voluntary_disclosures, self_identify, review_submit.
   * @param array  $options
   *   - timeout (int) — total seconds; default 120.
   *
   * @return array{
   *   ok: bool,
   *   target_step: string,
   *   detected_page: string,
   *   page_matched: bool,
   *   fields_filled: array,
   *   fields_skipped: array,
   *   continue_clicked: bool,
   *   post_continue_url: string,
   *   page_title: string,
   *   needs_manual_review: bool,
   *   evidence: string,
   *   screenshots: array,
   *   error: string,
   * }
   */
  public function advanceStep(int $job_id, int $uid, string $step_key, array $options = []): array {
    $timeout = (int) ($options['timeout'] ?? 120);
    $apply_url_override = trim((string) ($options['apply_url'] ?? ''));

    $blank = [
      'ok'                  => FALSE,
      'target_step'         => $step_key,
      'detected_page'       => '',
      'page_matched'        => FALSE,
      'fields_filled'       => [],
      'fields_skipped'      => [],
      'continue_clicked'    => FALSE,
      'post_continue_url'   => '',
      'page_title'          => '',
      'needs_manual_review' => FALSE,
      'evidence'            => '',
      'screenshots'         => [],
      'error'               => '',
    ];

    if (!in_array($step_key, self::VALID_STEPS, TRUE)) {
      return array_merge($blank, ['error' => "Invalid step key: $step_key"]);
    }

    // ── Load application record ───────────────────────────────────────────
    $application = $this->database->select('jobhunter_applications', 'a')
      ->fields('a', ['id', 'apply_url', 'ats_platform', 'metadata'])
      ->condition('a.uid', $uid)
      ->condition('a.job_id', $job_id)
      ->orderBy('created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$application) {
      return array_merge($blank, ['error' => 'No application record found for job ' . $job_id . '.']);
    }

    $metadata = [];
    if (!empty($application['metadata'])) {
      $decoded = json_decode((string) $application['metadata'], TRUE);
      if (is_array($decoded)) {
        $metadata = $decoded;
      }
    }

    $resume_post_continue_url = (string) ($metadata['step5_cache']['resume_upload_result']['post_continue_url'] ?? '');
    $wd_last_url = (string) ($metadata['step5_cache']['wd_last_url'] ?? '');
    $apply_url    = $apply_url_override !== ''
      ? $apply_url_override
      : ($wd_last_url !== ''
      ? $wd_last_url
      : ($resume_post_continue_url !== ''
      ? $resume_post_continue_url
      : (string) ($metadata['auth_url'] ?? $application['apply_url'] ?? '')));
    $ats_platform = (string) ($application['ats_platform'] ?? 'custom');

    if ($apply_url === '') {
      return array_merge($blank, ['error' => 'No apply URL found.']);
    }

    // ── Load stored credentials ───────────────────────────────────────────
    $company_id = $this->getCompanyIdForJob($job_id);
    if ($company_id <= 0) {
      return array_merge($blank, ['error' => 'No company linked to this job.']);
    }

    /** @var \Drupal\job_hunter\Service\CredentialManagementService $cred_service */
    $cred_service = \Drupal::service('job_hunter.credential_management_service');
    $credential = $cred_service->retrieveCredential($uid, $company_id, 'basic');

    if (!$credential || empty($credential['username']) || empty($credential['password'])) {
      return array_merge($blank, ['error' => 'No stored credentials found.']);
    }

    // ── Build profile data for form filling ───────────────────────────────
    $profile_data = $this->buildProfileData($uid);

    // ── Screenshot directory ──────────────────────────────────────────────
    $screenshot_dir = '';
    $private_path = $this->fileSystem->realpath('private://job_hunter/screenshots');
    if ($private_path) {
      if (!is_dir($private_path)) {
        @mkdir($private_path, 0755, TRUE);
      }
      if (is_dir($private_path) && is_writable($private_path)) {
        $screenshot_dir = $private_path;
      }
    }

    // ── Build payload file ────────────────────────────────────────────────
    $resume_pdf_path = $this->getResumePdfPath($uid, $job_id) ?? '';
    $payload = [
      'username'       => (string) $credential['username'],
      'password'       => (string) $credential['password'],
      'apply_url'      => $apply_url,
      'target_step'    => $step_key,
      'profile_data'   => $profile_data,
      'resume_pdf_path'=> $resume_pdf_path,
      'screenshot_dir' => $screenshot_dir,
      'application_id' => (int) $application['id'],
    ];

    $payload_file = tempnam(sys_get_temp_dir(), 'jh_wz_');
    file_put_contents($payload_file, json_encode($payload), LOCK_EX);
    chmod($payload_file, 0600);

    // ── Run the Node script ───────────────────────────────────────────────
    $result = $this->runNode($payload_file, $timeout, $step_key);

    // Clean up payload file if script didn't delete it.
    if (file_exists($payload_file)) {
      @unlink($payload_file);
    }

    if ($result === NULL) {
      return array_merge($blank, ['error' => 'Node script could not be launched or returned invalid output.']);
    }

    return array_merge($blank, [
      'ok'                  => !empty($result['ok']),
      'target_step'         => (string) ($result['target_step'] ?? $step_key),
      'detected_page'       => (string) ($result['detected_page'] ?? ''),
      'page_matched'        => !empty($result['page_matched']),
      'fields_filled'       => (array) ($result['fields_filled'] ?? []),
      'fields_skipped'      => (array) ($result['fields_skipped'] ?? []),
      'continue_clicked'    => !empty($result['continue_clicked']),
      'post_continue_url'   => (string) ($result['post_continue_url'] ?? ''),
      'page_title'          => (string) ($result['page_title'] ?? ''),
      'needs_manual_review' => !empty($result['needs_manual_review']),
      'evidence'            => (string) ($result['evidence'] ?? ''),
      'screenshots'         => (array) ($result['screenshots'] ?? []),
      'error'               => (string) ($result['error'] ?? ''),
    ]);
  }

  /**
   * Advance remaining Workday wizard steps in a single browser session.
   *
   * @param int $job_id
   *   The jobhunter_job_requirements.id.
   * @param int $uid
   *   The Drupal user ID.
   * @param string $start_step
   *   First step key to run in sequence.
   * @param array $options
   *   - timeout (int): total seconds, default 220.
   *   - apply_url (string): optional URL override.
   *
   * @return array
   *   Script result including step_results and completed_steps.
   */
  public function advanceWizardAutoSingleSession(int $job_id, int $uid, string $start_step = 'my_information', array $options = []): array {
    $timeout = (int) ($options['timeout'] ?? 220);
    $apply_url_override = trim((string) ($options['apply_url'] ?? ''));

    $blank = [
      'ok' => FALSE,
      'target_step' => 'wizard_auto',
      'completed_steps' => [],
      'step_results' => [],
      'post_continue_url' => '',
      'error' => '',
    ];

    if (!in_array($start_step, self::VALID_STEPS, TRUE)) {
      return array_merge($blank, ['error' => "Invalid start step: $start_step"]);
    }

    $application = $this->database->select('jobhunter_applications', 'a')
      ->fields('a', ['id', 'apply_url', 'metadata'])
      ->condition('a.uid', $uid)
      ->condition('a.job_id', $job_id)
      ->orderBy('created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$application) {
      return array_merge($blank, ['error' => 'No application record found for job ' . $job_id . '.']);
    }

    $metadata = [];
    if (!empty($application['metadata'])) {
      $decoded = json_decode((string) $application['metadata'], TRUE);
      if (is_array($decoded)) {
        $metadata = $decoded;
      }
    }

    $resume_post_continue_url = (string) ($metadata['step5_cache']['resume_upload_result']['post_continue_url'] ?? '');
    $wd_last_url = (string) ($metadata['step5_cache']['wd_last_url'] ?? '');
    $apply_url = $apply_url_override !== ''
      ? $apply_url_override
      : ($wd_last_url !== ''
      ? $wd_last_url
      : ($resume_post_continue_url !== ''
      ? $resume_post_continue_url
      : (string) ($metadata['auth_url'] ?? $application['apply_url'] ?? '')));

    if ($apply_url === '') {
      return array_merge($blank, ['error' => 'No apply URL found.']);
    }

    $company_id = $this->getCompanyIdForJob($job_id);
    if ($company_id <= 0) {
      return array_merge($blank, ['error' => 'No company linked to this job.']);
    }

    /** @var \Drupal\job_hunter\Service\CredentialManagementService $cred_service */
    $cred_service = \Drupal::service('job_hunter.credential_management_service');
    $credential = $cred_service->retrieveCredential($uid, $company_id, 'basic');

    if (!$credential || empty($credential['username']) || empty($credential['password'])) {
      return array_merge($blank, ['error' => 'No stored credentials found.']);
    }

    $profile_data = $this->buildProfileData($uid);

    $screenshot_dir = '';
    $private_path = $this->fileSystem->realpath('private://job_hunter/screenshots');
    if ($private_path) {
      if (!is_dir($private_path)) {
        @mkdir($private_path, 0755, TRUE);
      }
      if (is_dir($private_path) && is_writable($private_path)) {
        $screenshot_dir = $private_path;
      }
    }

    $resume_pdf_path = $this->getResumePdfPath($uid, $job_id) ?? '';
    $payload = [
      'username'       => (string) $credential['username'],
      'password'       => (string) $credential['password'],
      'apply_url'      => $apply_url,
      'target_step'    => 'wizard_validate',
      'start_step'     => $start_step,
      'profile_data'   => $profile_data,
      'resume_pdf_path'=> $resume_pdf_path,
      'screenshot_dir' => $screenshot_dir,
      'application_id' => (int) $application['id'],
    ];

    $payload_file = tempnam(sys_get_temp_dir(), 'jh_wz_');
    file_put_contents($payload_file, json_encode($payload), LOCK_EX);
    chmod($payload_file, 0600);

    $result = $this->runNode($payload_file, $timeout, 'wizard_auto');

    if (file_exists($payload_file)) {
      @unlink($payload_file);
    }

    if ($result === NULL) {
      return array_merge($blank, ['error' => 'Node script could not be launched or returned invalid output.']);
    }

    return array_merge($blank, [
      'ok' => !empty($result['ok']),
      'target_step' => (string) ($result['target_step'] ?? 'wizard_auto'),
      'completed_steps' => (array) ($result['completed_steps'] ?? []),
      'step_results' => (array) ($result['step_results'] ?? []),
      'post_continue_url' => (string) ($result['post_continue_url'] ?? ''),
      'error' => (string) ($result['error'] ?? ''),
      'evidence' => (string) ($result['evidence'] ?? ''),
      'screenshots' => (array) ($result['screenshots'] ?? []),
    ]);
  }

  // ── Private helpers ─────────────────────────────────────────────────────────

  /**
   * Assemble profile data from the job_seeker table for form filling.
   */
  private function buildProfileData(int $uid): array {
    $data = [
      'full_name'         => '',
      'first_name'        => '',
      'last_name'         => '',
      'email'             => '',
      'phone'             => '',
      'city'              => '',
      'state'             => '',
      'country'           => '',
      'linkedin'          => '',
      'eeo_gender'        => '',
      'eeo_ethnicity'     => '',
      'eeo_veteran'       => '',
      'disability_status' => '',
      'work_authorized_us'    => '',
      'requires_sponsorship'  => '',
      'age_18_or_older'       => '',
      'hear_about_us'         => '',
      'prior_company_employment' => '',
      'prior_company_wwid'    => '',
      'prior_company_email'   => '',
      'phone_device_type'     => '',
      'experience_job_title'  => '',
      'experience_company'    => '',
      'experience_from'       => '',
      'experience_to'         => '',
    ];

    try {
      $row = $this->database->select('jobhunter_job_seeker', 'j')
        ->fields('j', [
          'full_name', 'contact_email', 'contact_phone',
          'location_city', 'location_state', 'country', 'linkedin_url',
          'eeo_gender', 'eeo_ethnicity', 'eeo_veteran', 'eeo_disability',
          'work_authorized_us', 'requires_sponsorship', 'age_18_or_older',
          'consolidated_profile_json',
        ])
        ->condition('uid', $uid)
        ->execute()
        ->fetchAssoc();

      if ($row) {
        $data['full_name'] = (string) ($row['full_name'] ?? '');
        $data['email']     = (string) ($row['contact_email'] ?? '');
        $data['phone']     = (string) ($row['contact_phone'] ?? '');
        $data['city']      = (string) ($row['location_city'] ?? '');
        $data['state']     = (string) ($row['location_state'] ?? '');
        $data['country']   = (string) ($row['country'] ?? '');
        if (!empty($row['eeo_gender'])) {
          $data['eeo_gender'] = (string) $row['eeo_gender'];
        }
        if (!empty($row['eeo_ethnicity'])) {
          $data['eeo_ethnicity'] = (string) $row['eeo_ethnicity'];
        }
        if (!empty($row['eeo_veteran'])) {
          $data['eeo_veteran'] = (string) $row['eeo_veteran'];
        }
        if (!empty($row['eeo_disability'])) {
          $data['disability_status'] = (string) $row['eeo_disability'];
        }
        if (!empty($row['work_authorized_us'])) {
          $data['work_authorized_us'] = (string) $row['work_authorized_us'];
        }
        if (!empty($row['requires_sponsorship'])) {
          $data['requires_sponsorship'] = (string) $row['requires_sponsorship'];
        }
        if (!empty($row['age_18_or_older'])) {
          $data['age_18_or_older'] = (string) $row['age_18_or_older'];
        }

        $json = [];
        if (!empty($row['consolidated_profile_json'])) {
          $decoded = json_decode((string) $row['consolidated_profile_json'], TRUE);
          if (is_array($decoded)) {
            $json = $decoded;
          }
        }

        $contact = is_array($json['contact_info'] ?? NULL) ? $json['contact_info'] : [];
        $location = is_array($contact['location'] ?? NULL) ? $contact['location'] : [];
        $prefs = is_array($json['job_search_preferences'] ?? NULL) ? $json['job_search_preferences'] : [];
        $demographics = is_array($json['demographics'] ?? NULL) ? $json['demographics'] : [];
        $experience = is_array($json['professional_experience'] ?? NULL) ? $json['professional_experience'] : [];

        if ($data['full_name'] === '' && !empty($contact['full_name'])) {
          $data['full_name'] = (string) $contact['full_name'];
        }
        if ($data['email'] === '' && !empty($contact['email'])) {
          $data['email'] = (string) $contact['email'];
        }
        if ($data['phone'] === '' && !empty($contact['phone'])) {
          $data['phone'] = (string) $contact['phone'];
        }
        if ($data['city'] === '' && !empty($location['city'])) {
          $data['city'] = (string) $location['city'];
        }
        if ($data['state'] === '' && !empty($location['state'])) {
          $data['state'] = (string) $location['state'];
        }
        if ($data['country'] === '' && !empty($location['country'])) {
          $data['country'] = (string) $location['country'];
        }

        if ($data['work_authorized_us'] === '' && isset($prefs['us_work_authorized'])) {
          $data['work_authorized_us'] = (string) $prefs['us_work_authorized'];
        }
        if ($data['requires_sponsorship'] === '' && isset($prefs['requires_sponsorship'])) {
          $data['requires_sponsorship'] = (string) $prefs['requires_sponsorship'];
        }
        if ($data['age_18_or_older'] === '' && isset($prefs['age_18_or_older'])) {
          $data['age_18_or_older'] = (string) $prefs['age_18_or_older'];
        }
        if ($data['hear_about_us'] === '' && isset($prefs['hear_about_us'])) {
          $data['hear_about_us'] = (string) $prefs['hear_about_us'];
        }
        if ($data['prior_company_employment'] === '' && isset($prefs['prior_company_employment'])) {
          $data['prior_company_employment'] = (string) $prefs['prior_company_employment'];
        }
        if ($data['prior_company_wwid'] === '' && isset($prefs['prior_company_wwid'])) {
          $data['prior_company_wwid'] = (string) $prefs['prior_company_wwid'];
        }
        if ($data['prior_company_email'] === '' && isset($prefs['prior_company_email'])) {
          $data['prior_company_email'] = (string) $prefs['prior_company_email'];
        }
        if ($data['phone_device_type'] === '' && isset($prefs['phone_device_type'])) {
          $data['phone_device_type'] = (string) $prefs['phone_device_type'];
        }

        if (!empty($experience) && is_array($experience[0] ?? NULL)) {
          $exp0 = $experience[0];
          if (empty($data['experience_job_title']) && !empty($exp0['title'])) {
            $data['experience_job_title'] = (string) $exp0['title'];
          }
          if (empty($data['experience_company']) && !empty($exp0['company'])) {
            $data['experience_company'] = (string) $exp0['company'];
          }
          if (empty($data['experience_from']) && !empty($exp0['start_date'])) {
            $data['experience_from'] = (string) $exp0['start_date'];
          }
          if (empty($data['experience_to']) && !empty($exp0['end_date'])) {
            $data['experience_to'] = (string) $exp0['end_date'];
          }
        }

        if ($data['eeo_gender'] === '' && isset($demographics['gender'])) {
          $data['eeo_gender'] = (string) $demographics['gender'];
        }
        if ($data['eeo_ethnicity'] === '' && isset($demographics['race_ethnicity'])) {
          $data['eeo_ethnicity'] = (string) $demographics['race_ethnicity'];
        }
        if ($data['eeo_veteran'] === '' && isset($demographics['veteran_status'])) {
          $data['eeo_veteran'] = (string) $demographics['veteran_status'];
        }
        if ($data['disability_status'] === '' && isset($demographics['disability_status'])) {
          $data['disability_status'] = (string) $demographics['disability_status'];
        }

        // Split name.
        if ($data['full_name']) {
          $parts = preg_split('/\s+/', trim($data['full_name']));
          $data['first_name'] = $parts[0] ?? '';
          $data['last_name']  = implode(' ', array_slice($parts, 1));
        }

        // LinkedIn from column or consolidated JSON.
        if (!empty($row['linkedin_url'])) {
          $data['linkedin'] = (string) $row['linkedin_url'];
        }
        elseif (!empty($contact['linkedin'])) {
          $data['linkedin'] = (string) $contact['linkedin'];
        }

        // Normalize profile-coded values into Workday-facing text.
        $data['work_authorized_us'] = $this->normalizeYesNo($data['work_authorized_us']);
        $data['requires_sponsorship'] = $this->normalizeYesNo($data['requires_sponsorship']);
        $data['age_18_or_older'] = $this->normalizeYesNo($data['age_18_or_older']);
        $data['prior_company_employment'] = $this->normalizeYesNo($data['prior_company_employment']);
        $data['phone_device_type'] = $this->normalizePhoneDeviceType($data['phone_device_type']);
        $data['eeo_gender'] = $this->normalizeGender($data['eeo_gender']);
        $data['eeo_ethnicity'] = $this->normalizeEthnicity($data['eeo_ethnicity']);
        $data['eeo_veteran'] = $this->normalizeVeteran($data['eeo_veteran']);
        $data['disability_status'] = $this->normalizeDisability($data['disability_status']);
      }
    }
    catch (\Throwable $e) {
      // Non-fatal — continue with defaults.
    }

    return $data;
  }

  /**
   * Normalize yes/no style values from profile into Workday labels.
   */
  private function normalizeYesNo(string $value): string {
    $v = strtolower(trim($value));
    if (in_array($v, ['yes', 'y', 'true', '1'], TRUE)) {
      return 'Yes';
    }
    if (in_array($v, ['no', 'n', 'false', '0'], TRUE)) {
      return 'No';
    }
    return trim($value);
  }

  private function normalizeGender(string $value): string {
    $v = strtolower(trim($value));
    return match ($v) {
      'male' => 'Male',
      'female' => 'Female',
      'non_binary', 'non-binary' => 'Non-binary',
      'prefer_not_to_say' => 'Prefer not to say',
      default => trim($value),
    };
  }

  private function normalizeEthnicity(string $value): string {
    $v = strtolower(trim($value));
    return match ($v) {
      'american_indian' => 'American Indian or Alaska Native',
      'asian' => 'Asian',
      'black' => 'Black or African American',
      'hispanic' => 'Hispanic or Latino',
      'native_hawaiian' => 'Native Hawaiian or Other Pacific Islander',
      'white' => 'White',
      'two_or_more' => 'Two or More Races',
      'prefer_not_to_say' => 'Prefer not to say',
      default => trim($value),
    };
  }

  private function normalizeVeteran(string $value): string {
    $v = strtolower(trim($value));
    return match ($v) {
      'not_veteran' => 'I am not a protected veteran',
      'veteran' => 'I identify as one or more of the classifications of protected veteran',
      'prefer_not_to_say' => 'Prefer not to say',
      default => trim($value),
    };
  }

  private function normalizeDisability(string $value): string {
    $v = strtolower(trim($value));
    return match ($v) {
      'no_disability' => 'No, I do not have a disability',
      'yes_disability' => 'Yes, I have a disability (or previously had a disability)',
      'prefer_not_to_say' => 'Prefer not to say',
      default => trim($value),
    };
  }

  private function normalizePhoneDeviceType(string $value): string {
    $v = strtolower(trim($value));
    return match ($v) {
      'mobile', 'cell', 'cell phone' => 'Mobile',
      'home', 'home phone' => 'Home',
      'work', 'office', 'work phone' => 'Work',
      'other' => 'Other',
      default => trim($value),
    };
  }

  /**
   * Get the company_id for a given job requirement.
   */
  private function getCompanyIdForJob(int $job_id): int {
    try {
      $cid = $this->database->select('jobhunter_job_requirements', 'j')
        ->fields('j', ['company_id'])
        ->condition('j.id', $job_id)
        ->execute()
        ->fetchField();
      return (int) ($cid ?: 0);
    }
    catch (\Exception $e) {
      return 0;
    }
  }

  /**
   * Resolve tailored resume PDF absolute filesystem path.
   */
  private function getResumePdfPath(int $uid, int $job_id): ?string {
    $uri = $this->database->select('jobhunter_tailored_resumes', 't')
      ->fields('t', ['pdf_path'])
      ->condition('uid', $uid)
      ->condition('job_id', $job_id)
      ->isNotNull('pdf_path')
      ->orderBy('created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if (!$uri) {
      return NULL;
    }

    $real_path = $this->fileSystem->realpath($uri);
    return ($real_path && file_exists($real_path)) ? $real_path : NULL;
  }

  /**
   * Spawn the Node subprocess for the wizard advance script.
   */
  private function runNode(string $payload_file, int $timeout, string $step_key): ?array {
    $playwright_dir = DRUPAL_ROOT . '/../web/modules/custom/job_hunter/playwright';
    if (!is_dir($playwright_dir)) {
      $playwright_dir = DRUPAL_ROOT . '/modules/custom/job_hunter/playwright';
    }
    $script = $playwright_dir . '/workday-wizard-advance.js';
    if (!file_exists($script)) {
      return NULL;
    }

    $browser_timeout = max(120, $timeout + 20);
    $output_file = tempnam(sys_get_temp_dir(), 'jh_wz_out_');
    @unlink($output_file);

    // Prefer system-installed Chrome/Chromium.
    $system_chrome = '';
    foreach (['/usr/bin/google-chrome', '/usr/bin/chromium-browser', '/usr/bin/chromium'] as $candidate) {
      if (is_executable($candidate)) {
        $system_chrome = $candidate;
        break;
      }
    }

    $node_bin = is_executable('/usr/bin/node') ? '/usr/bin/node' : 'node';

    $cmd = $node_bin . ' ' . escapeshellarg($script)
      . ' --payload-file=' . escapeshellarg($payload_file)
      . ' --output-file=' . escapeshellarg($output_file)
      . ' --timeout=' . (int) $browser_timeout
      . ($system_chrome !== '' ? ' --executable-path=' . escapeshellarg($system_chrome) : '');

    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptors, $pipes, $playwright_dir);
    if (!is_resource($process)) {
      @unlink($output_file);
      return NULL;
    }

    fclose($pipes[0]);

    $hard_cap = $browser_timeout + 15;
    $start    = time();
    $stderr   = '';
    stream_set_blocking($pipes[2], FALSE);

    while (TRUE) {
      $chunk = fread($pipes[2], 8192);
      if ($chunk !== FALSE && $chunk !== '') {
        $stderr .= $chunk;
      }

      $status = proc_get_status($process);
      if (!$status['running']) {
        break;
      }

      if ((time() - $start) >= $hard_cap) {
        proc_terminate($process, 9);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        @unlink($output_file);
        return [
          'ok'    => FALSE,
          'error' => 'Browser subprocess timed out after ' . $hard_cap . 's.',
        ];
      }

      usleep(500000);
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    // Log stderr for diagnostics.
    if ($stderr !== '') {
      \Drupal::logger('job_hunter')->notice('WD wizard @step stderr: @stderr', [
        '@step'   => $step_key,
        '@stderr' => substr($stderr, 0, 2000),
      ]);
    }

    $raw = file_exists($output_file) ? file_get_contents($output_file) : '';
    @unlink($output_file);

    if ($raw === '' || $raw === FALSE) {
      return [
        'ok'    => FALSE,
        'error' => 'Output file empty. stderr: ' . substr($stderr, 0, 400),
      ];
    }

    $decoded = json_decode(trim($raw), TRUE);
    if (!is_array($decoded)) {
      return [
        'ok'    => FALSE,
        'error' => 'Invalid JSON from Node. stderr: ' . substr($stderr, 0, 400),
      ];
    }

    return $decoded;
  }

}
