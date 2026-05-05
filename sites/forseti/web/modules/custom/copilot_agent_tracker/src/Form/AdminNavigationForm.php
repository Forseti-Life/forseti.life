<?php

namespace Drupal\copilot_agent_tracker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\user\Entity\User;

/**
 * Admin Navigation Controls Form — landing page, section visibility, theme.
 *
 * AC-13 & AC-5: Customize console UI by setting landing page, toggling section visibility,
 * and storing team assignment selections in user preferences.
 */
class AdminNavigationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'langgraph_console_admin_navigation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#cache'] = ['max-age' => 0];

    $current_user = \Drupal::currentUser();
    $user_entity = User::load($current_user->id());
    $user_prefs = $this->loadUserPrefs($user_entity);

    $form['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Customize console navigation, landing page, and theme. Settings are stored in your user profile.'),
      '#attributes' => ['style' => 'margin-bottom: 1.5rem;'],
    ];

    $form['landing_page'] = [
      '#type' => 'select',
      '#title' => $this->t('Landing page'),
      '#description' => $this->t('Which section loads when you open /langgraph-console.'),
      '#options' => [
        'home' => $this->t('Home'),
        'build' => $this->t('Build'),
        'test' => $this->t('Test'),
        'run' => $this->t('Run'),
        'observe' => $this->t('Observe'),
        'release' => $this->t('Release'),
        'admin' => $this->t('Admin'),
      ],
      '#default_value' => $user_prefs['landing_page'] ?? 'home',
      '#required' => TRUE,
    ];

    $form['visible_sections'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Visible sections'),
      '#description' => $this->t('Uncheck to hide sections from the navigation menu.'),
      '#options' => [
        'home' => $this->t('Home'),
        'build' => $this->t('Build'),
        'test' => $this->t('Test'),
        'run' => $this->t('Run'),
        'observe' => $this->t('Observe'),
        'release' => $this->t('Release'),
        'admin' => $this->t('Admin'),
      ],
      '#default_value' => $user_prefs['visible_sections'] ?? ['home', 'build', 'test', 'run', 'observe', 'release', 'admin'],
    ];

    $form['theme'] = [
      '#type' => 'radios',
      '#title' => $this->t('Theme'),
      '#description' => $this->t('Choose light or dark mode (applied via data-theme attribute on body).'),
      '#options' => [
        'light' => $this->t('Light'),
        'dark' => $this->t('Dark'),
      ],
      '#default_value' => $user_prefs['theme'] ?? 'light',
    ];

    // AC-5: Team Assignment section.
    $form['team_assignment_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Team Assignment'),
      '#open' => FALSE,
      '#description' => $this->t('Select seats to track in your team view.'),
    ];

    $available_seats = $this->getAvailableSeats();
    $form['team_assignment_section']['team_seats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Assigned seats'),
      '#options' => $available_seats,
      '#default_value' => $user_prefs['team_seats'] ?? [],
      '#description' => $this->t('Check to include a seat in your team view.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save navigation settings'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $current_user = \Drupal::currentUser();
    $user_entity = User::load($current_user->id());
    $old_prefs = $this->loadUserPrefs($user_entity);

    // Prepare new preferences.
    $new_prefs = [
      'landing_page' => $values['landing_page'],
      'visible_sections' => array_filter($values['visible_sections']),
      'theme' => $values['theme'],
      'team_seats' => array_filter($values['team_seats']),
    ];

    // Save to user preferences.
    $this->saveUserPrefs($user_entity, $new_prefs);

    // Log audit entries for each change.
    $this->logAuditEntries($new_prefs, $old_prefs, $values);

    $this->messenger()->addStatus($this->t('Navigation settings saved successfully.'));
    $form_state->setRedirect('copilot_agent_tracker.langgraph_console_admin_navigation');
  }

  /**
   * Load user navigation preferences.
   */
  private function loadUserPrefs(User $user): array {
    $settings = $user->get('settings')->getValue();
    if (empty($settings) || !isset($settings[0]['value'])) {
      return [];
    }

    try {
      $prefs = json_decode($settings[0]['value'], TRUE);
      return is_array($prefs) ? $prefs : [];
    } catch (\Exception) {
      return [];
    }
  }

  /**
   * Save user navigation preferences.
   */
  private function saveUserPrefs(User $user, array $prefs): void {
    $user->set('settings', json_encode($prefs, JSON_UNESCAPED_SLASHES));
    $user->save();

    // Apply theme immediately (data-theme on body).
    \Drupal::request()->attributes->set('data-theme', $prefs['theme'] ?? 'light');
  }

  /**
   * Get available seats from HQ.
   */
  private function getAvailableSeats(): array {
    $hq_root = rtrim((string) (getenv('COPILOT_HQ_ROOT') ?: '/home/ubuntu/forseti.life/copilot-hq'), '/');
    $agents_yaml = $hq_root . '/org-chart/agents/agents.yaml';

    $seats = [];
    if (is_readable($agents_yaml)) {
      try {
        $yaml = \Drupal\Component\Yaml\Yaml::decode(@file_get_contents($agents_yaml));
        if (is_array($yaml)) {
          foreach ($yaml as $agent) {
            if (isset($agent['agent_id'], $agent['role'], $agent['module'])) {
              $label = $agent['agent_id'] . ' (' . $agent['module'] . ')';
              $seats[$agent['agent_id']] = $label;
            }
          }
        }
      } catch (\Exception) {
        // If YAML parsing fails, just skip.
      }
    }

    return $seats ?: ['dev-forseti' => 'dev-forseti (copilot_agent_tracker)'];
  }

  /**
   * Log audit entries for navigation changes.
   */
  private function logAuditEntries(array $new_prefs, array $old_prefs, array $form_values): void {
    $db = Database::getConnection();
    $operator_id = \Drupal::currentUser()->id();
    $csrf_verified = 1; // Form token is verified automatically by Drupal.

    // Log landing page change.
    if (($old_prefs['landing_page'] ?? 'home') !== ($new_prefs['landing_page'] ?? 'home')) {
      $db->insert('copilot_agent_tracker_audit')
        ->fields([
          'timestamp' => time(),
          'operator_id' => $operator_id,
          'action' => 'navigation_updated',
          'resource_id' => 'landing_page',
          'before_value' => $old_prefs['landing_page'] ?? 'home',
          'after_value' => $new_prefs['landing_page'] ?? 'home',
          'csrf_verified' => $csrf_verified,
        ])
        ->execute();
    }

    // Log visible sections change.
    $old_sections = $old_prefs['visible_sections'] ?? ['home', 'build', 'test', 'run', 'observe', 'release', 'admin'];
    $new_sections = $new_prefs['visible_sections'] ?? [];
    if (implode(',', sort($old_sections)) !== implode(',', sort($new_sections))) {
      $db->insert('copilot_agent_tracker_audit')
        ->fields([
          'timestamp' => time(),
          'operator_id' => $operator_id,
          'action' => 'navigation_updated',
          'resource_id' => 'visible_sections',
          'before_value' => json_encode($old_sections),
          'after_value' => json_encode($new_sections),
          'csrf_verified' => $csrf_verified,
        ])
        ->execute();
    }

    // Log theme change.
    if (($old_prefs['theme'] ?? 'light') !== ($new_prefs['theme'] ?? 'light')) {
      $db->insert('copilot_agent_tracker_audit')
        ->fields([
          'timestamp' => time(),
          'operator_id' => $operator_id,
          'action' => 'navigation_updated',
          'resource_id' => 'theme',
          'before_value' => $old_prefs['theme'] ?? 'light',
          'after_value' => $new_prefs['theme'] ?? 'light',
          'csrf_verified' => $csrf_verified,
        ])
        ->execute();
    }

    // Log team assignment change.
    $old_seats = $old_prefs['team_seats'] ?? [];
    $new_seats = $new_prefs['team_seats'] ?? [];
    if (implode(',', sort($old_seats)) !== implode(',', sort($new_seats))) {
      $db->insert('copilot_agent_tracker_audit')
        ->fields([
          'timestamp' => time(),
          'operator_id' => $operator_id,
          'action' => 'team_assignment_changed',
          'resource_id' => 'team_seats',
          'before_value' => json_encode($old_seats),
          'after_value' => json_encode($new_seats),
          'csrf_verified' => $csrf_verified,
        ])
        ->execute();
    }
  }

}
