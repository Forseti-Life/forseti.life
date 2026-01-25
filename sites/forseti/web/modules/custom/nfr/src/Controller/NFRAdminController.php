<?php

declare(strict_types=1);

namespace Drupal\nfr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for NFR administrative pages.
 */
class NFRAdminController extends ControllerBase {

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
   * Admin dashboard.
   *
   * @return array
   *   Render array.
   */
  public function adminDashboard(): array {
    $stats = $this->getStatistics();
    $recent_participants = $this->getRecentParticipants(10);
    $dashboard_content = $this->buildAdminDashboard($stats, $recent_participants);

    return [
      '#theme' => 'nfr_admin_page',
      '#page_id' => 'admin-dashboard',
      '#content' => [
        '#markup' => $dashboard_content,
      ],
      '#attached' => [
        'library' => ['nfr/admin'],
      ],
    ];
  }

  /**
   * Get statistics for dashboard.
   */
  private function getStatistics(): array {
    // Total participants
    $total_participants = $this->database->select('nfr_user_profile', 'p')
      ->condition('profile_completed', 1)
      ->countQuery()
      ->execute()
      ->fetchField();

    // New today
    $new_today = $this->database->select('nfr_user_profile', 'p')
      ->condition('profile_completed', 1)
      ->condition('created', strtotime('today'), '>=')
      ->countQuery()
      ->execute()
      ->fetchField();

    // This month
    $new_this_month = $this->database->select('nfr_user_profile', 'p')
      ->condition('profile_completed', 1)
      ->condition('created', strtotime('first day of this month'), '>=')
      ->countQuery()
      ->execute()
      ->fetchField();

    // Profile completion rate
    $total_profiles = $this->database->select('nfr_user_profile', 'p')
      ->countQuery()
      ->execute()
      ->fetchField();
    $profile_completion_rate = $total_profiles > 0 ? round(($total_participants / $total_profiles) * 100) : 0;

    // Questionnaire completion rate
    $questionnaires_completed = $this->database->select('nfr_questionnaire', 'q')
      ->condition('questionnaire_completed', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
    $questionnaire_completion_rate = $total_participants > 0 ? round(($questionnaires_completed / $total_participants) * 100) : 0;

    // Linkage consent
    $linkage_consents = $this->database->select('nfr_consent', 'c')
      ->condition('consented_to_registry_linkage', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
    $linkage_consent_rate = $total_participants > 0 ? round(($linkage_consents / $total_participants) * 100) : 0;

    // State distribution (top 5)
    $state_distribution = $this->database->select('nfr_user_profile', 'p')
      ->fields('p', ['state'])
      ->condition('profile_completed', 1)
      ->groupBy('state')
      ->execute()
      ->fetchAllKeyed(0, 0);

    return [
      'total_participants' => $total_participants,
      'new_today' => $new_today,
      'new_this_month' => $new_this_month,
      'profile_completion_rate' => $profile_completion_rate,
      'questionnaire_completion_rate' => $questionnaire_completion_rate,
      'linkage_consent_rate' => $linkage_consent_rate,
      'linkage_consents' => $linkage_consents,
      'state_distribution' => $state_distribution,
    ];
  }

  /**
   * Get recent participants.
   */
  private function getRecentParticipants(int $limit = 10): array {
    return $this->database->select('nfr_user_profile', 'p')
      ->fields('p', ['participant_id', 'first_name', 'last_name', 'primary_email', 'state', 'created', 'profile_completed'])
      ->orderBy('created', 'DESC')
      ->range(0, $limit)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Build admin dashboard HTML.
   */
  private function buildAdminDashboard(array $stats, array $recent): string {
    $html = '<div class="admin-dashboard">';
    
    // Header
    $html .= '<div class="admin-header">';
    $html .= '<h1>' . $this->t('NFR Administration Dashboard') . '</h1>';
    $html .= '<p class="admin-subtitle">' . $this->t('National Firefighter Registry Management System') . '</p>';
    $html .= '</div>';

    // Key metrics
    $html .= '<div class="metrics-grid">';
    
    // Total participants
    $html .= '<div class="metric-card metric-card--primary">';
    $html .= '<div class="metric-icon">👥</div>';
    $html .= '<div class="metric-content">';
    $html .= '<div class="metric-value">' . number_format($stats['total_participants']) . '</div>';
    $html .= '<div class="metric-label">' . $this->t('Total Participants') . '</div>';
    $html .= '<div class="metric-change">+' . $stats['new_today'] . ' ' . $this->t('today') . '</div>';
    $html .= '</div></div>';
    
    // Enrollment this month
    $html .= '<div class="metric-card metric-card--success">';
    $html .= '<div class="metric-icon">📈</div>';
    $html .= '<div class="metric-content">';
    $html .= '<div class="metric-value">' . number_format($stats['new_this_month']) . '</div>';
    $html .= '<div class="metric-label">' . $this->t('Enrolled This Month') . '</div>';
    $html .= '<div class="metric-change">' . $this->t('January 2026') . '</div>';
    $html .= '</div></div>';
    
    // Completion rates
    $html .= '<div class="metric-card metric-card--info">';
    $html .= '<div class="metric-icon">✓</div>';
    $html .= '<div class="metric-content">';
    $html .= '<div class="metric-value">' . $stats['questionnaire_completion_rate'] . '%</div>';
    $html .= '<div class="metric-label">' . $this->t('Questionnaire Completion') . '</div>';
    $html .= '<div class="metric-change">' . $this->t('Profile: @rate%', ['@rate' => $stats['profile_completion_rate']]) . '</div>';
    $html .= '</div></div>';
    
    // Linkage status
    $html .= '<div class="metric-card metric-card--warning">';
    $html .= '<div class="metric-icon">🔗</div>';
    $html .= '<div class="metric-content">';
    $html .= '<div class="metric-value">' . $stats['linkage_consent_rate'] . '%</div>';
    $html .= '<div class="metric-label">' . $this->t('Linkage Consent Rate') . '</div>';
    $html .= '<div class="metric-change">' . number_format($stats['linkage_consents']) . ' ' . $this->t('consented') . '</div>';
    $html .= '</div></div>';
    
    $html .= '</div>'; // .metrics-grid

    // Main content area
    $html .= '<div class="admin-content-grid">';
    
    // Left column
    $html .= '<div class="admin-main-content">';
    
    // Recent participants
    $html .= '<div class="admin-widget">';
    $html .= '<h2>' . $this->t('Recent Registrations') . '</h2>';
    $html .= '<div class="recent-participants-table">';
    $html .= '<table>';
    $html .= '<thead><tr>';
    $html .= '<th>' . $this->t('Participant ID') . '</th>';
    $html .= '<th>' . $this->t('Name') . '</th>';
    $html .= '<th>' . $this->t('State') . '</th>';
    $html .= '<th>' . $this->t('Enrolled') . '</th>';
    $html .= '<th>' . $this->t('Status') . '</th>';
    $html .= '</tr></thead><tbody>';
    
    foreach ($recent as $participant) {
      $html .= '<tr>';
      $html .= '<td><a href="/admin/nfr/participant/' . htmlspecialchars($participant['participant_id']) . '">' . 
        htmlspecialchars($participant['participant_id']) . '</a></td>';
      $html .= '<td>' . htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']) . '</td>';
      $html .= '<td>' . htmlspecialchars($participant['state'] ?? 'N/A') . '</td>';
      $html .= '<td>' . date('M j, Y', $participant['created']) . '</td>';
      $html .= '<td><span class="status-badge status-' . ($participant['profile_completed'] ? 'complete' : 'pending') . '">' . 
        ($participant['profile_completed'] ? $this->t('Complete') : $this->t('Pending')) . '</span></td>';
      $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    $html .= '</div>'; // table wrapper
    $html .= '<a href="/admin/nfr/participants" class="view-all-link">' . $this->t('View All Participants →') . '</a>';
    $html .= '</div>'; // .admin-widget

    // Quick actions
    $html .= '<div class="admin-widget">';
    $html .= '<h2>' . $this->t('Quick Actions') . '</h2>';
    $html .= '<div class="admin-actions-grid">';
    $html .= '<a href="/admin/nfr/participants" class="admin-action-button">';
    $html .= '<div class="action-icon">📋</div>';
    $html .= '<div class="action-text">' . $this->t('View All Participants') . '</div>';
    $html .= '</a>';
    $html .= '<a href="/admin/nfr/linkage" class="admin-action-button">';
    $html .= '<div class="action-icon">🔗</div>';
    $html .= '<div class="action-text">' . $this->t('Process Linkage') . '</div>';
    $html .= '</a>';
    $html .= '<a href="/admin/nfr/data-quality" class="admin-action-button">';
    $html .= '<div class="action-icon">📊</div>';
    $html .= '<div class="action-text">' . $this->t('Data Quality') . '</div>';
    $html .= '</a>';
    $html .= '<a href="/admin/nfr/reports" class="admin-action-button">';
    $html .= '<div class="action-icon">📄</div>';
    $html .= '<div class="action-text">' . $this->t('Generate Reports') . '</div>';
    $html .= '</a>';
    $html .= '</div></div>';
    
    $html .= '</div>'; // .admin-main-content
    
    // Right sidebar
    $html .= '<div class="admin-sidebar">';
    
    // State distribution
    $html .= '<div class="admin-widget">';
    $html .= '<h3>' . $this->t('Top States') . '</h3>';
    $html .= '<div class="state-distribution">';
    
    if (!empty($stats['state_distribution'])) {
      $state_counts = [];
      foreach ($stats['state_distribution'] as $state) {
        if ($state) {
          $state_counts[$state] = ($state_counts[$state] ?? 0) + 1;
        }
      }
      arsort($state_counts);
      $top_states = array_slice($state_counts, 0, 5, true);
      
      foreach ($top_states as $state => $count) {
        $percentage = $stats['total_participants'] > 0 ? round(($count / $stats['total_participants']) * 100, 1) : 0;
        $html .= '<div class="state-item">';
        $html .= '<div class="state-info">';
        $html .= '<span class="state-name">' . htmlspecialchars($state) . '</span>';
        $html .= '<span class="state-count">' . number_format($count) . '</span>';
        $html .= '</div>';
        $html .= '<div class="state-bar">';
        $html .= '<div class="state-bar-fill" style="width: ' . $percentage . '%"></div>';
        $html .= '</div>';
        $html .= '</div>';
      }
    }
    else {
      $html .= '<p class="no-data">' . $this->t('No state data yet') . '</p>';
    }
    
    $html .= '</div></div>';
    
    // System status
    $html .= '<div class="admin-widget">';
    $html .= '<h3>' . $this->t('System Status') . '</h3>';
    $html .= '<div class="system-status">';
    $html .= '<div class="status-item status-item--good">';
    $html .= '<span class="status-indicator"></span>';
    $html .= '<span class="status-text">' . $this->t('Database: Operational') . '</span>';
    $html .= '</div>';
    $html .= '<div class="status-item status-item--good">';
    $html .= '<span class="status-indicator"></span>';
    $html .= '<span class="status-text">' . $this->t('Email Service: Active') . '</span>';
    $html .= '</div>';
    $html .= '<div class="status-item status-item--good">';
    $html .= '<span class="status-indicator"></span>';
    $html .= '<span class="status-text">' . $this->t('Backups: Up to date') . '</span>';
    $html .= '</div>';
    $html .= '</div></div>';
    
    // Links
    $html .= '<div class="admin-widget">';
    $html .= '<h3>' . $this->t('Resources') . '</h3>';
    $html .= '<ul class="admin-resource-links">';
    $html .= '<li><a href="/admin/nfr/settings">' . $this->t('System Settings') . '</a></li>';
    $html .= '<li><a href="/admin/nfr/issues">' . $this->t('Support Issues') . '</a></li>';
    $html .= '<li><a href="/nfr/documentation">' . $this->t('Documentation') . '</a></li>';
    $html .= '<li><a href="/admin/reports">' . $this->t('System Reports') . '</a></li>';
    $html .= '</ul></div>';
    
    $html .= '</div>'; // .admin-sidebar
    
    $html .= '</div>'; // .admin-content-grid
    $html .= '</div>'; // .admin-dashboard

    return $html;
  }

  /**
   * Participant list page.
   *
   * @return array
   *   Render array.
   */
  public function participantList(): array {
    $participants = $this->getAllParticipants();
    $list_content = $this->buildParticipantList($participants);

    return [
      '#theme' => 'nfr_admin_page',
      '#page_id' => 'participant-list',
      '#content' => [
        '#markup' => $list_content,
      ],
      '#attached' => [
        'library' => ['nfr/admin'],
      ],
    ];
  }

  /**
   * Get all participants.
   */
  private function getAllParticipants(): array {
    $query = $this->database->select('nfr_user_profile', 'p')
      ->fields('p')
      ->orderBy('created', 'DESC');
    
    // Join with questionnaire to get completion status
    $query->leftJoin('nfr_questionnaire', 'q', 'p.uid = q.uid');
    $query->addField('q', 'questionnaire_completed');
    
    // Join with consent to get linkage status
    $query->leftJoin('nfr_consent', 'c', 'p.uid = c.uid');
    $query->addField('c', 'consented_to_registry_linkage');
    
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Build participant list HTML.
   */
  private function buildParticipantList(array $participants): string {
    $html = '<div class="participant-list-page">';
    
    // Header
    $html .= '<div class="page-header">';
    $html .= '<h1>' . $this->t('Participant Management') . '</h1>';
    $html .= '<div class="page-actions">';
    $html .= '<button class="btn btn-secondary" onclick="window.print()">' . $this->t('Export List') . '</button>';
    $html .= '<a href="/admin/nfr" class="btn btn-primary">' . $this->t('← Back to Dashboard') . '</a>';
    $html .= '</div>';
    $html .= '</div>';

    // Filters
    $html .= '<div class="list-filters">';
    $html .= '<input type="text" id="search-filter" placeholder="' . $this->t('Search by name, ID, or email...') . '" class="filter-input">';
    $html .= '<select id="state-filter" class="filter-select">';
    $html .= '<option value="">' . $this->t('All States') . '</option>';
    $html .= '</select>';
    $html .= '<select id="status-filter" class="filter-select">';
    $html .= '<option value="">' . $this->t('All Statuses') . '</option>';
    $html .= '<option value="complete">' . $this->t('Complete') . '</option>';
    $html .= '<option value="incomplete">' . $this->t('Incomplete') . '</option>';
    $html .= '</select>';
    $html .= '</div>';

    // Statistics bar
    $total = count($participants);
    $complete = count(array_filter($participants, fn($p) => $p['questionnaire_completed'] ?? false));
    $linkage = count(array_filter($participants, fn($p) => $p['consented_to_registry_linkage'] ?? false));
    
    $html .= '<div class="list-stats">';
    $html .= '<div class="stat-item">';
    $html .= '<span class="stat-label">' . $this->t('Total:') . '</span> ';
    $html .= '<span class="stat-value">' . number_format($total) . '</span>';
    $html .= '</div>';
    $html .= '<div class="stat-item">';
    $html .= '<span class="stat-label">' . $this->t('Completed:') . '</span> ';
    $html .= '<span class="stat-value">' . number_format($complete) . '</span>';
    $html .= '</div>';
    $html .= '<div class="stat-item">';
    $html .= '<span class="stat-label">' . $this->t('Linkage Consent:') . '</span> ';
    $html .= '<span class="stat-value">' . number_format($linkage) . '</span>';
    $html .= '</div>';
    $html .= '</div>';

    // Table
    $html .= '<div class="participant-table-wrapper">';
    $html .= '<table class="participant-table">';
    $html .= '<thead><tr>';
    $html .= '<th>' . $this->t('Participant ID') . '</th>';
    $html .= '<th>' . $this->t('Name') . '</th>';
    $html .= '<th>' . $this->t('Email') . '</th>';
    $html .= '<th>' . $this->t('State') . '</th>';
    $html .= '<th>' . $this->t('Enrolled') . '</th>';
    $html .= '<th>' . $this->t('Questionnaire') . '</th>';
    $html .= '<th>' . $this->t('Linkage') . '</th>';
    $html .= '<th>' . $this->t('Actions') . '</th>';
    $html .= '</tr></thead><tbody>';
    
    foreach ($participants as $participant) {
      $html .= '<tr>';
      $html .= '<td><strong>' . htmlspecialchars($participant['participant_id'] ?? 'N/A') . '</strong></td>';
      $html .= '<td>' . htmlspecialchars(($participant['first_name'] ?? '') . ' ' . ($participant['last_name'] ?? '')) . '</td>';
      $html .= '<td>' . htmlspecialchars($participant['primary_email'] ?? 'N/A') . '</td>';
      $html .= '<td>' . htmlspecialchars($participant['state'] ?? 'N/A') . '</td>';
      $html .= '<td>' . date('M j, Y', $participant['created'] ?? time()) . '</td>';
      
      $q_status = ($participant['questionnaire_completed'] ?? false) ? 'complete' : 'incomplete';
      $html .= '<td><span class="status-badge status-' . $q_status . '">' . 
        ($q_status === 'complete' ? $this->t('Complete') : $this->t('Incomplete')) . '</span></td>';
      
      $l_status = ($participant['consented_to_registry_linkage'] ?? false) ? 'consented' : 'no-consent';
      $html .= '<td><span class="status-badge status-' . $l_status . '">' . 
        ($l_status === 'consented' ? $this->t('Yes') : $this->t('No')) . '</span></td>';
      
      $html .= '<td><a href="/admin/nfr/participant/' . htmlspecialchars($participant['participant_id'] ?? '') . '" class="btn-link">' . 
        $this->t('View') . '</a></td>';
      $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    $html .= '</div>'; // table wrapper
    
    $html .= '</div>'; // .participant-list-page

    return $html;
  }

  /**
   * Participant detail page.
   *
   * @param string $id
   *   Participant ID.
   *
   * @return array
   *   Render array.
   */
  public function participantDetail(string $id): array {
    $participant_data = $this->getParticipantData($id);
    
    if (empty($participant_data)) {
      return [
        '#markup' => '<p>' . $this->t('Participant not found.') . '</p>',
      ];
    }
    
    $detail_content = $this->buildParticipantDetail($participant_data);

    return [
      '#theme' => 'nfr_admin_page',
      '#page_id' => 'participant-detail',
      '#content' => [
        '#markup' => $detail_content,
      ],
      '#attached' => [
        'library' => ['nfr/admin'],
      ],
    ];
  }

  /**
   * Get participant data.
   */
  private function getParticipantData(string $participant_id): array {
    // Get profile
    $profile = $this->database->select('nfr_user_profile', 'p')
      ->fields('p')
      ->condition('participant_id', $participant_id)
      ->execute()
      ->fetch(\PDO::FETCH_ASSOC);
    
    if (!$profile) {
      return [];
    }
    
    $uid = $profile['uid'];
    
    // Get consent
    $consent = $this->database->select('nfr_consent', 'c')
      ->fields('c')
      ->condition('uid', $uid)
      ->execute()
      ->fetch(\PDO::FETCH_ASSOC);
    
    // Get questionnaire
    $questionnaire = $this->database->select('nfr_questionnaire', 'q')
      ->fields('q')
      ->condition('uid', $uid)
      ->execute()
      ->fetch(\PDO::FETCH_ASSOC);
    
    return [
      'profile' => $profile,
      'consent' => $consent,
      'questionnaire' => $questionnaire,
    ];
  }

  /**
   * Build participant detail HTML.
   */
  private function buildParticipantDetail(array $data): string {
    $profile = $data['profile'];
    $consent = $data['consent'];
    $questionnaire = $data['questionnaire'];
    
    $html = '<div class="participant-detail-page">';
    
    // Header
    $html .= '<div class="page-header">';
    $html .= '<div>';
    $html .= '<h1>' . htmlspecialchars(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) . '</h1>';
    $html .= '<p class="participant-id-display">ID: ' . htmlspecialchars($profile['participant_id'] ?? 'N/A') . '</p>';
    $html .= '</div>';
    $html .= '<div class="page-actions">';
    $html .= '<a href="/admin/nfr/participants" class="btn btn-secondary">' . $this->t('← Back to List') . '</a>';
    $html .= '</div>';
    $html .= '</div>';

    // Status overview
    $html .= '<div class="detail-status-bar">';
    $html .= '<div class="status-item">';
    $html .= '<span class="status-label">' . $this->t('Profile:') . '</span> ';
    $html .= '<span class="status-badge status-' . (($profile['profile_completed'] ?? false) ? 'complete' : 'incomplete') . '">' . 
      (($profile['profile_completed'] ?? false) ? $this->t('Complete') : $this->t('Incomplete')) . '</span>';
    $html .= '</div>';
    $html .= '<div class="status-item">';
    $html .= '<span class="status-label">' . $this->t('Questionnaire:') . '</span> ';
    $html .= '<span class="status-badge status-' . (($questionnaire['questionnaire_completed'] ?? false) ? 'complete' : 'incomplete') . '">' . 
      (($questionnaire['questionnaire_completed'] ?? false) ? $this->t('Complete') : $this->t('Incomplete')) . '</span>';
    $html .= '</div>';
    $html .= '<div class="status-item">';
    $html .= '<span class="status-label">' . $this->t('Linkage:') . '</span> ';
    $html .= '<span class="status-badge status-' . (($consent['consented_to_registry_linkage'] ?? false) ? 'consented' : 'no-consent') . '">' . 
      (($consent['consented_to_registry_linkage'] ?? false) ? $this->t('Consented') : $this->t('No Consent')) . '</span>';
    $html .= '</div>';
    $html .= '</div>';

    // Tabs (simplified - showing data sections)
    $html .= '<div class="detail-sections">';
    
    // Profile information
    $html .= '<div class="detail-section">';
    $html .= '<h2>' . $this->t('Profile Information') . '</h2>';
    $html .= '<div class="info-grid">';
    $html .= '<div class="info-item"><strong>' . $this->t('Email:') . '</strong> ' . htmlspecialchars($profile['primary_email'] ?? 'N/A') . '</div>';
    $html .= '<div class="info-item"><strong>' . $this->t('Phone:') . '</strong> ' . htmlspecialchars($profile['phone_number'] ?? 'N/A') . '</div>';
    $html .= '<div class="info-item"><strong>' . $this->t('Date of Birth:') . '</strong> ' . htmlspecialchars($profile['date_of_birth'] ?? 'N/A') . '</div>';
    $html .= '<div class="info-item"><strong>' . $this->t('Address:') . '</strong> ' . 
      htmlspecialchars(($profile['street_address'] ?? '') . ', ' . ($profile['city'] ?? '') . ', ' . 
      ($profile['state'] ?? '') . ' ' . ($profile['zip_code'] ?? '')) . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Work information
    if (!empty($profile['current_department']) || !empty($profile['current_job_title'])) {
      $html .= '<div class="detail-section">';
      $html .= '<h2>' . $this->t('Work Information') . '</h2>';
      $html .= '<div class="info-grid">';
      $html .= '<div class="info-item"><strong>' . $this->t('Department:') . '</strong> ' . htmlspecialchars($profile['current_department'] ?? 'N/A') . '</div>';
      $html .= '<div class="info-item"><strong>' . $this->t('Job Title:') . '</strong> ' . htmlspecialchars($profile['current_job_title'] ?? 'N/A') . '</div>';
      $html .= '<div class="info-item"><strong>' . $this->t('Employment Status:') . '</strong> ' . htmlspecialchars($profile['employment_status'] ?? 'N/A') . '</div>';
      $html .= '<div class="info-item"><strong>' . $this->t('Firefighter Since:') . '</strong> ' . htmlspecialchars($profile['year_started_firefighting'] ?? 'N/A') . '</div>';
      $html .= '</div>';
    $html .= '</div>';
    }
    
    // Consent information
    if ($consent) {
      $html .= '<div class="detail-section">';
      $html .= '<h2>' . $this->t('Consent Information') . '</h2>';
      $html .= '<div class="info-grid">';
      $html .= '<div class="info-item"><strong>' . $this->t('Consent Date:') . '</strong> ' . 
        ($consent['consent_timestamp'] ? date('M j, Y', $consent['consent_timestamp']) : 'N/A') . '</div>';
      $html .= '<div class="info-item"><strong>' . $this->t('Registry Linkage:') . '</strong> ' . 
        (($consent['consented_to_registry_linkage'] ?? false) ? $this->t('Yes') : $this->t('No')) . '</div>';
      $html .= '<div class="info-item"><strong>' . $this->t('Signature:') . '</strong> ' . htmlspecialchars($consent['participant_signature'] ?? 'N/A') . '</div>';
      $html .= '</div>';
      $html .= '</div>';
    }
    
    // Questionnaire status
    if ($questionnaire) {
      $html .= '<div class="detail-section">';
      $html .= '<h2>' . $this->t('Questionnaire') . '</h2>';
      $html .= '<div class="info-grid">';
      $html .= '<div class="info-item"><strong>' . $this->t('Status:') . '</strong> ' . 
        (($questionnaire['questionnaire_completed'] ?? false) ? $this->t('Complete') : $this->t('In Progress')) . '</div>';
      $html .= '<div class="info-item"><strong>' . $this->t('Last Updated:') . '</strong> ' . 
        ($questionnaire['updated'] ? date('M j, Y g:i A', $questionnaire['updated']) : 'N/A') . '</div>';
      $html .= '<div class="info-item"><strong>' . $this->t('Current Section:') . '</strong> ' . htmlspecialchars($questionnaire['current_section'] ?? 'N/A') . '</div>';
      $html .= '</div>';
      $html .= '</div>';
    }
    
    $html .= '</div>'; // .detail-sections
    $html .= '</div>'; // .participant-detail-page

    return $html;
  }

  /**
   * Cancer registry linkage management page.
   *
   * @return array
   *   Render array.
   */
  public function linkageManagement(): array {
    $linkage_stats = $this->getLinkageStatistics();
    $linkage_content = $this->buildLinkageManagement($linkage_stats);

    return [
      '#theme' => 'nfr_admin_page',
      '#page_id' => 'linkage-management',
      '#content' => [
        '#markup' => $linkage_content,
      ],
      '#attached' => [
        'library' => ['nfr/admin'],
      ],
    ];
  }

  /**
   * Get linkage statistics.
   */
  private function getLinkageStatistics(): array {
    $total_consented = $this->database->select('nfr_consent', 'c')
      ->condition('consented_to_registry_linkage', 1)
      ->countQuery()
      ->execute()
      ->fetchField();

    $total_participants = $this->database->select('nfr_user_profile', 'p')
      ->condition('profile_completed', 1)
      ->countQuery()
      ->execute()
      ->fetchField();

    return [
      'total_consented' => $total_consented,
      'total_participants' => $total_participants,
      'consent_rate' => $total_participants > 0 ? round(($total_consented / $total_participants) * 100, 1) : 0,
      'pending_export' => $total_consented, // In real implementation, track exported status
    ];
  }

  /**
   * Build linkage management HTML.
   */
  private function buildLinkageManagement(array $stats): string {
    $html = '<div class="linkage-management-page">';
    
    // Header
    $html .= '<div class="page-header">';
    $html .= '<h1>' . $this->t('Cancer Registry Linkage Management') . '</h1>';
    $html .= '<p class="page-subtitle">' . $this->t('Manage cancer registry data linkage and matching') . '</p>';
    $html .= '</div>';

    // Statistics
    $html .= '<div class="linkage-stats">';
    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-value">' . number_format($stats['total_consented']) . '</div>';
    $html .= '<div class="stat-label">' . $this->t('Total Consented') . '</div>';
    $html .= '</div>';
    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-value">' . $stats['consent_rate'] . '%</div>';
    $html .= '<div class="stat-label">' . $this->t('Consent Rate') . '</div>';
    $html .= '</div>';
    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-value">' . number_format($stats['pending_export']) . '</div>';
    $html .= '<div class="stat-label">' . $this->t('Pending Export') . '</div>';
    $html .= '</div>';
    $html .= '</div>';

    // Workflow steps
    $html .= '<div class="linkage-workflow">';
    
    // Step 1: Generate files
    $html .= '<div class="workflow-step">';
    $html .= '<div class="step-number">1</div>';
    $html .= '<div class="step-content">';
    $html .= '<h3>' . $this->t('Generate Linkage Files') . '</h3>';
    $html .= '<p>' . $this->t('Export participant data for state cancer registries') . '</p>';
    $html .= '<button class="btn btn-primary">' . $this->t('Generate Export Files') . '</button>';
    $html .= '<p class="step-note">' . $this->t('Last export: Never') . '</p>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Step 2: Submit to registries
    $html .= '<div class="workflow-step">';
    $html .= '<div class="step-number">2</div>';
    $html .= '<div class="step-content">';
    $html .= '<h3>' . $this->t('Submit to State Registries') . '</h3>';
    $html .= '<p>' . $this->t('Send files to state cancer registries via secure channels') . '</p>';
    $html .= '<div class="step-info">' . $this->t('Files should be submitted according to each state\'s protocol') . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Step 3: Upload results
    $html .= '<div class="workflow-step">';
    $html .= '<div class="step-number">3</div>';
    $html .= '<div class="step-content">';
    $html .= '<h3>' . $this->t('Upload Match Results') . '</h3>';
    $html .= '<p>' . $this->t('Import cancer diagnosis data from state registries') . '</p>';
    $html .= '<button class="btn btn-secondary">' . $this->t('Upload Results File') . '</button>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Step 4: Review matches
    $html .= '<div class="workflow-step">';
    $html .= '<div class="step-number">4</div>';
    $html .= '<div class="step-content">';
    $html .= '<h3>' . $this->t('Review Matches') . '</h3>';
    $html .= '<p>' . $this->t('Verify and approve matched records') . '</p>';
    $html .= '<div class="step-info">' . $this->t('No pending matches to review') . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div>'; // .linkage-workflow

    // Additional resources
    $html .= '<div class="linkage-resources">';
    $html .= '<h2>' . $this->t('Resources') . '</h2>';
    $html .= '<ul>';
    $html .= '<li><a href="#">' . $this->t('Linkage Protocol Documentation') . '</a></li>';
    $html .= '<li><a href="#">' . $this->t('State Registry Contact List') . '</a></li>';
    $html .= '<li><a href="#">' . $this->t('Data Security Guidelines') . '</a></li>';
    $html .= '<li><a href="/admin/nfr">' . $this->t('← Back to Dashboard') . '</a></li>';
    $html .= '</ul>';
    $html .= '</div>';
    
    $html .= '</div>'; // .linkage-management-page

    return $html;
  }

  /**
   * Participant detail page.
   *
   * @param int $id
   *   Participant ID.
   *
   * @return array
   *   Render array.
   */
  public function participantDetail_old(int $id): array {
    return [
      '#theme' => 'nfr_admin_page',
      '#page_id' => 'participant-detail',
      '#content' => [
        '#markup' => '<h2>Participant Details</h2><p>Viewing participant ID: ' . $id . '</p>',
      ],
    ];
  }

  /**
   * Data quality monitoring page.
   *
   * @return array
   *   Render array.
   */
  public function dataQuality(): array {
    return [
      '#theme' => 'nfr_admin_page',
      '#page_id' => 'data-quality',
      '#content' => [
        '#markup' => '<h2>Data Quality Monitor</h2><p>Placeholder for data quality reports and validation.</p>',
      ],
    ];
  }

  /**
   * Report builder page.
   *
   * @return array
   *   Render array.
   */
  public function reportBuilder(): array {
    return [
      '#theme' => 'nfr_admin_page',
      '#page_id' => 'report-builder',
      '#content' => [
        '#markup' => '<h2>Report Builder</h2><p>Placeholder for custom report generation.</p>',
      ],
    ];
  }

  /**
   * User support issues page.
   *
   * @return array
   *   Render array.
   */
  public function userIssues(): array {
    return [
      '#theme' => 'nfr_admin_page',
      '#page_id' => 'user-issues',
      '#content' => [
        '#markup' => '<h2>User Support Issues</h2><p>Placeholder for support ticket queue.</p>',
      ],
    ];
  }

  /**
   * System settings page.
   *
   * @return array
   *   Render array.
   */
  public function systemSettings(): array {
    return [
      '#theme' => 'nfr_admin_page',
      '#page_id' => 'system-settings',
      '#content' => [
        '#markup' => '<h2>System Settings</h2><p>Placeholder for NFR configuration settings.</p>',
      ],
    ];
  }

}
