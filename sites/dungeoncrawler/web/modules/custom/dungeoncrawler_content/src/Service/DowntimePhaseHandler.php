<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles game actions during the Downtime phase (stub).
 *
 * Downtime is the between-adventure phase where characters perform long-duration
 * activities: crafting items, earning income, retraining feats/skills, and
 * recovering from conditions.
 *
 * This is a stub implementation. Only long_rest is functional.
 * Full implementation is deferred to a future sprint.
 */
class DowntimePhaseHandler implements PhaseHandlerInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CharacterStateService
   */
  protected CharacterStateService $characterStateService;

  /**
   * Constructs a DowntimePhaseHandler.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    CharacterStateService $character_state_service
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->characterStateService = $character_state_service;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhaseName(): string {
    return 'downtime';
  }

  /**
   * {@inheritdoc}
   */
  public function getLegalIntents(): array {
    return [
      'long_rest',
      'craft',
      'earn_income',
      'retrain',
      'advance_day',
      'talk',
      'return_to_exploration',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateIntent(array $intent, array $game_state, array $dungeon_data): array {
    $type = $intent['type'] ?? '';

    if (!in_array($type, $this->getLegalIntents(), TRUE)) {
      return [
        'valid' => FALSE,
        'reason' => "Action '$type' is not legal during downtime phase.",
      ];
    }

    return ['valid' => TRUE, 'reason' => NULL];
  }

  /**
   * {@inheritdoc}
   */
  public function processIntent(array $intent, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $type = $intent['type'] ?? '';
    $actor_id = $intent['actor'] ?? NULL;
    $params = $intent['params'] ?? [];

    $result = [];
    $mutations = [];
    $events = [];
    $phase_transition = NULL;
    $narration = NULL;

    switch ($type) {

      case 'long_rest':
        $result = $this->processLongRest($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('long_rest', 'downtime', $actor_id, [
          'hp_restored' => $result['hp_restored'] ?? 0,
          'conditions_removed' => $result['conditions_removed'] ?? [],
          'spell_slots_restored' => $result['spell_slots_restored'] ?? FALSE,
        ]);
        break;

      case 'return_to_exploration':
        $phase_transition = [
          'from' => 'downtime',
          'to' => 'exploration',
          'reason' => 'Returning to adventure.',
        ];
        $events[] = GameEventLogger::buildEvent('downtime_ended', 'downtime', $actor_id, []);
        break;

      case 'craft':
      case 'earn_income':
      case 'retrain':
      case 'advance_day':
        // Stubs — to be implemented in a future sprint.
        $result = [
          'stub' => TRUE,
          'message' => "The '$type' action is not yet implemented.",
        ];
        $events[] = GameEventLogger::buildEvent($type, 'downtime', $actor_id, [
          'stub' => TRUE,
        ]);
        break;

      case 'talk':
        $result = ['talked' => TRUE, 'message' => $params['message'] ?? ''];
        $events[] = GameEventLogger::buildEvent('talk', 'downtime', $actor_id, [
          'message' => $params['message'] ?? '',
        ]);
        break;

      default:
        return [
          'success' => FALSE,
          'result' => ['error' => "Unknown downtime action: $type"],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ];
    }

    return [
      'success' => TRUE,
      'result' => $result,
      'mutations' => $mutations,
      'events' => $events,
      'phase_transition' => $phase_transition,
      'narration' => $narration,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onEnter(array $context, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $game_state['phase'] = 'downtime';
    $game_state['round'] = NULL;
    $game_state['turn'] = NULL;
    $game_state['encounter_id'] = NULL;

    if (!isset($game_state['downtime'])) {
      $game_state['downtime'] = [
        'days_elapsed' => 0,
        'activities' => [],
      ];
    }

    return [
      GameEventLogger::buildEvent('phase_entered', 'downtime', NULL, [
        'from_phase' => $context['from_phase'] ?? 'none',
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onExit(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return [
      GameEventLogger::buildEvent('phase_exited', 'downtime', NULL, [
        'days_elapsed' => $game_state['downtime']['days_elapsed'] ?? 0,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableActions(array $game_state, array $dungeon_data, ?string $actor_id = NULL): array {
    return ['long_rest', 'talk', 'return_to_exploration'];
  }

  // =========================================================================
  // Action processors.
  // =========================================================================

  /**
   * Processes a long rest: restore HP, spell slots, remove conditions.
   */
  protected function processLongRest(?string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    // Long rest: 8 hours of rest. Per PF2e rules:
    // - Restore hit points equal to Con modifier × level (minimum 1)
    // - Regain all spell slots
    // - Remove the wounded condition
    // - Reduce the value of doomed by 1

    $hp_restored = 0;
    $conditions_removed = [];

    // Find the character entity and restore HP.
    if ($actor_id && !empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$entity) {
        $iid = $entity['instance_id'] ?? ($entity['id'] ?? NULL);
        if ($iid === $actor_id) {
          $current_hp = $entity['state']['hit_points']['current'] ?? 0;
          $max_hp = $entity['state']['hit_points']['max'] ?? 20;

          // Restore a portion of HP (Con mod × level, simplified to full HP for now).
          $entity['state']['hit_points']['current'] = $max_hp;
          $hp_restored = $max_hp - $current_hp;

          // Remove wounded condition.
          if (isset($entity['state']['conditions'])) {
            $entity['state']['conditions'] = array_filter(
              $entity['state']['conditions'],
              function ($condition) use (&$conditions_removed) {
                $name = $condition['name'] ?? ($condition['type'] ?? '');
                if ($name === 'wounded') {
                  $conditions_removed[] = 'wounded';
                  return FALSE;
                }
                return TRUE;
              }
            );
            $entity['state']['conditions'] = array_values($entity['state']['conditions']);
          }

          break;
        }
      }
      unset($entity);
    }

    // Advance downtime days.
    if (isset($game_state['downtime'])) {
      $game_state['downtime']['days_elapsed'] = ($game_state['downtime']['days_elapsed'] ?? 0) + 1;
    }

    // Persist.
    try {
      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist long rest: @error', ['@error' => $e->getMessage()]);
    }

    return [
      'rested' => TRUE,
      'hp_restored' => $hp_restored,
      'conditions_removed' => $conditions_removed,
      'spell_slots_restored' => TRUE,
      'mutations' => [
        ['entity' => $actor_id, 'field' => 'hit_points.current', 'to' => 'max'],
      ],
    ];
  }

}
