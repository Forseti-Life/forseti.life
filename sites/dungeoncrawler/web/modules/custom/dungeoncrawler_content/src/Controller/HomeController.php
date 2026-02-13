<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for the homepage.
 */
class HomeController extends ControllerBase {

  /**
   * Display the homepage.
   *
   * @return array
   *   A render array for the homepage.
   */
  public function index() {
    $campaignHubUrl = Url::fromRoute('dungeoncrawler_content.campaigns')->toString();
    $isAuthenticated = $this->currentUser()->isAuthenticated();

    $primaryCtaUrl = $campaignHubUrl;
    $primaryCtaLabel = $this->t('Start Your Adventure');
    $secondaryCtaLabel = $this->t('Learn More');

    if (!$isAuthenticated) {
      $primaryCtaUrl = Url::fromRoute('user.login', [], [
        'query' => ['destination' => $campaignHubUrl],
      ])->toString();
      $primaryCtaLabel = $this->t('Sign In to Start');
      $secondaryCtaLabel = $this->t('Learn How It Works');
    }

    // Home page entry-point render array for process flow.
    return [
      '#theme' => 'page__front',
      '#primary_cta_url' => $primaryCtaUrl,
      '#primary_cta_label' => $primaryCtaLabel,
      '#secondary_cta_url' => Url::fromRoute('dungeoncrawler_content.how_to_play')->toString(),
      '#secondary_cta_label' => $secondaryCtaLabel,
      '#creation_url' => $campaignHubUrl,
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['user.roles:authenticated'],
      ],
    ];
  }

}
