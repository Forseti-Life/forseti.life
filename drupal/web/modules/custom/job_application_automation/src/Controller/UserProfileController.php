<?php

namespace Drupal\job_application_automation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\job_application_automation\Service\UserProfileService;

/**
 * Controller for user profile management functionality.
 */
class UserProfileController extends ControllerBase {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The user profile service.
   *
   * @var \Drupal\job_application_automation\Service\UserProfileService
   */
  protected $userProfileService;

  /**
   * Constructs a new UserProfileController object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\job_application_automation\Service\UserProfileService $user_profile_service
   *   The user profile service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, UserProfileService $user_profile_service) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->userProfileService = $user_profile_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('job_application_automation.user_profile_service')
    );
  }

  /**
   * Displays the user profile dashboard.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   A render array for the profile dashboard.
   */
  public function dashboard($user = NULL) {
    // Load the user entity - either specified user or current user
    $uid = $user ? $user->id() : $this->currentUser->id();
    $user_entity = $user ?: User::load($uid);

    if (!$user_entity) {
      $this->messenger()->addError($this->t('User not found.'));
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    // Check access - users can only view their own profile unless admin
    if ($uid != $this->currentUser->id() && !$this->currentUser->hasPermission('administer users')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $build = [];

    // Page header
    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile-dashboard-header']],
    ];

    $build['header']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h1',
      '#value' => $this->t('My Job Application Profile'),
      '#attributes' => ['class' => ['profile-title']],
    ];

    // Profile completeness widget
    $completeness = $this->userProfileService->calculateProfileCompleteness($user_entity);
    $build['completeness'] = $this->buildCompletenessWidget($user_entity, $completeness);

    // Quick stats
    $build['stats'] = $this->buildProfileStats($user_entity);

    // Profile sections summary
    $build['sections'] = $this->buildProfileSections($user_entity);

    // Actions
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile-actions']],
    ];

    $build['actions']['edit_profile'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Profile'),
      '#url' => Url::fromRoute('job_application_automation.user_profile_edit', ['user' => $uid]),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $build['actions']['view_applications'] = [
      '#type' => 'link',
      '#title' => $this->t('View My Applications'),
      '#url' => Url::fromRoute('job_application_automation.home'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    // Add CSS and JS
    $build['#attached']['library'][] = 'job_application_automation/user_profile';

    // Use custom template for professional styling
    $build['#theme'] = 'user_profile_dashboard';
    $build['#user'] = $user_entity;

    return $build;
  }

  /**
   * Builds the profile completeness widget.
   *
   * @param \Drupal\user\Entity\User $user_entity
   *   The user entity.
   * @param int $completeness
   *   The completeness percentage.
   *
   * @return array
   *   Render array for the completeness widget.
   */
  protected function buildCompletenessWidget(User $user_entity, $completeness) {
    $status_class = 'low';
    $status_text = $this->t('Getting Started');
    
    if ($completeness >= 70) {
      $status_class = 'high';
      $status_text = $this->t('Profile Complete');
    } elseif ($completeness >= 40) {
      $status_class = 'medium';
      $status_text = $this->t('Almost There');
    }

    $widget = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['profile-completeness-widget', "completeness-{$status_class}"],
      ],
    ];

    $widget['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Profile Completeness'),
      '#attributes' => ['class' => ['widget-title']],
    ];

    $widget['progress_container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['progress-container']],
    ];

    $widget['progress_container']['progress_bar'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['progress-bar'],
      ],
    ];

    $widget['progress_container']['progress_bar']['fill'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['progress-fill'],
        'style' => "width: {$completeness}%",
      ],
    ];

    $widget['progress_container']['progress_text'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('@percent% Complete', ['@percent' => $completeness]),
      '#attributes' => ['class' => ['progress-text']],
    ];

    $widget['status'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $status_text,
      '#attributes' => ['class' => ['completeness-status']],
    ];

    // Add recommendations for improvement
    if ($completeness < 70) {
      $missing_fields = $this->userProfileService->getMissingFieldRecommendations($user_entity);
      if (!empty($missing_fields)) {
        $widget['recommendations'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['completeness-recommendations']],
        ];
        
        $widget['recommendations']['title'] = [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Complete these to reach 70%:'),
          '#attributes' => ['class' => ['recommendations-title']],
        ];

        $widget['recommendations']['list'] = [
          '#theme' => 'item_list',
          '#items' => $missing_fields,
          '#attributes' => ['class' => ['recommendations-list']],
        ];
      }
    }

    return $widget;
  }

  /**
   * Builds profile statistics.
   *
   * @param \Drupal\user\Entity\User $user_entity
   *   The user entity.
   *
   * @return array
   *   Render array for profile stats.
   */
  protected function buildProfileStats(User $user_entity) {
    $stats = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile-stats']],
    ];

    $stats['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Profile Overview'),
      '#attributes' => ['class' => ['section-title']],
    ];

    $stats['grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stats-grid']],
    ];

    // Last updated
    $last_update = 'Never';
    if ($user_entity->hasField('field_last_profile_update') && !$user_entity->get('field_last_profile_update')->isEmpty()) {
      $last_update_timestamp = $user_entity->get('field_last_profile_update')->value;
      $last_update = \Drupal::service('date.formatter')->format(strtotime($last_update_timestamp), 'short');
    }

    $stats['grid']['last_updated'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-item']],
    ];
    $stats['grid']['last_updated']['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Last Updated'),
      '#attributes' => ['class' => ['stat-label']],
    ];
    $stats['grid']['last_updated']['value'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $last_update,
      '#attributes' => ['class' => ['stat-value']],
    ];

    // Work Authorization
    $work_auth = $this->t('Not specified');
    if ($user_entity->hasField('field_work_authorization') && !$user_entity->get('field_work_authorization')->isEmpty()) {
      $work_auth_value = $user_entity->get('field_work_authorization')->value;
      $work_auth_options = [
        'us_citizen' => $this->t('US Citizen'),
        'permanent_resident' => $this->t('Permanent Resident'),
        'h1b' => $this->t('Work Visa (H1B)'),
        'f1' => $this->t('Student Visa (F1)'),
        'visa_required' => $this->t('Visa Sponsorship Required'),
        'other' => $this->t('Other'),
      ];
      $work_auth = $work_auth_options[$work_auth_value] ?? $work_auth_value;
    }

    $stats['grid']['work_auth'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-item']],
    ];
    $stats['grid']['work_auth']['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Work Authorization'),
      '#attributes' => ['class' => ['stat-label']],
    ];
    $stats['grid']['work_auth']['value'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $work_auth,
      '#attributes' => ['class' => ['stat-value']],
    ];

    // Experience Years
    $experience = $this->t('Not specified');
    if ($user_entity->hasField('field_experience_years') && !$user_entity->get('field_experience_years')->isEmpty()) {
      $years = $user_entity->get('field_experience_years')->value;
      $experience = $this->formatPlural($years, '1 year', '@count years');
    }

    $stats['grid']['experience'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-item']],
    ];
    $stats['grid']['experience']['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Experience'),
      '#attributes' => ['class' => ['stat-label']],
    ];
    $stats['grid']['experience']['value'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $experience,
      '#attributes' => ['class' => ['stat-value']],
    ];

    // Remote Preference
    $remote_pref = $this->t('Not specified');
    if ($user_entity->hasField('field_remote_preference') && !$user_entity->get('field_remote_preference')->isEmpty()) {
      $remote_value = $user_entity->get('field_remote_preference')->value;
      $remote_options = [
        'remote' => $this->t('Remote'),
        'hybrid' => $this->t('Hybrid'),
        'onsite' => $this->t('On-site'),
        'no_preference' => $this->t('No Preference'),
      ];
      $remote_pref = $remote_options[$remote_value] ?? $remote_value;
    }

    $stats['grid']['remote_pref'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-item']],
    ];
    $stats['grid']['remote_pref']['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Remote Preference'),
      '#attributes' => ['class' => ['stat-label']],
    ];
    $stats['grid']['remote_pref']['value'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $remote_pref,
      '#attributes' => ['class' => ['stat-value']],
    ];

    return $stats;
  }

  /**
   * Builds profile sections summary.
   *
   * @param \Drupal\user\Entity\User $user_entity
   *   The user entity.
   *
   * @return array
   *   Render array for profile sections.
   */
  protected function buildProfileSections(User $user_entity) {
    $sections = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile-sections']],
    ];

    $sections['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Profile Sections'),
      '#attributes' => ['class' => ['section-title']],
    ];

    // Core Information
    $has_resume = $user_entity->hasField('field_resume_file') && !$user_entity->get('field_resume_file')->isEmpty();
    $has_summary = $user_entity->hasField('field_professional_summary') && !$user_entity->get('field_professional_summary')->isEmpty();
    $has_skills = $user_entity->hasField('field_skills_summary') && !$user_entity->get('field_skills_summary')->isEmpty();
    
    $core_completed = 0;
    if ($has_resume) $core_completed++;
    if ($has_summary) $core_completed++;
    if ($has_skills) $core_completed++;

    $sections['core'] = $this->buildSectionSummary(
      $this->t('Core Professional Information'),
      $this->t('Resume, professional summary, and skills'),
      $core_completed,
      3,
      'core'
    );

    // Employment Preferences
    $employment_fields = ['field_work_authorization', 'field_salary_expectation_min', 'field_remote_preference'];
    $employment_completed = 0;
    foreach ($employment_fields as $field) {
      if ($user_entity->hasField($field) && !$user_entity->get($field)->isEmpty()) {
        $employment_completed++;
      }
    }

    $sections['employment'] = $this->buildSectionSummary(
      $this->t('Employment Preferences'),
      $this->t('Work authorization, salary expectations, and preferences'),
      $employment_completed,
      count($employment_fields),
      'employment'
    );

    // Online Presence
    $online_fields = ['field_linkedin_url', 'field_portfolio_url', 'field_github_url'];
    $online_completed = 0;
    foreach ($online_fields as $field) {
      if ($user_entity->hasField($field) && !$user_entity->get($field)->isEmpty()) {
        $uri = $user_entity->get($field)->uri;
        if (!empty($uri)) {
          $online_completed++;
        }
      }
    }

    $sections['online'] = $this->buildSectionSummary(
      $this->t('Online Presence'),
      $this->t('LinkedIn, portfolio, and GitHub profiles'),
      $online_completed,
      count($online_fields),
      'online'
    );

    return $sections;
  }

  /**
   * Builds a section summary widget.
   *
   * @param string $title
   *   The section title.
   * @param string $description
   *   The section description.
   * @param int $completed
   *   Number of completed items.
   * @param int $total
   *   Total number of items.
   * @param string $section_key
   *   The section key for CSS classes.
   *
   * @return array
   *   Render array for the section summary.
   */
  protected function buildSectionSummary($title, $description, $completed, $total, $section_key) {
    $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
    $status = $percentage == 100 ? 'complete' : ($percentage > 0 ? 'partial' : 'empty');

    $section = [
      '#type' => 'container',
      '#attributes' => ['class' => ['section-summary', "section-{$section_key}", "status-{$status}"]],
    ];

    $section['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $title,
      '#attributes' => ['class' => ['section-summary-title']],
    ];

    $section['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $description,
      '#attributes' => ['class' => ['section-summary-description']],
    ];

    $section['progress'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('@completed of @total completed (@percent%)', [
        '@completed' => $completed,
        '@total' => $total,
        '@percent' => $percentage,
      ]),
      '#attributes' => ['class' => ['section-summary-progress']],
    ];

    $section['progress_bar'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['section-progress-bar']],
    ];

    $section['progress_bar']['fill'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['section-progress-fill'],
        'style' => "width: {$percentage}%",
      ],
    ];

    return $section;
  }

  /**
   * Redirects to current user's profile dashboard.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the current user's profile.
   */
  public function myProfile() {
    return $this->redirect('job_application_automation.user_profile_dashboard', [
      'user' => $this->currentUser->id(),
    ]);
  }

  /**
   * Redirects to current user's profile edit form.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the current user's profile edit form.
   */
  public function myProfileEdit() {
    return $this->redirect('job_application_automation.user_profile_edit', [
      'user' => $this->currentUser->id(),
    ]);
  }

  /**
   * Displays the job seeker profile in view mode (not edit mode).
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   The render array for the profile view.
   */
  public function viewJobSeekerProfile(User $user) {
    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profile = $profile_storage->loadByUser($user, 'job_seeker');

    if (!$profile) {
      // If no profile exists, redirect to create one
      return $this->redirect('profile.user_page.add_form', [
        'user' => $user->id(),
        'profile_type' => 'job_seeker',
      ]);
    }

    // Build the profile view (not edit form)
    $view_builder = $this->entityTypeManager->getViewBuilder('profile');
    $build = $view_builder->view($profile, 'default');

    return $build;
  }

  /**
   * Start job discovery page for a user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   The render array for the job discovery page.
   */
  public function startJobDiscovery(User $user) {
    // Check access - user can only access their own job discovery
    if ($user->id() != $this->currentUser->id() && !$this->currentUser->hasPermission('administer users')) {
      throw new AccessDeniedHttpException();
    }

    // Load user's job seeker profile for keywords
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profiles = $profile_storage->loadByProperties([
      'uid' => $user->id(),
      'type' => 'job_seeker',
    ]);

    $profile = reset($profiles);
    
    if (!$profile) {
      $this->messenger()->addError($this->t('Please complete your job seeker profile first before starting job discovery.'));
      return $this->redirect('job_application_automation.user_job_seeker_view', ['user' => $user->id()]);
    }

    // Extract keywords from profile
    $keywords = $this->extractKeywordsFromProfile($profile);
    
    // Build the render array for the job discovery page
    $build = [
      '#theme' => 'job_discovery_start',
      '#user' => $user,
      '#profile' => $profile,
      '#keywords' => $keywords,
      '#attached' => [
        'library' => [
          'job_application_automation/job_discovery',
        ],
      ],
    ];

    return $build;
  }

  /**
   * AJAX endpoint for job discovery search.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with job search results.
   */
  public function jobDiscoverySearch() {
    $request = \Drupal::request();
    $user_id = $request->request->get('user_id');
    $company = $request->request->get('company', 'abbvie');
    
    if (!$user_id || !is_numeric($user_id)) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'error' => 'Invalid user ID',
      ], 400);
    }
    
    try {
      // Load user and profile
      $user = \Drupal\user\Entity\User::load($user_id);
      if (!$user) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'error' => 'User not found',
        ], 404);
      }
      
      // Check access
      if ($user->id() != $this->currentUser->id() && !$this->currentUser->hasPermission('administer users')) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'error' => 'Access denied',
        ], 403);
      }
      
      // Load job seeker profile
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      $profiles = $profile_storage->loadByProperties([
        'uid' => $user->id(),
        'type' => 'job_seeker',
      ]);
      
      $profile = reset($profiles);
      if (!$profile) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'error' => 'Job seeker profile not found',
        ], 404);
      }
      
      // Extract keywords from profile
      $keywords = $this->extractKeywordsFromProfile($profile);
      
      if (empty($keywords)) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'jobs' => [],
          'message' => 'No keywords found in profile',
        ]);
      }
      
      // Get the job scraping service
      $scraping_service = \Drupal::service('job_application_automation.abbvie_job_scraping_service');
      
      // Search for jobs
      $jobs = $scraping_service->searchJobs($keywords, [
        'company' => $company,
      ]);
      
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'jobs' => $jobs,
        'keywords_used' => $keywords,
        'total_found' => count($jobs),
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('job_application_automation')->error('Job discovery search error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'error' => 'Search failed: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Extract keywords from job seeker profile.
   *
   * @param \Drupal\profile\Entity\Profile $profile
   *   The job seeker profile.
   *
   * @return array
   *   Array of keywords extracted from profile fields.
   */
  private function extractKeywordsFromProfile($profile) {
    $keywords = [];
    
    // Extract from various fields that contain relevant keywords
    $keyword_fields = [
      'field_skills',
      'field_job_title',
      'field_career_objectives',
      'field_experience_summary',
      'field_industry_preferences',
    ];
    
    foreach ($keyword_fields as $field_name) {
      if ($profile->hasField($field_name) && !$profile->get($field_name)->isEmpty()) {
        $field_value = $profile->get($field_name)->value;
        if (!empty($field_value)) {
          // Split by common delimiters and clean up
          $field_keywords = preg_split('/[,;\n\r]+/', $field_value);
          foreach ($field_keywords as $keyword) {
            $keyword = trim($keyword);
            if (strlen($keyword) > 2) { // Only include meaningful keywords
              $keywords[] = $keyword;
            }
          }
        }
      }
    }
    
    // Remove duplicates and return
    return array_unique($keywords);
  }

}