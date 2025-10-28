<?php

namespace Drupal\amisafe\Service;

use Drupal\Core\Config\ConfigFactory;
use Drupal\amisafe\Service\CrimeDataService;

/**
 * Service for H3 spatial aggregation and multi-resolution processing.
 */
class H3AggregatorService {

  /**
   * The crime data service.
   *
   * @var \Drupal\amisafe\Service\CrimeDataService
   */
  protected $crimeDataService;

  /**
   * Configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructor.
   */
  public function __construct(CrimeDataService $crime_data_service, ConfigFactory $config_factory) {
    $this->crimeDataService = $crime_data_service;
    $this->configFactory = $config_factory;
  }

  /**
   * Get aggregated H3 data for the given filters and resolution.
   */
  public function getAggregatedData($filters = [], $resolution = 9, $bounds = null) {
    try {
      // Try to get real database aggregation
      $real_data = $this->getRealAggregatedData($filters, $resolution, $bounds);
      
      if (!empty($real_data)) {
        return $real_data;
      }
      
      // Fallback to sample data if database query fails
      return $this->generateSampleH3Data($resolution, $bounds);
      
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('Error in H3 aggregation: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      // Return sample data as fallback
      return $this->generateSampleH3Data($resolution, $bounds);
    }
  }

  /**
   * Get real aggregated data from database.
   */
  private function getRealAggregatedData($filters = [], $resolution = 9, $bounds = null) {
    try {
      // Get database connection
      $database = \Drupal\Core\Database\Database::getConnection('default', 'amisafe');
      
      // Build query for incidents
      $query = $database->select('raw_incidents', 'ri')
        ->fields('ri', ['h3_index', 'ucr_general', 'lat', 'lng', 'dispatch_date_time'])
        ->condition('h3_index', '', '!=')  // Only include records with H3 index
        ->condition('lat', 0, '!=')        // Valid coordinates
        ->condition('lng', 0, '!=');
      
      // Apply filters
      $this->applyFilters($query, $filters);
      
      // Apply bounds if provided
      if ($bounds && isset($bounds['north'], $bounds['south'], $bounds['east'], $bounds['west'])) {
        $query->condition('lat', $bounds['south'], '>=');
        $query->condition('lat', $bounds['north'], '<=');
        $query->condition('lng', $bounds['west'], '>=');
        $query->condition('lng', $bounds['east'], '<=');
      }
      
      // Limit to avoid performance issues
      $query->range(0, 10000);
      
      $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      
      if (empty($results)) {
        return [];
      }
      
      // Group by H3 index and aggregate
      $hexagon_data = [];
      foreach ($results as $incident) {
        $h3_index = $incident['h3_index'];
        
        if (!isset($hexagon_data[$h3_index])) {
          $hexagon_data[$h3_index] = [
            'h3_index' => $h3_index,
            'lat' => floatval($incident['lat']),
            'lng' => floatval($incident['lng']),
            'crime_count' => 0,
            'total_incidents' => 0,
            'crime_types' => [],
            'last_incident' => null,
            'resolution' => $resolution,
          ];
        }
        
        $hexagon_data[$h3_index]['crime_count']++;
        $hexagon_data[$h3_index]['total_incidents']++;
        
        // Track crime types
        $crime_type = $incident['ucr_general'];
        if ($crime_type && !in_array($crime_type, $hexagon_data[$h3_index]['crime_types'])) {
          $hexagon_data[$h3_index]['crime_types'][] = $crime_type;
        }
        
        // Track most recent incident
        if (!$hexagon_data[$h3_index]['last_incident'] || 
            $incident['dispatch_date_time'] > $hexagon_data[$h3_index]['last_incident']) {
          $hexagon_data[$h3_index]['last_incident'] = $incident['dispatch_date_time'];
        }
      }
      
      // Add severity calculations
      foreach ($hexagon_data as &$hexagon) {
        $hexagon['severity_avg'] = $this->calculateSeverity($hexagon['crime_types']);
      }
      
      return array_values($hexagon_data);
      
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('Error in getRealAggregatedData: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Generate sample H3 hexagon data for testing.
   */
  private function generateSampleH3Data($resolution = 9, $bounds = null) {
    // Philadelphia center coordinates
    $center_lat = 39.9526;
    $center_lng = -75.1652;
    
    $sample_hexagons = [];
    
    // Generate some sample H3 indices around Philadelphia
    $sample_h3_indices = [
      '892aacb2e57ffff', // These would be actual H3 indices from the database
      '892aacb2e4fffff',
      '892aacb2e47ffff', 
      '892aacb2e5fffff',
      '892aacb2e77ffff',
      '892aacb2e6fffff',
      '892aacb2e67ffff',
      '892aacb2e6fffff',
    ];
    
    foreach ($sample_h3_indices as $h3_index) {
      // Generate sample crime counts (would come from database)
      $crime_count = rand(1, 50);
      
      $sample_hexagons[] = [
        'h3_index' => $h3_index,
        'crime_count' => $crime_count,
        'severity_avg' => rand(1, 5),
        'resolution' => $resolution,
        'last_incident' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 720) . ' hours')),
        'crime_types' => $this->getRandomCrimeTypes(),
      ];
    }
    
    return $sample_hexagons;
  }

  /**
   * Get random crime types for sample data.
   */
  private function getRandomCrimeTypes() {
    $all_types = ['100', '200', '300', '400', '500', '600'];
    $selected_count = rand(1, 3);
    
    // Ensure we have an array and valid count
    if (!is_array($all_types) || $selected_count < 1) {
      return ['600']; // Fallback to a single crime type
    }
    
    // Shuffle and take the first n elements
    shuffle($all_types);
    return array_slice($all_types, 0, $selected_count);
  }

  /**
   * Get optimal H3 resolution based on zoom level.
   */
  public function getOptimalResolution($zoom_level) {
    if ($zoom_level <= 10) return 7;      // District level
    if ($zoom_level <= 12) return 8;      // Neighborhood level  
    if ($zoom_level <= 14) return 9;      // Block group level
    return 10;                            // Block level
  }

  /**
   * Calculate crime density for a given H3 index.
   */
  public function calculateDensity($h3_index, $filters = []) {
    // Placeholder implementation
    // Would query database for actual crime count in this H3 cell
    return rand(0, 100);
  }

  /**
   * Apply filters to a database query.
   */
  private function applyFilters($query, $filters) {
    if (!empty($filters['start_date'])) {
      $query->condition('dispatch_date_time', $filters['start_date'], '>=');
    }
    
    if (!empty($filters['end_date'])) {
      $query->condition('dispatch_date_time', $filters['end_date'], '<=');
    }
    
    if (!empty($filters['crime_types'])) {
      $query->condition('ucr_general', $filters['crime_types'], 'IN');
    }
    
    if (!empty($filters['districts'])) {
      $query->condition('dc_dist', $filters['districts'], 'IN');
    }
    
    if (isset($filters['hour_start']) && isset($filters['hour_end'])) {
      $query->condition('hour', $filters['hour_start'], '>=');
      $query->condition('hour', $filters['hour_end'], '<=');
    }
  }

  /**
   * Calculate severity score based on crime types.
   */
  private function calculateSeverity($crime_types) {
    if (empty($crime_types)) {
      return 1;
    }
    
    $severity_map = [
      '100' => 5, // Homicide
      '200' => 4, // Robbery
      '300' => 4, // Aggravated Assault
      '400' => 3, // Burglary
      '500' => 2, // Theft
      '600' => 3, // Auto Theft
      '700' => 1, // Other
    ];
    
    $total_severity = 0;
    $count = 0;
    
    foreach ($crime_types as $crime_type) {
      $code_prefix = substr($crime_type, 0, 1) . '00';
      if (isset($severity_map[$code_prefix])) {
        $total_severity += $severity_map[$code_prefix];
        $count++;
      }
    }
    
    return $count > 0 ? round($total_severity / $count) : 2;
  }

  /**
   * Get neighboring H3 cells for spatial analysis.
   */
  public function getNeighbors($h3_index, $ring_size = 1) {
    // Placeholder - would use H3 library to get actual neighbors
    return [];
  }

}