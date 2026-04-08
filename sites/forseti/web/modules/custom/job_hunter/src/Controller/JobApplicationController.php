<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\job_hunter\Repository\JobApplicationRepository;
use Drupal\Core\Link;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\job_hunter\Service\JobDiscoveryService;
use Drupal\job_hunter\Service\SearchAggregatorService;
use Drupal\job_hunter\Service\UserProfileService;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * The job application repository.
   *
   * @var \Drupal\job_hunter\Repository\JobApplicationRepository
   */
  protected JobApplicationRepository $repository;

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
   * @param \Drupal\job_hunter\Repository\JobApplicationRepository $repository
   *   The job application repository.
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
    JobApplicationRepository $repository,
    QueueFactory $queue_factory,
    SearchAggregatorService $search_aggregator,
    EntityTypeManagerInterface $entity_type_manager,
    UserProfileService $user_profile_service,
    CsrfTokenGenerator $csrf_token_generator
  ) {
    $this->jobDiscoveryService = $job_discovery_service;
    $this->requestStack = $request_stack;
    $this->repository = $repository;
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
      $container->get('job_hunter.job_application_repository'),
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
    return $this->repository->countJobRequirements();
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
    $companies = $this->repository->getAllCompanies();
    $job_results = $this->repository->getActiveJobCountsByCompany();
    
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
    $jobs = $this->repository->getActiveJobsForCompanyExtraction();
    
    $companies = [];
    
    foreach ($jobs as $job) {
      $company_name = null;
      
      // First, try to get company from company_id
      if ($job->company_id) {
        $company = $this->repository->getCompanyName((int) $job->company_id);
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
      $existing_mapping = $this->repository->findSavedJobMappingId($uid, $target_job_id);

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

      $this->repository->insertSavedJob($uid, $target_job_id);

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
      $imported_job_id = $this->repository->getImportedJobIdFromStaging((int) $matches[1]);
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
    return $this->repository->findJobIdByExternalId($external_id);
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
    $source_platform = '';
    if ($job_url !== '') {
      $parsed_host = parse_url($job_url, PHP_URL_HOST);
      if (is_string($parsed_host) && $parsed_host !== '') {
        $source_platform = substr(preg_replace('/^www\./', '', strtolower($parsed_host)), 0, 100);
      }
    }
    $now = time();

    $fields = [
      'job_title' => $job_title,
      'status' => 'active',
      'created' => $now,
      'updated' => $now,
      'external_source' => 'Google Jobs (SerpAPI)',
      'source_platform' => $source_platform,
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

    return $this->repository->insertJobRequirement($fields);
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
   * Derives a workflow status per job based on profile, tailoring, and
   * application state:
   *   profile_pending → tailoring_pending → tailoring_processing →
   *   application_pending → pending_response → closed
   *
   * @return array
   *   Renderable array for the my jobs page.
   */
  public function myJobs(): array {
    $request = $this->requestStack->getCurrentRequest();
    $filters = [
      'company' => $request->query->get('company', ''),
      'status' => $request->query->get('status', ''),
      'platform' => $request->query->get('platform', ''),
    ];
    $per_page = 50;
    $page = max(0, (int) $request->query->get('page', 0));

    $total = $this->jobDiscoveryService->getSavedJobsFiltered($filters);
    $jobs = $this->jobDiscoveryService->getSavedJobs($filters, $page, $per_page);
    $companies = $this->jobDiscoveryService->getCompanyNames();
    $platforms = $this->jobDiscoveryService->getSourcePlatforms();
    $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;

    // Determine whether the user has a completed profile.
    $has_profile = $this->userHasCompletedProfile();

    // Derive the workflow status for each job.
    foreach ($jobs as $job) {
      $job->workflow_status = $this->deriveWorkflowStatus($job, $has_profile);
      // Resolve display platform: prefer via (friendly name), fall back to source_platform.
      $job->display_platform = !empty($job->via) ? $job->via : (!empty($job->source_platform) ? $job->source_platform : '');
      $job->apply_csrf_token = \Drupal::csrfToken()->get('jobhunter/my-jobs/' . (int) $job->id . '/applied');
    }

    $content = [
      '#theme' => 'my_jobs',
      '#jobs' => $jobs,
      '#companies' => $companies,
      '#platforms' => $platforms,
      '#filter_company' => $filters['company'],
      '#filter_status' => $filters['status'],
      '#filter_platform' => $filters['platform'],
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
   * Check whether the current user has a completed job-seeker profile.
   *
   * A profile is considered complete when a consolidated_profile_json exists.
   *
   * @return bool
   *   TRUE if the user has a profile, FALSE otherwise.
   */
  private function userHasCompletedProfile(): bool {
    return $this->repository->hasCompletedProfile((int) $this->currentUser()->id());
  }

  /**
   * Derive the user-facing workflow status for a saved job.
   *
   * Status priority (first match wins):
   *   1. closed        – job.status == 'closed'
   *   2. pending_response – application submitted successfully
  *   3. approval_pending – tailoring complete, awaiting user PDF generation
  *   4. application_pending – PDF generated, no application yet
  *   5. tailoring_processing – tailoring queued / in progress
  *   6. tailoring_pending – profile done, no tailoring started
  *   7. profile_pending – user has no consolidated profile
   *
   * @param object $job
   *   Job row from getSavedJobs().
   * @param bool $has_profile
   *   Whether the user has a completed profile.
   *
   * @return string
   *   One of: profile_pending, tailoring_pending, tailoring_processing,
  *   approval_pending, application_pending, pending_response, closed.
   */
  private function deriveWorkflowStatus(object $job, bool $has_profile): string {
    // Closed takes priority.
    if (($job->status ?? '') === 'closed') {
      return 'closed';
    }

    // If an application was submitted successfully, we're waiting on company.
    $app_status = $job->application_status ?? '';
    if (in_array($app_status, ['submitted', 'confirmed', 'manual_completed'], TRUE)) {
      return 'pending_response';
    }

    // If tailoring is complete, user must approve/generate PDF before apply.
    $tailoring = $job->tailoring_status ?? '';
    if ($tailoring === 'completed') {
      $pdf_generated = (int) ($job->pdf_generated ?? 0);
      $pdf_path = (string) ($job->pdf_path ?? '');
      if ($pdf_generated === 1 || $pdf_path !== '') {
        return 'application_pending';
      }
      return 'approval_pending';
    }

    // If tailoring is actively queued or processing in the DB.
    if (in_array($tailoring, ['processing', 'queued'], TRUE)) {
      return 'tailoring_processing';
    }

    // If DB says "pending" a tailored_resumes row exists but the queue may
    // have already picked it up.  Check the actual Drupal queue to keep the
    // status honest (queue stores PHP-serialized data, not JSON).
    if ($tailoring === 'pending') {
      $uid = (int) $this->currentUser()->id();
      $job_id = (int) $job->id;
      if ($this->isItemInTailoringQueue($uid, $job_id)) {
        // Sync the DB so subsequent loads are correct without re-scanning.
        $this->repository->updateTailoredResume($uid, $job_id, ['tailoring_status' => 'queued', 'updated' => time()]);
        return 'tailoring_processing';
      }
    }

    // If user has no profile yet.
    if (!$has_profile) {
      return 'profile_pending';
    }

    // Default: profile exists but no tailoring started.
    return 'tailoring_pending';
  }

  /**
   * Check whether a tailoring queue item exists for a user + job.
   *
   * Drupal's queue table stores data as PHP-serialized blobs, so we
   * must unserialize and compare fields properly.
   *
   * @param int $uid
   *   The user ID.
   * @param int $job_id
   *   The job requirement ID.
   *
   * @return bool
   *   TRUE if a matching item is in the active queue.
   */
  private function isItemInTailoringQueue(int $uid, int $job_id): bool {
    $rows = $this->repository->getQueueDataItems('job_hunter_resume_tailoring');

    foreach ($rows as $row) {
      $item = @unserialize($row->data, ['allowed_classes' => FALSE]);
      if (is_array($item)
          && (int) ($item['uid'] ?? 0) === $uid
          && (int) ($item['job_id'] ?? 0) === $job_id) {
        return TRUE;
      }
    }

    return FALSE;
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
      $owned = $this->repository->findSavedJobMappingId((int) $this->currentUser()->id(), $job_id);

      if (!$owned) {
        $this->messenger()->addError($this->t('Job not found.'));
        return new RedirectResponse($return_to);
      }

      $this->repository->updateJobRequirement($job_id, ['status' => 'archived']);

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
      $owned = $this->repository->findSavedJobMappingId((int) $this->currentUser()->id(), $job_id);

      if (!$owned) {
        $this->messenger()->addError($this->t('Job not found.'));
        return new RedirectResponse($return_to);
      }

      $this->repository->updateJobRequirement($job_id, ['status' => 'active']);

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
      $saved_mapping_exists = (bool) $this->repository->findSavedJobMappingId((int) $this->currentUser()->id(), $job_id);

      if (!$saved_mapping_exists) {
        $this->messenger()->addError($this->t('Job not found in your saved jobs.'));
        return new RedirectResponse($return_to);
      }

      $job = $this->repository->getJobById($job_id, ['id', 'status', 'applied_on_date']);
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

      $this->repository->updateJobRequirement($job_id, $update_fields);

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
  public function applicationSubmission(?int $job_id = NULL) {
    $uid = (int) $this->currentUser()->id();
    $summary = $this->getApplicationSubmissionSummary($uid, $job_id);
    $applications = $this->getRecentApplicationSubmissions($uid, 25, $job_id);
    $has_profile = $this->userHasCompletedProfile();
    $saved_jobs = $this->jobDiscoveryService->getSavedJobs([], 0, 200);

    $selected_job = NULL;
    $selected_workflow_status = '';
    $ready_jobs = [];
    $approval_jobs = [];
    foreach ($saved_jobs as $job) {
      if ($job_id !== NULL && (int) $job->id !== (int) $job_id) {
        continue;
      }

      $job->workflow_status = $this->deriveWorkflowStatus($job, $has_profile);
      $job->display_platform = !empty($job->via) ? $job->via : (!empty($job->source_platform) ? $job->source_platform : '');
      $job->apply_csrf_token = \Drupal::csrfToken()->get('jobhunter/my-jobs/' . (int) $job->id . '/applied');

      if ($job_id !== NULL && (int) $job->id === (int) $job_id) {
        $selected_job = $job;
        $selected_workflow_status = (string) $job->workflow_status;
      }

      if ($job->workflow_status === 'application_pending') {
        $ready_jobs[] = $job;
      }
      elseif ($job->workflow_status === 'approval_pending') {
        $approval_jobs[] = $job;
      }
    }

    if ($job_id !== NULL && !$selected_job) {
      $selected_job = $this->loadSelectedJobContext($uid, $job_id);
      if ($selected_job) {
        $selected_job->workflow_status = $this->deriveWorkflowStatus($selected_job, $has_profile);
        $selected_job->display_platform = !empty($selected_job->via) ? $selected_job->via : (!empty($selected_job->source_platform) ? $selected_job->source_platform : '');
        $selected_job->apply_csrf_token = \Drupal::csrfToken()->get('jobhunter/my-jobs/' . (int) $selected_job->id . '/applied');
        $selected_workflow_status = (string) $selected_job->workflow_status;

        if ($selected_job->workflow_status === 'application_pending') {
          $ready_jobs[] = $selected_job;
        }
        elseif ($selected_job->workflow_status === 'approval_pending') {
          $approval_jobs[] = $selected_job;
        }
      }
    }

    $stage_counts = [
      'approval_pending' => count($approval_jobs),
      'application_pending' => count($ready_jobs),
      'processing' => (int) ($summary['processing'] ?? 0),
      'submitted' => (int) ($summary['submitted'] ?? 0),
      'manual_required' => (int) ($summary['manual_required'] ?? 0),
      'failed' => (int) ($summary['failed'] ?? 0),
    ];

    $latest_attempts = $this->getLatestAttemptsByApplicationIds(array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $applications));

    $recent_applications = [];
    foreach ($applications as $application) {
      $application_id = (int) ($application['id'] ?? 0);
      $application_job_id = (int) ($application['job_id'] ?? 0);
      $metadata = [];
      if (!empty($application['metadata']) && is_string($application['metadata'])) {
        $decoded = json_decode($application['metadata'], TRUE);
        if (is_array($decoded)) {
          $metadata = $decoded;
        }
      }

      $last_attempt = $latest_attempts[$application_id] ?? [];
      $recent_applications[] = [
        'application_id' => $application_id,
        'job_id' => $application_job_id,
        'job_title' => (string) ($application['job_title'] ?? ('Job #' . $application_job_id)),
        'submission_status' => (string) ($application['submission_status'] ?? 'unknown'),
        'status_label' => ucwords(str_replace('_', ' ', (string) ($application['submission_status'] ?? 'unknown'))),
        'attempt_count' => (int) ($application['attempt_count'] ?? 0),
        'ats_platform' => (string) ($application['ats_platform'] ?? ''),
        'selected_apply_option' => (string) ($application['selected_apply_option'] ?? ''),
        'resolution_confidence' => (string) ($metadata['confidence'] ?? ''),
        'resolution_steps_count' => is_array($metadata['resolution_steps'] ?? NULL) ? count($metadata['resolution_steps']) : 0,
        'verification_passed' => !empty($metadata['verification_passed_at']),
        'auth_type' => (string) ($metadata['auth_type'] ?? ''),
        'account_readiness_at' => (string) ($metadata['account_readiness_at'] ?? ''),
        'confirmation' => (string) ($application['confirmation_reference'] ?? $application['confirmation_ref'] ?? ''),
        'apply_url' => (string) ($application['apply_url'] ?? ''),
        'last_attempt_outcome' => (string) ($last_attempt['outcome'] ?? ''),
        'last_attempt_error' => (string) ($last_attempt['error_message'] ?? ''),
        'last_attempt_at' => (string) ($last_attempt['attempted_at'] ?? ''),
        'apply_csrf_token' => \Drupal::csrfToken()->get('jobhunter/my-jobs/' . (int) $application_job_id . '/applied'),
      ];
    }

    $selected_application = NULL;
    if ($job_id !== NULL && !empty($recent_applications)) {
      $selected_application = $recent_applications[0];
    }

    if ($job_id !== NULL && !$selected_application && $selected_job) {
      try {
        $resolved = \Drupal::service('job_hunter.apply_url_resolver')->resolve([
          'apply_options' => (string) ($selected_job->apply_options ?? ''),
          'job_url' => (string) ($selected_job->job_url ?? ''),
        ]);

        $selected_application = [
          'application_id' => 0,
          'job_id' => (int) $selected_job->id,
          'job_title' => (string) ($selected_job->job_title ?? ('Job #' . (int) $selected_job->id)),
          'submission_status' => 'not_started',
          'status_label' => 'Not Started',
          'attempt_count' => 0,
          'ats_platform' => (string) ($resolved['ats_platform'] ?? ''),
          'selected_apply_option' => (string) ($resolved['selected_option'] ?? ''),
          'resolution_confidence' => (string) ($resolved['confidence'] ?? ''),
          'resolution_steps_count' => is_array($resolved['resolution_steps'] ?? NULL) ? count($resolved['resolution_steps']) : 0,
          'confirmation' => '',
          'apply_url' => (string) ($resolved['url'] ?? ''),
          'last_attempt_outcome' => '',
          'last_attempt_error' => '',
          'last_attempt_at' => '',
          'account_readiness_at' => '',
          'apply_csrf_token' => \Drupal::csrfToken()->get('jobhunter/my-jobs/' . (int) $selected_job->id . '/applied'),
        ];
      }
      catch (\Exception $e) {
        $this->getLogger('job_hunter')->warning('Unable to resolve redirect chain for job @job_id: @error', [
          '@job_id' => $job_id,
          '@error' => $e->getMessage(),
        ]);
      }
    }

    $selected_attempt = NULL;
    if (!empty($selected_application['application_id'])) {
      $selected_attempt = $latest_attempts[(int) $selected_application['application_id']] ?? NULL;
    }

    $journey_steps = $this->buildJobJourneyFlow(
      $selected_job,
      $selected_workflow_status,
      $selected_application,
      $selected_attempt,
      $has_profile
    );

    $return_url = $job_id !== NULL
      ? '/jobhunter/application-submission/' . (int) $job_id
      : '/jobhunter/application-submission';

    $is_job_specific = $job_id !== NULL;
    $job_snapshot = NULL;
    if ($is_job_specific && $selected_job) {
      $extracted = is_array($selected_job->extracted_data ?? NULL) ? $selected_job->extracted_data : [];
      $job_title = (string) ($extracted['position']['title'] ?? $selected_job->job_title ?? ('Job #' . (int) $selected_job->id));
      $company_name = (string) ($extracted['company']['name'] ?? $selected_job->company_name ?? 'Unknown');
      $career_url = (string) ($selected_application['apply_url'] ?? $selected_job->job_url ?? '');
      $original_job_url = (string) ($selected_job->job_url ?? '');
      $career_host = '';
      if ($career_url !== '') {
        $parsed_host = parse_url($career_url, PHP_URL_HOST);
        $career_host = is_string($parsed_host) ? $parsed_host : '';
      }

      $pdf_generated = ((int) ($selected_job->pdf_generated ?? 0) > 0) || ((string) ($selected_job->pdf_path ?? '') !== '');
      $submission_status = (string) ($selected_application['submission_status'] ?? 'not_started');
      $resolution_steps_count = (int) ($selected_application['resolution_steps_count'] ?? 0);
      $resolution_confidence = (string) ($selected_application['resolution_confidence'] ?? '');
      $is_direct_company_link = $resolution_steps_count === 0;
      $redirect_chain_resolved = $resolution_steps_count > 0 && in_array(strtolower($resolution_confidence), ['high', 'medium'], TRUE);
      $attempt_count = (int) ($selected_application['attempt_count'] ?? 0);
      $last_attempt_outcome = (string) ($selected_application['last_attempt_outcome'] ?? '');
      $last_attempt_error = (string) ($selected_application['last_attempt_error'] ?? '');
      $career_page_identified = $career_url !== '';
      $auth_process_vetted = $attempt_count > 0 || in_array($submission_status, ['queued', 'pending', 'processing', 'submitted', 'confirmed', 'manual_required', 'failed', 'manual_completed'], TRUE);

      $job_snapshot = [
        'job_id' => (int) $selected_job->id,
        'job_title' => $job_title,
        'company_name' => $company_name,
        'workflow_status' => $selected_workflow_status,
        'pdf_generated' => $pdf_generated,
        'pdf_path' => (string) ($selected_job->pdf_path ?? ''),
        'original_job_url' => $original_job_url,
        'career_url' => $career_url,
        'career_host' => $career_host,
        'ats_platform' => (string) ($selected_application['ats_platform'] ?? ''),
        'submission_status' => $submission_status,
        'submission_status_label' => ucwords(str_replace('_', ' ', $submission_status)),
        'attempt_count' => $attempt_count,
        'last_attempt_outcome' => $last_attempt_outcome,
        'last_attempt_error' => $last_attempt_error,
        'resolution_steps_count' => $resolution_steps_count,
        'resolution_confidence' => $resolution_confidence,
        'is_direct_company_link' => $is_direct_company_link,
        'redirect_chain_resolved' => $redirect_chain_resolved,
        'career_page_identified' => $career_page_identified,
        'auth_process_vetted' => $auth_process_vetted,
      ];
    }

    $content = [
      '#theme' => 'application_submission',
      '#is_job_specific' => $is_job_specific,
      '#job_snapshot' => $job_snapshot,
      '#summary' => $summary,
      '#stage_counts' => $stage_counts,
      '#ready_jobs' => $ready_jobs,
      '#approval_jobs' => $approval_jobs,
      '#recent_applications' => $recent_applications,
      '#selected_job' => $selected_job,
      '#selected_workflow_status' => $selected_workflow_status,
      '#selected_application' => $selected_application,
      '#journey_steps' => $journey_steps,
      '#return_url' => $return_url,
      '#cache' => [
        'contexts' => ['user', 'url.query_args'],
        'tags' => ['job_hunter:jobs', 'job_hunter:applications'],
        'max-age' => 0,
      ],
    ];

    return $this->wrapWithNavigation($content);
  }

  /**
   * Dedicated Step 2 page: Resolve redirect chain for one requisition.
   */
  public function applicationSubmissionResolveRedirectChain(int $job_id): array {
    $uid = (int) $this->currentUser()->id();
    if ($uid <= 0) {
      return [
        '#markup' => $this->t('You must be logged in to access this page.'),
      ];
    }

    $selected_job = $this->loadSelectedJobContext($uid, $job_id);
    if (!$selected_job) {
      $this->messenger()->addError($this->t('Job requisition not found for your account.'));
      return $this->wrapWithNavigation([
        '#markup' => '<p>' . $this->t('Unable to load this requisition.') . '</p>',
      ]);
    }

    $extracted = is_array($selected_job->extracted_data ?? NULL) ? $selected_job->extracted_data : [];
    $job_title = (string) ($extracted['position']['title'] ?? $selected_job->job_title ?? ('Job #' . (int) $selected_job->id));
    $company_name = (string) ($extracted['company']['name'] ?? $selected_job->company_name ?? 'Unknown');
    $original_job_url = (string) ($selected_job->job_url ?? '');

    $request = $this->requestStack->getCurrentRequest();
    $run_step2_requested = FALSE;
    if ($request->isMethod('POST') && (string) $request->request->get('run_step2') === '1') {
      $token = (string) $request->request->get('csrf_token', '');
      if ($token !== '' && $this->csrfTokenGenerator->validate($token, 'job_hunter_step2_run_' . (int) $selected_job->id)) {
        $run_step2_requested = TRUE;
      }
      else {
        $this->messenger()->addError($this->t('Unable to run Step 2 checks because the request token is invalid. Refresh and try again.'));
      }
    }

    $existing_application = $this->repository->findLatestApplicationByJobAndUser($uid, (int) $selected_job->id, ['id', 'apply_url', 'ats_platform', 'metadata']);

    $metadata_base = [];
    if (!empty($existing_application['metadata'])) {
      $decoded_meta = json_decode((string) $existing_application['metadata'], TRUE);
      if (is_array($decoded_meta)) {
        $metadata_base = $decoded_meta;
      }
    }

    $step2_cache = is_array($metadata_base['step2_cache'] ?? NULL) ? $metadata_base['step2_cache'] : [];
    $has_cached_step2 = !empty($step2_cache);

    $resolved_url = (string) ($step2_cache['resolved_url'] ?? $existing_application['apply_url'] ?? '');
    $ats_platform = (string) ($step2_cache['ats_platform'] ?? $existing_application['ats_platform'] ?? 'unknown');
    $confidence = (string) ($step2_cache['confidence'] ?? $metadata_base['confidence'] ?? 'none');
    $resolution_steps = is_array($step2_cache['resolution_steps'] ?? NULL)
      ? $step2_cache['resolution_steps']
      : (is_array($metadata_base['resolution_steps'] ?? NULL) ? $metadata_base['resolution_steps'] : []);

    $verification = is_array($step2_cache['verification'] ?? NULL) ? $step2_cache['verification'] : [];
    if (empty($verification)) {
      $verification = [
        'final_pass' => !empty($metadata_base['verification_passed_at']),
        'decision_mode' => (string) ($metadata_base['verification_mode'] ?? ''),
        'error' => '',
        'checks' => [],
        'genai' => [
          'used' => FALSE,
          'available' => FALSE,
          'success' => FALSE,
          'confirmed' => FALSE,
          'confidence' => 'none',
          'response' => '',
          'evidence' => '',
        ],
      ];
    }

    if ($run_step2_requested || !$has_cached_step2) {
      $resolved = [];
      try {
        $resolved = \Drupal::service('job_hunter.apply_url_resolver')->resolve([
          'apply_options' => (string) ($selected_job->apply_options ?? ''),
          'job_url' => $original_job_url,
        ]);
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('Failed to resolve redirect chain: @error', ['@error' => $e->getMessage()]));
        $resolved = [
          'url' => $original_job_url,
          'ats_platform' => 'unknown',
          'resolution_steps' => [],
          'confidence' => 'none',
        ];
      }

      $resolution_steps = is_array($resolved['resolution_steps'] ?? NULL) ? $resolved['resolution_steps'] : [];
      $resolved_url = (string) ($resolved['url'] ?? '');
      $confidence = (string) ($resolved['confidence'] ?? 'none');
      $ats_platform = (string) ($resolved['ats_platform'] ?? 'unknown');

      try {
        @set_time_limit(120);
        $verification = \Drupal::service('job_hunter.application_location_verification_service')->verify((int) $selected_job->id, [
          'genai_fallback' => TRUE,
          'min_description_overlap' => 0.15,
          'timeout' => 45,
        ]);
      }
      catch (\Throwable $e) {
        $verification = [
          'final_pass' => FALSE,
          'decision_mode' => 'error',
          'error' => $e->getMessage(),
          'checks' => [],
          'genai' => [
            'used' => FALSE,
            'available' => FALSE,
            'success' => FALSE,
            'confirmed' => FALSE,
            'confidence' => 'none',
            'response' => '',
            'evidence' => '',
          ],
        ];
      }

      $redirect_hops_runtime = 0;
      foreach ($resolution_steps as $step) {
        if (($step['action'] ?? '') === 'following_redirect') {
          $redirect_hops_runtime++;
        }
      }
      $is_direct_link_runtime = $redirect_hops_runtime === 0;
      $has_career_page_runtime = $resolved_url !== '';
      $is_resolved_runtime = $has_career_page_runtime && in_array(strtolower($confidence), ['high', 'medium'], TRUE);

      $now = date('Y-m-d H:i:s');
      $effective_url = (string) ($verification['effective_url'] ?? $resolved_url);
      $effective_ats = (string) ($verification['ats_platform'] ?? $ats_platform ?: 'custom');

      $metadata_base['step2_cache'] = [
        'ran_at' => $now,
        'resolved_url' => $effective_url !== '' ? $effective_url : $resolved_url,
        'ats_platform' => $effective_ats,
        'confidence' => $confidence,
        'resolution_steps' => $resolution_steps,
        'is_direct_link' => $is_direct_link_runtime,
        'has_career_page' => $has_career_page_runtime,
        'is_resolved' => $is_resolved_runtime,
        'verification' => $verification,
      ];

      $metadata_base['confidence'] = !empty($verification['final_pass']) ? 'high' : $confidence;
      $metadata_base['resolution_steps'] = $resolution_steps;

      if (!empty($verification['final_pass'])) {
        $metadata_base['verification_passed_at'] = $now;
        $metadata_base['verification_mode'] = (string) ($verification['decision_mode'] ?? '');
      }

      if ($existing_application) {
        $this->repository->updateApplication((int) $existing_application['id'], [
          'apply_url' => $effective_url !== '' ? $effective_url : $resolved_url,
          'ats_platform' => $effective_ats,
          'metadata' => json_encode($metadata_base),
          'changed' => $now,
        ]);
      }
      else {
        $this->repository->insertApplication([
          'uid' => $uid,
          'job_id' => (int) $selected_job->id,
          'submission_status' => 'not_started',
          'submission_method' => 'pending',
          'apply_url' => $effective_url !== '' ? $effective_url : $resolved_url,
          'ats_platform' => $effective_ats,
          'attempt_count' => 0,
          'metadata' => json_encode($metadata_base),
          'created' => $now,
          'changed' => $now,
        ]);
      }

      if ($run_step2_requested) {
        $this->messenger()->addStatus($this->t('Step 2 checks completed and cached.'));
      }

      $has_cached_step2 = TRUE;
    }

    $redirect_hops = 0;
    foreach ($resolution_steps as $step) {
      if (($step['action'] ?? '') === 'following_redirect') {
        $redirect_hops++;
      }
    }

    $is_direct_link = !empty($step2_cache['is_direct_link']) || $redirect_hops === 0;
    $has_career_page = !empty($step2_cache['has_career_page']) || $resolved_url !== '';
    $is_resolved = !empty($step2_cache['is_resolved']) || ($has_career_page && in_array(strtolower($confidence), ['high', 'medium'], TRUE));

    if (!$run_step2_requested && !$has_cached_step2) {
      $verification = [
        'final_pass' => FALSE,
        'decision_mode' => 'not_run',
        'error' => 'Step 2 checks have not been run yet. Use the button above to execute and cache results.',
        'checks' => [],
        'genai' => [
          'used' => FALSE,
          'available' => FALSE,
          'success' => FALSE,
          'confirmed' => FALSE,
          'confidence' => 'none',
          'response' => '',
          'evidence' => '',
        ],
      ];
    }

    $content = [
      '#theme' => 'application_submission_step2',
      '#job_id' => (int) $selected_job->id,
      '#job_title' => $job_title,
      '#company_name' => $company_name,
      '#original_job_url' => $original_job_url,
      '#resolved_url' => $resolved_url,
      '#ats_platform' => $ats_platform,
      '#confidence' => $confidence,
      '#resolution_steps' => $resolution_steps,
      '#is_direct_link' => $is_direct_link,
      '#has_career_page' => $has_career_page,
      '#is_resolved' => $is_resolved,
      '#verification' => $verification,
      '#step2_cache_exists' => $has_cached_step2,
      '#step2_last_run_at' => (string) (($metadata_base['step2_cache']['ran_at'] ?? '')),
      '#step2_ran_this_request' => $run_step2_requested,
      '#run_step2_csrf_token' => $this->csrfTokenGenerator->get('job_hunter_step2_run_' . (int) $selected_job->id),
      '#return_url' => '/jobhunter/application-submission/' . (int) $selected_job->id,
      '#cache' => [
        'contexts' => ['user', 'url.query_args'],
        'tags' => ['job_hunter:jobs', 'job_hunter:applications'],
        'max-age' => 0,
      ],
    ];

    return $this->wrapWithNavigation($content);
  }

  /**
   * Step 3: Identify authentication path for a job application.
   *
   * Uses AuthPathIdentificationService to launch a stealth browser, click the
   * Apply button, and classify the auth mechanism (email/password, SSO, etc.).
   * Result is persisted to jobhunter_applications.metadata so the main
   * dashboard Step 3 gate reflects the outcome.
   *
   * @param int $job_id
   *   The job requisition ID.
   *
   * @return array
   *   A render array.
   */
  public function applicationSubmissionIdentifyAuthPath(int $job_id): array {
    $uid = (int) $this->currentUser()->id();
    if ($uid <= 0) {
      return [
        '#markup' => $this->t('You must be logged in to access this page.'),
      ];
    }

    $selected_job = $this->loadSelectedJobContext($uid, $job_id);
    if (!$selected_job) {
      $this->messenger()->addError($this->t('Job requisition not found for your account.'));
      return $this->wrapWithNavigation([
        '#markup' => '<p>' . $this->t('Unable to load this requisition.') . '</p>',
      ]);
    }

    $extracted = is_array($selected_job->extracted_data ?? NULL) ? $selected_job->extracted_data : [];
    $job_title = (string) ($extracted['position']['title'] ?? $selected_job->job_title ?? ('Job #' . (int) $selected_job->id));
    $company_name = (string) ($extracted['company']['name'] ?? $selected_job->company_name ?? 'Unknown');

    // ── Check for POST run request ──────────────────────────────────────────
    $request = $this->requestStack->getCurrentRequest();
    $run_step3_requested = FALSE;
    if ($request->isMethod('POST') && (string) $request->request->get('run_step3') === '1') {
      $token = (string) $request->request->get('csrf_token', '');
      if ($token !== '' && $this->csrfTokenGenerator->validate($token, 'job_hunter_step3_run_' . (int) $selected_job->id)) {
        $run_step3_requested = TRUE;
      }
      else {
        $this->messenger()->addError($this->t('Unable to run Step 3 checks because the request token is invalid. Refresh and try again.'));
      }
    }

    // ── Load existing application row + metadata ────────────────────────────
    $existing_application = $this->repository->findLatestApplicationByJobAndUser($uid, (int) $selected_job->id, ['id', 'apply_url', 'ats_platform', 'metadata']);

    $apply_url = (string) ($existing_application['apply_url'] ?? '');

    $metadata_base = [];
    if (!empty($existing_application['metadata'])) {
      $decoded_meta = json_decode((string) $existing_application['metadata'], TRUE);
      if (is_array($decoded_meta)) {
        $metadata_base = $decoded_meta;
      }
    }

    // ── Read cached Step 3 result ───────────────────────────────────────────
    $step3_cache = is_array($metadata_base['step3_cache'] ?? NULL) ? $metadata_base['step3_cache'] : [];
    $has_cached_step3 = !empty($step3_cache);

    // Default auth identification from cache (or empty).
    $auth_identification = $step3_cache ? $step3_cache['auth_identification'] ?? [] : [];

    // ── Run the stealth browser if requested or no cache ────────────────────
    if ($run_step3_requested) {
      try {
        @set_time_limit(120);
        $auth_identification = \Drupal::service('job_hunter.auth_path_identification_service')->identify(
          (int) $selected_job->id,
          ['timeout' => 45]
        );
      }
      catch (\Throwable $e) {
        $auth_identification = [
          'job_id'        => (int) $selected_job->id,
          'ok'            => FALSE,
          'auth_type'     => 'unknown',
          'sso_providers' => [],
          'form_fields'   => [],
          'auth_url'      => $apply_url,
          'page_title'    => '',
          'evidence'      => '',
          'html_excerpt'  => '',
          'error'         => $e->getMessage(),
        ];
      }

      // Persist the result to jobhunter_applications.metadata.
      try {
        $now  = date('Y-m-d H:i:s');
        $meta = $metadata_base;

        $meta['auth_type']               = (string) ($auth_identification['auth_type'] ?? 'unknown');
        $meta['auth_url']                = (string) ($auth_identification['auth_url'] ?? $auth_identification['apply_url'] ?? $apply_url);
        $meta['sso_providers']           = (array)  ($auth_identification['sso_providers'] ?? []);
        $meta['auth_identification_at']  = $now;
        $meta['step3_cache'] = [
          'ran_at' => $now,
          'auth_identification' => $auth_identification,
        ];

        // Detect ATS platform from the auth URL discovered by the stealth browser.
        $detected_ats = $this->detectAtsPlatformFromUrl((string) $meta['auth_url']);

        if ($existing_application) {
          $this->repository->updateApplication((int) $existing_application['id'], [
            'ats_platform' => $detected_ats,
            'metadata' => json_encode($meta),
            'changed'  => $now,
          ]);
        }
        else {
          $this->repository->insertApplication([
            'uid'              => $uid,
            'job_id'           => (int) $selected_job->id,
            'submission_status'=> 'not_started',
            'submission_method'=> 'pending',
            'apply_url'        => $apply_url,
            'ats_platform'     => $detected_ats,
            'attempt_count'    => 0,
            'metadata'         => json_encode($meta),
            'created'          => $now,
            'changed'          => $now,
          ]);
        }
      }
      catch (\Throwable $e) {
        // Non-fatal — continue to render the page even if persist fails.
      }

      $has_cached_step3 = TRUE;
      $this->messenger()->addStatus($this->t('Step 3 checks completed and cached.'));
    }

    $content = [
      '#theme'                 => 'application_submission_step3',
      '#job_id'                => (int) $selected_job->id,
      '#job_title'             => $job_title,
      '#company_name'          => $company_name,
      '#apply_url'             => $apply_url,
      '#auth_identification'   => $auth_identification,
      '#step3_cache_exists'    => $has_cached_step3,
      '#step3_last_run_at'     => (string) ($step3_cache['ran_at'] ?? ''),
      '#step3_ran_this_request'=> $run_step3_requested,
      '#run_step3_csrf_token'  => $this->csrfTokenGenerator->get('job_hunter_step3_run_' . (int) $selected_job->id),
      '#return_url'            => '/jobhunter/application-submission/' . (int) $selected_job->id,
      '#cache'                 => [
        'contexts' => ['user', 'url.query_args'],
        'tags'     => ['job_hunter:jobs', 'job_hunter:applications'],
        'max-age'  => 0,
      ],
    ];

    return $this->wrapWithNavigation($content);
  }

  /**
   * Step 4: Create account on the ATS platform.
   *
   * Loads the user's email / phone from jobhunter_job_seeker, reads
   * cached Step 3 auth type, and facilitates account creation on the
   * destination ATS (Workday, Greenhouse, etc.).
   *
   * Follows the same cache-first + POST-trigger model as Steps 2 & 3.
   * On POST (run_step4=1) persists account-readiness results to
   * metadata.step4_cache so the dashboard gate reflects the outcome.
   *
   * @param int $job_id
   *   The job requisition ID.
   *
   * @return array
   *   A render array.
   */
  public function applicationSubmissionCreateAccount(int $job_id): array {
    $uid = (int) $this->currentUser()->id();
    if ($uid <= 0) {
      return [
        '#markup' => $this->t('You must be logged in to access this page.'),
      ];
    }

    $selected_job = $this->loadSelectedJobContext($uid, $job_id);
    if (!$selected_job) {
      $this->messenger()->addError($this->t('Job requisition not found for your account.'));
      return $this->wrapWithNavigation([
        '#markup' => '<p>' . $this->t('Unable to load this requisition.') . '</p>',
      ]);
    }

    $extracted = is_array($selected_job->extracted_data ?? NULL) ? $selected_job->extracted_data : [];
    $job_title = (string) ($extracted['position']['title'] ?? $selected_job->job_title ?? ('Job #' . (int) $selected_job->id));
    $company_name = (string) ($extracted['company']['name'] ?? $selected_job->company_name ?? 'Unknown');
    $company_id = (int) ($selected_job->company_id ?? 0);

    // ── Load user profile (email, phone, name) from jobhunter_job_seeker ──
    $seeker = $this->repository->getJobSeekerProfile($uid, ['contact_email', 'contact_phone', 'full_name']) ?? [];

    $user_email = (string) ($seeker['contact_email'] ?? '');
    $user_phone = (string) ($seeker['contact_phone'] ?? '');
    $user_name  = (string) ($seeker['full_name'] ?? '');

    // Fall back to Drupal user entity email if seeker record is missing.
    if ($user_email === '') {
      $user = \Drupal\user\Entity\User::load($uid);
      if ($user) {
        $user_email = (string) $user->getEmail();
      }
    }

    // ── Load existing application row + metadata ──────────────────────────
    $existing_application = $this->repository->findLatestApplicationByJobAndUser($uid, (int) $selected_job->id, ['id', 'apply_url', 'ats_platform', 'metadata']);

    $apply_url = (string) ($existing_application['apply_url'] ?? '');
    $ats_platform = (string) ($existing_application['ats_platform'] ?? 'unknown');

    $metadata_base = [];
    if (!empty($existing_application['metadata'])) {
      $decoded_meta = json_decode((string) $existing_application['metadata'], TRUE);
      if (is_array($decoded_meta)) {
        $metadata_base = $decoded_meta;
      }
    }

    // Read auth type from Step 3 result.
    $auth_type = (string) ($metadata_base['auth_type'] ?? 'unknown');
    $auth_url  = (string) ($metadata_base['auth_url'] ?? $apply_url);

    // Re-detect ATS platform from auth URL if stored value is unhelpful.
    if (in_array($ats_platform, ['custom', 'unknown', ''], TRUE)) {
      $detected = $this->detectAtsPlatformFromUrl($auth_url);
      if ($detected !== 'custom') {
        $ats_platform = $detected;
        // Persist the corrected platform to the DB row.
        if ($existing_application) {
          $this->repository->updateApplication((int) $existing_application['id'], ['ats_platform' => $ats_platform]);
        }
      }
    }

    // ── Check for stored credentials via CredentialManagementService ──────
    /** @var \Drupal\job_hunter\Service\CredentialManagementService $cred_service */
    $cred_service = \Drupal::service('job_hunter.credential_management_service');

    $stored_credential = NULL;
    $has_stored_credential = FALSE;
    $stored_username = '';
    if ($company_id > 0) {
      $stored_credential = $cred_service->retrieveCredential($uid, $company_id, 'basic');
      if ($stored_credential) {
        $has_stored_credential = TRUE;
        $stored_username = (string) ($stored_credential['username'] ?? '');
      }
    }

    // ── Read cached Step 4 result ─────────────────────────────────────────
    $step4_cache = is_array($metadata_base['step4_cache'] ?? NULL) ? $metadata_base['step4_cache'] : [];
    $has_cached_step4 = !empty($step4_cache);

    $account_status      = (string) ($step4_cache['account_status'] ?? 'unknown');
    $account_evidence    = (string) ($step4_cache['account_evidence'] ?? '');
    $email_verified      = (bool)   ($step4_cache['email_verified'] ?? FALSE);
    $phone_verified      = (bool)   ($step4_cache['phone_verified'] ?? FALSE);
    $account_created_at  = (string) ($step4_cache['account_created_at'] ?? '');
    $verification_method = (string) ($step4_cache['verification_method'] ?? '');

    // If credentials are already stored, set status accordingly.
    if ($has_stored_credential && $account_status === 'unknown') {
      $account_status = 'verified';
      $account_evidence = 'Stored credentials found for ' . $company_name . '.';
    }

    // ── Check for POST actions ────────────────────────────────────────────
    $request = $this->requestStack->getCurrentRequest();
    $run_step4_requested = FALSE;
    $verification_result_data = [];
    if ($request->isMethod('POST')) {
      $token = (string) $request->request->get('csrf_token', '');
      if ($token !== '' && $this->csrfTokenGenerator->validate($token, 'job_hunter_step4_run_' . (int) $selected_job->id)) {
        $run_step4_requested = TRUE;
      }
      else {
        $this->messenger()->addError($this->t('Invalid request token. Refresh and try again.'));
      }
    }

    if ($run_step4_requested) {
      $now = date('Y-m-d H:i:s');
      $action = (string) $request->request->get('step4_action', '');
      $verification_result_data = [];

      // ── ACTION: Store new credentials ─────────────────────────────────
      if ($action === 'store_credentials') {
        $input_username = trim((string) $request->request->get('credential_username', ''));
        $input_password = trim((string) $request->request->get('credential_password', ''));

        if ($input_username === '' || $input_password === '') {
          $this->messenger()->addError($this->t('Username and password are both required.'));
        }
        elseif ($company_id <= 0) {
          $this->messenger()->addError($this->t('Cannot store credentials — no company linked to this job.'));
        }
        else {
          $result = $cred_service->storeCredential(
            $uid,
            $company_id,
            'basic',
            ['username' => $input_username, 'password' => $input_password],
            $auth_url
          );

          if (!empty($result['success'])) {
            $has_stored_credential = TRUE;
            $stored_username = $input_username;
            $account_status = 'verified';
            $email_verified = TRUE;
            $account_created_at = $now;
            $account_evidence = 'Credentials stored for ' . $company_name . ' (username: ' . $input_username . ') at ' . $now . '.';
            $this->messenger()->addStatus($this->t('Credentials securely stored. Account marked as ready.'));
          }
          else {
            $this->messenger()->addError($this->t('Failed to store credentials: @error', ['@error' => $result['error'] ?? 'Unknown error']));
          }
        }
      }

      // ── ACTION: Confirm existing account ──────────────────────────────
      elseif ($action === 'confirm_existing') {
        $account_status = 'verified';
        $email_verified = TRUE;
        $account_created_at = $now;
        $account_evidence = 'Existing account confirmed by user at ' . $now . '. Stored credentials present.';
        $this->messenger()->addStatus($this->t('Existing account confirmed and marked as ready.'));
      }

      // ── ACTION: Verify authentication via Playwright ──────────────────
      elseif ($action === 'verify_authentication') {
        /** @var \Drupal\job_hunter\Service\AccountVerificationService $verify_svc */
        $verify_svc = \Drupal::service('job_hunter.account_verification_service');
        $verify_result = $verify_svc->verify((int) $selected_job->id, $uid, ['timeout' => 90]);

        if (!empty($verify_result['verified'])) {
          $account_status = 'verified';
          $email_verified = TRUE;
          $account_created_at = $now;
          $verification_method = 'playwright_browser';
          $account_evidence = 'Browser verification confirmed: logged in as '
            . ($verify_result['verified_email'] ?: 'unknown')
            . ' at ' . ($verify_result['user_home_url'] ?: 'user home')
            . '. ' . ($verify_result['evidence'] ?: '') . ' [' . $now . ']';
          $this->messenger()->addStatus($this->t('Authentication verified! Logged in as @email.', [
            '@email' => $verify_result['verified_email'] ?: 'the expected user',
          ]));
        }
        elseif (!empty($verify_result['ok'])) {
          // Script ran successfully but couldn't verify identity.
          $account_status = 'pending_verification';
          $account_evidence = 'Browser ran but could not confirm identity. '
            . ($verify_result['error'] ?: $verify_result['evidence'] ?: 'No email match found.')
            . ' [' . $now . ']';
          $this->messenger()->addWarning($this->t('Browser connected but could not verify your identity. Check credentials and try again.'));
        }
        else {
          $account_evidence = 'Browser verification failed: '
            . ($verify_result['error'] ?: 'Unknown error') . ' [' . $now . ']';
          $this->messenger()->addError($this->t('Verification failed: @error', [
            '@error' => $verify_result['error'] ?: 'Unknown error',
          ]));
        }

        // Store the raw verification result for template display.
        $verification_result_data = $verify_result;
      }

      // Determine verification method from auth_type.
      if ($verification_method === '') {
        if (in_array($auth_type, ['email_password', 'email_only'], TRUE)) {
          $verification_method = 'email';
        }
        elseif (str_starts_with($auth_type, 'sso_')) {
          $verification_method = 'sso_provider';
        }
        elseif ($auth_type === 'registration_first') {
          $verification_method = 'email';
        }
        elseif ($auth_type === 'direct') {
          $verification_method = 'none';
        }
        else {
          $verification_method = 'manual';
        }
      }

      // Persist to metadata.
      $meta = $metadata_base;
      $meta['step4_cache'] = [
        'ran_at'              => $now,
        'account_status'      => $account_status,
        'account_evidence'    => $account_evidence,
        'email_verified'      => $email_verified,
        'phone_verified'      => $phone_verified,
        'account_created_at'  => $account_created_at,
        'verification_method' => $verification_method,
        'user_email'          => $user_email,
        'user_phone'          => $user_phone,
        'stored_username'     => $stored_username,
        'verification_result' => $verification_result_data,
      ];
      $meta['account_readiness_at'] = in_array($account_status, ['verified', 'not_required'], TRUE) ? $now : '';

      try {
        if ($existing_application) {
          $this->repository->updateApplication((int) $existing_application['id'], [
            'metadata' => json_encode($meta),
            'changed'  => $now,
          ]);
        }
        else {
          $this->repository->insertApplication([
            'uid'              => $uid,
            'job_id'           => (int) $selected_job->id,
            'submission_status'=> 'not_started',
            'submission_method'=> 'pending',
            'apply_url'        => $apply_url,
            'ats_platform'     => $ats_platform,
            'attempt_count'    => 0,
            'metadata'         => json_encode($meta),
            'created'          => $now,
            'changed'          => $now,
          ]);
        }
        $step4_cache = $meta['step4_cache'];
      }
      catch (\Throwable $e) {
        // Non-fatal.
      }

      $has_cached_step4 = TRUE;
    }

    // Prerequisite readiness checks for display.
    $prerequisites = [];
    $prerequisites[] = [
      'label' => 'User email address available',
      'met' => $user_email !== '',
      'value' => $user_email !== '' ? $user_email : 'Missing — update your profile.',
    ];
    $prerequisites[] = [
      'label' => 'User phone number available',
      'met' => $user_phone !== '',
      'value' => $user_phone !== '' ? $user_phone : 'Missing — update your profile.',
    ];
    $prerequisites[] = [
      'label' => 'Authentication path identified (Step 3)',
      'met' => !in_array($auth_type, ['unknown', 'captcha_blocked'], TRUE),
      'value' => $auth_type,
    ];
    $prerequisites[] = [
      'label' => 'ATS destination URL available',
      'met' => $auth_url !== '',
      'value' => $auth_url !== '' ? $auth_url : 'Not available — complete Step 2/3.',
    ];

    $account_ready = in_array($account_status, ['verified', 'not_required'], TRUE);

    // Default credential values for the "create new account" form.
    $default_username = $user_email !== '' ? $user_email : 'keith.aumiller';
    $default_password = 'Unsecure01!abc';

    $content = [
      '#theme'                   => 'application_submission_step4',
      '#job_id'                  => (int) $selected_job->id,
      '#job_title'               => $job_title,
      '#company_name'            => $company_name,
      '#company_id'              => $company_id,
      '#apply_url'               => $apply_url,
      '#auth_url'                => $auth_url,
      '#auth_type'               => $auth_type,
      '#ats_platform'            => $ats_platform,
      '#user_email'              => $user_email,
      '#user_phone'              => $user_phone,
      '#user_name'               => $user_name,
      '#prerequisites'           => $prerequisites,
      '#account_status'          => $account_status,
      '#account_evidence'        => $account_evidence,
      '#account_ready'           => $account_ready,
      '#email_verified'          => $email_verified,
      '#phone_verified'          => $phone_verified,
      '#verification_method'     => $verification_method,
      '#account_created_at'      => $account_created_at,
      '#has_stored_credential'   => $has_stored_credential,
      '#stored_username'         => $stored_username,
      '#default_username'        => $default_username,
      '#default_password'        => $default_password,
      '#step4_cache_exists'      => $has_cached_step4,
      '#step4_last_run_at'       => (string) ($step4_cache['ran_at'] ?? ''),
      '#step4_ran_this_request'  => $run_step4_requested,
      '#run_step4_csrf_token'    => $this->csrfTokenGenerator->get('job_hunter_step4_run_' . (int) $selected_job->id),
      '#verification_result'     => !empty($verification_result_data) ? $verification_result_data : (is_array($step4_cache['verification_result'] ?? NULL) ? $step4_cache['verification_result'] : []),
      '#return_url'              => '/jobhunter/application-submission/' . (int) $selected_job->id,
      '#cache'                   => [
        'contexts' => ['user', 'url.query_args'],
        'tags'     => ['job_hunter:jobs', 'job_hunter:applications'],
        'max-age'  => 0,
      ],
    ];

    return $this->wrapWithNavigation($content);
  }

  /**
   * Step 5: Submit Application (combined Confirm Job / Locate Apply / Submit).
   *
   * Merges former Steps 5-7 into a single page with three sections:
   *   A. Confirm the job still exists on the destination ATS.
   *   B. Locate the apply control / entry point.
   *   C. Submit the application.
   *
   * POST actions via step5_action:
   *   - confirm_job_exists:        Mark that the job is verified on-site.
   *   - upload_resume_continue:     Upload tailored resume to ATS and click Continue.
  *   - run_wd_wizard_auto:         Auto-progress remaining Workday steps (2-7).
   *   - run_wd_step:                Run Playwright automation for a Workday wizard step (2-7).
   *   - advance_wd_step:            Manually mark a Workday wizard step as complete.
   *   - submit_application:         Trigger ApplicationSubmissionService.
   *   - mark_manual_submission:     Record a manual submission.
   *
   * @param int $job_id
   *   The job requisition ID.
   *
   * @return array
   *   A render array.
   */
  public function applicationSubmissionSubmitApplication(int $job_id): array {
    $uid = (int) $this->currentUser()->id();
    if ($uid <= 0) {
      return ['#markup' => $this->t('You must be logged in to access this page.')];
    }

    $selected_job = $this->loadSelectedJobContext($uid, $job_id);
    if (!$selected_job) {
      $this->messenger()->addError($this->t('Job requisition not found for your account.'));
      return $this->wrapWithNavigation(['#markup' => '<p>' . $this->t('Unable to load this requisition.') . '</p>']);
    }

    $extracted = is_array($selected_job->extracted_data ?? NULL) ? $selected_job->extracted_data : [];
    $job_title = (string) ($extracted['position']['title'] ?? $selected_job->job_title ?? ('Job #' . (int) $selected_job->id));
    $company_name = (string) ($extracted['company']['name'] ?? $selected_job->company_name ?? 'Unknown');
    $company_id = (int) ($selected_job->company_id ?? 0);

    // ── Load application row + metadata ───────────────────────────────────
    $existing_application = $this->repository->findLatestApplicationByJobAndUser($uid, (int) $selected_job->id, ['id', 'apply_url', 'ats_platform', 'metadata', 'submission_status', 'confirmation_reference', 'confirmation_ref', 'attempt_count']);

    $apply_url        = (string) ($existing_application['apply_url'] ?? '');
    $ats_platform     = (string) ($existing_application['ats_platform'] ?? 'unknown');
    $submission_status = (string) ($existing_application['submission_status'] ?? 'not_started');
    $confirmation     = (string) ($existing_application['confirmation_reference'] ?? $existing_application['confirmation_ref'] ?? '');
    $attempt_count    = (int) ($existing_application['attempt_count'] ?? 0);

    // Derive last attempt details from the attempts table (if it exists).
    $last_outcome    = '';
    $last_error      = '';
    $last_attempt_at = '';
    if ($existing_application) {
      try {
        $last_attempt = $this->repository->getLastAttempt((int) $existing_application['id']);
        if ($last_attempt) {
          $last_outcome    = (string) ($last_attempt['outcome'] ?? '');
          $last_error      = (string) ($last_attempt['error_message'] ?? '');
          $last_attempt_at = (string) ($last_attempt['attempted_at'] ?? '');
        }
      }
      catch (\Throwable $e) {
        // Attempts table may not exist yet — non-fatal.
      }
    }

    $metadata_base = [];
    if (!empty($existing_application['metadata'])) {
      $decoded = json_decode((string) $existing_application['metadata'], TRUE);
      if (is_array($decoded)) {
        $metadata_base = $decoded;
      }
    }

    $auth_url  = (string) ($metadata_base['auth_url'] ?? $apply_url);
    $auth_type = (string) ($metadata_base['auth_type'] ?? 'unknown');

    // Re-detect ATS platform from auth URL if stored value is unhelpful.
    if (in_array($ats_platform, ['custom', 'unknown', ''], TRUE)) {
      $detected = $this->detectAtsPlatformFromUrl($auth_url);
      if ($detected !== 'custom') {
        $ats_platform = $detected;
        if ($existing_application) {
          $this->repository->updateApplication((int) $existing_application['id'], ['ats_platform' => $ats_platform]);
        }
      }
    }

    // ── Read cached Step 5 result ─────────────────────────────────────────
    $step5_cache = is_array($metadata_base['step5_cache'] ?? NULL) ? $metadata_base['step5_cache'] : [];
    $has_cached_step5 = !empty($step5_cache);

    $job_confirmed_on_site  = (bool) ($step5_cache['job_confirmed_on_site'] ?? FALSE);
    $apply_control_located  = (bool) ($step5_cache['apply_control_located'] ?? FALSE);
    $submission_attempted   = (bool) ($step5_cache['submission_attempted'] ?? FALSE);
    $submission_result_data = (array) ($step5_cache['submission_result'] ?? []);

    // Derive from application row data too.
    $submission_started   = in_array($submission_status, ['queued', 'pending', 'processing', 'submitted', 'confirmed', 'manual_required', 'failed', 'manual_completed', 'resume_uploaded'], TRUE);
    $submission_completed = in_array($submission_status, ['submitted', 'confirmed', 'manual_completed'], TRUE);

    // Job exists if we have title + company.
    if (!$job_confirmed_on_site && $job_title !== '' && $company_name !== '' && $company_name !== 'Unknown') {
      $job_confirmed_on_site = TRUE;
    }

    // Apply control located if we have a resolved URL + auth path.
    $auth_path_identified = !in_array($auth_type, ['unknown', 'captcha_blocked'], TRUE);
    if (!$apply_control_located && $apply_url !== '' && $auth_path_identified) {
      $apply_control_located = TRUE;
    }
    if (!$apply_control_located && ($submission_started || $attempt_count > 0)) {
      $apply_control_located = TRUE;
    }

    // ── Check for stored credentials ──────────────────────────────────────
    /** @var \Drupal\job_hunter\Service\CredentialManagementService $cred_service */
    $cred_service = \Drupal::service('job_hunter.credential_management_service');
    $has_stored_credential = FALSE;
    if ($company_id > 0) {
      $stored_credential = $cred_service->retrieveCredential($uid, $company_id, 'basic');
      $has_stored_credential = !empty($stored_credential);
    }

    // ── Upstream gate checks ──────────────────────────────────────────────
    $step4_cache = is_array($metadata_base['step4_cache'] ?? NULL) ? $metadata_base['step4_cache'] : [];
    $account_readiness_at = (string) ($metadata_base['account_readiness_at'] ?? '');
    $account_ready = $account_readiness_at !== '' || $has_stored_credential;

    $verification_result = is_array($step4_cache['verification_result'] ?? NULL) ? $step4_cache['verification_result'] : [];
    $browser_verified = !empty($verification_result['verified']);

    $prerequisites_met = $apply_url !== '' && $auth_path_identified && $account_ready;

    // ── POST Actions ──────────────────────────────────────────────────────
    $request = $this->requestStack->getCurrentRequest();
    $run_step5_requested = FALSE;
    if ($request->isMethod('POST')) {
      $token = (string) $request->request->get('csrf_token', '');
      if ($token !== '' && $this->csrfTokenGenerator->validate($token, 'job_hunter_step5_run_' . (int) $selected_job->id)) {
        $run_step5_requested = TRUE;
      }
      else {
        $this->messenger()->addError($this->t('Invalid request token. Refresh and try again.'));
      }
    }

    if ($run_step5_requested) {
      $now = date('Y-m-d H:i:s');
      $action = (string) $request->request->get('step5_action', '');
      $wizard_auto_completed_steps = [];
      $wizard_auto_last_url = '';

      // ── ACTION: Confirm job exists ──────────────────────────────────────
      if ($action === 'confirm_job_exists') {
        $job_confirmed_on_site = TRUE;
        $this->messenger()->addStatus($this->t('Job confirmed as existing on the destination site.'));
      }

      // ── ACTION: Submit application ──────────────────────────────────────
      elseif ($action === 'submit_application') {
        if (!$prerequisites_met) {
          $this->messenger()->addError($this->t('Prerequisites not met. Complete Steps 2-4 first.'));
        }
        else {
          /** @var \Drupal\job_hunter\Service\ApplicationSubmissionService $submission_svc */
          $submission_svc = \Drupal::service('job_hunter.application_submission_service');
          $submit_result = $submission_svc->submitApplication($uid, (int) $selected_job->id, TRUE);

          $submission_attempted = TRUE;
          $submission_result_data = $submit_result;

          if (!empty($submit_result['success'])) {
            $submission_started = TRUE;
            $apply_control_located = TRUE;
            $submission_status = (string) ($submit_result['status'] ?? 'queued');
            $this->messenger()->addStatus($this->t('Application submitted successfully. Status: @status', [
              '@status' => $submit_result['status'] ?? 'queued',
            ]));
          }
          else {
            $this->messenger()->addError($this->t('Submission failed: @error', [
              '@error' => $submit_result['message'] ?? 'Unknown error',
            ]));
          }
        }
      }

      // ── ACTION: Mark manual submission ──────────────────────────────────
      elseif ($action === 'mark_manual_submission') {
        $manual_confirmation = trim((string) $request->request->get('manual_confirmation', ''));
        $submission_attempted = TRUE;
        $submission_started = TRUE;
        $submission_completed = TRUE;
        $submission_result_data = [
          'success' => TRUE,
          'status' => 'manual_completed',
          'message' => 'Manually marked as submitted.',
          'manual_confirmation' => $manual_confirmation,
        ];

        // Update the application row directly.
        if ($existing_application) {
          $this->repository->updateApplication((int) $existing_application['id'], [
            'submission_status' => 'manual_completed',
            'confirmation_ref' => $manual_confirmation !== '' ? $manual_confirmation : 'Manual submission at ' . $now,
            'changed' => $now,
          ]);
        }

        $this->messenger()->addStatus($this->t('Application marked as manually submitted.'));
      }

      // ── ACTION: Upload resume and click Continue ─────────────────────
      elseif ($action === 'upload_resume_continue') {
        if (!$prerequisites_met) {
          $this->messenger()->addError($this->t('Prerequisites not met. Complete Steps 2-4 first.'));
        }
        else {
          /** @var \Drupal\job_hunter\Service\ResumeUploadService $resume_upload_svc */
          $resume_upload_svc = \Drupal::service('job_hunter.resume_upload_service');
          $upload_result = $resume_upload_svc->uploadResume((int) $selected_job->id, $uid);

          $submission_result_data = $upload_result;
          $job_confirmed_on_site = TRUE;
          $apply_control_located = TRUE;

          if (!empty($upload_result['ok'])) {
            $submission_attempted = TRUE;
            $submission_started = TRUE;
            $this->messenger()->addStatus($this->t('Resume uploaded and Continue clicked successfully. Auth verified: @email. File: @file', [
              '@email' => $upload_result['verified_email'] ?? 'unknown',
              '@file' => $upload_result['upload_filename'] ?? 'unknown',
            ]));

            // Update application row.
            if ($existing_application) {
              $this->repository->updateApplication((int) $existing_application['id'], [
                'submission_status' => 'resume_uploaded',
                'changed' => $now,
              ]);
              $submission_status = 'resume_uploaded';
            }

            // After Step 1 succeeds, auto-progress Workday steps 2-7.
            /** @var \Drupal\job_hunter\Service\WorkdayWizardService $wz_service */
            $wz_service = \Drupal::service('job_hunter.workday_wizard_service');
            $current_wizard_url = (string) ($upload_result['post_continue_url'] ?? '');
            $wizard_session_result = $wz_service->advanceWizardAutoSingleSession((int) $selected_job->id, $uid, 'my_information', [
              'apply_url' => $current_wizard_url,
              'timeout' => 320,
            ]);

            $submission_result_data = $wizard_session_result;
            $step_results = (array) ($wizard_session_result['step_results'] ?? []);
            $ordered_wd_steps = ['my_information', 'my_experience', 'application_questions', 'voluntary_disclosures', 'self_identify', 'review_submit'];
            $auto_success_count = 0;
            foreach ($ordered_wd_steps as $auto_step_key) {
              $step_result = (array) ($step_results[$auto_step_key] ?? []);
              if ((string) ($step_result['status'] ?? '') === 'pass') {
                $wizard_auto_completed_steps[$auto_step_key] = [
                  'status' => 'pass',
                  'completed_at' => $now,
                  'result' => $step_result,
                ];
                $auto_success_count++;
              }
              elseif (!empty($step_result)) {
                $wizard_auto_completed_steps[$auto_step_key] = [
                  'status' => 'failed',
                  'completed_at' => $now,
                  'result' => $step_result,
                ];
                break;
              }
            }

            $next_url = trim((string) ($wizard_session_result['post_continue_url'] ?? ''));
            if ($next_url !== '') {
              $wizard_auto_last_url = $next_url;
            }

            $review_status = (string) (($step_results['review_submit']['status'] ?? ''));
            if ($review_status === 'pass') {
              $submission_attempted = TRUE;
              $submission_started = TRUE;
              $submission_completed = TRUE;
              if ($existing_application) {
                $this->repository->updateApplication((int) $existing_application['id'], ['submission_status' => 'submitted', 'changed' => $now]);
                $submission_status = 'submitted';
              }
            }
            elseif ($auto_success_count > 0 || !empty($wizard_session_result['error'])) {
              $this->messenger()->addError($this->t('Wizard auto-progress failed in single-session mode: @error', [
                '@error' => (string) ($wizard_session_result['error'] ?? 'Unknown error'),
              ]));
            }

            if ($auto_success_count > 0) {
              $this->messenger()->addStatus($this->t('Wizard auto-progress completed @count step(s) after Autofill (single session).', [
                '@count' => $auto_success_count,
              ]));
            }
          }
          else {
            $this->messenger()->addError($this->t('Resume upload failed: @error', [
              '@error' => $upload_result['error'] ?? 'Unknown error',
            ]));
          }
        }
      }

      // ── ACTION: Auto-progress remaining Workday wizard steps ──────────
      elseif ($action === 'run_wd_wizard_auto') {
        if (!$prerequisites_met) {
          $this->messenger()->addError($this->t('Prerequisites not met. Complete Steps 2-4 first.'));
        }
        else {
          $wd_steps_cached = is_array($step5_cache['wd_flow_steps'] ?? NULL) ? $step5_cache['wd_flow_steps'] : [];
          $ordered_steps = ['autofill_resume', 'my_information', 'my_experience', 'application_questions', 'voluntary_disclosures', 'self_identify', 'review_submit'];

          // Determine first incomplete step.
          $first_incomplete_index = -1;
          foreach ($ordered_steps as $idx => $k) {
            $status = (string) (($wd_steps_cached[$k]['status'] ?? 'not_started'));
            if ($status !== 'pass') {
              $first_incomplete_index = $idx;
              break;
            }
          }

          if ($first_incomplete_index === -1) {
            $this->messenger()->addStatus($this->t('All Workday wizard steps are already complete.'));
          }
          elseif ($first_incomplete_index === 0) {
            $this->messenger()->addError($this->t('Run Step 1 (Autofill with Resume) first.'));
          }
          else {
            /** @var \Drupal\job_hunter\Service\WorkdayWizardService $wz_service */
            $wz_service = \Drupal::service('job_hunter.workday_wizard_service');
            $current_wizard_url = (string) ($step5_cache['wd_last_url'] ?? $step5_cache['resume_upload_result']['post_continue_url'] ?? '');
            // Even if cache says later steps passed, Workday can reopen earlier
            // pages in a new session. Start from My Information to keep flow stable.
            $start_step_key = 'my_information';

            $wizard_session_result = $wz_service->advanceWizardAutoSingleSession((int) $selected_job->id, $uid, $start_step_key, [
              'apply_url' => $current_wizard_url,
              'timeout' => 320,
            ]);

            $submission_result_data = $wizard_session_result;
            $step_results = (array) ($wizard_session_result['step_results'] ?? []);
            $ordered_wd_steps = ['my_information', 'my_experience', 'application_questions', 'voluntary_disclosures', 'self_identify', 'review_submit'];
            $auto_success_count = 0;
            foreach ($ordered_wd_steps as $step_key) {
              $step_result = (array) ($step_results[$step_key] ?? []);
              if ((string) ($step_result['status'] ?? '') === 'pass') {
                $wizard_auto_completed_steps[$step_key] = [
                  'status' => 'pass',
                  'completed_at' => $now,
                  'result' => $step_result,
                ];
                $submission_started = TRUE;
                $auto_success_count++;
              }
              elseif (!empty($step_result)) {
                $wizard_auto_completed_steps[$step_key] = [
                  'status' => 'failed',
                  'completed_at' => $now,
                  'result' => $step_result,
                ];
                break;
              }
            }

            $next_url = trim((string) ($wizard_session_result['post_continue_url'] ?? ''));
            if ($next_url !== '') {
              $wizard_auto_last_url = $next_url;
            }

            if ((string) (($step_results['review_submit']['status'] ?? '')) === 'pass') {
              $submission_attempted = TRUE;
              $submission_completed = TRUE;
              if ($existing_application) {
                $this->repository->updateApplication((int) $existing_application['id'], ['submission_status' => 'submitted', 'changed' => $now]);
                $submission_status = 'submitted';
              }
            }
            else {
              $this->messenger()->addError($this->t('Wizard auto-progress failed in single-session mode: @error', [
                '@error' => (string) ($wizard_session_result['error'] ?? 'Unknown error'),
              ]));
            }

            if ($auto_success_count > 0) {
              $this->messenger()->addStatus($this->t('Wizard auto-progress completed @count step(s) in single session.', [
                '@count' => $auto_success_count,
              ]));
            }
          }
        }
      }

      // ── ACTION: Run Workday wizard step automation ──────────────────
      elseif ($action === 'run_wd_step') {
        $wd_step_key = (string) $request->request->get('wd_step_key', '');
        $wd_automatable_steps = ['my_information', 'my_experience', 'application_questions', 'voluntary_disclosures', 'self_identify', 'review_submit'];

        if (!in_array($wd_step_key, $wd_automatable_steps, TRUE)) {
          $this->messenger()->addError($this->t('Invalid step key for automation.'));
        }
        elseif (!$prerequisites_met) {
          $this->messenger()->addError($this->t('Prerequisites not met. Complete Steps 2-4 first.'));
        }
        else {
          /** @var \Drupal\job_hunter\Service\WorkdayWizardService $wz_service */
          $wz_service = \Drupal::service('job_hunter.workday_wizard_service');
          $wz_result = $wz_service->advanceStep((int) $selected_job->id, $uid, $wd_step_key, [
            'timeout' => ($wd_step_key === 'review_submit') ? 220 : 120,
          ]);

          $submission_result_data = $wz_result;
          $job_confirmed_on_site = TRUE;
          $apply_control_located = TRUE;

          if (!empty($wz_result['ok'])) {
            $submission_started = TRUE;
            $next_url = trim((string) ($wz_result['post_continue_url'] ?? ''));
            if ($next_url !== '') {
              $wizard_auto_last_url = $next_url;
            }
            $this->messenger()->addStatus($this->t('Workday step "@step" automated successfully. Page: @page', [
              '@step' => $wd_step_key,
              '@page' => $wz_result['detected_page'] ?? 'unknown',
            ]));

            // If review_submit completed, mark the application as submitted.
            if ($wd_step_key === 'review_submit') {
              $submission_attempted = TRUE;
              $submission_completed = TRUE;
              if ($existing_application) {
                $this->repository->updateApplication((int) $existing_application['id'], ['submission_status' => 'submitted', 'changed' => $now]);
                $submission_status = 'submitted';
              }
            }
          }
          else {
            $needs_manual = !empty($wz_result['needs_manual_review']);
            if ($needs_manual) {
              $this->messenger()->addWarning($this->t('Workday step "@step" needs manual review. Fields skipped: @fields', [
                '@step' => $wd_step_key,
                '@fields' => implode(', ', $wz_result['fields_skipped'] ?? []),
              ]));
            }
            else {
              $this->messenger()->addError($this->t('Workday step "@step" automation failed: @error', [
                '@step' => $wd_step_key,
                '@error' => $wz_result['error'] ?? 'Unknown error',
              ]));
            }
          }
        }
      }

      // ── ACTION: Manually advance a Workday wizard step ──────────────
      elseif ($action === 'advance_wd_step') {
        $wd_step_key = (string) $request->request->get('wd_step_key', '');
        $wd_step_labels = [
          'my_information'        => 'My Information',
          'my_experience'         => 'My Experience',
          'application_questions' => 'Application Questions',
          'voluntary_disclosures' => 'Voluntary Disclosures',
          'self_identify'         => 'Self-Identify',
          'review_submit'         => 'Review & Submit',
        ];
        if (isset($wd_step_labels[$wd_step_key])) {
          $this->messenger()->addStatus($this->t('Workday step "@step" marked as complete.', [
            '@step' => $wd_step_labels[$wd_step_key],
          ]));
          // If this is review_submit, mark the application as submitted.
          if ($wd_step_key === 'review_submit') {
            $submission_attempted = TRUE;
            $submission_started = TRUE;
            $submission_completed = TRUE;
            $submission_result_data = [
              'success' => TRUE,
              'status' => 'submitted',
              'message' => 'Application submitted via Workday wizard flow.',
            ];
            if ($existing_application) {
              $this->repository->updateApplication((int) $existing_application['id'], [
                'submission_status' => 'submitted',
                'changed' => $now,
              ]);
              $submission_status = 'submitted';
            }
          }
        }
        else {
          $this->messenger()->addError($this->t('Unknown Workday step key.'));
        }
      }

      // Persist Step 5 cache.
      $meta = $metadata_base;
      // Update Workday flow step statuses based on action.
      $wd_steps_update = is_array($step5_cache['wd_flow_steps'] ?? NULL) ? $step5_cache['wd_flow_steps'] : [];
      if ($action === 'upload_resume_continue' && !empty($submission_result_data['ok'])) {
        $wd_steps_update['autofill_resume'] = [
          'status' => 'pass',
          'completed_at' => $now,
          'result' => $submission_result_data,
        ];
      }
      elseif ($action === 'run_wd_step' && !empty($submission_result_data['ok'])) {
        $wd_step_key = (string) $request->request->get('wd_step_key', '');
        if ($wd_step_key !== '') {
          $wd_steps_update[$wd_step_key] = [
            'status' => 'pass',
            'completed_at' => $now,
            'result' => $submission_result_data,
          ];
        }
      }
      elseif ($action === 'advance_wd_step') {
        $wd_step_key = (string) $request->request->get('wd_step_key', '');
        if ($wd_step_key !== '' && in_array($wd_step_key, ['my_information', 'my_experience', 'application_questions', 'voluntary_disclosures', 'self_identify', 'review_submit'], TRUE)) {
          $wd_steps_update[$wd_step_key] = [
            'status' => 'pass',
            'completed_at' => $now,
          ];
        }
      }
      elseif ($action === 'mark_manual_submission') {
        // Mark all WD steps as pass when manually submitted.
        foreach (['autofill_resume', 'my_information', 'my_experience', 'application_questions', 'voluntary_disclosures', 'self_identify', 'review_submit'] as $k) {
          if (empty($wd_steps_update[$k])) {
            $wd_steps_update[$k] = ['status' => 'pass', 'completed_at' => $now];
          }
        }
      }

      if (!empty($wizard_auto_completed_steps)) {
        foreach ($wizard_auto_completed_steps as $k => $v) {
          $wd_steps_update[$k] = $v;
        }
      }

      $meta['step5_cache'] = [
        'ran_at'                => $now,
        'job_confirmed_on_site' => $job_confirmed_on_site,
        'apply_control_located' => $apply_control_located,
        'submission_attempted'  => $submission_attempted,
        'submission_result'     => $submission_result_data,
        'resume_upload_result'  => ($action === 'upload_resume_continue') ? $submission_result_data : ($step5_cache['resume_upload_result'] ?? []),
        'wd_last_url'           => $wizard_auto_last_url !== ''
          ? $wizard_auto_last_url
          : (
            trim((string) ($submission_result_data['post_continue_url'] ?? '')) !== ''
            ? trim((string) ($submission_result_data['post_continue_url'] ?? ''))
            : (
              trim((string) ($step5_cache['wd_last_url'] ?? '')) !== ''
              ? trim((string) ($step5_cache['wd_last_url'] ?? ''))
              : trim((string) ($step5_cache['resume_upload_result']['post_continue_url'] ?? ''))
            )
          ),
        'wd_flow_steps'         => $wd_steps_update,
      ];

      try {
        if ($existing_application) {
          $this->repository->updateApplication((int) $existing_application['id'], [
            'metadata' => json_encode($meta),
            'changed'  => $now,
          ]);
        }
        $step5_cache = $meta['step5_cache'];
      }
      catch (\Throwable $e) {
        // Non-fatal.
      }

      $has_cached_step5 = TRUE;
    }

    // ── Section readiness ─────────────────────────────────────────────────
    $section_a_status = $job_confirmed_on_site ? 'pass' : 'incomplete';
    $section_b_status = $apply_control_located ? 'pass' : 'incomplete';
    $section_c_status = $submission_completed ? 'pass' : ($submission_started ? 'in_progress' : 'incomplete');

    $all_pass = $section_a_status === 'pass' && $section_b_status === 'pass' && $section_c_status === 'pass';

    // ── Resolve resume availability ───────────────────────────────────────
    $has_tailored_resume = FALSE;
    $resume_pdf_basename = '';
    try {
      $resume_uri = $this->repository->getResumeUri($uid, (int) $selected_job->id);
      if ($resume_uri) {
        $real_path = \Drupal::service('file_system')->realpath($resume_uri);
        if ($real_path && file_exists($real_path)) {
          $has_tailored_resume = TRUE;
          $resume_pdf_basename = basename($real_path);
        }
      }
    }
    catch (\Throwable $e) {
      // Non-fatal.
    }

    // Resume upload result (from cache or this request).
    $resume_upload_result = (array) ($step5_cache['resume_upload_result'] ?? []);
    $resume_uploaded = !empty($resume_upload_result['ok']);

    // ── Build Workday flow steps tracker ───────────────────────────────────
    $wd_steps_cached = is_array($step5_cache['wd_flow_steps'] ?? NULL) ? $step5_cache['wd_flow_steps'] : [];
    $wd_flow_definition = [
      ['key' => 'autofill_resume',       'label' => 'Autofill with Resume',    'number' => 1],
      ['key' => 'my_information',        'label' => 'My Information',          'number' => 2],
      ['key' => 'my_experience',         'label' => 'My Experience',           'number' => 3],
      ['key' => 'application_questions', 'label' => 'Application Questions',   'number' => 4],
      ['key' => 'voluntary_disclosures', 'label' => 'Voluntary Disclosures',   'number' => 5],
      ['key' => 'self_identify',         'label' => 'Self-Identify',           'number' => 6],
      ['key' => 'review_submit',         'label' => 'Review & Submit',         'number' => 7],
    ];

    $wd_flow_steps = [];
    $wd_current_step = 1;
    $wd_all_complete = TRUE;
    foreach ($wd_flow_definition as $step_def) {
      $step_data = $wd_steps_cached[$step_def['key']] ?? [];
      $step_status = (string) ($step_data['status'] ?? 'not_started');
      if ($step_status !== 'pass') {
        $wd_all_complete = FALSE;
      }
      $wd_flow_steps[] = [
        'key'          => $step_def['key'],
        'label'        => $step_def['label'],
        'number'       => $step_def['number'],
        'status'       => $step_status,
        'completed_at' => (string) ($step_data['completed_at'] ?? ''),
      ];
    }
    // Determine current active step (first non-pass).
    foreach ($wd_flow_steps as $s) {
      if ($s['status'] !== 'pass') {
        $wd_current_step = $s['number'];
        break;
      }
    }
    if ($wd_all_complete) {
      $wd_current_step = 8; // All done.
    }

    // Refine section C status based on WD flow step progress.
    $wd_completed_count = 0;
    foreach ($wd_flow_steps as $s) {
      if ($s['status'] === 'pass') {
        $wd_completed_count++;
      }
    }
    if ($wd_all_complete && !$submission_completed) {
      // All WD steps done but DB not yet updated (edge case) — mark pass.
      $section_c_status = 'pass';
    }
    elseif ($wd_completed_count > 0 && $section_c_status === 'incomplete') {
      $section_c_status = 'in_progress';
    }
    // Re-derive all_pass after potential section C upgrade.
    $all_pass = $section_a_status === 'pass' && $section_b_status === 'pass' && $section_c_status === 'pass';

    $content = [
      '#theme'                   => 'application_submission_step5',
      '#job_id'                  => (int) $selected_job->id,
      '#job_title'               => $job_title,
      '#company_name'            => $company_name,
      '#company_id'              => $company_id,
      '#apply_url'               => $apply_url,
      '#auth_url'                => $auth_url,
      '#auth_type'               => $auth_type,
      '#ats_platform'            => $ats_platform,
      '#account_ready'           => $account_ready,
      '#browser_verified'        => $browser_verified,
      '#has_stored_credential'   => $has_stored_credential,
      '#prerequisites_met'       => $prerequisites_met,
      '#job_confirmed_on_site'   => $job_confirmed_on_site,
      '#apply_control_located'   => $apply_control_located,
      '#submission_attempted'    => $submission_attempted,
      '#submission_started'      => $submission_started,
      '#submission_completed'    => $submission_completed,
      '#submission_status'       => $submission_status,
      '#submission_result'       => $submission_result_data,
      '#confirmation'            => $confirmation,
      '#attempt_count'           => $attempt_count,
      '#last_outcome'            => $last_outcome,
      '#last_error'              => $last_error,
      '#last_attempt_at'         => $last_attempt_at,
      '#section_a_status'        => $section_a_status,
      '#section_b_status'        => $section_b_status,
      '#section_c_status'        => $section_c_status,
      '#all_pass'                => $all_pass,
      '#step5_cache_exists'      => $has_cached_step5,
      '#step5_last_run_at'       => (string) ($step5_cache['ran_at'] ?? ''),
      '#step5_ran_this_request'  => $run_step5_requested,
      '#run_step5_csrf_token'    => $this->csrfTokenGenerator->get('job_hunter_step5_run_' . (int) $selected_job->id),
      '#return_url'              => '/jobhunter/application-submission/' . (int) $selected_job->id,
      '#has_tailored_resume'     => $has_tailored_resume,
      '#resume_pdf_basename'     => $resume_pdf_basename,
      '#resume_upload_result'    => $resume_upload_result,
      '#resume_uploaded'         => $resume_uploaded,
      '#wd_flow_steps'           => $wd_flow_steps,
      '#wd_current_step'         => $wd_current_step,
      '#wd_all_complete'         => $wd_all_complete,
      '#cache'                   => [
        'contexts' => ['user', 'url.query_args'],
        'tags'     => ['job_hunter:jobs', 'job_hunter:applications'],
        'max-age'  => 0,
      ],
    ];

    return $this->wrapWithNavigation($content);
  }

  /**
   * Securely streams Step 5 screenshot files for the authenticated owner.
   */
  public function applicationSubmissionScreenshot(int $job_id, string $filename): BinaryFileResponse {
    $uid = (int) $this->currentUser()->id();
    if ($uid <= 0) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $application = $this->repository->findLatestApplicationByJobAndUser($uid, $job_id, ['metadata']);

    if (!$application) {
      throw new NotFoundHttpException('Application not found.');
    }

    $metadata = [];
    if (!empty($application['metadata'])) {
      $decoded = json_decode((string) $application['metadata'], TRUE);
      if (is_array($decoded)) {
        $metadata = $decoded;
      }
    }

    $screenshots = (array) ($metadata['step5_cache']['resume_upload_result']['screenshots'] ?? []);
    $allowed_basenames = [];
    foreach ($screenshots as $path) {
      $base = basename((string) $path);
      if ($base !== '') {
        $allowed_basenames[$base] = TRUE;
      }
    }

    if (empty($allowed_basenames[$filename])) {
      throw new AccessDeniedHttpException('Screenshot not authorized for this job.');
    }

    $screenshots_dir = \Drupal::service('file_system')->realpath('private://job_hunter/screenshots');
    if (!$screenshots_dir || !is_dir($screenshots_dir)) {
      throw new AccessDeniedHttpException('Screenshot directory unavailable.');
    }

    $full_path = realpath($screenshots_dir . DIRECTORY_SEPARATOR . $filename);
    $dir_real = realpath($screenshots_dir);
    if (!$full_path || !$dir_real || !is_file($full_path) || strpos($full_path, $dir_real . DIRECTORY_SEPARATOR) !== 0) {
      throw new AccessDeniedHttpException('Screenshot file not found.');
    }

    $mime = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
      $detected = @mime_content_type($full_path);
      if (is_string($detected) && $detected !== '') {
        $mime = $detected;
      }
    }

    $response = new BinaryFileResponse($full_path);
    $response->headers->set('Content-Type', $mime);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($full_path));
    $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate');
    return $response;
  }

  /**
   * Generic stub page for application submission steps.
   */
  public function applicationSubmissionStepStub(int $job_id, int $step): array|RedirectResponse {
    $uid = (int) $this->currentUser()->id();
    if ($uid <= 0) {
      return [
        '#markup' => $this->t('You must be logged in to access this page.'),
      ];
    }

    if ($step === 2) {
      return new RedirectResponse(Url::fromRoute('job_hunter.application_submission_step2', ['job_id' => $job_id])->toString());
    }

    if ($step === 3) {
      return new RedirectResponse(Url::fromRoute('job_hunter.application_submission_step3', ['job_id' => $job_id])->toString());
    }

    if ($step === 4) {
      return new RedirectResponse(Url::fromRoute('job_hunter.application_submission_step4', ['job_id' => $job_id])->toString());
    }

    // Steps 5, 6, 7 are now combined into the single "Submit Application" page.
    if (in_array($step, [5, 6, 7], TRUE)) {
      return new RedirectResponse(Url::fromRoute('job_hunter.application_submission_step5', ['job_id' => $job_id])->toString());
    }

    $step_map = [
      1 => [
        'title' => 'Step 1: Pre-requirements',
        'description' => 'Validate tailored resume + PDF readiness and profile prerequisites for this requisition.',
      ],
      3 => [
        'title' => 'Step 3: Identify Authentication Path',
        'description' => 'Capture and verify the expected login/authentication path for the destination site.',
      ],
      4 => [
        'title' => 'Step 4: Verify Account Readiness',
        'description' => 'Confirm this user can authenticate and has required credentials/readiness for this company flow.',
      ],
      5 => [
        'title' => 'Step 5: Submit Application',
        'description' => 'Confirm the job exists, locate the apply control, and submit the application.',
      ],
      6 => [
        'title' => 'Step 6: Capture Confirmation & Evidence',
        'description' => 'Persist confirmation references and attempt evidence for auditability.',
      ],
    ];

    if (!isset($step_map[$step])) {
      $this->messenger()->addError($this->t('Unknown process step.'));
      return $this->wrapWithNavigation([
        '#markup' => '<p>' . $this->t('Step not found.') . '</p>',
      ]);
    }

    $selected_job = $this->loadSelectedJobContext($uid, $job_id);
    if (!$selected_job) {
      $this->messenger()->addError($this->t('Job requisition not found for your account.'));
      return $this->wrapWithNavigation([
        '#markup' => '<p>' . $this->t('Unable to load this requisition.') . '</p>',
      ]);
    }

    $extracted = is_array($selected_job->extracted_data ?? NULL) ? $selected_job->extracted_data : [];
    $job_title = (string) ($extracted['position']['title'] ?? $selected_job->job_title ?? ('Job #' . (int) $selected_job->id));
    $company_name = (string) ($extracted['company']['name'] ?? $selected_job->company_name ?? 'Unknown');

    $content = [
      '#theme' => 'application_submission_step_stub',
      '#job_id' => (int) $selected_job->id,
      '#job_title' => $job_title,
      '#company_name' => $company_name,
      '#step' => $step,
      '#step_title' => $step_map[$step]['title'],
      '#step_description' => $step_map[$step]['description'],
      '#return_url' => '/jobhunter/application-submission/' . (int) $selected_job->id,
      '#cache' => [
        'contexts' => ['user', 'url.query_args'],
        'tags' => ['job_hunter:jobs', 'job_hunter:applications'],
        'max-age' => 0,
      ],
    ];

    return $this->wrapWithNavigation($content);
  }

  /**
   * Gets summary counts for a user's application submissions.
   */
  private function getApplicationSubmissionSummary(int $uid, ?int $job_id = NULL): array {
    return $this->repository->getApplicationSubmissionSummary($uid, $job_id);
  }

  /**
   * Gets recent applications with optional fields when available.
   */
  private function getRecentApplicationSubmissions(int $uid, int $limit = 25, ?int $job_id = NULL): array {
    return $this->repository->getRecentApplicationSubmissions($uid, $limit, $job_id);
  }

  /**
   * Get latest attempt rows keyed by application_id.
   */
  private function getLatestAttemptsByApplicationIds(array $application_ids): array {
    return $this->repository->getLatestAttemptsByApplicationIds($application_ids);
  }

  /**
   * Build a full end-to-end process flow view for a selected job.
   */
  private function buildJobJourneyFlow(?object $selected_job, string $workflow_status, ?array $selected_application, ?array $selected_attempt, bool $has_profile): array {
    if (!$selected_job) {
      return [
        [
          'index' => 1,
          'label' => 'Step Pre-requirements',
          'status' => 'current',
          'gate_met' => FALSE,
          'detail' => 'Job context not loaded yet; pre-requirements cannot be verified.',
          'requirements' => [
            [
              'label' => 'Tailored resume PDF is generated and ready for submission',
              'met' => FALSE,
              'evidence' => 'No selected job context available for verification.',
            ],
          ],
        ],
        [
          'index' => 2,
          'label' => 'Resolve redirect chain',
          'status' => 'blocked',
          'gate_met' => FALSE,
          'detail' => 'Cannot evaluate redirect chain until selected job context is available.',
          'requirements' => [
            [
              'label' => 'Redirect chain is fully resolved',
              'met' => FALSE,
              'evidence' => 'No selected job context available for verification.',
            ],
          ],
        ],
        [
          'index' => 3,
          'label' => 'Identify authentication path',
          'status' => 'blocked',
          'gate_met' => FALSE,
          'detail' => 'Cannot evaluate authentication path until selected job context is available.',
          'requirements' => [
            [
              'label' => 'Authentication path is identified',
              'met' => FALSE,
              'evidence' => 'No selected job context available for verification.',
            ],
          ],
        ],
        [
          'index' => 4,
          'label' => 'Verify account readiness',
          'status' => 'blocked',
          'gate_met' => FALSE,
          'detail' => 'Cannot evaluate account readiness until selected job context is available.',
          'requirements' => [
            [
              'label' => 'User can authenticate and account readiness is confirmed',
              'met' => FALSE,
              'evidence' => 'No selected job context available for verification.',
            ],
          ],
        ],
        [
          'index' => 5,
          'label' => 'Submit application',
          'status' => 'blocked',
          'gate_met' => FALSE,
          'detail' => 'Cannot evaluate submission until selected job context is available.',
          'requirements' => [
            [
              'label' => 'Job confirmed, apply control located, and submission completed',
              'met' => FALSE,
              'evidence' => 'No selected job context available for verification.',
            ],
          ],
        ],
        [
          'index' => 6,
          'label' => 'Capture confirmation and evidence',
          'status' => 'blocked',
          'gate_met' => FALSE,
          'detail' => 'Cannot evaluate evidence stage until selected job context is available.',
          'requirements' => [
            [
              'label' => 'Confirmation and attempt evidence are captured',
              'met' => FALSE,
              'evidence' => 'No selected job context available for verification.',
            ],
          ],
        ],
      ];
    }

    $submission_status = (string) ($selected_application['submission_status'] ?? '');
    $ats_platform = (string) ($selected_application['ats_platform'] ?? '');
    $apply_url = (string) ($selected_application['apply_url'] ?? '');
    $career_url = $apply_url !== '' ? $apply_url : (string) ($selected_job->job_url ?? '');
    $career_page_identified = $career_url !== '';
    $resolution_steps_count = (int) ($selected_application['resolution_steps_count'] ?? 0);
    $resolution_confidence = (string) ($selected_application['resolution_confidence'] ?? '');
    $confirmation = (string) ($selected_application['confirmation'] ?? '');
    $attempt_count = (int) ($selected_application['attempt_count'] ?? 0);
    $last_attempt_outcome = (string) ($selected_application['last_attempt_outcome'] ?? '');
    $last_attempt_error = (string) ($selected_application['last_attempt_error'] ?? '');
    $last_attempt_at = (string) ($selected_application['last_attempt_at'] ?? '');

    $tailoring_completed = in_array($workflow_status, ['approval_pending', 'application_pending', 'pending_response', 'closed'], TRUE);
    $pdf_ready = (int) ($selected_job->pdf_generated ?? 0) > 0 || (string) ($selected_job->pdf_path ?? '') !== '';
    $has_resolved_url = $apply_url !== '';
    $has_ats_detection = $ats_platform !== '';
    $has_resolution_trace = $resolution_steps_count > 0;
    $submission_started = in_array($submission_status, ['queued', 'pending', 'processing', 'submitted', 'confirmed', 'manual_required', 'failed', 'manual_completed'], TRUE);
    $auth_process_vetted = $attempt_count > 0 || $submission_started;
    $submission_completed = in_array($submission_status, ['submitted', 'confirmed', 'manual_completed'], TRUE);
    $has_attempt_evidence = $attempt_count > 0 || $last_attempt_outcome !== '';
    $has_confirmation = $confirmation !== '';
    $resolved_confidence = in_array(strtolower($resolution_confidence), ['high', 'medium'], TRUE);
    $verification_passed = !empty($selected_application['verification_passed']);
    $redirect_chain_fully_resolved = $verification_passed || ($has_resolved_url && $has_resolution_trace && $resolved_confidence);
    // Step 3 passes when either:
    //   (a) the auth_type has been explicitly classified by Step 3 service, OR
    //   (b) legacy: ATS platform is known and not 'unknown'/'aggregator'.
    $stored_auth_type = strtolower((string) ($selected_application['auth_type'] ?? ''));
    $auth_type_classified = $stored_auth_type !== '' && !in_array($stored_auth_type, ['unknown', 'captcha_blocked'], TRUE);
    $auth_path_identified = $auth_type_classified || ($has_ats_detection && !in_array(strtolower($ats_platform), ['unknown', 'aggregator', ''], TRUE));
    $last_outcome_lc = strtolower($last_attempt_outcome);
    $auth_blocked = in_array($last_outcome_lc, ['auth_required', 'auth_failed', 'credential_missing', 'login_required'], TRUE)
      || str_contains(strtolower($last_attempt_error), 'auth')
      || str_contains(strtolower($last_attempt_error), 'login')
      || str_contains(strtolower($last_attempt_error), 'credential');
    // Step 4 passes when:
    //   (a) user confirmed account readiness via Step 4 page, OR
    //   (b) legacy: submission started and auth is not blocked.
    $account_readiness_at = (string) ($selected_application['account_readiness_at'] ?? '');
    $account_readiness_confirmed = $account_readiness_at !== '' || ($submission_started && !$auth_blocked);
    $job_title = (string) ($selected_job->job_title ?? '');
    $company_name = (string) ($selected_job->company_name ?? '');
    $job_exists_on_destination = $job_title !== '' && $company_name !== '';
    $apply_control_located = $submission_started || $has_attempt_evidence || ($has_resolved_url && $auth_path_identified);

    $step_1_detail = $pdf_ready
      ? 'Tailored PDF is generated and ready for submission.'
      : 'Tailored PDF not generated yet for this role.';
    $step_2_detail = $redirect_chain_fully_resolved
      ? ($verification_passed
        ? 'Redirect chain resolved and verified by Step 2 checks.'
        : 'Redirect chain resolved to a canonical apply destination.')
      : 'Redirect chain is not fully resolved yet.';
    $step_3_detail = $auth_path_identified
      ? ('Authentication path identified' . ($stored_auth_type !== '' ? ': ' . $stored_auth_type : ' (ATS: ' . $ats_platform . ')') . '.')
      : 'Authentication path not fully identified yet. Run Step 3 to classify.';
    $step_4_detail = $account_readiness_confirmed
      ? ('Account readiness confirmed' . ($account_readiness_at !== '' ? ' at ' . $account_readiness_at : '') . '.')
      : 'Account readiness is not confirmed yet. Run Step 4 to create account and verify.';
    // Step 5 combines: confirm job exists + locate apply control + submit.
    $step_5_gate = $job_exists_on_destination && $apply_control_located && $submission_completed;
    $step_5_detail = $submission_completed
      ? 'Application submitted successfully.'
      : ($submission_started
        ? ('Submission in progress (status: ' . $submission_status . ').')
        : ($job_exists_on_destination && $apply_control_located
          ? 'Ready to submit — job confirmed and apply path located.'
          : ($job_exists_on_destination
            ? 'Job confirmed on destination. Apply control not yet located.'
            : 'Job destination/requisition context is incomplete.')));
    $step_6_detail = $has_confirmation
      ? ('Confirmation captured: ' . $confirmation)
      : ($has_attempt_evidence ? 'Attempt evidence exists, but confirmation is not captured yet.' : 'No evidence/confirmation captured yet.');

    $step_1_requirements = [
      [
        'label' => 'Tailored resume content is complete for this role',
        'met' => $tailoring_completed,
        'evidence' => 'Workflow status: ' . ($workflow_status !== '' ? $workflow_status : 'unknown'),
      ],
      [
        'label' => 'Tailored resume PDF is generated and ready for submission',
        'met' => $pdf_ready,
        'evidence' => $pdf_ready ? 'PDF is generated.' : 'PDF is not generated yet.',
      ],
      [
        'label' => 'Profile prerequisite is satisfied for automation',
        'met' => $has_profile,
        'evidence' => $has_profile ? 'Profile prerequisite passed.' : 'Profile prerequisite not satisfied.',
      ],
    ];

    $step_2_requirements = [
      [
        'label' => 'Resolved destination apply URL is captured',
        'met' => $has_resolved_url,
        'evidence' => $has_resolved_url ? $apply_url : 'Apply URL not captured yet.',
      ],
      [
        'label' => 'Company careers page or direct application page is identified',
        'met' => $career_page_identified,
        'evidence' => $career_page_identified ? ('Career page identified: ' . $career_url) : 'Career/apply page not yet identified.',
      ],
      [
        'label' => 'Redirect chain trace is stored',
        'met' => $verification_passed || $has_resolution_trace,
        'evidence' => ($verification_passed || $has_resolution_trace)
          ? ($verification_passed ? 'Step 2 verification passed.' : ('Resolution steps: ' . $resolution_steps_count))
          : 'Redirect chain evidence not stored yet.',
      ],
      [
        'label' => 'Redirect resolution confidence is sufficient',
        'met' => $verification_passed || $resolved_confidence,
        'evidence' => ($verification_passed || $resolution_confidence !== '')
          ? ($verification_passed ? 'Step 2 verification passed.' : ('Confidence: ' . $resolution_confidence))
          : 'Resolution confidence not available.',
      ],
    ];

    $step_3_requirements = [
      [
        'label' => 'Authentication path (ATS/login flow) is identified',
        'met' => $auth_path_identified,
        'evidence' => $auth_path_identified ? ($stored_auth_type !== '' ? ('auth_type: ' . $stored_auth_type) : ('ATS: ' . $ats_platform)) : 'Authentication path not identified. Run Step 3 to classify.',
      ],
    ];

    $step_4_requirements = [
      [
        'label' => 'User can authenticate (account readiness confirmed)',
        'met' => $account_readiness_confirmed,
        'evidence' => $account_readiness_confirmed
          ? ($account_readiness_at !== '' ? 'Account verified at ' . $account_readiness_at . '.' : 'Submission/attempts indicate authentication is working.')
          : ($auth_blocked ? 'Authentication appears blocked by credential/login errors.' : 'Account readiness not confirmed yet. Run Step 4 to create account.'),
      ],
    ];

    $step_5_requirements = [
      [
        'label' => 'Job requisition appears available on destination',
        'met' => $job_exists_on_destination,
        'evidence' => $job_exists_on_destination ? ('Job: ' . $job_title . ' | Company: ' . $company_name) : 'Job/company destination context is incomplete.',
      ],
      [
        'label' => 'Apply control entry point is located',
        'met' => $apply_control_located,
        'evidence' => $apply_control_located
          ? 'Apply URL and/or attempts indicate actionable apply control path.'
          : 'Apply control path not confirmed from current evidence.',
      ],
      [
        'label' => 'Submission request has started for this job',
        'met' => $submission_started,
        'evidence' => $submission_status !== '' ? ('Submission status: ' . $submission_status) : 'No submission record yet.',
      ],
      [
        'label' => 'Submission reached a successful completion state',
        'met' => $submission_completed,
        'evidence' => $submission_completed ? 'Submission completed.' : 'Submission not complete yet.',
      ],
    ];

    $steps = [
      [
        'index' => 1,
        'label' => 'Step Pre-requirements',
        'status' => 'pending',
        'detail' => $step_1_detail,
        'requirements' => $step_1_requirements,
      ],
      [
        'index' => 2,
        'label' => 'Resolve redirect chain',
        'status' => 'pending',
        'detail' => $step_2_detail,
        'requirements' => $step_2_requirements,
      ],
      [
        'index' => 3,
        'label' => 'Identify authentication path',
        'status' => 'pending',
        'detail' => $step_3_detail,
        'requirements' => $step_3_requirements,
      ],
      [
        'index' => 4,
        'label' => 'Verify account readiness',
        'status' => 'pending',
        'detail' => $step_4_detail,
        'requirements' => $step_4_requirements,
      ],
      [
        'index' => 5,
        'label' => 'Submit application',
        'status' => 'pending',
        'detail' => $step_5_detail,
        'requirements' => $step_5_requirements,
      ],
      [
        'index' => 6,
        'label' => 'Capture confirmation and evidence',
        'status' => 'pending',
        'detail' => $step_6_detail,
        'requirements' => [
          [
            'label' => 'At least one attempt/evidence record exists',
            'met' => $has_attempt_evidence,
            'evidence' => $has_attempt_evidence ? ('Attempts: ' . $attempt_count) : 'No attempt evidence found.',
          ],
          [
            'label' => 'Confirmation reference is captured',
            'met' => $has_confirmation,
            'evidence' => $has_confirmation ? $confirmation : 'Confirmation reference missing.',
          ],
        ],
      ],
    ];

    $previous_unmet = FALSE;
    foreach ($steps as &$step) {
      $requirements = $step['requirements'] ?? [];
      $gate_met = !empty($requirements);
      foreach ($requirements as $requirement) {
        if (empty($requirement['met'])) {
          $gate_met = FALSE;
          break;
        }
      }

      $step['gate_met'] = $gate_met;
      if ($gate_met) {
        $step['status'] = 'completed';
      }
      elseif ($previous_unmet) {
        $step['status'] = 'blocked';
      }
      else {
        $step['status'] = 'current';
        $previous_unmet = TRUE;
      }
    }
    unset($step);

    return $steps;
  }

  /**
   * Load selected job context for a specific user/job journey page.
   */
  /**
   * Detect ATS platform from a URL based on known hostname patterns.
   *
   * @param string $url
   *   The URL to inspect.
   *
   * @return string
   *   Detected ATS platform slug, or 'custom' if unrecognized.
   */
  private function detectAtsPlatformFromUrl(string $url): string {
    if ($url === '') {
      return 'custom';
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === '' || $host === FALSE) {
      return 'custom';
    }
    $patterns = [
      'myworkdayjobs'  => ['myworkdayjobs.com', 'wd5.myworkdayjobs', 'wd3.myworkdayjobs', 'wd1.myworkdayjobs'],
      'greenhouse'     => ['greenhouse.io', 'boards.greenhouse.io'],
      'lever'          => ['lever.co', 'jobs.lever.co'],
      'icims'          => ['icims.com'],
      'taleo'          => ['taleo.net'],
      'smartrecruiters' => ['smartrecruiters.com'],
      'ashbyhq'        => ['ashbyhq.com'],
      'bamboohr'       => ['bamboohr.com'],
      'jobvite'        => ['jobvite.com'],
      'ultipro'        => ['ultipro.com'],
      'successfactors'  => ['successfactors.com', 'successfactors.eu'],
      'brassring'      => ['brassring.com'],
    ];
    foreach ($patterns as $platform => $domains) {
      foreach ($domains as $domain) {
        if (str_contains($host, $domain)) {
          return $platform;
        }
      }
    }
    return 'custom';
  }

  private function loadSelectedJobContext(int $uid, int $job_id): ?object {
    return $this->repository->loadJobContext($uid, $job_id);
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
