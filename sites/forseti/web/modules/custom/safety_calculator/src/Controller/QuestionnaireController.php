<?php

namespace Drupal\safety_calculator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for Safety Assessment Questionnaire.
 */
class QuestionnaireController extends ControllerBase {

  /**
   * Landing page for questionnaire.
   */
  public function landing() {
    $build = [];

    // Hero Section
    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bg-primary', 'text-white', 'py-5', 'mb-5']],
      'inner' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['container', 'text-center']],
        'title' => [
          '#markup' => '<h1 class="display-4 fw-bold mb-3">' . $this->t('Personal Safety Assessment') . '</h1>',
        ],
        'subtitle' => [
          '#markup' => '<p class="lead mb-4">' . $this->t('Get a comprehensive safety score based on the seven dimensions of community well-being') . '</p>',
        ],
        'cta' => [
          '#type' => 'link',
          '#title' => $this->t('Start Assessment'),
          '#url' => Url::fromRoute('safety_calculator.questionnaire_step', ['step' => 'safe']),
          '#attributes' => ['class' => ['btn', 'btn-light', 'btn-lg']],
        ],
      ],
    ];

    // 7 Dimensions Overview
    $build['dimensions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'my-5']],
      'title' => [
        '#markup' => '<h2 class="text-center mb-5">' . $this->t('The Seven Dimensions of Safety') . '</h2>',
      ],
      'grid' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'row-cols-1', 'row-cols-md-2', 'row-cols-lg-3', 'g-4']],
      ],
    ];

    $dimensions = $this->getDimensions();
    foreach ($dimensions as $dimension) {
      $build['dimensions']['grid'][$dimension['id']] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col']],
        'card' => [
          '#markup' => sprintf(
            '<div class="card card-forseti dimension-card h-100">
              <div class="d-flex align-items-start mb-2">
                <img src="%s" alt="%s" class="me-2">
                <div class="flex-grow-1">
                  <h6 class="text-cyan mb-0">%s</h6>
                  <small class="text-muted-light d-block">%s</small>
                </div>
              </div>
              <ul class="small text-muted mb-0">
                <li>%d questions</li>
                <li>~%d min</li>
              </ul>
            </div>',
            $dimension['icon'],
            $dimension['name'],
            $dimension['name'],
            $dimension['subtitle'],
            $dimension['question_count'],
            $dimension['estimated_minutes']
          ),
        ],
      ];
    }

    // How It Works
    $build['how_it_works'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'my-5', 'py-5', 'bg-light', 'rounded']],
      'title' => [
        '#markup' => '<h2 class="text-center mb-4">' . $this->t('How It Works') . '</h2>',
      ],
      'steps' => [
        '#markup' => '
          <div class="row text-center g-4">
            <div class="col-md-3">
              <div class="display-4 text-primary mb-3">1</div>
              <h5>' . $this->t('Answer Questions') . '</h5>
              <p class="text-muted">' . $this->t('Answer questions about each dimension at your own pace') . '</p>
            </div>
            <div class="col-md-3">
              <div class="display-4 text-primary mb-3">2</div>
              <h5>' . $this->t('Rate Your Experience') . '</h5>
              <p class="text-muted">' . $this->t('Rate factors from 0-100 based on your perception') . '</p>
            </div>
            <div class="col-md-3">
              <div class="display-4 text-primary mb-3">3</div>
              <h5>' . $this->t('Review & Submit') . '</h5>
              <p class="text-muted">' . $this->t('Review your assessment and submit for comprehensive analysis') . '</p>
            </div>
            <div class="col-md-3">
              <div class="display-4 text-primary mb-3">4</div>
              <h5>' . $this->t('Get Your Score') . '</h5>
              <p class="text-muted">' . $this->t('Receive a comprehensive safety score and insights') . '</p>
            </div>
          </div>',
      ],
    ];

    // CTA
    $build['cta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'text-center', 'my-5', 'py-5']],
      'content' => [
        '#markup' => '
          <h2 class="mb-4">' . $this->t('Ready to Begin?') . '</h2>
          <p class="lead mb-4">' . $this->t('Total: 210 questions across 7 dimensions • Approximately 45-60 minutes') . '</p>
          <a href="' . Url::fromRoute('safety_calculator.questionnaire_step', ['step' => 'safe'])->toString() . '" class="btn btn-primary btn-lg">' . $this->t('Start Assessment') . '</a>
          <div class="mt-3">
            <small class="text-muted">' . $this->t('Philadelphia baseline data is pre-loaded for reference') . '</small>
          </div>',
      ],
    ];

    return $build;
  }

  /**
   * Get the 7 safety dimensions.
   */
  protected function getDimensions() {
    return [
      [
        'id' => 'safe',
        'name' => $this->t('Safe'),
        'subtitle' => $this->t('Security & Protection'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_safe.png',
        'question_count' => 30,
        'estimated_minutes' => 8,
      ],
      [
        'id' => 'energized',
        'name' => $this->t('Energized'),
        'subtitle' => $this->t('Vitality & Basic Needs'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_energized.png',
        'question_count' => 30,
        'estimated_minutes' => 8,
      ],
      [
        'id' => 'connected',
        'name' => $this->t('Connected'),
        'subtitle' => $this->t('Community & Belonging'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_connected.png',
        'question_count' => 30,
        'estimated_minutes' => 8,
      ],
      [
        'id' => 'free',
        'name' => $this->t('Free'),
        'subtitle' => $this->t('Autonomy & Rights'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_free.png',
        'question_count' => 30,
        'estimated_minutes' => 8,
      ],
      [
        'id' => 'capable',
        'name' => $this->t('Capable'),
        'subtitle' => $this->t('Mastery & Development'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_capable.png',
        'question_count' => 30,
        'estimated_minutes' => 8,
      ],
      [
        'id' => 'useful',
        'name' => $this->t('Useful'),
        'subtitle' => $this->t('Purpose & Contribution'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_useful.png',
        'question_count' => 30,
        'estimated_minutes' => 8,
      ],
      [
        'id' => 'whole',
        'name' => $this->t('Whole'),
        'subtitle' => $this->t('Holistic Health & Identity'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_whole.png',
        'question_count' => 30,
        'estimated_minutes' => 8,
      ],
    ];
  }

}
