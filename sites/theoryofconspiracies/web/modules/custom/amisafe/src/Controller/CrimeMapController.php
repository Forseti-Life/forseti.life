<?php

namespace Drupal\amisafe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\amisafe\Service\CrimeDataService;
use Drupal\amisafe\Service\H3AggregatorService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the interactive crime map.
 */
class CrimeMapController extends ControllerBase {

  /**
   * The crime data service.
   *
   * @var \Drupal\amisafe\Service\CrimeDataService
   */
  protected $crimeDataService;

  /**
   * The H3 aggregator service.
   *
   * @var \Drupal\amisafe\Service\H3AggregatorService
   */
  protected $h3AggregatorService;

  /**
   * Constructs a CrimeMapController object.
   */
  public function __construct(CrimeDataService $crime_data_service, H3AggregatorService $h3_aggregator_service) {
    $this->crimeDataService = $crime_data_service;
    $this->h3AggregatorService = $h3_aggregator_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('amisafe.crime_data'),
      $container->get('amisafe.h3_aggregator')
    );
  }

  /**
   * Displays the interactive crime map.
   */
  public function map(Request $request) {
    // Get initial configuration
    $config = $this->config('amisafe.settings');
    $default_zoom = $config->get('map.default_zoom') ?: 11;
    $default_center = $config->get('map.default_center') ?: [39.9526, -75.1652];

    // Try to get data from services, but provide fallbacks if services fail
    try {
      $crime_types = $this->crimeDataService->getCrimeTypes();
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->warning('Failed to load crime types: @message', ['@message' => $e->getMessage()]);
      $crime_types = $this->getDefaultCrimeTypes();
    }

    try {
      $districts = $this->crimeDataService->getDistricts();
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->warning('Failed to load districts: @message', ['@message' => $e->getMessage()]);
      $districts = $this->getDefaultDistricts();
    }

    try {
      $date_range = $this->crimeDataService->getDateRange();
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->warning('Failed to load date range: @message', ['@message' => $e->getMessage()]);
      $date_range = $this->getDefaultDateRange();
    }

    $build = [
      '#theme' => 'amisafe_crime_map',
      '#map_config' => [
        'zoom' => $default_zoom,
        'center' => $default_center,
        'api_endpoints' => [
          'incidents' => '/api/amisafe/incidents',
          'aggregated' => '/api/amisafe/aggregated',
          'hotspots' => '/api/amisafe/hotspots',
          'districts' => '/api/amisafe/districts',
        ],
      ],
      '#crime_types' => $crime_types,
      '#districts' => $districts,
      '#date_range' => $date_range,
      '#attached' => [
        'library' => ['amisafe/crime-map'],
        'drupalSettings' => [
          'amisafe' => [
            'mapConfig' => [
              'zoom' => $default_zoom,
              'center' => $default_center,
            ],
            'apiEndpoints' => [
              'incidents' => '/api/amisafe/incidents',
              'aggregated' => '/api/amisafe/aggregated',
              'hotspots' => '/api/amisafe/hotspots',
              'districts' => '/api/amisafe/districts',
            ],
            'crimeTypes' => $crime_types,
            'districts' => $districts,
            'dateRange' => $date_range,
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * Provides default crime types if database is unavailable.
   */
  private function getDefaultCrimeTypes() {
    return [
      ['code' => '100', 'name' => 'Homicide', 'severity' => 5, 'color' => '#ff0000'],
      ['code' => '200', 'name' => 'Robbery', 'severity' => 4, 'color' => '#ff8800'],
      ['code' => '300', 'name' => 'Aggravated Assault', 'severity' => 4, 'color' => '#ffff00'],
      ['code' => '400', 'name' => 'Burglary', 'severity' => 3, 'color' => '#00ff00'],
      ['code' => '500', 'name' => 'Theft', 'severity' => 2, 'color' => '#00ffff'],
      ['code' => '600', 'name' => 'Auto Theft', 'severity' => 3, 'color' => '#0088ff'],
    ];
  }

  /**
   * Provides default districts if database is unavailable.
   */
  private function getDefaultDistricts() {
    return ['1', '2', '3', '5', '6', '7', '8', '9', '12', '14', '15', '16', '17', '18', '19', '22', '24', '25', '26', '35', '39'];
  }

  /**
   * Provides default date range if database is unavailable.
   */
  private function getDefaultDateRange() {
    return [
      'min' => '2022-01-01 00:00:00',
      'max' => '2025-10-27 23:59:59',
    ];
  }

}