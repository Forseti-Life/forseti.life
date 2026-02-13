<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Campaign creation form.
 */
class CampaignCreateForm extends FormBase {

  protected Connection $database;
  protected UuidInterface $uuid;
  protected TimeInterface $time;
  protected AccountProxyInterface $currentUser;
  protected SchemaLoader $schemaLoader;

  public function __construct(
    Connection $database,
    UuidInterface $uuid,
    TimeInterface $time,
    AccountProxyInterface $current_user,
    SchemaLoader $schema_loader
  ) {
    $this->database = $database;
    $this->uuid = $uuid;
    $this->time = $time;
    $this->currentUser = $current_user;
    $this->schemaLoader = $schema_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('uuid'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('dungeoncrawler_content.schema_loader'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dungeoncrawler_campaign_create_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'dc-character-form';

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Campaign Name'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $this->t('New Campaign'),
      '#attributes' => ['placeholder' => $this->t('Enter your campaign name...')],
    ];

    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#required' => TRUE,
      '#options' => [
        'classic_dungeon' => $this->t('Classic Dungeon'),
        'goblin_warrens' => $this->t('Goblin Warrens'),
        'undead_crypt' => $this->t('Undead Crypt'),
      ],
      '#default_value' => 'classic_dungeon',
    ];

    $form['difficulty'] = [
      '#type' => 'select',
      '#title' => $this->t('Difficulty'),
      '#required' => TRUE,
      '#options' => [
        'normal' => $this->t('Normal'),
        'hard' => $this->t('Hard'),
        'extreme' => $this->t('Extreme'),
      ],
      '#default_value' => 'normal',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Campaign'),
      '#attributes' => ['class' => ['dc-btn', 'dc-btn-primary']],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('dungeoncrawler_content.campaigns'),
      '#attributes' => ['class' => ['dc-btn', 'dc-btn-secondary']],
    ];

    $form['#attached']['library'][] = 'dungeoncrawler_content/character-sheet';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $payload = $this->buildCampaignPayload();
    $validation = $this->schemaLoader->validateCampaignData($payload);

    if (!$validation['valid']) {
      $form_state->setErrorByName('name', $this->t('Campaign schema validation failed: @errors', [
        '@errors' => implode(' ', $validation['errors']),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $now = $this->time->getRequestTime();
    $payload = $this->buildCampaignPayload();

    $campaign_id = $this->database->insert('dc_campaigns')
      ->fields([
        'uuid' => $this->uuid->generate(),
        'uid' => (int) $this->currentUser->id(),
        'name' => (string) $form_state->getValue('name'),
        'status' => 'draft',
        'theme' => (string) $form_state->getValue('theme'),
        'difficulty' => (string) $form_state->getValue('difficulty'),
        'campaign_data' => json_encode($payload, JSON_PRETTY_PRINT),
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Campaign created. Choose a character at the tavern entrance to launch your adventure.'));

    $form_state->setRedirect('dungeoncrawler_content.campaign_tavernentrance', [
      'campaign_id' => $campaign_id,
    ]);
  }

  /**
   * Build canonical campaign payload for campaign_data.
   */
  private function buildCampaignPayload(): array {
    return [
      'schema_version' => '1.0.0',
      'created_by' => (int) $this->currentUser->id(),
      'started' => FALSE,
      'progress' => [],
    ];
  }

}
