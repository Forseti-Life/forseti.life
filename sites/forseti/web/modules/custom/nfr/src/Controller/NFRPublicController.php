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
    // If user is logged in, redirect to welcome page
    if ($this->currentUser()->isAuthenticated()) {
      return new \Symfony\Component\HttpFoundation\RedirectResponse(
        \Drupal\Core\Url::fromRoute('nfr.welcome')->toString()
      );
    }

    return [
      '#theme' => 'nfr_public_page',
      '#page_id' => 'home',
      '#content' => [
        'hero' => [
          '#markup' => '<div class="nfr-hero"><h1>National Firefighter Registry</h1><p>Help advance cancer research for firefighters.</p><a href="/user/register" class="btn btn-primary">Register Now</a><a href="/user/login" class="btn btn-secondary">Log In</a></div>',
        ],
        'intro' => [
          '#markup' => '<p>The National Firefighter Registry is a research initiative to better understand cancer among firefighters.</p>',
        ],
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
