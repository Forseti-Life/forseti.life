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
    return [
      '#theme' => 'nfr_public_page',
      '#page_id' => 'faq',
      '#content' => [
        '#markup' => '<h2>Frequently Asked Questions</h2><p>Placeholder content for FAQ page.</p>',
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
    return [
      '#theme' => 'nfr_public_page',
      '#page_id' => 'contact',
      '#content' => [
        '#markup' => '<h2>Contact Us</h2><p>Placeholder content for Contact page.</p>',
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
