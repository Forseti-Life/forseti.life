<?php

declare(strict_types=1);

namespace Drupal\nfr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for NFR participant dashboard pages.
 */
class NFRDashboardController extends ControllerBase {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
    );
  }

  /**
   * Participant dashboard.
   *
   * @return array
   *   Render array.
   */
  public function myDashboard(): array {
    $uid = $this->currentUser()->id();
    
    // Load participant data
    $profile = $this->loadProfileData($uid);
    $consent = $this->loadConsentData($uid);
    $questionnaire = $this->loadQuestionnaireData($uid);
    
    // Check enrollment status
    $is_enrolled = $profile && $consent && !empty($profile['profile_completed']);
    
    if (!$is_enrolled) {
      // Redirect to enrollment if not complete
      $this->messenger()->addWarning($this->t('Please complete your enrollment to access the dashboard.'));
      return new \Symfony\Component\HttpFoundation\RedirectResponse(
        \Drupal\Core\Url::fromRoute('nfr.consent')->toString()
      );
    }

    $dashboard_content = $this->buildDashboardContent($profile, $consent, $questionnaire);

    return [
      '#theme' => 'nfr_dashboard_page',
      '#page_id' => 'my-dashboard',
      '#content' => [
        '#markup' => $dashboard_content,
      ],
      '#attached' => [
        'library' => ['nfr/participant-dashboard'],
      ],
    ];
  }

  /**
   * View/Edit profile page.
   *
   * @return array
   *   Render array.
   */
  public function myProfile(): array {
    return [
      '#theme' => 'nfr_dashboard_page',
      '#page_id' => 'my-profile',
      '#content' => [
        '#markup' => '<h2>My Profile</h2><p>Placeholder for viewing and editing profile.</p>',
      ],
    ];
  }

  /**
   * Follow-up survey page.
   *
   * @return array
   *   Render array.
   */
  public function followUp(): array {
    return [
      '#theme' => 'nfr_dashboard_page',
      '#page_id' => 'follow-up',
      '#content' => [
        '#markup' => '<h2>Follow-Up Survey</h2><p>Placeholder for longitudinal data collection.</p>',
      ],
    ];
  }

  /**
   * Account settings page.
   *
   * @return array
   *   Render array.
   */
  public function accountSettings(): array {
    return [
      '#theme' => 'nfr_dashboard_page',
      '#page_id' => 'account-settings',
      '#content' => [
        '#markup' => '<h2>Account Settings</h2><p>Placeholder for account preferences.</p>',
      ],
    ];
  }

  /**
   * Load profile data.
   */
  private function loadProfileData(int $uid): ?array {
    return $this->database->select('nfr_user_profile', 'p')
      ->fields('p')
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc() ?: NULL;
  }

  /**
   * Load consent data.
   */
  private function loadConsentData(int $uid): ?array {
    return $this->database->select('nfr_consent', 'c')
      ->fields('c')
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc() ?: NULL;
  }

  /**
   * Load questionnaire data.
   */
  private function loadQuestionnaireData(int $uid): ?array {
    return $this->database->select('nfr_questionnaire', 'q')
      ->fields('q')
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc() ?: NULL;
  }

  /**
   * Build dashboard content.
   */
  private function buildDashboardContent(array $profile, array $consent, ?array $questionnaire): string {
    $first_name = htmlspecialchars($profile['first_name']);
    $participant_id = htmlspecialchars($profile['participant_id'] ?? 'Pending');
    $profile_updated = date('F j, Y', $profile['updated'] ?? $profile['created']);
    $questionnaire_completed = $questionnaire && !empty($questionnaire['questionnaire_completed']);
    $questionnaire_date = $questionnaire_completed ? date('F j, Y', $questionnaire['completed_date']) : 'Not completed';
    $linkage_consent = !empty($consent['consented_to_registry_linkage']) ? 'Yes' : 'No';
    
    // Get total participants count (mock for now)
    $total_participants = $this->database->select('nfr_user_profile', 'p')
      ->condition('profile_completed', 1)
      ->countQuery()
      ->execute()
      ->fetchField();

    $html = '<div class="participant-dashboard">';
    
    // Welcome section
    $html .= '<div class="dashboard-welcome">';
    $html .= '<h1>' . $this->t('Welcome back, @name!', ['@name' => $first_name]) . '</h1>';
    $html .= '<p class="welcome-text">' . $this->t('Thank you for being part of the National Firefighter Registry.') . '</p>';
    $html .= '<div class="participant-id-display">' . $this->t('Your Participant ID: <strong>@id</strong>', ['@id' => $participant_id]) . '</div>';
    $html .= '</div>';

    $html .= '<div class="dashboard-layout">';
    
    // Left column - Main content
    $html .= '<div class="dashboard-main">';
    
    // Status cards
    $html .= '<div class="status-cards">';
    
    // Profile status card
    $html .= '<div class="status-card status-card--complete">';
    $html .= '<div class="status-card-icon">👤</div>';
    $html .= '<div class="status-card-content">';
    $html .= '<h3>' . $this->t('Profile Status') . '</h3>';
    $html .= '<p class="status-text">' . $this->t('Profile Complete') . '</p>';
    $html .= '<p class="status-meta">' . $this->t('Last updated: @date', ['@date' => $profile_updated]) . '</p>';
    $html .= '<a href="/nfr/profile" class="status-link">' . $this->t('Update Profile') . '</a>';
    $html .= '</div></div>';
    
    // Questionnaire status card
    $html .= '<div class="status-card ' . ($questionnaire_completed ? 'status-card--complete' : 'status-card--pending') . '">';
    $html .= '<div class="status-card-icon">📋</div>';
    $html .= '<div class="status-card-content">';
    $html .= '<h3>' . $this->t('Enrollment Questionnaire') . '</h3>';
    if ($questionnaire_completed) {
      $html .= '<p class="status-text">' . $this->t('Questionnaire Complete') . '</p>';
      $html .= '<p class="status-meta">' . $this->t('Submitted: @date', ['@date' => $questionnaire_date]) . '</p>';
      $html .= '<a href="/nfr/review" class="status-link">' . $this->t('View Responses') . '</a>';
    }
    else {
      $html .= '<p class="status-text">' . $this->t('Not Completed') . '</p>';
      $html .= '<a href="/nfr/questionnaire" class="status-link">' . $this->t('Complete Now') . '</a>';
    }
    $html .= '</div></div>';
    
    // Follow-up survey card
    $html .= '<div class="status-card status-card--info">';
    $html .= '<div class="status-card-icon">📅</div>';
    $html .= '<div class="status-card-content">';
    $html .= '<h3>' . $this->t('Follow-Up Surveys') . '</h3>';
    $html .= '<p class="status-text">' . $this->t('No Follow-Up Required Yet') . '</p>';
    $html .= '<p class="status-meta">' . $this->t('We\'ll contact you annually') . '</p>';
    $html .= '</div></div>';
    
    // Cancer registry linkage card
    $html .= '<div class="status-card status-card--info">';
    $html .= '<div class="status-card-icon">🔗</div>';
    $html .= '<div class="status-card-content">';
    $html .= '<h3>' . $this->t('Cancer Registry Linkage') . '</h3>';
    $html .= '<p class="status-text">' . $this->t('Linkage Consent: @status', ['@status' => $linkage_consent]) . '</p>';
    $html .= '<p class="status-meta">' . $this->t('Status: Not Yet Linked') . '</p>';
    $html .= '<a href="/nfr/consent" class="status-link">' . $this->t('Change Consent Status') . '</a>';
    $html .= '</div></div>';
    
    $html .= '</div>'; // .status-cards
    
    // Action buttons
    $html .= '<div class="dashboard-actions">';
    $html .= '<h2>' . $this->t('Quick Actions') . '</h2>';
    $html .= '<div class="action-buttons">';
    $html .= '<a href="/nfr/profile" class="action-button action-button--primary">' . $this->t('Update My Profile') . '</a>';
    $html .= '<a href="/nfr/contact" class="action-button action-button--secondary">' . $this->t('Report a Cancer Diagnosis') . '</a>';
    $html .= '<a href="/nfr/review" class="action-button action-button--secondary">' . $this->t('Download My Data') . '</a>';
    $html .= '<a href="/nfr/contact" class="action-button action-button--secondary">' . $this->t('Contact NFR Team') . '</a>';
    $html .= '</div></div>';
    
    // Recent activity
    $html .= '<div class="recent-activity">';
    $html .= '<h2>' . $this->t('Recent Activity') . '</h2>';
    $html .= '<div class="activity-timeline">';
    
    if ($questionnaire_completed) {
      $html .= '<div class="activity-item">';
      $html .= '<div class="activity-icon">✓</div>';
      $html .= '<div class="activity-content">';
      $html .= '<p><strong>' . $this->t('Enrollment Questionnaire Completed') . '</strong></p>';
      $html .= '<p class="activity-date">' . $questionnaire_date . '</p>';
      $html .= '</div></div>';
    }
    
    $html .= '<div class="activity-item">';
    $html .= '<div class="activity-icon">✓</div>';
    $html .= '<div class="activity-content">';
    $html .= '<p><strong>' . $this->t('Profile Created') . '</strong></p>';
    $html .= '<p class="activity-date">' . date('F j, Y', $profile['created']) . '</p>';
    $html .= '</div></div>';
    
    $html .= '<div class="activity-item">';
    $html .= '<div class="activity-icon">✓</div>';
    $html .= '<div class="activity-content">';
    $html .= '<p><strong>' . $this->t('Informed Consent Signed') . '</strong></p>';
    $html .= '<p class="activity-date">' . date('F j, Y', $consent['consent_timestamp']) . '</p>';
    $html .= '</div></div>';
    
    $html .= '</div></div>'; // .recent-activity
    
    $html .= '</div>'; // .dashboard-main
    
    // Right column - Sidebar
    $html .= '<div class="dashboard-sidebar">';
    
    // Participation impact
    $html .= '<div class="sidebar-widget">';
    $html .= '<h3>' . $this->t('Your Impact') . '</h3>';
    $html .= '<div class="impact-stats">';
    $html .= '<p class="impact-number">' . number_format($total_participants) . '</p>';
    $html .= '<p class="impact-text">' . $this->t('Firefighters in the NFR') . '</p>';
    $html .= '<p class="impact-description">' . $this->t('You are 1 of @count firefighters helping to improve firefighter health and safety.', ['@count' => number_format($total_participants)]) . '</p>';
    $html .= '</div></div>';
    
    // NFR News
    $html .= '<div class="sidebar-widget">';
    $html .= '<h3>' . $this->t('NFR News') . '</h3>';
    $html .= '<div class="news-items">';
    $html .= '<div class="news-item">';
    $html .= '<h4><a href="/nfr">' . $this->t('New Research Findings Published') . '</a></h4>';
    $html .= '<p class="news-date">' . $this->t('January 2026') . '</p>';
    $html .= '</div>';
    $html .= '<div class="news-item">';
    $html .= '<h4><a href="/nfr">' . $this->t('NFR Expands to All 50 States') . '</a></h4>';
    $html .= '<p class="news-date">' . $this->t('December 2025') . '</p>';
    $html .= '</div>';
    $html .= '<a href="/nfr" class="see-all-link">' . $this->t('See All News') . '</a>';
    $html .= '</div></div>';
    
    // Resources
    $html .= '<div class="sidebar-widget">';
    $html .= '<h3>' . $this->t('Resources') . '</h3>';
    $html .= '<ul class="resource-links">';
    $html .= '<li><a href="/nfr/faq">' . $this->t('Frequently Asked Questions') . '</a></li>';
    $html .= '<li><a href="/nfr">' . $this->t('Privacy Policy') . '</a></li>';
    $html .= '<li><a href="/nfr">' . $this->t('How Data is Used') . '</a></li>';
    $html .= '<li><a href="/nfr">' . $this->t('Published Findings') . '</a></li>';
    $html .= '<li><a href="/nfr/contact">' . $this->t('Contact Us') . '</a></li>';
    $html .= '</ul></div>';
    
    // Communication preferences
    $html .= '<div class="sidebar-widget">';
    $html .= '<h3>' . $this->t('Communication Preferences') . '</h3>';
    $html .= '<div class="preferences">';
    $html .= '<p><strong>' . $this->t('Email Notifications:') . '</strong> ' . $this->t('On') . '</p>';
    $html .= '<p><strong>' . $this->t('SMS Notifications:') . '</strong> ' . ($profile['sms_opt_in'] ? $this->t('On') : $this->t('Off')) . '</p>';
    $html .= '<a href="/user/' . $this->currentUser()->id() . '/edit" class="change-link">' . $this->t('Change Preferences') . '</a>';
    $html .= '</div></div>';
    
    $html .= '</div>'; // .dashboard-sidebar
    
    $html .= '</div>'; // .dashboard-layout
    $html .= '</div>'; // .participant-dashboard

    return $html;
  }

}
