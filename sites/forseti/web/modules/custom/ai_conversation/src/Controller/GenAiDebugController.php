<?php

namespace Drupal\ai_conversation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for GenAI debugging - inspect request/response data.
 */
class GenAiDebugController extends ControllerBase {

  /**
   * The table name for AI conversation API usage tracking.
   */
  const TABLE_NAME = 'ai_conversation_api_usage';

  /**
   * Seconds in one day.
   */
  const SECONDS_PER_DAY = 86400;

  /**
   * Maximum number of records to display.
   */
  const MAX_LIMIT = 1000;

  /**
   * Default number of records to display.
   */
  const DEFAULT_LIMIT = 100;

  /**
   * Date format for timestamps.
   */
  const DATE_FORMAT_FULL = 'M d, Y H:i:s';

  /**
   * Date format for relative timestamps.
   */
  const DATE_FORMAT_SHORT = 'D M j, H:i:s';

  /**
   * Cost precision for list view (4 decimal places).
   */
  const COST_PRECISION_LIST = 4;

  /**
   * Cost precision for detail view (6 decimal places).
   */
  const COST_PRECISION_DETAIL = 6;

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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a GenAiDebugController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(Connection $database, DateFormatterInterface $date_formatter, MessengerInterface $messenger, TimeInterface $time) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
    $this->messenger = $messenger;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('messenger'),
      $container->get('datetime.time')
    );
  }

  /**
   * Lists recent GenAI API calls with filtering options.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   Render array for the list view.
   */
  public function debugList(Request $request): array {
    // Extract filter parameters using helper
    [$module, $operation, $success, $days] = $this->extractFilterParams($request->query);
    
    // Validate and sanitize limit
    $limit = min((int) $request->query->get('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);

    // Build query
    $query = $this->database->select(self::TABLE_NAME, 'u');
    $query->fields('u', [
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
      'stop_reason',
      'context_data',
    ]);
    $query->orderBy('timestamp', 'DESC');
    $query->range(0, $limit);

    // Apply filters using helper
    $this->applyFilters($query, $module, $operation, $success, $days);

    $results = $query->execute()->fetchAll();

    // Format results using helper
    $calls = array_map([$this, 'formatCallRow'], $results);

    // Get unique modules and operations for filters
    $modules = $this->getUniqueValues('module');
    $operations = $this->getUniqueValues('operation');

    // Calculate cost totals for filtered period
    $filtered_totals = $this->getCostTotals($module, $operation, $success, $days);
    
    // Calculate all-time cost totals (no time filter)
    $alltime_totals = $this->getCostTotals($module, $operation, $success, 0);

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
      '#filtered_total_cost' => $filtered_totals['total_cost'],
      '#filtered_total_calls' => $filtered_totals['total_calls'],
      '#alltime_total_cost' => $alltime_totals['total_cost'],
      '#alltime_total_calls' => $alltime_totals['total_calls'],
    ];
  }

  /**
   * Delete a GenAI API call record.
   *
   * @param int $id
   *   The ID of the record to delete.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect back to debug list.
   */
  public function deleteCall(int $id): RedirectResponse {
    try {
      $deleted = $this->database->delete(self::TABLE_NAME)
        ->condition('id', $id)
        ->execute();
      
      if ($deleted) {
        $this->messenger->addStatus($this->t('GenAI call #@id deleted successfully.', ['@id' => $id]));
      }
      else {
        $this->messenger->addWarning($this->t('GenAI call #@id not found.', ['@id' => $id]));
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Failed to delete GenAI call: @error', ['@error' => $e->getMessage()]));
    }
    
    return new RedirectResponse(Url::fromRoute('ai_conversation.genai_debug_list')->toString());
  }

  /**
   * Delete all filtered GenAI API call records.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect back to debug list with filters.
   */
  public function deleteAllFiltered(Request $request): RedirectResponse {
    // Extract filter parameters using helper
    [$module, $operation, $success, $days] = $this->extractFilterParams($request->request);

    try {
      $query = $this->database->delete(self::TABLE_NAME);
      
      // Apply same filters as list view using helper
      $this->applyFilters($query, $module, $operation, $success, $days);
      
      $deleted = $query->execute();
      
      $this->messenger->addStatus($this->t('Deleted @count GenAI call records.', ['@count' => $deleted]));
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Failed to delete GenAI calls: @error', ['@error' => $e->getMessage()]));
    }
    
    // Build redirect URL with same filters
    $params = $this->buildFilterParams($module, $operation, $success, $days);
    $url = Url::fromRoute('ai_conversation.genai_debug_list', [], ['query' => $params]);
    
    return new RedirectResponse($url->toString());
  }

  /**
   * Shows detailed view of a specific GenAI API call.
   *
   * @param int $id
   *   The ID of the API call to display.
   *
   * @return array
   *   Render array for the detail view.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the API call record is not found.
   */
  public function debugDetail(int $id): array {
    $query = $this->database->select(self::TABLE_NAME, 'u');
    $query->fields('u');
    $query->condition('id', $id);
    $query->range(0, 1);
    
    $row = $query->execute()->fetchObject();

    if (!$row) {
      throw new NotFoundHttpException('GenAI API call not found.');
    }

    // Extract context data using helper
    $context_info = $this->extractContextData($row->context_data ?? NULL);

    $call_data = [
      'id' => $row->id,
      'timestamp' => $this->dateFormatter->format($row->timestamp, 'custom', self::DATE_FORMAT_FULL),
      'uid' => $row->uid,
      'module' => $row->module,
      'operation' => $row->operation,
      'model_id' => $row->model_id,
      'input_tokens' => number_format($row->input_tokens),
      'output_tokens' => number_format($row->output_tokens),
      'total_tokens' => $this->calculateTotalTokens($row->input_tokens, $row->output_tokens),
      'cost' => $this->formatCost($row->estimated_cost, self::COST_PRECISION_DETAIL),
      'duration_ms' => number_format($row->duration_ms) . 'ms',
      'stop_reason' => $row->stop_reason,
      'context_data' => $context_info['data'],
      'max_tokens_used' => $context_info['max_tokens'],
      'model_id_used' => $context_info['model_id'] ?? $row->model_id,
    ];

    return [
      '#theme' => 'genai_debug_detail',
      '#call_data' => $call_data,
    ];
  }

  /**
   * Check if a string is valid JSON.
   *
   * @param mixed $string
   *   The value to check.
   *
   * @return bool
   *   TRUE if valid JSON, FALSE otherwise.
   */
  protected function isJson($string): bool {
    if (!is_string($string)) {
      return FALSE;
    }
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
  }

  /**
   * Format a database row into display data for the list view.
   *
   * @param object $row
   *   Database row object.
   *
   * @return array
   *   Formatted data array.
   */
  protected function formatCallRow(object $row): array {
    return [
      'id' => $row->id,
      'timestamp' => $this->dateFormatter->format($row->timestamp, 'custom', self::DATE_FORMAT_FULL),
      'timestamp_ago' => $this->dateFormatter->format($row->timestamp, 'custom', self::DATE_FORMAT_SHORT),
      'uid' => $row->uid,
      'module' => $row->module,
      'operation' => $row->operation,
      'model_id' => $row->model_id,
      'input_tokens' => number_format($row->input_tokens),
      'output_tokens' => number_format($row->output_tokens),
      'total_tokens' => $this->calculateTotalTokens($row->input_tokens, $row->output_tokens),
      'cost' => $this->formatCost($row->estimated_cost, self::COST_PRECISION_LIST),
      'duration_ms' => number_format($row->duration_ms) . 'ms',
      'stop_reason' => $row->stop_reason,
    ];
  }

  /**
   * Format a JSON response string with pretty printing.
   *
   * @param string $response
   *   The response string to format.
   *
   * @return string
   *   Formatted response (pretty JSON if applicable, original otherwise).
   */
  protected function formatJsonResponse(string $response): string {
    if ($this->isJson($response)) {
      $decoded = json_decode($response, TRUE);
      return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return $response;
  }

  /**
   * Build filter parameters array for URL generation.
   *
   * @param string|null $module
   *   Module filter.
   * @param string|null $operation
   *   Operation filter.
   * @param string|null $success
   *   Success filter.
   * @param int|null $days
   *   Days filter.
   *
   * @return array
   *   Parameters array for URL query string.
   */
  protected function buildFilterParams(?string $module, ?string $operation, ?string $success, ?int $days): array {
    $params = [];
    if (!empty($module)) {
      $params['module'] = $module;
    }
    if (!empty($operation)) {
      $params['operation'] = $operation;
    }
    if ($success !== NULL && $success !== '') {
      $params['success'] = $success;
    }
    if ($days) {
      $params['days'] = $days;
    }
    return $params;
  }

  /**
   * Apply common filters to a query.
   * 
   * @param \Drupal\Core\Database\Query\SelectInterface|\Drupal\Core\Database\Query\Delete $query
   *   The query to apply filters to.
   * @param string|null $module
   *   Module filter.
   * @param string|null $operation
   *   Operation filter.
   * @param string|null $success
   *   Success filter (0 or 1).
   * @param int $days
   *   Number of days to filter (0 for no time filter).
   */
  protected function applyFilters($query, $module, $operation, $success, $days) {
    if (!empty($module)) {
      $query->condition('module', $module);
    }
    if (!empty($operation)) {
      $query->condition('operation', $operation);
    }
    // Note: success field does not exist in current schema
    if ($days > 0) {
      $timestamp_cutoff = $this->time->getRequestTime() - ($days * self::SECONDS_PER_DAY);
      $query->condition('timestamp', $timestamp_cutoff, '>=');
    }
  }

  /**
   * Get unique values for a specific field.
   * 
   * @param string $field
   *   The field name to get unique values for.
   * 
   * @return array
   *   Array of unique values.
   */
  protected function getUniqueValues(string $field): array {
    $query = $this->database->select(self::TABLE_NAME, 'u');
    $query->distinct();
    $query->fields('u', [$field]);
    $query->orderBy($field);
    
    $results = $query->execute()->fetchAll();
    return array_column($results, $field);
  }

  /**
   * Calculate cost totals with optional filters.
   * 
   * @param string|null $module
   *   Module filter.
   * @param string|null $operation
   *   Operation filter.
   * @param string|null $success
   *   Success filter (0 or 1).
   * @param int $days
   *   Number of days to filter (0 for no time filter).
   * 
   * @return array
   *   Array with 'total_cost' and 'total_calls' keys.
   */
  protected function getCostTotals(?string $module, ?string $operation, ?string $success, int $days): array {
    $query = $this->database->select(self::TABLE_NAME, 'u');
    $query->addExpression('SUM(estimated_cost)', 'total_cost');
    $query->addExpression('COUNT(*)', 'total_calls');
    
    // Apply filters using helper
    $this->applyFilters($query, $module, $operation, $success, $days);
    
    $result = $query->execute()->fetchObject();
    
    return [
      'total_cost' => $result->total_cost ?? 0,
      'total_calls' => $result->total_calls ?? 0,
    ];
  }

  /**
   * Extract filter parameters from a parameter bag.
   *
   * @param \Symfony\Component\HttpFoundation\ParameterBag $params
   *   The parameter bag (query or request).
   *
   * @return array
   *   Array containing [module, operation, success, days].
   */
  protected function extractFilterParams($params): array {
    return [
      $params->get('module'),
      $params->get('operation'),
      $params->get('success'),
      (int) $params->get('days', 1),
    ];
  }

  /**
   * Extract context data from JSON string.
   *
   * @param string|null $context_json
   *   The JSON-encoded context data.
   *
   * @return array
   *   Array with 'data', 'max_tokens', and 'model_id' keys.
   */
  protected function extractContextData(?string $context_json): array {
    $data = NULL;
    $max_tokens = NULL;
    $model_id = NULL;

    if (!empty($context_json)) {
      $data = json_decode($context_json, TRUE);
      $max_tokens = $data['max_tokens'] ?? NULL;
      $model_id = $data['model_id'] ?? NULL;
    }

    return [
      'data' => $data,
      'max_tokens' => $max_tokens,
      'model_id' => $model_id,
    ];
  }

  /**
   * Calculate total tokens (input + output).
   *
   * @param int $input_tokens
   *   Number of input tokens.
   * @param int $output_tokens
   *   Number of output tokens.
   *
   * @return string
   *   Formatted total tokens with thousand separators.
   */
  protected function calculateTotalTokens(int $input_tokens, int $output_tokens): string {
    return number_format($input_tokens + $output_tokens);
  }

  /**
   * Format cost with proper precision and currency symbol.
   *
   * @param float $cost
   *   The cost value.
   * @param int $precision
   *   Number of decimal places.
   *
   * @return string
   *   Formatted cost string with $ symbol.
   */
  protected function formatCost(float $cost, int $precision): string {
    return '$' . number_format($cost, $precision);
  }

}
