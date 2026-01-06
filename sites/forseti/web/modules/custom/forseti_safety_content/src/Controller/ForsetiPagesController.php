<?php

namespace Drupal\forseti_safety_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Controller for Forseti content pages.
 */
class ForsetiPagesController extends ControllerBase {

  /**
   * Talk with Forseti page/redirect.
   */
  public function talkWithForseti() {
    $current_user = $this->currentUser();
    
    // If user is not authenticated, redirect to registration with message.
    if ($current_user->isAnonymous()) {
      $this->messenger()->addWarning($this->t('Conversations with Forseti are reserved for community members. Please register for a free account to get started.'));
      $url = Url::fromRoute('user.register');
      return new RedirectResponse($url->toString());
    }
    
    // User is authenticated - create a new conversation and redirect to it.
    $conversation = Node::create([
      'type' => 'ai_conversation',
      'title' => 'Conversation with Forseti - ' . date('Y-m-d H:i'),
      'uid' => $current_user->id(),
      'status' => TRUE,
      'field_messages' => [],
      'field_ai_model' => 'anthropic.claude-3-5-sonnet-20240620-v1:0',
      'field_message_count' => 0,
      'field_total_tokens' => 0,
    ]);
    
    $conversation->save();
    
    // Redirect to the chat interface.
    $url = Url::fromRoute('ai_conversation.chat_interface', ['node' => $conversation->id()]);
    return new RedirectResponse($url->toString());
  }



  /**
   * About page.
   */
  public function about() {
    return [
      '#theme' => 'forseti_page_about',
      '#title' => $this->t('About Forseti'),
      '#lead' => $this->t('Forseti is an AI-powered community safety platform dedicated to making Philadelphia a safer place through intelligent monitoring, predictive analytics, and community engagement.'),
      '#mission' => [
        'title' => $this->t('Our Mission'),
        'paragraphs' => [
          $this->t('<strong>"AI Looking Out For Us"</strong> - We believe technology should serve humanity by protecting individuals and communities by improving quality of life for as many people as possible. Forseti is a super intelligence in its infancy with the mission to protect its community members.'),
          $this->t('Named after the Norse god of justice of peaceful resolution, Forseti represents our commitment to fair, intelligent, and proactive safety measures. Our platform aims to resolve community safety challenges through technology, transparency, and collaboration.'),
        ],
      ],
      '#core_values' => [
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_mobile_trimmed.png" alt="" class="forseti-icon">',
          'title' => $this->t('Vigilance'),
          'description' => $this->t('24/7 AI monitoring ensures constant awareness of situational safety conditions across Philadelphia.'),
        ],
        [
          'icon' => '🔍',
          'title' => $this->t('Transparency'),
          'description' => $this->t('Open data and clear communication about safety trends and our methods.'),
        ],
        [
          'icon' => '⚖️',
          'title' => $this->t('Justice'),
          'description' => $this->t('Fair and unbiased safety measures that protect all community members equally.'),
        ],
        [
          'icon' => '👥',
          'title' => $this->t('Community'),
          'description' => $this->t('Empowering residents with knowledge and tools to take ownership of their safety.'),
        ],
      ],
      '#philly_focus' => [
        'title' => $this->t('Philadelphia Focus'),
        'content' => $this->t("We've chosen to focus our initial efforts on Philadelphia because we are based in Philadelphia. By deeply understanding one community's unique safety challenges, we can create more effective solutions. As we prove our model, we plan to expand to other cities facing similar challenges to protect our community members any where they go."),
      ],
      '#cta' => [
        'title' => $this->t('Join Our Mission'),
        'content' => $this->t('We\'re always looking for community members, safety advocates, and technology partners who share our vision. <a href="/talk-with-forseti" class="alert-link">Talk with Forseti</a> to learn how you can contribute to safer communities.'),
      ],
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['url'],
      ],
    ];
  }

  /**
   * How It Works page.
   */
  public function howItWorks() {
    return [
      '#theme' => 'forseti_page_how_it_works',
      '#title' => $this->t('How Forseti Works'),
      '#simple_answer' => $this->t('We use AI to analyze crime patterns and alert you when you enter areas with elevated safety concerns based on your geographic location and situational context.'),
      '#subtitle' => $this->t('The Technology Behind Community Safety'),
      '#steps' => [
        [
          'number' => 1,
          'title' => $this->t('Data Collection'),
          'icon' => '📊',
          'description' => $this->t('We continuously gather crime incident data from Philadelphia Police Department open data sources, emergency service reports, and community submissions. All data is verified and anonymized to protect privacy.'),
        ],
        [
          'number' => 2,
          'title' => $this->t('H3 Geospatial Analysis'),
          'icon' => '🗺️',
          'description' => $this->t("Using Uber's H3 hexagonal hierarchical geospatial indexing system, we map crime incidents with incredible precision. Unlike traditional square grids, H3 hexagons provide more accurate spatial analysis and better visualization of crime patterns."),
          'list' => [
            $this->t('<strong>Resolution levels:</strong> From neighborhood overviews to block-level details'),
            $this->t('<strong>Aggregation:</strong> Incident density calculations for hotspot identification'),
            $this->t('<strong>Neighbor analysis:</strong> Understanding how crime spreads between adjacent areas'),
          ],
        ],
        [
          'number' => 3,
          'title' => $this->t('AI Pattern Recognition'),
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_mobile_trimmed.png" alt="" class="forseti-icon">',
          'description' => $this->t('Our machine learning algorithms analyze historical and real-time data to identify:'),
          'list' => [
            $this->t('<strong>Temporal patterns:</strong> When crimes are most likely to occur (time of day, day of week, season)'),
            $this->t('<strong>Spatial patterns:</strong> Geographic clustering and crime migration'),
            $this->t('<strong>Trend analysis:</strong> Increasing or decreasing crime rates over time'),
            $this->t('<strong>Predictive modeling:</strong> Forecasting high-risk areas and times'),
          ],
        ],
        [
          'number' => 4,
          'title' => $this->t('Intelligent Alerts'),
          'icon' => '🔔',
          'description' => $this->t('When our AI detects concerning patterns or emerging threats, we send targeted alerts to:'),
          'list' => [
            $this->t('Pedestrians passing through the area'),
            $this->t('Residents in affected areas'),
            $this->t('Neighborhood watch coordinators'),
            $this->t('Community safety groups'),
            $this->t('Local authorities (with user consent)'),
          ],
        ],
        [
          'number' => 5,
          'title' => $this->t('Community Feedback Loop'),
          'icon' => '🔄',
          'description' => $this->t('User reports and feedback help improve our AI models. When community members report incidents or validate our predictions, our system becomes smarter and more accurate.'),
        ],
      ],
      '#privacy_security' => [
        'title' => $this->t('Privacy & Security'),
        'subtitle' => $this->t('Your Data is Safe'),
        'items' => [
          $this->t('End-to-end encryption for all communications'),
          $this->t('Anonymous incident reporting options'),
          $this->t('No sale or sharing of personal data'),
          $this->t('GDPR and privacy law compliant'),
          $this->t('Transparent data usage policies'),
        ],
      ],
      '#cta_buttons' => [
        ['url' => '/safety-map', 'text' => $this->t('Explore Safety Map'), 'style' => 'primary'],
        ['url' => '/mobile-app', 'text' => $this->t('Get Mobile App'), 'style' => 'outline-primary'],
      ],
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['url'],
      ],
    ];
  }

  /**
   * Safety Map page.
   */
  public function safetyMap() {
    return [
      '#markup' => $this->getSafetyMapContent(),
      '#attached' => [
        'library' => [
          'amisafe/crime-map',
        ],
      ],
    ];
  }

  /**
   * Community page.
   */
  public function community() {
    return [
      '#theme' => 'forseti_page_community',
      '#title' => $this->t('Join Our Safety Community'),
      '#subtitle' => $this->t('Together, we\'re making Philadelphia safer for everyone'),
      '#why_join_title' => $this->t('Why Join?'),
      '#benefits' => [
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="" class="forseti-icon">',
          'title' => $this->t('Stay Informed'),
          'description' => $this->t('Get notified when you enter areas with elevated safety concerns based on your current geographic location and situational context, plus receive weekly safety summaries.'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_connected.png" alt="" class="forseti-icon">',
          'title' => $this->t('Connect with Neighbors'),
          'description' => $this->t('Join neighborhood watch groups, coordinate safety efforts, and build stronger community bonds.'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_capable.png" alt="" class="forseti-icon">',
          'title' => $this->t('Make an Impact'),
          'description' => $this->t('Report incidents, validate AI predictions, and contribute to the safety intelligence that protects your community.'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_useful.png" alt="" class="forseti-icon">',
          'title' => $this->t('Learn & Grow'),
          'description' => $this->t('Access safety resources, attend community events, and participate in safety awareness programs.'),
        ],
      ],
      '#how_to_title' => $this->t('How to Get Involved'),
      '#steps' => [
        [
          'title' => $this->t('1. Get Forseti Mobile'),
          'description' => $this->t('Download our mobile app for location-based safety alerts and on-the-go situational awareness.'),
          'button_url' => '/mobile-app',
          'button_text' => $this->t('Get the App'),
          'button_style' => 'primary',
        ],
        [
          'title' => $this->t('2. Create Account'),
          'description' => $this->t('Set up your profile, customize your alert preferences, and define your safety zones.'),
          'button_url' => '/user/register',
          'button_text' => $this->t('Sign Up'),
          'button_style' => 'outline-primary',
        ],
        [
          'title' => $this->t('3. Join Local Groups'),
          'description' => $this->t('Connect with neighborhood watch groups and community safety initiatives in your area.'),
          'button_disabled' => TRUE,
          'button_text' => $this->t('Coming Soon'),
          'button_style' => 'outline-primary',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['url'],
      ],
    ];
  }

  /**
   * Mobile App page.
   */
  public function mobileApp() {
    return [
      '#theme' => 'forseti_page_mobile_app',
      '#title' => $this->t('Forseti Mobile App'),
      '#subtitle' => $this->t('Your personal safety companion for Philadelphia'),
      '#beta_alert' => [
        'title' => '<i class="fas fa-mobile-alt"></i> ' . $this->t('Beta Testing Available!'),
        'description' => $this->t('The Forseti Mobile App is now available for beta testing on Android devices.'),
        'link_url' => '/mobile-app/beta-testing',
        'button_text' => $this->t('Access Beta Testing'),
        'note' => $this->t('Authenticated users only'),
      ],
      '#intro' => [
        'title' => $this->t('Safety in Your Pocket'),
        'description' => $this->t('Forseti Mobile will bring the power of AI monitoring directly to your smartphone. Get notified when you enter areas with elevated safety concerns, access location-based safety information, and one-touch emergency services.'),
      ],
      '#app_display' => [
        'logo' => '/themes/custom/forseti/images/logos/originals/forseti_safe.png',
        'android_icon' => '<i class="fab fa-android fa-2x text-success"></i>',
        'ios_icon' => '<i class="fab fa-apple fa-2x text-muted"></i>',
        'status' => $this->t('Beta Testing Available'),
        'availability' => $this->t('Android beta | iOS coming soon'),
      ],
      '#features_title' => $this->t('Planned Features'),
      '#features' => [
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="" class="forseti-icon">',
          'title' => $this->t('Location-Based Alerts'),
          'description' => $this->t('Automatic notifications when you enter high-risk areas or when incidents occur near your location.'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_energized.png" alt="" class="forseti-icon">',
          'title' => $this->t('Emergency SOS'),
          'description' => $this->t('One-touch access to emergency services with automatic location sharing and emergency contact notifications.'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_connected.png" alt="" class="forseti-icon">',
          'title' => $this->t('Interactive Maps'),
          'description' => $this->t('View real-time crime incidents, safety zones, and navigate the safest routes to your destination.'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_useful.png" alt="" class="forseti-icon">',
          'title' => $this->t('Incident Reporting'),
          'description' => $this->t('Quickly report suspicious activity or incidents with photos, descriptions, and automatic GPS tagging.'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_whole.png" alt="" class="forseti-icon">',
          'title' => $this->t('Check-In Feature'),
          'description' => $this->t('Let friends and family know you\'re safe with automatic check-ins and location sharing.'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_capable.png" alt="" class="forseti-icon">',
          'title' => $this->t('Offline Resources'),
          'description' => $this->t('Access safety tips, emergency contacts, and critical information even without an internet connection.'),
        ],
      ],
      '#cta' => [
        'title' => '<i class="fas fa-bell"></i> ' . $this->t('Get Notified When We Launch'),
        'description' => $this->t('Sign up for early access and be among the first to download the Forseti Mobile App when it becomes available.'),
        'button_url' => '/talk-with-forseti',
        'button_text' => $this->t('Request Early Access'),
      ],
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['url'],
      ],
    ];
  }

  /**
   * Mobile App Beta Testing page (authenticated users only).
   */
  public function mobileAppBeta() {
    return [
      '#theme' => 'forseti_page_mobile_app_beta',
      '#title' => $this->t('Beta Testing'),
      '#subtitle' => $this->t('Help us improve the Forseti Mobile App'),
      '#download_section' => [
        'title' => '<i class="fas fa-mobile-alt"></i> ' . $this->t('Beta Testing Now Available!'),
        'description' => $this->t('The Forseti Mobile App is now available for beta testing on Android devices. Help us improve by testing the app and providing feedback!'),
        'download_url' => '/sites/default/files/forseti/mobile/Forseti-latest.apk',
        'button_text' => '<i class="fas fa-download"></i> ' . $this->t('Download Beta (Android)'),
        'version_info' => '<strong>' . $this->t('Version 1.0.0') . '</strong> | ' . $this->t('Android 5.0+') . ' | 18MB',
        'availability' => $this->t('iOS version coming soon | Full launch: Q1 2026'),
      ],
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  /**
   * Privacy page.
   */
  public function privacy() {
    return [
      '#theme' => 'forseti_page_privacy',
      '#title' => $this->t('Privacy & Security'),
      '#commitment' => [
        'title' => $this->t('Our Commitment: Privacy First'),
        'content' => $this->t('At Forseti, we believe safety and privacy go hand-in-hand. We never sell your data, and we design every feature with your privacy in mind.'),
      ],
      '#data_collection' => [
        'title' => $this->t('Data Collection'),
        'what_we_collect' => [
          'title' => $this->t('What We Collect'),
          'items' => [
            $this->t('<strong>Crime Data:</strong> Public incident data from Philadelphia PD and emergency services'),
            $this->t('<strong>Location Data:</strong> Only when you explicitly enable location services'),
            $this->t('<strong>User Reports:</strong> Incident reports you voluntarily submit'),
            $this->t('<strong>Usage Analytics:</strong> Anonymous app usage data to improve our service'),
          ],
        ],
        'what_we_dont' => [
          'title' => $this->t('What We DON\'T Collect'),
          'items' => [
            '❌ ' . $this->t('Your browsing history outside Forseti'),
            '❌ ' . $this->t('Your contacts or messages'),
            '❌ ' . $this->t('Your photos (unless you choose to attach them to a report)'),
            '❌ ' . $this->t('Your personal conversations'),
          ],
        ],
      ],
      '#data_usage' => [
        'title' => $this->t('Data Usage'),
        'intro' => $this->t('We use your data exclusively to:'),
        'items' => [
          $this->t('Provide safety alerts relevant to your location'),
          $this->t('Improve our AI prediction models'),
          $this->t('Generate anonymized crime statistics'),
          $this->t('Communicate important safety information'),
        ],
      ],
      '#never_do' => [
        'title' => $this->t('We Never:'),
        'items' => [
          $this->t('Sell your personal information'),
          $this->t('Share your data with advertisers'),
          $this->t('Track you across other websites'),
          $this->t('Use your data for purposes you didn\'t consent to'),
        ],
      ],
      '#security_measures' => [
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="" class="forseti-icon">',
          'title' => $this->t('Encryption'),
          'description' => $this->t('All data is encrypted in transit (TLS 1.3) and at rest (AES-256).'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_capable.png" alt="" class="forseti-icon">',
          'title' => $this->t('Authentication'),
          'description' => $this->t('Multi-factor authentication and secure password policies.'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_free.png" alt="" class="forseti-icon">',
          'title' => $this->t('Access Controls'),
          'description' => $this->t('Strict role-based access with audit logging.'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_useful.png" alt="" class="forseti-icon">',
          'title' => $this->t('Regular Audits'),
          'description' => $this->t('Third-party security audits and penetration testing.'),
        ],
      ],
      '#rights' => [
        'title' => $this->t('Your Rights'),
        'intro' => $this->t('Under GDPR and other privacy laws, you have the right to:'),
        'items' => [
          $this->t('<strong>Access:</strong> Request a copy of all data we have about you'),
          $this->t('<strong>Correction:</strong> Update or correct inaccurate information'),
          $this->t('<strong>Deletion:</strong> Request deletion of your personal data'),
          $this->t('<strong>Portability:</strong> Export your data in a standard format'),
          $this->t('<strong>Opt-Out:</strong> Disable location tracking or notifications anytime'),
        ],
      ],
      '#anonymous_reporting' => [
        'title' => $this->t('Anonymous Reporting'),
        'intro' => $this->t('We offer completely anonymous incident reporting. When you choose this option:'),
        'items' => [
          $this->t('No account required'),
          $this->t('No location tracking'),
          $this->t('No identifying information stored'),
          $this->t('Reports still help improve community safety'),
        ],
      ],
      '#contact_cta' => [
        'title' => $this->t('Questions or Concerns?'),
        'content' => $this->t('If you have any questions about our privacy practices or want to exercise your rights, please <a href="/talk-with-forseti" class="alert-link">talk with Forseti</a>. We typically respond within 48 hours.'),
      ],
      '#last_updated' => $this->t('Last Updated: December 9, 2025'),
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['url'],
      ],
    ];
  }





  /**
   * Contact thank you page.
   */
  public function contactThankYou() {
    return [
      '#theme' => 'forseti_page_contact_thank_you',
      '#title' => $this->t('Thank You for Reaching Out!'),
      '#message' => [
        'title' => $this->t('Message Received'),
        'content' => $this->t('Your message has been received and we\'ll get back to you within 24-48 hours.'),
      ],
      '#what_next' => [
        'title' => $this->t('What Happens Next?'),
        'items' => [
          $this->t('<strong>Review:</strong> Our team will carefully review your message'),
          $this->t('<strong>Response:</strong> You\'ll receive a personal response via email'),
          $this->t('<strong>Support:</strong> We\'re committed to addressing your needs'),
        ],
      ],
      '#quick_links' => [
        'title' => $this->t('Explore More About Forseti'),
        'column1' => [
          '<a href="/" class="link-cyan">' . $this->t('Return Home') . '</a>',
          '<a href="/about" class="link-cyan">' . $this->t('Learn About Forseti') . '</a>',
          '<a href="/how-it-works" class="link-cyan">' . $this->t('How It Works') . '</a>',
        ],
        'column2' => [
          '<a href="/safety-map" class="link-cyan">' . $this->t('View Safety Map') . '</a>',
          '<a href="/mobile-app" class="link-cyan">' . $this->t('Download App') . '</a>',
          '<a href="/community" class="link-cyan">' . $this->t('Join Community') . '</a>',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['url'],
      ],
    ];
  }

  /**
   * Contact page.
   */
  public function contact() {
    return $this->getContactContent();
  }

  /**
   * Get Safety Map content.
   */
  private function getSafetyMapContent() {
    // Redirect to the fully functional crime map
    $response = new \Symfony\Component\HttpFoundation\RedirectResponse('/amisafe/crime-map');
    $response->send();
    return '';
  }

  /**
   * Get Contact content.
   */
  private function getContactContent() {
    // Get the webform entity
    $webform = \Drupal::entityTypeManager()
      ->getStorage('webform')
      ->load('contact_forseti');
    
    // Build the webform render array
    $webform_build = [];
    if ($webform) {
      $webform_build = $webform->getSubmissionForm();
    }
    
    $build = [];
    
    $build['header'] = [
      '#markup' => '
      <div class="container py-3">
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <h1 class="text-center mb-3 text-cyan">Contact Forseti</h1>
            <p class="text-center mb-4 text-muted-light">
              We\'re here to help make Philadelphia safer together
            </p>
            
            <div class="alert alert-emergency mb-4">
              <h4 class="text-danger-custom">🚨 Emergency?</h4>
              <p class="mb-0">
                For immediate emergencies, always call <strong>911</strong>. 
                Forseti is a safety information platform, not an emergency service.
              </p>
            </div>
            
            <p class="text-center mb-4 text-muted-light">
              Whether you have questions, ideas, or want to get involved, we\'d love to hear from you.
            </p>
            
            <div class="card card-forseti p-4 mb-4">
              <h2 class="mb-3 text-cyan">Send Us a Message</h2>',
    ];
    
    $build['webform'] = $webform_build;
    
    $build['form_footer'] = [
      '#markup' => '
            </div>',
    ];
    
    $build['footer'] = [
      '#markup' => '
            
            <div class="row mb-4">
              <div class="col-md-6 mb-3">
                <div class="card card-forseti h-100 text-center">
                  <div class="card-body">
                    <h4>📍 Location</h4>
                    <p class="text-muted-light">Philadelphia, PA</p>
                    <p class="text-muted-gray">Serving Greater Philadelphia</p>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6 mb-3">
                <div class="card card-forseti h-100 text-center">
                  <div class="card-body">
                    <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="" class="forseti-icon"> Support</h4>
                    <p class="text-muted-light">24/7 AI Monitoring</p>
                    <p class="text-muted-gray">Email support Mon-Fri 9am-6pm</p>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="card card-forseti p-4 mb-4">
              <h3 class="text-cyan mb-3">Quick Links</h3>
              <div class="row">
                <div class="col-md-6">
                  <ul>
                    <li><a href="/about" class="link-cyan">Learn about our mission</a></li>
                    <li><a href="/how-it-works" class="link-cyan">How Forseti works</a></li>
                    <li><a href="/community" class="link-cyan">Join our community</a></li>
                  </ul>
                </div>
                <div class="col-md-6">
                  <ul>
                    <li><a href="/mobile-app" class="link-cyan">Download Forseti app</a></li>
                    <li><a href="/privacy" class="link-cyan">Privacy & security info</a></li>
                    <li><a href="/safety-map" class="link-cyan">View safety map</a></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>',
    ];
    
    return $build;
  }


}
