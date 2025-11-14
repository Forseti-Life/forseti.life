<?php

namespace Drupal\amisafe\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * AmISafe Controller - Dashboard for safety monitoring in Philadelphia 2085.
 */
class AmISafeController extends ControllerBase {

  /**
   * Display the Am I Safe dashboard with Resolution 13 ultra-precision data.
   */
  public function dashboard() {
    
    // Get module configuration
    $config = $this->config('amisafe.settings');
    $use_gold_layer = $config->get('use_gold_layer') ?? TRUE;
    $default_resolution = $config->get('default_resolution') ?? 9;
    
    // Crime analytics dashboard data with ultra-precision monitoring
    $dashboard_data = [
      'current_threat_level' => 'MODERATE',
      'threat_color' => 'warning', // warning, danger, success
      'last_updated' => date('Y-m-d H:i:s'),
      'location_status' => 'City-Wide Crime Analytics',
      'active_threats' => [
        [
          'type' => 'Burglary Trend Alert',
          'severity' => 'HIGH',
          'description' => 'Increased burglary activity detected in residential areas. Property crime up 15% this week.',
          'time_detected' => '2 hours ago',
        ],
        [
          'type' => 'Vehicle Theft Hotspot',
          'severity' => 'MEDIUM',
          'description' => 'Auto theft incidents concentrated in downtown parking areas. Enhanced monitoring active.',
          'time_detected' => '4 hours ago',
        ],
        [
          'type' => 'Vandalism Pattern',
          'severity' => 'LOW',
          'description' => 'Minor vandalism reports showing geographic clustering in commercial district.',
          'time_detected' => '6 hours ago',
        ],
      ],
      'safety_recommendations' => [
        'Avoid leaving valuables visible in vehicles',
        'Use well-lit parking areas and walkways',
        'Stay aware of surroundings in high-activity areas',
        'Report suspicious activity to local authorities',
        'Consider varying daily routines and routes',
      ],
      'safe_zones' => [
        [
          'name' => 'Police Station - Central District',
          'distance' => '0.8 km',
          'capacity' => 'Available',
          'security_rating' => 'HIGH',
        ],
        [
          'name' => 'Community Safety Center',
          'distance' => '1.2 km', 
          'capacity' => 'Available',
          'security_rating' => 'HIGH',
        ],
        [
          'name' => 'Hospital - Emergency Services',
          'distance' => '1.8 km',
          'capacity' => 'Available',
          'security_rating' => 'MEDIUM',
        ],
      ],
      'network_status' => [
        'system_connectivity' => 95,
        'data_accuracy' => 'HIGH',
        'analysis_coverage' => 'COMPREHENSIVE',
        'active_sensors' => 847,
      ],
      // Ultra-precision analytics powered by Resolution 13
              'ultra_precision_stats' => [
          'resolution_13_hexagons' => 413172,
          'precision_area' => '44 m² per hexagon',
          'spatial_detail' => '7m × 7m precision',
          'improvement_factor' => '20.1x over standard',
          'data_source' => 'Gold Layer Analytics',
          'api_endpoints' => [
            '/api/amisafe/ultra-precision',
            '/api/amisafe/system-stats',
            '/api/amisafe/aggregated?resolution=13',
          ],
        ],
      // Configuration
      'use_gold_layer' => $use_gold_layer,
    ];

    return [
      '#theme' => 'amisafe_dashboard',
      '#dashboard_data' => $dashboard_data,
      '#attached' => [
        'library' => [
          'amisafe/dashboard-styling',
        ],
      ],
    ];
  }

  /**
   * Get precision level label for H3 resolution.
   */
  private function getPrecisionLabel($resolution) {
    $labels = [
      6 => 'City-wide Coverage',
      7 => 'District Analysis',
      8 => 'Neighborhood Detail',
      9 => 'Block Group Precision',
      10 => 'Block-level Accuracy',
      11 => 'Building-level Detail',
      12 => 'Room-level Precision',
      13 => 'Ultra-precision Analytics'
    ];
    
    return $labels[$resolution] ?? 'Unknown Resolution';
  }

}