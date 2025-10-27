<?php

namespace Drupal\amisafe\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Service for accessing crime data from the amisafe database.
 */
class CrimeDataService {

  /**
   * Cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(CacheBackendInterface $cache, LoggerChannelFactory $logger_factory) {
    $this->cache = $cache;
    $this->logger = $logger_factory->get('amisafe');
  }

  /**
   * Get the amisafe database connection.
   */
  protected function getDatabase() {
    try {
      return \Drupal\Core\Database\Database::getConnection('default', 'amisafe');
    } catch (\Exception $e) {
      $this->logger->error('Failed to connect to amisafe database: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw new \Exception('Database connection failed: ' . $e->getMessage());
    }
  }

  /**
   * Get filtered incident data.
   */
  public function getIncidents($filters = [], $page = 0, $limit = 1000) {
    $cache_key = 'amisafe:incidents:' . md5(serialize($filters) . $page . $limit);
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    try {
      $database = \Drupal\Core\Database\Database::getConnection('default', 'amisafe');
      $query = $database->select('raw_incidents', 'ri')
        ->fields('ri')
        ->range($page * $limit, $limit)
        ->orderBy('dispatch_date_time', 'DESC');

      $this->applyFilters($query, $filters);

      $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      
      // Process results for frontend consumption
      $processed_results = array_map([$this, 'processIncident'], $results);
      
      // Cache for 5 minutes
      $this->cache->set($cache_key, $processed_results, time() + 300);
      
      return $processed_results;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching incidents: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Get count of incidents matching filters.
   */
  public function getIncidentCount($filters = []) {
    $cache_key = 'amisafe:incident_count:' . md5(serialize($filters));
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    try {
      $database = \Drupal\Core\Database\Database::getConnection('default', 'amisafe');
      $query = $database->select('raw_incidents', 'ri')
        ->addExpression('COUNT(*)', 'count');

      $this->applyFilters($query, $filters);

      $result = $query->execute()->fetchField();
      
      // Cache for 10 minutes
      $this->cache->set($cache_key, $result, time() + 600);
      
      return $result;
    } catch (\Exception $e) {
      $this->logger->error('Error counting incidents: @message', [
        '@message' => $e->getMessage(),
      ]);
      return 0;
    }
  }

  /**
   * Get available crime types.
   */
  public function getCrimeTypes() {
    $cache_key = 'amisafe:crime_types';
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    try {
      $database = \Drupal\Core\Database\Database::getConnection('default', 'amisafe');
      $query = $database->select('raw_incidents', 'ri')
        ->fields('ri', ['ucr_general', 'text_general_code'])
        ->groupBy('ucr_general')
        ->groupBy('text_general_code')
        ->orderBy('ucr_general')
        ->range(0, 50); // Limit to avoid too many results

      $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      
      $crime_types = [];
      foreach ($results as $result) {
        if (!empty($result['ucr_general']) && !empty($result['text_general_code'])) {
          $crime_types[] = [
            'code' => $result['ucr_general'],
            'name' => $result['text_general_code'],
            'severity' => $this->getCrimeSeverity($result['ucr_general']),
            'color' => $this->getCrimeColor($result['ucr_general']),
          ];
        }
      }
      
      // Cache for 1 hour
      $this->cache->set($cache_key, $crime_types, time() + 3600);
      
      return $crime_types;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching crime types: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      // Return fallback data
      return [
        ['code' => '100', 'name' => 'Homicide', 'severity' => 5, 'color' => '#ff0000'],
        ['code' => '200', 'name' => 'Robbery', 'severity' => 4, 'color' => '#ff8800'],
        ['code' => '300', 'name' => 'Aggravated Assault', 'severity' => 4, 'color' => '#ffff00'],
        ['code' => '400', 'name' => 'Burglary', 'severity' => 3, 'color' => '#00ff00'],
        ['code' => '500', 'name' => 'Theft', 'severity' => 2, 'color' => '#00ffff'],
        ['code' => '600', 'name' => 'Auto Theft', 'severity' => 3, 'color' => '#0088ff'],
      ];
    }
  }

  /**
   * Get police districts.
   */
  public function getDistricts() {
    $cache_key = 'amisafe:districts';
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    try {
      $database = \Drupal\Core\Database\Database::getConnection('default', 'amisafe');
      $query = $database->select('raw_incidents', 'ri')
        ->fields('ri', ['dc_dist'])
        ->groupBy('dc_dist')
        ->orderBy('dc_dist');

      $results = $query->execute()->fetchCol();
      
      $districts = array_filter($results, function($district) {
        return !empty($district) && is_numeric($district);
      });
      
      sort($districts, SORT_NUMERIC);
      
      // Cache for 1 hour
      $this->cache->set($cache_key, array_values($districts), time() + 3600);
      
      return array_values($districts);
    } catch (\Exception $e) {
      $this->logger->error('Error fetching districts: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      // Return fallback data
      return ['1', '2', '3', '5', '6', '7', '8', '9', '12', '14', '15', '16', '17', '18', '19', '22', '24', '25', '26', '35', '39'];
    }
  }

  /**
   * Get date range of available data.
   */
  public function getDateRange() {
    // Simplified version for debugging
    try {
      $database = \Drupal\Core\Database\Database::getConnection('default', 'amisafe');
      $result = $database->query('SELECT MIN(dispatch_date_time) as min_date, MAX(dispatch_date_time) as max_date FROM raw_incidents')->fetchAssoc();
      
      return [
        'min' => $result['min_date'],
        'max' => $result['max_date'],
      ];
    } catch (\Exception $e) {
      $this->logger->error('Error fetching date range: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [
        'min' => '2025-01-01',
        'max' => '2025-12-31',
      ];
    }
  }

  /**
   * Get available crime types for filtering.
   */
  public function getCrimeTypes() {
    try {
      $database = \Drupal\Core\Database\Database::getConnection('default', 'amisafe');
      $result = $database->query('SELECT DISTINCT ucr_code, ucr_description FROM crime_incidents WHERE ucr_code IS NOT NULL AND ucr_description IS NOT NULL ORDER BY ucr_code');
      
      $crime_types = [];
      foreach ($result as $row) {
        $crime_types[$row->ucr_code] = $row->ucr_description;
      }
      
      return $crime_types;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching crime types: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      // Return common Philadelphia crime types as fallback
      return [
        '100' => 'Murder',
        '200' => 'Rape',
        '300' => 'Robbery - Total',
        '400' => 'Aggravated Assault - Total',
        '500' => 'Burglary - Total',
        '600' => 'Theft from Vehicle',
        '700' => 'All Other Larceny',
        '800' => 'Vandalism',
        '900' => 'Fraud',
        '1000' => 'Embezzlement',
        '1100' => 'Narcotic Drug Law Violations',
        '1200' => 'Weapons Violations',
        '1300' => 'Prostitution',
        '1400' => 'Other Assaults',
        '1500' => 'Arson',
        '1600' => 'Stolen Property',
        '1700' => 'DUI',
        '1800' => 'Liquor Laws',
        '2000' => 'Public Drunkenness',
        '2100' => 'Disorderly Conduct',
        '2600' => 'Theft from Person',
      ];
    }
  }

  /**
   * Get district boundaries (placeholder for future implementation).
   */
  public function getDistrictBoundaries() {
    // For now, return empty array - this would normally query a districts table
    return [];
  }

  /**
   * Apply filters to a query.
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
   * Process incident data for frontend consumption.
   */
  private function processIncident($incident) {
    return [
      'id' => $incident['id'],
      'h3_index' => $incident['h3_index'],
      'lat' => floatval($incident['lat']),
      'lng' => floatval($incident['lng']),
      'crime_type' => $incident['ucr_general'],
      'description' => $incident['text_general_code'],
      'datetime' => $incident['dispatch_date_time'],
      'district' => $incident['dc_dist'],
      'block' => $incident['location_block'],
      'hour' => $incident['hour'],
      'severity' => $this->getCrimeSeverity($incident['ucr_general']),
    ];
  }

  /**
   * Get crime severity score.
   */
  private function getCrimeSeverity($ucr_code) {
    $severity_map = [
      '100' => 5, // Homicide
      '200' => 4, // Robbery
      '300' => 4, // Aggravated Assault
      '400' => 3, // Burglary
      '500' => 2, // Theft
      '600' => 3, // Auto Theft
      '700' => 1, // Other
    ];
    
    $code_prefix = substr($ucr_code, 0, 1) . '00';
    return $severity_map[$code_prefix] ?? 2;
  }

  /**
   * Get crime color for visualization.
   */
  private function getCrimeColor($ucr_code) {
    $color_map = [
      '100' => '#ff0000', // Red - Homicide
      '200' => '#ff8800', // Orange - Robbery
      '300' => '#ffff00', // Yellow - Assault
      '400' => '#00ff00', // Green - Burglary
      '500' => '#00ffff', // Cyan - Theft
      '600' => '#0088ff', // Blue - Auto Theft
      '700' => '#888888', // Gray - Other
    ];
    
    $code_prefix = substr($ucr_code, 0, 1) . '00';
    return $color_map[$code_prefix] ?? '#888888';
  }

}