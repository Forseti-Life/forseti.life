<?php

namespace Drupal\forseti_safety_content\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to handle front page routing.
 */
class ForsetiFrontPageSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 28];
    return $events;
  }

  /**
   * Handle the request event.
   */
  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();
    
    // Only handle GET requests to the root path
    if ($request->getMethod() === 'GET' && $request->getPathInfo() === '/') {
      // Rewrite the path to our home route
      $request->attributes->set('_route', 'forseti.home');
      $request->attributes->set('_controller', '\Drupal\forseti_safety_content\Controller\ForsetiHomeController::content');
      $request->attributes->set('_title', 'Forseti - AI-Powered Community Safety');
    }
  }

}
