<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\dungeoncrawler_content\Form\GeminiImageGenerationStubForm;
use Drupal\dungeoncrawler_content\Service\GeminiImageGenerationService;
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
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Gemini integration service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\GeminiImageGenerationService
   */
  protected $geminiImageService;

  /**
   * Constructs a DashboardController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\dungeoncrawler_content\Service\GeminiImageGenerationService $gemini_image_service
   *   Gemini integration service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder, GeminiImageGenerationService $gemini_image_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->geminiImageService = $gemini_image_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('dungeoncrawler_content.gemini_image_generator'),
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

    $gemini_status = $this->geminiImageService->getIntegrationStatus();
    $mode_label = ($gemini_status['enabled'] && $gemini_status['has_api_key']) ? 'live-ready' : 'stub';

    $build['gemini_image_generation'] = [
      '#type' => 'details',
      '#title' => $this->t('🖼️ Gemini Image Generation (Stub)'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['game-content-dashboard']],
      'overview' => [
        '#markup' => '<p>' . $this->t('Use this panel to stage prompt payloads for Google Gemini image generation. This is currently a non-production stub that validates inputs and logs a structured request without calling an external API.') . '</p>',
      ],
      'status' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Provider target: Gemini image generation API'),
          $this->t('Current mode: @mode', ['@mode' => $mode_label]),
          $this->t('Live mode enabled: @enabled', ['@enabled' => $gemini_status['enabled'] ? 'yes' : 'no']),
          $this->t('API key source: @source', ['@source' => (string) $gemini_status['api_key_source']]),
          $this->t('Model: @model', ['@model' => (string) $gemini_status['model']]),
          $this->t('Integration output: request ID + normalized payload preview'),
        ],
      ],
      'setup_help_title' => [
        '#markup' => '<h4>' . $this->t('Server Environment Setup') . '</h4>',
      ],
      'setup_help_text' => [
        '#markup' => '<p>' . $this->t('Set GEMINI_API_KEY as an environment variable for the web server user, then reload Apache and rebuild cache.') . '</p>',
      ],
      'setup_help_commands' => [
        '#markup' => '<pre>sudo tee -a /etc/apache2/envvars >/dev/null &lt;&lt;\'EOF\'
export GEMINI_API_KEY="YOUR_GEMINI_API_KEY"
EOF

sudo systemctl reload apache2
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler
./vendor/bin/drush cr</pre>',
      ],
      'form' => $this->formBuilder->getForm(GeminiImageGenerationStubForm::class),
    ];

    return $build;
  }

}
