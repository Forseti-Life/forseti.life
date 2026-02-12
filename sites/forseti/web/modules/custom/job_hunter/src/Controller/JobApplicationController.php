<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Url;
use Drupal\job_hunter\Service\JobDiscoveryService;
use Drupal\job_hunter\Service\SearchAggregatorService;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides route responses for the Job Application Automation module.
 */
class JobApplicationController extends ControllerBase {
  use JobHunterControllerTrait;

  /**
   * The job discovery service.
   *
   * @var \Drupal\job_hunter\Service\JobDiscoveryService
   */
  protected JobDiscoveryService $jobDiscoveryService;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * The search aggregator service.
   *
   * @var \Drupal\job_hunter\Service\SearchAggregatorService
   */
  protected SearchAggregatorService $searchAggregator;

  /**
   * Constructs a JobApplicationController object.
   *
   * @param \Drupal\job_hunter\Service\JobDiscoveryService $job_discovery_service
   *   The job discovery service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\job_hunter\Service\SearchAggregatorService $search_aggregator
   *   The search aggregator service.
   */
  public function __construct(
    JobDiscoveryService $job_discovery_service,
    RequestStack $request_stack,
    Connection $database,
    QueueFactory $queue_factory,
    SearchAggregatorService $search_aggregator
  ) {
    $this->jobDiscoveryService = $job_discovery_service;
    $this->requestStack = $request_stack;
    $this->database = $database;
    $this->queueFactory = $queue_factory;
    $this->searchAggregator = $search_aggregator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('job_hunter.job_discovery_service'),
      $container->get('request_stack'),
      $container->get('database'),
      $container->get('queue'),
      $container->get('job_hunter.search_aggregator')
    );
  }

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
   * Redirect /jobhunter/jobs to /jobhunter/job-discovery.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the job discovery page.
   */
  public function listJobsRedirect() {
    return new RedirectResponse(Url::fromRoute('job_hunter.job_discovery')->toString());
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
    
    return $this->wrapWithNavigation($content, ['job_hunter/job-hunter-home']);
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
    $content = [
      '#markup' => '<h2>Job Application View</h2><p>Details for job application ID: ' . $job_application . '</p>',
    ];
    
    return $this->wrapWithNavigation($content);
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
    // Job Application Workflow
    // ========================================
    $build['flow_header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['flow-header', 'flow-automated']],
      '#value' => '<h2>🚀 Job Application Workflow</h2>
                   <p class="flow-description">Streamlined process from profile setup to application tracking.</p>',
    ];
    
    // Step 1: Profile Setup
    $build['step1'] = [
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
    
    // Step 2: Job Discovery & Management
    $build['step2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-discovery']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 2</div>
                     <div class="phase-info">
                       <h3>Job Discovery & Management</h3>
                       <p>Add jobs manually, search using AI, or paste job postings. Generate tailored resumes for each position.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">' . $saved_jobs . '</div>
                       <div class="stat-label">Jobs Saved</div>
                       <div class="stat-sublabel">' . $target_companies . ' companies tracked</div>
                     </div>
                     <div class="phase-actions">
                       <a href="' . $job_paste_url->toString() . '" class="phase-button primary">+ Add Job Posting</a>
                       <a href="/jobhunter/job-discovery" class="phase-button">Search Jobs</a>
                       <a href="' . $jobs_list_url->toString() . '" class="phase-button">View All Jobs</a>
                     </div>',
      ],
    ];
    
    // Step 3: Application Submission
    $build['step3'] = [
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
    $build['step4'] = [
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
    $build['step5'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-analytics', 'disabled']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 5</div>
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
      $count = $this->database->select('jobhunter_job_requirements', 'j')
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
    $database = $this->database;
    
    // Query companies from jobhunter_companies table.
    $query = $database->select('jobhunter_companies', 'c')
      ->fields('c')
      ->orderBy('name', 'ASC');
    $companies = $query->execute()->fetchAll();
    
    // Count jobs per company.
    $job_query = $database->select('jobhunter_job_requirements', 'j')
      ->fields('j', ['company_id'])
      ->condition('status', 'active')
      ->groupBy('company_id');
    $job_query->addExpression('COUNT(*)', 'job_count');
    $job_results = $job_query->execute()->fetchAllKeyed(0, 1);
    
    // Calculate statistics.
    $total_companies = count($companies);
    $active_companies = count(array_filter($companies, fn($c) => $c->active == 1));
    $total_jobs = array_sum($job_results);
    
    // Prepare target companies data for template.
    $target_companies_data = [];
    foreach ($companies as $company) {
      $target_companies_data[] = [
        'id' => $company->id,
        'name' => $company->name,
        'location' => $company->location,
        'industry' => $company->industry,
        'website' => $company->website,
        'careers_page_url' => $company->careers_page_url,
        'active' => $company->active,
        'job_count' => $job_results[$company->id] ?? 0,
      ];
    }
    
    // Get companies from job postings (extracted via AI).
    $job_companies = $this->getCompaniesFromJobPostings();
    
    // Get list of existing company names for template comparison.
    $existing_companies = array_column($companies, 'name');
    
    $content = [
      '#theme' => 'target_companies',
      '#total_companies' => $total_companies,
      '#active_companies' => $active_companies,
      '#total_jobs' => $total_jobs,
      '#target_companies' => $target_companies_data,
      '#job_companies' => $job_companies,
      '#existing_companies' => $existing_companies,
      '#attached' => [
        'library' => [
          'job_hunter/target-companies',
        ],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['job_hunter:companies', 'job_hunter:jobs'],
      ],
    ];
    
    return $this->wrapWithNavigation($content);
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
      $companies = $this->entityTypeManager()->getStorage('node')->loadMultiple($company_ids);
      
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

    return $this->wrapWithNavigation($build);
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
    $queue_factory = $this->queueFactory;
    
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
            <div id="queue-status-message" class="queue-status-message hidden"></div>
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
    $database = $this->database;
    
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
  /**
   * Job Discovery page with unified search and saved jobs management.
   *
   * @return array
   *   A renderable array for the job discovery page.
   */
  public function jobDiscovery(): array {
    // Get search defaults from user profile.
    $defaults = $this->jobDiscoveryService->getUserSearchDefaults();
    
    // Check API credentials status.
    $api_status = $this->jobDiscoveryService->getApiCredentialsStatus();
    
    // Render the template with all necessary variables.
    $content = [
      '#theme' => 'job_discovery_page',
      '#default_keywords' => $defaults['keywords'],
      '#default_location' => $defaults['location'],
      '#default_remote_pref' => $defaults['remote_pref'],
      '#default_salary_min' => $defaults['salary_min'],
      '#default_salary_max' => $defaults['salary_max'],
      '#default_employment_type' => $defaults['employment_type'],
      '#default_relocation' => $defaults['relocation'],
      '#has_google_cloud' => $api_status['google_cloud'],
      '#has_adzuna' => $api_status['adzuna'],
      '#has_usajobs' => $api_status['usajobs'],
      '#has_serpapi' => $api_status['serpapi'],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['job_hunter:settings'],
      ],
    ];
    
    return $this->wrapWithNavigation($content);
  }

  /**
   * My Jobs page - displays user's saved job postings.
   *
   * @return array
   *   Renderable array for the my jobs page.
   */
  public function myJobs(): array {
    // Get filter parameters from request.
    $request = $this->requestStack->getCurrentRequest();
    $filters = [
      'company' => $request->query->get('company', ''),
      'status' => $request->query->get('status', ''),
      'ai_status' => $request->query->get('ai_status', ''),
      'tailoring' => $request->query->get('tailoring', ''),
    ];
    
    // Get saved jobs and company names for filtering.
    $jobs = $this->jobDiscoveryService->getSavedJobs($filters);
    $companies = $this->jobDiscoveryService->getCompanyNames();
    
    // Render the template.
    $content = [
      '#theme' => 'my_jobs',
      '#jobs' => $jobs,
      '#companies' => $companies,
      '#filter_company' => $filters['company'],
      '#filter_status' => $filters['status'],
      '#filter_ai_status' => $filters['ai_status'],
      '#filter_tailoring' => $filters['tailoring'],
      '#cache' => [
        'contexts' => ['user', 'url.query_args'],
        'tags' => ['job_hunter:jobs', 'job_hunter:companies'],
      ],
    ];
    
    return $this->wrapWithNavigation($content);
  }

  /**
   * Job Discovery Search Results page.
   *
   * This method now uses the SearchAggregatorService to centralize
   * all search logic and API orchestration. The controller is simplified
   * to only handle request parameter extraction and result rendering.
   *
   * @return array
   *   A renderable array for the job search results page.
   */
  public function jobDiscoverySearchResults(): array {
    $request = $this->requestStack->getCurrentRequest();

    // Extract search parameters from request
    $search_params = [
      'query' => $request->query->get('q', ''),  // Using 'q' to match form
      'location' => $request->query->get('location', ''),
      'sources' => $request->query->all('sources'),
      'employment_type' => $request->query->get('employment_type', ''),
      'salary_min' => $request->query->get('salary_min', ''),
      'salary_max' => $request->query->get('salary_max', ''),
      'remote_preference' => $request->query->get('remote_preference', ''),
      'date_posted' => $request->query->get('date_posted', ''),
      'company' => $request->query->get('company', ''),
      'relocation_willing' => $request->query->get('relocation_willing', ''),
    ];

    // Ensure sources is an array with default
    if (empty($search_params['sources'])) {
      $search_params['sources'] = ['forseti'];
    }

    $this->getLogger('job_hunter')->info('🔍 Controller: Delegating search to SearchAggregatorService with @count sources', [
      '@count' => count($search_params['sources']),
    ]);

    // Delegate to SearchAggregatorService
    $search_results = $this->searchAggregator->searchJobs($search_params);

    // Prepare display parameters
    $display_params = [];
    if (!empty($search_params['query'])) {
      $display_params['query'] = $search_params['query'];
    }
    if (!empty($search_params['location'])) {
      $display_params['location'] = $search_params['location'];
    }
    if (!empty($search_params['employment_type'])) {
      $display_params['employment_type'] = $search_params['employment_type'];
    }
    if (!empty($search_params['salary_min'])) {
      $display_params['salary_min'] = $search_params['salary_min'];
    }
    if (!empty($search_params['salary_max'])) {
      $display_params['salary_max'] = $search_params['salary_max'];
    }
    if (!empty($search_params['remote_preference'])) {
      $display_params['remote_preference'] = $search_params['remote_preference'];
    }
    if (!empty($search_params['relocation_willing'])) {
      $display_params['relocation_willing'] = $search_params['relocation_willing'];
    }

    // Capitalize source names for display
    $sources_display = array_map('ucfirst', $search_results['sources_searched']);

    // Build render array
    $content = [
      '#theme' => 'job_search_results',
      '#results' => $search_results['results'],
      '#search_params' => $display_params,
      '#total_results' => $search_results['total'],
      '#sources_searched' => $sources_display,
      '#diagnostics' => $search_results['diagnostics'],
      '#attached' => [
        'library' => [
          'job_hunter/job-search-results',
        ],
      ],
      '#cache' => [
        'contexts' => ['url.query_args'],
        'tags' => ['job_hunter:search'],
        'max-age' => 3600,
      ],
    ];

    return $this->wrapWithNavigation($content);
  }

  /**
   * Step 3: Application Submission page.
   *
   * @return array
   *   A renderable array for the application submission page.
   */
  public function applicationSubmission() {
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
    
    return $this->wrapWithNavigation($content);
  }

  /**
   * Step 5: Interview & Follow-up page.
   *
   * @return array
   *   A renderable array for the interview and follow-up page.
   */
  public function interviewFollowup() {
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
    
    return $this->wrapWithNavigation($content);
  }

  /**
   * Step 5: Analytics page.
   *
   * @return array
   *   A renderable array for the analytics page.
   */
  public function analytics() {
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
    
    return $this->wrapWithNavigation($content);
  }

}
