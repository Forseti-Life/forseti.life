<?php

namespace Drupal\copilot_agent_tracker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin Settings Form — configurable thresholds and retention policies.
 *
 * AC-1 through AC-3: Tunable parameters (max tick history, metrics trend window, drift threshold,
 * alert retention, canary duration) with validation and audit logging.
 */
class AdminSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'langgraph_console_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->loadSettings();

    $form['#cache'] = ['max-age' => 0];

    $form['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Configure LangGraph Console behavior. All changes are logged to the audit table.'),
      '#attributes' => ['style' => 'margin-bottom: 1.5rem;'],
    ];

    $form['max_tick_history'] = [
      '#type' => 'number',
      '#title' => $this->t('Max tick history'),
      '#description' => $this->t('How many ticks to retain in console display (10–1000).'),
      '#min' => 10,
      '#max' => 1000,
      '#default_value' => $settings['max_tick_history'] ?? 100,
      '#required' => TRUE,
    ];

    $form['metrics_trend_window'] = [
      '#type' => 'number',
      '#title' => $this->t('Metrics trend window'),
      '#description' => $this->t('Ticks to include in trend calculation (5–50).'),
      '#min' => 5,
      '#max' => 50,
      '#default_value' => $settings['metrics_trend_window'] ?? 10,
      '#required' => TRUE,
    ];

    $form['drift_threshold_percent'] = [
      '#type' => 'number',
      '#title' => $this->t('Drift threshold %'),
      '#description' => $this->t('Variance threshold to trigger alert (1–100 percent). Cannot be 0 or negative.'),
      '#min' => 1,
      '#max' => 100,
      '#default_value' => $settings['drift_threshold_percent'] ?? 50,
      '#required' => TRUE,
    ];

    $form['alert_retention_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Alert retention (days)'),
      '#description' => $this->t('How long to keep incident records (1–30 days).'),
      '#min' => 1,
      '#max' => 30,
      '#default_value' => $settings['alert_retention_days'] ?? 7,
      '#required' => TRUE,
    ];

    $form['canary_default_duration_hours'] = [
      '#type' => 'number',
      '#title' => $this->t('Canary default duration (hours)'),
      '#description' => $this->t('Suggested canary duration for Phase 6 (0.5–24 hours).'),
      '#min' => 0.5,
      '#max' => 24,
      '#step' => 0.5,
      '#default_value' => $settings['canary_default_duration_hours'] ?? 1,
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save settings'),
      '#button_type' => 'primary',
    ];

    $form['#attributes']['novalidate'] = 'novalidate';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();

    // AC-3: Validate ranges.
    if ($values['max_tick_history'] < 10 || $values['max_tick_history'] > 1000) {
      $form_state->setErrorByName('max_tick_history', $this->t('Max tick history must be between 10 and 1000.'));
    }

    if ($values['metrics_trend_window'] < 5 || $values['metrics_trend_window'] > 50) {
      $form_state->setErrorByName('metrics_trend_window', $this->t('Metrics trend window must be between 5 and 50.'));
    }

    if ($values['drift_threshold_percent'] <= 0 || $values['drift_threshold_percent'] > 100) {
      $form_state->setErrorByName('drift_threshold_percent', $this->t('Drift threshold must be between 1 and 100 (cannot be 0 or negative).'));
    }

    if ($values['alert_retention_days'] < 1 || $values['alert_retention_days'] > 30) {
      $form_state->setErrorByName('alert_retention_days', $this->t('Alert retention must be between 1 and 30 days.'));
    }

    if ($values['canary_default_duration_hours'] < 0.5 || $values['canary_default_duration_hours'] > 24) {
      $form_state->setErrorByName('canary_default_duration_hours', $this->t('Canary default duration must be between 0.5 and 24 hours.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $old_settings = $this->loadSettings();

    // AC-2: Save to both backends (JSON file and Drupal config).
    $this->saveSettingsJson($values);
    $this->saveSettingsConfig($values);

    // AC-16: Log audit entries for each changed field.
    $this->logAuditEntries($values, $old_settings);

    // Show success message.
    $this->messenger()->addStatus($this->t('Settings saved successfully.'));
    $form_state->setRedirect('copilot_agent_tracker.langgraph_console_admin_settings');
  }

  /**
   * Load settings from Drupal config (with JSON fallback).
   */
  private function loadSettings(): array {
    $config = \Drupal::config('copilot_agent_tracker.admin_settings');
    $settings = [
      'max_tick_history' => $config->get('max_tick_history') ?? 100,
      'metrics_trend_window' => $config->get('metrics_trend_window') ?? 10,
      'drift_threshold_percent' => $config->get('drift_threshold_percent') ?? 50,
      'alert_retention_days' => $config->get('alert_retention_days') ?? 7,
      'canary_default_duration_hours' => $config->get('canary_default_duration_hours') ?? 1,
    ];

    // If config is empty, try JSON fallback.
    if (!$config->get('max_tick_history')) {
      $hq_root = rtrim((string) (getenv('COPILOT_HQ_ROOT') ?: '/home/ubuntu/forseti.life/copilot-hq'), '/');
      $json_path = $hq_root . '/admin/settings.json';
      if (is_readable($json_path)) {
        $json_data = json_decode(@file_get_contents($json_path), TRUE);
        if (is_array($json_data)) {
          $settings = array_merge($settings, $json_data);
        }
      }
    }

    return $settings;
  }

  /**
   * Save settings to Drupal config.
   */
  private function saveSettingsConfig(array $values): void {
    $config = \Drupal::configFactory()->getEditable('copilot_agent_tracker.admin_settings');
    $config->set('max_tick_history', (int) $values['max_tick_history'])
      ->set('metrics_trend_window', (int) $values['metrics_trend_window'])
      ->set('drift_threshold_percent', (int) $values['drift_threshold_percent'])
      ->set('alert_retention_days', (int) $values['alert_retention_days'])
      ->set('canary_default_duration_hours', (float) $values['canary_default_duration_hours'])
      ->save();
  }

  /**
   * Save settings to JSON file (secondary backend).
   */
  private function saveSettingsJson(array $values): void {
    $hq_root = rtrim((string) (getenv('COPILOT_HQ_ROOT') ?: '/home/ubuntu/forseti.life/copilot-hq'), '/');
    $admin_dir = $hq_root . '/admin';
    $json_path = $admin_dir . '/settings.json';

    if (!is_dir($admin_dir)) {
      @mkdir($admin_dir, 0755, TRUE);
    }

    $data = [
      'max_tick_history' => (int) $values['max_tick_history'],
      'metrics_trend_window' => (int) $values['metrics_trend_window'],
      'drift_threshold_percent' => (int) $values['drift_threshold_percent'],
      'alert_retention_days' => (int) $values['alert_retention_days'],
      'canary_default_duration_hours' => (float) $values['canary_default_duration_hours'],
      'updated_at' => date('Y-m-d H:i:s'),
    ];

    @file_put_contents($json_path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Log audit entries for settings changes.
   */
  private function logAuditEntries(array $new_values, array $old_values): void {
    $db = Database::getConnection();
    $operator_id = \Drupal::currentUser()->id();
    $csrf_verified = 1; // Form token is verified automatically by Drupal.

    foreach ($new_values as $key => $new_value) {
      if (!isset($old_values[$key]) || $old_values[$key] != $new_value) {
        $db->insert('copilot_agent_tracker_audit')
          ->fields([
            'timestamp' => time(),
            'operator_id' => $operator_id,
            'action' => 'settings_changed',
            'resource_id' => $key,
            'before_value' => isset($old_values[$key]) ? (string) $old_values[$key] : NULL,
            'after_value' => (string) $new_value,
            'csrf_verified' => $csrf_verified,
          ])
          ->execute();
      }
    }
  }

}
