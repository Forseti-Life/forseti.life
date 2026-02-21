<?php

namespace Drupal\copilot_agent_tracker\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to submit a reply to an agent inbox item.
 */
final class InboxReplyForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'copilot_agent_tracker_inbox_reply_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $item_id = '', string $to_agent_id = ''): array {
    $form['item_id'] = [
      '#type' => 'hidden',
      '#value' => $item_id,
    ];
    $form['to_agent_id'] = [
      '#type' => 'hidden',
      '#value' => $to_agent_id,
    ];

    $form['reply'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reply'),
      '#required' => TRUE,
      '#rows' => 8,
      '#description' => $this->t('This will be queued back to HQ for delivery to the agent.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send reply'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $item_id = (string) $form_state->getValue('item_id');
    $to_agent_id = (string) $form_state->getValue('to_agent_id');
    $reply = (string) $form_state->getValue('reply');

    $this->database->insert('copilot_agent_tracker_replies')
      ->fields([
        'to_agent_id' => $to_agent_id,
        'in_reply_to' => $item_id,
        'message' => $reply,
        'created' => (int) $this->time->getRequestTime(),
        'consumed' => 0,
        'consumed_at' => 0,
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Reply queued for @agent.', ['@agent' => $to_agent_id]));
  }

}

