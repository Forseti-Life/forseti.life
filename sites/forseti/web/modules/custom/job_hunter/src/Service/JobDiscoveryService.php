<?php

namespace Drupal\job_hunter\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Service for job discovery data operations.
 * 
 * Handles data fetching and processing for the job discovery page,
 * including user profile data, job listings, and API credentials.
 */
class JobDiscoveryService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructs a JobDiscoveryService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(
    Connection $database,
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $current_user,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Get default search parameters from user profile.
   *
   * @return array
   *   Array containing default search values:
   *   - keywords: string
   *   - location: string
   *   - remote_pref: string
   *   - salary_min: int
   *   - salary_max: int
   *   - employment_type: string
   *   - relocation: string
   */
  public function getUserSearchDefaults(): array {
    $defaults = [
      'keywords' => '',
      'location' => '',
      'remote_pref' => '',
      'salary_min' => '',
      'salary_max' => '',
      'employment_type' => '',
      'relocation' => '',
    ];

    try {
      $profile = $this->database->select('jobhunter_job_seeker', 'js')
        ->fields('js')
        ->condition('uid', $this->currentUser->id())
        ->execute()
        ->fetchObject();

      if ($profile && !empty($profile->consolidated_profile_json)) {
        $consolidated = json_decode($profile->consolidated_profile_json, TRUE) ?: [];

        // Extract target job titles and keywords.
        $titles = $consolidated['job_search_preferences']['target_titles'] ?? '';
        $keywords = $consolidated['job_search_preferences']['keywords'] ?? '';

        // Handle both string and array formats.
        $titles_array = is_array($titles) ? $titles : ($titles ? explode("\n", $titles) : []);
        $keywords_array = is_array($keywords) ? $keywords : ($keywords ? explode("\n", $keywords) : []);

        $combined = array_filter(array_merge($titles_array, $keywords_array));
        if (!empty($combined)) {
          // Use first 3.
          $defaults['keywords'] = implode(', ', array_slice($combined, 0, 3));
        }

        // Extract location from contact info.
        if (isset($consolidated['contact_info']['location'])) {
          $location_parts = [];
          if (!empty($consolidated['contact_info']['location']['city'])) {
            $location_parts[] = $consolidated['contact_info']['location']['city'];
          }
          if (!empty($consolidated['contact_info']['location']['state'])) {
            $location_parts[] = $consolidated['contact_info']['location']['state'];
          }
          if (!empty($location_parts)) {
            $defaults['location'] = implode(', ', $location_parts);
          }
        }

        // Get remote preference.
        $defaults['remote_pref'] = $consolidated['job_search_preferences']['remote_preference'] ?? '';
        if ($defaults['remote_pref'] === 'remote' && empty($defaults['location'])) {
          $defaults['location'] = 'Remote';
        }

        // Get salary expectations.
        $salary_min = $consolidated['job_search_preferences']['salary_expectation_min'] ?? '';
        $salary_max = $consolidated['job_search_preferences']['salary_expectation_max'] ?? '';
        if ($salary_min && is_numeric($salary_min)) {
          $defaults['salary_min'] = (int) $salary_min;
        }
        if ($salary_max && is_numeric($salary_max)) {
          $defaults['salary_max'] = (int) $salary_max;
        }

        // Get relocation preference.
        $defaults['relocation'] = $consolidated['job_search_preferences']['relocation_willing'] ?? '';
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('job_hunter')->error('Error loading profile for search: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return $defaults;
  }

  /**
   * Check which external API credentials are configured.
   *
   * @return array
   *   Array with boolean keys:
   *   - google_cloud: boolean
   *   - adzuna: boolean
   *   - usajobs: boolean
   *   - serpapi: boolean
   */
  public function getApiCredentialsStatus(): array {
    $status = [
      'google_cloud' => FALSE,
      'adzuna' => FALSE,
      'usajobs' => FALSE,
      'serpapi' => FALSE,
    ];

    try {
      $config = $this->configFactory->get('job_hunter.settings');

      // Check Google Cloud credentials.
      $google_credentials = $config->get('google_cloud_credentials');
      if (!empty($google_credentials)) {
        $status['google_cloud'] = TRUE;
      }

      // Check Adzuna credentials.
      $adzuna_app_id = $config->get('adzuna_app_id');
      $adzuna_app_key = $config->get('adzuna_app_key');
      if (!empty($adzuna_app_id) && !empty($adzuna_app_key)) {
        $status['adzuna'] = TRUE;
      }

      // Check USAJobs credentials.
      $usajobs_api_key = $config->get('usajobs_api_key');
      $usajobs_email = $config->get('usajobs_email');
      if (!empty($usajobs_api_key) && !empty($usajobs_email)) {
        $status['usajobs'] = TRUE;
      }

      // Check SerpAPI credentials.
      $serpapi_api_key = $config->get('serpapi_api_key');
      if (!empty($serpapi_api_key)) {
        $status['serpapi'] = TRUE;
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('job_hunter')->error('Error checking credentials: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return $status;
  }

  /**
   * Get count of saved jobs for current user.
   *
   * @return int
   *   Number of saved jobs.
   */
  public function getSavedJobsCount(): int {
    try {
      return (int) $this->database->select('jobhunter_job_requirements', 'j')
        ->condition('uid', $this->currentUser->id())
        ->countQuery()
        ->execute()
        ->fetchField();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('job_hunter')->error('Error counting saved jobs: @error', [
        '@error' => $e->getMessage(),
      ]);
      return 0;
    }
  }

  /**
   * Get count of target companies for current user.
   *
   * @return int
   *   Number of target companies.
   */
  public function getTargetCompaniesCount(): int {
    try {
      return (int) $this->database->select('jobhunter_companies', 'c')
        ->condition('uid', $this->currentUser->id())
        ->countQuery()
        ->execute()
        ->fetchField();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('job_hunter')->error('Error counting target companies: @error', [
        '@error' => $e->getMessage(),
      ]);
      return 0;
    }
  }

  /**
   * Get saved jobs with optional filters.
   *
   * @param array $filters
   *   Array of filter criteria:
   *   - company: string, company name filter
   *   - status: string, job status filter
   *   - ai_status: string, AI extraction status filter
   *   - tailoring: string, tailoring status filter
   *
   * @return array
   *   Array of job objects with company and tailoring information.
   */
  public function getSavedJobs(array $filters = []): array {
    try {
      $query = $this->database->select('jobhunter_job_requirements', 'j')
        ->fields('j');
      
      $query->leftJoin('jobhunter_companies', 'c', 'j.company_id = c.id');
      $query->addField('c', 'name', 'company_name');
      
      // Join tailored resumes for current user.
      $query->leftJoin('jobhunter_tailored_resumes', 'tr', 'j.id = tr.job_id AND tr.uid = :uid', [
        ':uid' => $this->currentUser->id(),
      ]);
      $query->addField('tr', 'tailoring_status');
      $query->addField('tr', 'tailored_resume_json');
      $query->addField('tr', 'pdf_path');

      // Apply filters.
      if (!empty($filters['company'])) {
        $query->condition('c.name', '%' . $this->database->escapeLike($filters['company']) . '%', 'LIKE');
      }
      if (!empty($filters['status'])) {
        $query->condition('j.status', $filters['status']);
      }
      if (!empty($filters['ai_status'])) {
        $query->condition('j.ai_extraction_status', $filters['ai_status']);
      }
      if (!empty($filters['tailoring'])) {
        $query->condition('tr.tailoring_status', $filters['tailoring']);
      }

      $query->orderBy('c.name', 'ASC');
      $query->orderBy('j.job_title', 'ASC');

      $results = $query->execute()->fetchAll();
      
      // Decode JSON fields for template use.
      foreach ($results as $job) {
        if (!empty($job->extracted_json)) {
          $job->extracted_data = json_decode($job->extracted_json, TRUE);
        }
        if (!empty($job->tailored_resume_json)) {
          $job->tailored_data = json_decode($job->tailored_resume_json, TRUE);
        }
      }
      
      return $results;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('job_hunter')->error('Error fetching saved jobs: @error', [
        '@error' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Get list of all company names for filter dropdown.
   *
   * @return array
   *   Array of company names.
   */
  public function getCompanyNames(): array {
    try {
      return $this->database->select('jobhunter_companies', 'c')
        ->fields('c', ['name'])
        ->distinct()
        ->orderBy('name', 'ASC')
        ->execute()
        ->fetchCol();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('job_hunter')->error('Error fetching company names: @error', [
        '@error' => $e->getMessage(),
      ]);
      return [];
    }
  }

}
