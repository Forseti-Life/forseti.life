<?php

namespace Drupal\ai_conversation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for GenAI debugging - inspect request/response data.
 */
class GenAiDebugController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a GenAiDebugController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(Connection $database, DateFormatterInterface $date_formatter) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * Lists recent GenAI API calls with filtering options.
   */
  public function debugList(Request $request) {
    // Get filter parameters
    $module = $request->query->get('module');
    $operation = $request->query->get('operation');
    $success = $request->query->get('success');
    $limit = $request->query->get('limit', 100);

    // Build query
    $query = $this->database->select('ai_conversation_api_usage', 'u')
      ->fields('u', [
        'id',
        'timestamp',
        'uid',
        'module',
        'operation',
        'model_id',
        'input_tokens',
        'output_tokens',
        'estimated_cost',
        'duration_ms',
        'success',
        'error_message',
        'stop_reason',
      ])
      ->orderBy('timestamp', 'DESC')
      ->range(0, $limit);

    // Apply filters
    if (!empty($module)) {
      $query->condition('module', $module);
    }
    if (!empty($operation)) {
      $query->condition('operation', $operation);
    }
    if ($success !== NULL && $success !== '') {
      $query->condition('success', (int) $success);
    }

    $results = $query->execute()->fetchAll();

    // Format results
    $calls = [];
    foreach ($results as $row) {
      $calls[] = [
        'id' => $row->id,
        'timestamp' => $this->dateFormatter->format($row->timestamp, 'custom', 'M d, Y H:i:s'),
        'timestamp_ago' => $this->dateFormatter->format($row->timestamp, 'custom', 'D M j, H:i:s'),
        'uid' => $row->uid,
        'module' => $row->module,
        'operation' => $row->operation,
        'model_id' => $row->model_id,
        'input_tokens' => number_format($row->input_tokens),
        'output_tokens' => number_format($row->output_tokens),
        'total_tokens' => number_format($row->input_tokens + $row->output_tokens),
        'cost' => '$' . number_format($row->estimated_cost, 4),
        'duration_ms' => number_format($row->duration_ms) . 'ms',
        'success' => $row->success ? '✅' : '❌',
        'success_bool' => (bool) $row->success,
        'error_message' => $row->error_message,
        'stop_reason' => $row->stop_reason,
      ];
    }

    // Get unique modules and operations for filters
    $modules_query = $this->database->select('ai_conversation_api_usage', 'u')
      ->distinct()
      ->fields('u', ['module'])
      ->orderBy('module');
    $modules = array_column($modules_query->execute()->fetchAll(), 'module');

    $operations_query = $this->database->select('ai_conversation_api_usage', 'u')
      ->distinct()
      ->fields('u', ['operation'])
      ->orderBy('operation');
    $operations = array_column($operations_query->execute()->fetchAll(), 'operation');

    return [
      '#theme' => 'genai_debug_list',
      '#calls' => $calls,
      '#modules' => $modules,
      '#operations' => $operations,
      '#current_module' => $module,
      '#current_operation' => $operation,
      '#current_success' => $success,
      '#current_limit' => $limit,
    ];
  }

  /**
   * Shows detailed view of a specific GenAI API call.
   */
  public function debugDetail($id) {
    // Get the full record
    $query = $this->database->select('ai_conversation_api_usage', 'u')
      ->fields('u')
      ->condition('id', $id)
      ->range(0, 1);
    
    $row = $query->execute()->fetchObject();

    if (!$row) {
      throw new NotFoundHttpException('GenAI API call not found.');
    }

    // Decode context_data if it exists
    $context_data = NULL;
    $max_tokens_used = NULL;
    $model_id_used = NULL;
    if (!empty($row->context_data)) {
      $context_data = json_decode($row->context_data, TRUE);
      // Extract max_tokens and model_id if available
      $max_tokens_used = $context_data['max_tokens'] ?? NULL;
      $model_id_used = $context_data['model_id'] ?? NULL;
    }

    // Pretty-print JSON responses
    $formatted_response = $row->response_preview;
    if ($this->isJson($row->response_preview)) {
      $decoded = json_decode($row->response_preview, TRUE);
      $formatted_response = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $call_data = [
      'id' => $row->id,
      'timestamp' => $this->dateFormatter->format($row->timestamp, 'custom', 'M d, Y H:i:s'),
      'uid' => $row->uid,
      'module' => $row->module,
      'operation' => $row->operation,
      'model_id' => $row->model_id,
      'input_tokens' => number_format($row->input_tokens),
      'output_tokens' => number_format($row->output_tokens),
      'total_tokens' => number_format($row->input_tokens + $row->output_tokens),
      'cost' => '$' . number_format($row->estimated_cost, 6),
      'duration_ms' => number_format($row->duration_ms) . 'ms',
      'success' => (bool) $row->success,
      'error_message' => $row->error_message,
      'stop_reason' => $row->stop_reason,
      'prompt_preview' => $row->prompt_preview,
      'response_preview' => $formatted_response,
      'context_data' => $context_data,
      'cache_hit' => $row->cache_hit ?? NULL,
      'max_tokens_used' => $max_tokens_used,
      'model_id_used' => $model_id_used ?? $row->model_id,
    ];

    return [
      '#theme' => 'genai_debug_detail',
      '#call_data' => $call_data,
    ];
  }

  /**
   * Check if a string is valid JSON.
   */
  protected function isJson($string) {
    if (!is_string($string)) {
      return FALSE;
    }
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
  }

}
