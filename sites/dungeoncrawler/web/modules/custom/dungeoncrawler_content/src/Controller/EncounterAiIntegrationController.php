<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Documentation page for encounter AI integration architecture.
 */
class EncounterAiIntegrationController extends ControllerBase {

  /**
   * Render encounter AI integration overview and current implementation status.
   */
  public function overview() {
    $phaseStatus = [
      'Phase 0 — Blueprint and route visibility' => 'Complete',
      'Phase 1 — Read-only orchestration scaffold' => 'Started',
      'Phase 2 — Controlled NPC auto-play integration' => 'Not started',
      'Phase 3 — Encounter narration integration' => 'Not started',
      'Phase 4 — Hardening and observability' => 'Not started',
    ];

    $integrationBoundaries = [
      'Server-authoritative combat flow remains canonical; AI output is recommendation-only.',
      'Encounter state and campaign ownership checks execute before provider calls.',
      'Recommendation payloads must be validated against available actions and turn state.',
      'Fallback behavior uses deterministic rules if provider fails or output is rejected.',
    ];

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['encounter-ai-integration-doc']],
      'header' => [
        '#markup' => '<h2>Encounter AI Integration Blueprint</h2><p>Design summary and implementation progress for AI-assisted encounter orchestration.</p><p>Blueprint source: AI_ENCOUNTER_INTEGRATION.md</p><p><a href="/architecture/encounter-ai-integration">Refresh this status page</a></p>',
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    $build['boundaries'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => 'Integration boundaries',
      'list' => [
        '#theme' => 'item_list',
        '#items' => $integrationBoundaries,
      ],
    ];

    $build['phases'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => 'Implementation phases',
      'table' => [
        '#type' => 'table',
        '#header' => ['Phase', 'Status'],
      ],
    ];

    foreach ($phaseStatus as $phase => $status) {
      $build['phases']['table'][] = [
        'phase' => ['#plain_text' => $phase],
        'status' => ['#plain_text' => $status],
      ];
    }

    $build['next'] = [
      '#markup' => '<p><strong>Next implementation target:</strong> add a read-only recommendation preview endpoint that builds encounter context and validates provider recommendations without mutating combat state.</p>',
    ];

    return $build;
  }

}
