<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
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
    
    // Load user profile data for pre-filling search form
    $current_user = \Drupal::currentUser();
    $connection = \Drupal::database();
    $default_keywords = '';
    $default_location = '';
    $default_remote = '';
    $default_salary = '';
    
    try {
      $profile = $connection->select('jobhunter_job_seeker', 'js')
        ->fields('js')
        ->condition('uid', $current_user->id())
        ->execute()
        ->fetchObject();
      
      if ($profile && !empty($profile->consolidated_profile_json)) {
        $consolidated = json_decode($profile->consolidated_profile_json, TRUE) ?: [];
        
        // Extract target job titles and keywords
        $titles = $consolidated['job_search_preferences']['target_titles'] ?? '';
        $keywords = $consolidated['job_search_preferences']['keywords'] ?? '';
        $combined = array_filter(array_merge(
          $titles ? explode("\n", $titles) : [],
          $keywords ? explode("\n", $keywords) : []
        ));
        if (!empty($combined)) {
          $default_keywords = implode(', ', array_slice($combined, 0, 3)); // Use first 3
        }
        
        // Extract location preference from work history or profile
        if (isset($consolidated['work_experience']) && !empty($consolidated['work_experience'])) {
          $latest_job = reset($consolidated['work_experience']);
          $default_location = $latest_job['location'] ?? '';
        }
        
        // Get remote preference
        $remote_pref = $consolidated['job_search_preferences']['remote_preference'] ?? '';
        if ($remote_pref === 'remote') {
          $default_location = 'Remote';
          $default_remote = 'checked';
        }
        
        // Get salary expectations
        $salary_min = $consolidated['job_search_preferences']['salary_expectation_min'] ?? '';
        if ($salary_min && is_numeric($salary_min)) {
          $default_salary = (int) $salary_min;
        }
      }
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Error loading profile for search: @error', ['@error' => $e->getMessage()]);
    }
    
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
        '#value' => '🔍 Job Discovery & Search',
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Search for jobs across multiple sources. Jobs you save will automatically add their companies to your target list.',
      ],
      'search_form' => [
        '#markup' => Markup::create('
        <div class="job-search-form">
          <form id="unified-job-search" method="GET" action="/jobhunter/job-discovery/search">
            <div class="search-form-container">
              <div class="search-primary-row">
                <div class="search-field search-field-primary">
                  <label for="search-query">
                    <span class="field-icon">💼</span>
                    <span class="field-label">Job Title or Keywords</span>
                  </label>
                  <input 
                    type="text" 
                    id="search-query" 
                    name="q" 
                    value="' . htmlspecialchars($default_keywords) . '"
                    placeholder="e.g., Software Engineer, Product Manager, Data Scientist"
                    class="search-input"
                  >
                </div>
                
                <div class="search-field">
                  <label for="search-location">
                    <span class="field-icon">📍</span>
                    <span class="field-label">Location</span>
                  </label>
                  <input 
                    type="text" 
                    id="search-location" 
                    name="location" 
                    value="' . htmlspecialchars($default_location) . '"
                    placeholder="e.g., San Francisco, CA or Remote"
                    class="search-input"
                  >
                </div>
              </div>
              
              <div class="search-filters-row">
                <div class="search-field search-field-select">
                  <label for="employment-type">
                    <span class="field-icon">📋</span>
                    <span class="field-label">Employment Type</span>
                  </label>
                  <select id="employment-type" name="employment_type" class="search-select">
                    <option value="">Any</option>
                    <option value="FULL_TIME">Full-time</option>
                    <option value="PART_TIME">Part-time</option>
                    <option value="CONTRACT">Contract</option>
                    <option value="TEMPORARY">Temporary</option>
                    <option value="INTERN">Internship</option>
                  </select>
                </div>
                
                <div class="search-field search-field-checkboxes">
                  <label class="field-label-block">
                    <span class="field-icon">🔍</span>
                    <span class="field-label">Search In</span>
                  </label>
                  <div class="checkbox-group">
                    <label class="checkbox-label">
                      <input type="checkbox" name="sources[]" value="forseti" checked>
                      <span>Forseti Jobs</span>
                    </label>
                    <label class="checkbox-label' . ($has_credentials ? '' : ' disabled') . '">
                      <input type="checkbox" name="sources[]" value="google_cloud" ' . ($has_credentials ? 'checked' : 'disabled') . '>
                      <span>Google Jobs' . ($has_credentials ? '' : ' (Configure API)') . '</span>
                    </label>
                    <label class="checkbox-label disabled">
                      <input type="checkbox" name="sources[]" value="linkedin" disabled>
                      <span>LinkedIn (Coming Soon)</span>
                    </label>
                    <label class="checkbox-label disabled">
                      <input type="checkbox" name="sources[]" value="indeed" disabled>
                      <span>Indeed (Coming Soon)</span>
                    </label>
                  </div>
                </div>
              </div>
              
              <div class="search-actions-row">
                <button type="submit" class="btn-search">
                  <span class="btn-icon">🔍</span>
                  <span class="btn-text">Search Jobs</span>
                </button>
                <button type="button" class="btn-advanced" onclick="toggleAdvancedFilters()">
                  <span class="btn-text">Advanced Filters</span>
                  <span class="btn-icon">▼</span>
                </button>
                <div class="search-stats">
                  <span class="stat-item">
                    <strong>' . $this->getSavedJobsCount($this->currentUser()) . '</strong> jobs saved
                  </span>
                  <span class="stat-separator">•</span>
                  <span class="stat-item">
                    <strong>' . $this->getTargetCompaniesCount($this->currentUser()) . '</strong> companies tracked
                  </span>
                </div>
              </div>
              
              <div id="advanced-filters" class="advanced-filters" style="display: none;">
                <div class="advanced-filters-grid">
                  <div class="filter-field">
                    <label for="company-filter">Company</label>
                    <input type="text" id="company-filter" name="company" placeholder="Filter by company name" class="filter-input">
                  </div>
                  
                  <div class="filter-field">
                    <label for="salary-min">Min Salary</label>
                    <input type="number" id="salary-min" name="salary_min" value="' . htmlspecialchars($default_salary) . '" placeholder="e.g., 100000" class="filter-input">
                  </div>
                  
                  <div class="filter-field">
                    <label for="date-posted">Posted Within</label>
                    <select id="date-posted" name="date_posted" class="filter-select">
                      <option value="">Any time</option>
                      <option value="1">Last 24 hours</option>
                      <option value="7">Last 7 days</option>
                      <option value="30">Last 30 days</option>
                    </select>
                  </div>
                  
                  <div class="filter-field">
                    <label>
                      <input type="checkbox" name="remote_only" value="1" ' . $default_remote . '>
                      <span>Remote jobs only</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </form>
          
          <script>
            function toggleAdvancedFilters() {
              const filters = document.getElementById("advanced-filters");
              const btn = event.currentTarget;
              const icon = btn.querySelector(".btn-icon");
              
              if (filters.style.display === "none") {
                filters.style.display = "block";
                icon.textContent = "▲";
              } else {
                filters.style.display = "none";
                icon.textContent = "▼";
              }
            }
          </script>
        </div>
        '),
      ],
      'integrations_section' => [
        '#markup' => Markup::create('
        <div class="integrations-section">
          <h2>� Job Source Status</h2>
          <div class="integration-status-grid">
            <div class="status-card status-card-active">
              <div class="status-card-icon">💼</div>
              <div class="status-card-content">
                <h4>Forseti Jobs</h4>
                <span class="status-badge status-success">Active</span>
                <p>Your saved jobs and manual entries</p>
              </div>
            </div>
            
            <div class="status-card status-card-' . ($has_credentials ? 'active' : 'pending') . '">
              <div class="status-card-icon">🔍</div>
              <div class="status-card-content">
                <h4>Google Cloud Jobs</h4>
                <span class="status-badge ' . $credentials_class . '">' . $credentials_status . '</span>
                <p>' . ($has_credentials ? 'AI-powered job search across the web' : 'Configure API credentials to enable') . '</p>
              </div>
            </div>
            
            <div class="status-card status-card-disabled">
              <div class="status-card-icon">💼</div>
              <div class="status-card-content">
                <h4>LinkedIn Jobs</h4>
                <span class="status-badge status-inactive">Coming Soon</span>
                <p>Professional network job postings</p>
              </div>
            </div>
            
            <div class="status-card status-card-disabled">
              <div class="status-card-icon">🌐</div>
              <div class="status-card-content">
                <h4>Indeed Jobs</h4>
                <span class="status-badge status-inactive">Coming Soon</span>
                <p>World\'s largest job board</p>
              </div>
            </div>
          </div>
        </div>
        '),
      ],
      'styles' => [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '
          .job-discovery-page { max-width: 1400px; margin: 0 auto; }
          .job-discovery-page h1 { margin: 0 0 10px 0; font-size: 2.5em; color: #2d3748; }
          .job-discovery-page > p { color: #718096; font-size: 1.05em; margin-bottom: 30px; }
          
          /* Search Form */
          .job-search-form { background: white; border: 2px solid #e2e8f0; border-radius: 12px; padding: 30px; margin-bottom: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
          .search-form-container { display: flex; flex-direction: column; gap: 20px; }
          
          .search-primary-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
          .search-filters-row { display: grid; grid-template-columns: 200px 1fr; gap: 20px; align-items: start; }
          .search-actions-row { display: flex; gap: 15px; align-items: center; padding-top: 10px; border-top: 2px solid #f7fafc; margin-top: 10px; }
          
          .search-field { display: flex; flex-direction: column; gap: 8px; }
          .search-field-primary { grid-column: span 1; }
          .search-field label { display: flex; align-items: center; gap: 6px; font-weight: 600; color: #2d3748; font-size: 0.95em; }
          .field-label-block { margin-bottom: 8px; }
          .field-icon { font-size: 1.1em; }
          .field-label { font-size: 0.95em; }
          
          .search-input, .search-select { padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1em; color: #2d3748; transition: all 0.2s; width: 100%; }
          .search-input:focus, .search-select:focus { outline: none; border-color: #4299e1; box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1); }
          .search-input::placeholder { color: #a0aec0; }
          
          .checkbox-group { display: flex; flex-wrap: wrap; gap: 15px; }
          .checkbox-label { display: flex; align-items: center; gap: 6px; font-size: 0.9em; color: #4a5568; cursor: pointer; user-select: none; }
          .checkbox-label.disabled { opacity: 0.5; cursor: not-allowed; }
          .checkbox-label input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
          .checkbox-label input[type="checkbox"]:disabled { cursor: not-allowed; }
          
          .btn-search { background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%); color: white; border: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; font-size: 1.05em; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 2px 4px rgba(66, 153, 225, 0.3); }
          .btn-search:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(66, 153, 225, 0.4); }
          .btn-search .btn-icon { font-size: 1.2em; }
          
          .btn-advanced { background: #f7fafc; color: #4a5568; border: 2px solid #e2e8f0; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s; }
          .btn-advanced:hover { background: #edf2f7; border-color: #cbd5e0; }
          
          .search-stats { margin-left: auto; display: flex; align-items: center; gap: 10px; color: #718096; font-size: 0.9em; }
          .search-stats strong { color: #2d3748; font-weight: 700; }
          .stat-separator { color: #cbd5e0; }
          
          .advanced-filters { padding-top: 20px; border-top: 2px solid #f7fafc; margin-top: 15px; }
          .advanced-filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
          .filter-field { display: flex; flex-direction: column; gap: 6px; }
          .filter-field label { font-weight: 600; color: #4a5568; font-size: 0.9em; }
          .filter-input, .filter-select { padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 0.95em; }
          .filter-input:focus, .filter-select:focus { outline: none; border-color: #4299e1; box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1); }
          
          /* Job Source Status */
          .integrations-section { margin-top: 50px; padding-top: 40px; border-top: 2px solid #e2e8f0; }
          .integrations-section h2 { font-size: 1.4em; margin: 0 0 20px 0; color: #2d3748; }
          .integration-status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
          .status-card { background: white; border: 2px solid #e2e8f0; border-radius: 10px; padding: 20px; text-align: center; transition: all 0.2s; }
          .status-card:hover { border-color: #cbd5e0; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
          .status-card-disabled { opacity: 0.6; }
          .status-card-icon { font-size: 2.5em; margin-bottom: 12px; }
          .status-card-content h4 { margin: 0 0 8px 0; font-size: 1.1em; color: #2d3748; }
          .status-card-content p { margin: 12px 0 0 0; color: #718096; font-size: 0.85em; line-height: 1.4; }
          .status-card-content .status-badge { display: inline-block; margin: 8px 0; }
          .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
          .status-success { background: #c6f6d5; color: #22543d; }
          .status-warning { background: #fbd38d; color: #744210; }
          .status-inactive { background: #e2e8f0; color: #4a5568; }
          
          /* Responsive */
          @media (max-width: 768px) {
            .search-primary-row { grid-template-columns: 1fr; }
            .search-filters-row { grid-template-columns: 1fr; }
            .search-actions-row { flex-direction: column; align-items: stretch; }
            .search-stats { margin-left: 0; margin-top: 10px; }
            .integration-status-grid { grid-template-columns: 1fr; }
          }
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
   * Job Discovery Search Results page.
   * 
   * Handles unified search across multiple job sources based on user query.
   *
   * @return array
   *   A renderable array for the job search results page.
   */
  public function jobDiscoverySearchResults() {
    $request = \Drupal::request();
    $connection = \Drupal::database();
    $current_user = \Drupal::currentUser();
    
    // Get search parameters from GET request
    $query = $request->query->get('query', '');
    $location = $request->query->get('location', '');
    $employment_type = $request->query->get('employment_type', '');
    $sources = $request->query->get('sources', ['forseti']); // Default to Forseti
    $company_filter = $request->query->get('company', '');
    $salary_min = $request->query->get('salary_min', '');
    $date_posted = $request->query->get('date_posted', '');
    $remote_only = $request->query->get('remote_only', false);
    
    // Ensure sources is an array
    if (!is_array($sources)) {
      $sources = [$sources];
    }
    
    // Initialize results array
    $all_results = [];
    
    // Search in Forseti database if selected
    if (in_array('forseti', $sources)) {
      $db_query = $connection->select('jobhunter_job_requirements', 'j')
        ->fields('j')
        ->orderBy('created', 'DESC')
        ->range(0, 50); // Limit to 50 results
      
      // Add keyword search if provided
      if (!empty($query)) {
        $or = $db_query->orConditionGroup()
          ->condition('job_title', '%' . $connection->escapeLike($query) . '%', 'LIKE')
          ->condition('job_description', '%' . $connection->escapeLike($query) . '%', 'LIKE')
          ->condition('required_skills', '%' . $connection->escapeLike($query) . '%', 'LIKE');
        $db_query->condition($or);
      }
      
      // Add location filter if provided
      if (!empty($location)) {
        $db_query->condition('location', '%' . $connection->escapeLike($location) . '%', 'LIKE');
      }
      
      // Add employment type filter if provided
      if (!empty($employment_type)) {
        $db_query->condition('employment_type', $employment_type);
      }
      
      // Add company name filter if provided
      if (!empty($company_filter)) {
        // Join with companies table to filter by company name
        $db_query->leftJoin('jobhunter_companies', 'c', 'j.company_id = c.id');
        $db_query->condition('c.name', '%' . $connection->escapeLike($company_filter) . '%', 'LIKE');
      }
      
      // Add salary filter if provided
      if (!empty($salary_min) && is_numeric($salary_min)) {
        $db_query->condition('salary_min', $salary_min, '>=');
      }
      
      // Add date posted filter if provided
      if (!empty($date_posted)) {
        $days_ago = 30; // Default to last 30 days
        if ($date_posted === 'last_24h') {
          $days_ago = 1;
        } elseif ($date_posted === 'last_week') {
          $days_ago = 7;
        } elseif ($date_posted === 'last_month') {
          $days_ago = 30;
        }
        $timestamp = time() - ($days_ago * 24 * 60 * 60);
        $db_query->condition('created', $timestamp, '>=');
      }
      
      // Add remote only filter if provided
      if ($remote_only) {
        $or = $db_query->orConditionGroup()
          ->condition('location', '%remote%', 'LIKE')
          ->condition('location', '%Remote%', 'LIKE')
          ->condition('is_remote', 1);
        $db_query->condition($or);
      }
      
      $results = $db_query->execute()->fetchAll();
      
      // Format results from database
      foreach ($results as $job) {
        // Get company name
        $company_name = 'N/A';
        if (!empty($job->company_id)) {
          $company = $connection->select('jobhunter_companies', 'c')
            ->fields('c', ['name'])
            ->condition('id', $job->company_id)
            ->execute()
            ->fetchField();
          if ($company) {
            $company_name = $company;
          }
        }
        
        $all_results[] = [
          'id' => $job->id,
          'title' => $job->job_title,
          'company' => $company_name,
          'location' => $job->location ?? 'Not specified',
          'employment_type' => $job->employment_type ?? 'Not specified',
          'salary_range' => !empty($job->salary_min) ? '$' . number_format($job->salary_min) . (!empty($job->salary_max) ? ' - $' . number_format($job->salary_max) : '+') : 'Not specified',
          'description' => $this->truncateText($job->job_description ?? '', 200),
          'source' => 'Forseti',
          'posted_date' => !empty($job->created) ? date('M j, Y', $job->created) : 'Unknown',
          'url' => $job->application_url ?? '',
        ];
      }
    }
    
    // TODO: Search Google Cloud Talent Solution API if selected and credentials available
    if (in_array('google_cloud', $sources)) {
      // Check if Google Cloud credentials are configured
      // If yes, query the API and append results to $all_results
      // This will be implemented when API integration is ready
    }
    
    // TODO: Search LinkedIn Jobs API if selected and credentials available
    if (in_array('linkedin', $sources)) {
      // LinkedIn API integration coming soon
    }
    
    // TODO: Search Indeed Job Search if selected and credentials available
    if (in_array('indeed', $sources)) {
      // Indeed API integration coming soon
    }
    
    // Build results display
    $results_html = '';
    if (empty($all_results)) {
      $results_html = '<div class="no-results">
        <p>No jobs found matching your criteria. Try adjusting your search filters.</p>
      </div>';
    } else {
      $results_html = '<div class="results-summary">
        <h3>Found ' . count($all_results) . ' job' . (count($all_results) !== 1 ? 's' : '') . '</h3>
      </div>
      <div class="job-results-list">';
      
      foreach ($all_results as $job) {
        $results_html .= '
        <div class="job-result-card">
          <div class="job-result-header">
            <div class="job-result-title-block">
              <h4 class="job-result-title">' . htmlspecialchars($job['title']) . '</h4>
              <div class="job-result-meta">
                <span class="job-company">🏢 ' . htmlspecialchars($job['company']) . '</span>
                <span class="job-location">📍 ' . htmlspecialchars($job['location']) . '</span>
                <span class="job-type">💼 ' . htmlspecialchars($job['employment_type']) . '</span>
              </div>
            </div>
            <div class="job-result-actions">
              <span class="job-source-badge">' . htmlspecialchars($job['source']) . '</span>
            </div>
          </div>
          <div class="job-result-body">
            <div class="job-result-details">
              <span class="job-salary">💰 ' . htmlspecialchars($job['salary_range']) . '</span>
              <span class="job-posted">📅 ' . htmlspecialchars($job['posted_date']) . '</span>
            </div>
            <p class="job-description">' . htmlspecialchars($job['description']) . '</p>
          </div>
          <div class="job-result-footer">
            <a href="/jobhunter/addposting?job_id=' . $job['id'] . '" class="btn-save-job">💾 Save Job</a>
            ' . (!empty($job['url']) ? '<a href="' . htmlspecialchars($job['url']) . '" target="_blank" class="btn-view-job">🔗 View Original</a>' : '') . '
          </div>
        </div>';
      }
      
      $results_html .= '</div>';
    }
    
    // Build search summary
    $search_summary = '<div class="search-summary">';
    if (!empty($query)) {
      $search_summary .= '<span class="search-param"><strong>Keywords:</strong> ' . htmlspecialchars($query) . '</span>';
    }
    if (!empty($location)) {
      $search_summary .= '<span class="search-param"><strong>Location:</strong> ' . htmlspecialchars($location) . '</span>';
    }
    if (!empty($employment_type)) {
      $search_summary .= '<span class="search-param"><strong>Type:</strong> ' . htmlspecialchars($employment_type) . '</span>';
    }
    $search_summary .= '<span class="search-param"><strong>Sources:</strong> ' . htmlspecialchars(implode(', ', $sources)) . '</span>';
    $search_summary .= '</div>';
    
    // Render navigation
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    $content = [
      '#type' => 'container',
      '#attributes' => ['class' => ['job-search-results-page']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => '🔍 Job Search Results',
      ],
      'back_link' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['back-link-container']],
        '#value' => '<a href="/jobhunter/job-discovery" class="back-link">← Back to Search</a>',
      ],
      'search_summary' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['search-summary-container']],
        '#value' => $search_summary,
      ],
      'results' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['results-container']],
        '#value' => $results_html,
      ],
      'styles' => [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '
          .job-search-results-page { max-width: 1200px; margin: 0 auto; }
          .job-search-results-page h1 { margin: 0 0 20px 0; font-size: 2.5em; color: #2d3748; }
          .back-link-container { margin-bottom: 20px; }
          .back-link { color: #4299e1; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
          .back-link:hover { color: #3182ce; text-decoration: underline; }
          
          .search-summary-container { background: #f7fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 15px 20px; margin-bottom: 30px; }
          .search-summary { display: flex; flex-wrap: wrap; gap: 15px; }
          .search-param { color: #4a5568; font-size: 0.95em; }
          .search-param strong { color: #2d3748; }
          
          .results-summary { margin-bottom: 20px; }
          .results-summary h3 { margin: 0; font-size: 1.4em; color: #2d3748; }
          
          .no-results { background: #fff5f5; border: 2px solid #fc8181; border-radius: 8px; padding: 30px; text-align: center; }
          .no-results p { margin: 0; color: #742a2a; font-size: 1.1em; }
          
          .job-results-list { display: flex; flex-direction: column; gap: 20px; }
          .job-result-card { background: white; border: 2px solid #e2e8f0; border-radius: 12px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: all 0.2s; }
          .job-result-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-color: #cbd5e0; }
          
          .job-result-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
          .job-result-title { margin: 0 0 10px 0; font-size: 1.4em; color: #2d3748; }
          .job-result-meta { display: flex; flex-wrap: wrap; gap: 15px; color: #718096; font-size: 0.9em; }
          
          .job-source-badge { background: #e2e8f0; color: #4a5568; padding: 6px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
          
          .job-result-body { margin: 15px 0; }
          .job-result-details { display: flex; gap: 20px; margin-bottom: 12px; color: #4a5568; font-size: 0.95em; font-weight: 600; }
          .job-description { color: #4a5568; line-height: 1.6; margin: 0; }
          
          .job-result-footer { display: flex; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 2px solid #f7fafc; }
          .btn-save-job { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
          .btn-save-job:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(72, 187, 120, 0.4); }
          .btn-view-job { background: #e2e8f0; color: #2d3748; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
          .btn-view-job:hover { background: #cbd5e0; }
          
          @media (max-width: 768px) {
            .job-result-header { flex-direction: column; gap: 15px; }
            .job-result-footer { flex-direction: column; }
          }
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
   * Helper method to truncate text to a specified length.
   *
   * @param string $text
   *   The text to truncate.
   * @param int $length
   *   The maximum length.
   *
   * @return string
   *   The truncated text with ellipsis if needed.
   */
  private function truncateText($text, $length = 200) {
    $text = strip_tags($text);
    if (strlen($text) <= $length) {
      return $text;
    }
    return substr($text, 0, $length) . '...';
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
