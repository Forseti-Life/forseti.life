<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirmation form for archiving a campaign.
 */
class CampaignArchiveForm extends ConfirmFormBase {

  protected Connection $database;
  protected TimeInterface $time;
  protected ?object $campaign = NULL;

  public function __construct(Connection $database, TimeInterface $time) {
    $this->database = $database;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dungeoncrawler_campaign_archive_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Archive %name?', [
      '%name' => $this->campaign->name ?? $this->t('this campaign'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Archiving hides this campaign from your /campaigns page without deleting it. You can still keep campaign data in the database.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Archive Campaign');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('dungeoncrawler_content.campaigns');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $campaign_id = NULL) {
    $this->campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'name', 'status', 'campaign_data'])
      ->condition('id', (int) $campaign_id)
      ->execute()
      ->fetchObject();

    if (!$this->campaign) {
      throw new NotFoundHttpException();
    }

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ((string) $this->campaign->status === 'archived') {
      $this->messenger()->addStatus($this->t('%name is already archived.', ['%name' => $this->campaign->name]));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    $campaign_data = json_decode((string) ($this->campaign->campaign_data ?? '{}'), TRUE);
    if (!is_array($campaign_data)) {
      $campaign_data = [];
    }

    $campaign_data['_archive_meta'] = [
      'previous_status' => (string) $this->campaign->status,
      'archived_at' => $this->time->getRequestTime(),
    ];

    $this->database->update('dc_campaigns')
      ->fields([
        'status' => 'archived',
        'campaign_data' => json_encode($campaign_data, JSON_UNESCAPED_UNICODE),
        'changed' => $this->time->getRequestTime(),
      ])
      ->condition('id', (int) $this->campaign->id)
      ->execute();

    $this->messenger()->addStatus($this->t('%name archived. It is now hidden from your campaigns list.', [
      '%name' => $this->campaign->name,
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
