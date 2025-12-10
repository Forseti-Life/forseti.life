<?php

namespace Drupal\forseti_safety_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller to redirect front page to /home.
 */
class ForsetiFrontRedirectController extends ControllerBase {

  /**
   * Redirects to the home page.
   */
  public function redirectToHome() {
    \Drupal::logger('forseti')->notice('FrontRedirectController called!');
    return new RedirectResponse('/home');
  }

}
