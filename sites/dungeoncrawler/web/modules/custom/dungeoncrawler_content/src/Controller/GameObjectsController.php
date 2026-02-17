<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for game object management and review.
 */
class GameObjectsController extends ControllerBase {

  /**
   * Maximum number of objects displayed in the review table.
   */
  private const MAX_ROWS = 200;

  /**
   * Maximum number of attributes shown per object.
   */
  private const MAX_ATTRIBUTE_PREVIEW = 4;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManagerService;

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs a new GameObjectsController.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter) {
    $this->entityTypeManagerService = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Builds the game object management page.
   *
   * @return array
   *   Render array for the page.
   */
  public function content(): array {
    $build = [];

    $build['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mb-4']],
      'title' => [
        '#markup' => '<h2>' . $this->t('Game Object Manager') . '</h2>',
      ],
      'description' => [
        '#markup' => '<p>' . $this->t('Review all game objects and inspect their core attributes in one place.') . '</p>',
      ],
    ];

    $node_storage = $this->entityTypeManagerService->getStorage('node');
    $bundle_labels = [];
    foreach ($this->entityTypeManagerService->getStorage('node_type')->loadMultiple() as $bundle_id => $bundle) {
      $bundle_labels[$bundle_id] = $bundle->label();
    }

    $build['bundle_summary'] = $this->buildBundleSummaryTable($node_storage, $bundle_labels);

    $query = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('changed', 'DESC')
      ->range(0, self::MAX_ROWS);
    $nids = $query->execute();

    if (empty($nids)) {
      $build['objects_empty'] = [
        '#markup' => '<p>' . $this->t('No game objects were found.') . '</p>',
      ];
      return $build;
    }

    $nodes = $node_storage->loadMultiple($nids);

    $rows = [];
    foreach ($nodes as $node) {
      $title = $node->label();
      if ($node->hasLinkTemplate('canonical')) {
        $title = Link::fromTextAndUrl($node->label(), $node->toUrl())->toRenderable();
      }

      $rows[] = [
        'title' => $title,
        'bundle' => $bundle_labels[$node->bundle()] ?? $node->bundle(),
        'status' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
        'attributes' => $this->buildAttributePreview($node),
        'updated' => $this->dateFormatter->format((int) $node->getChangedTime(), 'short'),
      ];
    }

    $build['objects_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Object'),
        $this->t('Type'),
        $this->t('Status'),
        $this->t('Attribute Preview'),
        $this->t('Updated'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No objects available.'),
      '#attributes' => ['class' => ['game-content-dashboard']],
      '#caption' => $this->t('Most recently updated objects (up to @limit rows).', ['@limit' => self::MAX_ROWS]),
    ];

    return $build;
  }

  /**
   * Builds a content-type summary table.
   */
  protected function buildBundleSummaryTable($node_storage, array $bundle_labels): array {
    $rows = [];

    foreach ($bundle_labels as $bundle_id => $bundle_label) {
      $count = $node_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $bundle_id)
        ->count()
        ->execute();

      if ($count > 0) {
        $rows[] = [$bundle_label, $count];
      }
    }

    return [
      '#type' => 'table',
      '#header' => [$this->t('Object Type'), $this->t('Count')],
      '#rows' => $rows,
      '#empty' => $this->t('No object types with saved content yet.'),
      '#attributes' => ['class' => ['game-content-dashboard', 'mb-4']],
      '#caption' => $this->t('Object totals by content type.'),
    ];
  }

  /**
   * Creates a concise attribute preview for a node.
   */
  protected function buildAttributePreview($node): string {
    $exclude = [
      'nid',
      'vid',
      'uuid',
      'langcode',
      'type',
      'revision_timestamp',
      'revision_uid',
      'revision_log',
      'revision_default',
      'isDefaultRevision',
      'status',
      'uid',
      'title',
      'created',
      'changed',
      'promote',
      'sticky',
      'default_langcode',
      'revision_translation_affected',
      'path',
    ];

    $parts = [];
    foreach ($node->getFields() as $field_name => $field) {
      if (in_array($field_name, $exclude, TRUE) || $field->isEmpty()) {
        continue;
      }

      $label = $field->getFieldDefinition()->getLabel();
      $parts[] = $label . ': ' . $this->summarizeFieldValue($field);

      if (count($parts) >= self::MAX_ATTRIBUTE_PREVIEW) {
        break;
      }
    }

    if (empty($parts)) {
      return (string) $this->t('No additional attributes');
    }

    return implode(' | ', $parts);
  }

  /**
   * Summarizes a field value for table display.
   */
  protected function summarizeFieldValue(FieldItemListInterface $field): string {
    $definition = $field->getFieldDefinition();
    $field_type = $definition->getType();

    if ($field_type === 'entity_reference') {
      $target_count = count($field->getValue());
      return (string) $this->t('@count reference(s)', ['@count' => $target_count]);
    }

    if ($field_type === 'boolean') {
      $value = (int) ($field->first()?->value ?? 0);
      return $value === 1 ? (string) $this->t('Yes') : (string) $this->t('No');
    }

    if ($field_type === 'text_long' || $field_type === 'text_with_summary') {
      $text = (string) ($field->first()?->value ?? '');
      return mb_strimwidth(trim(strip_tags($text)), 0, 80, '…');
    }

    $main_property = $definition->getFieldStorageDefinition()->getMainPropertyName();
    if ($main_property && isset($field->first()?->{$main_property})) {
      $value = (string) $field->first()->{$main_property};
      return mb_strimwidth(trim($value), 0, 80, '…');
    }

    $storage = $definition->getFieldStorageDefinition();
    if ($storage->getCardinality() === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED || $storage->getCardinality() > 1) {
      return (string) $this->t('@count value(s)', ['@count' => count($field->getValue())]);
    }

    return mb_strimwidth(trim((string) json_encode($field->getValue())), 0, 80, '…');
  }

}
