<?php

namespace Drupal\forseti_safety_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\agent_evaluation\Service\AgentEvaluationService;

/**
 * Controller for batch processing entity evaluations.
 */
class BatchEvaluationController extends ControllerBase {

  /**
   * The agent evaluation service.
   *
   * @var \Drupal\agent_evaluation\Service\AgentEvaluationService
   */
  protected $evaluationService;

  /**
   * Constructs a new BatchEvaluationController.
   *
   * @param \Drupal\agent_evaluation\Service\AgentEvaluationService $evaluation_service
   *   The evaluation service.
   */
  public function __construct(AgentEvaluationService $evaluation_service) {
    $this->evaluationService = $evaluation_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('agent_evaluation.service')
    );
  }

  /**
   * Process a single entity evaluation automatically.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with evaluation result.
   */
  public function processEntity(Request $request) {
    $entity_name = $request->query->get('entity');
    
    if (empty($entity_name)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Entity name is required',
      ], 400);
    }

    // Create the evaluation
    $result = $this->evaluationService->createEvaluation($entity_name);

    if (!$result['success']) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $result['error'] ?? 'Unknown error occurred',
      ], 500);
    }

    return new JsonResponse([
      'success' => TRUE,
      'entity_name' => $entity_name,
      'entity_nid' => $result['entity_nid'],
      'conversation_nid' => $result['conversation_nid'],
      'existing' => $result['existing'] ?? FALSE,
      'message' => $result['existing'] 
        ? "Entity '{$entity_name}' already exists" 
        : "Successfully created evaluation for '{$entity_name}'",
    ]);
  }

}
