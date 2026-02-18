<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Schema-driven multi-step character creation.
 */
class CharacterCreationStepController extends ControllerBase {

  protected CharacterManager $characterManager;
  protected SchemaLoader $schemaLoader;
  protected CsrfTokenGenerator $csrfToken;
  protected Connection $database;

  public function __construct(CharacterManager $character_manager, SchemaLoader $schema_loader, CsrfTokenGenerator $csrf_token, Connection $database) {
    $this->characterManager = $character_manager;
    $this->schemaLoader = $schema_loader;
    $this->csrfToken = $csrf_token;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.schema_loader'),
      $container->get('csrf_token'),
      $container->get('database'),
    );
  }

  /**
   * Start or resume character creation.
   */
  public function start(Request $request) {
    // Check for existing draft
    $character_id = $request->query->get('character_id');
    $campaign_id = $request->query->get('campaign_id');
    
    if ($character_id) {
      // Load existing draft
      $character = $this->characterManager->loadCharacter($character_id);
      if ($character && $character->uid == $this->currentUser()->id()) {
        $data = json_decode($character->character_data, TRUE);
        $step = (int) ($data['step'] ?? 1);
        if ($step === 5) {
          $step = 6;
        }
        $url = Url::fromRoute('dungeoncrawler_content.character_step', [
          'step' => $step,
        ]);
        $query = ['character_id' => $character_id];
        if ($campaign_id) {
          $query['campaign_id'] = $campaign_id;
        }
        $url->setOption('query', $query);
        return new RedirectResponse($url->toString());
      }
    }
    
    // Start new character at step 1
    $url = Url::fromRoute('dungeoncrawler_content.character_step', ['step' => 1]);
    if ($campaign_id) {
      $url->setOption('query', ['campaign_id' => $campaign_id]);
    }
    return new RedirectResponse($url->toString());
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
    $campaign_id = $request->query->get('campaign_id');
    
    // Return the form
    return $this->formBuilder()->getForm(
      'Drupal\dungeoncrawler_content\Form\CharacterCreationStepForm',
      $step,
      $character_id,
      $campaign_id
    );
  }

  /**
   * Save step data and return JSON response for AJAX.
   * Requires CSRF token for security.
   */
  public function saveStep(int $step, Request $request) {
    // Validate CSRF token
    $token = $request->headers->get('X-CSRF-Token');
    if (!$token || !$this->csrfToken->validate($token, 'rest')) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Invalid or missing CSRF token.'),
      ], 403);
    }

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
    $result = $this->updateStepData($character_data, $step, $data);
    
    // If validation failed, return the error response
    if ($result instanceof JsonResponse) {
      return $result;
    }
    
    $character_data = $result;
    $next_step = $this->getNextStep($step);
    $character_data['step'] = $next_step; // Advance to next step

    $hit_points = is_array($character_data['hit_points'] ?? NULL) ? $character_data['hit_points'] : [];
    $abilities = is_array($character_data['abilities'] ?? NULL) ? $character_data['abilities'] : [];
    $dex = (int) ($abilities['dex'] ?? 10);

    // Save to database
    if ($character) {
      $this->characterManager->updateCharacter($character_id, [
        'name' => $character_data['name'] ?: 'Unnamed Character',
        'ancestry' => $character_data['ancestry'] ?? '',
        'class' => $character_data['class'] ?? '',
        'level' => (int) ($character_data['level'] ?? 1),
        'hp_current' => (int) ($hit_points['current'] ?? 0),
        'hp_max' => (int) ($hit_points['max'] ?? 0),
        'armor_class' => (int) (10 + floor(($dex - 10) / 2)),
        'experience_points' => (int) ($character_data['experience_points'] ?? 0),
        'position_q' => (int) ($character_data['position']['q'] ?? 0),
        'position_r' => (int) ($character_data['position']['r'] ?? 0),
        'last_room_id' => (string) ($character_data['position']['room_id'] ?? ''),
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
        'step' => $next_step,
      ])->setOption('query', ['character_id' => $character_id])->toString(),
    ]);
  }

  /**
   * Gets the next step in the flow, skipping editable step 5.
   */
  private function getNextStep(int $step): int {
    if ($step === 4 || $step === 5) {
      return 6;
    }

    return min(8, $step + 1);
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
    $instance_id = \Drupal::service('uuid')->generate();
    
    return $db->insert('dc_campaign_characters')
      ->fields([
        'uuid' => $instance_id,
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => $instance_id,
        'uid' => (int) $this->currentUser()->id(),
        'name' => $character_data['name'] ?: 'Unnamed Character',
        'level' => 1,
        'ancestry' => $character_data['ancestry'] ?? '',
        'class' => $character_data['class'] ?? '',
        'hp_current' => 0,
        'hp_max' => 0,
        'armor_class' => 10,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
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
    // Simple mapping of form fields to character data
    $field_mappings = [
      1 => ['name', 'concept'],
      2 => ['ancestry', 'heritage'],
      3 => ['background', 'background_boosts'],
      4 => ['class'],
      5 => ['free_boosts'],
      6 => ['alignment', 'deity', 'age', 'gender'],
      7 => ['equipment', 'gold'],
      8 => ['appearance', 'personality', 'backstory'],
    ];

    // Use schema validation
    $validation = $this->schemaLoader->validateStepData($step, $form_data);
    if (!$validation['valid']) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => implode(' ', $validation['errors']),
      ], 400);
    }

    // Map form data to character data
    if (isset($field_mappings[$step])) {
      foreach ($field_mappings[$step] as $field) {
        if (isset($form_data[$field])) {
          $character_data[$field] = $form_data[$field];
        }
      }
    }

    return $character_data;
  }

  /**
   * Prepare options data for a specific step from CharacterManager constants.
   */
  private function prepareOptionsForStep(int $step): array {
    switch ($step) {
      case 2:
        return [
          'ancestries' => $this->prepareAncestries(),
          'heritages' => CharacterManager::HERITAGES,
        ];

      case 3:
        return [
          'backgrounds' => CharacterManager::BACKGROUNDS,
        ];

      case 4:
        return [
          'classes' => array_values(CharacterManager::CLASSES),
        ];

      case 6:
        return [
          'alignments' => $this->getAlignments(),
        ];

      case 7:
        return [
          'equipment' => $this->getEquipmentCatalog(),
        ];

      default:
        return [];
    }
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
        'boosts' => $data['boosts'],
        'flaw' => $data['flaw'] ?? '',
        'vision' => $data['vision'],
      ];
    }
    return $ancestries;
  }

  /**
   * Get alignment options.
   */
  private function getAlignments() {
    return [
      ['id' => 'LG', 'name' => 'Lawful Good', 'description' => 'Acts with compassion and honor within the bounds of law and order.'],
      ['id' => 'NG', 'name' => 'Neutral Good', 'description' => 'Does good without bias toward or against order.'],
      ['id' => 'CG', 'name' => 'Chaotic Good', 'description' => 'Acts with freedom and kindness.'],
      ['id' => 'LN', 'name' => 'Lawful Neutral', 'description' => 'Values tradition and order above morality.'],
      ['id' => 'N', 'name' => 'Neutral', 'description' => 'Seeks balance or personal interest.'],
      ['id' => 'CN', 'name' => 'Chaotic Neutral', 'description' => 'Follows individual freedom.'],
      ['id' => 'LE', 'name' => 'Lawful Evil', 'description' => 'Uses order as a tool for exploitation.'],
      ['id' => 'NE', 'name' => 'Neutral Evil', 'description' => 'Acts selfishly with no regard for others.'],
      ['id' => 'CE', 'name' => 'Chaotic Evil', 'description' => 'Driven by greed and hatred.'],
    ];
  }

  /**
   * Get equipment catalog.
   */
  private function getEquipmentCatalog() {
    $template_catalog = $this->buildEquipmentCatalogFromTemplates();
    if (!empty($template_catalog['weapons']) || !empty($template_catalog['armor']) || !empty($template_catalog['gear'])) {
      return $template_catalog;
    }

    return [
      'weapons' => [
        ['id' => 'longsword', 'name' => 'Longsword', 'cost' => 1, 'damage' => '1d8 S', 'bulk' => 1, 'hands' => 1],
        ['id' => 'shortsword', 'name' => 'Shortsword', 'cost' => 0.9, 'damage' => '1d6 P', 'bulk' => 'L', 'hands' => 1],
        ['id' => 'dagger', 'name' => 'Dagger', 'cost' => 0.2, 'damage' => '1d4 P', 'bulk' => 'L', 'hands' => 1],
        ['id' => 'staff', 'name' => 'Staff', 'cost' => 0, 'damage' => '1d4 B', 'bulk' => 1, 'hands' => 1],
      ],
      'armor' => [
        ['id' => 'leather', 'name' => 'Leather Armor', 'cost' => 2, 'ac' => '+1', 'bulk' => 1],
        ['id' => 'chain-shirt', 'name' => 'Chain Shirt', 'cost' => 5, 'ac' => '+2', 'bulk' => 1],
      ],
      'gear' => [
        ['id' => 'backpack', 'name' => 'Backpack', 'cost' => 0.1, 'bulk' => 'L'],
        ['id' => 'bedroll', 'name' => 'Bedroll', 'cost' => 0.1, 'bulk' => 'L'],
        ['id' => 'rope', 'name' => 'Rope (50ft)', 'cost' => 0.5, 'bulk' => 'L'],
      ],
    ];
  }

  /**
   * Builds step-7 equipment catalog from template item tables.
   */
  private function buildEquipmentCatalogFromTemplates(): array {
    $catalog = [
      'weapons' => [],
      'armor' => [],
      'gear' => [],
    ];

    if (!$this->database->schema()->tableExists('dungeoncrawler_content_item_instances') || !$this->database->schema()->tableExists('dungeoncrawler_content_registry')) {
      return $catalog;
    }

    $query = $this->database->select('dungeoncrawler_content_item_instances', 'ii');
    $query->fields('ii', ['item_id']);
    $query->leftJoin('dungeoncrawler_content_registry', 'r', 'r.content_type = :content_type AND r.content_id = ii.item_id', [':content_type' => 'item']);
    $query->fields('r', ['name', 'tags', 'schema_data']);
    $query->distinct();

    $result = $query->execute();

    foreach ($result as $row) {
      $item_id = (string) ($row->item_id ?? '');
      if ($item_id === '') {
        continue;
      }

      $schema_data = json_decode((string) ($row->schema_data ?? '{}'), TRUE);
      if (!is_array($schema_data)) {
        $schema_data = [];
      }

      $tags = $this->normalizeTags((string) ($row->tags ?? ''));
      $category = $this->mapTemplateItemCategory((string) ($schema_data['item_type'] ?? ''), $tags);

      $name = (string) ($row->name ?? '');
      if ($name === '') {
        $name = ucwords(str_replace('_', ' ', $item_id));
      }

      $item = [
        'id' => $item_id,
        'name' => $name,
        'type' => (string) ($schema_data['item_type'] ?? 'adventuring_gear'),
        'cost' => (float) ($schema_data['price_gp'] ?? 0),
        'bulk' => $schema_data['bulk'] ?? 'L',
        'traits' => $tags,
      ];

      if ($category === 'weapons') {
        $item['damage'] = (string) ($schema_data['damage'] ?? '');
        $item['hands'] = (int) ($schema_data['hands'] ?? 1);
      }
      elseif ($category === 'armor') {
        $item['ac'] = (string) ($schema_data['ac'] ?? '');
      }

      $catalog[$category][$item_id] = $item;
    }

    foreach (['weapons', 'armor', 'gear'] as $category) {
      uasort($catalog[$category], static function (array $a, array $b): int {
        return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
      });
      $catalog[$category] = array_values($catalog[$category]);
    }

    return $catalog;
  }

  /**
   * Normalizes stored registry tags into a plain string list.
   */
  private function normalizeTags(string $raw_tags): array {
    $decoded = json_decode($raw_tags, TRUE);
    if (is_array($decoded)) {
      return array_values(array_filter(array_map(static fn($tag): string => (string) $tag, $decoded)));
    }

    return [];
  }

  /**
   * Maps template item metadata to step-7 equipment categories.
   */
  private function mapTemplateItemCategory(string $item_type, array $tags): string {
    $normalized_type = strtolower($item_type);
    $normalized_tags = array_map('strtolower', $tags);

    if ($normalized_type === 'weapon' || in_array('weapon', $normalized_tags, TRUE)) {
      return 'weapons';
    }

    if ($normalized_type === 'armor' || in_array('armor', $normalized_tags, TRUE) || in_array('shield', $normalized_tags, TRUE)) {
      return 'armor';
    }

    return 'gear';
  }

}
