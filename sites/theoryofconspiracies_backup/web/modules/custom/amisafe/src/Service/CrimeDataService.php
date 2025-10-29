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
    try {
      $database = \Drupal\Core\Database\Database::getConnection('default', 'amisafe');
      $query = $database->select('raw_incidents', 'ri');
      $query->addExpression('MIN(incident_date)', 'min_date');
      $query->addExpression('MAX(incident_date)', 'max_date');
      $result = $query->execute()->fetchAssoc();
      
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
      $result = $database->query('SELECT DISTINCT ucr_code, ucr_description FROM raw_incidents WHERE ucr_code IS NOT NULL AND ucr_description IS NOT NULL ORDER BY ucr_code');
      
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
    // Date range filters
    if (!empty($filters['start_date'])) {
      $query->condition('dispatch_date', $filters['start_date'], '>=');
    }
    
    if (!empty($filters['end_date'])) {
      $query->condition('dispatch_date', $filters['end_date'], '<=');
    }
    
    // Crime type filters
    if (!empty($filters['crime_types'])) {
      $query->condition('ucr_general', $filters['crime_types'], 'IN');
    }
    
    // District filters
    if (!empty($filters['districts'])) {
      $query->condition('dc_dist', $filters['districts'], 'IN');
    }
    
    // Severity filters (new selector-based)
    if (!empty($filters['severities'])) {
      // Apply severity filtering based on UCR codes
      $severity_conditions = $query->orConditionGroup();
      foreach ($filters['severities'] as $severity_level) {
        $ucr_codes = $this->getUcrCodesBySeverity($severity_level);
        if (!empty($ucr_codes)) {
          $severity_conditions->condition('ucr_general', $ucr_codes, 'IN');
        }
      }
      if ($severity_conditions->count() > 0) {
        $query->condition($severity_conditions);
      }
    }
    
    // Time period filters (new selector-based)
    if (!empty($filters['time_periods'])) {
      $time_conditions = $query->orConditionGroup();
      foreach ($filters['time_periods'] as $time_period) {
        switch ($time_period) {
          case 'early-morning':
            $time_conditions->condition('dispatch_time', '00:00:00', '>=');
            $time_conditions->condition('dispatch_time', '05:59:59', '<=');
            break;
          case 'morning':
            $time_conditions->condition('dispatch_time', '06:00:00', '>=');
            $time_conditions->condition('dispatch_time', '11:59:59', '<=');
            break;
          case 'afternoon':
            $time_conditions->condition('dispatch_time', '12:00:00', '>=');
            $time_conditions->condition('dispatch_time', '17:59:59', '<=');
            break;
          case 'evening':
            $time_conditions->condition('dispatch_time', '18:00:00', '>=');
            $time_conditions->condition('dispatch_time', '23:59:59', '<=');
            break;
        }
      }
      if ($time_conditions->count() > 0) {
        $query->condition($time_conditions);
      }
    }
    
    // Legacy time filters (backwards compatibility)
    if (isset($filters['hour_start']) && isset($filters['hour_end'])) {
      $query->condition('dispatch_time', sprintf('%02d:00:00', $filters['hour_start']), '>=');
      $query->condition('dispatch_time', sprintf('%02d:59:59', $filters['hour_end']), '<=');
    }
    
    // Legacy severity filters (backwards compatibility)
    if (isset($filters['severity_min']) || isset($filters['severity_max'])) {
      if (isset($filters['severity_min'])) {
        $min_ucr_codes = $this->getUcrCodesBySeverity($filters['severity_min'], 'min');
        if (!empty($min_ucr_codes)) {
          $query->condition('ucr_general', $min_ucr_codes, 'IN');
        }
      }
      if (isset($filters['severity_max'])) {
        $max_ucr_codes = $this->getUcrCodesBySeverity($filters['severity_max'], 'max');
        if (!empty($max_ucr_codes)) {
          $query->condition('ucr_general', $max_ucr_codes, 'IN');
        }
      }
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
      '100' => 5, // Murder
      '200' => 4, // Rape
      '300' => 4, // Robbery
      '400' => 4, // Aggravated Assault
      '500' => 3, // Burglary
      '600' => 2, // Theft from Vehicle
      '700' => 2, // All Other Larceny
      '800' => 2, // Vandalism
      '900' => 2, // Fraud
      '1000' => 2, // Embezzlement
      '1100' => 3, // Narcotic Drug Law Violations
      '1200' => 3, // Weapons Violations
      '1300' => 1, // Prostitution
      '1400' => 2, // Other Assaults
      '1500' => 4, // Arson
      '1600' => 2, // Stolen Property
      '1700' => 2, // DUI
      '1800' => 1, // Liquor Laws
      '2000' => 1, // Public Drunkenness
      '2100' => 1, // Disorderly Conduct
      '2600' => 3, // Theft from Person
    ];
    
    return $severity_map[$ucr_code] ?? 2;
  }

  /**
   * Get UCR codes by severity level.
   */
  private function getUcrCodesBySeverity($severity_level, $mode = 'exact') {
    $severity_to_codes = [
      1 => ['1300', '1800', '2000', '2100'], // Low severity
      2 => ['600', '700', '800', '900', '1000', '1400', '1600', '1700'], // Moderate severity
      3 => ['500', '1100', '1200', '2600'], // High severity
      4 => ['200', '300', '400', '1500'], // Critical severity
      5 => ['100'], // Extreme severity
    ];
    
    switch ($mode) {
      case 'min':
        // Get codes with severity >= specified level
        $codes = [];
        for ($level = $severity_level; $level <= 5; $level++) {
          if (isset($severity_to_codes[$level])) {
            $codes = array_merge($codes, $severity_to_codes[$level]);
          }
        }
        return $codes;
        
      case 'max':
        // Get codes with severity <= specified level
        $codes = [];
        for ($level = 1; $level <= $severity_level; $level++) {
          if (isset($severity_to_codes[$level])) {
            $codes = array_merge($codes, $severity_to_codes[$level]);
          }
        }
        return $codes;
        
      default:
        // Get codes with exact severity level
        return $severity_to_codes[$severity_level] ?? [];
    }
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

  /**
   * Get detailed information for a specific hexagon.
   */
  public function getHexagonDetails($h3_index, array $filters = []) {
    
    try {
      // Get database connection like other methods
      $database = \Drupal\Core\Database\Database::getConnection('default', 'amisafe');
      
      $query = $database->select('raw_incidents', 'ri');
      $query->fields('ri');
      $query->condition('ri.h3_index', $h3_index);

      // Apply filters
      if (!empty($filters['crime_types'])) {
        $query->condition('ri.ucr_general', $filters['crime_types'], 'IN');
      }

      if (!empty($filters['start_date'])) {
        $query->condition('ri.dispatch_date', $filters['start_date'], '>=');
      }

      if (!empty($filters['end_date'])) {
        $query->condition('ri.dispatch_date', $filters['end_date'], '<=');
      }

      if (!empty($filters['districts'])) {
        $query->condition('ri.dc_dist', $filters['districts'], 'IN');
      }

      $results = $query->execute()->fetchAll();

      if (empty($results)) {
        return [
          'h3_index' => $h3_index,
          'incidents' => [],
          'summary' => [
            'total_incidents' => 0,
            'crime_types' => [],
            'severity_avg' => 0,
            'threat_level' => 'MINIMAL',
            'last_incident' => null,
          ]
        ];
      }

      // Process incidents
      $incidents = [];
      $crime_types = [];
      $severities = [];
      $latest_date = null;

      foreach ($results as $row) {
        $ucr_code = $row->ucr_general;
        $severity = $this->calculateSeverityScore($ucr_code);
        $severities[] = $severity;
        
        if (!isset($crime_types[$ucr_code])) {
          $crime_types[$ucr_code] = 0;
        }
        $crime_types[$ucr_code]++;

        if (!$latest_date || $row->dispatch_date > $latest_date) {
          $latest_date = $row->dispatch_date;
        }

        $incidents[] = [
          'incident_id' => $row->id,
          'date' => $row->dispatch_date,
          'time' => $row->dispatch_time ?? '00:00:00',
          'ucr_code' => $ucr_code,
          'crime_type' => $row->text_general_code ?? 'Unknown',
          'address' => $row->location_block ?? 'Address unavailable',
          'district' => $row->dc_dist ?? 'Unknown',
          'severity' => $severity,
          'lat' => $row->lat,
          'lng' => $row->lng,
        ];
      }

      $avg_severity = !empty($severities) ? round(array_sum($severities) / count($severities), 1) : 0;
      
      // Determine threat level
      $threat_level = 'MINIMAL';
      if ($avg_severity >= 4.5) {
        $threat_level = 'EXTREME';
      } elseif ($avg_severity >= 3.5) {
        $threat_level = 'HIGH';
      } elseif ($avg_severity >= 2.5) {
        $threat_level = 'MODERATE';
      } elseif ($avg_severity >= 1.5) {
        $threat_level = 'LOW';
      }

      return [
        'h3_index' => $h3_index,
        'incidents' => $incidents,
        'summary' => [
          'total_incidents' => count($incidents),
          'crime_types' => $crime_types,
          'severity_avg' => $avg_severity,
          'threat_level' => $threat_level,
          'last_incident' => $latest_date,
        ]
      ];

    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('Error getting hexagon details: @message', ['@message' => $e->getMessage()]);
      return [
        'error' => 'Failed to fetch hexagon details',
        'h3_index' => $h3_index,
        'incidents' => [],
        'summary' => [
          'total_incidents' => 0,
          'crime_types' => [],
          'severity_avg' => 0,
          'threat_level' => 'UNKNOWN',
          'last_incident' => null,
        ]
      ];
    }
  }

  /**
   * Calculate severity score for a UCR code.
   */
  private function calculateSeverityScore($ucr_code) {
    $severity_map = [
      '100' => 5, // Murder
      '200' => 4, // Rape  
      '300' => 4, // Robbery
      '400' => 3, // Aggravated Assault
      '500' => 2, // Burglary
      '600' => 3, // Theft from Vehicle
      '700' => 1, // All Other Larceny
      '800' => 2, // Vandalism
      '900' => 2, // Fraud
      '1000' => 2, // Embezzlement
      '1100' => 3, // Drug Violations
      '1200' => 4, // Weapons
      '1300' => 2, // Prostitution
      '1400' => 3, // Other Assaults
      '1500' => 4, // Arson
      '1600' => 2, // Stolen Property
      '1700' => 2, // DUI
      '1800' => 1, // Liquor Laws
      '2000' => 1, // Public Drunkenness
      '2100' => 1, // Disorderly Conduct
      '2600' => 3, // Theft from Person
    ];
    
    return $severity_map[$ucr_code] ?? 2;
  }

}