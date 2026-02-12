<?php

namespace Drupal\job_hunter\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Centralized service for orchestrating job searches across multiple sources.
 *
 * This service provides a unified interface for searching jobs from multiple
 * sources (internal database, external APIs) and stores results for analytics.
 */
class SearchAggregatorService {

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
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Google Cloud Talent Solution service.
   *
   * @var \Drupal\job_hunter\Service\CloudTalentSolutionService
   */
  protected $googleCloudService;

  /**
   * The Adzuna API service.
   *
   * @var \Drupal\job_hunter\Service\AdzunaApiService
   */
  protected $adzunaService;

  /**
   * The USAJobs API service.
   *
   * @var \Drupal\job_hunter\Service\UsaJobsApiService
   */
  protected $usaJobsService;

  /**
   * The SerpAPI service.
   *
   * @var \Drupal\job_hunter\Service\SerpApiService
   */
  protected $serpApiService;

  /**
   * Constructs a SearchAggregatorService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\job_hunter\Service\CloudTalentSolutionService $google_cloud_service
   *   The Google Cloud service.
   * @param \Drupal\job_hunter\Service\AdzunaApiService $adzuna_service
   *   The Adzuna API service.
   * @param \Drupal\job_hunter\Service\UsaJobsApiService $usajobs_service
   *   The USAJobs API service.
   * @param \Drupal\job_hunter\Service\SerpApiService $serpapi_service
   *   The SerpAPI service.
   */
  public function __construct(
    Connection $database,
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $current_user,
    LoggerChannelFactoryInterface $logger_factory,
    CloudTalentSolutionService $google_cloud_service,
    AdzunaApiService $adzuna_service,
    UsaJobsApiService $usajobs_service,
    SerpApiService $serpapi_service
  ) {
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->logger = $logger_factory->get('job_hunter');
    $this->googleCloudService = $google_cloud_service;
    $this->adzunaService = $adzuna_service;
    $this->usaJobsService = $usajobs_service;
    $this->serpApiService = $serpapi_service;
  }

  /**
   * Search for jobs across multiple sources.
   *
   * @param array $params
   *   Search parameters containing:
   *   - query: (string) Search keywords
   *   - location: (string) Location filter
   *   - sources: (array) Array of source identifiers to search
   *   - employment_type: (string) Employment type filter
   *   - salary_min: (int) Minimum salary
   *   - salary_max: (int) Maximum salary
   *   - remote_preference: (string) Remote work preference
   *   - date_posted: (string) Date posted filter
   *   - company: (string) Company filter (Forseti only)
   *   - relocation_willing: (bool) Relocation preference
   *   - page: (int) Page number
   *   - next_page_token: (string) Pagination token for SerpAPI
   *
   * @return array
   *   Array containing:
   *   - results: Array of normalized job results
   *   - total: Total number of results
   *   - sources_searched: Array of sources that were searched
   *   - diagnostics: Diagnostic information if no results found
   *   - pagination: Pagination metadata (for sources that support it)
   */
  public function searchJobs(array $params): array {
    $sources = $params['sources'] ?? ['forseti'];
    $all_results = [];
    $pagination_metadata = [];

    $this->logger->info('🔍 SearchAggregator: Starting search with sources: @sources', [
      '@sources' => implode(', ', $sources),
    ]);

    // Search each requested source
    foreach ($sources as $source) {
      switch ($source) {
        case 'forseti':
          $results = $this->searchForsetiDatabase($params);
          $all_results = array_merge($all_results, $results);
          break;

        case 'google_cloud':
          $results = $this->searchGoogleCloud($params);
          $all_results = array_merge($all_results, $results);
          break;

        case 'adzuna':
          $results = $this->searchAdzuna($params);
          $all_results = array_merge($all_results, $results);
          break;

        case 'usajobs':
          $results = $this->searchUsaJobs($params);
          $all_results = array_merge($all_results, $results);
          break;

        case 'serpapi':
          $serpapi_data = $this->searchSerpApi($params);
          $all_results = array_merge($all_results, $serpapi_data['results'] ?? []);
          // Store pagination metadata for SerpAPI
          if (!empty($serpapi_data['pagination'])) {
            $pagination_metadata['serpapi'] = $serpapi_data['pagination'];
          }
          break;
      }
    }

    $this->logger->info('✅ SearchAggregator: Total results from all sources: @count', [
      '@count' => count($all_results),
    ]);

    // Store search results for analytics
    $this->storeSearchResults($params, $all_results);

    // Prepare diagnostics if no results
    $diagnostics = [];
    if (empty($all_results)) {
      $diagnostics = $this->generateDiagnostics($sources);
    }

    return [
      'results' => $all_results,
      'total' => count($all_results),
      'sources_searched' => $sources,
      'diagnostics' => $diagnostics,
      'pagination' => $pagination_metadata,
    ];
  }

  /**
   * Search Forseti internal database.
   *
   * @param array $params
   *   Search parameters.
   *
   * @return array
   *   Array of normalized job results.
   */
  protected function searchForsetiDatabase(array $params): array {
    $results = [];

    try {
      // Query main job requirements table
      $query = $this->database->select('jobhunter_job_requirements', 'j')
        ->fields('j');

      // Apply filters
      if (!empty($params['query'])) {
        $query->condition('job_title', '%' . $this->database->escapeLike($params['query']) . '%', 'LIKE');
      }

      if (!empty($params['location'])) {
        $query->condition('location', '%' . $this->database->escapeLike($params['location']) . '%', 'LIKE');
      }

      if (!empty($params['employment_type'])) {
        $query->condition('employment_type', $params['employment_type']);
      }

      if (!empty($params['company'])) {
        $query->condition('company_id', $params['company']);
      }

      if (!empty($params['remote_preference']) && $params['remote_preference'] === 'remote') {
        $query->condition('remote_ok', 1);
      }

      if (!empty($params['date_posted'])) {
        $days = $this->convertDatePostedToDays($params['date_posted']);
        if ($days) {
          $date_threshold = strtotime("-{$days} days");
          $query->condition('created', $date_threshold, '>=');
        }
      }

      $query->orderBy('created', 'DESC');
      $query->range(0, 50);

      $job_rows = $query->execute()->fetchAll();

      // Also query staging table for recent unimported results (last 24 hours)
      $staging_query = $this->database->select('jobhunter_job_search_results', 's')
        ->fields('s');
      
      $staging_query->condition('retrieved', time() - 86400, '>='); // Last 24 hours
      
      // Apply same filters to staging results
      if (!empty($params['query'])) {
        $staging_query->condition('job_title', '%' . $this->database->escapeLike($params['query']) . '%', 'LIKE');
      }

      if (!empty($params['location'])) {
        $staging_query->condition('location', '%' . $this->database->escapeLike($params['location']) . '%', 'LIKE');
      }

      $staging_query->orderBy('retrieved', 'DESC');
      $staging_query->range(0, 25); // Limit staging results

      $staging_rows = $staging_query->execute()->fetchAll();

      $this->logger->info('📊 Forseti DB returned @main_count main jobs + @staging_count staging jobs', [
        '@main_count' => count($job_rows),
        '@staging_count' => count($staging_rows),
      ]);

      // Normalize main table results
      foreach ($job_rows as $job) {
        // Get company name
        $company_name = 'Unknown';
        if (!empty($job->company_id)) {
          $company = $this->database->select('jobhunter_companies', 'c')
            ->fields('c', ['company_name'])
            ->condition('id', $job->company_id)
            ->execute()
            ->fetchField();
          $company_name = $company ?: 'Unknown';
        }

        $results[] = [
          'id' => 'forseti_' . $job->id,
          'title' => $job->job_title ?? 'No title',
          'company' => $company_name,
          'location' => $job->location ?? 'Not specified',
          'employment_type' => $job->employment_type ?? 'Not specified',
          'salary_range' => $this->formatSalaryRange($job->min_salary ?? null, $job->max_salary ?? null),
          'description' => $this->truncateText($job->job_description ?? '', 200),
          'source' => 'Forseti Jobs',
          'posted_date' => !empty($job->created) ? date('M j, Y', $job->created) : 'Unknown',
          'url' => '/jobhunter/job/' . $job->id,
        ];
      }

      // Normalize staging table results (pending import)
      foreach ($staging_rows as $staging_job) {
        $results[] = [
          'id' => 'staging_' . $staging_job->id,
          'title' => $staging_job->job_title ?? 'No title',
          'company' => $staging_job->company_name ?? 'Unknown',
          'location' => $staging_job->location ?? 'Not specified',
          'employment_type' => 'Not specified',
          'salary_range' => '',
          'description' => $this->truncateText($staging_job->description ?? '', 200),
          'source' => 'Forseti Jobs (Pending)',
          'posted_date' => !empty($staging_job->retrieved) ? date('M j, Y', $staging_job->retrieved) : 'Unknown',
          'url' => $staging_job->link ?? '#',
        ];
      }
    }
    catch (\Exception $e) {
      $this->logger->error('❌ Forseti database search failed: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return $results;
  }

  /**
   * Search Google Cloud Talent Solution API.
   *
   * @param array $params
   *   Search parameters.
   *
   * @return array
   *   Array of normalized job results.
   */
  protected function searchGoogleCloud(array $params): array {
    $results = [];

    try {
      $config = $this->configFactory->get('job_hunter.settings');
      $google_credentials = $config->get('google_cloud_credentials');

      if (empty($google_credentials)) {
        $this->logger->warning('⚠️ Google Cloud search skipped: no credentials configured');
        return [];
      }

      // Run diagnostic check
      try {
        $diagnostic_results = $this->googleCloudService->testSimpleSearch();
        $this->logger->info('🔍 Google Cloud diagnostic: Tenant has @total total jobs available', [
          '@total' => $diagnostic_results['total_size'] ?? 0,
        ]);
      }
      catch (\Exception $e) {
        $this->logger->warning('⚠️ Google Cloud diagnostic failed: @error', [
          '@error' => $e->getMessage(),
        ]);
      }

      // Build API parameters
      $google_params = [];
      if (!empty($params['query'])) {
        $google_params['query'] = $params['query'];
      }
      if (!empty($params['location'])) {
        $google_params['location'] = $params['location'];
      }
      if (!empty($params['employment_type'])) {
        $google_params['employment_types'] = [$params['employment_type']];
      }
      if (!empty($params['salary_min'])) {
        $google_params['salary_min'] = $params['salary_min'];
      }
      if (!empty($params['salary_max'])) {
        $google_params['salary_max'] = $params['salary_max'];
      }
      if (!empty($params['remote_preference'])) {
        $google_params['remote_preference'] = $params['remote_preference'];
      }
      if (!empty($params['date_posted'])) {
        $google_params['date_posted'] = $params['date_posted'];
      }

      $google_results = $this->googleCloudService->searchJobs($google_params);

      $this->logger->info('📊 Google Cloud API returned @count results', [
        '@count' => count($google_results['jobs'] ?? []),
      ]);

      // Normalize Google Cloud results
      foreach ($google_results['jobs'] ?? [] as $google_job) {
        $job_data = $google_job['job'] ?? [];
        $results[] = [
          'id' => $job_data['name'] ?? uniqid('google_'),
          'title' => $job_data['title'] ?? 'No title',
          'company' => $job_data['companyDisplayName'] ?? 'Unknown',
          'location' => !empty($job_data['addresses']) ? implode(', ', $job_data['addresses']) : 'Not specified',
          'employment_type' => !empty($job_data['employmentTypes']) ? implode(', ', $job_data['employmentTypes']) : 'Not specified',
          'salary_range' => 'Not specified',
          'description' => $this->truncateText($job_data['description'] ?? '', 200),
          'source' => 'Google Jobs',
          'posted_date' => !empty($job_data['postingPublishTime']) ? date('M j, Y', strtotime($job_data['postingPublishTime'])) : 'Unknown',
          'url' => $job_data['applicationInfo']['uris'][0] ?? '',
        ];
      }
    }
    catch (\Exception $e) {
      $this->logger->error('❌ Google Cloud search failed: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return $results;
  }

  /**
   * Search Adzuna API.
   *
   * @param array $params
   *   Search parameters.
   *
   * @return array
   *   Array of normalized job results.
   */
  protected function searchAdzuna(array $params): array {
    $results = [];

    try {
      $adzuna_params = [
        'query' => $params['query'] ?? '',
        'location' => $params['location'] ?? '',
        'employment_type' => $params['employment_type'] ?? '',
        'page' => 1,
        'results_per_page' => 25,
      ];

      $this->logger->info('🔍 Searching Adzuna API');

      $adzuna_results = $this->adzunaService->searchJobs($adzuna_params);

      $this->logger->info('📥 Adzuna returned @count jobs', [
        '@count' => $adzuna_results['total'] ?? 0,
      ]);

      // Normalize Adzuna results
      foreach ($adzuna_results['jobs'] ?? [] as $job_data) {
        $results[] = [
          'id' => 'adzuna_' . ($job_data['id'] ?? uniqid()),
          'title' => $job_data['title'] ?? 'Unknown',
          'company' => $job_data['company']['display_name'] ?? 'Unknown',
          'location' => $job_data['location']['display_name'] ?? 'Unknown',
          'employment_type' => 'Not specified',
          'salary_range' => $this->formatSalaryRange($job_data['salary_min'] ?? null, $job_data['salary_max'] ?? null),
          'description' => $this->truncateText($job_data['description'] ?? '', 200),
          'source' => 'Adzuna',
          'posted_date' => !empty($job_data['created']) ? date('M j, Y', strtotime($job_data['created'])) : 'Unknown',
          'url' => $job_data['redirect_url'] ?? '',
        ];
      }
    }
    catch (\Exception $e) {
      $this->logger->error('❌ Adzuna API search failed: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return $results;
  }

  /**
   * Search USAJobs API.
   *
   * @param array $params
   *   Search parameters.
   *
   * @return array
   *   Array of normalized job results.
   */
  protected function searchUsaJobs(array $params): array {
    $results = [];

    try {
      $usajobs_params = [
        'query' => $params['query'] ?? '',
        'location' => $params['location'] ?? '',
        'page' => 1,
        'results_per_page' => 25,
      ];

      $this->logger->info('🔍 Searching USAJobs API');

      $usajobs_results = $this->usaJobsService->searchJobs($usajobs_params);

      $this->logger->info('📥 USAJobs returned @count jobs', [
        '@count' => $usajobs_results['total'] ?? 0,
      ]);

      // Normalize USAJobs results
      foreach ($usajobs_results['jobs'] ?? [] as $job_data) {
        $matched_job = $job_data['MatchedObjectDescriptor'] ?? [];

        $salary_range = 'Not specified';
        if (!empty($matched_job['PositionRemuneration'])) {
          $remuneration = $matched_job['PositionRemuneration'][0] ?? [];
          $min_range = $remuneration['MinimumRange'] ?? null;
          $max_range = $remuneration['MaximumRange'] ?? null;
          $salary_range = $this->formatSalaryRange($min_range, $max_range);
        }

        $results[] = [
          'id' => 'usajobs_' . ($matched_job['PositionID'] ?? uniqid()),
          'title' => $matched_job['PositionTitle'] ?? 'Unknown',
          'company' => $matched_job['OrganizationName'] ?? 'U.S. Government',
          'location' => $matched_job['PositionLocationDisplay'] ?? 'Washington, DC',
          'employment_type' => 'Not specified',
          'salary_range' => $salary_range,
          'description' => $this->truncateText($matched_job['UserArea']['Details']['JobSummary'] ?? '', 200),
          'source' => 'USAJobs',
          'posted_date' => !empty($matched_job['PublicationStartDate']) ? date('M j, Y', strtotime($matched_job['PublicationStartDate'])) : 'Unknown',
          'url' => $matched_job['PositionURI'] ?? '',
        ];
      }
    }
    catch (\Exception $e) {
      $this->logger->error('❌ USAJobs API search failed: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return $results;
  }

  /**
   * Search SerpAPI (Google Jobs).
   *
   * @param array $params
   *   Search parameters.
   *
   * @return array
   *   Array with 'results' and 'pagination' keys.
   */
  protected function searchSerpApi(array $params): array {
    $results = [];
    $pagination = [];

    try {
      $serpapi_params = [
        'query' => $params['query'] ?? '',
        'location' => $params['location'] ?? '',
        'employment_type' => $params['employment_type'] ?? '',
        'page' => $params['page'] ?? 1,
        'results_per_page' => 10, // SerpAPI standard
      ];

      // Pass through next_page_token if provided
      if (!empty($params['next_page_token'])) {
        $serpapi_params['next_page_token'] = $params['next_page_token'];
      }

      $this->logger->info('🔍 Searching SerpAPI (Google Jobs)');

      $serpapi_results = $this->serpApiService->searchJobs($serpapi_params);

      $this->logger->info('📥 SerpAPI returned @count jobs', [
        '@count' => $serpapi_results['total'] ?? 0,
      ]);

      // Store pagination info
      $pagination = [
        'current_page' => $serpapi_results['page'] ?? 1,
        'next_page_token' => $serpapi_results['next_page_token'] ?? NULL,
        'has_more' => $serpapi_results['has_more'] ?? FALSE,
      ];

      // Normalize SerpAPI results
      foreach ($serpapi_results['jobs'] ?? [] as $job_data) {
        $salary_range = 'Not specified';
        if (!empty($job_data['detected_extensions']['salary'])) {
          $salary_range = $job_data['detected_extensions']['salary'];
        }

        $posted_date = 'Unknown';
        if (!empty($job_data['detected_extensions']['posted_at'])) {
          $posted_date = $job_data['detected_extensions']['posted_at'];
        }

        // Extract employment type from schedule_type or extensions
        $employment_type = 'Not specified';
        if (!empty($job_data['detected_extensions']['schedule_type'])) {
          $employment_type = $job_data['detected_extensions']['schedule_type'];
        }

        // Generate content-based hash for deduplication
        $job_hash = $this->generateJobHash(
          $job_data['company_name'] ?? '',
          $job_data['title'] ?? '',
          $job_data['location'] ?? ''
        );

        $results[] = [
          // Use native SerpAPI job_id instead of random uniqid()
          'id' => $job_data['job_id'] ?? 'serpapi_' . uniqid(),
          'title' => $job_data['title'] ?? 'Unknown',
          'company' => $job_data['company_name'] ?? 'Unknown',
          'location' => $job_data['location'] ?? 'Unknown',
          'employment_type' => $employment_type,
          'salary_range' => $salary_range,
          // Store FULL description without truncation
          'description' => $job_data['description'] ?? '',
          'source' => 'Google Jobs (SerpAPI)',
          'posted_date' => $posted_date,
          // Use apply_options first, fallback to share_link
          'url' => $job_data['apply_options'][0]['link'] ?? $job_data['share_link'] ?? '',
          // NEW: Content-based hash for deduplication
          'job_hash' => $job_hash,
          // NEW: Rich metadata fields
          'via' => $job_data['via'] ?? '',
          'thumbnail' => $job_data['thumbnail'] ?? '',
          'share_link' => $job_data['share_link'] ?? '',
          // NEW: Detected extensions (work from home, benefits, etc.)
          'work_from_home' => $job_data['detected_extensions']['work_from_home'] ?? false,
          'health_insurance' => $job_data['detected_extensions']['health_insurance'] ?? false,
          'dental_coverage' => $job_data['detected_extensions']['dental_coverage'] ?? false,
          'paid_time_off' => $job_data['detected_extensions']['paid_time_off'] ?? false,
          // NEW: Structured highlights
          'job_highlights' => $job_data['job_highlights'] ?? [],
          // NEW: Multiple application links
          'apply_options' => $job_data['apply_options'] ?? [],
          // Store complete raw data for future use
          'raw_data' => $job_data,
        ];
      }
    }
    catch (\Exception $e) {
      $this->logger->error('❌ SerpAPI search failed: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return [
      'results' => $results,
      'pagination' => $pagination,
    ];
  }

  /**
   * Store search results for analytics and caching.
   *
   * Stores both search metadata AND individual job results so they can be:
   * - Searched later without hitting external APIs
   * - Imported into jobhunter_job_requirements via cron
   * - Used for analytics and trending job data
   *
   * @param array $params
   *   Search parameters used.
   * @param array $results
   *   Results found.
   */
  protected function storeSearchResults(array $params, array $results): void {
    try {
      // Store search history metadata
      $search_history_id = $this->database->insert('jobhunter_search_history')
        ->fields([
          'uid' => $this->currentUser->id(),
          'search_query' => $params['query'] ?? '',
          'location' => $params['location'] ?? '',
          'sources' => implode(',', $params['sources'] ?? []),
          'results_count' => count($results),
          'created' => time(),
        ])
        ->execute();

      $this->logger->info('💾 Stored search history (ID @id): @count results', [
        '@id' => $search_history_id,
        '@count' => count($results),
      ]);

      // Store individual job results for caching and future import
      if (!empty($results)) {
        $stored_count = 0;
        foreach ($results as $position => $result) {
          try {
            // Only store external API results (not Forseti DB results which are already stored)
            if (isset($result['source']) && $result['source'] !== 'Forseti Jobs') {
              $this->database->insert('jobhunter_job_search_results')
                ->fields([
                  'search_query_id' => $search_history_id,
                  'external_job_id' => $result['id'] ?? uniqid('job_'),
                  'job_title' => substr($result['title'] ?? '', 0, 255),
                  'company_name' => substr($result['company'] ?? '', 0, 255),
                  'location' => substr($result['location'] ?? '', 0, 255),
                  'job_data_json' => json_encode($result),
                  'rank_position' => $position + 1,
                  'imported_to_job_id' => NULL,
                  'imported_at' => NULL,
                  'imported_by_uid' => NULL,
                  'created' => time(),
                ])
                ->execute();
              $stored_count++;
            }
          }
          catch (\Exception $e) {
            // Log but continue storing other results
            $this->logger->warning('⚠️ Failed to store job result @title: @error', [
              '@title' => $result['title'] ?? 'unknown',
              '@error' => $e->getMessage(),
            ]);
          }
        }

        $this->logger->info('💾 Stored @count individual job results for future import', [
          '@count' => $stored_count,
        ]);
      }
    }
    catch (\Exception $e) {
      // Non-critical - log but don't fail the search
      $this->logger->warning('⚠️ Failed to store search results: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Generate diagnostic information when no results found.
   *
   * @param array $sources
   *   Sources that were searched.
   *
   * @return array
   *   Diagnostic information.
   */
  protected function generateDiagnostics(array $sources): array {
    $diagnostics = [];

    // Check Forseti database total
    try {
      $forseti_total = $this->database->select('jobhunter_job_requirements', 'j')
        ->countQuery()
        ->execute()
        ->fetchField();
      $diagnostics['forseti_total'] = $forseti_total;
    }
    catch (\Exception $e) {
      $diagnostics['forseti_error'] = 'Error checking database';
    }

    // Check Google Cloud if it was searched
    if (in_array('google_cloud', $sources)) {
      try {
        $config = $this->configFactory->get('job_hunter.settings');
        $google_credentials = $config->get('google_cloud_credentials');

        if (!empty($google_credentials)) {
          $diagnostic_check = $this->googleCloudService->testSimpleSearch();
          $diagnostics['google_cloud_total'] = $diagnostic_check['total_size'] ?? 0;
        }
        else {
          $diagnostics['google_cloud_error'] = 'Not configured (no credentials)';
        }
      }
      catch (\Exception $e) {
        $diagnostics['google_cloud_error'] = 'Service error';
      }
    }

    return $diagnostics;
  }

  /**
   * Format salary range for display.
   *
   * @param int|null $min
   *   Minimum salary.
   * @param int|null $max
   *   Maximum salary.
   *
   * @return string
   *   Formatted salary range.
   */
  protected function formatSalaryRange($min, $max): string {
    if (!empty($min) && !empty($max)) {
      return '$' . number_format($min) . '-$' . number_format($max);
    }
    elseif (!empty($min)) {
      return '$' . number_format($min) . '+';
    }
    elseif (!empty($max)) {
      return 'Up to $' . number_format($max);
    }
    return 'Not specified';
  }

  /**
   * Truncate text to specified length.
   *
   * @param string $text
   *   Text to truncate.
   * @param int $length
   *   Maximum length.
   *
   * @return string
   *   Truncated text.
   */
  protected function truncateText(string $text, int $length): string {
    if (strlen($text) <= $length) {
      return $text;
    }
    return substr($text, 0, $length) . '...';
  }

  /**
   * Generate content-based hash for job deduplication.
   *
   * Creates MD5 hash from normalized company, title, and location.
   * Same job from different sources will generate same hash.
   *
   * @param string $company
   *   Company name.
   * @param string $title
   *   Job title.
   * @param string $location
   *   Job location.
   *
   * @return string
   *   32-character MD5 hash for deduplication.
   */
  protected function generateJobHash(string $company, string $title, string $location): string {
    // Normalize company name: lowercase, remove common suffixes
    $normalized_company = strtolower(trim($company));
    $normalized_company = preg_replace('/\b(inc|llc|ltd|corp|corporation|company|co)\b\.?/i', '', $normalized_company);
    $normalized_company = trim(preg_replace('/\s+/', ' ', $normalized_company));

    // Normalize title and location: lowercase, trim whitespace
    $normalized_title = strtolower(trim($title));
    $normalized_location = strtolower(trim($location));

    // Generate hash from normalized values
    return md5($normalized_company . '|' . $normalized_title . '|' . $normalized_location);
  }

  /**
   * Convert date_posted string to number of days.
   *
   * @param string $date_posted
   *   Date posted filter value.
   *
   * @return int|null
   *   Number of days, or null if invalid.
   */
  protected function convertDatePostedToDays(string $date_posted): ?int {
    $map = [
      'today' => 1,
      'last_3_days' => 3,
      'last_week' => 7,
      'last_14_days' => 14,
      'last_month' => 30,
    ];
    return $map[$date_posted] ?? null;
  }

  /**
   * Import recent unimported external job results immediately.
   * 
   * This makes external API results immediately searchable in Forseti DB
   * instead of waiting for cron to import them.
   */
  protected function importRecentResults(): void {
    try {
      // Get unimported results from last hour (just stored)
      $results = $this->database->select('jobhunter_job_search_results', 'r')
        ->fields('r')
        ->isNull('imported_to_job_id')
        ->condition('created', time() - 3600, '>')
        ->execute()
        ->fetchAll();

      if (empty($results)) {
        return;
      }

      $imported = 0;
      $skipped = 0;

      foreach ($results as $result) {
        $job_data = json_decode($result->job_data_json, TRUE);
        
        if (empty($job_data)) {
          $skipped++;
          continue;
        }

        // Check for duplicates using job_hash
        $job_hash = $job_data['job_hash'] ?? NULL;
        if ($job_hash) {
          $existing = $this->database->select('jobhunter_job_requirements', 'j')
            ->fields('j', ['id'])
            ->condition('job_hash', $job_hash)
            ->execute()
            ->fetchField();

          if ($existing) {
            // Mark as imported (duplicate)
            $this->database->update('jobhunter_job_search_results')
              ->fields(['imported_to_job_id' => $existing, 'imported_at' => time()])
              ->condition('id', $result->id)
              ->execute();
            $skipped++;
            continue;
          }
        }

        // Get or create company
        $company_id = 1;
        if (!empty($job_data['company'])) {
          $existing_company = $this->database->select('jobhunter_companies', 'c')
            ->fields('c', ['id'])
            ->condition('company_name', $job_data['company'])
            ->execute()
            ->fetchField();

          if ($existing_company) {
            $company_id = $existing_company;
          }
        }

        // Map source
        $source_map = [
          'Google Jobs' => 'google_cloud',
          'Adzuna' => 'adzuna',
          'USAJobs' => 'usajobs',
          'Google Jobs (SerpAPI)' => 'serpapi',
        ];
        $external_source = $source_map[$job_data['source']] ?? 'external_api';

        // Insert into main table
        $new_job_id = $this->database->insert('jobhunter_job_requirements')
          ->fields([
            'company_id' => $company_id,
            'job_title' => $job_data['title'] ?? 'Unknown',
            'job_description' => $job_data['description'] ?? '',
            'requirements' => '',
            'salary_range' => $job_data['salary_range'] ?? 'Not specified',
            'location' => $job_data['location'] ?? 'Unknown',
            'remote_option' => (stripos($job_data['location'] ?? '', 'remote') !== FALSE) ? 'remote' : 'onsite',
            'employment_type' => $job_data['employment_type'] ?? 'Full-time',
            'job_url' => $job_data['url'] ?? '',
            'status' => 'active',
            'created' => time(),
            'updated' => time(),
            'external_source' => $external_source,
            'external_job_id' => $result->external_job_id,
            'job_hash' => $job_hash,
            'ai_extraction_status' => 'pending',
            'via' => $job_data['via'] ?? NULL,
            'thumbnail' => $job_data['thumbnail'] ?? NULL,
            'share_link' => $job_data['share_link'] ?? NULL,
            'work_from_home' => !empty($job_data['work_from_home']) ? 1 : 0,
            'health_insurance' => !empty($job_data['health_insurance']) ? 1 : 0,
            'dental_coverage' => !empty($job_data['dental_coverage']) ? 1 : 0,
            'paid_time_off' => !empty($job_data['paid_time_off']) ? 1 : 0,
            'job_highlights' => !empty($job_data['job_highlights']) ? json_encode($job_data['job_highlights']) : NULL,
            'apply_options' => !empty($job_data['apply_options']) ? json_encode($job_data['apply_options']) : NULL,
          ])
          ->execute();

        // Mark as imported
        $this->database->update('jobhunter_job_search_results')
          ->fields([
            'imported_to_job_id' => $new_job_id,
            'imported_at' => time(),
            'imported_by_uid' => $this->currentUser->id(),
          ])
          ->condition('id', $result->id)
          ->execute();

        $imported++;
      }

      if ($imported > 0) {
        $this->logger->info('⚡ Immediately imported @count external job results into Forseti DB', [
          '@count' => $imported,
        ]);
      }
    }
    catch (\Exception $e) {
      // Non-critical - log but don't fail
      $this->logger->warning('⚠️ Failed to import recent results: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

}
