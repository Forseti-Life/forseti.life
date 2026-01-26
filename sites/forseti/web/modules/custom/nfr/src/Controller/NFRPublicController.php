<?php

declare(strict_types=1);

namespace Drupal\nfr\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for NFR public pages.
 */
class NFRPublicController extends ControllerBase {

  /**
   * Home/Landing page.
   *
   * @return array
   *   Render array.
   */
  public function home(): array {
    $current_user = $this->currentUser();
    $user_storage = $this->entityTypeManager()->getStorage('user');
    
    // Build role-specific welcome links
    $role_links = [];
    
    if ($current_user->isAuthenticated()) {
      $user = $user_storage->load($current_user->id());
      $roles = $user->getRoles(TRUE); // Exclude 'authenticated'
      
      // Determine primary role and redirect
      if (in_array('nfr_administrator', $roles)) {
        $role_links['primary'] = [
          'title' => 'Administrator Dashboard',
          'url' => '/admin/nfr',
          'description' => 'Manage participants, monitor data quality, and oversee registry operations.',
        ];
      }
      elseif (in_array('nfr_researcher', $roles)) {
        $role_links['primary'] = [
          'title' => 'Research Dashboard',
          'url' => '/admin/nfr/reports',
          'description' => 'Access research reports and export de-identified data.',
        ];
      }
      elseif (in_array('fire_dept_admin', $roles)) {
        $role_links['primary'] = [
          'title' => 'Department Dashboard',
          'url' => '/nfr/firefighters',
          'description' => 'View your department\'s participation and enrollment status.',
        ];
      }
      else {
        // Firefighter or authenticated user
        $role_links['primary'] = [
          'title' => 'My Dashboard',
          'url' => '/nfr/my-dashboard',
          'description' => 'View your enrollment status and manage your profile.',
        ];
      }
      
      // Common links for all authenticated users
      $role_links['enrollment'] = [
        'title' => 'Start Enrollment',
        'url' => '/nfr/welcome',
        'description' => 'Begin or continue your NFR enrollment process.',
      ];
    }

    return [
      '#theme' => 'nfr_home_page',
      '#authenticated' => $current_user->isAuthenticated(),
      '#role_links' => $role_links,
      '#attached' => [
        'library' => ['nfr/home'],
      ],
    ];
  }

  /**
   * About NFR page.
   *
   * @return array
   *   Render array.
   */
  public function about(): array {
    return [
      '#theme' => 'nfr_public_page',
      '#page_id' => 'about',
      '#content' => [
        '#markup' => '<h2>About the National Firefighter Registry</h2><p>Placeholder content for About page.</p>',
      ],
    ];
  }

  /**
   * How It Works page.
   *
   * @return array
   *   Render array.
   */
  public function howItWorks(): array {
    return [
      '#theme' => 'nfr_public_page',
      '#page_id' => 'how-it-works',
      '#content' => [
        '#markup' => '<h2>How It Works</h2><p>Placeholder content for How It Works page.</p>',
      ],
    ];
  }

  /**
   * Why Participate page.
   *
   * @return array
   *   Render array.
   */
  public function whyParticipate(): array {
    return [
      '#theme' => 'nfr_public_page',
      '#page_id' => 'why-participate',
      '#content' => [
        '#markup' => '<h2>Why Participate</h2><p>Placeholder content for Why Participate page.</p>',
      ],
    ];
  }

  /**
   * FAQ page.
   *
   * @return array
   *   Render array.
   */
  public function faq(): array {
    $html = '<div class="nfr-faq-page">';
    $html .= '<div class="container my-5">';
    
    // Header
    $html .= '<div class="row mb-5">';
    $html .= '<div class="col-12 text-center">';
    $html .= '<h1 class="display-4 mb-3">Frequently Asked Questions</h1>';
    $html .= '<p class="lead">Learn more about the National Firefighter Registry</p>';
    $html .= '</div></div>';
    
    // FAQ Items
    $html .= '<div class="row justify-content-center">';
    $html .= '<div class="col-lg-10">';
    
    // FAQ 1
    $html .= '<div class="faq-item mb-4">';
    $html .= '<h3 class="h4 mb-3">What is the NFR?</h3>';
    $html .= '<p>The National Firefighter Registry, or NFR, will be a large database of health and occupational information on firefighters that can be used to analyze and track cancer and identify occupational risk factors for cancer to help the public safety community, scientists, and public health and medical professionals find better ways to protect those who protect our communities and environment. With voluntary participation from firefighters, the NFR will include information about firefighter characteristics, work assignments and exposure, and relevant health details to monitor, track and improve our knowledge about cancer risks for firefighters.</p>';
    $html .= '</div>';
    
    // FAQ 2
    $html .= '<div class="faq-item mb-4">';
    $html .= '<h3 class="h4 mb-3">Who can enroll in the NFR?</h3>';
    $html .= '<p>We encourage anyone who is or ever has been a firefighter in the United States to join the NFR (provided they are 18 years of age or older). This includes all active and former firefighters, such as volunteer, paid-on-call, part time, seasonal, and career firefighters. It also includes wildland firefighters, fire-cause investigators, fire instructors, industrial firefighters, airport-rescue firefighters, and other subspecialties of the fire service. There is no minimum service time required to register in the NFR. The more firefighters who register, the more we can learn about the cancer risk in the fire service.</p>';
    $html .= '</div>';
    
    // FAQ 3
    $html .= '<div class="faq-item mb-4">';
    $html .= '<h3 class="h4 mb-3">Is a cancer diagnosis required to enroll?</h3>';
    $html .= '<p>No. In fact, firefighters without a cancer diagnosis are just as critical to making the NFR a success as those who have received a cancer diagnosis. NIOSH would like all firefighters to be part of the NFR, not just those with cancer or other illnesses.</p>';
    $html .= '</div>';
    
    // FAQ 4
    $html .= '<div class="faq-item mb-4">';
    $html .= '<h3 class="h4 mb-3">Do firefighters have to join the NFR?</h3>';
    $html .= '<p>No. Being part of the NFR is completely voluntary, and no one can make a firefighter join. NIOSH needs your consent for you to be part of the NFR. However, participation is strongly encouraged because it will help improve the health and safety of the firefighter community today and in the future. The NFR is your opportunity to leave a legacy for those who follow you.</p>';
    $html .= '</div>';
    
    // FAQ 5
    $html .= '<div class="faq-item mb-4">';
    $html .= '<h3 class="h4 mb-3">Do NFR participants need to contact NIOSH if they are diagnosed with cancer?</h3>';
    $html .= '<p>No. NIOSH will be able to track information related to cancer by linking information on individual firefighters enrolled in the NFR with state cancer registries. Providing the last 4 digits of your social security number will ensure that these linkages can be made accurately. Firefighters should consult with their doctor if they have any concerns about their health.</p>';
    $html .= '</div>';
    
    // FAQ 6
    $html .= '<div class="faq-item mb-4">';
    $html .= '<h3 class="h4 mb-3">How will we protect firefighter data?</h3>';
    $html .= '<p>Firefighter data is stored securely with multiple layers of encryption and is only accessible to NIOSH-approved staff with necessary training and security clearance. Firefighters\' identifying information is protected under the highest level of government protection (known as an Assurance of Confidentiality), and firefighters can be sure their information will never be given to fire departments, insurance companies, or anyone else not involved with the NFR program.</p>';
    $html .= '</div>';
    
    $html .= '</div></div>'; // col, row
    
    // Call to Action
    $html .= '<div class="row justify-content-center mt-5">';
    $html .= '<div class="col-lg-10">';
    $html .= '<div class="card bg-light border-0">';
    $html .= '<div class="card-body text-center p-5">';
    $html .= '<h3 class="mb-3">Still Have Questions?</h3>';
    $html .= '<p class="mb-4">Contact the NFR Help Desk for assistance with enrollment or technical support.</p>';
    $html .= '<a href="/nfr/contact" class="btn btn-primary btn-lg">Contact Us</a>';
    $html .= '</div></div>';
    $html .= '</div></div>';
    
    // Additional Resources
    $html .= '<div class="row justify-content-center mt-4">';
    $html .= '<div class="col-lg-10">';
    $html .= '<div class="card border-0">';
    $html .= '<div class="card-body text-center p-4">';
    $html .= '<h4 class="mb-3">Additional Resources</h4>';
    $html .= '<p class="mb-3">For more information about firefighter health and safety, visit the CDC NIOSH Firefighter Resources page.</p>';
    $html .= '<a href="https://www.cdc.gov/niosh/firefighters/resources/index.html" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">';
    $html .= 'CDC NIOSH Firefighter Resources <i class="bi bi-box-arrow-up-right ms-1"></i>';
    $html .= '</a>';
    $html .= '</div></div>';
    $html .= '</div></div>';
    
    $html .= '</div></div>'; // container, nfr-faq-page
    
    return [
      '#markup' => $html,
      '#attached' => [
        'library' => ['nfr/nfr-styles'],
      ],
    ];
  }

  /**
   * Contact Us page.
   *
   * @return array
   *   Render array.
   */
  public function contact(): array {
    $html = '<div class="nfr-contact-page">';
    $html .= '<div class="container my-5">';
    
    // Header
    $html .= '<div class="row mb-4">';
    $html .= '<div class="col-12 text-center">';
    $html .= '<h1 class="display-4 mb-3">Contact the NFR Help Desk</h1>';
    $html .= '<p class="lead">We\'re here to assist you with enrollment, questions, and technical support.</p>';
    $html .= '</div></div>';
    
    // Contact Information Card
    $html .= '<div class="row justify-content-center">';
    $html .= '<div class="col-lg-8">';
    $html .= '<div class="card shadow-sm">';
    $html .= '<div class="card-body p-5">';
    
    // Phone
    $html .= '<div class="contact-method mb-4">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<svg class="me-3" width="32" height="32" fill="currentColor" viewBox="0 0 16 16"><path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.568 17.568 0 0 0 4.168 6.608 17.569 17.569 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.678.678 0 0 0-.58-.122l-2.19.547a1.745 1.745 0 0 1-1.657-.459L5.482 8.062a1.745 1.745 0 0 1-.46-1.657l.548-2.19a.678.678 0 0 0-.122-.58L3.654 1.328z"/></svg>';
    $html .= '<h3 class="mb-0">Phone</h3>';
    $html .= '</div>';
    $html .= '<p class="h4 text-primary mb-0"><a href="tel:833-489-1298" class="text-decoration-none">833-489-1298</a></p>';
    $html .= '<p class="text-muted small mb-0">Toll-free</p>';
    $html .= '</div>';
    
    // Email
    $html .= '<div class="contact-method mb-4">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<svg class="me-3" width="32" height="32" fill="currentColor" viewBox="0 0 16 16"><path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555zM0 4.697v7.104l5.803-3.558L0 4.697zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757zm3.436-.586L16 11.801V4.697l-5.803 3.546z"/></svg>';
    $html .= '<h3 class="mb-0">Email</h3>';
    $html .= '</div>';
    $html .= '<p class="h4 text-primary mb-0"><a href="mailto:NFRegistry@cdc.gov" class="text-decoration-none">NFRegistry@cdc.gov</a></p>';
    $html .= '</div>';
    
    // Hours
    $html .= '<div class="contact-method">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<svg class="me-3" width="32" height="32" fill="currentColor" viewBox="0 0 16 16"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/></svg>';
    $html .= '<h3 class="mb-0">Hours of Operation</h3>';
    $html .= '</div>';
    $html .= '<p class="mb-1"><strong>Monday - Friday</strong></p>';
    $html .= '<p class="mb-0">8:30 AM - 5:00 PM EST</p>';
    $html .= '</div>';
    
    $html .= '</div></div>'; // card-body, card
    $html .= '</div></div>'; // col, row
    
    // Additional Information
    $html .= '<div class="row justify-content-center mt-4">';
    $html .= '<div class="col-lg-8">';
    $html .= '<div class="alert alert-info">';
    $html .= '<h5>Before You Contact Us</h5>';
    $html .= '<ul class="mb-0">';
    $html .= '<li>Check our <a href="/nfr/faq">FAQ page</a> for answers to common questions</li>';
    $html .= '<li>Have your Participant ID ready if you\'re already enrolled</li>';
    $html .= '<li>For technical issues, please describe the problem in detail</li>';
    $html .= '</ul>';
    $html .= '</div>';
    $html .= '</div></div>';
    
    $html .= '</div></div>'; // container, nfr-contact-page
    
    return [
      '#markup' => $html,
      '#attached' => [
        'library' => ['nfr/nfr-styles'],
      ],
    ];
  }

  /**
   * Public Data/Statistics page.
   *
   * @return array
   *   Render array.
   */
  public function publicData(): array {
    return [
      '#theme' => 'nfr_public_page',
      '#page_id' => 'public-data',
      '#content' => [
        '#markup' => '<h2>Public Statistics</h2><p>Placeholder content for public data dashboard.</p>',
      ],
    ];
  }

  /**
   * Privacy Policy page.
   *
   * @return array
   *   Render array.
   */
  public function privacy(): array {
    return [
      '#theme' => 'nfr_public_page',
      '#page_id' => 'privacy',
      '#content' => [
        '#markup' => '<h2>Privacy Policy</h2><p>Placeholder content for Privacy Policy.</p>',
      ],
    ];
  }

  /**
   * Terms of Service page.
   *
   * @return array
   *   Render array.
   */
  public function terms(): array {
    return [
      '#theme' => 'nfr_public_page',
      '#page_id' => 'terms',
      '#content' => [
        '#markup' => '<h2>Terms of Service</h2><p>Placeholder content for Terms of Service.</p>',
      ],
    ];
  }

}
