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
   * Get pre-computed aggregated data from H3 aggregation table.
   */
  private function getRealAggregatedData($filters = [], $resolution = 9, $bounds = null) {
    try {
      // Get database connection (use default Drupal database)
      $database = \Drupal\Core\Database\Database::getConnection();
      
      // Query pre-computed H3 aggregation data
      $query = $database->select('amisafe_h3_aggregated', 'h3')
        ->fields('h3', [
          'h3_index', 
          'center_lat', 
          'center_lng', 
          'boundary_json',
          'crime_count',
          'crime_types_json',
          'is_empty'
        ])
        ->condition('h3_resolution', $resolution);
      
      // Apply geographic bounds if provided
      if ($bounds && isset($bounds['north'], $bounds['south'], $bounds['east'], $bounds['west'])) {
        $query->condition('center_lat', $bounds['south'], '>=');
        $query->condition('center_lat', $bounds['north'], '<=');
        $query->condition('center_lng', $bounds['west'], '>=');
        $query->condition('center_lng', $bounds['east'], '<=');
      }
      
      // For high resolutions, limit results to avoid overwhelming the client
      if ($resolution >= 12) {
        // For extreme detail levels, prioritize hexagons with data
        $query->orderBy('is_empty', 'ASC');  // Show data hexagons first
        $query->orderBy('crime_count', 'DESC'); // Then by crime count
        $query->range(0, 5000); // Limit for performance
      } else {
        // For lower resolutions, show all hexagons
        $query->range(0, 10000);
      }
      
      $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      
      if (empty($results)) {
        return [];
      }
      
      // Convert pre-computed data to expected format
      $hexagon_data = [];
      foreach ($results as $hexagon) {
        $h3_index = $hexagon['h3_index'];
        $boundary = json_decode($hexagon['boundary_json'], true);
        $crime_types = json_decode($hexagon['crime_types_json'], true) ?: [];
        
        // Build hexagon data structure
        $hexagon_item = [
          'h3_index' => $h3_index,
          'lat' => floatval($hexagon['center_lat']),
          'lng' => floatval($hexagon['center_lng']),
          'crime_count' => intval($hexagon['crime_count']),
          'total_incidents' => intval($hexagon['crime_count']),
          'crime_types' => array_keys($crime_types),
          'crime_type_counts' => $crime_types,
          'boundary' => $boundary,
          'is_empty' => (bool)$hexagon['is_empty'],
          'resolution' => $resolution,
          'severity_avg' => $this->calculateSeverity(array_keys($crime_types)),
          'last_incident' => date('Y-m-d H:i:s') // Mock timestamp for now
        ];
        
        // Apply client-side filters if needed
        if ($this->passesFilters($hexagon_item, $filters)) {
          $hexagon_data[$h3_index] = $hexagon_item;
        }
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
   * ENHANCED: Now supports 1-meter precision mapping!
   * H3 Resolution System: 0 (continental) → 15 (sub-meter)
   * Maximum Detail: 0.5 meters at zoom level 20+
   */
  public function getOptimalResolution($zoom_level) {
    // Enhanced resolution mapping for extreme detail capability
    if ($zoom_level <= 8)  return 6;   // ~3.1 km - Neighborhoods  
    if ($zoom_level <= 10) return 7;   // ~1.2 km - Large blocks
    if ($zoom_level <= 12) return 8;   // ~460 m - City blocks
    if ($zoom_level <= 14) return 9;   // ~174 m - Street level
    if ($zoom_level <= 16) return 10;  // ~65 m - Building groups
    if ($zoom_level <= 17) return 11;  // ~25 m - Individual buildings
    if ($zoom_level <= 18) return 12;  // ~9 m - Building parts
    if ($zoom_level <= 19) return 13;  // ~3.4 m - Rooms/parking spaces
    if ($zoom_level <= 20) return 14;  // ~1.3 m - NEAR 1-METER DETAIL! 🎯
    return 15;  // ~0.5 m - SUB-METER PRECISION! ⚡
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
   * Apply filters to a database query (legacy method - kept for compatibility).
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
   * Check if a pre-computed hexagon passes the client filters.
   */
  private function passesFilters($hexagon, $filters) {
    // If no filters, all hexagons pass
    if (empty($filters)) {
      return true;
    }
    
    // Filter by crime types
    if (!empty($filters['crime_types'])) {
      $requested_types = is_array($filters['crime_types']) ? $filters['crime_types'] : explode(',', $filters['crime_types']);
      $hexagon_types = $hexagon['crime_types'];
      
      // Check if hexagon has any of the requested crime types
      $has_matching_type = false;
      foreach ($requested_types as $requested_type) {
        if (in_array(trim($requested_type), $hexagon_types)) {
          $has_matching_type = true;
          break;
        }
      }
      
      if (!$has_matching_type && !$hexagon['is_empty']) {
        return false; // Skip hexagons without matching crime types (but include empty ones for grid completeness)
      }
    }
    
    // For now, we'll keep date/time filtering simple since pre-computed data aggregates over time
    // Future enhancement: Add time-based filtering by storing temporal data in aggregation
    
    return true;
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