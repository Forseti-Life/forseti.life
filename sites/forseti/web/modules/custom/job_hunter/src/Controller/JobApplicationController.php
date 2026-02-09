<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides route responses for the Job Application Automation module.
 */
class JobApplicationController extends ControllerBase {

  /**
   * Returns a simple homepage for authenticated users.
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
    $current_user = $this->currentUser();
    
    // If user is not authenticated, redirect to registration with message.
    if ($current_user->isAnonymous()) {
      $this->messenger()->addWarning($this->t('Job Hunter is reserved for community members. Please register for a free account to get started.'));
      $url = Url::fromRoute('user.register');
      return new RedirectResponse($url->toString());
    }
    
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    // Build dashboard content directly
    $content = [];
    $content['#attached']['library'][] = 'job_hunter/job-hunter-home';
    
    // Dashboard Header
    $content['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['job-application-hero']],
      '#value' => '<h1>Job Application Dashboard</h1>
                   <div class="subtitle">Your Complete Job Search Management System</div>',
    ];
    
    // Check if user is authenticated
    if ($current_user->isAuthenticated() && $current_user->id() > 0) {
      $content = $this->buildAuthenticatedView($content, $current_user);
    } else {
      $content = $this->buildUnauthenticatedView($content);
    }
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
    ];
    
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
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    $content = [
      '#markup' => '<h2>Job Application View</h2><p>Details for job application ID: ' . $job_application . '</p>',
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
    ];
    
    return $build;
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
    $saved_jobs = $this->getSavedJobsCount($current_user);
    
    // URLs
    $user_edit_url = Url::fromRoute('job_hunter.user_profile_edit');
    $job_paste_url = Url::fromRoute('job_hunter.job_paste');
    $jobs_list_url = Url::fromRoute('job_hunter.jobs_list');
    
    // Welcome message
    $build['welcome'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['user-welcome']],
      '#value' => '<div class="user-welcome">Welcome back, ' . $user_name . '!</div>',
    ];
    
    // Queue Controls Section (visible to all authenticated users)
    $build['queue_controls'] = $this->buildQueueControlsSection();
    
    // ========================================
    // PROCESS FLOW 1: Tailored Resume
    // ========================================
    $build['flow1_header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['flow-header', 'flow-tailored']],
      '#value' => '<h2>📄 Tailored Resume Process</h2>
                   <p class="flow-description">Create job-specific resumes by uploading your profile and matching to job descriptions.</p>',
    ];
    
    // Step 1: Profile Setup
    $build['tailored_step1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-profile']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 1</div>
                     <div class="phase-info">
                       <h3>Upload Resume & Clean Up Profile</h3>
                       <p>Import your resume, parse it with AI, and refine your consolidated profile.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">' . $profile_completion . '%</div>
                       <div class="stat-label">Profile Complete</div>
                     </div>
                     <div class="phase-actions">
                       <a href="' . $user_edit_url->toString() . '" class="phase-button primary">Edit Profile</a>
                     </div>',
      ],
    ];
    
    // Step 2: Job Description & Tailored Resume
    $build['tailored_step2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-tailoring']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 2</div>
                     <div class="phase-info">
                       <h3>Submit Job Description → Get Tailored Resume</h3>
                       <p>Paste a job posting to generate an AI-tailored resume and downloadable PDF.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">' . $saved_jobs . '</div>
                       <div class="stat-label">Saved Jobs</div>
                     </div>
                     <div class="phase-actions">
                       <a href="' . $job_paste_url->toString() . '" class="phase-button primary">+ Add Job Posting</a>
                       <a href="' . $jobs_list_url->toString() . '" class="phase-button">View All Jobs</a>
                     </div>',
      ],
    ];
    
    // ========================================
    // PROCESS FLOW 2: Automated Job Applications (Future)
    // ========================================
    $build['flow2_header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['flow-header', 'flow-automated']],
      '#value' => '<h2>🤖 Automated Job Applications</h2>
                   <p class="flow-description">Coming soon: Automated job discovery and application submission.</p>
                   <span class="status-badge not-implemented">Not Fully Implemented</span>',
    ];
    
    // Step 1: Profile Setup (shared with Tailored Resume process)
    $build['automated_step1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-profile']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 1</div>
                     <div class="phase-info">
                       <h3>Upload Resume & Clean Up Profile</h3>
                       <p>Import your resume, parse it with AI, and refine your consolidated profile.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">' . $profile_completion . '%</div>
                       <div class="stat-label">Profile Complete</div>
                     </div>
                     <div class="phase-actions">
                       <a href="' . $user_edit_url->toString() . '" class="phase-button primary">Edit Profile</a>
                     </div>',
      ],
    ];
    
    // Step 2: AI Job Discovery & Search
    $build['automated_step2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-discovery']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 2</div>
                     <div class="phase-info">
                       <h3>AI Job Discovery & Search</h3>
                       <p>Search and discover jobs using multiple sources. Companies are automatically added to your targets when you save jobs.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">' . $saved_jobs . '</div>
                       <div class="stat-label">Jobs Saved</div>
                       <div class="stat-sublabel">' . $target_companies . ' companies tracked</div>
                     </div>
                     <div class="phase-actions">
                       <a href="/jobhunter/job-discovery" class="phase-button primary">Search Jobs</a>
                       <a href="/jobhunter/jobs" class="phase-button">View Saved Jobs</a>
                     </div>',
      ],
    ];
    
    // Step 3: Application Submission
    $build['automated_step3'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-submission', 'disabled']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 3</div>
                     <div class="phase-info">
                       <h3>Application Submission</h3>
                       <p>Auto-apply to jobs with tailored resumes and cover letters.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">0</div>
                       <div class="stat-label">Auto-Applied</div>
                     </div>
                     <div class="phase-actions">
                       <a href="/jobhunter/application-submission" class="phase-button">View Submissions</a>
                     </div>
                     <div class="coming-soon-badge">Coming Soon</div>',
      ],
    ];
    
    // Step 4: Interview & Follow-up
    $build['automated_step4'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-interview', 'disabled']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 4</div>
                     <div class="phase-info">
                       <h3>Interview & Follow-up</h3>
                       <p>Track application status, schedule interviews, and manage follow-ups.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">0</div>
                       <div class="stat-label">Interviews</div>
                     </div>
                     <div class="phase-actions">
                       <a href="/jobhunter/interview-followup" class="phase-button">Manage Pipeline</a>
                     </div>
                     <div class="coming-soon-badge">Coming Soon</div>',
      ],
    ];
    
    // Step 5: Analytics & Optimization
    $build['automated_step5'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-analytics', 'disabled']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 6</div>
                     <div class="phase-info">
                       <h3>Analytics & Optimization</h3>
                       <p>Measure success rates, identify patterns, and optimize your strategy.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">--</div>
                       <div class="stat-label">Success Rate</div>
                     </div>
                     <div class="phase-actions">
                       <a href="/jobhunter/analytics" class="phase-button">View Analytics</a>
                     </div>
                     <div class="coming-soon-badge">Coming Soon</div>',
      ],
    ];
    
    // Add CSS styles
    $build['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '
          .job-dashboard { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
          .user-welcome { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; margin: 20px 0; border-radius: 10px; text-align: center; font-size: 1.2em; }
          
          /* Flow Headers */
          .flow-header { margin: 40px 0 20px 0; padding: 20px; border-radius: 10px; }
          .flow-header h2 { margin: 0 0 10px 0; font-size: 1.5em; }
          .flow-header .flow-description { margin: 0; color: #4a5568; }
          .flow-tailored { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; }
          .flow-tailored .flow-description { color: rgba(255,255,255,0.9); }
          .flow-automated { background: #e2e8f0; color: #2d3748; border: 2px dashed #a0aec0; }
          .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.8em; margin-top: 10px; }
          .status-badge.not-implemented { background: #fbd38d; color: #744210; }
          
          /* Phase Sections */
          .phase-section { background: white; border: 2px solid #e2e8f0; border-radius: 12px; margin: 15px 0; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative; }
          .phase-section.disabled { opacity: 0.7; }
          .phase-profile { border-left: 5px solid #48bb78; }
          .phase-tailoring { border-left: 5px solid #d69e2e; }
          .phase-companies { border-left: 5px solid #4299e1; }
          .phase-discovery { border-left: 5px solid #9f7aea; }
          .phase-submission { border-left: 5px solid #ed8936; }
          .phase-interview { border-left: 5px solid #f56565; }
          .phase-analytics { border-left: 5px solid #38b2ac; }
          
          /* Coming Soon Badge */
          .coming-soon-badge { 
            position: absolute; 
            top: 10px; 
            right: 10px; 
            background: #fbd38d; 
            color: #744210; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 0.7em; 
            font-weight: bold;
          }
          
          /* Phase Content */
          .phase-content { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
          .step-indicator { background: #1a365d; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; font-size: 0.9em; white-space: nowrap; }
          .phase-info { flex: 1; min-width: 200px; }
          .phase-info h3 { margin: 0 0 5px 0; color: #2d3748; font-size: 1.1em; }
          .phase-info p { margin: 0; color: #718096; font-size: 0.9em; }
          .phase-stat { text-align: center; min-width: 100px; }
          .stat-number { font-size: 2em; font-weight: bold; color: #1a365d; }
          .stat-label { color: #4a5568; font-size: 0.85em; }
          .phase-actions { display: flex; gap: 10px; flex-wrap: wrap; }
          .phase-button { padding: 10px 18px; text-decoration: none; border-radius: 6px; font-size: 0.9em; transition: all 0.2s; }
          .phase-button.primary { background: #4299e1; color: white; }
          .phase-button.primary:hover { background: #3182ce; }
          .phase-button:not(.primary) { background: #e2e8f0; color: #2d3748; }
          .phase-button:not(.primary):hover { background: #cbd5e0; }
          
          /* Future Steps */
          .future-steps { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 15px 0; }
          .future-placeholder ul { margin: 10px 0 0 0; padding-left: 20px; color: #718096; }
          .future-placeholder li { margin: 5px 0; }
          
          @media (max-width: 768px) {
            .phase-content { flex-direction: column; text-align: center; }
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
    // Use the UserProfileService for real calculation
    $userProfileService = \Drupal::service('job_hunter.user_profile_service');
    $user_entity = User::load($user->id());
    if ($user_entity) {
      return $userProfileService->calculateProfileCompleteness($user_entity);
    }
    return 0;
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
   * Get count of saved job postings.
   */
  private function getSavedJobsCount($user) {
    try {
      $count = \Drupal::database()->select('jobhunter_job_requirements', 'j')
        ->countQuery()
        ->execute()
        ->fetchField();
      return (int) $count;
    }
    catch (\Exception $e) {
      return 0;
    }
  }

  /**
   * Manage target companies.
   */
  public function manageTargetCompanies() {
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    $database = \Drupal::database();
    
    // Query companies from jobhunter_companies table
    $query = $database->select('jobhunter_companies', 'c')
      ->fields('c')
      ->orderBy('name', 'ASC');
    $companies = $query->execute()->fetchAll();
    
    // Count jobs per company
    $job_counts = [];
    $job_query = $database->select('jobhunter_job_requirements', 'j')
      ->fields('j', ['company_id'])
      ->condition('status', 'active')
      ->groupBy('company_id');
    $job_query->addExpression('COUNT(*)', 'job_count');
    $job_results = $job_query->execute()->fetchAllKeyed(0, 1);
    
    $content = [];
    $content['#attached']['library'][] = 'job_hunter/job-hunter-home';
    
    // Header with stats
    $total_companies = count($companies);
    $active_companies = count(array_filter($companies, fn($c) => $c->active == 1));
    
    $content['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['target-companies-header']],
      '#value' => '<h2>🎯 Target Companies</h2>
                   <p class="subtitle">Your primary target companies that Job Hunter actively monitors for opportunities</p>
                   <p class="description">These are the organizations you want to work for. The Job Hunter AI will prioritize opportunities from these companies when discovering jobs, tracking applications, and tailoring resumes.</p>
                   <div class="stats-bar">
                     <div class="stat"><span class="stat-number">' . $total_companies . '</span> Target Companies</div>
                     <div class="stat"><span class="stat-number">' . $active_companies . '</span> Active</div>
                     <div class="stat"><span class="stat-number">' . array_sum($job_results) . '</span> Jobs Found</div>
                   </div>',
    ];
    
    // Add company button
    $content['add_button'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['action-bar']],
      '#value' => '<a href="/jobhunter/bulk-import-companies" class="btn-add-company">+ Add Companies</a>',
    ];
    
    // Get companies from job postings (extracted via AI)
    $job_companies = $this->getCompaniesFromJobPostings();
    
    // All companies section - filterable list from job postings
    if (!empty($job_companies)) {
      $content['all_companies_header'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['section-header']],
        '#value' => '<h3>📋 Companies from Job Postings</h3>
                     <p class="section-description">Companies extracted from job descriptions you\'ve added</p>',
      ];
      
      $content['filter'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['filter-bar']],
        '#value' => '<input type="text" id="company-filter" placeholder="Filter companies by name..." class="company-filter-input">
                     <span class="filter-count">Showing <span id="visible-count">' . count($job_companies) . '</span> of ' . count($job_companies) . ' companies</span>',
      ];
      
      $all_rows = '';
      foreach ($job_companies as $company_name => $job_count) {
        // Check if already in target companies
        $exists = $database->select('jobhunter_companies', 'c')
          ->condition('name', $company_name)
          ->countQuery()
          ->execute()
          ->fetchField();
        
        $action = $exists 
          ? '<span class="already-added">✓ Already in targets</span>'
          : '<a href="#" class="btn-add-quick" data-company="' . htmlspecialchars($company_name) . '" onclick="addCompanyQuick(this); return false;">+ Add to Targets</a>';
        
        $all_rows .= '<tr class="company-row" data-company-name="' . strtolower(htmlspecialchars($company_name)) . '">
          <td class="company-name-cell"><strong>' . htmlspecialchars($company_name) . '</strong></td>
          <td class="job-count-cell"><span class="badge">' . $job_count . '</span></td>
          <td class="action-cell">' . $action . '</td>
        </tr>';
      }
      
      $content['all_companies_table'] = [
        '#type' => 'inline_template',
        '#template' => '<table class="all-companies-table">
          <thead>
            <tr>
              <th>Company Name</th>
              <th>Job Postings</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="companies-table-body">{{ rows|raw }}</tbody>
        </table>',
        '#context' => ['rows' => $all_rows],
      ];
    }
    
    if (empty($companies)) {
      $content['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['empty-state']],
        '#value' => '<div class="empty-icon">🏢</div>
                     <h3>No Target Companies Yet</h3>
                     <p>Start by adding companies you\'re interested in working for.</p>
                     <a href="/jobhunter/bulk-import-companies" class="btn-primary">Add Your First Company</a>',
      ];
    } else {
      // Build companies table
      $rows = '';
      foreach ($companies as $company) {
        $job_count = $job_results[$company->id] ?? 0;
        $status_class = $company->active ? 'status-active' : 'status-inactive';
        $status_text = $company->active ? 'Active' : 'Inactive';
        
        $website_link = $company->website 
          ? '<a href="' . htmlspecialchars($company->website) . '" target="_blank">🔗 Website</a>'
          : '<span class="text-muted">No website</span>';
        
        $careers_link = $company->careers_page_url
          ? '<a href="' . htmlspecialchars($company->careers_page_url) . '" target="_blank">💼 Careers</a>'
          : '';
        
        $rows .= '<tr>
          <td class="company-name">
            <strong>' . htmlspecialchars($company->name) . '</strong>
            ' . ($company->industry ? '<div class="company-industry">' . htmlspecialchars($company->industry) . '</div>' : '') . '
          </td>
          <td class="company-location">' . ($company->location ? htmlspecialchars($company->location) : '-') . '</td>
          <td class="company-links">' . $website_link . ' ' . $careers_link . '</td>
          <td class="company-jobs"><span class="badge">' . $job_count . '</span></td>
          <td class="company-status"><span class="' . $status_class . '">' . $status_text . '</span></td>
          <td class="company-actions">
            <a href="/jobhunter/companies/' . $company->id . '/edit" class="btn-edit">Edit</a>
            <a href="/jobhunter/companies/' . $company->id . '/delete" class="btn-delete" onclick="return confirm(\'Delete ' . htmlspecialchars($company->name) . '?\')">Delete</a>
          </td>
        </tr>';
      }
      
      $content['table'] = [
        '#type' => 'inline_template',
        '#template' => '<table class="companies-table">
          <thead>
            <tr>
              <th>Company</th>
              <th>Location</th>
              <th>Links</th>
              <th>Jobs</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>{{ rows|raw }}</tbody>
        </table>',
        '#context' => ['rows' => $rows],
      ];
    }
    
    // Add CSS
    $content['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '
          .target-companies-header { margin-bottom: 30px; }
          .target-companies-header h2 { margin: 0 0 10px 0; font-size: 2em; }
          .target-companies-header .subtitle { color: #666; font-size: 1.1em; margin-bottom: 20px; }
          .stats-bar { display: flex; gap: 30px; margin-top: 15px; }
          .stat { background: #f8f9fa; padding: 15px 20px; border-radius: 8px; }
          .stat-number { font-size: 1.8em; font-weight: bold; color: #2c5282; display: block; }
          .stat-label { font-size: 0.9em; color: #666; }
          .action-bar { margin: 20px 0; display: flex; gap: 10px; }
          .btn-add-company { padding: 12px 24px; background: #48bb78; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; }
          .btn-add-company:hover { background: #38a169; }
          .empty-state { text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 12px; margin: 30px 0; }
          .empty-icon { font-size: 4em; margin-bottom: 20px; }
          .empty-state h3 { margin: 0 0 10px 0; font-size: 1.5em; }
          .empty-state p { color: #666; margin-bottom: 20px; }
          .btn-primary { display: inline-block; padding: 12px 30px; background: #48bb78; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; }
          .section-header { margin: 40px 0 20px 0; padding-top: 30px; border-top: 2px solid #e2e8f0; }
          .section-header h3 { margin: 0 0 10px 0; font-size: 1.5em; }
          .section-description { color: #666; margin: 0; }
          .filter-bar { margin: 20px 0; display: flex; gap: 20px; align-items: center; }
          .company-filter-input { flex: 1; max-width: 400px; padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 1em; }
          .company-filter-input:focus { outline: none; border-color: #4299e1; }
          .filter-count { color: #666; font-size: 0.9em; }
          .all-companies-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
          .all-companies-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
          .all-companies-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
          .all-companies-table .company-row.hidden { display: none; }
          .btn-add-quick { padding: 6px 16px; background: #48bb78; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em; display: inline-block; }
          .btn-add-quick:hover { background: #38a169; }
          .already-added { color: #38a169; font-weight: 600; }
          .companies-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
          .companies-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
          .companies-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
          .company-name strong { font-size: 1.1em; color: #2d3748; }
          .company-industry { font-size: 0.9em; color: #718096; margin-top: 4px; }
          .company-links a { margin-right: 10px; color: #4299e1; text-decoration: none; }
          .company-links a:hover { text-decoration: underline; }
          .badge { background: #e6fffa; color: #234e52; padding: 4px 12px; border-radius: 12px; font-weight: 600; }
          .status-active { color: #38a169; font-weight: 600; }
          .status-inactive { color: #a0aec0; }
          .btn-edit, .btn-delete { padding: 6px 12px; margin-right: 8px; border-radius: 4px; text-decoration: none; font-size: 0.9em; }
          .btn-edit { background: #4299e1; color: white; }
          .btn-delete { background: #f56565; color: white; }
          .btn-edit:hover { background: #3182ce; }
          .btn-delete:hover { background: #e53e3e; }
          .text-muted { color: #a0aec0; }
        ',
      ],
      'target_companies_styles',
    ];
    
    // Add JavaScript for filtering
    $content['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => '
          function filterCompanies() {
            var input = document.getElementById("company-filter");
            var filter = input.value.toLowerCase();
            var rows = document.querySelectorAll(".company-row");
            var visibleCount = 0;
            
            rows.forEach(function(row) {
              var companyName = row.getAttribute("data-company-name");
              if (companyName.indexOf(filter) > -1) {
                row.classList.remove("hidden");
                visibleCount++;
              } else {
                row.classList.add("hidden");
              }
            });
            
            document.getElementById("visible-count").textContent = visibleCount;
          }
          
          function addCompanyQuick(btn) {
            var companyName = btn.getAttribute("data-company");
            var formData = new FormData();
            formData.append("company_name", companyName);
            
            fetch("/jobhunter/companies/add-quick", {
              method: "POST",
              body: formData
            }).then(function(response) {
              return response.json();
            }).then(function(data) {
              if (data.success) {
                btn.outerHTML = "<span class=\"already-added\">✓ Added to targets</span>";
                location.reload();
              } else {
                alert("Error adding company: " + data.message);
              }
            });
          }
          
          document.addEventListener("DOMContentLoaded", function() {
            var filterInput = document.getElementById("company-filter");
            if (filterInput) {
              filterInput.addEventListener("keyup", filterCompanies);
            }
          });
        ',
      ],
      'target_companies_js',
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
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
    $company_ids = $query->execute();
    $company_count = count($company_ids);

    $build = [];
    $build['header'] = [
      '#markup' => '<h2>Companies Overview</h2><p>Total companies: ' . $company_count . '</p>',
    ];

    if ($company_count > 0) {
      // Load companies and build table
      $companies = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($company_ids);
      
      // Table header
      $table_header = '<table class="companies-table">
        <thead>
          <tr>
            <th>Company</th>
            <th>Industry</th>
            <th>Size</th>
            <th>Profile Complete</th>
            <th>Jobs Found</th>
            <th>Applications</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>';
      
      $table_rows = '';
      foreach ($companies as $company) {
        $company_name = $company->getTitle();
        $company_url = $company->hasField('field_company_website') && !$company->get('field_company_website')->isEmpty() 
          ? $company->get('field_company_website')->value : '#';
        $company_industry = $company->hasField('field_company_industry') && !$company->get('field_company_industry')->isEmpty()
          ? $company->get('field_company_industry')->value : 'Not specified';
        $company_size = $company->hasField('field_company_size') && !$company->get('field_company_size')->isEmpty()
          ? $company->get('field_company_size')->value : 'Not specified';
        
        // Calculate completion percentage (mock data for now)
        $completion_fields = 0;
        $total_fields = 5; // Name, industry, size, website, description
        
        if (!empty($company_name)) $completion_fields++;
        if ($company->hasField('field_company_industry') && !$company->get('field_company_industry')->isEmpty()) $completion_fields++;
        if ($company->hasField('field_company_size') && !$company->get('field_company_size')->isEmpty()) $completion_fields++;
        if ($company->hasField('field_company_website') && !$company->get('field_company_website')->isEmpty()) $completion_fields++;
        if ($company->hasField('field_company_description') && !$company->get('field_company_description')->isEmpty()) $completion_fields++;
        
        $completion_percentage = round(($completion_fields / $total_fields) * 100);
        
        // Mock job and application counts (replace with real queries later)
        $jobs_found = rand(0, 15);
        $applications_count = rand(0, 5);
        $status = $completion_percentage >= 80 ? 'Active' : 'Incomplete';
        
        $table_rows .= '<tr>
          <td><a href="/node/' . $company->id() . '">' . $company_name . '</a></td>
          <td>' . $company_industry . '</td>
          <td>' . $company_size . '</td>
          <td>
            <div class="progress-bar">
              <div class="progress-fill" data-width="' . $completion_percentage . '%"></div>
              <span class="progress-text">' . $completion_percentage . '%</span>
            </div>
          </td>
          <td>' . $jobs_found . '</td>
          <td>' . $applications_count . '</td>
          <td><span class="status-badge status-' . strtolower($status) . '">' . $status . '</span></td>
          <td>
            <a href="/node/' . $company->id() . '/edit" class="btn btn-sm">Edit</a>
            <a href="/node/' . $company->id() . '" class="btn btn-sm">View</a>
          </td>
        </tr>';
      }
      
      $table_footer = '</tbody></table>';
      
      $build['companies'] = [
        '#markup' => $table_header . $table_rows . $table_footer,
      ];
      
      // Attach companies table CSS library
      $build['#attached']['library'][] = 'job_hunter/companies_table';
    } else {
      $build['no_companies'] = [
        '#markup' => '<div class="no-companies">
          <p>No companies found. <a href="/job-applications/bulk-import-companies">Add companies via bulk import</a> or <a href="/node/add/company">add a single company</a>.</p>
        </div>',
      ];
    }

    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    // Wrap with navigation
    $wrapper = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $build,
    ];

    return $wrapper;
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

  /**
   * Build the queue controls section.
   * Status visible to all users, actions only for admins.
   *
   * @return array
   *   Render array for queue controls.
   */
  private function buildQueueControlsSection() {
    $current_user = $this->currentUser();
    $is_admin = $current_user->hasPermission('administer job application automation');
    $queue_factory = \Drupal::service('queue');
    
    // Queue definitions
    $queues = [
      'job_hunter_genai_parsing' => [
        'name' => 'Resume AI Parsing',
        'description' => 'Extracts structured data from uploaded resumes using Claude AI',
        'icon' => '📄',
      ],
      'job_hunter_job_posting_parsing' => [
        'name' => 'Job Posting AI Parsing',
        'description' => 'Extracts job requirements, skills, and company info from job postings',
        'icon' => '📋',
      ],
      'job_hunter_resume_tailoring' => [
        'name' => 'Resume Tailoring',
        'description' => 'Generates tailored resumes matching job requirements',
        'icon' => '✨',
      ],
    ];
    
    // Build queue rows with enhanced status columns
    $queue_rows = '';
    $total_items = 0;
    
    foreach ($queues as $queue_id => $info) {
      $queue = $queue_factory->get($queue_id);
      $items = $queue->numberOfItems();
      $total_items += $items;
      
      $badge_class = $items > 0 ? 'queue-badge-pending' : 'queue-badge-empty';
      $disabled_attr = $items == 0 ? ' disabled="disabled"' : '';
      
      // Actions column - only show buttons to admins
      $actions_cell = $is_admin 
        ? '<button type="button" class="btn-run-queue" data-queue="' . $queue_id . '"' . $disabled_attr . '>▶️ Run</button>'
        : '<span class="text-muted">Admin only</span>';
      
      $queue_rows .= '
        <tr class="queue-row" data-queue-id="' . $queue_id . '">
          <td class="queue-icon">' . $info['icon'] . '</td>
          <td class="queue-name">
            <strong>' . $info['name'] . '</strong>
            <div class="queue-description">' . $info['description'] . '</div>
          </td>
          <td class="queue-count">
            <span class="queue-badge ' . $badge_class . '" data-count>' . $items . '</span>
          </td>
          <td class="queue-status">
            <span class="status-indicator status-idle" data-status>
              <span class="status-dot"></span>
              <span class="status-text">Idle</span>
            </span>
          </td>
          <td class="queue-last-activity">
            <span class="last-activity-text" data-last-activity>-</span>
          </td>
          <td class="queue-actions">' . $actions_cell . '</td>
        </tr>';
    }
    
    $run_all_disabled = $total_items == 0 ? ' disabled="disabled"' : '';
    
    // Global actions - conditional rendering based on admin status
    $global_actions = $is_admin ? '
      <div class="queue-global-actions">
        <button type="button" id="run-all-queues" class="btn-run-all"{{ run_all_disabled|raw }}>
          Run All Queues (<span id="total-queue-items">{{ total_items }}</span> items)
        </button>
        <button type="button" id="refresh-queue-status" class="btn-refresh">🔄 Refresh Status</button>
        <label class="auto-refresh-toggle">
          <input type="checkbox" id="auto-refresh-toggle" checked>
          <span>Auto-refresh (<span id="auto-refresh-countdown">5</span>s)</span>
        </label>
      </div>' : '
      <div class="queue-global-actions">
        <button type="button" id="refresh-queue-status" class="btn-refresh">🔄 Refresh Status</button>
        <label class="auto-refresh-toggle">
          <input type="checkbox" id="auto-refresh-toggle" checked>
          <span>Auto-refresh (<span id="auto-refresh-countdown">5</span>s)</span>
        </label>
      </div>';
    
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['queue-controls-section'], 'id' => 'queue-controls-panel'],
      'content' => [
        '#type' => 'inline_template',
        '#template' => '
          <div class="queue-controls-wrapper">
            <div class="queue-controls-header">
              <h3>🎛️ Queue Processing Dashboard</h3>
              <p class="queue-controls-subtitle">Monitor background processing queues</p>
              {{ global_actions|raw }}
            </div>
            <div id="queue-status-message" class="queue-status-message" style="display:none;"></div>
            <table class="queue-controls-table">
              <thead>
                <tr>
                  <th></th>
                  <th>Queue</th>
                  <th>Pending</th>
                  <th>Status</th>
                  <th>Last Activity</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="queue-table-body">{{ queue_rows|raw }}</tbody>
            </table>
            <div class="queue-processing-log" id="processing-log">
              <h4>📋 Recent Activity</h4>
              <div class="log-entries" id="log-entries">
                <div class="log-entry log-info">Waiting for activity...</div>
              </div>
            </div>
          </div>
        ',
        '#context' => [
          'total_items' => $total_items,
          'run_all_disabled' => $run_all_disabled,
          'queue_rows' => $queue_rows,
          'global_actions' => $global_actions,
        ],
      ],
      '#attached' => [
        'library' => ['job_hunter/queue-controls'],
      ],
    ];
    
    return $build;
  }

  /**
   * Extract unique company names from job postings.
   * 
   * @return array
   *   Array of company names with job counts [company_name => count].
   */
  private function getCompaniesFromJobPostings() {
    $database = \Drupal::database();
    
    // Get all job requirements with extracted JSON
    $query = $database->select("jobhunter_job_requirements", "j")
      ->fields("j", ["id", "extracted_json", "company_id"])
      ->condition("status", "active");
    $jobs = $query->execute()->fetchAll();
    
    $companies = [];
    
    foreach ($jobs as $job) {
      $company_name = null;
      
      // First, try to get company from company_id
      if ($job->company_id) {
        $company = $database->select("jobhunter_companies", "c")
          ->fields("c", ["name"])
          ->condition("id", $job->company_id)
          ->execute()
          ->fetchField();
        if ($company) {
          $company_name = $company;
        }
      }
      
      // If no company_id or not found, try to extract from JSON
      if (!$company_name && $job->extracted_json) {
        $extracted = json_decode($job->extracted_json, TRUE);
        if (isset($extracted["company_name"]) && !empty($extracted["company_name"])) {
          $company_name = $extracted["company_name"];
        } elseif (isset($extracted["company"]) && !empty($extracted["company"])) {
          $company_name = $extracted["company"];
        }
      }
      
      // Count this company
      if ($company_name) {
        if (!isset($companies[$company_name])) {
          $companies[$company_name] = 0;
        }
        $companies[$company_name]++;
      }
    }
    
    // Sort by job count descending, then alphabetically
    arsort($companies);
    
    return $companies;
  }

  /**
   * Step 2: Job Discovery & Search page.
   *
   * @return array
   *   A renderable array for the job discovery page.
   */
  public function jobDiscovery() {
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    // Check Google Cloud credentials
    $has_credentials = FALSE;
    $credentials_status = 'Not Configured';
    $credentials_class = 'status-warning';
    
    try {
      $config = \Drupal::config('job_hunter.settings');
      $google_credentials = $config->get('google_cloud_credentials_json');
      if (!empty($google_credentials)) {
        $has_credentials = TRUE;
        $credentials_status = 'Configured';
        $credentials_class = 'status-success';
      }
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Error checking credentials: @error', ['@error' => $e->getMessage()]);
    }
    
    $content = [
      '#type' => 'container',
      '#attributes' => ['class' => ['job-discovery-page']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => '🔍 AI Job Discovery',
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Automatically find matching jobs at your target companies using AI-powered search integrations.',
      ],
      'integrations_section' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['integrations-section']],
        '#value' => '
          <h2>🔗 Job Search Integrations</h2>
          <div class="integration-cards">
            <div class="integration-card">
              <div class="integration-header">
                <div class="integration-icon">�️</div>
                <div class="integration-info">
                  <h3>Forseti Jobs Search</h3>
                  <p>Search jobs you\'ve manually added and manage your application pipeline</p>
                </div>
              </div>
              <div class="integration-status">
                <div class="status-row">
                  <span class="status-label">Status:</span>
                  <span class="status-badge status-success">Active</span>
                </div>
                <div class="status-row">
                  <span class="status-label">Features:</span>
                  <span class="status-text">Manual Job Entry, Resume Tailoring, Application Tracking</span>
                </div>
              </div>
              <div class="integration-actions">
                <a href="/jobhunter/job-paste" class="btn btn-primary">➕ Add Job</a>
                <a href="/jobhunter/jobs" class="btn btn-secondary">📋 View Jobs</a>
              </div>
            </div>
            
            <div class="integration-card">
              <div class="integration-header">
                <div class="integration-icon">�📊</div>
                <div class="integration-info">
                  <h3>Google Cloud Talent Solution</h3>
                  <p>Search millions of jobs across the web using Google\'s AI-powered job search API</p>
                </div>
              </div>
              <div class="integration-status">
                <div class="status-row">
                  <span class="status-label">API Status:</span>
                  <span class="status-badge ' . $credentials_class . '">' . $credentials_status . '</span>
                </div>
                <div class="status-row">
                  <span class="status-label">Features:</span>
                  <span class="status-text">Job Search, Company Filtering, Location Search</span>
                </div>
              </div>
              <div class="integration-actions">
                ' . ($has_credentials ? 
                  '<a href="/jobhunter/google-jobs-search" class="btn btn-primary">🔍 Search Jobs</a>
                   <a href="/admin/config/forseti/job-hunter" class="btn btn-secondary">⚙️ Settings</a>' :
                  '<a href="/admin/config/forseti/job-hunter" class="btn btn-warning">⚙️ Configure API</a>') . '
              </div>
            </div>
            
            <div class="integration-card disabled">
              <div class="integration-header">
                <div class="integration-icon">💼</div>
                <div class="integration-info">
                  <h3>LinkedIn Jobs API</h3>
                  <p>Access professional network job postings and company data</p>
                </div>
              </div>
              <div class="integration-status">
                <div class="status-row">
                  <span class="status-label">API Status:</span>
                  <span class="status-badge status-inactive">Coming Soon</span>
                </div>
              </div>
            </div>
            
            <div class="integration-card disabled">
              <div class="integration-header">
                <div class="integration-icon">🌐</div>
                <div class="integration-info">
                  <h3>Indeed Job Search</h3>
                  <p>Search one of the world\'s largest job boards</p>
                </div>
              </div>
              <div class="integration-status">
                <div class="status-row">
                  <span class="status-label">API Status:</span>
                  <span class="status-badge status-inactive">Coming Soon</span>
                </div>
              </div>
            </div>
          </div>
        ',
      ],
      'styles' => [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '
          .job-discovery-page { max-width: 1200px; }
          .job-discovery-page h1 { margin: 0 0 15px 0; font-size: 2.5em; }
          .job-discovery-page > p { color: #666; font-size: 1.1em; margin-bottom: 40px; }
          .integrations-section h2 { font-size: 1.8em; margin: 30px 0 20px 0; }
          .integration-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-top: 20px; }
          .integration-card { background: white; border: 2px solid #e2e8f0; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s; }
          .integration-card:hover { box-shadow: 0 6px 12px rgba(0,0,0,0.15); transform: translateY(-2px); }
          .integration-card.disabled { opacity: 0.6; }
          .integration-header { display: flex; gap: 15px; margin-bottom: 20px; }
          .integration-icon { font-size: 3em; }
          .integration-info h3 { margin: 0 0 8px 0; font-size: 1.3em; color: #2d3748; }
          .integration-info p { margin: 0; color: #718096; font-size: 0.9em; }
          .integration-status { background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
          .status-row { display: flex; justify-content: space-between; align-items: center; margin: 8px 0; }
          .status-label { font-weight: 600; color: #4a5568; }
          .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
          .status-success { background: #c6f6d5; color: #22543d; }
          .status-warning { background: #fbd38d; color: #744210; }
          .status-inactive { background: #e2e8f0; color: #4a5568; }
          .status-text { color: #4a5568; font-size: 0.9em; }
          .integration-actions { display: flex; gap: 10px; flex-wrap: wrap; }
          .btn { padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9em; display: inline-block; text-align: center; }
          .btn-primary { background: #4299e1; color: white; }
          .btn-primary:hover { background: #3182ce; }
          .btn-secondary { background: #e2e8f0; color: #2d3748; }
          .btn-secondary:hover { background: #cbd5e0; }
          .btn-warning { background: #ed8936; color: white; }
          .btn-warning:hover { background: #dd6b20; }
        ',
      ],
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
      '#attached' => [
        'library' => [
          'job_hunter/job-hunter-navigation',
          'job_hunter/job-hunter-home',
        ],
      ],
    ];
    
    return $build;
  }

  /**
   * Step 3: Application Submission page.
   *
   * @return array
   *   A renderable array for the application submission page.
   */
  public function applicationSubmission() {
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    $content = [
      '#type' => 'container',
      '#attributes' => ['class' => ['application-submission-page']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => '🚀 Application Submission',
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Auto-apply to jobs with tailored resumes and cover letters.',
      ],
      'todo' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['alert', 'alert-warning']],
        '#value' => '<strong>TODO:</strong> Implement automated application submission.',
      ],
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
      '#attached' => [
        'library' => [
          'job_hunter/job-hunter-navigation',
          'job_hunter/job-hunter-home',
        ],
      ],
    ];
    
    return $build;
  }

  /**
   * Step 5: Interview & Follow-up page.
   *
   * @return array
   *   A renderable array for the interview and follow-up page.
   */
  public function interviewFollowup() {
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    $content = [
      '#type' => 'container',
      '#attributes' => ['class' => ['interview-followup-page']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => '📅 Interview & Follow-up',
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Track application status, schedule interviews, and manage follow-ups.',
      ],
      'todo' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['alert', 'alert-warning']],
        '#value' => '<strong>TODO:</strong> Implement interview tracking and follow-up management.',
      ],
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
      '#attached' => [
        'library' => [
          'job_hunter/job-hunter-navigation',
          'job_hunter/job-hunter-home',
        ],
      ],
    ];
    
    return $build;
  }

  /**
   * Step 5: Analytics page.
   *
   * @return array
   *   A renderable array for the analytics page.
   */
  public function analytics() {
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    $content = [
      '#type' => 'container',
      '#attributes' => ['class' => ['analytics-page']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => '📊 Analytics & Optimization',
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Measure success rates, identify patterns, and optimize your job search strategy.',
      ],
      'todo' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['alert', 'alert-warning']],
        '#value' => '<strong>TODO:</strong> Implement analytics dashboard with success metrics.',
      ],
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
      '#attached' => [
        'library' => [
          'job_hunter/job-hunter-navigation',
          'job_hunter/job-hunter-home',
        ],
      ],
    ];
    
    return $build;
  }

}
