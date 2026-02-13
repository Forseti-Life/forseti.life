<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the /characters page — lists all characters for the user.
 */
class CharacterListController extends ControllerBase {

  protected CharacterManager $characterManager;
  protected Connection $database;

  public function __construct(CharacterManager $character_manager, Connection $database) {
    $this->characterManager = $character_manager;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('database'),
    );
  }

  /**
   * Renders the character list page.
   */
  public function listCharacters() {
    $characters = $this->characterManager->getUserCharacters();
    $campaign_id = (int) (\Drupal::request()->query->get('campaign_id') ?? 0);
    $campaign_name = NULL;

    if ($campaign_id > 0) {
      $campaign = $this->database->select('dc_campaigns', 'c')
        ->fields('c', ['id', 'name', 'uid'])
        ->condition('id', $campaign_id)
        ->execute()
        ->fetchObject();

      if ($campaign && (int) $campaign->uid === (int) $this->currentUser()->id()) {
        $campaign_name = $campaign->name;
      }
      else {
        $campaign_id = 0;
      }
    }

    $character_cards = [];
    foreach ($characters as $record) {
      $data = $this->characterManager->getCharacterData($record);
      $char = $data['character'] ?? [];

      $view_url = Url::fromRoute('dungeoncrawler_content.character_view', ['character_id' => $record->id]);
      if ($campaign_id > 0) {
        $view_url->setOption('query', ['campaign_id' => $campaign_id]);
      }

      $select_url = NULL;
      if ($campaign_id > 0 && (int) $record->status === 1) {
        $select_url = Url::fromRoute('dungeoncrawler_content.campaign_select_character', [
          'campaign_id' => $campaign_id,
          'character_id' => $record->id,
        ])->toString();
      }

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
        'url' => $view_url->toString(),
        'select_url' => $select_url,
        'created' => date('M j, Y', $record->created),
      ];
    }

    $create_url = Url::fromRoute('dungeoncrawler_content.character_creation_wizard');
    if ($campaign_id > 0) {
      $create_url->setOption('query', ['campaign_id' => $campaign_id]);
    }

    $build = [
      '#theme' => 'character_list',
      '#characters' => $character_cards,
      '#create_url' => $create_url->toString(),
      '#create_campaign_url' => Url::fromRoute('dungeoncrawler_content.campaign_create')->toString(),
      '#campaign_id' => $campaign_id,
      '#campaign_name' => $campaign_name,
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'contexts' => ['user', 'url.query_args:campaign_id'],
        'tags' => ['dc_characters'],
      ],
    ];

    return $build;
  }

}
