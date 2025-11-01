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

    // Crime types (comma-separated or array)
    if ($request->query->has('crime_types')) {
      $crime_types = $request->query->get('crime_types');
      if (is_string($crime_types)) {
        $filters['crime_types'] = explode(',', $crime_types);
      } elseif (is_array($crime_types)) {
        $filters['crime_types'] = $crime_types;
      }
    }

    // Districts (comma-separated or array)
    if ($request->query->has('districts')) {
      $districts = $request->query->get('districts');
      if (is_string($districts)) {
        $filters['districts'] = explode(',', $districts);
      } elseif (is_array($districts)) {
        $filters['districts'] = $districts;
      }
    }

    // Severity levels (comma-separated or array)
    if ($request->query->has('severities')) {
      $severities = $request->query->get('severities');
      if (is_string($severities)) {
        $filters['severities'] = explode(',', $severities);
      } elseif (is_array($severities)) {
        $filters['severities'] = $severities;
      }
    }

    // Time periods (from new selector-based system)
    if ($request->query->has('time_periods')) {
      $time_periods = $request->query->get('time_periods');
      if (is_string($time_periods)) {
        $filters['time_periods'] = explode(',', $time_periods);
      } elseif (is_array($time_periods)) {
        $filters['time_periods'] = $time_periods;
      }
    }

    // Legacy time filters (still support hour_start/hour_end for backwards compatibility)
    if ($request->query->has('hour_start')) {
      $filters['hour_start'] = $request->query->get('hour_start');
    }
    if ($request->query->has('hour_end')) {
      $filters['hour_end'] = $request->query->get('hour_end');
    }

    // Legacy severity filters (backwards compatibility)
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

  /**
   * Returns citywide crime statistics for dashboard overview.
   */
  public function citywideStats() {
    try {
      // Get overall statistics from the actual database (real data source)
      $total_incidents = $this->crimeDataService->getIncidentCount([]);
      
      $districts = $this->crimeDataService->getDistricts();
      
      // Calculate citywide threat level based on incident density
      $threat_level = $this->calculateCitywideThreatlevel($total_incidents);
      
      // Calculate coverage percentage (simulated for Philadelphia 2085)
      $coverage_percentage = min(100, ($total_incidents / 500) + 85); // Base 85% + incidents factor
      
      return new JsonResponse([
        'stats' => [
          'total_incidents' => $total_incidents,
          'active_districts' => count($districts),
          'citywide_threat_level' => $threat_level,
          'coverage_percentage' => round($coverage_percentage, 1),
          'last_updated' => date('Y-m-d H:i:s'),
        ],
        'meta' => [
          'districts' => $districts,
          'calculation_method' => 'h3_aggregated_data',
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('Citywide stats API error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      // Return fallback data
      return new JsonResponse([
        'stats' => [
          'total_incidents' => 28750,
          'active_districts' => 21,
          'citywide_threat_level' => 'CRITICAL',
          'coverage_percentage' => 94.2,
          'last_updated' => date('Y-m-d H:i:s'),
        ],
        'meta' => [
          'fallback' => true,
          'error' => 'Using simulated data for Philadelphia 2085',
        ],
      ]);
    }
  }

  /**
   * Calculate citywide threat level based on incident count.
   */
  private function calculateCitywideThreatlevel($incident_count) {
    if ($incident_count >= 30000) {
      return 'EXTREME';
    } elseif ($incident_count >= 20000) {
      return 'CRITICAL';
    } elseif ($incident_count >= 10000) {
      return 'HIGH';
    } elseif ($incident_count >= 5000) {
      return 'ELEVATED';
    } else {
      return 'MODERATE';
    }
  }

  /**
   * Debug endpoint to test basic API functionality.
   */
  public function debugTest() {
    return new JsonResponse([
      'status' => 'API_WORKING',
      'timestamp' => date('Y-m-d H:i:s'),
      'message' => 'Debug test successful'
    ]);
  }

  /**
   * Get detailed information for a specific hexagon.
   */
  public function hexagonDetails(Request $request, $h3_index) {
    try {
      
      // Get filters from request
      $filters = [];
      $data = json_decode($request->getContent(), TRUE);
      if ($data) {
        $filters = $data;
      } else {
        // Fallback to query parameters
        $filters = [
          'crime_types' => $request->query->all('crime_types') ?: [],
          'date_start' => $request->query->get('date_start'),
          'date_end' => $request->query->get('date_end'),
          'districts' => $request->query->all('districts') ?: [],
        ];
      }

      // Get detailed hexagon data
      $hexagon_data = $this->crimeDataService->getHexagonDetails($h3_index, $filters);
      
      if (!$hexagon_data) {
        return new JsonResponse([
          'error' => 'Hexagon not found',
          'h3_index' => $h3_index,
        ], 404);
      }

      // Calculate threat analysis
      $threat_level = $this->calculateThreatLevel($hexagon_data);
      $recommendations = $this->generateRecommendations($hexagon_data, $threat_level);

      return new JsonResponse([
        'h3_index' => $h3_index,
        'hexagon_data' => $hexagon_data,
        'threat_analysis' => [
          'level' => $threat_level,
          'score' => $hexagon_data['severity_avg'] ?? 0,
          'risk_factors' => $this->identifyRiskFactors($hexagon_data),
        ],
        'recommendations' => $recommendations,
        'meta' => [
          'timestamp' => date('Y-m-d H:i:s'),
          'data_quality' => 'HIGH',
          'confidence' => $this->calculateConfidence($hexagon_data),
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('API hexagon details error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'error' => 'Failed to fetch hexagon details',
        'message' => $e->getMessage(),
        'h3_index' => $h3_index,
      ], 500);
    }
  }

  /**
   * Calculate threat level based on hexagon data.
   */
  private function calculateThreatLevel($hexagon_data) {
    $crime_count = $hexagon_data['crime_count'] ?? 0;
    $severity_avg = $hexagon_data['severity_avg'] ?? 0;
    
    // Cyberpunk-style threat calculation
    if ($crime_count >= 50 && $severity_avg >= 4) {
      return 'CRITICAL';
    } elseif ($crime_count >= 25 && $severity_avg >= 3) {
      return 'HIGH';
    } elseif ($crime_count >= 10 && $severity_avg >= 2) {
      return 'MODERATE';
    } elseif ($crime_count >= 1) {
      return 'LOW';
    } else {
      return 'MINIMAL';
    }
  }

  /**
   * Generate security recommendations.
   */
  private function generateRecommendations($hexagon_data, $threat_level) {
    $recommendations = [];
    $crime_types = $hexagon_data['crime_types'] ?? [];
    
    switch ($threat_level) {
      case 'CRITICAL':
        $recommendations[] = 'AVOID AREA - High criminal activity detected';
        $recommendations[] = 'If transit required, use secure vehicle with escort';
        $recommendations[] = 'Implement enhanced surveillance protocols';
        break;
        
      case 'HIGH':
        $recommendations[] = 'Exercise extreme caution in this sector';
        $recommendations[] = 'Travel in groups, avoid night operations';
        $recommendations[] = 'Maintain constant comm link with security';
        break;
        
      case 'MODERATE':
        $recommendations[] = 'Standard security protocols recommended';
        $recommendations[] = 'Stay alert for suspicious activity';
        $recommendations[] = 'Avoid displaying valuable items';
        break;
        
      case 'LOW':
        $recommendations[] = 'Basic precautions sufficient';
        $recommendations[] = 'Monitor local conditions';
        break;
        
      default:
        $recommendations[] = 'Area appears secure - standard vigilance';
    }
    
    // Add crime-specific recommendations
    if (in_array('300', $crime_types) || in_array('2600', $crime_types)) {
      $recommendations[] = 'HIGH THEFT RISK - Secure all possessions';
    }
    if (in_array('400', $crime_types) || in_array('1400', $crime_types)) {
      $recommendations[] = 'VIOLENCE DETECTED - Avoid confrontations';
    }
    if (in_array('1100', $crime_types)) {
      $recommendations[] = 'NARCOTIC ACTIVITY - Potential gang presence';
    }
    
    return $recommendations;
  }

  /**
   * Identify risk factors.
   */
  private function identifyRiskFactors($hexagon_data) {
    $risk_factors = [];
    $crime_types = $hexagon_data['crime_types'] ?? [];
    $crime_count = $hexagon_data['crime_count'] ?? 0;
    
    if ($crime_count > 50) {
      $risk_factors[] = 'High incident density';
    }
    
    if (count($crime_types) > 5) {
      $risk_factors[] = 'Multiple crime categories';
    }
    
    $violent_crimes = array_intersect(['100', '200', '300', '400', '1400'], $crime_types);
    if (!empty($violent_crimes)) {
      $risk_factors[] = 'Violent crime presence';
    }
    
    if (in_array('1100', $crime_types)) {
      $risk_factors[] = 'Drug-related activity';
    }
    
    // Time-based analysis
    $last_incident = strtotime($hexagon_data['last_incident'] ?? '2025-01-01');
    $days_since = (time() - $last_incident) / (24 * 60 * 60);
    
    if ($days_since < 7) {
      $risk_factors[] = 'Recent criminal activity';
    }
    
    return $risk_factors;
  }

  /**
   * Calculate confidence score.
   */
  private function calculateConfidence($hexagon_data) {
    $crime_count = $hexagon_data['crime_count'] ?? 0;
    
    if ($crime_count >= 20) {
      return 'HIGH';
    } elseif ($crime_count >= 5) {
      return 'MEDIUM';
    } else {
      return 'LOW';
    }
  }

}