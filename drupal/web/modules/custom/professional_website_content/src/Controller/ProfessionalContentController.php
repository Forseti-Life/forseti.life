<?php

namespace Drupal\professional_website_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for professional website content pages.
 */
class ProfessionalContentController extends ControllerBase {

  /**
   * Display the services page.
   */
  public function servicesPage() {
    // Redirect to the Services node page
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'page')
      ->condition('title', 'Services')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->range(0, 1);
    
    $nids = $query->execute();
    if (!empty($nids)) {
      $nid = reset($nids);
      $url = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
      return new RedirectResponse($url->toString());
    }
    
    return [
      '#markup' => $this->t('Services page not found. Please install the professional content first.'),
    ];
  }

  /**
   * Display the FinTech solutions page.
   */
  public function fintechPage() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'page')
      ->condition('title', 'FinTech Solutions')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->range(0, 1);
    
    $nids = $query->execute();
    if (!empty($nids)) {
      $nid = reset($nids);
      $url = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
      return new RedirectResponse($url->toString());
    }
    
    return [
      '#markup' => $this->t('FinTech Solutions page not found. Please install the professional content first.'),
    ];
  }

  /**
   * Display the Healthcare solutions page.
   */
  public function healthcarePage() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'page')
      ->condition('title', 'Healthcare Solutions')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->range(0, 1);
    
    $nids = $query->execute();
    if (!empty($nids)) {
      $nid = reset($nids);
      $url = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
      return new RedirectResponse($url->toString());
    }
    
    return [
      '#markup' => $this->t('Healthcare Solutions page not found. Please install the professional content first.'),
    ];
  }

  /**
   * Display the Energy solutions page.
   */
  public function energyPage() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'page')
      ->condition('title', 'Energy Solutions')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->range(0, 1);
    
    $nids = $query->execute();
    if (!empty($nids)) {
      $nid = reset($nids);
      $url = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
      return new RedirectResponse($url->toString());
    }
    
    return [
      '#markup' => $this->t('Energy Solutions page not found. Please install the professional content first.'),
    ];
  }

  /**
   * Display the Product Prototyping page.
   */
  public function productPrototypingPage() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'page')
      ->condition('title', 'Product Prototyping')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->range(0, 1);
    
    $nids = $query->execute();
    if (!empty($nids)) {
      $nid = reset($nids);
      $url = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
      return new RedirectResponse($url->toString());
    }
    
    return [
      '#markup' => $this->t('Product Prototyping page not found. Please install the professional content first.'),
    ];
  }

}