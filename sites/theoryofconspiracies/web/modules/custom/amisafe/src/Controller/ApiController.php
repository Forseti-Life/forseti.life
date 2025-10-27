<?php

namespace Drupal\amisafe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\amisafe\Service\CrimeDataService;
use Drupal\amisafe\Service\H3AggregatorService;
use Drupal\amisafe\Service\SpatialAnalyzerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API controller for crime data endpoints.
 */
class ApiController extends ControllerBase {

  protected $crimeDataService;
  protected $h3AggregatorService;
  protected $spatialAnalyzerService;

  public function __construct(CrimeDataService $crime_data_service, H3AggregatorService $h3_aggregator_service, SpatialAnalyzerService $spatial_analyzer_service) {
    $this->crimeDataService = $crime_data_service;
    $this->h3AggregatorService = $h3_aggregator_service;
    $this->spatialAnalyzerService = $spatial_analyzer_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('amisafe.crime_data'),
      $container->get('amisafe.h3_aggregator'),
      $container->get('amisafe.spatial_analyzer')
    );
  }

  /**
   * Returns filtered incident data.
   */
  public function incidents(Request $request) {
    $filters = $this->parseFilters($request);
    $page = $request->query->get('page', 0);
    $limit = min($request->query->get('limit', 1000), 5000); // Max 5000 records

    try {
      $incidents = $this->crimeDataService->getIncidents($filters, $page, $limit);
      $total = $this->crimeDataService->getIncidentCount($filters);

      return new JsonResponse([
        'incidents' => $incidents,
        'meta' => [
          'total' => $total,
          'page' => $page,
          'limit' => $limit,
          'filters' => $filters,
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('API incidents error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'error' => 'Failed to fetch incident data',
        'message' => $e->getMessage(),
        'incidents' => [],
        'meta' => ['total' => 0, 'page' => $page, 'limit' => $limit],
      ], 500);
    }
  }

  /**
   * Returns H3 aggregated data.
   */
  public function aggregated(Request $request) {
    $filters = $this->parseFilters($request);
    $resolution = $request->query->get('resolution', 9);
    $bounds = $this->parseBounds($request);

    try {
      $aggregated_data = $this->h3AggregatorService->getAggregatedData($filters, $resolution, $bounds);

      return new JsonResponse([
        'hexagons' => $aggregated_data,
        'meta' => [
          'resolution' => $resolution,
          'bounds' => $bounds,
          'filters' => $filters,
          'count' => count($aggregated_data),
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('API aggregated error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'error' => 'Failed to fetch aggregated data',
        'message' => $e->getMessage(),
        'hexagons' => [],
        'meta' => ['resolution' => $resolution, 'count' => 0],
      ], 500);
    }
  }

  /**
   * Returns crime hotspot analysis.
   */
  public function hotspots(Request $request) {
    $filters = $this->parseFilters($request);
    $resolution = $request->query->get('resolution', 9);
    $threshold = $request->query->get('threshold', 10);

    try {
      $hotspots = $this->spatialAnalyzerService->getHotspots($filters, $resolution, $threshold);

      return new JsonResponse([
        'hotspots' => $hotspots,
        'meta' => [
          'resolution' => $resolution,
          'threshold' => $threshold,
          'filters' => $filters,
          'count' => count($hotspots),
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('API hotspots error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'error' => 'Failed to fetch hotspot data',
        'message' => $e->getMessage(),
        'hotspots' => [],
        'meta' => ['resolution' => $resolution, 'count' => 0],
      ], 500);
    }
  }

  /**
   * Returns police districts for filtering.
   */
  public function districts(Request $request) {
    try {
      // Get district list for filtering dropdown
      $districts = $this->crimeDataService->getDistricts();

      return new JsonResponse([
        'districts' => $districts,
        'meta' => [
          'count' => count($districts),
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('API districts error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'error' => 'Failed to fetch districts',
        'message' => $e->getMessage(),
        'districts' => [],
        'meta' => ['count' => 0],
      ], 500);
    }
  }

  /**
   * Parse filters from request.
   */
  private function parseFilters(Request $request) {
    $filters = [];

    // Date range
    if ($request->query->has('start_date')) {
      $filters['start_date'] = $request->query->get('start_date');
    }
    if ($request->query->has('end_date')) {
      $filters['end_date'] = $request->query->get('end_date');
    }

    // Crime types
    if ($request->query->has('crime_types')) {
      $filters['crime_types'] = explode(',', $request->query->get('crime_types'));
    }

    // Districts
    if ($request->query->has('districts')) {
      $filters['districts'] = explode(',', $request->query->get('districts'));
    }

    // Time of day
    if ($request->query->has('hour_start')) {
      $filters['hour_start'] = $request->query->get('hour_start');
    }
    if ($request->query->has('hour_end')) {
      $filters['hour_end'] = $request->query->get('hour_end');
    }

    // Severity
    if ($request->query->has('severity_min')) {
      $filters['severity_min'] = $request->query->get('severity_min');
    }
    if ($request->query->has('severity_max')) {
      $filters['severity_max'] = $request->query->get('severity_max');
    }

    return $filters;
  }

  /**
   * Parse map bounds from request.
   */
  private function parseBounds(Request $request) {
    if (!$request->query->has('bounds')) {
      return null;
    }

    $bounds_string = $request->query->get('bounds');
    $bounds_array = explode(',', $bounds_string);
    
    if (count($bounds_array) === 4) {
      return [
        'north' => floatval($bounds_array[0]),
        'east' => floatval($bounds_array[1]),
        'south' => floatval($bounds_array[2]),
        'west' => floatval($bounds_array[3]),
      ];
    }

    return null;
  }

  /**
   * Returns available crime types for filtering.
   */
  public function crimeTypes() {
    try {
      $crime_types = $this->crimeDataService->getCrimeTypes();

      return new JsonResponse([
        'crime_types' => $crime_types,
        'meta' => [
          'count' => count($crime_types),
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('API crime types error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'error' => 'Failed to fetch crime types',
        'message' => $e->getMessage(),
        'crime_types' => [],
      ], 500);
    }
  }



  /**
   * Returns date range of available data.
   */
  public function dateRange() {
    try {
      $date_range = $this->crimeDataService->getDateRange();

      return new JsonResponse([
        'date_range' => $date_range,
        'meta' => [
          'min_date' => $date_range['min'],
          'max_date' => $date_range['max'],
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('API date range error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'error' => 'Failed to fetch date range',
        'message' => $e->getMessage(),
        'date_range' => ['min' => '2025-01-01', 'max' => '2025-12-31'],
      ], 500);
    }
  }

}