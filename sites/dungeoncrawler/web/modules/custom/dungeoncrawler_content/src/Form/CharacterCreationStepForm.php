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
use Drupal\Component\Utility\Html;
use Drupal\dungeoncrawler_content\Service\AbilityScoreTracker;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
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
   * Character portrait generator.
   */
  protected CharacterPortraitGenerationService $portraitGenerator;

  /**
   * Ability score tracker service.
   */
  protected AbilityScoreTracker $abilityScoreTracker;

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
    TimeInterface $time,
    CharacterPortraitGenerationService $portrait_generator,
    AbilityScoreTracker $ability_score_tracker
  ) {
    $this->characterManager = $character_manager;
    $this->schemaLoader = $schema_loader;
    $this->database = $database;
    $this->uuid = $uuid;
    $this->currentUser = $current_user;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
    $this->portraitGenerator = $portrait_generator;
    $this->abilityScoreTracker = $ability_score_tracker;
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
      $container->get('datetime.time'),
      $container->get('dungeoncrawler_content.character_portrait_generator'),
      $container->get('dungeoncrawler_content.ability_score_tracker')
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

    if ($campaign_id) {
      $form['campaign_id'] = [
        '#type' => 'hidden',
        '#value' => $campaign_id,
      ];
    }

    // Load schema for tips and descriptions
    $schema = $this->schemaLoader->loadStepSchema($step);
    $step_name = $schema['properties']['step_name']['const']
      ?? $schema['properties']['step_name']['default']
      ?? "Step {$step}";
    $step_description = $schema['properties']['step_description']['const']
      ?? $schema['properties']['step_description']['default']
      ?? '';

    $form['#attributes']['class'][] = 'character-creation-form';
    $form['#attached']['library'][] = 'dungeoncrawler_content/character-creation';
    $form['#attached']['library'][] = "dungeoncrawler_content/character-step-{$step}";
    
    // Attach ability boost selector for interactive steps (3, 4, 5)
    if (in_array($step, [3, 4, 5], TRUE)) {
      $form['#attached']['library'][] = 'dungeoncrawler_content/ability-boost-selector';
    }
    
    $form['#prefix'] = '<div class="character-creation-step"><div class="creation-container"><div class="progress-bar"><div class="progress-indicator progress-step-' . $step . '"></div></div><div class="progress-text">' . $this->t('Step @step of @total', ['@step' => $step, '@total' => 8]) . '</div><div class="step-content">';
    $form['#suffix'] = '</div></div></div>';

    $form['header'] = [
      '#markup' => "<h2>{$step_name}</h2><p class=\"step-description\">{$step_description}</p>",
    ];

    $tips_items = $this->extractStepTips($schema);
    if (!empty($tips_items)) {
      $form['tips'] = [
        '#type' => 'details',
        '#title' => $this->t('Legacy Player Tips'),
        '#open' => FALSE,
        '#attributes' => ['class' => ['tips-section']],
      ];
      $form['tips']['list'] = [
        '#theme' => 'item_list',
        '#items' => $tips_items,
        '#attributes' => ['class' => ['tips-list']],
      ];
    }

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
      '#value' => $step < 8 ? $this->t('Next →') : $this->t('Create Legacy Character'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];

    return $form;
  }

  /**
   * Extracts step tips from schema in either string or object format.
   *
   * @param array $schema
   *   Loaded step schema array.
   *
   * @return array
   *   Renderable tip strings.
   */
  private function extractStepTips(array $schema): array {
    $raw_tips = $schema['properties']['tips']['default'] ?? NULL;
    if (!is_array($raw_tips)) {
      return [];
    }

    $tips = [];
    foreach ($raw_tips as $tip) {
      if (is_string($tip) && trim($tip) !== '') {
        $tips[] = Html::escape($tip);
      }
      elseif (is_array($tip)) {
        $title = trim((string) ($tip['title'] ?? ''));
        $text = trim((string) ($tip['text'] ?? ''));
        if ($title !== '' && $text !== '') {
          $tips[] = Html::escape($title . ': ' . $text);
        }
        elseif ($text !== '') {
          $tips[] = Html::escape($text);
        }
      }
    }

    return $tips;
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
   * Apply HTML5 validation attributes from schema definitions.
   *
   * @param array $element
   *   The form element to update.
   * @param array $schema_fields
   *   Schema field definitions for the current step.
   * @param string $field_name
   *   Field name to look up in schema.
   */
  private function applySchemaValidationAttributes(array &$element, array $schema_fields, string $field_name): void {
    $field_schema = $schema_fields[$field_name]['properties'] ?? [];
    if ($field_schema === []) {
      return;
    }

    $validation = $field_schema['validation']['properties'] ?? [];
    $required = $field_schema['required']['const'] ?? NULL;

    if ($required !== NULL && !isset($element['#required'])) {
      $element['#required'] = (bool) $required;
    }

    if (!isset($element['#attributes'])) {
      $element['#attributes'] = [];
    }

    $min_length = $this->getSchemaConstraintValue($validation['min_length'] ?? NULL);
    if ($min_length !== NULL) {
      $element['#attributes']['minlength'] = (int) $min_length;
    }

    $max_length = $this->getSchemaConstraintValue($validation['max_length'] ?? NULL);
    if ($max_length !== NULL) {
      $element['#maxlength'] = $element['#maxlength'] ?? (int) $max_length;
      $element['#attributes']['maxlength'] = (int) $max_length;
    }

    $pattern = $this->getSchemaConstraintValue($validation['pattern'] ?? NULL);
    if ($pattern !== NULL) {
      $element['#attributes']['pattern'] = $pattern;
    }
  }

  /**
   * Read a constraint value from a schema node.
   *
   * @param array|null $constraint
   *   Schema node containing const/default values.
   *
   * @return mixed|null
   *   Constraint value, or NULL when absent.
   */
  private function getSchemaConstraintValue(?array $constraint) {
    if (!is_array($constraint)) {
      return NULL;
    }

    return $constraint['const'] ?? $constraint['default'] ?? NULL;
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
    $schema_fields = $this->schemaLoader->getStepFields((int) $step);

    switch ($step) {
      case 1:
        // Ability preview at top of form
        $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
        $abilities_preview = [];
        foreach ($calculation['scores'] as $ability_key => $score) {
          $abilities_preview[$ability_key] = [
            'score' => $score,
            'modifier' => $calculation['modifiers'][$ability_key],
            'sources' => $calculation['sources'][$ability_key] ?? [],
          ];
        }
        
        $form['ability_preview'] = [
          '#theme' => 'character_ability_widget',
          '#abilities' => $abilities_preview,
          '#mode' => 'compact',
          '#show_sources' => FALSE,
          '#help_text' => $this->t('Your ability scores (will update as you progress)'),
        ];

        $form['name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Legacy Character Name'),
          '#required' => TRUE,
          '#default_value' => $character_data['name'] ?? '',
          '#maxlength' => 50,
          '#placeholder' => $this->t('The name your roster will remember'),
          '#description' => $this->t('Your character\'s name will appear in all campaign records and legacy logs.'),
        ];
        $this->applySchemaValidationAttributes($form['name'], $schema_fields, 'name');
        $form['concept'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Character Concept'),
          '#default_value' => $character_data['concept'] ?? '',
          '#rows' => 4,
          '#placeholder' => $this->t('e.g., "Fortune-favored rogue seeking redemption", "Dwarf paladin defending the old ways"'),
          '#description' => $this->t('Optional: Capture your character\'s long-term identity and campaign arc. Think in terms of a character you\'ll want to revisit across many expeditions.'),
        ];
        $this->applySchemaValidationAttributes($form['concept'], $schema_fields, 'concept');
        break;

      case 2:
        // Ability preview showing ancestry boosts/flaws
        $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
        $abilities_preview = [];
        foreach ($calculation['scores'] as $ability_key => $score) {
          $abilities_preview[$ability_key] = [
            'score' => $score,
            'modifier' => $calculation['modifiers'][$ability_key],
            'sources' => $calculation['sources'][$ability_key] ?? [],
          ];
        }
        
        $form['ability_preview'] = [
          '#theme' => 'character_ability_widget',
          '#abilities' => $abilities_preview,
          '#mode' => 'compact',
          '#show_sources' => TRUE,
          '#help_text' => $this->t('Current ability scores (from ancestry)'),
        ];

        $heritage_payload = [];
        foreach (CharacterManager::HERITAGES as $ancestry_name => $heritages) {
          $ancestry_id = strtolower(str_replace(' ', '-', $ancestry_name));
          $heritage_payload[$ancestry_id] = $heritages;
        }

        $heritage_json = Html::escape(json_encode(
          $heritage_payload,
          JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ));

        $selected_ancestry = (string) ($character_data['ancestry'] ?? '');
        $ancestry_cards_markup = '<div class="ancestry-selection" data-heritages="' . $heritage_json . '">';
        $ancestry_cards_markup .= '<div class="ancestry-grid">';

        foreach (CharacterManager::ANCESTRIES as $ancestry_name => $ancestry_data) {
          $ancestry_id = strtolower(str_replace(' ', '-', $ancestry_name));
          $selected_class = $selected_ancestry === $ancestry_id ? ' selected' : '';
          $boosts = $ancestry_data['boosts'] ?? [];
          $boosts_label = $boosts ? implode(', ', $boosts) : 'None';
          $flaw = $ancestry_data['flaw'] ?? '';
          $vision = $ancestry_data['vision'] ?? 'normal';

          $ancestry_cards_markup .= '<div class="ancestry-card' . $selected_class . '" data-ancestry="' . Html::escape($ancestry_id) . '">';
          $ancestry_cards_markup .= '<h3>' . Html::escape($ancestry_name) . '</h3>';
          $ancestry_cards_markup .= '<div class="ancestry-stats">';
          $ancestry_cards_markup .= '<span class="stat"><strong>HP:</strong> ' . (int) ($ancestry_data['hp'] ?? 0) . '</span>';
          $ancestry_cards_markup .= '<span class="stat"><strong>Size:</strong> ' . Html::escape((string) ($ancestry_data['size'] ?? '')) . '</span>';
          $ancestry_cards_markup .= '<span class="stat"><strong>Speed:</strong> ' . (int) ($ancestry_data['speed'] ?? 0) . 'ft</span>';
          $ancestry_cards_markup .= '</div>';
          $ancestry_cards_markup .= '<div class="ancestry-traits">';
          $ancestry_cards_markup .= '<span><strong>Boosts:</strong> ' . Html::escape($boosts_label) . '</span>';
          if ($flaw !== '') {
            $ancestry_cards_markup .= '<span><strong>Flaw:</strong> ' . Html::escape($flaw) . '</span>';
          }
          $ancestry_cards_markup .= '<span><strong>Vision:</strong> ' . Html::escape($vision) . '</span>';
          $ancestry_cards_markup .= '</div>';
          $ancestry_cards_markup .= '</div>';
        }

        $ancestry_cards_markup .= '</div>';
        $ancestry_cards_markup .= '<div id="heritageSelection" class="heritage-section hidden">';
        $ancestry_cards_markup .= '<h3>' . $this->t('Choose a Heritage') . '</h3>';
        $ancestry_cards_markup .= '<div id="heritageOptions" class="heritage-grid"></div>';
        $ancestry_cards_markup .= '</div>';
        $ancestry_cards_markup .= '</div>';

        $form['ancestry_cards'] = [
          '#type' => 'markup',
          '#markup' => $ancestry_cards_markup,
        ];

        $form['ancestry'] = [
          '#type' => 'select',
          '#title' => $this->t('Legacy Ancestry'),
          '#required' => TRUE,
          '#options' => $this->getAncestryOptions(),
          '#default_value' => $character_data['ancestry'] ?? '',
          '#description' => $this->t('Your character\'s ancestral blood will determine size, speed, special senses, and long-term physical identity across all campaigns.'),
          '#ajax' => [
            'callback' => '::updateHeritageOptions',
            'wrapper' => 'heritage-wrapper',
          ],
        ];
        $form['ancestry']['#attributes']['class'][] = 'dc-visually-hidden';
        $this->applySchemaValidationAttributes($form['ancestry'], $schema_fields, 'ancestry');
        $form['heritage'] = [
          '#type' => 'select',
          '#title' => $this->t('Heritage Path'),
          '#required' => FALSE,
          '#options' => $this->getHeritageOptions($form_state->getValue('ancestry') ?: $character_data['ancestry'] ?? ''),
          '#default_value' => $character_data['heritage'] ?? '',
          '#description' => $this->t('Optional: Select a heritage to specialize your ancestry with unique talents and abilities that define your legacy.'),
          '#prefix' => '<div id="heritage-wrapper">',
          '#suffix' => '</div>',
        ];
        $form['heritage']['#attributes']['class'][] = 'dc-visually-hidden';
        $this->applySchemaValidationAttributes($form['heritage'], $schema_fields, 'heritage');

        // Ancestry Feat Selection
        $selected_ancestry = $form_state->getValue('ancestry') ?: $character_data['ancestry'] ?? '';
        if (!empty($selected_ancestry)) {
          $ancestry_feats = CharacterManager::ANCESTRY_FEATS[$selected_ancestry] ?? [];
          
          if (!empty($ancestry_feats)) {
            $form['ancestry_feat_section'] = [
              '#markup' => '<div class="section-instructions ancestry-feat-section">'
                . '<h3>' . $this->t('Ancestry Feat') . '</h3>'
                . '<p>' . $this->t('Choose one 1st-level ancestry feat. This represents a special ability or training unique to your ancestry.') . '</p>'
                . '</div>',
            ];

            $feat_options = [];
            $feat_descriptions = [];
            
            foreach ($ancestry_feats as $feat) {
              $feat_options[$feat['id']] = $feat['name'];
              $prereq_text = !empty($feat['prerequisites']) ? ' <em>(Requires: ' . $feat['prerequisites'] . ')</em>' : '';
              $feat_descriptions[$feat['id']] = [
                '#markup' => '<div class="feat-description">'
                  . '<strong>' . $feat['name'] . '</strong>' . $prereq_text . '<br>'
                  . $feat['benefit']
                  . '</div>',
              ];
            }

            $form['ancestry_feat'] = [
              '#type' => 'radios',
              '#title' => $this->t('Select Ancestry Feat'),
              '#options' => $feat_options,
              '#default_value' => $character_data['ancestry_feat'] ?? '',
              '#required' => TRUE,
              '#description' => $this->t('Each feat provides unique mechanical benefits that reflect your ancestry\'s culture and abilities.'),
            ];

            // Add detailed descriptions for each feat
            foreach ($feat_descriptions as $feat_id => $description_markup) {
              $form['ancestry_feat_desc_' . $feat_id] = $description_markup;
              $form['ancestry_feat_desc_' . $feat_id]['#states'] = [
                'visible' => [
                  ':input[name="ancestry_feat"]' => ['value' => $feat_id],
                ],
              ];
            }
          }
        }
        break;

      case 3:
        $form['background'] = [
          '#type' => 'select',
          '#title' => $this->t('Pre-Campaign Background'),
          '#required' => TRUE,
          '#options' => $this->getBackgroundOptions(),
          '#default_value' => $character_data['background'] ?? '',
          '#description' => $this->t('Your character\'s former life shaped who they are. This choice grants lasting skills and a foundation for long-term roleplay consistency.'),
        ];

        // Background Ability Boosts (2 free boosts)
        // Calculate current scores from ancestry only
        $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
        
        $abilities_data = [];
        foreach ($calculation['scores'] as $ability_key => $score) {
          $abilities_data[$ability_key] = [
            'score' => $score,
            'modifier' => $calculation['modifiers'][$ability_key],
            'sources' => $calculation['sources'][$ability_key] ?? [],
            'selected' => in_array($ability_key, $character_data['background_boosts'] ?? [], TRUE),
            'disabled' => FALSE,
          ];
        }

        $form['background_boosts_help'] = [
          '#markup' => '<div class="section-instructions background-boosts-section">'
            . '<h3>' . $this->t('Background Ability Boosts') . '</h3>'
            . '<p>' . $this->t('Your background grants 2 free ability boosts. Choose any two different abilities to boost.') . '</p>'
            . '</div>',
        ];

        $form['background_boosts_selector'] = [
          '#theme' => 'character_ability_widget',
          '#abilities' => $abilities_data,
          '#mode' => 'interactive',
          '#show_sources' => TRUE,
          '#boosts_remaining' => 2 - count($character_data['background_boosts'] ?? []),
          '#boosts_total' => 2,
          '#attributes' => [
            'data-step' => 'background',
            'data-max-boosts' => 2,
            'data-character-data' => json_encode($character_data),
          ],
        ];

        $form['background_boosts'] = [
          '#type' => 'hidden',
          '#default_value' => json_encode($character_data['background_boosts'] ?? []),
          '#attributes' => ['id' => 'background-boosts-field'],
        ];

        // Background Skill Training
        $selected_background = $form_state->getValue('background') ?: $character_data['background'] ?? '';
        if (!empty($selected_background)) {
          $background_data = CharacterManager::BACKGROUNDS[$selected_background] ?? NULL;
          
          if ($background_data) {
            $form['background_skills_section'] = [
              '#markup' => '<div class="section-instructions background-skills-section">'
                . '<h3>' . $this->t('Background Skills') . '</h3>'
                . '<p>' . $this->t('Your background grants training in a specific skill and lore, plus a skill feat.') . '</p>'
                . '</div>',
            ];

            $form['background_skill'] = [
              '#markup' => '<div class="background-benefit">'
                . '<p><strong>' . $this->t('Skill Training:') . '</strong> ' . ($background_data['skill'] ?? 'Varies') . '</p>'
                . '<p><strong>' . $this->t('Lore Skill:') . '</strong> ' . ($background_data['lore'] ?? 'Varies') . '</p>'
                . '<p><strong>' . $this->t('Skill Feat:') . '</strong> ' . ($background_data['feat'] ?? 'Varies') . '</p>'
                . '<p class="help-text">' . $this->t('These will be automatically applied to your character.') . '</p>'
                . '</div>',
            ];

            // For backgrounds with skill choices (like Scholar), add selector
            if ($selected_background === 'scholar') {
              $form['scholar_skill_choice'] = [
                '#type' => 'radios',
                '#title' => $this->t('Choose Primary Skill'),
                '#options' => [
                  'Arcana' => 'Arcana (magic and spells)',
                  'Nature' => 'Nature (wilderness and animals)',
                  'Occultism' => 'Occultism (mysteries and spirits)',
                  'Religion' => 'Religion (gods and divine power)',
                ],
                '#default_value' => $character_data['scholar_skill_choice'] ?? 'Arcana',
                '#required' => FALSE,
                '#description' => $this->t('Scholars can specialize in one of these knowledge domains.'),
              ];
            }
          }
        }
        break;

      case 4:
        $form['class'] = [
          '#type' => 'select',
          '#title' => $this->t('Class Role'),
          '#required' => TRUE,
          '#options' => $this->getClassOptions(),
          '#default_value' => $character_data['class'] ?? '',
          '#description' => $this->t('Choose how your character will contribute to the party across many campaigns. Consider what role you\'ll enjoy playing across dozens of sessions.'),
        ];

        // Key Ability Selection (if class has multiple options)
        if (!empty($character_data['class'])) {
          $class_data = CharacterManager::CLASSES[$character_data['class']] ?? NULL;
          if ($class_data) {
            $key_ability_raw = $class_data['key_ability'] ?? '';
            $key_options = array_map('trim', explode(' or ', strtolower($key_ability_raw)));

            // If class has choice of key abilities, show selector
            if (count($key_options) > 1) {
              $form['class_key_ability_help'] = [
                '#markup' => '<div class="section-instructions class-key-ability-section">'
                  . '<h3>' . $this->t('Choose Key Ability') . '</h3>'
                  . '<p>' . $this->t('Your class allows a choice of key ability. This determines which ability receives a boost from your class.') . '</p>'
                  . '</div>',
              ];

              $key_ability_options = [];
              foreach ($key_options as $option) {
                $normalized = $this->abilityScoreTracker->normalizeAbilityKey($option);
                if ($normalized) {
                  $key_ability_options[$normalized] = ucfirst($normalized);
                }
              }

              $form['class_key_ability'] = [
                '#type' => 'radios',
                '#title' => $this->t('Select Key Ability'),
                '#options' => $key_ability_options,
                '#default_value' => $character_data['class_key_ability'] ?? '',
                '#required' => TRUE,
                '#description' => $this->t('This ability will receive a +2 boost and is the primary ability for your class features.'),
              ];
            }
            else {
              // Fixed key ability - show as read-only
              $key_ability = $this->abilityScoreTracker->normalizeAbilityKey($key_options[0]);
              $form['class_key_ability_readonly'] = [
                '#markup' => '<div class="class-info">'
                  . '<p><strong>' . $this->t('Key Ability:') . '</strong> ' . ucfirst($key_ability ?? 'Unknown') . ' ' . $this->t('(automatically applied)') . '</p>'
                  . '</div>',
              ];
            }

            // Class Feat Selection
            $selected_class = $character_data['class'];
            $class_feats = CharacterManager::CLASS_FEATS[$selected_class] ?? [];
            
            if (!empty($class_feats)) {
              $form['class_feat_section'] = [
                '#markup' => '<div class="section-instructions class-feat-section">'
                  . '<h3>' . $this->t('Class Feat') . '</h3>'
                  . '<p>' . $this->t('Choose one 1st-level class feat. This represents specialized training or a unique technique for your class.') . '</p>'
                  . '</div>',
              ];

              $feat_options = [];
              $feat_descriptions = [];
              
              foreach ($class_feats as $feat) {
                $feat_options[$feat['id']] = $feat['name'];
                $prereq_text = !empty($feat['prerequisites']) ? ' <em>(Requires: ' . $feat['prerequisites'] . ')</em>' : '';
                $traits_text = !empty($feat['traits']) ? ' [' . implode(', ', $feat['traits']) . ']' : '';
                $feat_descriptions[$feat['id']] = [
                  '#markup' => '<div class="feat-description">'
                    . '<strong>' . $feat['name'] . '</strong>' . $traits_text . $prereq_text . '<br>'
                    . $feat['benefit']
                    . '</div>',
                ];
              }

              $form['class_feat'] = [
                '#type' => 'radios',
                '#title' => $this->t('Select Class Feat'),
                '#options' => $feat_options,
                '#default_value' => $character_data['class_feat'] ?? '',
                '#required' => TRUE,
                '#description' => $this->t('Each feat provides unique tactical options that define your combat style.'),
              ];

              // Add detailed descriptions for each feat
              foreach ($feat_descriptions as $feat_id => $description_markup) {
                $form['class_feat_desc_' . $feat_id] = $description_markup;
                $form['class_feat_desc_' . $feat_id]['#states'] = [
                  'visible' => [
                    ':input[name="class_feat"]' => ['value' => $feat_id],
                  ],
                ];
              }
            }

            // Spell Selection for Spellcasting Classes
            if ($selected_class === 'wizard') {
              $form['spells_section'] = [
                '#markup' => '<div class="section-instructions spells-section">'
                  . '<h3>' . $this->t('Spells') . '</h3>'
                  . '<p>' . $this->t('As a Wizard, you begin with knowledge of arcane magic. Choose your starting cantrips and spells for your spellbook.') . '</p>'
                  . '</div>',
              ];

              // Cantrip Selection (5 cantrips for Wizard at level 1)
              $cantrips = CharacterManager::SPELLS['arcane']['cantrips'] ?? [];
              $cantrip_options = [];
              
              foreach ($cantrips as $cantrip) {
                $traits_text = !empty($cantrip['traits']) ? ' [' . implode(', ', $cantrip['traits']) . ']' : '';
                $cantrip_options[$cantrip['id']] = $cantrip['name'] . $traits_text . ' - ' . $cantrip['description'];
              }

              $form['cantrips_help'] = [
                '#markup' => '<div class="spell-help"><strong>' . $this->t('Cantrips (Select 5)') . '</strong><br>'
                  . $this->t('Cantrips are spells you can cast at will. They heighten automatically to half your level. You should have a mix of offensive and utility cantrips.') 
                  . '</div>',
              ];

              $form['cantrips'] = [
                '#type' => 'checkboxes',
                '#title' => $this->t('Choose 5 Cantrips'),
                '#options' => $cantrip_options,
                '#default_value' => $character_data['cantrips'] ?? [],
                '#required' => FALSE,
                '#description' => $this->t('Popular choices: Shield (defense), Electric Arc (damage), Detect Magic (utility), Prestidigitation (flexibility)'),
              ];

              // 1st Level Spell Selection (10 spells in spellbook for Wizard)
              $first_level_spells = CharacterManager::SPELLS['arcane']['1st'] ?? [];
              $spell_options = [];
              
              foreach ($first_level_spells as $spell) {
                $traits_text = !empty($spell['traits']) ? ' [' . implode(', ', $spell['traits']) . ']' : '';
                $spell_options[$spell['id']] = $spell['name'] . $traits_text . ' - ' . $spell['description'];
              }

              $form['spells_help'] = [
                '#markup' => '<div class="spell-help"><strong>' . $this->t('1st Level Spells (Select up to 10)') . '</strong><br>'
                  . $this->t('These spells are added to your spellbook. You can prepare 4 spells per day at level 1 (2 from class, +2 from INT modifier if you have 14+ INT). Choose versatile spells.') 
                  . '</div>',
              ];

              $form['spells_first'] = [
                '#type' => 'checkboxes',
                '#title' => $this->t('Choose up to 10 First Level Spells'),
                '#options' => $spell_options,
                '#default_value' => $character_data['spells_first'] ?? [],
                '#required' => FALSE,
                '#description' => $this->t('Popular choices: Magic Missile (always hits), Mage Armor (AC), Sleep (crowd control), True Strike (accuracy)'),
              ];
            }
          }
        }
        break;

      case 5:
        // Step 5: Free Ability Boosts (Pathbuilder-style interactive selection)
        // Calculate current scores from ancestry + background + class
        $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
        
        // Prepare ability data for interactive widget
        $abilities_data = [];
        foreach ($calculation['scores'] as $ability_key => $score) {
          $abilities_data[$ability_key] = [
            'score' => $score,
            'modifier' => $calculation['modifiers'][$ability_key],
            'sources' => $calculation['sources'][$ability_key] ?? [],
            'selected' => in_array($ability_key, $character_data['free_boosts'] ?? [], TRUE),
            'disabled' => FALSE,
          ];
        }

        $form['abilities_help'] = [
          '#markup' => '<div class="section-instructions">'
            . '<p><strong>' . $this->t('Choose 4 abilities to boost') . '</strong></p>'
            . '<p>' . $this->t('You have 4 free ability boosts to assign. Each boost adds +2 to an ability score (or +1 if the score is already 18 or higher). You cannot boost the same ability twice in this step.') . '</p>'
            . '<p class="tip">' . $this->t('💡 Tip: Consider boosting your class\'s key ability and abilities that complement your playstyle. Most characters benefit from having at least one high ability score (16-18).') . '</p>'
            . '</div>',
        ];

        // Render interactive ability widget using Twig template
        $form['ability_selector'] = [
          '#theme' => 'character_ability_widget',
          '#abilities' => $abilities_data,
          '#mode' => 'interactive',
          '#show_sources' => TRUE,
          '#boosts_remaining' => 4 - count($character_data['free_boosts'] ?? []),
          '#boosts_total' => 4,
          '#attributes' => [
            'data-step' => 'free',
            'data-max-boosts' => 4,
            'data-character-data' => json_encode($character_data),
          ],
        ];

        // Hidden field to store selected boosts
        $form['free_boosts'] = [
          '#type' => 'hidden',
          '#default_value' => json_encode($character_data['free_boosts'] ?? []),
          '#attributes' => ['id' => 'free-boosts-field'],
        ];
        break;

      case 6:
        // Ability preview showing final scores
        $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
        $abilities_preview = [];
        foreach ($calculation['scores'] as $ability_key => $score) {
          $abilities_preview[$ability_key] = [
            'score' => $score,
            'modifier' => $calculation['modifiers'][$ability_key],
            'sources' => $calculation['sources'][$ability_key] ?? [],
          ];
        }
        
        $form['ability_preview'] = [
          '#theme' => 'character_ability_widget',
          '#abilities' => $abilities_preview,
          '#mode' => 'compact',
          '#show_sources' => TRUE,
          '#help_text' => $this->t('Final ability scores'),
        ];

        // Skill Training Selection
        $selected_class = $character_data['class'] ?? '';
        if (!empty($selected_class)) {
          $class_data = CharacterManager::CLASSES[$selected_class] ?? NULL;
          if ($class_data) {
            $trained_skills = $class_data['trained_skills'] ?? 3;
            
            // Calculate Intelligence modifier for bonus skills
            $int_modifier = $calculation['modifiers']['intelligence'] ?? 0;
            $total_skill_picks = max(1, $trained_skills + $int_modifier);

            $form['skills_section'] = [
              '#markup' => '<div class="section-instructions skills-section">'
                . '<h3>' . $this->t('Skill Training') . '</h3>'
                . '<p>' . $this->t('Choose @count skills to be trained in.', ['@count' => $total_skill_picks])
                . ' <em>' . $this->t('(@base from class + @bonus from Intelligence)', ['@base' => $trained_skills, '@bonus' => $int_modifier]) . '</em></p>'
                . '<p class="help-text">' . $this->t('Being trained in a skill gives you a +2 proficiency bonus. Choose skills that complement your class and planned activities.') . '</p>'
                . '</div>',
            ];

            $all_skills = [
              'Acrobatics' => 'Acrobatics - Balance, tumble, maneuver while flying',
              'Arcana' => 'Arcana - Recall knowledge about arcane magic, traditions, creatures',
              'Athletics' => 'Athletics - Climb, force open, grapple, swim',
              'Crafting' => 'Crafting - Repair items, identify alchemical objects, craft goods',
              'Deception' => 'Deception - Create a diversion, feint, lie, impersonate',
              'Diplomacy' => 'Diplomacy - Gather information, make an impression, request',
              'Intimidation' => 'Intimidation - Coerce, demoralize',
              'Medicine' => 'Medicine - Administer first aid, treat disease, treat poison',
              'Nature' => 'Nature - Command an animal, recall knowledge about natural creatures',
              'Occultism' => 'Occultism - Recall knowledge about occult topics, creatures',
              'Performance' => 'Performance - Act, dance, play instrument, give speech',
              'Religion' => 'Religion - Recall knowledge about divine topics, creatures',
              'Society' => 'Society - Recall knowledge about society, civilization, history',
              'Stealth' => 'Stealth - Conceal an object, hide, sneak',
              'Survival' => 'Survival - Cover tracks, sense direction, subsist, track',
              'Thievery' => 'Thievery - Palm an object, disable a device, pick a lock',
            ];

            $form['trained_skills'] = [
              '#type' => 'checkboxes',
              '#title' => $this->t('Select Skills'),
              '#options' => $all_skills,
              '#default_value' => $character_data['trained_skills'] ?? [],
              '#description' => $this->t('Select exactly @count skill(s). You can gain additional skills from feats and ancestry features.', ['@count' => $total_skill_picks]),
              '#required' => FALSE,
            ];
          }
        }

        $form['alignment'] = [
          '#type' => 'select',
          '#title' => $this->t('Legacy Alignment'),
          '#required' => TRUE,
          '#options' => $this->getAlignmentOptions(),
          '#default_value' => $character_data['alignment'] ?? '',
          '#description' => $this->t('This character\'s moral and ethical compass will guide roleplay decisions across the entire span of their campaign life.'),
        ];
        $this->applySchemaValidationAttributes($form['alignment'], $schema_fields, 'alignment');
        $form['deity'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Deity or Guiding Belief (Optional)'),
          '#default_value' => $character_data['deity'] ?? '',
          '#placeholder' => $this->t('e.g., Iomedae, The Old Gods, Ancestor Oath, Unaligned'),
          '#description' => $this->t('Optional: A spiritual patron or philosophy that will anchor your character\'s identity and roleplay flavor across all campaigns.'),
        ];
        $this->applySchemaValidationAttributes($form['deity'], $schema_fields, 'deity');
        break;

      case 7:
        // Ability preview for reference during equipment selection
        $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
        $abilities_preview = [];
        foreach ($calculation['scores'] as $ability_key => $score) {
          $abilities_preview[$ability_key] = [
            'score' => $score,
            'modifier' => $calculation['modifiers'][$ability_key],
            'sources' => $calculation['sources'][$ability_key] ?? [],
          ];
        }
        
        $form['ability_preview'] = [
          '#theme' => 'character_ability_widget',
          '#abilities' => $abilities_preview,
          '#mode' => 'compact',
          '#show_sources' => FALSE,
          '#help_text' => $this->t('Your character\'s abilities'),
        ];

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

        $form['equipment_intro'] = [
          '#markup' => '<div class="section-instructions equipment-intro">'
            . '<h3>' . $this->t('Starting Equipment') . '</h3>'
            . '<p>' . $this->t('Assemble your starting loadout with up to 15 gp. Choose wisely - these items will be your tools for survival in early adventures.') . '</p>'
            . '</div>',
        ];

        $form['starting_gold'] = [
          '#markup' => '<div class="gold-display" style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; border-radius: 8px; margin: 20px 0;">'
            . '<div style="font-size: 24px; font-weight: bold; color: #856404;">'
            . '<span class="gold-icon" style="font-size: 32px;">🪙</span> '
            . $this->t('Budget: @gold gp', ['@gold' => 15])
            . '</div>'
            . '<div style="font-size: 16px; margin-top: 10px; color: #856404;">'
            . $this->t('Spent: <strong>@cost gp</strong> • Remaining: <strong style="color: @color;">@remaining gp</strong>', [
              '@cost' => number_format($selected_cost, 1),
              '@remaining' => number_format($remaining_gold, 1),
              '@color' => $remaining_gold > 0 ? '#28a745' : '#dc3545',
            ])
            . '</div>'
            . '</div>',
        ];

        // Organize equipment by category
        $form['equipment_weapons'] = [
          '#type' => 'details',
          '#title' => $this->t('⚔️ Weapons'),
          '#open' => TRUE,
        ];

        $form['equipment_armor'] = [
          '#type' => 'details',
          '#title' => $this->t('🛡️ Armor & Shields'),
          '#open' => TRUE,
        ];

        $form['equipment_gear'] = [
          '#type' => 'details',
          '#title' => $this->t('🎒 Adventuring Gear'),
          '#open' => TRUE,
        ];

        // Build categorized options
        $weapons_options = [];
        $armor_options = [];
        $gear_options = [];

        foreach ($catalog as $category => $items) {
          foreach ($items as $item) {
            $catalog_by_id[$item['id']] = $item;
            $item_label = $item['name'] . ' (' . (float) $item['cost'] . ' gp)';
            
            // Add extra info for weapons and armor
            if ($category === 'weapons' && !empty($item['damage'])) {
              $item_label .= ' - ' . $item['damage'] . ' damage';
            }
            elseif ($category === 'armor' && !empty($item['ac'])) {
              $item_label .= ' - AC ' . $item['ac'];
            }

            if ($category === 'weapons') {
              $weapons_options[$item['id']] = $item_label;
            }
            elseif ($category === 'armor') {
              $armor_options[$item['id']] = $item_label;
            }
            else {
              $gear_options[$item['id']] = $item_label;
            }
          }
        }

        $form['equipment_weapons']['weapons'] = [
          '#type' => 'checkboxes',
          '#options' => $weapons_options,
          '#default_value' => array_filter($selected_ids, fn($id) => isset($catalog['weapons']) && in_array($id, array_column($catalog['weapons'], 'id'))),
          '#description' => $this->t('Select weapons for combat. Consider your class proficiencies.'),
        ];

        $form['equipment_armor']['armor'] = [
          '#type' => 'checkboxes',
          '#options' => $armor_options,
          '#default_value' => array_filter($selected_ids, fn($id) => isset($catalog['armor']) && in_array($id, array_column($catalog['armor'], 'id'))),
          '#description' => $this->t('Choose armor and shields for protection. Heavy armor may slow you down.'),
        ];

        $form['equipment_gear']['gear'] = [
          '#type' => 'checkboxes',
          '#options' => $gear_options,
          '#default_value' => array_filter($selected_ids, fn($id) => isset($catalog['gear']) && in_array($id, array_column($catalog['gear'], 'id'))),
          '#description' => $this->t('Essential adventuring supplies: rope, torches, rations, and tools.'),
        ];

        // Keep old equipment field for backwards compatibility
        $form['equipment'] = [
          '#type' => 'hidden',
          '#default_value' => json_encode($selected_ids),
        ];

        $form['equipment_help'] = [
          '#markup' => '<div class="equipment-tips" style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0;">'
            . '<h4 style="margin-top: 0; color: #1976D2;">💡 ' . $this->t('Equipment Tips') . '</h4>'
            . '<ul style="margin-bottom: 0;">'
            . '<li>' . $this->t('<strong>Weapons:</strong> Choose at least one weapon your class is proficient with.') . '</li>'
            . '<li>' . $this->t('<strong>Armor:</strong> Wizards and sorcerers typically wear no armor. Fighters can wear heavy armor.') . '</li>'
            . '<li>' . $this->t('<strong>Essentials:</strong> Don\'t forget rope, torches, and a backpack!') . '</li>'
            . '<li>' . $this->t('<strong>Gold:</strong> Unspent gold carries over to your starting funds.') . '</li>'
            . '</ul>'
            . '</div>',
        ];
        break;

      case 8:
        // Final ability scores summary
        $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
        $abilities_preview = [];
        foreach ($calculation['scores'] as $ability_key => $score) {
          $abilities_preview[$ability_key] = [
            'score' => $score,
            'modifier' => $calculation['modifiers'][$ability_key],
            'sources' => $calculation['sources'][$ability_key] ?? [],
          ];
        }
        
        $form['ability_preview'] = [
          '#theme' => 'character_ability_widget',
          '#abilities' => $abilities_preview,
          '#mode' => 'compact',
          '#show_sources' => TRUE,
          '#help_text' => $this->t('Final ability scores - Review your character'),
        ];

        $form['portrait_generation'] = [
          '#type' => 'details',
          '#title' => $this->t('Portrait Generation'),
          '#open' => TRUE,
        ];
        $form['portrait_generation']['portrait_generate'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Generate a character portrait'),
          '#default_value' => (int) ($character_data['portrait_generate'] ?? 1),
          '#parents' => ['portrait_generate'],
          '#description' => $this->t('Creates a portrait using the configured AI image provider after character creation.'),
        ];
        $form['portrait_generation']['portrait_prompt'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Portrait prompt (optional)'),
          '#default_value' => $character_data['portrait_prompt'] ?? '',
          '#rows' => 3,
          '#maxlength' => 500,
          '#parents' => ['portrait_prompt'],
          '#description' => $this->t('Add extra visual direction. Character attributes will be injected automatically.'),
        ];
        $form['age'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Age / Life Stage'),
          '#default_value' => $character_data['age'] ?? '',
          '#maxlength' => 10,
          '#placeholder' => $this->t('e.g., 28, middle-aged, elderly'),
          '#description' => $this->t('Optional: Your character\'s age or life stage informs their experience and how they might view future growth and eventual retirement.'),
        ];
        $form['gender'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Gender / Pronouns'),
          '#default_value' => $character_data['gender'] ?? '',
          '#maxlength' => 50,
          '#placeholder' => $this->t('e.g., she/her, he/him, they/them'),
          '#description' => $this->t('Optional: How you present your character at the table. Respected by all players for long-term roleplay and respect.'),
        ];
        $form['appearance'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Appearance & Presence'),
          '#default_value' => $character_data['appearance'] ?? '',
          '#rows' => 3,
          '#placeholder' => $this->t('What distinguishing features, scars, or style will make this character memorable?'),
          '#description' => $this->t('Tell the table what they see: build, distinctive features, clothing style. This is what other players will picture across every campaign session.'),
        ];
        $form['personality'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Personality & Table Voice'),
          '#default_value' => $character_data['personality'] ?? '',
          '#rows' => 3,
          '#placeholder' => $this->t('How does this character speak and act? What are their quirks, habits, and mannerisms?'),
          '#description' => $this->t('Define the emotional tone and voice you\'ll bring to roleplay. Think about personality traits you can embody consistently over many sessions.'),
        ];
        $form['backstory'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Backstory & Legacy Goal'),
          '#default_value' => $character_data['backstory'] ?? '',
          '#rows' => 5,
          '#placeholder' => $this->t('Where did this character come from? What drives them? What is their ultimate goal (which could be a noble end like retirement or legendary status)?'),
          '#description' => $this->t('Frame your character\'s story with an arc in mind: how they begin, what motivates them, and an end goal they might work toward across years of campaigning. This becomes your character\'s lasting legacy.'),
        ];
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $step = (int) ($form_state->get('step') ?? 1);

    // Validate background boosts (Step 3)
    if ($step === 3) {
      $background_boosts_raw = $form_state->getValue('background_boosts', '[]');
      $background_boosts = json_decode($background_boosts_raw, TRUE);
      
      if (!is_array($background_boosts) || count($background_boosts) < 2) {
        $form_state->setErrorByName('background_boosts', $this->t('You must select 2 ability boosts from your background.'));
      }
      elseif (count($background_boosts) > 2) {
        $form_state->setErrorByName('background_boosts', $this->t('You can only select 2 ability boosts from your background.'));
      }
      elseif (count($background_boosts) !== count(array_unique($background_boosts))) {
        $form_state->setErrorByName('background_boosts', $this->t('You cannot boost the same ability twice.'));
      }
    }

    // Validate spell selection for spellcasting classes (Step 4)
    if ($step === 4) {
      $character_data = $this->loadCharacterData($form_state->get('character_id'));
      $selected_class = $character_data['class'] ?? $form_state->getValue('class');
      
      // Wizard spell validation
      if ($selected_class === 'wizard') {
        // Validate cantrip selection (exactly 5 required)
        $cantrips = array_filter((array) $form_state->getValue('cantrips', []));
        if (count($cantrips) < 5) {
          $form_state->setErrorByName('cantrips', $this->t('You must select exactly 5 cantrips. Currently selected: @count', ['@count' => count($cantrips)]));
        }
        elseif (count($cantrips) > 5) {
          $form_state->setErrorByName('cantrips', $this->t('You can only select 5 cantrips. Currently selected: @count', ['@count' => count($cantrips)]));
        }

        // Validate 1st level spells (minimum 4, maximum 10 recommended)
        $first_level = array_filter((array) $form_state->getValue('spells_first', []));
        if (count($first_level) < 4) {
          $form_state->setErrorByName('spells_first', $this->t('You should select at least 4 first-level spells for your spellbook. Currently selected: @count', ['@count' => count($first_level)]));
        }
        elseif (count($first_level) > 10) {
          $form_state->setErrorByName('spells_first', $this->t('You can select up to 10 first-level spells at character creation. Currently selected: @count', ['@count' => count($first_level)]));
        }
      }
    }

    // Validate free boosts (Step 5)
    if ($step === 5) {
      $free_boosts_raw = $form_state->getValue('free_boosts', '[]');
      $free_boosts = json_decode($free_boosts_raw, TRUE);
      
      if (!is_array($free_boosts) || count($free_boosts) < 4) {
        $form_state->setErrorByName('free_boosts', $this->t('You must select 4 free ability boosts.'));
      }
      elseif (count($free_boosts) > 4) {
        $form_state->setErrorByName('free_boosts', $this->t('You can only select 4 free ability boosts.'));
      }
      elseif (count($free_boosts) !== count(array_unique($free_boosts))) {
        $form_state->setErrorByName('free_boosts', $this->t('You cannot boost the same ability twice.'));
      }
    }

    // Validate equipment cost (Step 7)
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
        // Handle JSON-encoded hidden fields
        if (in_array($key, ['background_boosts', 'free_boosts'], TRUE) && is_string($value)) {
          $decoded = json_decode($value, TRUE);
          $character_data[$key] = is_array($decoded) ? $decoded : [];
        }
        else {
          $character_data[$key] = $value;
        }
      }
    }

    // After steps 3, 4, or 5: Recalculate ability scores using tracker service
    if (in_array($step, [3, 4, 5], TRUE)) {
      $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
      
      // Store final scores and sources
      foreach ($calculation['scores'] as $ability => $score) {
        $character_data[$ability] = $score;
      }
      
      // Store source attribution for transparency
      $character_data['ability_sources'] = $calculation['sources'];
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
      $this->portraitGenerator->generatePortrait(
        $character_data,
        (int) $character_id,
        (int) $this->currentUser->id(),
        $campaign_id !== NULL && $campaign_id !== '' ? (int) $campaign_id : NULL,
        [
          'generate' => $character_data['portrait_generate'] ?? NULL,
          'user_prompt' => $character_data['portrait_prompt'] ?? '',
        ]
      );
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
   * 
   * Returns equipment data conforming to character_options_step7.json schema.
   * All items include: id, name, cost, bulk, and category-specific fields.
   */
  private function getEquipmentCatalog(): array {
    $template_catalog = $this->buildEquipmentCatalogFromTemplates();
    if (!empty($template_catalog['weapons']) || !empty($template_catalog['armor']) || !empty($template_catalog['gear'])) {
      return $template_catalog;
    }

    return [
      'weapons' => [
        ['id' => 'longsword', 'name' => 'Longsword', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'damage' => '1d8 S', 'hands' => 1, 'traits' => ['versatile P']],
        ['id' => 'shortsword', 'name' => 'Shortsword', 'type' => 'weapon', 'cost' => 0.9, 'bulk' => 'L', 'damage' => '1d6 P', 'hands' => 1, 'traits' => ['agile', 'finesse', 'versatile S']],
        ['id' => 'dagger', 'name' => 'Dagger', 'type' => 'weapon', 'cost' => 0.2, 'bulk' => 'L', 'damage' => '1d4 P', 'hands' => 1, 'traits' => ['agile', 'finesse', 'thrown 10 ft.', 'versatile S']],
        ['id' => 'rapier', 'name' => 'Rapier', 'type' => 'weapon', 'cost' => 2.0, 'bulk' => 1, 'damage' => '1d6 P', 'hands' => 1, 'traits' => ['deadly d8', 'disarm', 'finesse']],
        ['id' => 'battleaxe', 'name' => 'Battle Axe', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'damage' => '1d8 S', 'hands' => 1, 'traits' => ['sweep']],
        ['id' => 'warhammer', 'name' => 'Warhammer', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'damage' => '1d8 B', 'hands' => 1, 'traits' => ['shove']],
        ['id' => 'shortbow', 'name' => 'Shortbow', 'type' => 'weapon', 'cost' => 3.0, 'bulk' => 1, 'damage' => '1d6 P', 'hands' => 2, 'traits' => ['deadly d10', 'range 60 ft.']],
        ['id' => 'longbow', 'name' => 'Longbow', 'type' => 'weapon', 'cost' => 6.0, 'bulk' => 2, 'damage' => '1d8 P', 'hands' => 2, 'traits' => ['deadly d10', 'range 100 ft.', 'volley 30 ft.']],
        ['id' => 'staff', 'name' => 'Staff', 'type' => 'weapon', 'cost' => 0.0, 'bulk' => 1, 'damage' => '1d4 B', 'hands' => 2, 'traits' => ['two-hand d8']],
      ],
      'armor' => [
        ['id' => 'leather', 'name' => 'Leather Armor', 'type' => 'armor', 'cost' => 2.0, 'bulk' => 1, 'ac' => '+1', 'traits' => []],
        ['id' => 'studded-leather', 'name' => 'Studded Leather Armor', 'type' => 'armor', 'cost' => 3.0, 'bulk' => 1, 'ac' => '+2', 'traits' => []],
        ['id' => 'chain-shirt', 'name' => 'Chain Shirt', 'type' => 'armor', 'cost' => 5.0, 'bulk' => 1, 'ac' => '+2', 'traits' => ['flexible', 'noisy']],
        ['id' => 'hide-armor', 'name' => 'Hide Armor', 'type' => 'armor', 'cost' => 2.0, 'bulk' => 2, 'ac' => '+3', 'traits' => []],
        ['id' => 'scale-mail', 'name' => 'Scale Mail', 'type' => 'armor', 'cost' => 4.0, 'bulk' => 2, 'ac' => '+3', 'traits' => []],
        ['id' => 'chain-mail', 'name' => 'Chain Mail', 'type' => 'armor', 'cost' => 6.0, 'bulk' => 2, 'ac' => '+4', 'traits' => ['flexible', 'noisy']],
        ['id' => 'breastplate', 'name' => 'Breastplate', 'type' => 'armor', 'cost' => 8.0, 'bulk' => 2, 'ac' => '+4', 'traits' => []],
        ['id' => 'shield', 'name' => 'Wooden Shield', 'type' => 'armor', 'cost' => 1.0, 'bulk' => 1, 'ac' => '+2 circumstance', 'traits' => []],
      ],
      'gear' => [
        ['id' => 'backpack', 'name' => 'Backpack', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
        ['id' => 'bedroll', 'name' => 'Bedroll', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
        ['id' => 'rope', 'name' => 'Rope (50 ft.)', 'type' => 'adventuring_gear', 'cost' => 0.5, 'bulk' => 'L', 'traits' => []],
        ['id' => 'torch-5', 'name' => 'Torches (5)', 'type' => 'adventuring_gear', 'cost' => 0.05, 'bulk' => 'L', 'traits' => []],
        ['id' => 'rations', 'name' => 'Rations (1 week)', 'type' => 'adventuring_gear', 'cost' => 0.4, 'bulk' => 'L', 'traits' => []],
        ['id' => 'waterskin', 'name' => 'Waterskin', 'type' => 'adventuring_gear', 'cost' => 0.05, 'bulk' => 'L', 'traits' => []],
        ['id' => 'healers-kit', 'name' => "Healer's Tools", 'type' => 'adventuring_gear', 'cost' => 5.0, 'bulk' => 1, 'traits' => []],
        ['id' => 'thieves-tools', 'name' => "Thieves' Tools", 'type' => 'adventuring_gear', 'cost' => 3.0, 'bulk' => 'L', 'traits' => []],
        ['id' => 'grappling-hook', 'name' => 'Grappling Hook', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
        ['id' => 'lantern', 'name' => 'Hooded Lantern', 'type' => 'adventuring_gear', 'cost' => 0.7, 'bulk' => 'L', 'traits' => []],
        ['id' => 'oil-flask', 'name' => 'Oil (1 flask)', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
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
      'portrait_generate' => 1,
      'portrait_prompt' => '',
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

    $armor_class = 10 + floor(((int) $schema_data['abilities']['dex'] - 10) / 2);

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
      $this->database->update('dc_campaign_characters')
        ->fields([
          'name' => $schema_data['name'] ?: 'Unnamed Character',
          'level' => $schema_data['level'],
          'ancestry' => $schema_data['ancestry'] ?? '',
          'class' => $schema_data['class'] ?? '',
          'hp_current' => (int) ($schema_data['hit_points']['current'] ?? $max_hp),
          'hp_max' => (int) $max_hp,
          'armor_class' => (int) $armor_class,
          'experience_points' => (int) ($schema_data['experience_points'] ?? 0),
          'position_q' => (int) ($schema_data['position']['q'] ?? 0),
          'position_r' => (int) ($schema_data['position']['r'] ?? 0),
          'last_room_id' => (string) ($schema_data['position']['room_id'] ?? ''),
          'character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
          'status' => $schema_data['step'] >= 8 ? 1 : 0,
          'changed' => $now,
        ])
        ->condition('id', $character_id)
        ->execute();
      return $character_id;
    }
    else {
      $instance_id = $this->uuid->generate();
      return $this->database->insert('dc_campaign_characters')
        ->fields([
          'uuid' => $instance_id,
          'campaign_id' => 0,
          'character_id' => 0,
          'instance_id' => $instance_id,
          'uid' => (int) $this->currentUser->id(),
          'name' => $schema_data['name'] ?: 'Unnamed Character',
          'level' => $schema_data['level'],
          'ancestry' => $schema_data['ancestry'] ?? '',
          'class' => $schema_data['class'] ?? '',
          'hp_current' => (int) ($schema_data['hit_points']['current'] ?? $max_hp),
          'hp_max' => (int) $max_hp,
          'armor_class' => (int) $armor_class,
          'experience_points' => (int) ($schema_data['experience_points'] ?? 0),
          'position_q' => (int) ($schema_data['position']['q'] ?? 0),
          'position_r' => (int) ($schema_data['position']['r'] ?? 0),
          'last_room_id' => (string) ($schema_data['position']['room_id'] ?? ''),
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
