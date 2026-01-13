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
        'title' => '<i class="fas fa-mobile-alt"></i> ' . $this->t('Beta Version Available Now!'),
        'description' => $this->t('The Forseti Mobile App v1.0.2 is now available for beta testing on Android devices with production authentication.'),
        'link_url' => 'https://forseti.life/sites/default/files/forseti/mobile/Forseti-latest.apk',
        'button_text' => $this->t('Download Android APK'),
        'note' => $this->t('~26 MB - Create an account to access all features'),
      ],
      '#intro' => [
        'title' => $this->t('Safety in Your Pocket'),
        'description' => $this->t('Forseti Mobile brings the power of AI monitoring directly to your smartphone. Get notified when you enter areas with elevated safety concerns, access location-based safety information with real-time crime data, and create your account to access personalized features.'),
      ],
      '#app_display' => [
        'logo' => '/themes/custom/forseti/images/logos/originals/forseti_safe.png',
        'android_icon' => '<i class="fab fa-android fa-2x text-success"></i>',
        'ios_icon' => '<i class="fab fa-apple fa-2x text-muted"></i>',
        'status' => $this->t('Beta v1.0.2 Available'),
        'availability' => $this->t('Android APK (~26 MB) | iOS coming soon'),
      ],
      '#features_title' => $this->t('Current Features'),
      '#features' => [
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="" class="forseti-icon">',
          'title' => $this->t('User Accounts & Authentication'),
          'description' => $this->t('Create your account and login with secure production authentication. Access personalized safety features.'),
        ],
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
        'title' => '<i class="fas fa-download"></i> ' . $this->t('Download Now'),
        'description' => $this->t('Get the Forseti Mobile App v1.0.2 Beta for Android. Create an account to unlock all features and stay safe in Philadelphia.'),
        'button_url' => 'https://forseti.life/sites/default/files/forseti/mobile/Forseti-latest.apk',
        'button_text' => $this->t('Download Android APK'),
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
        'version_info' => '<strong>' . $this->t('Version 1.0.0-1') . '</strong> | ' . $this->t('Android 6.0+') . ' | 51MB',
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

  /**
   * Legal hub page.
   */
  public function legal() {
    return [
      '#theme' => 'forseti_page_legal',
      '#title' => $this->t('Legal Information'),
      '#intro' => $this->t('Access all legal documents, policies, and terms governing the use of Forseti services.'),
      '#sections' => [
        [
          'title' => $this->t('User Agreements'),
          'links' => [
            ['url' => '/terms-of-service', 'text' => $this->t('Terms of Service')],
            ['url' => '/mobile-app-terms', 'text' => $this->t('Mobile App Terms')],
            ['url' => '/api-terms', 'text' => $this->t('API Terms of Use')],
            ['url' => '/ai-terms', 'text' => $this->t('AI Terms of Use')],
          ],
        ],
        [
          'title' => $this->t('Privacy & Data'),
          'links' => [
            ['url' => '/privacy', 'text' => $this->t('Privacy & Security')],
            ['url' => '/data-usage-policy', 'text' => $this->t('Data Usage Policy')],
            ['url' => '/cookie-policy', 'text' => $this->t('Cookie Policy')],
          ],
        ],
        [
          'title' => $this->t('Policies & Guidelines'),
          'links' => [
            ['url' => '/disclaimer', 'text' => $this->t('Disclaimer')],
            ['url' => '/community-guidelines', 'text' => $this->t('Community Guidelines')],
            ['url' => '/accessibility', 'text' => $this->t('Accessibility Statement')],
            ['url' => '/copyright', 'text' => $this->t('Copyright & DMCA')],
          ],
        ],
      ],
      '#last_updated' => date('F j, Y'),
      '#cache' => ['max-age' => 3600, 'contexts' => ['url']],
    ];
  }

  /**
   * Terms of Service page.
   */
  public function termsOfService() {
    return [
      '#theme' => 'forseti_page_terms',
      '#title' => $this->t('Terms of Service'),
      '#effective_date' => 'January 15, 2025',
      '#acceptance' => [
        'title' => $this->t('1. Acceptance of Terms'),
        'content' => $this->t('By accessing or using Forseti services, you agree to be bound by these Terms of Service. If you do not agree, please do not use our services.'),
      ],
      '#sections' => [
        [
          'title' => $this->t('2. Service Description'),
          'content' => $this->t('Forseti provides AI-powered community safety information, crime analytics, and alerts based on publicly available data. Our service is informational only and should not replace professional security services or emergency services.'),
        ],
        [
          'title' => $this->t('3. User Responsibilities'),
          'items' => [
            $this->t('Provide accurate information when creating reports'),
            $this->t('Use the service only for lawful purposes'),
            $this->t('Not attempt to manipulate or game our AI systems'),
            $this->t('Respect other users and community members'),
            $this->t('Not share false or misleading safety information'),
          ],
        ],
        [
          'title' => $this->t('4. Account Terms'),
          'items' => [
            $this->t('You must be 13 years or older to create an account'),
            $this->t('You are responsible for maintaining account security'),
            $this->t('You may not share your account credentials'),
            $this->t('One person or legal entity per account'),
            $this->t('We reserve the right to suspend accounts that violate these terms'),
          ],
        ],
        [
          'title' => $this->t('5. Limitations of Liability'),
          'content' => $this->t('Forseti provides information "as is" without warranty. We are not liable for damages arising from use of our service. Our safety predictions are estimates based on historical data and may not reflect current conditions. Always exercise caution and common sense.'),
        ],
        [
          'title' => $this->t('6. Service Modifications'),
          'content' => $this->t('We reserve the right to modify or discontinue services at any time. We will provide reasonable notice of material changes to these terms.'),
        ],
        [
          'title' => $this->t('7. Governing Law'),
          'content' => $this->t('These terms are governed by the laws of Pennsylvania, United States. Disputes will be resolved in Philadelphia County courts.'),
        ],
      ],
      '#contact' => $this->t('Questions? <a href="/talk-with-forseti" class="alert-link">Talk with Forseti</a>'),
      '#cache' => ['max-age' => 3600, 'contexts' => ['url']],
    ];
  }

  /**
   * Disclaimer page.
   */
  public function disclaimer() {
    return [
      '#theme' => 'forseti_page_disclaimer',
      '#title' => $this->t('Disclaimer'),
      '#warnings' => [
        [
          'icon' => '⚠️',
          'title' => $this->t('Not a Substitute for Emergency Services'),
          'content' => $this->t('Forseti is NOT a replacement for 911 or emergency services. In case of emergency, always call 911 immediately.'),
        ],
        [
          'icon' => '🤖',
          'title' => $this->t('AI-Generated & Research Content'),
          'content' => $this->t('Much of the content, data, and analysis on this platform has been researched, consolidated, and generated using Generative AI (GenAI) technologies. While we strive for accuracy, this content is a <strong>work in progress</strong> and may contain errors, inconsistencies, or outdated information. References are provided where available to support the information presented.'),
        ],
        [
          'icon' => '📊',
          'title' => $this->t('Informational Purposes Only'),
          'content' => $this->t('All data and predictions are provided for informational purposes only. Safety ratings are estimates based on historical data and may not reflect current conditions.'),
        ],
        [
          'icon' => '🔍',
          'title' => $this->t('AI Limitations'),
          'content' => $this->t('Our AI models make predictions based on patterns in historical data. No predictive system is 100% accurate. Always exercise personal judgment and caution.'),
        ],
        [
          'icon' => '⏰',
          'title' => $this->t('Data Delays'),
          'content' => $this->t('Crime data may be delayed by hours or days depending on source availability. Real-time conditions may differ from our data.'),
        ],
      ],
      '#no_guarantees' => [
        'title' => $this->t('No Guarantees'),
        'content' => $this->t('We do not guarantee the accuracy, completeness, or timeliness of any information. Use Forseti as one tool among many for situational awareness, not as your sole source of safety information.'),
      ],
      '#user_responsibility' => [
        'title' => $this->t('User Responsibility & Reporting Issues'),
        'content' => $this->t('You are responsible for your own safety. Forseti provides information to help you make informed decisions, but cannot and does not ensure your safety. Always be aware of your surroundings and trust your instincts. <strong>If you encounter any errors, concerns, or issues with our content or data, please <a href="/talk-with-forseti" class="alert-link">Talk with Forseti</a> to report them.</strong> We are committed to making corrections and improvements based on user feedback.'),
      ],
      '#liability_limitation' => [
        'title' => $this->t('Limitation of Liability'),
        'content' => $this->t('Forseti and its operators are not liable for any damages, injuries, or losses that may result from using or relying on our service. This includes but is not limited to: personal injury, property damage, financial loss, or emotional distress.'),
      ],
      '#cache' => ['max-age' => 3600, 'contexts' => ['url']],
    ];
  }

  /**
   * Cookie Policy page.
   */
  public function cookiePolicy() {
    return [
      '#theme' => 'forseti_page_cookie_policy',
      '#title' => $this->t('Cookie Policy'),
      '#intro' => $this->t('This policy explains how Forseti uses cookies and similar technologies.'),
      '#what_are_cookies' => [
        'title' => $this->t('What Are Cookies?'),
        'content' => $this->t('Cookies are small text files stored on your device that help websites remember your preferences and improve your experience.'),
      ],
      '#cookies_we_use' => [
        [
          'type' => $this->t('Essential Cookies'),
          'icon' => '🔑',
          'purpose' => $this->t('Required for basic site functionality'),
          'examples' => [
            $this->t('Session management'),
            $this->t('Authentication'),
            $this->t('Security features'),
          ],
          'can_disable' => FALSE,
        ],
        [
          'type' => $this->t('Analytics Cookies'),
          'icon' => '📊',
          'purpose' => $this->t('Help us understand how users interact with our site'),
          'examples' => [
            $this->t('Page views'),
            $this->t('Feature usage'),
            $this->t('Error tracking'),
          ],
          'can_disable' => TRUE,
        ],
        [
          'type' => $this->t('Preference Cookies'),
          'icon' => '⚙️',
          'purpose' => $this->t('Remember your settings and preferences'),
          'examples' => [
            $this->t('Language preferences'),
            $this->t('Map view settings'),
            $this->t('Notification preferences'),
          ],
          'can_disable' => TRUE,
        ],
      ],
      '#managing_cookies' => [
        'title' => $this->t('Managing Cookies'),
        'content' => $this->t('You can control cookies through your browser settings. Note that disabling essential cookies may impact site functionality.'),
        'browser_links' => [
          $this->t('<a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Chrome</a>'),
          $this->t('<a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" rel="noopener">Firefox</a>'),
          $this->t('<a href="https://support.apple.com/guide/safari/manage-cookies-sfri11471/mac" target="_blank" rel="noopener">Safari</a>'),
          $this->t('<a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener">Edge</a>'),
        ],
      ],
      '#third_party' => [
        'title' => $this->t('Third-Party Cookies'),
        'content' => $this->t('We do not use third-party advertising cookies. Any third-party cookies are limited to essential services like map providers or analytics.'),
      ],
      '#cache' => ['max-age' => 3600, 'contexts' => ['url']],
    ];
  }

  /**
   * Accessibility Statement page.
   */
  public function accessibility() {
    return [
      '#theme' => 'forseti_page_accessibility',
      '#title' => $this->t('Accessibility Statement'),
      '#commitment' => [
        'title' => $this->t('Our Commitment'),
        'content' => $this->t('Forseti is committed to ensuring digital accessibility for people with disabilities. We continuously work to improve the user experience for everyone.'),
      ],
      '#standards' => [
        'title' => $this->t('Standards We Follow'),
        'items' => [
          $this->t('<strong>WCAG 2.1 Level AA:</strong> Web Content Accessibility Guidelines'),
          $this->t('<strong>Section 508:</strong> US Federal accessibility requirements'),
          $this->t('<strong>ADA:</strong> Americans with Disabilities Act compliance'),
        ],
      ],
      '#features' => [
        [
          'icon' => '⌨️',
          'title' => $this->t('Keyboard Navigation'),
          'description' => $this->t('Full site navigation using keyboard only'),
        ],
        [
          'icon' => '📱',
          'title' => $this->t('Screen Reader Support'),
          'description' => $this->t('Compatible with JAWS, NVDA, and VoiceOver'),
        ],
        [
          'icon' => '🔍',
          'title' => $this->t('Adjustable Text'),
          'description' => $this->t('Text can be resized without loss of functionality'),
        ],
        [
          'icon' => '🎨',
          'title' => $this->t('High Contrast'),
          'description' => $this->t('Sufficient color contrast ratios throughout'),
        ],
        [
          'icon' => '🖱️',
          'title' => $this->t('Alternative Input'),
          'description' => $this->t('Support for voice input and assistive technologies'),
        ],
        [
          'icon' => '📝',
          'title' => $this->t('Clear Content'),
          'description' => $this->t('Plain language and logical content structure'),
        ],
      ],
      '#limitations' => [
        'title' => $this->t('Known Limitations'),
        'intro' => $this->t('We are aware of some accessibility limitations:'),
        'items' => [
          $this->t('Some map interactions may require mouse input'),
          $this->t('Real-time alerts may need additional screen reader optimization'),
          $this->t('Legacy PDF documents may not be fully accessible'),
        ],
        'working_on_it' => $this->t('We are actively working to address these issues.'),
      ],
      '#feedback' => [
        'title' => $this->t('Accessibility Feedback'),
        'content' => $this->t('We welcome feedback on accessibility. If you encounter barriers, please <a href="/talk-with-forseti" class="alert-link">talk with Forseti</a>. We strive to respond within 3 business days.'),
      ],
      '#assistive_tech' => [
        'title' => $this->t('Tested With'),
        'content' => $this->t('Forseti has been tested with: JAWS 2024, NVDA 2024, VoiceOver (macOS/iOS), TalkBack (Android), Dragon NaturallySpeaking, and ZoomText.'),
      ],
      '#cache' => ['max-age' => 3600, 'contexts' => ['url']],
    ];
  }

  /**
   * Data Usage Policy page.
   */
  public function dataUsage() {
    return [
      '#theme' => 'forseti_page_data_usage',
      '#title' => $this->t('Data Usage Policy'),
      '#intro' => $this->t('This policy explains how Forseti collects, uses, and protects your data.'),
      '#data_sources' => [
        [
          'title' => $this->t('Public Crime Data'),
          'icon' => '🚔',
          'description' => $this->t('Philadelphia Police Department open data portal'),
          'usage' => $this->t('Used for crime mapping, trend analysis, and safety predictions'),
        ],
        [
          'title' => $this->t('Location Data'),
          'icon' => '📍',
          'description' => $this->t('Your device location (with your explicit permission)'),
          'usage' => $this->t('Provides location-specific safety alerts and recommendations'),
        ],
        [
          'title' => $this->t('User Reports'),
          'icon' => '📝',
          'description' => $this->t('Incident reports submitted by community members'),
          'usage' => $this->t('Enhances real-time safety awareness and AI training'),
        ],
        [
          'title' => $this->t('Usage Analytics'),
          'icon' => '📊',
          'description' => $this->t('Anonymous data about how you use Forseti'),
          'usage' => $this->t('Helps us improve features and user experience'),
        ],
      ],
      '#how_we_use' => [
        'title' => $this->t('How We Use Your Data'),
        'items' => [
          $this->t('<strong>Safety Alerts:</strong> Send you timely warnings about nearby incidents'),
          $this->t('<strong>Personalization:</strong> Customize your experience based on your location and preferences'),
          $this->t('<strong>AI Improvement:</strong> Train and refine our prediction models'),
          $this->t('<strong>Research:</strong> Generate anonymized safety statistics and reports'),
          $this->t('<strong>Service Improvement:</strong> Identify bugs and optimize performance'),
        ],
      ],
      '#data_retention' => [
        'title' => $this->t('Data Retention'),
        'items' => [
          $this->t('<strong>Account Data:</strong> Retained until you delete your account'),
          $this->t('<strong>Location History:</strong> Stored for 90 days, then anonymized'),
          $this->t('<strong>User Reports:</strong> Retained indefinitely (anonymized after 1 year)'),
          $this->t('<strong>Usage Logs:</strong> 30 days detailed, 1 year aggregated'),
        ],
      ],
      '#data_sharing' => [
        'title' => $this->t('Data Sharing'),
        'never_shared' => [
          'title' => $this->t('We NEVER Share:'),
          'items' => [
            '❌ ' . $this->t('Your personal information with advertisers'),
            '❌ ' . $this->t('Your location history with third parties'),
            '❌ ' . $this->t('Individual user data for commercial purposes'),
          ],
        ],
        'may_share' => [
          'title' => $this->t('We MAY Share:'),
          'items' => [
            '✅ ' . $this->t('Anonymized, aggregated statistics with researchers'),
            '✅ ' . $this->t('Required data with law enforcement (with valid legal process)'),
            '✅ ' . $this->t('Public safety alerts with community partners'),
          ],
        ],
      ],
      '#your_control' => [
        'title' => $this->t('Your Data Controls'),
        'items' => [
          $this->t('Export all your data in JSON format'),
          $this->t('Delete your account and associated data'),
          $this->t('Opt out of analytics collection'),
          $this->t('Disable location tracking anytime'),
          $this->t('Control alert preferences'),
        ],
      ],
      '#cache' => ['max-age' => 3600, 'contexts' => ['url']],
    ];
  }

  /**
   * AI Terms of Use page.
   */
  public function aiTerms() {
    return [
      '#theme' => 'forseti_page_ai_terms',
      '#title' => $this->t('AI Terms of Use'),
      '#intro' => $this->t('These terms govern your interactions with Forseti\'s AI systems, including conversations and AI-generated content.'),
      '#ai_capabilities' => [
        'title' => $this->t('AI Capabilities'),
        'items' => [
          $this->t('<strong>Conversational AI:</strong> Natural language interactions about safety'),
          $this->t('<strong>Predictive Analytics:</strong> Crime pattern analysis and forecasting'),
          $this->t('<strong>Risk Assessment:</strong> Location-based safety scoring'),
          $this->t('<strong>Content Generation:</strong> Safety reports and recommendations'),
        ],
      ],
      '#limitations' => [
        'title' => $this->t('AI Limitations & Disclaimers'),
        'items' => [
          $this->t('<strong>Not Perfect:</strong> AI can make mistakes or provide inaccurate information'),
          $this->t('<strong>Not Legal Advice:</strong> AI responses are informational only'),
          $this->t('<strong>Not Emergency Response:</strong> AI cannot dispatch emergency services'),
          $this->t('<strong>Historical Data:</strong> Predictions based on past patterns, not guarantees'),
          $this->t('<strong>Bias Awareness:</strong> We work to minimize bias but cannot eliminate it entirely'),
        ],
      ],
      '#conversation_guidelines' => [
        'title' => $this->t('Conversation Guidelines'),
        'dos' => [
          'title' => $this->t('Do:'),
          'items' => [
            '✅ ' . $this->t('Ask about safety information and crime patterns'),
            '✅ ' . $this->t('Request clarification if something is unclear'),
            '✅ ' . $this->t('Report problems or inaccuracies'),
            '✅ ' . $this->t('Use conversations for legitimate safety purposes'),
          ],
        ],
        'donts' => [
          'title' => $this->t('Don\'t:'),
          'items' => [
            '❌ ' . $this->t('Try to manipulate or "jailbreak" the AI'),
            '❌ ' . $this->t('Use AI to plan illegal activities'),
            '❌ ' . $this->t('Share sensitive personal information unnecessarily'),
            '❌ ' . $this->t('Rely solely on AI for emergency situations'),
          ],
        ],
      ],
      '#data_usage' => [
        'title' => $this->t('AI Data Usage'),
        'content' => $this->t('Conversations with Forseti AI are used to:'),
        'items' => [
          $this->t('Provide you with relevant safety information'),
          $this->t('Improve AI response quality and accuracy'),
          $this->t('Identify and fix bugs or issues'),
          $this->t('Train future AI models (anonymized)'),
        ],
      ],
      '#intellectual_property' => [
        'title' => $this->t('Intellectual Property'),
        'content' => $this->t('AI-generated content is provided "as is" for your personal use. You may share safety information generated by Forseti, but you must attribute it to Forseti and not claim it as your own original work.'),
      ],
      '#human_oversight' => [
        'title' => $this->t('Human Oversight'),
        'content' => $this->t('Our AI systems operate with human oversight. Critical safety decisions and content moderation involve human review. You can always request to speak with a human team member.'),
      ],
      '#cache' => ['max-age' => 3600, 'contexts' => ['url']],
    ];
  }

  /**
   * Mobile App Terms page.
   */
  public function mobileAppTerms() {
    return [
      '#theme' => 'forseti_page_mobile_app_terms',
      '#title' => $this->t('Mobile App Terms'),
      '#intro' => $this->t('These terms apply specifically to the Forseti mobile application for iOS and Android.'),
      '#license' => [
        'title' => $this->t('App License'),
        'content' => $this->t('Forseti grants you a limited, non-exclusive, non-transferable license to use the mobile app for personal, non-commercial purposes. This license does not include the right to:'),
        'restrictions' => [
          '❌ ' . $this->t('Reverse engineer or decompile the app'),
          '❌ ' . $this->t('Remove copyright or proprietary notices'),
          '❌ ' . $this->t('Use the app for commercial purposes without permission'),
          '❌ ' . $this->t('Redistribute or resell the app'),
        ],
      ],
      '#permissions' => [
        [
          'permission' => $this->t('Location Services'),
          'icon' => '📍',
          'why_needed' => $this->t('Required for location-based safety alerts'),
          'required' => TRUE,
        ],
        [
          'permission' => $this->t('Push Notifications'),
          'icon' => '🔔',
          'why_needed' => $this->t('Receive real-time safety alerts'),
          'required' => FALSE,
        ],
        [
          'permission' => $this->t('Camera'),
          'icon' => '📷',
          'why_needed' => $this->t('Attach photos to incident reports'),
          'required' => FALSE,
        ],
        [
          'permission' => $this->t('Background Location'),
          'icon' => '🌍',
          'why_needed' => $this->t('Monitor safety even when app is closed'),
          'required' => FALSE,
        ],
      ],
      '#platform_specific' => [
        [
          'platform' => 'iOS',
          'terms' => $this->t('Subject to the <a href="https://www.apple.com/legal/internet-services/itunes/dev/stdeula/" target="_blank" rel="noopener">Apple App Store Terms</a>'),
          'requirements' => $this->t('Requires iOS 14.0 or later'),
        ],
        [
          'platform' => 'Android',
          'terms' => $this->t('Subject to the <a href="https://play.google.com/intl/en_us/about/play-terms/" target="_blank" rel="noopener">Google Play Terms</a>'),
          'requirements' => $this->t('Requires Android 8.0 or later'),
        ],
      ],
      '#background_location' => [
        'title' => $this->t('Background Location Usage'),
        'content' => $this->t('If you enable background location, Forseti can alert you to safety concerns even when the app is closed. We:'),
        'items' => [
          '✅ ' . $this->t('Only check location periodically (not continuously)'),
          '✅ ' . $this->t('Minimize battery impact'),
          '✅ ' . $this->t('Encrypt location data in transit and at rest'),
          '✅ ' . $this->t('Allow you to disable this feature anytime'),
        ],
      ],
      '#updates' => [
        'title' => $this->t('App Updates'),
        'content' => $this->t('We regularly update the app with new features, improvements, and security patches. Some updates may be required to continue using the service. We recommend enabling automatic updates.'),
      ],
      '#termination' => [
        'title' => $this->t('Termination'),
        'content' => $this->t('We may suspend or terminate your access to the mobile app if you violate these terms. You may stop using the app and delete it from your device at any time.'),
      ],
      '#cache' => ['max-age' => 3600, 'contexts' => ['url']],
    ];
  }

  /**
   * API Terms of Use page.
   */
  public function apiTerms() {
    return [
      '#theme' => 'forseti_page_api_terms',
      '#title' => $this->t('API Terms of Use'),
      '#intro' => $this->t('These terms govern access to and use of the Forseti API.'),
      '#access' => [
        'title' => $this->t('API Access'),
        'content' => $this->t('API access is currently limited to approved partners and researchers. To request access, <a href="/talk-with-forseti" class="alert-link">talk with Forseti</a>.'),
        'tiers' => [
          [
            'tier' => $this->t('Research Tier'),
            'description' => $this->t('For academic researchers and non-profits'),
            'limits' => $this->t('1,000 requests/day'),
            'cost' => $this->t('Free'),
          ],
          [
            'tier' => $this->t('Developer Tier'),
            'description' => $this->t('For app developers and integrations'),
            'limits' => $this->t('10,000 requests/day'),
            'cost' => $this->t('Contact us'),
          ],
          [
            'tier' => $this->t('Enterprise Tier'),
            'description' => $this->t('For organizations and institutions'),
            'limits' => $this->t('Custom'),
            'cost' => $this->t('Contact us'),
          ],
        ],
      ],
      '#acceptable_use' => [
        'title' => $this->t('Acceptable Use'),
        'allowed' => [
          'title' => $this->t('Allowed Uses:'),
          'items' => [
            '✅ ' . $this->t('Research and academic study'),
            '✅ ' . $this->t('Non-commercial community safety apps'),
            '✅ ' . $this->t('News and journalism'),
            '✅ ' . $this->t('Public safety applications'),
          ],
        ],
        'prohibited' => [
          'title' => $this->t('Prohibited Uses:'),
          'items' => [
            '❌ ' . $this->t('Scraping or bulk downloading data'),
            '❌ ' . $this->t('Surveillance or stalking'),
            '❌ ' . $this->t('Discrimination or bias amplification'),
            '❌ ' . $this->t('Competing products without permission'),
            '❌ ' . $this->t('Exceeding rate limits'),
          ],
        ],
      ],
      '#rate_limits' => [
        'title' => $this->t('Rate Limits & Quotas'),
        'content' => $this->t('All API tiers have rate limits to ensure fair usage:'),
        'items' => [
          $this->t('<strong>Requests per second:</strong> 10'),
          $this->t('<strong>Daily quota:</strong> Based on your tier'),
          $this->t('<strong>Response time:</strong> Typically < 500ms'),
          $this->t('<strong>Retry policy:</strong> Exponential backoff recommended'),
        ],
      ],
      '#authentication' => [
        'title' => $this->t('Authentication'),
        'content' => $this->t('API access requires authentication via API keys:'),
        'items' => [
          $this->t('Include API key in the <code>Authorization</code> header'),
          $this->t('Keep your API keys secret and secure'),
          $this->t('Rotate keys regularly'),
          $this->t('Never commit keys to public repositories'),
          $this->t('Report compromised keys immediately'),
        ],
      ],
      '#data_attribution' => [
        'title' => $this->t('Data Attribution'),
        'content' => $this->t('If you display Forseti data in your application, you must:'),
        'items' => [
          $this->t('Clearly attribute data to Forseti'),
          $this->t('Include a link back to forseti.life'),
          $this->t('Display data freshness (timestamp)'),
          $this->t('Include appropriate disclaimers'),
        ],
      ],
      '#liability' => [
        'title' => $this->t('Liability & Support'),
        'content' => $this->t('The API is provided "as is" without warranty. We are not liable for damages arising from API use. Support response times vary by tier.'),
      ],
      '#termination' => [
        'title' => $this->t('Termination'),
        'content' => $this->t('We may suspend or revoke API access for violations of these terms. You may terminate access by deleting your API keys.'),
      ],
      '#cache' => ['max-age' => 3600, 'contexts' => ['url']],
    ];
  }

  /**
   * Community Guidelines page.
   */
  public function communityGuidelines() {
    return [
      '#theme' => 'forseti_page_community_guidelines',
      '#title' => $this->t('Community Guidelines'),
      '#intro' => $this->t('Forseti is a community built on mutual respect, safety, and shared responsibility. These guidelines help maintain a positive environment for everyone.'),
      '#core_values' => [
        [
          'icon' => '🤝',
          'title' => $this->t('Respect'),
          'description' => $this->t('Treat all community members with dignity and kindness'),
        ],
        [
          'icon' => '✅',
          'title' => $this->t('Honesty'),
          'description' => $this->t('Share accurate information and report truthfully'),
        ],
        [
          'icon' => '<img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="" class="forseti-icon">',
          'title' => $this->t('Safety First'),
          'description' => $this->t('Prioritize community safety in all interactions'),
        ],
        [
          'icon' => '🌍',
          'title' => $this->t('Inclusivity'),
          'description' => $this->t('Welcome and respect people from all backgrounds'),
        ],
      ],
      '#expected_behavior' => [
        'title' => $this->t('Expected Behavior'),
        'items' => [
          '✅ ' . $this->t('Report incidents accurately and promptly'),
          '✅ ' . $this->t('Respect others\' privacy and safety'),
          '✅ ' . $this->t('Provide constructive feedback'),
          '✅ ' . $this->t('Help new community members'),
          '✅ ' . $this->t('Follow local laws and regulations'),
          '✅ ' . $this->t('Use appropriate language'),
        ],
      ],
      '#prohibited_behavior' => [
        'title' => $this->t('Prohibited Behavior'),
        'items' => [
          '❌ ' . $this->t('False or misleading reports'),
          '❌ ' . $this->t('Harassment, bullying, or threats'),
          '❌ ' . $this->t('Hate speech or discrimination'),
          '❌ ' . $this->t('Sharing personal information of others'),
          '❌ ' . $this->t('Spamming or commercial solicitation'),
          '❌ ' . $this->t('Vigilantism or encouraging violence'),
          '❌ ' . $this->t('Gaming or manipulating the system'),
        ],
      ],
      '#reporting_incidents' => [
        'title' => $this->t('Reporting Incidents'),
        'best_practices' => [
          'title' => $this->t('Best Practices:'),
          'items' => [
            $this->t('Be specific and factual'),
            $this->t('Include relevant details (time, location, type)'),
            $this->t('Add context without speculation'),
            $this->t('Respect privacy - don\'t include identifying information'),
            $this->t('Update if situation changes'),
          ],
        ],
        'avoid' => [
          'title' => $this->t('Avoid:'),
          'items' => [
            $this->t('Inflammatory language'),
            $this->t('Unverified rumors'),
            $this->t('Personal opinions as facts'),
            $this->t('Duplicate reports'),
          ],
        ],
      ],
      '#consequences' => [
        'title' => $this->t('Consequences of Violations'),
        'levels' => [
          [
            'level' => $this->t('First Offense'),
            'action' => $this->t('Warning and education'),
          ],
          [
            'level' => $this->t('Second Offense'),
            'action' => $this->t('Temporary suspension (7-30 days)'),
          ],
          [
            'level' => $this->t('Serious/Repeated Offenses'),
            'action' => $this->t('Permanent ban'),
          ],
        ],
        'appeal' => $this->t('You may appeal moderation decisions by <a href="/talk-with-forseti" class="alert-link">talking with Forseti</a>.'),
      ],
      '#reporting_violations' => [
        'title' => $this->t('Reporting Guideline Violations'),
        'content' => $this->t('If you see someone violating these guidelines, please report it through the app or <a href="/talk-with-forseti" class="alert-link">talk with Forseti</a>. All reports are reviewed by human moderators.'),
      ],
      '#cache' => ['max-age' => 3600, 'contexts' => ['url']],
    ];
  }

  /**
   * Copyright & DMCA page.
   */
  public function copyright() {
    return [
      '#theme' => 'forseti_page_copyright',
      '#title' => $this->t('Copyright & DMCA'),
      '#copyright_notice' => [
        'title' => $this->t('Copyright Notice'),
        'content' => $this->t('© ' . date('Y') . ' Forseti. All rights reserved. Forseti name, logo, and original content are protected by copyright, trademark, and other intellectual property laws.'),
      ],
      '#ownership' => [
        'title' => $this->t('Content Ownership'),
        'forseti_content' => [
          'title' => $this->t('Forseti-Created Content:'),
          'items' => [
            $this->t('Website design and code'),
            $this->t('Forseti branding and logos'),
            $this->t('AI-generated safety reports'),
            $this->t('Original documentation and guides'),
          ],
        ],
        'user_content' => [
          'title' => $this->t('User-Generated Content:'),
          'content' => $this->t('You retain ownership of content you submit (incident reports, photos, etc.). By submitting content, you grant Forseti a non-exclusive, worldwide license to use, display, and distribute it for safety purposes.'),
        ],
        'public_data' => [
          'title' => $this->t('Public Data:'),
          'content' => $this->t('Crime statistics from Philadelphia PD and other public sources remain in the public domain. Forseti\'s analysis and presentation of this data is our intellectual property.'),
        ],
      ],
      '#fair_use' => [
        'title' => $this->t('Permitted Uses'),
        'items' => [
          '✅ ' . $this->t('News reporting and commentary'),
          '✅ ' . $this->t('Academic research and education'),
          '✅ ' . $this->t('Personal, non-commercial use'),
          '✅ ' . $this->t('Linking to our content'),
        ],
      ],
      '#dmca_policy' => [
        'title' => $this->t('DMCA Takedown Policy'),
        'content' => $this->t('Forseti respects intellectual property rights. If you believe content on our platform infringes your copyright, please send a DMCA takedown notice including:'),
        'requirements' => [
          $this->t('Your contact information'),
          $this->t('Description of copyrighted work'),
          $this->t('URL of infringing content'),
          $this->t('Statement of good faith belief'),
          $this->t('Statement of accuracy under penalty of perjury'),
          $this->t('Physical or electronic signature'),
        ],
      ],
      '#dmca_contact' => [
        'title' => $this->t('DMCA Agent'),
        'content' => $this->t('Send DMCA notices to: <strong>legal@forseti.life</strong><br>Response time: Within 72 hours'),
      ],
      '#counter_notice' => [
        'title' => $this->t('Counter-Notification'),
        'content' => $this->t('If your content was removed due to a DMCA notice and you believe it was removed in error, you may file a counter-notification. We will review and may restore the content if appropriate.'),
      ],
      '#repeat_infringers' => [
        'title' => $this->t('Repeat Infringer Policy'),
        'content' => $this->t('Users who repeatedly infringe copyrights will have their accounts terminated.'),
      ],
      '#trademark' => [
        'title' => $this->t('Trademark'),
        'content' => $this->t('Forseti® and the Forseti logo are trademarks. You may not use our trademarks without written permission, except as necessary for fair use (e.g., news articles about Forseti).'),
      ],
      '#cache' => ['max-age' => 3600, 'contexts' => ['url']],
    ];
  }

}
