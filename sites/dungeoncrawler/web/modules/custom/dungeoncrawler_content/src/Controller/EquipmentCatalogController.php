<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\EquipmentCatalogService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Equipment catalog API endpoints.
 *
 * Routes:
 *   GET /equipment?type=weapon|armor|shield|gear  -> filtered catalog
 *   GET /classes/{id}/starting-equipment          -> class starter kit
 */
class EquipmentCatalogController extends ControllerBase {

  protected EquipmentCatalogService $catalog;

  public function __construct(EquipmentCatalogService $catalog) {
    $this->catalog = $catalog;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.equipment_catalog')
    );
  }

  /**
   * GET /equipment?type=<type>
   * Returns equipment items. Optional ?type= filter: weapon|armor|shield|gear.
   * Equipment catalog is public (reference data).
   */
  public function catalog(Request $request): JsonResponse {
    $type = $request->query->get('type');

    if ($type !== NULL && !in_array($type, EquipmentCatalogService::VALID_TYPES, TRUE)) {
      return new JsonResponse([
        'error'       => 'Invalid type. Must be one of: ' . implode(', ', EquipmentCatalogService::VALID_TYPES),
        'valid_types' => EquipmentCatalogService::VALID_TYPES,
      ], 400);
    }

    $items = $this->catalog->getByType($type ?: NULL);

    return new JsonResponse([
      'type'  => $type ?? 'all',
      'count' => count($items),
      'items' => $items,
    ], 200);
  }

  /**
   * GET /classes/{id}/starting-equipment
   * Returns the starting gear package for a class.
   * Resolves each item ID to full catalog data.
   */
  public function startingEquipment(string $id): JsonResponse {
    $class_data = CharacterManager::CLASSES[$id] ?? NULL;
    if ($class_data === NULL) {
      return new JsonResponse(['error' => 'Class not found: ' . $id], 404);
    }

    $package = CharacterManager::STARTING_EQUIPMENT[$id] ?? NULL;
    if ($package === NULL) {
      return new JsonResponse(['error' => 'No starting equipment defined for class: ' . $id], 404);
    }

    $resolve = function (array $ids): array {
      $items = [];
      foreach ($ids as $item_id) {
        $item = $this->catalog->getById($item_id);
        $items[] = $item ?? ['id' => $item_id, 'name' => $item_id, 'note' => 'item not in catalog'];
      }
      return $items;
    };

    return new JsonResponse([
      'class'    => $id,
      'weapons'  => $resolve($package['weapons'] ?? []),
      'armor'    => $resolve($package['armor'] ?? []),
      'gear'     => $resolve($package['gear'] ?? []),
      'currency' => $package['currency'] ?? [],
      'note'     => $package['note'] ?? '',
    ], 200);
  }

}
