<?php

namespace Drupal\amisafe\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * AmISafe Controller - Dashboard for safety monitoring in Philadelphia 2085.
 */
class AmISafeController extends ControllerBase {

  /**
   * Display the Am I Safe dashboard.
   */
  public function dashboard() {
    
    // Safety monitoring data for Philadelphia 2085
    $dashboard_data = [
      'current_threat_level' => 'ELEVATED',
      'threat_color' => 'warning', // warning, danger, success
      'last_updated' => date('Y-m-d H:i:s'),
      'location_status' => 'Northern Liberties Industrial Maze',
      'active_threats' => [
        [
          'type' => 'Corporate Surveillance Drones',
          'severity' => 'HIGH',
          'description' => 'Increased drone activity detected in your sector. Avoid main thoroughfares.',
          'time_detected' => '14 minutes ago',
        ],
        [
          'type' => 'Automated Security Checkpoints',
          'severity' => 'MEDIUM',
          'description' => 'Enhanced biometric scanning active. Identity verification required.',
          'time_detected' => '1 hour ago',
        ],
        [
          'type' => 'Network Intrusion Attempts',
          'severity' => 'LOW',
          'description' => 'Minor AI probing detected on local mesh networks.',
          'time_detected' => '3 hours ago',
        ],
      ],
      'safety_recommendations' => [
        'Use underground passages when possible',
        'Disable non-essential biometric devices',
        'Travel in groups of 2-3 maximum',
        'Avoid pattern recognition by varying routes',
        'Keep emergency contact codes active',
      ],
      'safe_zones' => [
        [
          'name' => 'Underground Resistance Hideout Alpha',
          'distance' => '0.8 km',
          'capacity' => 'Available',
          'security_rating' => 'HIGH',
        ],
        [
          'name' => 'Black Market Med Clinic',
          'distance' => '1.2 km', 
          'capacity' => 'Limited',
          'security_rating' => 'MEDIUM',
        ],
        [
          'name' => 'Abandoned Subway Junction',
          'distance' => '2.1 km',
          'capacity' => 'Available',
          'security_rating' => 'LOW',
        ],
      ],
      'network_status' => [
        'mesh_connectivity' => 78,
        'encryption_level' => 'MAXIMUM',
        'ai_detection_risk' => 'MODERATE',
        'peer_nodes_active' => 23,
      ],
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

}