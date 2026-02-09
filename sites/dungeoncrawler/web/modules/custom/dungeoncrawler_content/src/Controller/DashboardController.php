<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the Dungeon Crawler game content dashboard.
 */
class DashboardController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a DashboardController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Displays the game content dashboard.
   *
   * @return array
   *   A render array for the dashboard page.
   */
  public function content() {
    $build = [];

    $build['header'] = [
      '#markup' => '<h2>⚔️ ' . $this->t('Dungeon Content Management') . '</h2><p>' . $this->t('Manage the AI-generated dungeon rooms, creatures, items, and quests that populate the living dungeon.') . '</p>',
    ];

    // Content type statistics.
    $game_types = [
      'dungeon' => $this->t('🏰 Dungeon Rooms'),
      'character_class' => $this->t('🧙 Character Classes'),
      'quest' => $this->t('📜 Quests'),
      'item' => $this->t('⚔️ Items & Loot'),
      'article' => $this->t('📖 Lore & World-Building'),
    ];

    $rows = [];
    $node_storage = $this->entityTypeManager->getStorage('node');

    foreach ($game_types as $type => $label) {
      try {
        $count = $node_storage->getQuery()
          ->condition('type', $type)
          ->accessCheck(FALSE)
          ->count()
          ->execute();
        $rows[] = [$label, $count];
      }
      catch (\Exception $e) {
        $rows[] = [$label, $this->t('Content type not yet created')];
      }
    }

    $build['stats'] = [
      '#type' => 'table',
      '#header' => [$this->t('Content Type'), $this->t('Count')],
      '#rows' => $rows,
      '#empty' => $this->t('No game content types have been created yet.'),
      '#attributes' => ['class' => ['game-content-dashboard']],
    ];

    $build['actions'] = [
      '#markup' => '<p>' . $this->t('The dungeon grows procedurally as adventurers explore. Use Structure → Content Types to manage dungeon rooms, AI creatures, items, and quests.') . '</p>',
    ];

    return $build;
  }

}
