<?php

namespace Drupal\job_application_automation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides route responses for the Job Application Automation module.
 */
class JobApplicationController extends ControllerBase {

  /**
   *    // Phase 4: Application Manage    // Phase 4: Application Management
    $build['phase4'] = [
      '#type' => 'container',    $build['phase6'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-6']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 6: Analytics & Optimization',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $success_rate . '%</div>
                       <div class="stat-label">Success Rate</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="#" class="phase-button">📊 View Analytics</a>
                       <a href="#" class="phase-button">🎯 Get Recommendations</a>
                       <a href="#" class="phase-button">⚙️ Optimize Process</a>',
        ],
      ],
    ];' => ['class' => ['phase-section', 'phase-4']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 4: Application Management',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $active_applications . '</div>
                       <div class="stat-label">Active Applications</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="#" class="phase-button">📝 Submit Applications</a>
                       <a href="#" class="phase-button">📄 Customize Resume</a>
                       <a href="#" class="phase-button">📊 Track Status</a>',
        ],
      ],
    ];se4'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-4']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 4: Application Management',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $active_applications . '</div>
                       <div class="stat-label">Active Applications</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="#" class="phase-button">📝 Submit Applications</a>
                       <a href="#" class="phase-button">📄 Customize Resume</a>
                       <a href="#" class="phase-button">📊 Track Status</a>',
        ],
      ],
    ]; for authenticated users.
   *
   * @return array
   *   A simple renderable array with Hello World message.
   */
  public function home() {
    return [
      '#markup' => '<h1>Hello World!</h1><p>Job Application Automation Module is working for regular users!</p><p>Welcome, you are logged in successfully!</p>',
    ];
  }

  /**
   * Returns an administrative dashboard for job applications.
   *
   * @return array
   *   A comprehensive renderable array for the administrative dashboard.
   */
  public function dashboard() {
    $build = [];
    $current_user = \Drupal::currentUser();
    
    // Attach our custom CSS library
    $build['#attached']['library'][] = 'job_application_automation/home-page';
    
    // Dashboard Header
    $build['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['job-application-hero']],
      '#value' => '<h1>Job Application Dashboard</h1>
                   <div class="subtitle">Your Complete Job Search Management System</div>',
    ];
    
    // Check if user is authenticated
    if ($current_user->isAuthenticated() && $current_user->id() > 0) {
      return $this->buildAuthenticatedView($build, $current_user);
    } else {
      return $this->buildUnauthenticatedView($build);
    }
    
    // Quick Stats Section
    $build['stats'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['stats-overview']],
      '#value' => '<div class="stat-card">
                     <div class="stat-number">0</div>
                     <div class="stat-label">Total Applications</div>
                   </div>
                   <div class="stat-card">
                     <div class="stat-number">0</div>
                     <div class="stat-label">Active Applications</div>
                   </div>
                   <div class="stat-card">
                     <div class="stat-number">0</div>
                     <div class="stat-label">Pending Reviews</div>
                   </div>
                   <div class="stat-card">
                     <div class="stat-number">0</div>
                     <div class="stat-label">This Month</div>
                   </div>',
    ];
    
    // Administrative Actions
    $add_url = Url::fromRoute('job_application_automation.add');
    // $settings_url = Url::fromRoute('job_application_automation.settings');
    
    $build['actions'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['quick-actions']],
      '#value' => '<h3>Administrative Actions</h3>
                   <div class="action-buttons">
                     <a href="' . $add_url->toString() . '" class="action-button primary">+ Add New Application</a>
                     <a href="#" class="action-button">View All Applications</a>
                     <a href="#" class="action-button secondary disabled">System Settings</a>
                     <a href="#" class="action-button">Export Reports</a>
                   </div>',
    ];
    
    // Management Features Grid
    $build['management'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['features-grid']],
      '#value' => '<div class="feature-card">
                     <h3>Application Management</h3>
                     <p>View, edit, and manage all job applications in the system. Monitor status changes and track progress across all users.</p>
                   </div>
                   <div class="feature-card">
                     <h3>User Permissions</h3>
                     <p>Configure user access levels and permissions for different aspects of the job application system.</p>
                   </div>
                   <div class="feature-card">
                     <h3>System Configuration</h3>
                     <p>Customize application workflows, notification settings, and integration parameters.</p>
                   </div>
                   <div class="feature-card">
                     <h3>Reporting & Analytics</h3>
                     <p>Generate comprehensive reports and analyze job application trends and performance metrics.</p>
                   </div>',
    ];
    
    // Recent Activity Section (placeholder)
    $build['recent_activity'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['no-applications']],
      '#value' => '<h4>Recent Activity</h4>
                   <p>No recent activity to display. Application activity will appear here as users interact with the system.</p>',
    ];
    
    // Main container wrapper
    $build['#prefix'] = '<div class="job-application-home">';
    $build['#suffix'] = '</div>';
    
    return $build;
  }

  /**
   * View a specific job application.
   *
   * @param mixed $job_application
   *   The job application entity.
   *
   * @return array
   *   A renderable array for the job application view.
   */
  public function view($job_application) {
    return [
      '#markup' => '<h2>Job Application View</h2><p>Details for job application ID: ' . $job_application . '</p>',
    ];
  }

  /**
   * Build the view for unauthenticated users.
   */
  private function buildUnauthenticatedView($build) {
    $build['auth_required'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['process-phase', 'phase-1', 'authentication-required']],
      '#value' => '<h3>Please Sign In</h3>
                   <p>Sign in or create an account to access your job application dashboard.</p>
                   <a href="/user/login" class="auth-button primary">Sign In</a>
                   <a href="/user/register" class="auth-button secondary">Create Account</a>',
    ];
    
    $build['#prefix'] = '<div class="job-application-home unauthenticated">';
    $build['#suffix'] = '</div>';
    
    return $build;
  }

  /**
   * Build the view for authenticated users.
   */
  private function buildAuthenticatedView($build, $current_user) {
    $user_name = $current_user->getDisplayName();
    
    // Calculate stats
    $profile_completion = $this->calculateProfileCompletion($current_user);
    $target_companies = $this->getTargetCompaniesCount($current_user);
    $matched_jobs = $this->getMatchedJobsCount($current_user);
    $active_applications = $this->getActiveApplicationsCount($current_user);
    
    // Welcome message
    $build['welcome'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['user-welcome']],
      '#value' => '<div class="user-welcome">Welcome back, ' . $user_name . '! Let\'s continue your job search journey.</div>',
    ];
    
    // Phase 1: Profile Setup
    $user_edit_url = Url::fromRoute('entity.user.edit_form', ['user' => $current_user->id()]);
    $build['phase1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-1']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 1: Profile Setup',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $profile_completion . '%</div>
                       <div class="stat-label">Profile Complete</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="' . $user_edit_url->toString() . '" class="phase-button">Edit Profile</a>
                       <a href="#" class="phase-button">Upload Resume</a>
                       <a href="#" class="phase-button">Set Preferences</a>',
        ],
      ],
    ];
    
    // Phase 2: Target Companies
    $build['phase2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-2']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 2: Target Companies',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $target_companies . '</div>
                       <div class="stat-label">Target Companies</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="/job-applications/companies-overview" class="phase-button">📊 View All</a>
                       <a href="/job-applications/bulk-import-companies" class="phase-button">📥 Bulk Import</a>
                       <a href="/node/add/company" class="phase-button">+ Add One</a>',
        ],
      ],
    ];
    
    // Phase 3: Job Discovery
    $build['phase3'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-3']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 3: AI Job Discovery',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $matched_jobs . '</div>
                       <div class="stat-label">Jobs Discovered</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="#" class="phase-button">🔍 Start Discovery</a>
                       <a href="#" class="phase-button">⚙️ Configure Matching</a>
                       <a href="#" class="phase-button">📋 View Matches</a>',
        ],
      ],
    ];
    
    // Phase 4: Application Management
    $build['phase4'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['phase-section', 'phase-4']],
      '#value' => '<div class="phase-header">
                     <h3>Phase 4: Application Management</h3>
                   </div>
                   <div class="phase-content">
                     <div class="phase-stat">
                       <div class="stat-number">' . $active_applications . '</div>
                       <div class="stat-label">Active Applications</div>
                     </div>
                     <div class="phase-actions">
                       <a href="#" class="phase-button">📝 Submit Applications</a>
                       <a href="#" class="phase-button">� Customize Resume</a>
                       <a href="#" class="phase-button">📊 Track Status</a>
                     </div>
                   </div>',
    ];
    
    // Phase 5: Interview & Follow-up
    $interviews_scheduled = 0; // Placeholder
    $build['phase5'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-5']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 5: Interview & Follow-up',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $interviews_scheduled . '</div>
                       <div class="stat-label">Interviews Scheduled</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="#" class="phase-button">📅 Schedule Interviews</a>
                       <a href="#" class="phase-button">💌 Send Follow-ups</a>
                       <a href="#" class="phase-button">📈 Track Progress</a>',
        ],
      ],
    ];
    
    // Phase 6: Analytics & Optimization
    $success_rate = 0; // Placeholder
    $build['phase6'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['phase-section', 'phase-6']],
      '#value' => '<div class="phase-header">
                     <h3>Phase 6: Analytics & Optimization</h3>
                   </div>
                   <div class="phase-content">
                     <div class="phase-stat">
                       <div class="stat-number">' . $success_rate . '%</div>
                       <div class="stat-label">Success Rate</div>
                     </div>
                     <div class="phase-actions">
                       <a href="#" class="phase-button">� View Analytics</a>
                       <a href="#" class="phase-button">🎯 Get Recommendations</a>
                       <a href="#" class="phase-button">⚙️ Optimize Process</a>
                     </div>
                   </div>',
    ];
    
    // Add CSS styles
    $build['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '
          .job-dashboard { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
          .user-welcome { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; margin: 20px 0; border-radius: 10px; text-align: center; font-size: 1.2em; }
          .phase-section { background: white; border: 2px solid #e2e8f0; border-radius: 12px; margin: 20px 0; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s; }
          .phase-section:hover { transform: translateY(-2px); }
          .phase-1 { border-left: 5px solid #48bb78; }
          .phase-2 { border-left: 5px solid #4299e1; }
          .phase-3 { border-left: 5px solid #ed8936; }
          .phase-4 { border-left: 5px solid #9f7aea; }
          .phase-5 { border-left: 5px solid #f56565; }
          .phase-6 { border-left: 5px solid #38b2ac; }
          .phase-header h3 { color: #2d3748; margin: 0 0 20px 0; font-size: 1.3em; font-weight: 600; }
          .phase-content { display: flex; justify-content: space-between; align-items: center; }
          .phase-stat { text-align: center; }
          .stat-number { font-size: 2.2em; font-weight: bold; color: #1a365d; margin-bottom: 5px; }
          .stat-label { color: #4a5568; font-size: 1em; }
          .phase-actions { display: flex; gap: 12px; flex-wrap: wrap; }
          .phase-button { background: #4299e1; color: white; padding: 10px 16px; text-decoration: none; border-radius: 6px; font-size: 0.9em; transition: background 0.2s; }
          .phase-button:hover { background: #3182ce; }
          .phase-1 .phase-button { background: #48bb78; }
          .phase-1 .phase-button:hover { background: #38a169; }
          .phase-2 .phase-button { background: #4299e1; }
          .phase-2 .phase-button:hover { background: #3182ce; }
          .phase-3 .phase-button { background: #ed8936; }
          .phase-3 .phase-button:hover { background: #dd6b20; }
          .phase-4 .phase-button { background: #9f7aea; }
          .phase-4 .phase-button:hover { background: #805ad5; }
          .phase-5 .phase-button { background: #f56565; }
          .phase-5 .phase-button:hover { background: #e53e3e; }
          .phase-6 .phase-button { background: #38b2ac; }
          .phase-6 .phase-button:hover { background: #319795; }
          @media (max-width: 768px) {
            .phase-content { flex-direction: column; gap: 20px; }
            .phase-actions { justify-content: center; }
          }
        ',
      ],
      'job-dashboard-styles'
    ];
    
    $build['#prefix'] = '<div class="job-dashboard">';
    $build['#suffix'] = '</div>';
    
    return $build;
  }

  /**
   * Calculate user profile completion percentage.
   */
  private function calculateProfileCompletion($user) {
    // Simplified calculation
    return 75; // Placeholder
  }

  /**
   * Get count of target companies.
   */
  private function getTargetCompaniesCount($user) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'company')
      ->condition('status', 1)
      ->accessCheck(TRUE);
    return count($query->execute());
  }

  /**
   * Get count of matched jobs.
   */
  private function getMatchedJobsCount($user) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'job_posting')
      ->condition('status', 1)
      ->accessCheck(TRUE);
    return count($query->execute());
  }

  /**
   * Get count of active applications.
   */
  private function getActiveApplicationsCount($user) {
    return 0; // Placeholder
  }

  /**
   * Manage target companies.
   */
  public function manageTargetCompanies() {
    $build = [];
    $build['content'] = [
      '#markup' => '<h2>Manage Target Companies</h2><p>Company management interface.</p>',
    ];
    return $build;
  }

  /**
   * Save target companies.
   */
  public function saveTargetCompanies() {
    return new \Symfony\Component\HttpFoundation\RedirectResponse('/job-applications');
  }

  /**
   * Companies overview.
   */
  public function companiesOverview() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'company')
      ->condition('status', 1)
      ->accessCheck(TRUE);
    $company_count = count($query->execute());

    $build = [];
    $build['content'] = [
      '#markup' => '<h2>Companies Overview</h2><p>Total companies: ' . $company_count . '</p>',
    ];
    return $build;
  }

  /**
   * Get the title for a job application.
   *
   * @param mixed $job_application
   *   The job application entity.
   *
   * @return string
   *   The title for the job application.
   */
  public function getTitle($job_application) {
    return 'Job Application #' . $job_application;
  }

}