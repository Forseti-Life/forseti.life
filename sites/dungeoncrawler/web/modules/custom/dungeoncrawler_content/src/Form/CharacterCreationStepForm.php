<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple form for character creation steps.
 */
class CharacterCreationStepForm extends FormBase {

  /**
   * The character manager service.
   */
  protected CharacterManager $characterManager;

  /**
   * The schema loader service.
   */
  protected SchemaLoader $schemaLoader;

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * The UUID service.
   */
  protected UuidInterface $uuid;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The date formatter service.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * Constructs a CharacterCreationStepForm object.
   */
  public function __construct(
    CharacterManager $character_manager,
    SchemaLoader $schema_loader,
    Connection $database,
    UuidInterface $uuid,
    AccountProxyInterface $current_user,
    DateFormatterInterface $date_formatter,
    TimeInterface $time
  ) {
    $this->characterManager = $character_manager;
    $this->schemaLoader = $schema_loader;
    $this->database = $database;
    $this->uuid = $uuid;
    $this->currentUser = $current_user;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.schema_loader'),
      $container->get('database'),
      $container->get('uuid'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'character_creation_step_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $step = 1, $character_id = NULL, $campaign_id = NULL) {
    $character_data = $this->loadCharacterData($character_id);
    
    // Store metadata
    $form_state->set('step', $step);
    $form_state->set('character_id', $character_id);
    $form_state->set('campaign_id', $campaign_id);

    // Load schema for tips and descriptions
    $schema = $this->schemaLoader->loadStepSchema($step);
    $step_name = $schema['properties']['step_name']['const'] ?? "Step {$step}";
    $step_description = $schema['properties']['step_description']['const'] ?? '';

    $form['#attributes']['class'][] = 'character-creation-form';
    $form['#attached']['library'][] = 'dungeoncrawler_content/character-creation';
    $form['#attached']['library'][] = "dungeoncrawler_content/character-step-{$step}";
    $form['#prefix'] = '<div class="character-creation-step"><div class="creation-container"><div class="progress-bar"><div class="progress-indicator progress-step-' . $step . '"></div></div><div class="progress-text">' . $this->t('Step @step of @total', ['@step' => $step, '@total' => 8]) . '</div><div class="step-content">';
    $form['#suffix'] = '</div></div></div>';

    $form['header'] = [
      '#markup' => "<h2>{$step_name}</h2><p class=\"step-description\">{$step_description}</p>",
    ];

    // Build step-specific fields
    $this->buildStepFields($form, $form_state, $step, $character_data);
    $this->applyInputStylingClasses($form);

    // Navigation buttons
    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['button-group']],
    ];
    
    if ($step > 1) {
      $back_query = ['character_id' => $character_id];
      if ($campaign_id) {
        $back_query['campaign_id'] = $campaign_id;
      }

      $form['actions']['back'] = [
        '#type' => 'link',
        '#title' => $this->t('← Back'),
        '#url' => Url::fromRoute('dungeoncrawler_content.character_step', [
          'step' => $this->getPreviousStep((int) $step),
        ])->setOption('query', $back_query),
        '#attributes' => ['class' => ['btn', 'btn-secondary']],
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $step < 8 ? $this->t('Next →') : $this->t('Create Character'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];

    return $form;
  }

  /**
   * Applies shared styling classes to standard form controls.
   *
   * @param array $elements
   *   Form elements array.
   */
  private function applyInputStylingClasses(array &$elements): void {
    $input_types = ['textfield', 'textarea', 'select', 'number'];

    foreach ($elements as &$element) {
      if (!is_array($element)) {
        continue;
      }

      if (isset($element['#type']) && in_array($element['#type'], $input_types, TRUE)) {
        $element['#wrapper_attributes']['class'][] = 'form-group';
        $element['#attributes']['class'][] = 'form-control';
      }

      $this->applyInputStylingClasses($element);
    }
  }

  /**
   * Builds step-specific form fields.
   *
   * @param array $form
   *   The form array to add fields to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param int $step
   *   The current step number (1-8).
   * @param array $character_data
   *   The character data for default values.
   */
  private function buildStepFields(&$form, FormStateInterface $form_state, $step, $character_data) {
    switch ($step) {
      case 1:
        $form['name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Character Name'),
          '#required' => TRUE,
          '#default_value' => $character_data['name'] ?? '',
          '#maxlength' => 50,
        ];
        $form['concept'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Character Concept'),
          '#default_value' => $character_data['concept'] ?? '',
          '#rows' => 4,
          '#description' => $this->t('Optional: Describe your character in one sentence.'),
        ];
        break;

      case 2:
        $form['ancestry'] = [
          '#type' => 'select',
          '#title' => $this->t('Ancestry'),
          '#required' => TRUE,
          '#options' => $this->getAncestryOptions(),
          '#default_value' => $character_data['ancestry'] ?? '',
          '#ajax' => [
            'callback' => '::updateHeritageOptions',
            'wrapper' => 'heritage-wrapper',
          ],
        ];
        $form['heritage'] = [
          '#type' => 'select',
          '#title' => $this->t('Heritage'),
          '#required' => FALSE,
          '#options' => $this->getHeritageOptions($form_state->getValue('ancestry') ?: $character_data['ancestry'] ?? ''),
          '#default_value' => $character_data['heritage'] ?? '',
          '#prefix' => '<div id="heritage-wrapper">',
          '#suffix' => '</div>',
        ];
        break;

      case 3:
        $form['background'] = [
          '#type' => 'select',
          '#title' => $this->t('Background'),
          '#required' => TRUE,
          '#options' => $this->getBackgroundOptions(),
          '#default_value' => $character_data['background'] ?? '',
        ];
        break;

      case 4:
        $form['class'] = [
          '#type' => 'select',
          '#title' => $this->t('Class'),
          '#required' => TRUE,
          '#options' => $this->getClassOptions(),
          '#default_value' => $character_data['class'] ?? '',
        ];
        break;

      case 5:
        $abilities = $this->calculateAbilitiesFromSelections($character_data);
        $ability_labels = [
          'strength' => $this->t('Strength'),
          'dexterity' => $this->t('Dexterity'),
          'constitution' => $this->t('Constitution'),
          'intelligence' => $this->t('Intelligence'),
          'wisdom' => $this->t('Wisdom'),
          'charisma' => $this->t('Charisma'),
        ];

        $cards = '';
        foreach ($ability_labels as $ability_key => $label) {
          $score = (int) ($abilities[$ability_key] ?? 10);
          $modifier = (int) floor(($score - 10) / 2);
          $modifier_text = $modifier >= 0 ? '+' . $modifier : (string) $modifier;
          $cards .= '<div class="ability-card selected">'
            . '<h3>' . $label . '</h3>'
            . '<div class="boost-counter"><span class="boost-count">' . $score . '</span> <span>(' . $modifier_text . ')</span></div>'
            . '</div>';
        }

        $form['abilities_help'] = [
          '#markup' => '<p class="section-instructions">'
            . $this->t('Ability scores are auto-calculated from your previous choices and can’t be edited on this step.')
            . '</p>',
        ];

        $form['abilities_readonly'] = [
          '#markup' => '<div class="abilities-grid">' . $cards . '</div>',
        ];
        break;

      case 6:
        $form['alignment'] = [
          '#type' => 'select',
          '#title' => $this->t('Alignment'),
          '#required' => TRUE,
          '#options' => $this->getAlignmentOptions(),
          '#default_value' => $character_data['alignment'] ?? '',
        ];
        $form['deity'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Deity (Optional)'),
          '#default_value' => $character_data['deity'] ?? '',
        ];
        break;

      case 7:
        $catalog = $this->getEquipmentCatalog();
        $catalog_by_id = [];
        $options = [];

        foreach ($catalog as $category => $items) {
          foreach ($items as $item) {
            $catalog_by_id[$item['id']] = $item;
            $options[$item['id']] = $item['name'] . ' (' . (float) $item['cost'] . ' gp)';
          }
        }

        $selected_ids = [];
        foreach (($character_data['equipment'] ?? []) as $selected_item) {
          if (!empty($selected_item['id'])) {
            $selected_ids[] = $selected_item['id'];
          }
        }

        $selected_cost = 0.0;
        foreach ($selected_ids as $item_id) {
          if (isset($catalog_by_id[$item_id])) {
            $selected_cost += (float) $catalog_by_id[$item_id]['cost'];
          }
        }

        $remaining_gold = max(0, 15 - $selected_cost);

        $form['starting_gold'] = [
          '#markup' => '<div class="gold-display"><span class="gold-icon">🪙</span>'
            . $this->t('Starting Gold: @gold gp', ['@gold' => 15])
            . '<br><small>'
            . $this->t('Currently selected cost: @cost gp • Remaining: @remaining gp', [
              '@cost' => number_format($selected_cost, 1),
              '@remaining' => number_format($remaining_gold, 1),
            ])
            . '</small></div>',
        ];

        $form['equipment'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Select Equipment'),
          '#options' => $options,
          '#default_value' => $selected_ids,
          '#description' => $this->t('Choose any items up to 15 gp total.'),
        ];

        $form['equipment_help'] = [
          '#markup' => '<p class="section-instructions">'
            . $this->t('Your selections are saved to your character and can be managed later through inventory APIs.')
            . '</p>',
        ];
        break;

      case 8:
        $form['age'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Age'),
          '#default_value' => $character_data['age'] ?? '',
          '#maxlength' => 10,
        ];
        $form['gender'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Gender'),
          '#default_value' => $character_data['gender'] ?? '',
          '#maxlength' => 50,
        ];
        $form['appearance'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Appearance'),
          '#default_value' => $character_data['appearance'] ?? '',
          '#rows' => 3,
        ];
        $form['personality'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Personality'),
          '#default_value' => $character_data['personality'] ?? '',
          '#rows' => 3,
        ];
        $form['backstory'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Backstory'),
          '#default_value' => $character_data['backstory'] ?? '',
          '#rows' => 5,
        ];
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $step = (int) ($form_state->get('step') ?? 1);

    if ($step === 7) {
      $selected = array_filter((array) $form_state->getValue('equipment', []));
      $catalog = $this->getEquipmentCatalog();
      $catalog_by_id = [];

      foreach ($catalog as $items) {
        foreach ($items as $item) {
          $catalog_by_id[$item['id']] = $item;
        }
      }

      $total_cost = 0.0;
      foreach ($selected as $item_id) {
        if (isset($catalog_by_id[$item_id])) {
          $total_cost += (float) $catalog_by_id[$item_id]['cost'];
        }
      }

      if ($total_cost > 15) {
        $form_state->setErrorByName('equipment', $this->t('Selected equipment costs @total gp, which exceeds your 15 gp starting budget.', ['@total' => number_format($total_cost, 1)]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');
    $character_id = $form_state->get('character_id');
    $campaign_id = $form_state->get('campaign_id');
    $character_data = $this->loadCharacterData($character_id);

    // Update character data with form values
    foreach ($form_state->getValues() as $key => $value) {
      if (!in_array($key, ['form_build_id', 'form_token', 'form_id', 'op'])) {
        $character_data[$key] = $value;
      }
    }

    // Step 5 is now read-only and ability scores are derived from prior choices.
    if ($step <= 5) {
      $calculated_abilities = $this->calculateAbilitiesFromSelections($character_data);
      foreach ($calculated_abilities as $ability => $score) {
        $character_data[$ability] = $score;
      }
    }

    if ((int) $step === 7) {
      $selected_ids = array_filter((array) $form_state->getValue('equipment', []));
      $catalog = $this->getEquipmentCatalog();
      $catalog_by_id = [];

      foreach ($catalog as $items) {
        foreach ($items as $item) {
          $catalog_by_id[$item['id']] = $item;
        }
      }

      $selected_items = [];
      $total_cost = 0.0;
      foreach ($selected_ids as $item_id) {
        if (isset($catalog_by_id[$item_id])) {
          $selected_items[] = $catalog_by_id[$item_id];
          $total_cost += (float) $catalog_by_id[$item_id]['cost'];
        }
      }

      $character_data['equipment'] = $selected_items;
      $character_data['gold'] = max(0, 15 - $total_cost);
    }

    $next_step = $this->getNextStep((int) $step);
    $character_data['step'] = $next_step;

    // Save to database
    $character_id = $this->saveCharacter($character_id, $character_data);

    // Redirect to next step or character view
    if ($step >= 8) {
      $final_options = [];
      if ($campaign_id) {
        $final_options['query'] = ['campaign_id' => $campaign_id];
      }
      $form_state->setRedirect('dungeoncrawler_content.character_view', ['character_id' => $character_id], $final_options);
    } else {
      $next_query = ['character_id' => $character_id];
      if ($campaign_id) {
        $next_query['campaign_id'] = $campaign_id;
      }

      $form_state->setRedirect('dungeoncrawler_content.character_step', [
        'step' => $next_step,
      ], ['query' => $next_query]);
    }
  }

  /**
   * Gets the next step in the wizard flow.
   */
  private function getNextStep(int $step): int {
    if ($step === 4 || $step === 5) {
      return 6;
    }

    return min(8, $step + 1);
  }

  /**
   * Gets the previous step in the wizard flow.
   */
  private function getPreviousStep(int $step): int {
    if ($step === 6 || $step === 5) {
      return 4;
    }

    return max(1, $step - 1);
  }

  /**
   * Calculates step-5 ability scores from prior character choices.
   */
  private function calculateAbilitiesFromSelections(array $character_data): array {
    $abilities = [
      'strength' => 10,
      'dexterity' => 10,
      'constitution' => 10,
      'intelligence' => 10,
      'wisdom' => 10,
      'charisma' => 10,
    ];

    if (!empty($character_data['ancestry'])) {
      $ancestry = NULL;
      $selected_ancestry = strtolower((string) $character_data['ancestry']);
      foreach (CharacterManager::ANCESTRIES as $ancestry_name => $ancestry_data) {
        $ancestry_id = strtolower(str_replace(' ', '-', $ancestry_name));
        if ($ancestry_id === $selected_ancestry) {
          $ancestry = $ancestry_data;
          break;
        }
      }
      if ($ancestry) {
        foreach (($ancestry['boosts'] ?? []) as $boost) {
          $key = strtolower((string) $boost);
          if (isset($abilities[$key])) {
            $abilities[$key] = $this->applyAbilityBoost((int) $abilities[$key]);
          }
        }

        $flaw_key = strtolower((string) ($ancestry['flaw'] ?? ''));
        if (isset($abilities[$flaw_key])) {
          $abilities[$flaw_key] = max(8, (int) $abilities[$flaw_key] - 2);
        }
      }
    }

    if (!empty($character_data['class'])) {
      $class = CharacterManager::CLASSES[(string) $character_data['class']] ?? NULL;
      if ($class && !empty($class['key_ability'])) {
        $key_ability = strtolower((string) $class['key_ability']);
        $primary = trim(explode(' or ', $key_ability)[0]);
        if (isset($abilities[$primary])) {
          $abilities[$primary] = $this->applyAbilityBoost((int) $abilities[$primary]);
        }
      }
    }

    return $abilities;
  }

  /**
   * Applies PF2e ability boost rules.
   */
  private function applyAbilityBoost(int $score): int {
    return $score < 18 ? $score + 2 : $score + 1;
  }

  /**
   * Gets equipment catalog options for step 7.
   */
  private function getEquipmentCatalog(): array {
    return [
      'weapons' => [
        ['id' => 'longsword', 'name' => 'Longsword', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'damage' => '1d8 S', 'hands' => 1],
        ['id' => 'shortsword', 'name' => 'Shortsword', 'type' => 'weapon', 'cost' => 0.9, 'bulk' => 'L', 'damage' => '1d6 P', 'hands' => 1],
        ['id' => 'dagger', 'name' => 'Dagger', 'type' => 'weapon', 'cost' => 0.2, 'bulk' => 'L', 'damage' => '1d4 P', 'hands' => 1],
        ['id' => 'staff', 'name' => 'Staff', 'type' => 'weapon', 'cost' => 0.0, 'bulk' => 1, 'damage' => '1d4 B', 'hands' => 2],
      ],
      'armor' => [
        ['id' => 'leather', 'name' => 'Leather Armor', 'type' => 'armor', 'cost' => 2.0, 'bulk' => 1],
        ['id' => 'chain-shirt', 'name' => 'Chain Shirt', 'type' => 'armor', 'cost' => 5.0, 'bulk' => 1],
      ],
      'gear' => [
        ['id' => 'backpack', 'name' => 'Backpack', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L'],
        ['id' => 'bedroll', 'name' => 'Bedroll', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L'],
        ['id' => 'rope', 'name' => 'Rope (50 ft.)', 'type' => 'adventuring_gear', 'cost' => 0.5, 'bulk' => 'L'],
      ],
    ];
  }

  /**
   * Loads character data from database.
   *
   * @param int|null $character_id
   *   The character ID to load.
   *
   * @return array
   *   Character data array with defaults.
   */
  private function loadCharacterData($character_id) {
    if ($character_id) {
      $character = $this->characterManager->loadCharacter($character_id);
      if ($character && $character->uid == $this->currentUser->id()) {
        $data = json_decode($character->character_data, TRUE);
        // Support both old flat structure and new nested abilities structure
        if (!empty($data['abilities'])) {
          // New format - flatten for form
          $data['strength'] = $data['abilities']['str'] ?? 10;
          $data['dexterity'] = $data['abilities']['dex'] ?? 10;
          $data['constitution'] = $data['abilities']['con'] ?? 10;
          $data['intelligence'] = $data['abilities']['int'] ?? 10;
          $data['wisdom'] = $data['abilities']['wis'] ?? 10;
          $data['charisma'] = $data['abilities']['cha'] ?? 10;
        }
        return $data;
      }
    }
    return [
      'step' => 1,
      'name' => '',
      'concept' => '',
      'level' => 1,
      'experience_points' => 0,
      'ancestry' => '',
      'heritage' => '',
      'background' => '',
      'class' => '',
      'strength' => 10,
      'dexterity' => 10,
      'constitution' => 10,
      'intelligence' => 10,
      'wisdom' => 10,
      'charisma' => 10,
      'alignment' => '',
      'deity' => '',
      'age' => '',
      'gender' => '',
      'appearance' => '',
      'personality' => '',
      'backstory' => '',
      'gold' => 15,
      'hero_points' => 1,
    ];
  }

  /**
   * Saves character data to database.
   *
   * @param int|null $character_id
   *   The character ID to update, or NULL to create new.
   * @param array $character_data
   *   Character data array to save.
   *
   * @return int
   *   The character ID.
   */
  private function saveCharacter($character_id, $character_data) {
    $now = $this->time->getRequestTime();

    // Restructure data to match schema
    $schema_data = $character_data;

    // Convert flat ability scores to nested structure
    $schema_data['abilities'] = [
      'str' => (int) ($character_data['strength'] ?? 10),
      'dex' => (int) ($character_data['dexterity'] ?? 10),
      'con' => (int) ($character_data['constitution'] ?? 10),
      'int' => (int) ($character_data['intelligence'] ?? 10),
      'wis' => (int) ($character_data['wisdom'] ?? 10),
      'cha' => (int) ($character_data['charisma'] ?? 10),
    ];

    // Remove flat ability scores from root
    unset($schema_data['strength'], $schema_data['dexterity'], $schema_data['constitution']);
    unset($schema_data['intelligence'], $schema_data['wisdom'], $schema_data['charisma']);

    // Auto-populate ancestry-derived fields
    if (!empty($schema_data['ancestry'])) {
      $ancestry_data = CharacterManager::ANCESTRIES[$schema_data['ancestry']] ?? NULL;
      if ($ancestry_data) {
        $schema_data['size'] = $ancestry_data['size'];
        $schema_data['speed'] = $ancestry_data['speed'];
        if (empty($schema_data['languages'])) {
          $schema_data['languages'] = $ancestry_data['languages'];
        }
      }
    }

    // Calculate max HP
    $level = $schema_data['level'] ?? 1;
    $con_mod = floor(($schema_data['abilities']['con'] - 10) / 2);
    $ancestry_hp = 0;
    if (!empty($schema_data['ancestry'])) {
      $ancestry_data = CharacterManager::ANCESTRIES[$schema_data['ancestry']] ?? NULL;
      if ($ancestry_data) {
        $ancestry_hp = $ancestry_data['hp'];
      }
    }
    $class_hp = 8; // Default fallback
    if (!empty($schema_data['class'])) {
      $class_hp = $this->characterManager->getClassHP((string) $schema_data['class']);
    }
    $max_hp = $ancestry_hp + $class_hp + $con_mod + (($level - 1) * ($class_hp + $con_mod));

    // Structure hit_points
    $schema_data['hit_points'] = [
      'max' => $max_hp,
      'current' => $schema_data['hit_points']['current'] ?? $max_hp,
      'temp' => $schema_data['hit_points']['temp'] ?? 0,
    ];

    // Ensure required schema fields exist
    $schema_data['level'] = $level;
    $schema_data['experience_points'] = (int) ($schema_data['experience_points'] ?? 0);
    $schema_data['gold'] = (float) ($schema_data['gold'] ?? 15);
    $schema_data['hero_points'] = (int) ($schema_data['hero_points'] ?? 1);
    $schema_data['equipment'] = $schema_data['equipment'] ?? [];
    $schema_data['skills'] = $schema_data['skills'] ?? [];
    $schema_data['feats'] = $schema_data['feats'] ?? [];
    $schema_data['conditions'] = $schema_data['conditions'] ?? [];

    // Timestamps
    if (!isset($schema_data['created_at'])) {
      $schema_data['created_at'] = date('c', $now);
    }
    $schema_data['updated_at'] = date('c', $now);

    if ($character_id) {
      $this->database->update('dc_characters')
        ->fields([
          'name' => $schema_data['name'] ?: 'Unnamed Character',
          'level' => $schema_data['level'],
          'ancestry' => $schema_data['ancestry'] ?? '',
          'class' => $schema_data['class'] ?? '',
          'character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
          'status' => $schema_data['step'] >= 8 ? 1 : 0,
          'changed' => $now,
        ])
        ->condition('id', $character_id)
        ->execute();
      return $character_id;
    }
    else {
      return $this->database->insert('dc_characters')
        ->fields([
          'uuid' => $this->uuid->generate(),
          'uid' => (int) $this->currentUser->id(),
          'name' => $schema_data['name'] ?: 'Unnamed Character',
          'level' => $schema_data['level'],
          'ancestry' => $schema_data['ancestry'] ?? '',
          'class' => $schema_data['class'] ?? '',
          'character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
          'status' => 0,
          'created' => $now,
          'changed' => $now,
        ])
        ->execute();
    }
  }

  /**
   * Gets ancestry dropdown options.
   *
   * @return array
   *   Associative array of ancestry options.
   */
  private function getAncestryOptions() {
    $options = ['' => $this->t('- Select -')];
    foreach (CharacterManager::ANCESTRIES as $name => $data) {
      $options[strtolower(str_replace(' ', '-', $name))] = $name;
    }
    return $options;
  }

  /**
   * Gets heritage options filtered by ancestry.
   *
   * @param string $ancestry
   *   The ancestry key to filter heritages by.
   *
   * @return array
   *   Associative array of heritage options.
   */
  private function getHeritageOptions($ancestry) {
    $options = ['' => $this->t('- Select -')];
    if ($ancestry) {
      $ancestry_name = str_replace('-', ' ', ucwords($ancestry, '-'));
      $heritages = CharacterManager::HERITAGES[$ancestry_name] ?? [];
      foreach ($heritages as $heritage) {
        $options[$heritage['id']] = $heritage['name'];
      }
    }
    return $options;
  }

  /**
   * Gets background dropdown options.
   *
   * @return array
   *   Associative array of background options.
   */
  private function getBackgroundOptions() {
    $options = ['' => $this->t('- Select -')];
    foreach (CharacterManager::BACKGROUNDS as $bg) {
      $options[$bg['id']] = $bg['name'];
    }
    return $options;
  }

  /**
   * Gets class dropdown options.
   *
   * @return array
   *   Associative array of class options.
   */
  private function getClassOptions() {
    $options = ['' => $this->t('- Select -')];
    foreach (CharacterManager::CLASSES as $class) {
      $options[$class['id']] = $class['name'];
    }
    return $options;
  }

  /**
   * Gets alignment dropdown options.
   *
   * @return array
   *   Associative array of alignment options.
   */
  private function getAlignmentOptions() {
    return [
      '' => $this->t('- Select -'),
      'LG' => $this->t('Lawful Good'),
      'NG' => $this->t('Neutral Good'),
      'CG' => $this->t('Chaotic Good'),
      'LN' => $this->t('Lawful Neutral'),
      'N' => $this->t('Neutral'),
      'CN' => $this->t('Chaotic Neutral'),
      'LE' => $this->t('Lawful Evil'),
      'NE' => $this->t('Neutral Evil'),
      'CE' => $this->t('Chaotic Evil'),
    ];
  }

  /**
   * AJAX callback to update heritage dropdown when ancestry changes.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The heritage form element to replace.
   */
  public function updateHeritageOptions(array &$form, FormStateInterface $form_state) {
    return $form['heritage'];
  }

}
