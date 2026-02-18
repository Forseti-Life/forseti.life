<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Imports template examples from file storage into template tables.
 */
class TemplateImportService {

  /**
   * Database connection.
   */
  protected Connection $database;

  /**
   * Module extension list.
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * Logger channel.
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, ModuleExtensionList $module_extension_list, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->moduleExtensionList = $module_extension_list;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Imports templates from module example files.
   */
  public function importTemplates(): array {
    $summary = [
      'table_rows_processed' => 0,
      'table_rows_inserted' => 0,
      'table_rows_updated' => 0,
      'table_rows_skipped' => 0,
      'tables_processed' => [],
      'errors' => [],
      'missing_template_pairs' => [],
    ];

    $templates_root = $this->getTemplatesRootPath();
    if (!is_dir($templates_root)) {
      $summary['errors'][] = 'Templates directory not found: ' . $templates_root;
      $summary['missing_template_pairs'] = $this->getMissingTemplatePairs();
      return $summary;
    }

    $table_directories = array_values(array_filter(scandir($templates_root) ?: [], function (string $name) use ($templates_root): bool {
      return $name !== '.' && $name !== '..' && is_dir($templates_root . '/' . $name);
    }));

    foreach ($table_directories as $table_name) {
      $table_path = $templates_root . '/' . $table_name;
      $table_result = $this->importTableDirectory($table_name, $table_path);

      $summary['tables_processed'][$table_name] = $table_result;
      $summary['table_rows_processed'] += $table_result['processed'];
      $summary['table_rows_inserted'] += $table_result['inserted'];
      $summary['table_rows_updated'] += $table_result['updated'];
      $summary['table_rows_skipped'] += $table_result['skipped'];
      $summary['errors'] = array_merge($summary['errors'], $table_result['errors']);
    }

    $summary['missing_template_pairs'] = $this->getMissingTemplatePairs();

    return $summary;
  }

  /**
   * Returns module template examples root.
   */
  public function getTemplatesRootPath(): string {
    return $this->moduleExtensionList->getPath('dungeoncrawler_content') . '/config/examples/templates';
  }

  /**
   * Gets campaign tables that do not have matching template tables.
   */
  public function getMissingTemplatePairs(): array {
    $rows = $this->database->query(
      'SELECT TABLE_NAME
       FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = DATABASE()
         AND (TABLE_NAME = :campaigns OR TABLE_NAME LIKE :pattern)',
      [
        ':campaigns' => 'dc_campaigns',
        ':pattern' => 'dc\\_campaign\\_%',
      ],
    )->fetchCol();

    $campaign_tables = array_values(array_filter(array_map('strval', $rows), static fn(string $name): bool => $name !== ''));
    sort($campaign_tables);

    $missing = [];
    foreach ($campaign_tables as $campaign_table) {
      $expected_template = $this->getExpectedTemplateTable($campaign_table);
      if ($expected_template === '') {
        continue;
      }

      $exists = $this->database->query(
        'SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
        [':table' => $expected_template],
      )->fetchField();

      if (!$exists) {
        $missing[] = [
          'campaign_table' => $campaign_table,
          'expected_template_table' => $expected_template,
        ];
      }
    }

    return $missing;
  }

  /**
   * Gets expected template table name for a campaign table.
   */
  protected function getExpectedTemplateTable(string $campaign_table): string {
    $explicit_mappings = [
      'dc_campaigns' => 'dungeoncrawler_content_campaigns',
      'dc_campaign_content_registry' => 'dungeoncrawler_content_registry',
      'dc_campaign_loot_tables' => 'dungeoncrawler_content_loot_tables',
      'dc_campaign_encounter_templates' => 'dungeoncrawler_content_encounter_templates',
    ];

    if (isset($explicit_mappings[$campaign_table])) {
      return $explicit_mappings[$campaign_table];
    }

    if (str_starts_with($campaign_table, 'dc_campaign_')) {
      $suffix = substr($campaign_table, strlen('dc_campaign_'));
      if ($suffix !== FALSE && $suffix !== '') {
        return 'dungeoncrawler_content_' . $suffix;
      }
    }

    return '';
  }

  /**
   * Imports all rows from one table directory.
   */
  protected function importTableDirectory(string $table_name, string $table_path): array {
    $result = [
      'processed' => 0,
      'inserted' => 0,
      'updated' => 0,
      'skipped' => 0,
      'errors' => [],
    ];

    if (!$this->database->schema()->tableExists($table_name)) {
      $result['errors'][] = sprintf('Table %s does not exist. Skipped directory %s.', $table_name, $table_name);
      return $result;
    }

    $columns = $this->getTableColumns($table_name);
    $merge_keys = $this->getMergeKeys($table_name);
    $json_files = $this->scanJsonFiles($table_path);

    foreach ($json_files as $json_file) {
      $rows = $this->extractRows($json_file);
      if (empty($rows)) {
        continue;
      }

      foreach ($rows as $row) {
        $result['processed']++;
        if (!is_array($row)) {
          $result['skipped']++;
          $result['errors'][] = sprintf('Skipping non-object row in %s.', $this->relativePath($json_file));
          continue;
        }

        $normalized = $this->normalizeRow($row, $columns, $json_file);
        if (empty($normalized)) {
          $result['skipped']++;
          $result['errors'][] = sprintf('Skipping row with no matching columns for table %s in %s.', $table_name, $this->relativePath($json_file));
          continue;
        }

        $keys = [];
        foreach ($merge_keys as $key_column) {
          if (!array_key_exists($key_column, $normalized)) {
            $keys = [];
            break;
          }
          $keys[$key_column] = $normalized[$key_column];
        }

        if (empty($keys)) {
          $result['skipped']++;
          $result['errors'][] = sprintf('Skipping row missing merge keys (%s) for table %s in %s.', implode(', ', $merge_keys), $table_name, $this->relativePath($json_file));
          continue;
        }

        $was_existing = $this->rowExists($table_name, $keys);

        try {
          $this->database->merge($table_name)
            ->keys($keys)
            ->fields($normalized)
            ->execute();

          if ($was_existing) {
            $result['updated']++;
          }
          else {
            $result['inserted']++;
          }
        }
        catch (\Throwable $throwable) {
          $result['skipped']++;
          $result['errors'][] = sprintf('Failed importing row into %s from %s: %s', $table_name, $this->relativePath($json_file), $throwable->getMessage());
          $this->logger->error('Failed importing row into @table from @file: @message', [
            '@table' => $table_name,
            '@file' => $json_file,
            '@message' => $throwable->getMessage(),
          ]);
        }
      }
    }

    return $result;
  }

  /**
   * Extracts import rows from a JSON file.
   */
  protected function extractRows(string $json_file): array {
    $contents = file_get_contents($json_file);
    if ($contents === FALSE) {
      return [];
    }

    $data = json_decode($contents, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return [];
    }

    if (is_array($data) && isset($data['rows']) && is_array($data['rows'])) {
      return $data['rows'];
    }

    if ($this->isList($data)) {
      return $data;
    }

    return is_array($data) ? [$data] : [];
  }

  /**
   * Gets table columns and metadata.
   */
  protected function getTableColumns(string $table_name): array {
    $query = $this->database->query(
      'SELECT COLUMN_NAME, DATA_TYPE
       FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
      [':table' => $table_name],
    );

    $columns = [];
    foreach ($query->fetchAll() as $row) {
      $columns[(string) $row->COLUMN_NAME] = [
        'data_type' => (string) $row->DATA_TYPE,
      ];
    }

    return $columns;
  }

  /**
   * Selects merge keys for a table, preferring non-primary unique indexes.
   */
  protected function getMergeKeys(string $table_name): array {
    $query = $this->database->query(
      'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
       FROM information_schema.STATISTICS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
       ORDER BY INDEX_NAME, SEQ_IN_INDEX',
      [':table' => $table_name],
    );

    $indexes = [];
    foreach ($query->fetchAll() as $row) {
      if ((int) $row->NON_UNIQUE !== 0) {
        continue;
      }
      $index_name = (string) $row->INDEX_NAME;
      $indexes[$index_name][] = (string) $row->COLUMN_NAME;
    }

    foreach ($indexes as $index_name => $columns) {
      if ($index_name !== 'PRIMARY') {
        return $columns;
      }
    }

    return $indexes['PRIMARY'] ?? [];
  }

  /**
   * Converts and filters row values to match table columns.
   */
  protected function normalizeRow(array $row, array $columns, string $json_file): array {
    $normalized = [];
    foreach ($row as $column => $value) {
      if (!isset($columns[$column])) {
        continue;
      }

      $data_type = $columns[$column]['data_type'];
      if (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      }
      elseif (is_bool($value)) {
        $value = in_array($data_type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'], TRUE) ? (int) $value : ($value ? '1' : '0');
      }

      $normalized[$column] = $value;
    }

    $timestamp = time();
    if (isset($columns['created']) && !isset($normalized['created'])) {
      $normalized['created'] = $timestamp;
    }
    if (isset($columns['updated']) && !isset($normalized['updated'])) {
      $normalized['updated'] = $timestamp;
    }
    if (isset($columns['source_file']) && !isset($normalized['source_file'])) {
      $normalized['source_file'] = $this->relativePath($json_file);
    }

    return $normalized;
  }

  /**
   * Checks whether a row exists for merge keys.
   */
  protected function rowExists(string $table_name, array $keys): bool {
    $query = $this->database->select($table_name, 't');
    $query->addExpression('1');
    foreach ($keys as $column => $value) {
      $query->condition($column, $value);
    }
    $query->range(0, 1);

    return (bool) $query->execute()->fetchField();
  }

  /**
   * Returns all JSON files in a directory recursively.
   */
  protected function scanJsonFiles(string $directory): array {
    $files = [];
    if (!is_dir($directory)) {
      return $files;
    }

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
      if ($file->isFile() && strtolower($file->getExtension()) === 'json') {
        $files[] = $file->getPathname();
      }
    }

    sort($files);
    return $files;
  }

  /**
   * Returns path relative to module root.
   */
  protected function relativePath(string $absolute_path): string {
    $module_path = $this->moduleExtensionList->getPath('dungeoncrawler_content');
    return ltrim(str_replace($module_path, '', $absolute_path), '/');
  }

  /**
   * Determines whether an array is list-like.
   */
  protected function isList(mixed $value): bool {
    if (!is_array($value)) {
      return FALSE;
    }
    if ($value === []) {
      return TRUE;
    }
    return array_keys($value) === range(0, count($value) - 1);
  }

}
