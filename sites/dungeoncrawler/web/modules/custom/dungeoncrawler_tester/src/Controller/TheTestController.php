<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Simple toggle page for automation validation.
 */
class TheTestController extends ControllerBase {

  /**
   * Hardcoded status; flip to 'pass' in code to satisfy the functional test.
   * The intent is to require a code edit (not UI input) to change the outcome.
   */
  private const STATUS = 'fail';

  /**
   * Render the /thetest page.
   */
  public function page(): array {
    $text = self::STATUS === 'fail' ? $this->t('TEST:FAIL') : $this->t('TEST:PASS');

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['thetest-page']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $text,
        '#attributes' => ['class' => ['thetest-heading']],
      ],
      'desc' => [
        '#markup' => '<p>' . $this->t('Toggle this page to force the automation check to pass or fail.') . '</p>',
      ],
    ];

    return $build;
  }

}
