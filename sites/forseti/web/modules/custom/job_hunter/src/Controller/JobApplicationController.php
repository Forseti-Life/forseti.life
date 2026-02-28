<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\job_hunter\Service\JobDiscoveryService;
use Drupal\job_hunter\Service\SearchAggregatorService;
use Drupal\job_hunter\Service\UserProfileService;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
   * The user profile service.
   *
   * @var \Drupal\job_hunter\Service\UserProfileService
   */
  protected UserProfileService $userProfileService;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $csrfTokenGenerator;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\job_hunter\Service\UserProfileService $user_profile_service
   *   The user profile service.
  * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token_generator
  *   The CSRF token generator.
   */
  public function __construct(
    JobDiscoveryService $job_discovery_service,
    RequestStack $request_stack,
    Connection $database,
    QueueFactory $queue_factory,
    SearchAggregatorService $search_aggregator,
    EntityTypeManagerInterface $entity_type_manager,
    UserProfileService $user_profile_service,
    CsrfTokenGenerator $csrf_token_generator
  ) {
    $this->jobDiscoveryService = $job_discovery_service;
    $this->requestStack = $request_stack;
    $this->database = $database;
    $this->queueFactory = $queue_factory;
    $this->searchAggregator = $search_aggregator;
    $this->entityTypeManager = $entity_type_manager;
    $this->userProfileService = $user_profile_service;
    $this->csrfTokenGenerator = $csrf_token_generator;
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
      $container->get('job_hunter.search_aggregator'),
      $container->get('entity_type.manager'),
      $container->get('job_hunter.user_profile_service'),
      $container->get('csrf_token')
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
    
    return $this->wrapWithNavigation($content, ['job_hunter/job-application-dashboard']);
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
    $submission_summary = $this->getApplicationSubmissionSummary((int) $current_user->id());
    
    // Calculate stats
    $profile_completion = $this->calculateProfileCompletion($current_user);
    $target_companies = $this->getTargetCompaniesCount($current_user);
    $saved_jobs = $this->getSavedJobsCount($current_user);
    
    // URLs
    $user_edit_url = Url::fromRoute('job_hunter.user_profile_edit');
    $job_discovery_url = Url::fromRoute('job_hunter.job_discovery');
    
    // Welcome message
    $build['welcome'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['user-welcome']],
      '#value' => '<div class="user-welcome">Welcome back, ' . $user_name . '!</div>',
    ];
    
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
    
    // Step 1: Complete Profile
    $build['step1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-profile']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 1</div>
                     <div class="phase-info">
                       <h3>Complete Profile</h3>
                       <p>Finish your job seeker profile so discovery and tailoring are more accurate.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">' . (int) $profile_completion . '%</div>
                       <div class="stat-label">Profile</div>
                     </div>
                     <div class="phase-actions">
                       <a href="' . $user_edit_url->toString() . '" class="phase-button">My Profile</a>
                     </div>',
      ],
    ];
    
    // Step 2: Job Discovery
    $build['step2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-discovery']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 2</div>
                     <div class="phase-info">
                       <h3>Job Discovery</h3>
                       <p>Search and save jobs that match your profile and preferences.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">' . (int) $saved_jobs . '</div>
                       <div class="stat-label">Saved Jobs</div>
                     </div>
                     <div class="phase-actions">
                       <a href="' . $job_discovery_url->toString() . '" class="phase-button">Job Discovery</a>
                     </div>',
      ],
    ];
    
    // Step 3: Application Submission
    $application_submission_url = Url::fromRoute('job_hunter.application_submission');
    $build['step3'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-submission']],
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
                        <div class="stat-number">' . (int) $submission_summary['submitted'] . '</div>
                        <div class="stat-label">Auto-Applied</div>
                      </div>
                      <div class="phase-actions">
                        <a href="' . $application_submission_url->toString() . '" class="phase-button">View Submissions</a>
                      </div>',
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
    
    // Attach CSS library instead of inline styles.
    $build['#attached']['library'][] = 'job_hunter/job-application-dashboard';
    
    $build['#prefix'] = '<div class="job-dashboard">';
    $build['#suffix'] = '</div>';
    
    return $build;
  }

  /**
   * Calculate user profile completeness percentage.
   *
   * Uses the UserProfileService to calculate how complete a user's
   * job seeker profile is based on filled fields.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user account.
   *
   * @return int
   *   The profile completeness percentage (0-100).
   */
  private function calculateProfileCompletion(AccountInterface $user) {
    // Use the injected UserProfileService for real calculation.
    $user_entity = User::load($user->id());
    if ($user_entity) {
      return $this->userProfileService->calculateProfileCompleteness($user_entity);
    }
    return 0;
  }

  /**
   * Get count of target companies.
   *
   * Counts the number of active company nodes in the system.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user account. Kept for future use when implementing
   *   user-specific company filtering. Currently unused.
   *
   * @return int
   *   The number of active companies.
   *
   * @todo Implement user-specific company filtering in the query.
   */
  private function getTargetCompaniesCount(AccountInterface $user) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'company')
      ->condition('status', 1)
      ->accessCheck(TRUE);
    return count($query->execute());
  }

  /**
   * Get count of matched jobs.
   *
   * Counts the number of active job posting nodes in the system.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user account. Kept for future use when implementing
   *   user-specific job matching. Currently unused.
   *
   * @return int
   *   The number of active job postings.
   *
   * @todo Implement user-specific job matching in the query.
   */
  private function getMatchedJobsCount(AccountInterface $user) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'job_posting')
      ->condition('status', 1)
      ->accessCheck(TRUE);
    return count($query->execute());
  }

  /**
   * Get count of active applications.
   *
   * Placeholder method for counting active job applications.
   * Currently returns 0.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user account.
   *
   * @return int
   *   The number of active applications (currently always 0).
   */
  private function getActiveApplicationsCount(AccountInterface $user) {
    return 0; // Placeholder
  }

  /**
   * Get count of saved job postings.
   *
   * Counts the total number of job requirements in the database.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user account. Kept for future use when implementing
   *   user-specific saved jobs. Currently unused.
   *
   * @return int
   *   The number of saved job postings.
   *
   * @todo Implement user-specific saved jobs filtering in the query.
   */
  private function getSavedJobsCount(AccountInterface $user) {
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
   * Manage target companies page.
   *
   * Displays a list of target companies for job hunting, with statistics
   * about each company including job counts and activity status.
   *
   * @return array
   *   A renderable array for the target companies management page.
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
   *
   * Handles the saving of target company selections.
   * Currently redirects to job applications page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the job applications page.
   */
  public function saveTargetCompanies() {
    return new \Symfony\Component\HttpFoundation\RedirectResponse('/job-applications');
  }

  /**
   * Companies overview page.
   *
   * Displays a comprehensive overview of all companies in the system,
   * including completion percentages, job counts, and application statistics.
   *
   * @return array
   *   A renderable array for the companies overview page.
   */
  public function companiesOverview() {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
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
      // Load companies and build table.
      $companies = $this->entityTypeManager->getStorage('node')->loadMultiple($company_ids);
      
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
   * Save a searched job into My Jobs from legacy addposting URL.
   *
    * Expected query parameter:
    * - job_id: Search result token (e.g. forseti_{id}, staging_{id},
    *   external ID, or legacy base64 JSON payload).
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\JsonResponse
   *   Redirect response for normal navigation, or JSON for AJAX requests.
   */
  public function addPostingFromSearch(): RedirectResponse|JsonResponse {
    $request = $this->requestStack->getCurrentRequest();
    $is_ajax = $request->isXmlHttpRequest();

    if ($request->isMethod('POST')) {
      $csrf_token = $request->headers->get('X-CSRF-Token', '') ?: (string) $request->request->get('csrf_token', '');
      if (!$this->csrfTokenGenerator->validate($csrf_token, 'job_hunter.addposting')) {
        if ($is_ajax) {
          return new JsonResponse([
            'success' => FALSE,
            'message' => (string) $this->t('Security token validation failed. Refresh and try again.'),
          ], 403);
        }
        $this->messenger()->addError($this->t('Security token validation failed. Refresh and try again.'));
        return new RedirectResponse('/jobhunter/job-discovery/search');
      }
    }

    if ($this->currentUser()->isAnonymous()) {
      if ($is_ajax) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => (string) $this->t('You must be logged in to save jobs.'),
          'redirect' => '/user/login',
        ], 401);
      }
      $this->messenger()->addError($this->t('You must be logged in to save jobs.'));
      return new RedirectResponse('/user/login');
    }

    $encoded = (string) ($request->request->get('job_id') ?? $request->query->get('job_id', ''));
    if ($encoded === '') {
      if ($is_ajax) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => (string) $this->t('Missing job payload.'),
        ], 400);
      }
      $this->messenger()->addError($this->t('Missing job payload.'));
      return new RedirectResponse('/jobhunter/job-discovery');
    }

    $uid = (int) $this->currentUser()->id();

    try {
      $target_job_id = $this->resolveTargetJobIdFromToken($encoded);

      if (!$target_job_id) {
        $target_job_id = $this->createJobFromSearchPayload($encoded);
      }

      if (!$target_job_id) {
        if ($is_ajax) {
          return new JsonResponse([
            'success' => FALSE,
            'message' => (string) $this->t('Job not found in Forseti jobs yet. Refresh search and try again.'),
          ], 404);
        }
        $this->messenger()->addError($this->t('Job not found in Forseti jobs yet. Refresh search and try again.'));
        return new RedirectResponse('/jobhunter/job-discovery/search');
      }

      // User-specific save mapping.
      $existing_mapping = $this->database->select('jobhunter_saved_jobs', 'sj')
        ->fields('sj', ['id'])
        ->condition('sj.uid', $uid)
        ->condition('sj.job_id', $target_job_id)
        ->execute()
        ->fetchField();

      if ($existing_mapping) {
        if ($is_ajax) {
          return new JsonResponse([
            'success' => TRUE,
            'already_saved' => TRUE,
            'message' => (string) $this->t('Job is already in My Jobs.'),
          ]);
        }
        $this->messenger()->addStatus($this->t('Job is already in My Jobs.'));
        return new RedirectResponse('/jobhunter/my-jobs');
      }

      $this->database->insert('jobhunter_saved_jobs')
        ->fields([
          'uid' => $uid,
          'job_id' => $target_job_id,
          'created' => time(),
          'updated' => time(),
        ])
        ->execute();

      if ($is_ajax) {
        return new JsonResponse([
          'success' => TRUE,
          'already_saved' => FALSE,
          'message' => (string) $this->t('Job added to My Jobs.'),
        ]);
      }

      $this->messenger()->addStatus($this->t('Job added to My Jobs.'));
      return new RedirectResponse('/jobhunter/my-jobs');
    }
    catch (\Exception $e) {
      $this->getLogger('job_hunter')->error('Failed to add posting from search payload: @error', [
        '@error' => $e->getMessage(),
      ]);

      if ($is_ajax) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => (string) $this->t('Unable to save this job right now.'),
        ], 500);
      }

      $this->messenger()->addError($this->t('Unable to save this job right now.'));
      return new RedirectResponse('/jobhunter/job-discovery');
    }
  }

  /**
   * Resolve a Forseti job ID from a search result token.
   *
   * @param string $encoded
   *   Search result token from query string.
   *
   * @return int|null
   *   Forseti job ID or NULL if unresolved.
   */
  private function resolveTargetJobIdFromToken(string $encoded): ?int {
    if (preg_match('/^forseti_(\d+)$/', $encoded, $matches)) {
      return (int) $matches[1];
    }

    if (preg_match('/^staging_(\d+)$/', $encoded, $matches)) {
      $imported_job_id = (int) $this->database->select('jobhunter_job_search_results', 's')
        ->fields('s', ['imported_to_job_id'])
        ->condition('s.id', (int) $matches[1])
        ->execute()
        ->fetchField();
      if ($imported_job_id > 0) {
        return $imported_job_id;
      }
    }

    $job_id = $this->findJobIdByExternalId($this->normalizeExternalJobId($encoded));
    if ($job_id !== NULL) {
      return $job_id;
    }

    $job_data = $this->decodeSearchPayloadToken($encoded);
    if (is_array($job_data)) {
      $embedded_external_id = trim((string) ($job_data['htidocid'] ?? $job_data['job_id'] ?? $job_data['id'] ?? ''));
      if ($embedded_external_id !== '') {
        return $this->findJobIdByExternalId($this->normalizeExternalJobId($embedded_external_id));
      }
    }

    return NULL;
  }

  /**
   * Find Forseti job ID by normalized external job identifier.
   *
   * @param string $external_id
   *   Normalized external identifier.
   *
   * @return int|null
   *   Matching Forseti job ID or NULL.
   */
  private function findJobIdByExternalId(string $external_id): ?int {
    if ($external_id === '') {
      return NULL;
    }

    $job_id = (int) $this->database->select('jobhunter_job_requirements', 'j')
      ->fields('j', ['id'])
      ->condition('j.external_job_id', $external_id)
      ->orderBy('j.id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $job_id > 0 ? $job_id : NULL;
  }

  /**
   * Decode legacy search payload token if it is base64-encoded JSON.
   *
   * @param string $encoded
   *   Raw token from query string.
   *
   * @return array|null
   *   Decoded payload array or NULL.
   */
  private function decodeSearchPayloadToken(string $encoded): ?array {
    $encoded = urldecode($encoded);
    $raw_json = json_decode($encoded, TRUE);
    if (is_array($raw_json)) {
      return $raw_json;
    }
    // Query parsing may convert "+" to spaces; restore before base64 decode.
    $encoded = str_replace(' ', '+', trim($encoded));
    $remainder = strlen($encoded) % 4;
    if ($remainder > 0) {
      $encoded .= str_repeat('=', 4 - $remainder);
    }
    $decoded = base64_decode(strtr($encoded, '-_', '+/'), TRUE);
    if ($decoded === FALSE) {
      $decoded = base64_decode($encoded, TRUE);
    }

    if ($decoded === FALSE) {
      return NULL;
    }

    $job_data = json_decode($decoded, TRUE);
    return is_array($job_data) ? $job_data : NULL;
  }

  /**
   * Create a minimal Forseti job record from a legacy search payload.
   *
   * @param string $encoded
   *   Raw search token from query string.
   *
   * @return int|null
   *   Created Forseti job ID or NULL if payload is not usable.
   */
  private function createJobFromSearchPayload(string $encoded): ?int {
    $job_data = $this->decodeSearchPayloadToken($encoded);
    $job_data = is_array($job_data) ? $job_data : [];

    $job_title = trim((string) ($job_data['job_title'] ?? $job_data['title'] ?? 'Imported External Job'));

    $external_job_id = trim((string) ($job_data['htidocid'] ?? $job_data['job_id'] ?? $job_data['id'] ?? $encoded));
    if ($external_job_id === '' || preg_match('/^(forseti|staging)_\d+$/', $external_job_id)) {
      return NULL;
    }
    $location = trim((string) ($job_data['address_city'] ?? $job_data['location'] ?? ''));
    $job_url = trim((string) ($job_data['job_url'] ?? $job_data['link'] ?? $job_data['url'] ?? ''));
    $now = time();

    $fields = [
      'job_title' => $job_title,
      'status' => 'active',
      'created' => $now,
      'updated' => $now,
      'external_source' => 'Google Jobs (SerpAPI)',
      'source_platform' => 'serpapi',
    ];

    if ($location !== '') {
      $fields['location'] = $location;
    }
    if ($job_url !== '') {
      $fields['job_url'] = substr($job_url, 0, 512);
    }
    if ($external_job_id !== '') {
      $fields['external_job_id'] = $this->normalizeExternalJobId($external_job_id);
    }
    if (!empty($job_data['description'])) {
      $fields['job_description'] = (string) $job_data['description'];
    }

    return (int) $this->database->insert('jobhunter_job_requirements')
      ->fields($fields)
      ->execute();
  }

  /**
   * Normalize external job IDs to fit schema constraints safely.
   *
   * @param string $external_job_id
   *   Source-provided external job identifier.
   *
   * @return string
   *   A schema-safe external job ID.
   */
  private function normalizeExternalJobId(string $external_job_id): string {
    if (strlen($external_job_id) <= 255) {
      return $external_job_id;
    }

    return 'hash_' . hash('sha256', $external_job_id);
  }

  /**
   * My Jobs page - displays user's saved job postings.
   *
   * @return array
   *   Renderable array for the my jobs page.
   */
  public function myJobs(): array {
    $request = $this->requestStack->getCurrentRequest();
    $filters = [
      'company' => $request->query->get('company', ''),
      'status' => $request->query->get('status', ''),
      'ai_status' => $request->query->get('ai_status', ''),
      'tailoring' => $request->query->get('tailoring', ''),
    ];
    $per_page = 50;
    $page = max(0, (int) $request->query->get('page', 0));

    $total = $this->jobDiscoveryService->getSavedJobsFiltered($filters);
    $jobs = $this->jobDiscoveryService->getSavedJobs($filters, $page, $per_page);
    $companies = $this->jobDiscoveryService->getCompanyNames();
    $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;

    $content = [
      '#theme' => 'my_jobs',
      '#jobs' => $jobs,
      '#companies' => $companies,
      '#filter_company' => $filters['company'],
      '#filter_status' => $filters['status'],
      '#filter_ai_status' => $filters['ai_status'],
      '#filter_tailoring' => $filters['tailoring'],
      '#return_url' => $request->getRequestUri(),
      '#current_page' => $page,
      '#total_pages' => $total_pages,
      '#total_jobs' => $total,
      '#cache' => [
        'contexts' => ['user', 'url.query_args'],
        'tags' => ['job_hunter:jobs', 'job_hunter:companies'],
      ],
    ];

    return $this->wrapWithNavigation($content);
  }

  /**
   * Archive a saved job (sets status to 'archived').
   */
  public function archiveJob(int $job_id): RedirectResponse {
    $request = $this->requestStack->getCurrentRequest();
    $return_to = (string) $request->query->get('return_to', '/jobhunter/my-jobs');
    if (strpos($return_to, '/') !== 0) {
      $return_to = '/jobhunter/my-jobs';
    }

    if ($this->currentUser()->isAnonymous()) {
      return new RedirectResponse('/user/login');
    }

    try {
      // Verify ownership via jobhunter_saved_jobs before updating status.
      $owned = $this->database->select('jobhunter_saved_jobs', 'sj')
        ->fields('sj', ['job_id'])
        ->condition('sj.uid', (int) $this->currentUser()->id())
        ->condition('sj.job_id', $job_id)
        ->execute()
        ->fetchField();

      if (!$owned) {
        $this->messenger()->addError($this->t('Job not found.'));
        return new RedirectResponse($return_to);
      }

      $this->database->update('jobhunter_job_requirements')
        ->fields(['status' => 'archived'])
        ->condition('id', $job_id)
        ->execute();

      $this->messenger()->addMessage($this->t('Job archived.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to archive job. Please try again.'));
      $this->getLogger('job_hunter')->error('Failed to archive job @id: @error', [
        '@id' => $job_id,
        '@error' => $e->getMessage(),
      ]);
    }

    return new RedirectResponse($return_to);
  }

  /**
   * Unarchive a job (sets status back to 'active').
   */
  public function unarchiveJob(int $job_id): RedirectResponse {
    $request = $this->requestStack->getCurrentRequest();
    $return_to = (string) $request->query->get('return_to', '/jobhunter/my-jobs/archive');
    if (strpos($return_to, '/') !== 0) {
      $return_to = '/jobhunter/my-jobs/archive';
    }

    if ($this->currentUser()->isAnonymous()) {
      return new RedirectResponse('/user/login');
    }

    try {
      $owned = $this->database->select('jobhunter_saved_jobs', 'sj')
        ->fields('sj', ['job_id'])
        ->condition('sj.uid', (int) $this->currentUser()->id())
        ->condition('sj.job_id', $job_id)
        ->execute()
        ->fetchField();

      if (!$owned) {
        $this->messenger()->addError($this->t('Job not found.'));
        return new RedirectResponse($return_to);
      }

      $this->database->update('jobhunter_job_requirements')
        ->fields(['status' => 'active'])
        ->condition('id', $job_id)
        ->execute();

      $this->messenger()->addMessage($this->t('Job restored to My Jobs.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to restore job. Please try again.'));
      $this->getLogger('job_hunter')->error('Failed to unarchive job @id: @error', [
        '@id' => $job_id,
        '@error' => $e->getMessage(),
      ]);
    }

    return new RedirectResponse($return_to);
  }

  /**
   * Archive page — shows archived jobs with pagination.
   */
  public function myJobsArchive(): array {
    $request = $this->requestStack->getCurrentRequest();
    $per_page = 50;
    $page = max(0, (int) $request->query->get('page', 0));

    $total = $this->jobDiscoveryService->getArchivedJobsCount();
    $jobs = $this->jobDiscoveryService->getArchivedJobs($page, $per_page);
    $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;

    $content = [
      '#theme' => 'my_jobs_archive',
      '#jobs' => $jobs,
      '#current_page' => $page,
      '#total_pages' => $total_pages,
      '#total_jobs' => $total,
      '#cache' => [
        'contexts' => ['user', 'url.query_args'],
        'tags' => ['job_hunter:jobs'],
      ],
    ];

    return $this->wrapWithNavigation($content);
  }

  /**
   * Toggle "have applied" status for a saved job.
   *
   * @param int $job_id
   *   The job requirement ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects back to My Jobs page.
   */
  public function toggleJobApplied(int $job_id): RedirectResponse {
    $request = $this->requestStack->getCurrentRequest();
    $return_to = (string) $request->request->get('return_to', '/jobhunter/my-jobs');

    if ($this->currentUser()->isAnonymous()) {
      $this->messenger()->addError($this->t('You must be logged in to update job status.'));
      return new RedirectResponse('/user/login');
    }

    if (strpos($return_to, '/') !== 0) {
      $return_to = '/jobhunter/my-jobs';
    }

    try {
      $saved_mapping_exists = (bool) $this->database->select('jobhunter_saved_jobs', 'sj')
        ->fields('sj', ['id'])
        ->condition('sj.uid', (int) $this->currentUser()->id())
        ->condition('sj.job_id', $job_id)
        ->execute()
        ->fetchField();

      if (!$saved_mapping_exists) {
        $this->messenger()->addError($this->t('Job not found in your saved jobs.'));
        return new RedirectResponse($return_to);
      }

      $query = $this->database->select('jobhunter_job_requirements', 'j')
        ->fields('j', ['id', 'status', 'applied_on_date'])
        ->condition('j.id', $job_id);

      $job = $query->execute()->fetchObject();
      if (!$job) {
        $this->messenger()->addError($this->t('Job not found or access denied.'));
        return new RedirectResponse($return_to);
      }

      $have_applied = (bool) $request->request->get('have_applied');
      $applied_on_date = trim((string) $request->request->get('applied_on_date', ''));
      $is_valid_date = $applied_on_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $applied_on_date) === 1;

      $update_fields = [
        'status' => $have_applied ? 'applied' : 'active',
        'applied_on_date' => $have_applied ? ($is_valid_date ? $applied_on_date : date('Y-m-d')) : NULL,
        'updated' => time(),
      ];

      $this->database->update('jobhunter_job_requirements')
        ->fields($update_fields)
        ->condition('id', $job_id)
        ->execute();

      if ($have_applied) {
        $this->messenger()->addStatus($this->t('Marked as applied.'));
      }
      else {
        $this->messenger()->addStatus($this->t('Marked as not applied.'));
      }
    }
    catch (\Exception $e) {
      $this->getLogger('job_hunter')->error('Failed to toggle applied status for job @job_id: @error', [
        '@job_id' => $job_id,
        '@error' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('Unable to update applied status right now.'));
    }

    return new RedirectResponse($return_to);
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
      'page' => $request->query->get('page', 1),
      'next_page_token' => $request->query->get('next_page_token', ''),
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
      '#pagination' => $search_results['pagination'] ?? [],
      '#current_page' => $search_params['page'],
      '#save_job_csrf_token' => $this->csrfTokenGenerator->get('job_hunter.addposting'),
      '#attached' => [
        'library' => [
          'job_hunter/job-search-results',
        ],
      ],
      '#cache' => [
        'contexts' => ['url.query_args', 'user'],
        'tags' => ['job_hunter:search'],
        // CSRF tokens are per-session, not per-user: must not be cached.
        'max-age' => 0,
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
    $uid = (int) $this->currentUser()->id();
    $summary = $this->getApplicationSubmissionSummary($uid);
    $applications = $this->getRecentApplicationSubmissions($uid, 25);

    $rows = '';
    foreach ($applications as $application) {
      $job_id = (int) ($application['job_id'] ?? 0);
      $job_title = htmlspecialchars($application['job_title'] ?? ('Job #' . $job_id), ENT_QUOTES, 'UTF-8');
      $status = htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($application['submission_status'] ?? 'unknown'))), ENT_QUOTES, 'UTF-8');
      $attempt_count = (int) ($application['attempt_count'] ?? 0);
      $ats_platform = htmlspecialchars((string) ($application['ats_platform'] ?? ''), ENT_QUOTES, 'UTF-8');
      $confirmation = htmlspecialchars((string) ($application['confirmation_reference'] ?? $application['confirmation_ref'] ?? ''), ENT_QUOTES, 'UTF-8');
      $apply_url = (string) ($application['apply_url'] ?? '');
      $job_url = Url::fromRoute('job_hunter.job_view', ['job_id' => $job_id])->toString();
      $apply_link = $apply_url !== ''
        ? '<a href="' . htmlspecialchars($apply_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Open Apply URL</a>'
        : '—';

      $rows .= '<tr>'
        . '<td><a href="' . htmlspecialchars($job_url, ENT_QUOTES, 'UTF-8') . '">' . $job_title . '</a></td>'
        . '<td>' . $status . '</td>'
        . '<td>' . $attempt_count . '</td>'
        . '<td>' . ($ats_platform !== '' ? $ats_platform : '—') . '</td>'
        . '<td>' . ($confirmation !== '' ? $confirmation : '—') . '</td>'
        . '<td>' . $apply_link . '</td>'
        . '</tr>';
    }

    if ($rows === '') {
      $rows = '<tr><td colspan="6">No applications submitted yet. Start from <a href="' . Url::fromRoute('job_hunter.my_jobs')->toString() . '">My Jobs</a>.</td></tr>';
    }

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
      'summary' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['job-info-box']],
        '#value' => '<strong>Total:</strong> ' . (int) $summary['total']
          . ' &nbsp;|&nbsp; <strong>Submitted:</strong> ' . (int) $summary['submitted']
          . ' &nbsp;|&nbsp; <strong>Pending/Processing:</strong> ' . (int) $summary['processing']
          . ' &nbsp;|&nbsp; <strong>Manual Required:</strong> ' . (int) $summary['manual_required']
          . ' &nbsp;|&nbsp; <strong>Failed:</strong> ' . (int) $summary['failed'],
      ],
      'table' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['job-info-box']],
        '#value' => '<h3>Recent Application Submissions</h3>'
          . '<table class="queue-status-table"><thead><tr>'
          . '<th>Job</th><th>Status</th><th>Attempts</th><th>ATS</th><th>Confirmation</th><th>Action</th>'
          . '</tr></thead><tbody>'
          . $rows
          . '</tbody></table>',
      ],
    ];
    
    return $this->wrapWithNavigation($content);
  }

  /**
   * Gets summary counts for a user's application submissions.
   */
  private function getApplicationSubmissionSummary(int $uid): array {
    if (!$this->database->schema()->tableExists('jobhunter_applications')) {
      return ['total' => 0, 'submitted' => 0, 'processing' => 0, 'manual_required' => 0, 'failed' => 0];
    }

    $base = $this->database->select('jobhunter_applications', 'a')
      ->condition('a.uid', $uid);

    $total = (int) (clone $base)->countQuery()->execute()->fetchField();
    $submitted = (int) (clone $base)->condition('a.submission_status', 'submitted')->countQuery()->execute()->fetchField();
    $processing = (int) (clone $base)->condition('a.submission_status', ['pending', 'processing', 'queued'], 'IN')->countQuery()->execute()->fetchField();
    $manual_required = (int) (clone $base)->condition('a.submission_status', 'manual_required')->countQuery()->execute()->fetchField();
    $failed = (int) (clone $base)->condition('a.submission_status', 'failed')->countQuery()->execute()->fetchField();

    return [
      'total' => $total,
      'submitted' => $submitted,
      'processing' => $processing,
      'manual_required' => $manual_required,
      'failed' => $failed,
    ];
  }

  /**
   * Gets recent applications with optional fields when available.
   */
  private function getRecentApplicationSubmissions(int $uid, int $limit = 25): array {
    $schema = $this->database->schema();
    if (!$schema->tableExists('jobhunter_applications')) {
      return [];
    }
    $query = $this->database->select('jobhunter_applications', 'a')
      ->condition('a.uid', $uid)
      ->orderBy('a.created', 'DESC')
      ->range(0, $limit);

    $fields = ['id', 'job_id', 'submission_status'];
    foreach (['attempt_count', 'ats_platform', 'apply_url', 'confirmation_reference', 'confirmation_ref'] as $optional_field) {
      if ($schema->fieldExists('jobhunter_applications', $optional_field)) {
        $fields[] = $optional_field;
      }
    }
    $query->fields('a', $fields);

    if ($schema->tableExists('jobhunter_job_requirements') && $schema->fieldExists('jobhunter_job_requirements', 'job_title')) {
      $query->leftJoin('jobhunter_job_requirements', 'j', 'a.job_id = j.id');
      $query->addField('j', 'job_title');
    }

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
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
