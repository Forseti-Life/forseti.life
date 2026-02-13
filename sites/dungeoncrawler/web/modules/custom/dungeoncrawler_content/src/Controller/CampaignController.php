<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Form\CampaignCreateForm;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for campaign interactions.
 */
class CampaignController extends ControllerBase {

  protected Connection $database;
  protected CharacterManager $characterManager;
  protected FormBuilderInterface $formBuilderService;

  public function __construct(Connection $database, CharacterManager $character_manager, FormBuilderInterface $form_builder) {
    $this->database = $database;
    $this->characterManager = $character_manager;
    $this->formBuilderService = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('form_builder'),
    );
  }

  /**
   * Render campaign creation using the centralized management page template.
   */
  public function createCampaignPage() {
    return [
      '#theme' => 'management_form_page',
      '#page_title' => $this->t('Create Campaign'),
      '#page_description' => $this->t('Set up your campaign, then choose an existing character or create a new one.'),
      '#form' => $this->formBuilderService->getForm(CampaignCreateForm::class),
      '#back_url' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
      '#back_label' => $this->t('Back to Campaigns'),
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * List campaigns for the current user.
   */
  public function listCampaigns() {
    $uid = (int) $this->currentUser()->id();

    $campaigns = $this->database->select('dc_campaigns', 'c')
      ->fields('c')
      ->condition('uid', $uid)
      ->orderBy('changed', 'DESC')
      ->execute()
      ->fetchAll();

    $campaign_ids = [];
    $active_character_ids = [];
    foreach ($campaigns as $campaign) {
      $campaign_ids[] = (int) $campaign->id;
      if (!empty($campaign->active_character_id)) {
        $active_character_ids[] = (int) $campaign->active_character_id;
      }
    }

    $character_counts = [];
    if (!empty($campaign_ids)) {
      $count_query = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['campaign_id']);
      $count_query->addExpression('COUNT(*)', 'total');
      $character_counts = $count_query
        ->condition('campaign_id', $campaign_ids, 'IN')
        ->groupBy('campaign_id')
        ->execute()
        ->fetchAllKeyed(0, 1);
    }

    $active_character_names = [];
    if (!empty($active_character_ids)) {
      $active_character_names = $this->database->select('dc_characters', 'ch')
        ->fields('ch', ['id', 'name'])
        ->condition('id', array_values(array_unique($active_character_ids)), 'IN')
        ->execute()
        ->fetchAllKeyed(0, 1);
    }

    $status_labels = [
      'draft' => (string) $this->t('Draft'),
      'ready' => (string) $this->t('Ready'),
      'active' => (string) $this->t('Active'),
      'completed' => (string) $this->t('Completed'),
    ];

    $campaign_cards = [];
    foreach ($campaigns as $campaign) {
      $campaign_id = (int) $campaign->id;
      $active_character_id = (int) ($campaign->active_character_id ?? 0);
      $active_character_name = $active_character_id > 0
        ? ($active_character_names[$active_character_id] ?? $this->t('Unknown'))
        : $this->t('None selected');
      $can_launch = $active_character_id > 0;

      $action_url = Url::fromRoute('dungeoncrawler_content.campaign_tavernentrance', [
        'campaign_id' => $campaign_id,
      ])->toString();

      $campaign_cards[] = [
        'id' => $campaign_id,
        'name' => $campaign->name,
        'status' => $campaign->status,
        'status_label' => $status_labels[$campaign->status] ?? ucfirst((string) $campaign->status),
        'theme' => ucfirst(str_replace('_', ' ', (string) $campaign->theme)),
        'difficulty' => ucfirst((string) $campaign->difficulty),
        'character_count' => (int) ($character_counts[$campaign_id] ?? 0),
        'active_character' => (string) $active_character_name,
        'created' => date('M j, Y', (int) $campaign->created),
        'changed' => date('M j, Y', (int) $campaign->changed),
        'can_launch' => $can_launch,
        'action_label' => (string) $this->t('Enter Tavern'),
        'url' => $action_url,
      ];
    }

    return [
      '#theme' => 'campaign_list',
      '#campaigns' => $campaign_cards,
      '#create_url' => Url::fromRoute('dungeoncrawler_content.campaign_create')->toString(),
      '#characters_url' => Url::fromRoute('dungeoncrawler_content.characters')->toString(),
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['dc_campaigns', 'dc_campaign_characters', 'dc_characters'],
      ],
    ];
  }

  /**
   * Tavern entrance flow: choose a character and launch this campaign.
   */
  public function tavernEntrance(int $campaign_id) {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c')
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchObject();

    if (!$campaign) {
      throw new NotFoundHttpException();
    }

    if ((int) $campaign->uid !== (int) $this->currentUser()->id()) {
      throw new AccessDeniedHttpException();
    }

    $characters = $this->characterManager->getUserCharacters();
    $character_cards = [];

    foreach ($characters as $record) {
      $data = $this->characterManager->getCharacterData($record);
      $char = $data['character'] ?? [];

      $select_url = NULL;
      if ((int) $record->status === 1) {
        $select_url = Url::fromRoute('dungeoncrawler_content.campaign_select_character', [
          'campaign_id' => $campaign_id,
          'character_id' => (int) $record->id,
        ])->toString();
      }

      $character_cards[] = [
        'id' => (int) $record->id,
        'name' => $record->name,
        'level' => (int) $record->level,
        'ancestry' => $record->ancestry,
        'class' => $record->class,
        'hp_current' => (int) ($char['hit_points']['current'] ?? 0),
        'hp_max' => (int) ($char['hit_points']['max'] ?? 0),
        'ac' => (int) ($char['armor_class'] ?? 10),
        'status' => $record->status ? 'active' : 'dead',
        'portrait' => $record->portrait,
        'alignment' => $char['personality']['alignment'] ?? '',
        'created' => date('M j, Y', (int) $record->created),
        'select_url' => $select_url,
      ];
    }

    $campaign_data = [
      'id' => (int) $campaign->id,
      'name' => (string) $campaign->name,
      'theme' => ucfirst(str_replace('_', ' ', (string) $campaign->theme)),
      'difficulty' => ucfirst((string) $campaign->difficulty),
      'status' => ucfirst((string) $campaign->status),
    ];

    return [
      '#theme' => 'campaign_tavernentrance',
      '#campaign' => $campaign_data,
      '#characters' => $character_cards,
      '#create_character_url' => Url::fromRoute('dungeoncrawler_content.character_creation_wizard', [], [
        'query' => ['campaign_id' => $campaign_id],
      ])->toString(),
      '#back_url' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['dc_campaigns', 'dc_campaign_characters', 'dc_characters'],
      ],
    ];
  }

  /**
   * Select a character for a campaign.
   */
  public function selectCharacter(int $campaign_id, int $character_id) {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c')
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchObject();

    if (!$campaign) {
      throw new NotFoundHttpException();
    }

    if ((int) $campaign->uid !== (int) $this->currentUser()->id()) {
      throw new AccessDeniedHttpException();
    }

    $character = $this->characterManager->loadCharacter($character_id);
    if (!$character) {
      throw new NotFoundHttpException();
    }

    if (!$this->characterManager->isOwner($character)) {
      throw new AccessDeniedHttpException();
    }

    $now = \Drupal::time()->getRequestTime();

    $this->database->merge('dc_campaign_characters')
      ->keys([
        'campaign_id' => $campaign_id,
        'character_id' => $character_id,
      ])
      ->fields([
        'uid' => (int) $this->currentUser()->id(),
        'role' => 'player',
        'is_active' => 1,
        'joined' => $now,
      ])
      ->execute();

    $this->database->update('dc_campaigns')
      ->fields([
        'active_character_id' => $character_id,
        'status' => 'ready',
        'changed' => $now,
      ])
      ->condition('id', $campaign_id)
      ->execute();

    $this->messenger()->addStatus($this->t('Character selected for campaign.'));

    return $this->redirect('dungeoncrawler_content.hexmap_demo', [], [
      'query' => [
        'campaign_id' => $campaign_id,
        'character_id' => $character_id,
        'dungeon_level_id' => 'f8c6b8f1-2df9-469f-9fd5-67a59f120001',
        'map_id' => '0b7e3d2f-8f7c-4ae0-8f72-9e99e0800001',
        'room_id' => '7f2f1051-5f88-45a2-a66a-0f7063900001',
        'next_room_id' => '7f2f1051-5f88-45a2-a66a-0f7063900002',
        'start_q' => 0,
        'start_r' => 0,
      ],
    ]);
  }

}
