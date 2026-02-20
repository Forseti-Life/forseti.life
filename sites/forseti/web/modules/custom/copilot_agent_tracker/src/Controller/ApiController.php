<?php

namespace Drupal\copilot_agent_tracker\Controller;

use Drupal\copilot_agent_tracker\Service\AgentTrackerStorage;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Internal API endpoint for posting agent telemetry.
 */
final class ApiController extends ControllerBase {

  public function __construct(
    private readonly AgentTrackerStorage $storage,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('copilot_agent_tracker.storage'),
    );
  }

  /**
   * POST /api/copilot-agent-tracker/event
   */
  public function event(Request $request): JsonResponse {
    $content = (string) $request->getContent();
    if ($content === '') {
      throw new BadRequestHttpException('Missing JSON payload.');
    }

    $payload = json_decode($content, TRUE);
    if (!is_array($payload)) {
      throw new BadRequestHttpException('Invalid JSON payload.');
    }

    // Minimal required fields; do not accept raw chat logs.
    $summary = (string) ($payload['summary'] ?? '');
    if ($summary === '') {
      throw new BadRequestHttpException('summary is required.');
    }

    $sanitized = [
      'agent_id' => $payload['agent_id'] ?? NULL,
      'session_id' => $payload['session_id'] ?? NULL,
      'work_item_id' => $payload['work_item_id'] ?? NULL,
      'role' => $payload['role'] ?? NULL,
      'website' => $payload['website'] ?? NULL,
      'module' => $payload['module'] ?? NULL,
      'action' => $payload['action'] ?? NULL,
      'status' => $payload['status'] ?? NULL,
      'summary' => $summary,
      'details' => $payload['details'] ?? NULL,
      'metadata' => isset($payload['metadata']) ? json_encode($payload['metadata']) : NULL,
    ];

    $event_id = $this->storage->recordEvent($sanitized);
    return new JsonResponse([
      'ok' => TRUE,
      'event_id' => $event_id,
    ]);
  }

}

