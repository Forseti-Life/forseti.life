<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the /characters page — lists all characters for the user.
 */
class CharacterListController extends ControllerBase {

  protected CharacterManager $characterManager;

  public function __construct(CharacterManager $character_manager) {
    $this->characterManager = $character_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
    );
  }

  /**
   * Renders the character list page.
   */
  public function listCharacters() {
    $characters = $this->characterManager->getUserCharacters();

    $character_cards = [];
    foreach ($characters as $record) {
      $data = $this->characterManager->getCharacterData($record);
      $char = $data['character'] ?? [];

      $character_cards[] = [
        'id' => $record->id,
        'uuid' => $record->uuid,
        'name' => $record->name,
        'level' => $record->level,
        'ancestry' => $record->ancestry,
        'class' => $record->class,
        'hp_current' => $char['hit_points']['current'] ?? 0,
        'hp_max' => $char['hit_points']['max'] ?? 0,
        'ac' => $char['armor_class'] ?? 10,
        'status' => $record->status ? 'active' : 'dead',
        'portrait' => $record->portrait,
        'heritage' => $char['ancestry']['heritage'] ?? '',
        'alignment' => $char['personality']['alignment'] ?? '',
        'url' => Url::fromRoute('dungeoncrawler_content.character_view', ['character_id' => $record->id])->toString(),
        'created' => date('M j, Y', $record->created),
      ];
    }

    $build = [
      '#theme' => 'character_list',
      '#characters' => $character_cards,
      '#create_url' => Url::fromRoute('dungeoncrawler_content.character_create')->toString(),
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['dc_characters'],
      ],
    ];

    return $build;
  }

}
