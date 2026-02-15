<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for Dungeon Crawler tester.
 */
class TesterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_tester_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['dungeoncrawler_tester.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('dungeoncrawler_tester.settings');
    $has_token = (bool) $config->get('github_token');

    $form['help'] = [
      '#type' => 'details',
      '#title' => $this->t('How to get a GitHub token'),
      '#open' => TRUE,
      '#description' => $this->t('Create a token at <a href=":tokens">GitHub settings → Tokens</a>. A classic token only needs the <code>public_repo</code> scope for issue creation; fine-grained tokens need repo issue access. Default repo: <code>keithaumiller/forseti.life</code> (override below).', [':tokens' => 'https://github.com/settings/tokens']),
    ];

    $form['github_repo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GitHub repository'),
      '#description' => $this->t('Format: owner/repo. Used for auto-created issues on failed tester stages. If left empty, the tester will fall back to ai_conversation settings or the TESTER_GITHUB_REPO environment variable.'),
      '#default_value' => $config->get('github_repo') ?: '',
      '#required' => FALSE,
    ];

    $form['github_token'] = [
      '#type' => 'password',
      '#title' => $this->t('GitHub token'),
      '#description' => $has_token
        ? $this->t('Token is stored. Enter a new token to replace, or check "Clear stored token" to remove. If empty, the tester will fall back to ai_conversation settings or the TESTER_GITHUB_TOKEN environment variable.')
        : $this->t('Enter a token with permission to create issues in the configured repository. If left empty, the tester will fall back to ai_conversation settings or the TESTER_GITHUB_TOKEN environment variable.'),
      '#default_value' => '',
      '#attributes' => ['autocomplete' => 'new-password'],
    ];

    $form['clear_github_token'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clear stored token'),
      '#description' => $this->t('If checked, the stored token will be removed when saving. Leave unchecked to keep the current token unless a new one is provided above.'),
      '#default_value' => FALSE,
    ];

    $form['copilot_assignment_max_open'] = [
      '#type' => 'number',
      '#title' => $this->t('Copilot assignment max open issues'),
      '#description' => $this->t('Maximum number of open issues already assigned to Copilot before new auto-assignment is skipped. Set to 0 to disable this cap. Default: 0 (disabled).'),
      '#default_value' => (int) ($config->get('copilot_assignment_max_open') ?? 0),
      '#min' => 0,
      '#step' => 1,
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $repo = trim((string) $form_state->getValue('github_repo'));
    if ($repo !== '' && !str_contains($repo, '/')) {
      $form_state->setErrorByName('github_repo', $this->t('Repository must be in the format owner/repo.'));
    }

    $max_open = $form_state->getValue('copilot_assignment_max_open');
    if ($max_open !== NULL && $max_open !== '' && ((int) $max_open) < 0) {
      $form_state->setErrorByName('copilot_assignment_max_open', $this->t('Max open issues must be 0 or greater.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('dungeoncrawler_tester.settings');

    $repo = trim((string) $form_state->getValue('github_repo'));
    $config->set('github_repo', $repo);

    $clear_token = (bool) $form_state->getValue('clear_github_token');
    $new_token = trim((string) $form_state->getValue('github_token'));

    if ($clear_token) {
      $config->set('github_token', '');
    }
    elseif ($new_token !== '') {
      $config->set('github_token', $new_token);
    }

    $max_open = (int) $form_state->getValue('copilot_assignment_max_open');
    $config->set('copilot_assignment_max_open', max(0, $max_open));

    // If neither clear nor new token provided, keep existing token value.
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
