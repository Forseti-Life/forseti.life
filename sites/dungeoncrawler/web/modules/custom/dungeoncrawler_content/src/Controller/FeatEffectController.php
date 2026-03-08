<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\FeatEffectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * API controller for feat effect resolution.
 */
class FeatEffectController extends ControllerBase {

  protected CharacterManager $characterManager;
  protected FeatEffectManager $featEffectManager;

  public function __construct(CharacterManager $character_manager, FeatEffectManager $feat_effect_manager) {
    $this->characterManager = $character_manager;
    $this->featEffectManager = $feat_effect_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.feat_effect_manager'),
    );
  }

  /**
   * Resolve feat effects for a character.
   *
   * GET /api/character/{character_id}/feat-effects
   */
  public function getCharacterFeatEffects(int $character_id): JsonResponse {
    $record = $this->characterManager->loadCharacter($character_id);
    if (!$record) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Character not found',
      ], 404);
    }

    if (!$this->characterManager->isOwner($record) && !$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied',
      ], 403);
    }

    $decoded = $this->characterManager->getCharacterData($record);
    $char_data = is_array($decoded['character'] ?? NULL) ? $decoded['character'] : $decoded;

    $base_speed = is_array($char_data['ancestry'] ?? NULL)
      ? (int) ($char_data['ancestry']['speed'] ?? 25)
      : (int) ($char_data['speed'] ?? 25);

    $effect_state = $this->featEffectManager->buildEffectState($char_data, [
      'level' => (int) ($char_data['level'] ?? $record->level ?? 1),
      'base_speed' => $base_speed,
      'existing_hp_max' => (int) ($record->hp_max ?? 0),
    ]);

    return new JsonResponse([
      'success' => TRUE,
      'data' => [
        'character_id' => (int) $record->id,
        'character_name' => (string) ($char_data['name'] ?? $record->name),
        'feat_effects' => $effect_state,
      ],
    ]);
  }

}
