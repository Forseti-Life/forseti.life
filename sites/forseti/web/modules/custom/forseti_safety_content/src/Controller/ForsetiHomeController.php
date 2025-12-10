<?php

namespace Drupal\forseti_safety_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for Forseti home page.
 */
class ForsetiHomeController extends ControllerBase {

  /**
   * Returns the home page content.
   */
  public function content() {
    $build = [];

    $build['#attached']['library'][] = 'forseti_safety_content/style';

    $build['philly_banner'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="philly-focus-banner">
          <span class="location-icon">📍</span>
          Currently Serving: Philadelphia Metropolitan Area
        </div>
      ',
    ];

    $build['hero'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="safety-hero">
          <div class="hero-content container text-center">
            <div class="ai-monitoring-badge mb-4">
              <svg class="ai-icon" fill="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="3" />
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
              </svg>
              AI Looking Out For You
            </div>
            
            <h1>Building Safer Communities Through Intelligence</h1>
            <p class="hero-tagline">AI-Powered Safety Monitoring for Philadelphia</p>
            
            <p class="hero-mission">
              Forseti combines advanced artificial intelligence, real-time crime data analysis, 
              and community engagement to help maintain and improve quality of life for as many 
              people as possible. Our mission is simple: use technology to make Philadelphia safer.
            </p>
            
            <div class="cta-buttons">
              <a href="/safety-map" class="btn btn-light btn-lg">View Safety Map</a>
              <a href="/mobile-app" class="btn btn-outline-light btn-lg">Get AmISafe App</a>
            </div>
          </div>
        </div>
      ',
    ];

    $build['safety_status'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="container my-5">
          <h2 class="text-center mb-5">Philadelphia Safety Overview</h2>
          
          <div class="safety-status-grid">
            <div class="status-card safe">
              <div class="status-icon">🛡️</div>
              <div class="status-title">AI Monitoring Active</div>
              <div class="status-description">
                24/7 intelligent monitoring analyzing crime patterns, trends, and emerging threats 
                across Philadelphia neighborhoods.
              </div>
              <div class="status-metric">
                <span class="metric-value">99.9%</span>
                <span class="metric-label">Uptime</span>
              </div>
            </div>
            
            <div class="status-card safe">
              <div class="status-icon">📊</div>
              <div class="status-title">Real-Time Data</div>
              <div class="status-description">
                Live crime incident tracking with H3 geospatial analysis providing 
                precise location-based safety information.
              </div>
              <div class="status-metric">
                <span class="metric-value">1,247</span>
                <span class="metric-label">Incidents This Month</span>
              </div>
            </div>
            
            <div class="status-card safe">
              <div class="status-icon">👥</div>
              <div class="status-title">Community Strong</div>
              <div class="status-description">
                Thousands of Philadelphia residents staying informed and connected 
                through our safety network.
              </div>
              <div class="status-metric">
                <span class="metric-value">12.5K+</span>
                <span class="metric-label">Active Members</span>
              </div>
            </div>
          </div>
        </div>
      ',
    ];

    $build['features'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="community-features">
          <div class="container">
            <h2 class="text-center mb-3">How Forseti Keeps You Safe</h2>
            <p class="text-center text-muted mb-5">
              Advanced technology combined with community engagement
            </p>
            
            <div class="feature-grid">
              <div class="feature-card">
                <div class="feature-icon">🗺️</div>
                <h3>Live Crime Mapping</h3>
                <p>
                  Interactive maps showing real-time crime incidents, hot spots, and trends 
                  using H3 hexagonal grid analysis for precise location data.
                </p>
              </div>
              
              <div class="feature-card">
                <div class="feature-icon">🤖</div>
                <h3>Predictive AI Alerts</h3>
                <p>
                  Machine learning algorithms identify patterns and predict high-risk 
                  areas and times, sending proactive safety notifications.
                </p>
              </div>
              
              <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Mobile Access</h3>
                <p>
                  AmISafe mobile app provides on-the-go safety information, location-based 
                  alerts, and one-touch emergency services.
                </p>
              </div>
              
              <div class="feature-card">
                <div class="feature-icon">👁️</div>
                <h3>Neighborhood Watch</h3>
                <p>
                  Digital neighborhood watch coordination with community reporting, 
                  incident tracking, and collaborative safety efforts.
                </p>
              </div>
              
              <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Privacy First</h3>
                <p>
                  End-to-end encryption, anonymous reporting options, and transparent 
                  data policies. Your privacy is our priority.
                </p>
              </div>
              
              <div class="feature-card">
                <div class="feature-icon">📈</div>
                <h3>Trend Analysis</h3>
                <p>
                  Historical data analysis showing crime trends over time, helping you 
                  understand long-term safety patterns in your area.
                </p>
              </div>
            </div>
          </div>
        </div>
      ',
    ];

    $build['cta_section'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="container my-3 py-3 text-center">
          <h2 class="mb-3">Join the Movement for Safer Communities</h2>
          <p class="mb-3">
            Together, we can make Philadelphia a safer place for everyone.
          </p>
          <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="/community" class="btn btn-primary">Join Community</a>
            <a href="/how-it-works" class="btn btn-outline-primary">Learn More</a>
            <a href="/contact" class="btn btn-outline-secondary">Contact Us</a>
          </div>
        </div>
      ',
    ];

    return $build;
  }

}
