<?php

namespace Drupal\job_hunter\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for interacting with SerpAPI to scrape Google Jobs results.
 *
 * SerpAPI provides access to Google Jobs search results and other search engines.
 * Get your free API key at: https://serpapi.com/users/sign_up
 * Free tier: 100 searches per month
 */
class SerpApiService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * SerpAPI base URL.
   */
  const API_BASE_URL = 'https://serpapi.com/search';

  /**
   * Constructs a SerpApiService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory) {
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
  }

  /**
   * Search for jobs using SerpAPI Google Jobs engine.
   *
   * @param array $params
   *   Search parameters including:
   *   - query: Job search keywords (required)
   *   - location: Location to search in (optional)
   *   - employment_type: Employment type filter (optional)
   *   - page: Page number (optional, default 1)
   *   - results_per_page: Number of results (optional, max 100, default 10)
   *
   * @return array
   *   Array containing:
   *   - jobs: Array of job listings
   *   - total: Total number of results
   *   - page: Current page number
   */
  public function searchJobs(array $params) {
    $config = $this->configFactory->get('job_hunter.settings');
    $api_key = $config->get('serpapi_api_key');

    if (empty($api_key)) {
      $this->loggerFactory->get('job_hunter')->warning('SerpAPI API key not configured');
      return ['jobs' => [], 'total' => 0, 'page' => 1];
    }

    // Build query parameters
    $query_params = [
      'engine' => 'google_jobs',
      'api_key' => $api_key,
      'q' => $params['query'] ?? '',
      'num' => $params['results_per_page'] ?? 10,
    ];

    // Add location if provided
    if (!empty($params['location'])) {
      $query_params['location'] = $params['location'];
    }

    // Add employment type filter if provided
    if (!empty($params['employment_type'])) {
      // Map our employment types to Google Jobs filters
      $type_mapping = [
        'FULL_TIME' => 'FULLTIME',
        'PART_TIME' => 'PARTTIME',
        'CONTRACT' => 'CONTRACTOR',
        'TEMPORARY' => 'TEMPORARY',
        'INTERN' => 'INTERN',
      ];
      
      if (isset($type_mapping[$params['employment_type']])) {
        $query_params['chips'] = 'employment_type:' . $type_mapping[$params['employment_type']];
      }
    }

    // Add pagination
    if (!empty($params['page']) && $params['page'] > 1) {
      $query_params['start'] = ($params['page'] - 1) * ($params['results_per_page'] ?? 10);
    }

    $this->loggerFactory->get('job_hunter')->info('🔍 SerpAPI Google Jobs search: @params', [
      '@params' => print_r($query_params, TRUE),
    ]);

    try {
      $response = $this->httpClient->get(self::API_BASE_URL, [
        'query' => $query_params,
        'timeout' => 30,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (isset($data['error'])) {
        $this->loggerFactory->get('job_hunter')->error('SerpAPI error: @error', [
          '@error' => $data['error'],
        ]);
        return ['jobs' => [], 'total' => 0, 'page' => $params['page'] ?? 1];
      }

      $jobs = $data['jobs_results'] ?? [];
      
      $this->loggerFactory->get('job_hunter')->info('✅ SerpAPI returned @count jobs', [
        '@count' => count($jobs),
      ]);

      return [
        'jobs' => $jobs,
        'total' => count($jobs), // SerpAPI doesn't provide total count easily
        'page' => $params['page'] ?? 1,
      ];

    } catch (RequestException $e) {
      $error_message = $e->getMessage();
      if ($e->hasResponse()) {
        $error_message .= ' - Response: ' . $e->getResponse()->getBody()->getContents();
      }
      
      $this->loggerFactory->get('job_hunter')->error('SerpAPI request failed: @error', [
        '@error' => $error_message,
      ]);
      
      return ['jobs' => [], 'total' => 0, 'page' => $params['page'] ?? 1];
    }
  }

}
