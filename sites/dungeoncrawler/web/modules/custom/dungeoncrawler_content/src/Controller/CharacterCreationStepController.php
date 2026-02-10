<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Multi-step character creation with separate pages per step.
 */
class CharacterCreationStepController extends ControllerBase {

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
   * Start or resume character creation.
   */
  public function start(Request $request) {
    // Check for existing draft
    $character_id = $request->query->get('character_id');
    
    if ($character_id) {
      // Load existing draft
      $character = $this->characterManager->loadCharacter($character_id);
      if ($character && $character->uid == $this->currentUser()->id()) {
        $data = json_decode($character->character_data, TRUE);
        $step = $data['step'] ?? 1;
        return new RedirectResponse(Url::fromRoute('dungeoncrawler_content.character_step', [
          'step' => $step,
          'character_id' => $character_id,
        ])->toString());
      }
    }
    
    // Start new character at step 1
    return new RedirectResponse(Url::fromRoute('dungeoncrawler_content.character_step', ['step' => 1])->toString());
  }

  /**
   * Display a specific character creation step.
   */
  public function step(int $step, Request $request) {
    if ($step < 1 || $step > 8) {
      $this->messenger()->addError($this->t('Invalid step number.'));
      return new RedirectResponse(Url::fromRoute('dungeoncrawler_content.characters')->toString());
    }

    $character_id = $request->query->get('character_id');
    $character_data = $this->loadOrCreateDraft($character_id);

    // Prepare step-specific data
    $build = [
      '#theme' => 'character_step_' . $step,
      '#character_id' => $character_data['id'],
      '#character' => $character_data['data'],
      '#step' => $step,
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/character-step-' . $step,
        ],
      ],
    ];

    // Add step-specific variables
    switch ($step) {
      case 2:
        $build['#ancestries'] = $this->prepareAncestries();
        break;
      
      case 3:
        $build['#backgrounds'] = $this->prepareBackgrounds();
        break;
      
      case 4:
        $build['#classes'] = $this->prepareClasses();
        break;
      
      case 6:
        $build['#alignments'] = $this->getAlignments();
        break;
      
      case 7:
        $build['#equipment'] = $this->getEquipmentCatalog();
        break;
    }

    return $build;
  }

  /**
   * Save step data and return JSON response for AJAX.
   */
  public function saveStep(int $step, Request $request) {
    $character_id = $request->request->get('character_id') ?: $request->query->get('character_id');
    $data = $request->request->all();
    
    // Load existing character
    $character = $character_id ? $this->characterManager->loadCharacter($character_id) : NULL;
    
    if ($character && $character->uid != $this->currentUser()->id()) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Access denied.'),
      ], 403);
    }

    // Merge with existing data
    $character_data = $character ? json_decode($character->character_data, TRUE) : $this->getDefaultCharacterData();
    
    // Update with step data
    $character_data = $this->updateStepData($character_data, $step, $data);
    $character_data['step'] = $step + 1; // Advance to next step

    // Save to database
    if ($character) {
      $this->characterManager->updateCharacter($character_id, [
        'name' => $character_data['name'] ?: 'Unnamed Character',
        'ancestry' => $character_data['ancestry'] ?? '',
        'class' => $character_data['class'] ?? '',
        'character_data' => json_encode($character_data, JSON_PRETTY_PRINT),
      ]);
    } else {
      $character_id = $this->createDraft($character_data);
    }

    // Return JSON response with redirect URL
    if ($step >= 8) {
      // Mark as complete
      $this->characterManager->updateCharacter($character_id, ['status' => 1]);
      return new JsonResponse([
        'success' => TRUE,
        'message' => $this->t('Character created successfully!'),
        'redirect' => Url::fromRoute('dungeoncrawler_content.character_view', [
          'character_id' => $character_id,
        ])->toString(),
      ]);
    }

    return new JsonResponse([
      'success' => TRUE,
      'redirect' => Url::fromRoute('dungeoncrawler_content.character_step', [
        'step' => $step + 1,
      ])->setOption('query', ['character_id' => $character_id])->toString(),
    ]);
  }

  /**
   * Load existing draft or create new one.
   */
  private function loadOrCreateDraft($character_id) {
    if ($character_id) {
      $character = $this->characterManager->loadCharacter($character_id);
      if ($character && $character->uid == $this->currentUser()->id()) {
        return [
          'id' => $character->id,
          'data' => json_decode($character->character_data, TRUE),
        ];
      }
    }

    return [
      'id' => NULL,
      'data' => $this->getDefaultCharacterData(),
    ];
  }

  /**
   * Create new draft character.
   */
  private function createDraft(array $character_data) {
    $db = \Drupal::database();
    $now = \Drupal::time()->getRequestTime();
    
    return $db->insert('dc_characters')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => (int) $this->currentUser()->id(),
        'name' => $character_data['name'] ?: 'Unnamed Character',
        'level' => 1,
        'ancestry' => $character_data['ancestry'] ?? '',
        'class' => $character_data['class'] ?? '',
        'character_data' => json_encode($character_data, JSON_PRETTY_PRINT),
        'status' => 0, // Draft
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();
  }

  /**
   * Update character data with step-specific fields.
   */
  private function updateStepData(array $character_data, int $step, array $form_data) {
    switch ($step) {
      case 1:
        $character_data['name'] = $form_data['name'] ?? '';
        $character_data['concept'] = $form_data['concept'] ?? '';
        break;
      
      case 2:
        $character_data['ancestry'] = $form_data['ancestry'] ?? '';
        $character_data['heritage'] = $form_data['heritage'] ?? '';
        break;
      
      case 3:
        $character_data['background'] = $form_data['background'] ?? '';
        $character_data['background_boosts'] = $form_data['background_boosts'] ?? [];
        break;
      
      case 4:
        $character_data['class'] = $form_data['class'] ?? '';
        break;
      
      case 5:
        $character_data['free_boosts'] = $form_data['free_boosts'] ?? [];
        break;
      
      case 6:
        $character_data['alignment'] = $form_data['alignment'] ?? '';
        $character_data['deity'] = $form_data['deity'] ?? '';
        $character_data['age'] = $form_data['age'] ?? '';
        $character_data['gender'] = $form_data['gender'] ?? '';
        break;
      
      case 7:
        $character_data['equipment'] = $form_data['equipment'] ?? [];
        $character_data['gold'] = $form_data['gold'] ?? 15;
        break;
      
      case 8:
        $character_data['appearance'] = $form_data['appearance'] ?? '';
        $character_data['personality'] = $form_data['personality'] ?? '';
        $character_data['backstory'] = $form_data['backstory'] ?? '';
        break;
    }

    return $character_data;
  }

  /**
   * Get default character data structure.
   */
  private function getDefaultCharacterData() {
    return [
      'step' => 1,
      'name' => '',
      'concept' => '',
      'ancestry' => '',
      'heritage' => '',
      'background' => '',
      'background_boosts' => [],
      'class' => '',
      'free_boosts' => [],
      'abilities' => [
        'str' => 10, 'dex' => 10, 'con' => 10,
        'int' => 10, 'wis' => 10, 'cha' => 10,
      ],
      'alignment' => '',
      'deity' => '',
      'age' => '',
      'gender' => '',
      'equipment' => [],
      'gold' => 15,
      'appearance' => '',
      'personality' => '',
      'backstory' => '',
    ];
  }

  /**
   * Prepare ancestry data.
   */
  private function prepareAncestries() {
    $ancestries = [];
    foreach (CharacterManager::ANCESTRIES as $name => $data) {
      $ancestries[] = [
        'id' => strtolower(str_replace(' ', '-', $name)),
        'name' => $name,
        'hp' => $data['hp'],
        'size' => $data['size'],
        'speed' => $data['speed'],
      ];
    }
    return $ancestries;
  }

  /**
   * Prepare class data.
   */
  private function prepareClasses() {
    $classes = [];
    foreach (CharacterManager::CLASSES as $name => $data) {
      $classes[] = [
        'id' => strtolower(str_replace(' ', '-', $name)),
        'name' => $name,
        'hp' => $data['hp'],
        'key_ability' => $data['key_ability'],
      ];
    }
    return $classes;
  }

  /**
   * Prepare background options.
   */
  private function prepareBackgrounds() {
    return [
      ['id' => 'acolyte', 'name' => 'Acolyte'],
      ['id' => 'criminal', 'name' => 'Criminal'],
      ['id' => 'entertainer', 'name' => 'Entertainer'],
      ['id' => 'farmhand', 'name' => 'Farmhand'],
      ['id' => 'guard', 'name' => 'Guard'],
      ['id' => 'merchant', 'name' => 'Merchant'],
      ['id' => 'noble', 'name' => 'Noble'],
      ['id' => 'scholar', 'name' => 'Scholar'],
      ['id' => 'warrior', 'name' => 'Warrior'],
    ];
  }

  /**
   * Get alignment options.
   */
  private function getAlignments() {
    return [
      ['id' => 'LG', 'name' => 'Lawful Good'],
      ['id' => 'NG', 'name' => 'Neutral Good'],
      ['id' => 'CG', 'name' => 'Chaotic Good'],
      ['id' => 'LN', 'name' => 'Lawful Neutral'],
      ['id' => 'N', 'name' => 'True Neutral'],
      ['id' => 'CN', 'name' => 'Chaotic Neutral'],
      ['id' => 'LE', 'name' => 'Lawful Evil'],
      ['id' => 'NE', 'name' => 'Neutral Evil'],
      ['id' => 'CE', 'name' => 'Chaotic Evil'],
    ];
  }

  /**
   * Get equipment catalog.
   */
  private function getEquipmentCatalog() {
    return [
      'weapons' => [
        ['id' => 'longsword', 'name' => 'Longsword', 'cost' => 1, 'damage' => '1d8 S'],
        ['id' => 'shortsword', 'name' => 'Shortsword', 'cost' => 0.9, 'damage' => '1d6 P'],
        ['id' => 'dagger', 'name' => 'Dagger', 'cost' => 0.2, 'damage' => '1d4 P'],
      ],
      'armor' => [
        ['id' => 'leather', 'name' => 'Leather Armor', 'cost' => 2, 'ac' => '+1'],
        ['id' => 'chain-shirt', 'name' => 'Chain Shirt', 'cost' => 5, 'ac' => '+2'],
      ],
    ];
  }

}
