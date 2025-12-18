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
            
            <p class="hero-tagline">AI-Powered Safety Monitoring for Philadelphia</p>
            
            <p class="hero-mission">
              Forseti combines advanced artificial intelligence, real-time crime data analysis, 
              and community engagement to help maintain and improve quality of life for as many 
              people as possible. Our mission is simple: use technology to make Philadelphia safer.
            </p>
            
            <div class="cta-buttons">
              <a href="/talk-with-forseti" class="btn btn-primary btn-lg me-3">
                <svg class="ai-icon" fill="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">
                  <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                </svg>
                Talk with Forseti
              </a>
              <a href="/safety-map" class="btn btn-light btn-lg me-2">View Safety Map</a>
              <a href="/mobile-app" class="btn btn-outline-light btn-lg">Get Forseti App</a>
            </div>
          </div>
        </div>
      ',
    ];

    $build['talk_with_forseti'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="talk-with-forseti-section my-5 py-5">
          <div class="container">
            <div class="row align-items-center">
              <div class="col-md-6 mb-4 mb-md-0">
                <div class="forseti-chat-preview">
                  <div class="chat-icon-wrapper">
                    <img src="/themes/custom/forseti/images/logos/originals/forseti_energized.png" alt="Forseti AI" class="forseti-chat-icon">
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <h2 class="mb-3">
                  <svg fill="currentColor" viewBox="0 0 24 24" style="width: 32px; height: 32px; margin-right: 12px; vertical-align: middle; color: #00d4ff;">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                  </svg>
                  Talk with Forseti
                </h2>
                <p class="lead mb-4">
                  Have a conversation with Forseti, our AI guardian. Ask questions about safety in your neighborhood, 
                  get personalized advice, make suggestions, or learn more about how we can work together to keep Philadelphia safe.
                </p>
                <ul class="forseti-features-list mb-4">
                  <li><img src="/themes/custom/forseti/images/logos/originals/forseti_connected.png" alt="" class="forseti-icon"> Natural conversation powered by advanced AI</li>
                  <li><img src="/themes/custom/forseti/images/logos/originals/forseti_capable.png" alt="" class="forseti-icon"> Personalized safety recommendations</li>
                  <li><img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="" class="forseti-icon"> Location-specific crime insights</li>
                  <li><img src="/themes/custom/forseti/images/logos/originals/forseti_useful.png" alt="" class="forseti-icon"> Submit suggestions and feedback</li>
                  <li><img src="/themes/custom/forseti/images/logos/originals/forseti_free.png" alt="" class="forseti-icon"> Private and secure conversations</li>
                </ul>
                <a href="/talk-with-forseti" class="btn btn-primary btn-lg">
                  Start Conversation
                  <svg fill="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-left: 8px; vertical-align: middle;">
                    <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                  </svg>
                </a>
                <p class="text-muted mt-3 small">
                  <em>Free for all community members</em>
                </p>
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
                <div class="feature-icon"><img src="/themes/custom/forseti/images/logos/originals/forseti_energized.png" alt=""></div>
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
                  Forseti mobile app provides on-the-go safety information, location-based 
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

    return $build;
  }

}
