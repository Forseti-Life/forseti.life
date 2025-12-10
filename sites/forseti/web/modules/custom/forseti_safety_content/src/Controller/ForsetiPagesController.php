<?php

namespace Drupal\forseti_safety_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for Forseti content pages.
 */
class ForsetiPagesController extends ControllerBase {

  /**
   * About page.
   */
  public function about() {
    return [
      '#markup' => $this->getAboutContent(),
    ];
  }

  /**
   * How It Works page.
   */
  public function howItWorks() {
    return [
      '#markup' => $this->getHowItWorksContent(),
    ];
  }

  /**
   * Safety Map page.
   */
  public function safetyMap() {
    return [
      '#markup' => $this->getSafetyMapContent(),
    ];
  }

  /**
   * Community page.
   */
  public function community() {
    return [
      '#markup' => $this->getCommunityContent(),
    ];
  }

  /**
   * Mobile App page.
   */
  public function mobileApp() {
    return [
      '#markup' => $this->getMobileAppContent(),
    ];
  }

  /**
   * Privacy page.
   */
  public function privacy() {
    return [
      '#markup' => $this->getPrivacyContent(),
    ];
  }

  /**
   * Contact page.
   */
  public function contact() {
    return [
      '#markup' => $this->getContactContent(),
    ];
  }

  /**
   * Get About content.
   */
  private function getAboutContent() {
    return '
      <div class="container py-5">
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <h1 class="mb-4">About Forseti</h1>
            
            <div class="lead mb-4">
              Forseti is an AI-powered community safety platform dedicated to making Philadelphia 
              a safer place through intelligent monitoring, predictive analytics, and community engagement.
            </div>
            
            <h2 class="mt-5 mb-3">Our Mission</h2>
            <p>
              <strong>"AI Looking Out For Us"</strong> - We believe technology should serve humanity 
              by protecting communities and improving quality of life for as many people as possible. 
              Forseti combines cutting-edge artificial intelligence with real-time crime data to provide 
              Philadelphia residents with the information and tools they need to stay safe.
            </p>
            
            <h2 class="mt-5 mb-3">Why "Forseti"?</h2>
            <p>
              Named after the Norse god of justice and peaceful resolution, Forseti represents our 
              commitment to fair, intelligent, and proactive safety measures. Just as Forseti\'s hall 
              Glitnir was a place where disputes were settled justly, our platform aims to resolve 
              community safety challenges through technology, transparency, and collaboration.
            </p>
            
            <h2 class="mt-5 mb-3">Core Values</h2>
            <div class="row mt-4">
              <div class="col-md-6 mb-4">
                <div class="p-4 border rounded">
                  <h4>🛡️ Vigilance</h4>
                  <p>24/7 AI monitoring ensures constant awareness of safety conditions across Philadelphia.</p>
                </div>
              </div>
              <div class="col-md-6 mb-4">
                <div class="p-4 border rounded">
                  <h4>🔍 Transparency</h4>
                  <p>Open data and clear communication about safety trends and our methods.</p>
                </div>
              </div>
              <div class="col-md-6 mb-4">
                <div class="p-4 border rounded">
                  <h4>⚖️ Justice</h4>
                  <p>Fair and unbiased safety measures that protect all community members equally.</p>
                </div>
              </div>
              <div class="col-md-6 mb-4">
                <div class="p-4 border rounded">
                  <h4>👥 Community</h4>
                  <p>Empowering residents with knowledge and tools to take ownership of their safety.</p>
                </div>
              </div>
            </div>
            
            <h2 class="mt-5 mb-3">Philadelphia Focus</h2>
            <p>
              We\'ve chosen to focus our initial efforts on Philadelphia because we believe in starting 
              local and growing organically. By deeply understanding one community\'s unique safety 
              challenges, we can create more effective solutions. As we prove our model, we plan to 
              expand to other cities facing similar challenges.
            </p>
            
            <div class="alert alert-info mt-5">
              <h4>Join Our Mission</h4>
              <p class="mb-0">
                We\'re always looking for community members, safety advocates, and technology partners 
                who share our vision. <a href="/contact" class="alert-link">Get in touch</a> to learn 
                how you can contribute to safer communities.
              </p>
            </div>
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get How It Works content.
   */
  private function getHowItWorksContent() {
    return '
      <div class="container py-5">
        <h1 class="text-center mb-5">How Forseti Works</h1>
        
        <div class="row mb-5">
          <div class="col-lg-10 mx-auto">
            <div class="alert alert-primary">
              <strong>Simple Answer:</strong> We use AI to analyze crime data in real-time, 
              identify patterns, and alert you to potential safety concerns in your area.
            </div>
          </div>
        </div>
        
        <div class="row">
          <div class="col-lg-10 mx-auto">
            <h2 class="mb-4">The Technology Behind Community Safety</h2>
            
            <div class="mb-5">
              <h3>1. Data Collection 📊</h3>
              <p>
                We continuously gather crime incident data from Philadelphia Police Department 
                open data sources, emergency service reports, and community submissions. All data 
                is verified and anonymized to protect privacy.
              </p>
            </div>
            
            <div class="mb-5">
              <h3>2. H3 Geospatial Analysis 🗺️</h3>
              <p>
                Using Uber\'s H3 hexagonal hierarchical geospatial indexing system, we map crime 
                incidents with incredible precision. Unlike traditional square grids, H3 hexagons 
                provide more accurate spatial analysis and better visualization of crime patterns.
              </p>
              <div class="ps-4">
                <ul>
                  <li><strong>Resolution levels:</strong> From neighborhood overviews to block-level details</li>
                  <li><strong>Aggregation:</strong> Incident density calculations for hotspot identification</li>
                  <li><strong>Neighbor analysis:</strong> Understanding how crime spreads between adjacent areas</li>
                </ul>
              </div>
            </div>
            
            <div class="mb-5">
              <h3>3. AI Pattern Recognition 🤖</h3>
              <p>
                Our machine learning algorithms analyze historical and real-time data to identify:
              </p>
              <div class="ps-4">
                <ul>
                  <li><strong>Temporal patterns:</strong> When crimes are most likely to occur (time of day, day of week, season)</li>
                  <li><strong>Spatial patterns:</strong> Geographic clustering and crime migration</li>
                  <li><strong>Trend analysis:</strong> Increasing or decreasing crime rates over time</li>
                  <li><strong>Predictive modeling:</strong> Forecasting high-risk areas and times</li>
                </ul>
              </div>
            </div>
            
            <div class="mb-5">
              <h3>4. Intelligent Alerts 🔔</h3>
              <p>
                When our AI detects concerning patterns or emerging threats, we send targeted alerts to:
              </p>
              <div class="ps-4">
                <ul>
                  <li>Residents in affected areas</li>
                  <li>Neighborhood watch coordinators</li>
                  <li>Community safety groups</li>
                  <li>Local authorities (with user consent)</li>
                </ul>
              </div>
            </div>
            
            <div class="mb-5">
              <h3>5. Community Feedback Loop 🔄</h3>
              <p>
                User reports and feedback help improve our AI models. When community members report 
                incidents or validate our predictions, our system becomes smarter and more accurate.
              </p>
            </div>
            
            <h2 class="mt-5 mb-4">Privacy & Security</h2>
            <div class="alert alert-success">
              <h4>Your Data is Safe</h4>
              <ul class="mb-0">
                <li>End-to-end encryption for all communications</li>
                <li>Anonymous incident reporting options</li>
                <li>No sale or sharing of personal data</li>
                <li>GDPR and privacy law compliant</li>
                <li>Transparent data usage policies</li>
              </ul>
            </div>
            
            <div class="text-center mt-5">
              <a href="/amisafe/crime-map" class="btn btn-primary btn-lg me-3">Explore Safety Map</a>
              <a href="/amisafe/download" class="btn btn-outline-primary btn-lg">Get Mobile App</a>
            </div>
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get Safety Map content.
   */
  private function getSafetyMapContent() {
    return '
      <div class="container-fluid py-5">
        <h1 class="text-center mb-4">Philadelphia Safety Map</h1>
        <p class="text-center text-muted mb-5">
          Real-time crime incident tracking with H3 geospatial analysis
        </p>
        
        <div class="safety-map-container">
          <div class="map-header">
            <h2>Live Crime Data</h2>
            <div class="map-filters">
              <button class="filter-btn active">All Crimes</button>
              <button class="filter-btn">Violent</button>
              <button class="filter-btn">Property</button>
              <button class="filter-btn">Last 24h</button>
              <button class="filter-btn">Last Week</button>
            </div>
          </div>
          
          <div class="alert alert-info">
            <strong>🚧 Coming Soon:</strong> Interactive crime map with real-time data visualization. 
            Our development team is currently integrating the H3 geospatial engine with live 
            Philadelphia Police Department data feeds.
          </div>
          
          <div class="row mt-4">
            <div class="col-md-4 mb-3">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title">Features</h5>
                  <ul>
                    <li>Real-time incident markers</li>
                    <li>Heat map overlays</li>
                    <li>Historical trend views</li>
                    <li>Neighborhood comparisons</li>
                    <li>Custom alert zones</li>
                  </ul>
                </div>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title">Data Sources</h5>
                  <ul>
                    <li>Philadelphia PD Open Data</li>
                    <li>Emergency service reports</li>
                    <li>Community submissions</li>
                    <li>Weather & environmental data</li>
                  </ul>
                </div>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title">Coming Updates</h5>
                  <ul>
                    <li>Mobile app integration</li>
                    <li>Custom notifications</li>
                    <li>Predictive overlays</li>
                    <li>Safe route planning</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
          
          <div class="text-center mt-4">
            <a href="/amisafe/download" class="btn btn-primary">Get Early Access via AmISafe App</a>
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get Community content.
   */
  private function getCommunityContent() {
    return '
      <div class="container py-5">
        <h1 class="text-center mb-4">Join Our Safety Community</h1>
        <p class="text-center lead mb-5">
          Together, we\'re making Philadelphia safer for everyone
        </p>
        
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <h2 class="mb-4">Why Join?</h2>
            
            <div class="mb-4 p-4 border rounded">
              <h4>📢 Stay Informed</h4>
              <p>Receive real-time safety alerts for your neighborhood, emergency notifications, 
              and weekly safety summaries.</p>
            </div>
            
            <div class="mb-4 p-4 border rounded">
              <h4>🤝 Connect with Neighbors</h4>
              <p>Join neighborhood watch groups, coordinate safety efforts, and build stronger 
              community bonds.</p>
            </div>
            
            <div class="mb-4 p-4 border rounded">
              <h4>💪 Make an Impact</h4>
              <p>Report incidents, validate AI predictions, and contribute to the safety intelligence 
              that protects your community.</p>
            </div>
            
            <div class="mb-4 p-4 border rounded">
              <h4>🎓 Learn & Grow</h4>
              <p>Access safety resources, attend community events, and participate in safety 
              awareness programs.</p>
            </div>
            
            <h2 class="mt-5 mb-4">How to Get Involved</h2>
            
            <div class="row">
              <div class="col-md-6 mb-4">
                <div class="card h-100">
                  <div class="card-body">
                    <h5 class="card-title">1. Download AmISafe</h5>
                    <p class="card-text">Get our mobile app for real-time alerts and on-the-go safety information.</p>
                    <a href="/amisafe/download" class="btn btn-primary">Get the App</a>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6 mb-4">
                <div class="card h-100">
                  <div class="card-body">
                    <h5 class="card-title">2. Create Account</h5>
                    <p class="card-text">Set up your profile, customize your alert preferences, and define your safety zones.</p>
                    <a href="/user/register" class="btn btn-outline-primary">Sign Up</a>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6 mb-4">
                <div class="card h-100">
                  <div class="card-body">
                    <h5 class="card-title">3. Join Local Groups</h5>
                    <p class="card-text">Connect with neighborhood watch groups and community safety initiatives in your area.</p>
                    <button class="btn btn-outline-primary" disabled>Coming Soon</button>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6 mb-4">
                <div class="card h-100">
                  <div class="card-body">
                    <h5 class="card-title">4. Contribute</h5>
                    <p class="card-text">Report incidents, verify alerts, and help improve our AI models with your feedback.</p>
                    <button class="btn btn-outline-primary" disabled>Coming Soon</button>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="alert alert-success mt-5">
              <h4>Community Stats</h4>
              <div class="row text-center">
                <div class="col-md-4">
                  <h2 class="text-primary">12,500+</h2>
                  <p>Active Members</p>
                </div>
                <div class="col-md-4">
                  <h2 class="text-primary">89</h2>
                  <p>Neighborhood Groups</p>
                </div>
                <div class="col-md-4">
                  <h2 class="text-primary">3,400+</h2>
                  <p>Reports This Month</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get Mobile App content.
   */
  private function getMobileAppContent() {
    return '
      <div class="container py-5">
        <h1 class="text-center mb-4">AmISafe Mobile App</h1>
        <p class="text-center lead mb-5">
          Your personal safety companion for Philadelphia
        </p>
        
        <div class="row align-items-center mb-5">
          <div class="col-lg-6">
            <h2>Safety in Your Pocket</h2>
            <p class="lead">
              AmISafe brings the power of Forseti\'s AI monitoring directly to your smartphone. 
              Stay safe with real-time alerts, location-based safety information, and one-touch 
              emergency services.
            </p>
          </div>
          <div class="col-lg-6 text-center">
            <div class="p-5 bg-light rounded">
              <h3>📱</h3>
              <h4>iOS & Android</h4>
              <p class="text-muted">Coming Soon to App Stores</p>
            </div>
          </div>
        </div>
        
        <h2 class="mb-4">Key Features</h2>
        
        <div class="row">
          <div class="col-md-6 mb-4">
            <div class="card h-100">
              <div class="card-body">
                <h3 class="card-title">📍 Location-Based Alerts</h3>
                <p class="card-text">
                  Automatic notifications when you enter high-risk areas or when incidents 
                  occur near your location.
                </p>
              </div>
            </div>
          </div>
          
          <div class="col-md-6 mb-4">
            <div class="card h-100">
              <div class="card-body">
                <h3 class="card-title">🚨 Emergency SOS</h3>
                <p class="card-text">
                  One-touch access to emergency services with automatic location sharing 
                  and emergency contact notifications.
                </p>
              </div>
            </div>
          </div>
          
          <div class="col-md-6 mb-4">
            <div class="card h-100">
              <div class="card-body">
                <h3 class="card-title">🗺️ Interactive Maps</h3>
                <p class="card-text">
                  View real-time crime incidents, safety zones, and navigate the safest 
                  routes to your destination.
                </p>
              </div>
            </div>
          </div>
          
          <div class="col-md-6 mb-4">
            <div class="card h-100">
              <div class="card-body">
                <h3 class="card-title">📝 Incident Reporting</h3>
                <p class="card-text">
                  Quickly report suspicious activity or incidents with photos, descriptions, 
                  and automatic GPS tagging.
                </p>
              </div>
            </div>
          </div>
          
          <div class="col-md-6 mb-4">
            <div class="card h-100">
              <div class="card-body">
                <h3 class="card-title">👥 Check-In Feature</h3>
                <p class="card-text">
                  Let friends and family know you\'re safe with automatic check-ins and 
                  location sharing.
                </p>
              </div>
            </div>
          </div>
          
          <div class="col-md-6 mb-4">
            <div class="card h-100">
              <div class="card-body">
                <h3 class="card-title">📚 Offline Resources</h3>
                <p class="card-text">
                  Access safety tips, emergency contacts, and critical information even 
                  without an internet connection.
                </p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="alert alert-info mt-5">
          <h4>Early Access Program</h4>
          <p>
            AmISafe is currently in beta testing with select Philadelphia neighborhoods. 
            Want to be among the first to use it?
          </p>
          <a href="/contact" class="btn btn-primary">Request Early Access</a>
        </div>
        
        <div class="text-center mt-5">
          <h3>Current Version: Beta 0.9.5</h3>
          <p class="text-muted">Expected Public Release: Q1 2026</p>
        </div>
      </div>
    ';
  }

  /**
   * Get Privacy content.
   */
  private function getPrivacyContent() {
    return '
      <div class="container py-5">
        <h1 class="mb-4">Privacy & Security</h1>
        
        <div class="alert alert-success">
          <h4>Our Commitment: Privacy First</h4>
          <p class="mb-0">
            At Forseti, we believe safety and privacy go hand-in-hand. We never sell your data, 
            and we design every feature with your privacy in mind.
          </p>
        </div>
        
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <h2 class="mt-5 mb-3">Data Collection</h2>
            <h4>What We Collect</h4>
            <ul>
              <li><strong>Crime Data:</strong> Public incident data from Philadelphia PD and emergency services</li>
              <li><strong>Location Data:</strong> Only when you explicitly enable location services</li>
              <li><strong>User Reports:</strong> Incident reports you voluntarily submit</li>
              <li><strong>Usage Analytics:</strong> Anonymous app usage data to improve our service</li>
            </ul>
            
            <h4 class="mt-4">What We DON\'T Collect</h4>
            <ul>
              <li>❌ Your browsing history outside Forseti</li>
              <li>❌ Your contacts or messages</li>
              <li>❌ Your photos (unless you choose to attach them to a report)</li>
              <li>❌ Your personal conversations</li>
            </ul>
            
            <h2 class="mt-5 mb-3">Data Usage</h2>
            <p>We use your data exclusively to:</p>
            <ul>
              <li>Provide safety alerts relevant to your location</li>
              <li>Improve our AI prediction models</li>
              <li>Generate anonymized crime statistics</li>
              <li>Communicate important safety information</li>
            </ul>
            
            <div class="alert alert-warning mt-4">
              <strong>We Never:</strong>
              <ul class="mb-0">
                <li>Sell your personal information</li>
                <li>Share your data with advertisers</li>
                <li>Track you across other websites</li>
                <li>Use your data for purposes you didn\'t consent to</li>
              </ul>
            </div>
            
            <h2 class="mt-5 mb-3">Security Measures</h2>
            <div class="row">
              <div class="col-md-6 mb-3">
                <div class="p-3 border rounded">
                  <h5>🔒 Encryption</h5>
                  <p>All data is encrypted in transit (TLS 1.3) and at rest (AES-256).</p>
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <div class="p-3 border rounded">
                  <h5>🔐 Authentication</h5>
                  <p>Multi-factor authentication and secure password policies.</p>
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <div class="p-3 border rounded">
                  <h5>👁️ Access Controls</h5>
                  <p>Strict role-based access with audit logging.</p>
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <div class="p-3 border rounded">
                  <h5>🔍 Regular Audits</h5>
                  <p>Third-party security audits and penetration testing.</p>
                </div>
              </div>
            </div>
            
            <h2 class="mt-5 mb-3">Your Rights</h2>
            <p>Under GDPR and other privacy laws, you have the right to:</p>
            <ul>
              <li><strong>Access:</strong> Request a copy of all data we have about you</li>
              <li><strong>Correction:</strong> Update or correct inaccurate information</li>
              <li><strong>Deletion:</strong> Request deletion of your personal data</li>
              <li><strong>Portability:</strong> Export your data in a standard format</li>
              <li><strong>Opt-Out:</strong> Disable location tracking or notifications anytime</li>
            </ul>
            
            <h2 class="mt-5 mb-3">Anonymous Reporting</h2>
            <p>
              We offer completely anonymous incident reporting. When you choose this option:
            </p>
            <ul>
              <li>No account required</li>
              <li>No location tracking</li>
              <li>No identifying information stored</li>
              <li>Reports still help improve community safety</li>
            </ul>
            
            <h2 class="mt-5 mb-3">Third-Party Services</h2>
            <p>We use the following third-party services:</p>
            <ul>
              <li><strong>Philadelphia PD Open Data:</strong> Public crime statistics</li>
              <li><strong>Map Services:</strong> For map display only (no tracking)</li>
              <li><strong>Cloud Infrastructure:</strong> Secure AWS hosting with encryption</li>
            </ul>
            <p>
              All third parties are contractually bound to the same privacy standards we follow.
            </p>
            
            <div class="alert alert-info mt-5">
              <h4>Questions or Concerns?</h4>
              <p class="mb-0">
                If you have any questions about our privacy practices or want to exercise your rights, 
                please <a href="/contact" class="alert-link">contact us</a>. We typically respond within 48 hours.
              </p>
            </div>
            
            <p class="text-muted mt-5">
              <small>Last Updated: December 9, 2025</small>
            </p>
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get Contact content.
   */
  private function getContactContent() {
    return '
      <div class="container py-5">
        <h1 class="text-center mb-4">Contact Forseti</h1>
        <p class="text-center lead mb-5">
          We\'re here to help keep Philadelphia safe
        </p>
        
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <div class="alert alert-primary">
              <h4>🚨 Emergency?</h4>
              <p class="mb-0">
                For immediate emergencies, always call <strong>911</strong>. 
                Forseti is a safety information platform, not an emergency service.
              </p>
            </div>
            
            <h2 class="mt-5 mb-4">Get in Touch</h2>
            
            <div class="row">
              <div class="col-md-6 mb-4">
                <div class="card h-100">
                  <div class="card-body">
                    <h4>📧 Email Us</h4>
                    <p><a href="mailto:keith.aumiller@forseti.life">keith.aumiller@forseti.life</a></p>
                    <p class="text-muted">We typically respond within 24-48 hours</p>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6 mb-4">
                <div class="card h-100">
                  <div class="card-body">
                    <h4>🌐 Website</h4>
                    <p><a href="https://forseti.life" target="_blank">forseti.life</a></p>
                    <p class="text-muted">Visit our main website for more information</p>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6 mb-4">
                <div class="card h-100">
                  <div class="card-body">
                    <h4>📍 Location</h4>
                    <p>Philadelphia, PA<br>Serving the Greater Philadelphia Area</p>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6 mb-4">
                <div class="card h-100">
                  <div class="card-body">
                    <h4>⏰ Response Time</h4>
                    <p>Monday - Friday: 9am - 6pm EST<br>Automated alerts: 24/7</p>
                  </div>
                </div>
              </div>
            </div>
            
            <h2 class="mt-5 mb-4">Frequently Contacted For</h2>
            
            <div class="accordion" id="contactFAQ">
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                    AmISafe App Support
                  </button>
                </h2>
                <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#contactFAQ">
                  <div class="accordion-body">
                    For app-related issues, email us with your device type, app version, and a description of the problem. 
                    Include screenshots if possible.
                  </div>
                </div>
              </div>
              
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                    Partnership Opportunities
                  </button>
                </h2>
                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#contactFAQ">
                  <div class="accordion-body">
                    We\'re always interested in collaborating with community organizations, local government, 
                    and technology partners. Email us with "Partnership" in the subject line.
                  </div>
                </div>
              </div>
              
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                    Privacy & Data Requests
                  </button>
                </h2>
                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#contactFAQ">
                  <div class="accordion-body">
                    For privacy concerns or to exercise your data rights (access, correction, deletion), 
                    email us with "Privacy Request" in the subject line. We respond within 48 hours.
                  </div>
                </div>
              </div>
              
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                    Media Inquiries
                  </button>
                </h2>
                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#contactFAQ">
                  <div class="accordion-body">
                    Members of the press should email keith.aumiller@forseti.life with "Media" in the subject line.
                  </div>
                </div>
              </div>
            </div>
            
            <div class="alert alert-secondary mt-5">
              <h4>Report a Safety Incident</h4>
              <p>
                To report a crime or safety incident, please use the AmISafe mobile app or 
                call 311 for non-emergency issues. For emergencies, always call 911.
              </p>
            </div>
            
            <div class="text-center mt-5">
              <h3>Join the Conversation</h3>
              <p class="text-muted mb-4">Follow us for safety updates and community news</p>
              <div>
                <p class="text-muted">(Social media links coming soon)</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    ';
  }

}
