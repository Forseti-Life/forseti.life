<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Form\DungeonCrawlerTableRowEditForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for game object management and review.
 */
class GameObjectsController extends ControllerBase {

  /**
   * Maximum number of rows displayed for a selected table.
   */
  private const MAX_ROWS = 100;

  /**
   * Dungeon Crawler table descriptions.
   */
  private const TABLE_OBJECT_MAP = [
    'dc_characters' => 'Player character records and progression snapshots.',
    'dc_campaigns' => 'Campaign headers, lifecycle state, and campaign-level metadata.',
    'dc_campaign_characters' => 'Character-to-campaign assignments and active party membership.',
    'dc_campaign_rooms' => 'Generated room objects for active campaigns.',
    'dc_campaign_room_states' => 'Per-room state flags and progression data.',
    'dc_campaign_dungeons' => 'Dungeon-layer records tied to campaigns.',
    'dc_campaign_log' => 'Campaign event log entries and timeline history.',
    'dc_campaign_item_instances' => 'Item instance objects spawned in campaigns.',
    'dc_campaign_encounter_instances' => 'Encounter instance objects generated during play.',
    'dc_campaign_encounter_templates' => 'Encounter template objects available to campaigns.',
    'dc_campaign_loot_tables' => 'Loot table objects used by campaign generation.',
    'dc_campaign_content_registry' => 'Campaign content object registry and lookup records.',
    'dungeoncrawler_content_registry' => 'Global content registry objects for generator lookups.',
    'dungeoncrawler_content_loot_tables' => 'Global loot table objects used by the generator.',
    'dungeoncrawler_content_encounter_templates' => 'Global encounter template objects.',
  ];

  /**
   * Database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilderService;

  /**
   * Request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a new GameObjectsController.
   */
  public function __construct(Connection $database, FormBuilderInterface $form_builder, RequestStack $request_stack) {
    $this->database = $database;
    $this->formBuilderService = $form_builder;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('form_builder'),
      $container->get('request_stack'),
    );
  }

  /**
   * Builds the Dungeon Crawler table inventory and editor page.
   *
   * @return array
   *   Render array for the page.
   */
  public function content(): array {
    $request = $this->requestStack->getCurrentRequest();
    $table_inventory = $this->getDungeonCrawlerTableInventory();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4']],
    ];

    $build['intro_card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#markup' => '<h2 class="card-title mb-2">' . $this->t('Dungeon Crawler Data Object Manager') . '</h2>',
        ],
        'description' => [
          '#markup' => '<p class="mb-0">' . $this->t('Inventory all Dungeon Crawler tables, review stored objects, and edit table fields from one admin page.') . '</p>',
        ],
      ],
    ];

    $build['inventory_card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'heading' => [
          '#markup' => '<h3 class="h5 mb-3">' . $this->t('Table Inventory') . '</h3>',
        ],
        'table' => $this->buildInventoryTable($table_inventory),
      ],
    ];

    if (empty($table_inventory)) {
      return $build;
    }

    $table_names = array_keys($table_inventory);
    $selected_table = $request->query->get('table');
    if (!is_string($selected_table) || !isset($table_inventory[$selected_table])) {
      $selected_table = $table_names[0];
    }

    $selected_metadata = $table_inventory[$selected_table];
    $rows = $this->loadTableRows($selected_table, $selected_metadata['primary_keys']);

    $build['table_fields_card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'heading' => [
          '#markup' => '<h3 class="h5 mb-3">' . $this->t('Field Inventory: @table', ['@table' => $selected_table]) . '</h3>',
        ],
        'table' => $this->buildFieldInventoryTable($selected_table, $selected_metadata),
      ],
    ];

    $build['table_rows_card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'heading' => [
          '#markup' => '<h3 class="h5 mb-3">' . $this->t('Stored Objects: @table', ['@table' => $selected_table]) . '</h3>',
        ],
        'table' => $this->buildRowsTable($selected_table, $selected_metadata, $rows),
      ],
    ];

    $primary_key_values = $this->extractPrimaryKeyValues($request->query->all(), $selected_metadata['primary_keys']);
    $edit_requested = (string) $request->query->get('edit', '') === '1';
    if ($edit_requested && !empty($primary_key_values)) {
      $row = $this->loadTableRowByPrimaryKey($selected_table, $primary_key_values);
      if (!empty($row)) {
        $build['row_editor_card'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['card', 'card-dungeoncrawler']],
          'body' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card-body']],
            'heading' => [
              '#markup' => '<h3 class="h5 mb-3">' . $this->t('Edit Row: @table', ['@table' => $selected_table]) . '</h3>',
            ],
            'form' => $this->formBuilderService->getForm(
              DungeonCrawlerTableRowEditForm::class,
              $selected_table,
              $selected_metadata['columns'],
              $selected_metadata['primary_keys'],
              $primary_key_values,
              $row,
            ),
          ],
        ];
      }
    }

    return $build;
  }

  /**
   * Builds the table inventory summary.
   */
  protected function buildInventoryTable(array $table_inventory): array {
    $rows = [];

    foreach ($table_inventory as $table_name => $metadata) {
      $link = Link::fromTextAndUrl(
        $table_name,
        Url::fromRoute('dungeoncrawler_content.game_objects', [], ['query' => ['table' => $table_name]]),
      )->toRenderable();

      $rows[] = [
        'table' => $link,
        'objects' => $metadata['object_description'],
        'fields' => count($metadata['columns']),
        'rows' => $metadata['row_count'],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Table'),
        $this->t('Objects Stored'),
        $this->t('Field Count'),
        $this->t('Row Count'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No Dungeon Crawler tables found.'),
      '#attributes' => ['class' => ['game-content-dashboard', 'mb-4']],
      '#caption' => $this->t('Inventory of Dungeon Crawler data tables and stored object classes.'),
    ];
  }

  /**
   * Builds a field-level inventory table for a selected table.
   */
  protected function buildFieldInventoryTable(string $table_name, array $metadata): array {
    $rows = [];

    foreach ($metadata['columns'] as $column_name => $column) {
      $rows[] = [
        $column_name,
        $column['data_type'],
        $column['is_nullable'] === 'YES' ? $this->t('Yes') : $this->t('No'),
        $column['column_key'] === 'PRI' ? $this->t('Primary Key') : $this->t(''),
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Field'),
        $this->t('Type'),
        $this->t('Nullable'),
        $this->t('Index'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No fields found for @table.', ['@table' => $table_name]),
      '#attributes' => ['class' => ['game-content-dashboard']],
      '#caption' => $this->t('All fields in @table.', ['@table' => $table_name]),
    ];
  }

  /**
   * Builds a row browser table for the selected data table.
   */
  protected function buildRowsTable(string $table_name, array $metadata, array $rows): array {
    $headers = array_keys($metadata['columns']);
    $headers[] = $this->t('Operations');

    $table_rows = [];
    foreach ($rows as $row) {
      $display_row = [];
      foreach ($metadata['columns'] as $column_name => $column) {
        $display_row[] = $this->formatCellValue($row[$column_name] ?? NULL);
      }

      if (!empty($metadata['primary_keys'])) {
        $query = ['table' => $table_name, 'edit' => 1];
        foreach ($metadata['primary_keys'] as $primary_key) {
          $query[$primary_key] = (string) ($row[$primary_key] ?? '');
        }

        $display_row[] = Link::fromTextAndUrl(
          $this->t('Edit'),
          Url::fromRoute('dungeoncrawler_content.game_objects', [], ['query' => $query]),
        )->toRenderable();
      }
      else {
        $display_row[] = $this->t('No primary key');
      }

      $table_rows[] = $display_row;
    }

    return [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $table_rows,
      '#empty' => $this->t('No rows found in @table.', ['@table' => $table_name]),
      '#attributes' => ['class' => ['game-content-dashboard']],
      '#caption' => $this->t('Showing up to @limit rows from @table.', ['@limit' => self::MAX_ROWS, '@table' => $table_name]),
    ];
  }

  /**
   * Gets Dungeon Crawler table inventory metadata.
   */
  protected function getDungeonCrawlerTableInventory(): array {
    $tables = array_keys($this->database->schema()->findTables('^(dc_|dungeoncrawler_content_)'));
    sort($tables);

    $inventory = [];
    foreach ($tables as $table_name) {
      $columns = $this->getTableColumns($table_name);
      $primary_keys = [];
      foreach ($columns as $column_name => $column) {
        if ($column['column_key'] === 'PRI') {
          $primary_keys[] = $column_name;
        }
      }

      $inventory[$table_name] = [
        'columns' => $columns,
        'primary_keys' => $primary_keys,
        'row_count' => $this->getTableRowCount($table_name),
        'object_description' => $this->describeTableObjects($table_name),
      ];
    }

    return $inventory;
  }

  /**
   * Loads table column metadata from information_schema.
   */
  protected function getTableColumns(string $table_name): array {
    $query = $this->database->query(
      'SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY
       FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
       ORDER BY ORDINAL_POSITION',
      [':table' => $table_name],
    );

    $columns = [];
    foreach ($query->fetchAllAssoc('COLUMN_NAME') as $column_name => $row) {
      $columns[$column_name] = [
        'data_type' => (string) $row->DATA_TYPE,
        'is_nullable' => (string) $row->IS_NULLABLE,
        'column_key' => (string) $row->COLUMN_KEY,
      ];
    }

    return $columns;
  }

  /**
   * Gets row count for a table.
   */
  protected function getTableRowCount(string $table_name): int {
    return (int) $this->database->select($table_name, 't')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Loads rows from a selected table.
   */
  protected function loadTableRows(string $table_name, array $primary_keys): array {
    $query = $this->database->select($table_name, 't');
    $query->fields('t');

    if (count($primary_keys) === 1) {
      $query->orderBy($primary_keys[0], 'DESC');
    }

    $query->range(0, self::MAX_ROWS);
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Loads a single row using primary key values.
   */
  protected function loadTableRowByPrimaryKey(string $table_name, array $primary_key_values): array {
    $query = $this->database->select($table_name, 't');
    $query->fields('t');
    foreach ($primary_key_values as $key => $value) {
      $query->condition($key, $value);
    }
    $query->range(0, 1);

    $row = $query->execute()->fetchAssoc();
    return is_array($row) ? $row : [];
  }

  /**
   * Extracts primary key values from query parameters.
   */
  protected function extractPrimaryKeyValues(array $query, array $primary_keys): array {
    $values = [];
    foreach ($primary_keys as $primary_key) {
      if (!array_key_exists($primary_key, $query)) {
        return [];
      }
      $values[$primary_key] = (string) $query[$primary_key];
    }
    return $values;
  }

  /**
   * Gets object description for a table.
   */
  protected function describeTableObjects(string $table_name): string {
    if (isset(self::TABLE_OBJECT_MAP[$table_name])) {
      return self::TABLE_OBJECT_MAP[$table_name];
    }

    if (str_starts_with($table_name, 'dc_campaign_')) {
      return (string) $this->t('Campaign runtime objects and generated world state records.');
    }

    if (str_starts_with($table_name, 'dc_')) {
      return (string) $this->t('Dungeon Crawler domain objects and game data records.');
    }

    return (string) $this->t('Custom Dungeon Crawler data objects.');
  }

  /**
   * Formats a cell value for browser display.
   */
  protected function formatCellValue(mixed $value): string {
    if ($value === NULL) {
      return 'NULL';
    }

    $string = (string) $value;
    if ($string === '') {
      return '';
    }

    if (mb_strlen($string) > 160) {
      return mb_substr($string, 0, 157) . '...';
    }

    return $string;
  }

}
