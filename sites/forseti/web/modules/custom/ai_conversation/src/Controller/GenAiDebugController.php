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
    $days = $request->query->get('days', 1);

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
    
    // Apply time filter (days back from now)
    if ($days > 0) {
      $timestamp_cutoff = \Drupal::time()->getRequestTime() - ($days * 86400);
      $query->condition('timestamp', $timestamp_cutoff, '>=');
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

    // Calculate cost totals for filtered period
    // Debug: Verify database object type
    if (!($this->database instanceof Connection)) {
      \Drupal::logger('ai_conversation')->error('Database is not Connection instance: @type', ['@type' => gettype($this->database)]);
      throw new \Exception('Database connection invalid: ' . gettype($this->database));
    }
    
    $filtered_cost_query = $this->database->select('ai_conversation_api_usage', 'u');
    if (!is_object($filtered_cost_query)) {
      \Drupal::logger('ai_conversation')->error('Select query did not return object: @type', ['@type' => gettype($filtered_cost_query)]);
      throw new \Exception('Select query failed: ' . gettype($filtered_cost_query));
    }
    
    $filtered_cost_query
      ->addExpression('SUM(estimated_cost)', 'total_cost')
      ->addExpression('COUNT(*)', 'total_calls');
    
    if (!empty($module)) {
      $filtered_cost_query->condition('module', $module);
    }
    if (!empty($operation)) {
      $filtered_cost_query->condition('operation', $operation);
    }
    if ($success !== NULL && $success !== '') {
      $filtered_cost_query->condition('success', (int) $success);
    }
    if ($days > 0) {
      $timestamp_cutoff = \Drupal::time()->getRequestTime() - ($days * 86400);
      $filtered_cost_query->condition('timestamp', $timestamp_cutoff, '>=');
    }
    
    $filtered_totals = $filtered_cost_query->execute()->fetchObject();
    $filtered_total_cost = $filtered_totals->total_cost ?? 0;
    $filtered_total_calls = $filtered_totals->total_calls ?? 0;

    // Calculate all-time cost totals (no time filter)
    $alltime_cost_query = $this->database->select('ai_conversation_api_usage', 'u')
      ->addExpression('SUM(estimated_cost)', 'total_cost')
      ->addExpression('COUNT(*)', 'total_calls');
    
    if (!empty($module)) {
      $alltime_cost_query->condition('module', $module);
    }
    if (!empty($operation)) {
      $alltime_cost_query->condition('operation', $operation);
    }
    if ($success !== NULL && $success !== '') {
      $alltime_cost_query->condition('success', (int) $success);
    }
    
    $alltime_totals = $alltime_cost_query->execute()->fetchObject();
    $alltime_total_cost = $alltime_totals->total_cost ?? 0;
    $alltime_total_calls = $alltime_totals->total_calls ?? 0;

    return [
      '#theme' => 'genai_debug_list',
      '#calls' => $calls,
      '#modules' => $modules,
      '#operations' => $operations,
      '#current_module' => $module,
      '#current_operation' => $operation,
      '#current_success' => $success,
      '#current_limit' => $limit,
      '#current_days' => $days,
      '#filtered_total_cost' => $filtered_total_cost,
      '#filtered_total_calls' => $filtered_total_calls,
      '#alltime_total_cost' => $alltime_total_cost,
      '#alltime_total_calls' => $alltime_total_calls,
    ];
  }

  /**
   * Delete a GenAI API call record.
   */
  public function deleteCall($id) {
    try {
      // Delete from database
      $deleted = $this->database->delete('ai_conversation_api_usage')
        ->condition('id', $id)
        ->execute();
      
      if ($deleted) {
        \Drupal::messenger()->addStatus($this->t('GenAI call #@id deleted successfully.', ['@id' => $id]));
      } else {
        \Drupal::messenger()->addWarning($this->t('GenAI call #@id not found.', ['@id' => $id]));
      }
    } catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Failed to delete GenAI call: @error', ['@error' => $e->getMessage()]));
    }
    
    // Redirect back to list
    return new \Symfony\Component\HttpFoundation\RedirectResponse('/admin/reports/genai-debug');
  }

  /**
   * Delete all filtered GenAI API call records.
   */
  public function deleteAllFiltered(Request $request) {
    $module = $request->request->get('module');
    $operation = $request->request->get('operation');
    $success = $request->request->get('success');
    $days = $request->request->get('days');

    try {
      $query = $this->database->delete('ai_conversation_api_usage');
      
      // Apply same filters as list view
      if (!empty($module)) {
        $query->condition('module', $module);
      }
      if (!empty($operation)) {
        $query->condition('operation', $operation);
      }
      if ($success !== NULL && $success !== '') {
        $query->condition('success', (int) $success);
      }
      if ($days > 0) {
        $timestamp_cutoff = \Drupal::time()->getRequestTime() - ($days * 86400);
        $query->condition('timestamp', $timestamp_cutoff, '>=');
      }
      
      $deleted = $query->execute();
      
      \Drupal::messenger()->addStatus($this->t('Deleted @count GenAI call records.', ['@count' => $deleted]));
    } catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Failed to delete GenAI calls: @error', ['@error' => $e->getMessage()]));
    }
    
    // Redirect back to list with same filters
    $params = [];
    if (!empty($module)) $params['module'] = $module;
    if (!empty($operation)) $params['operation'] = $operation;
    if ($success !== NULL && $success !== '') $params['success'] = $success;
    if ($days) $params['days'] = $days;
    
    $url = '/admin/reports/genai-debug';
    if (!empty($params)) {
      $url .= '?' . http_build_query($params);
    }
    
    return new \Symfony\Component\HttpFoundation\RedirectResponse($url);
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
