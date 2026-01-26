<?php

declare(strict_types=1);

namespace Drupal\nfr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Component\Render\FormattableMarkup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Controller for NFR validation and testing.
 */
class NFRValidationController extends ControllerBase {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly RouteProviderInterface $routeProvider,
    private readonly AccountSwitcherInterface $accountSwitcher,
    private readonly HttpKernelInterface $httpKernel,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('router.route_provider'),
      $container->get('account_switcher'),
      $container->get('http_kernel'),
      $container->get('logger.factory')->get('nfr'),
    );
  }

  /**
   * Validation dashboard page.
   *
   * @return array
   *   Render array.
   */
  public function validationDashboard(): array {
    $nfr_routes = $this->getNFRRoutes();
    $test_users = $this->getTestUsers();

    return [
      '#theme' => 'nfr_admin_page',
      '#page_id' => 'validation-dashboard',
      '#content' => [
        '#markup' => new FormattableMarkup($this->buildValidationDashboard($nfr_routes, $test_users), []),
      ],
      '#attached' => [
        'library' => ['nfr/admin', 'nfr/validation'],
      ],
    ];
  }

  /**
   * Get all NFR routes.
   */
  private function getNFRRoutes(): array {
    $routes = [];
    
    // Get all routes
    $all_routes = $this->routeProvider->getAllRoutes();
    
    // Routes to exclude from testing (validation/testing routes themselves)
    $exclude_routes = [
      'nfr.validation',
      'nfr.validation.test_route',
      'nfr.validation.test_questionnaire',
      'nfr.validation.verify_database',
      'nfr.validation.clear_test_data',
      'nfr.validation.test_full_enrollment',
      'nfr.validation.test_max_values',
      'nfr.validation.test_min_values',
      'nfr.validation.test_yes_minimal',
      'nfr.validation.check_error_logs',
      'nfr.validation.create_test_users',
      'nfr.validation.delete_test_users',
      'nfr.validation.submit_all_firefighters',
      'nfr.validation.fill_rates',
      'nfr.validation.fill_rates_redirect',
    ];
    
    foreach ($all_routes as $route_name => $route) {
      // Filter only NFR routes, excluding validation routes
      if (str_starts_with($route_name, 'nfr.') && !in_array($route_name, $exclude_routes)) {
        $path = $route->getPath();
        $requirements = $route->getRequirements();
        $permission = $requirements['_permission'] ?? null;
        $logged_in = isset($requirements['_user_is_logged_in']);
        
        $routes[$route_name] = [
          'name' => $route_name,
          'path' => $path,
          'permission' => $permission,
          'requires_login' => $logged_in,
          'title' => $route->getDefault('_title') ?? 'No title',
        ];
      }
    }
    
    // Sort by path
    usort($routes, fn($a, $b) => strcmp($a['path'], $b['path']));
    
    return $routes;
  }

  /**
   * Get test users with their expected access levels.
   * 
   * Permission Matrix:
   * - access content: All authenticated users (default Drupal permission)
   * - access nfr dashboard: Firefighters, Dept Admins, NFR Admins, Researchers
   * - administer nfr: NFR Administrators only (full system control)
   * - view nfr reports: NFR Researchers and NFR Administrators
   * 
   * Expected Access by Role:
   * - Anonymous (uid=0): Public pages only (/nfr, /nfr/faq, /nfr/contact, /nfr/documentation)
   * - Firefighter Active (uid=2): Enrollment pages, My Dashboard, public pages
   * - Firefighter Retired (uid=3): Enrollment pages, My Dashboard, public pages
   * - NFR Administrator (uid=4): ALL pages including /admin/nfr/* (complete system access)
   * - NFR Researcher (uid=5): Public pages, reports, data quality/validation (read-only admin), participant list, linkage status
   * - Fire Dept Admin (uid=6): SAME as firefighters - enrollment, dashboard, public pages (NO admin access)
   */
  private function getTestUsers(): array {
    return [
      'anonymous' => [
        'uid' => 0,
        'name' => 'Anonymous',
        'label' => 'Anonymous User',
        'expected_access' => [
          'public_pages' => true,
          'enrollment' => false,
          'dashboard' => false,
          'admin' => false,
        ],
      ],
      'firefighter_active' => [
        'uid' => 2,
        'name' => 'firefighter_active',
        'label' => 'Firefighter (Active)',
        'expected_access' => [
          'public_pages' => true,
          'enrollment' => true,
          'dashboard' => true,
          'admin' => false,
        ],
      ],
      'firefighter_retired' => [
        'uid' => 3,
        'name' => 'firefighter_retired',
        'label' => 'Firefighter (Retired)',
        'expected_access' => [
          'public_pages' => true,
          'enrollment' => true,
          'dashboard' => true,
          'admin' => false,
        ],
      ],
      'nfr_admin' => [
        'uid' => 4,
        'name' => 'nfr_admin',
        'label' => 'NFR Administrator',
        'expected_access' => [
          'public_pages' => true,
          'enrollment' => true,
          'dashboard' => true,
          'admin' => true,
        ],
      ],
      'nfr_researcher' => [
        'uid' => 5,
        'name' => 'nfr_researcher',
        'label' => 'NFR Researcher',
        'expected_access' => [
          'public_pages' => true,
          'enrollment' => false,
          'dashboard' => true,
          'admin' => false,
          'reports' => true,
        ],
      ],
      'dept_admin' => [
        'uid' => 6,
        'name' => 'dept_admin',
        'label' => 'Fire Dept Admin',
        'expected_access' => [
          'public_pages' => true,
          'enrollment' => false,
          'dashboard' => true,
          'admin' => false,
        ],
      ],
    ];
  }

  /**
   * Determine if a user should have access to a route based on permission.
   * 
   * @param string|null $permission
   *   The required permission for the route.
   * @param bool $requires_login
   *   Whether the route requires login.
   * @param string $user_key
   *   The user key (anonymous, firefighter_active, etc.).
   * @param string $route_name
   *   The route name to check for special cases.
   * 
   * @return bool
   *   TRUE if access should be granted, FALSE otherwise.
   */
  private function shouldHaveAccess(?string $permission, bool $requires_login, string $user_key, string $route_name = ''): bool {
    // Anonymous user
    if ($user_key === 'anonymous') {
      // Can only access routes that don't require login and have 'access content' or no permission
      return !$requires_login && ($permission === 'access content' || $permission === null);
    }

    // If route requires login and user is not anonymous, check permission
    if ($requires_login && $user_key === 'anonymous') {
      return FALSE;
    }

    // All authenticated users have 'access content' by default
    if ($permission === 'access content' || $permission === null) {
      return TRUE;
    }

    // Check specific permissions by role
    switch ($user_key) {
      case 'nfr_admin':
        // NFR Admin has all permissions - full system access
        return TRUE;

      case 'nfr_researcher':
        // Researcher has: view nfr reports, access nfr dashboard
        // Plus special access to data quality/validation pages for research purposes
        if (in_array($permission, ['view nfr reports', 'access nfr dashboard'])) {
          return TRUE;
        }
        // Grant access to admin pages that are read-only/reporting focused
        if ($permission === 'administer nfr') {
          // Check route name for data quality, validation, and reporting routes
          $researcher_routes = [
            'nfr.admin_data_quality',
            'nfr.admin_reports',
            'nfr.admin_linkage', // View linkage status
            'nfr.admin_participants', // View participant list (read-only)
          ];
          if (in_array($route_name, $researcher_routes)) {
            return TRUE;
          }
        }
        return FALSE;

      case 'firefighter_active':
      case 'firefighter_retired':
        // Firefighters have: access nfr dashboard
        return $permission === 'access nfr dashboard';

      case 'dept_admin':
        // Fire Dept Admin has SAME access as firefighters
        // access nfr dashboard only - no admin pages
        return $permission === 'access nfr dashboard';

      default:
        return FALSE;
    }
  }

  /**
   * Build validation dashboard HTML.
   */
  private function buildValidationDashboard(array $routes, array $users): string {
    $html = '<div class="validation-dashboard">';
    
    // Header
    $html .= '<div class="validation-header">';
    $html .= '<h1>' . $this->t('NFR Validation Dashboard') . '</h1>';
    $html .= '<p class="validation-subtitle">' . 
      $this->t('Test all NFR routes with different user permissions') . '</p>';
    $html .= '<p><a href="/admin/nfr/validation/fill-rates" class="btn btn-outline-info">📊 View Fill Rates Report</a></p>';
    
    // User statistics bar
    $total_users = $this->getTotalUsers();
    $test_users = $this->getTestUsersCount();
    $production_users = $total_users - $test_users;
    
    $html .= '<div class="user-stats-bar">';
    $html .= '<div class="stat-item">';
    $html .= '<span class="stat-label">Total Users:</span> ';
    $html .= '<span class="stat-value">' . number_format($total_users) . '</span>';
    $html .= '</div>';
    $html .= '<div class="stat-divider">|</div>';
    $html .= '<div class="stat-item">';
    $html .= '<span class="stat-label">Production Users:</span> ';
    $html .= '<span class="stat-value text-success">' . number_format($production_users) . '</span>';
    $html .= '</div>';
    $html .= '<div class="stat-divider">|</div>';
    $html .= '<div class="stat-item">';
    $html .= '<span class="stat-label">Test Users:</span> ';
    $html .= '<span class="stat-value text-warning">' . number_format($test_users) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div>';

    // Questionnaire Test Section
    $html .= '<div class="questionnaire-test-section card card-forseti mb-4">';
    $html .= '<h2 class="text-white">🧪 Questionnaire Data Flow Test</h2>';
    $html .= '<p><strong>Tests questionnaire only (9 sections).</strong> Assumes profile is already complete. Submits data through Section 1-9 forms and verifies database storage.</p>';
    $html .= '<ul class="text-muted small mb-3">';
    $html .= '<li>Uses existing test user (firefighter_active)</li>';
    $html .= '<li>Generates sample data for all sections</li>';
    $html .= '<li>Submits through actual form workflow</li>';
    $html .= '<li>Verifies questionnaire data in database</li>';
    $html .= '</ul>';
    $html .= '<div class="test-controls">';
    $html .= '<button id="test-questionnaire-flow" class="btn btn-cyan btn-large">';
    $html .= '📝 Run Questionnaire Test</button>';
    $html .= '<button id="verify-questionnaire-data" class="btn btn-outline-primary">';
    $html .= '✓ Verify Database</button>';
    $html .= '<button id="clear-test-data" class="btn btn-outline-secondary">';
    $html .= '🗑️ Clear Test Data</button>';
    $html .= '</div>';
    $html .= '<div id="questionnaire-test-results" class="test-results mt-3"></div>';
    $html .= '</div>';

    // Full Enrollment Flow Test Section
    $html .= '<div class="enrollment-flow-test-section card card-forseti mb-4">';
    $html .= '<h2 class="text-white">🚀 Complete Enrollment Flow Tests</h2>';
    $html .= '<p><strong>Tests entire enrollment process (Profile + Questionnaire).</strong> Full end-to-end validation from profile creation through all 9 questionnaire sections.</p>';
    $html .= '<ul class="text-muted small mb-3">';
    $html .= '<li>Creates/updates user profile data</li>';
    $html .= '<li>Submits all 9 questionnaire sections</li>';
    $html .= '<li>Checks system error logs for issues</li>';
    $html .= '<li>Verifies both profile and questionnaire in database</li>';
    $html .= '<li>Tests with different data patterns (random, max values, min values)</li>';
    $html .= '</ul>';
    $html .= '<div class="test-controls">';
    $html .= '<button id="test-full-enrollment" class="btn btn-primary btn-large">';
    $html .= '🎲 Run Full Enrollment Test (Random Data)</button>';
    $html .= '<button id="test-max-values" class="btn btn-success btn-large">';
    $html .= '⬆️ Max Values Test (Yes to All)</button>';
    $html .= '<button id="test-min-values" class="btn btn-info btn-large">';
    $html .= '⬇️ Min Values Test (No to All)</button>';
    $html .= '<button id="test-yes-minimal" class="btn btn-warning btn-large">';
    $html .= '✔️ Yes + Minimal Values Test</button>';
    $html .= '<button id="check-error-logs" class="btn btn-outline-warning">';
    $html .= '⚠️ Check Error Logs</button>';
    $html .= '</div>';
    $html .= '<div id="enrollment-flow-results" class="test-results mt-3"></div>';
    $html .= '</div>';

    // Test Users Management Section
    $html .= '<div class="test-users-section card card-forseti mb-4">';
    $html .= '<h2 class="text-white">👥 Test Users Management</h2>';
    $html .= '<p>Create test users for different NFR roles: 5 of each role + 150 additional firefighters (170 total).</p>';
    $html .= '<div class="test-controls">';
    $html .= '<button id="create-test-users" class="btn btn-success btn-large">';
    $html .= '➕ Create Test Users (170 users)</button>';
    $html .= '<button id="submit-all-firefighters" class="btn btn-primary btn-large">';
    $html .= '📋 Submit Questionnaires for All Firefighters</button>';
    $html .= '<button id="view-fill-rates" class="btn btn-info btn-large">';
    $html .= '📊 View Fill Rates</button>';
    $html .= '<button id="delete-test-users" class="btn btn-danger">';
    $html .= '🗑️ Delete All Test Users</button>';
    $html .= '</div>';
    $html .= '<div id="test-users-results" class="test-results mt-3"></div>';
    $html .= '</div>';

    // Summary stats
    $html .= '<div class="validation-stats">';
    $html .= '<div class="stat-box">';
    $html .= '<div class="stat-value">' . count($routes) . '</div>';
    $html .= '<div class="stat-label">' . $this->t('Total Routes') . '</div>';
    $html .= '</div>';
    $html .= '<div class="stat-box">';
    $html .= '<div class="stat-value">' . count($users) . '</div>';
    $html .= '<div class="stat-label">' . $this->t('Test Users') . '</div>';
    $html .= '</div>';
    $html .= '<div class="stat-box">';
    $html .= '<div class="stat-value">' . (count($routes) * count($users)) . '</div>';
    $html .= '<div class="stat-label">' . $this->t('Total Tests') . '</div>';
    $html .= '</div>';
    $html .= '</div>';

    // Test all button
    $html .= '<div class="validation-actions">';
    $html .= '<button id="test-all-routes" class="btn btn-primary btn-large">' . 
      $this->t('🧪 Run All Tests') . '</button>';
    $html .= '<button id="clear-results" class="btn btn-secondary">' . 
      $this->t('Clear Results') . '</button>';
    $html .= '</div>';

    // Routes table
    $html .= '<div class="routes-table-wrapper">';
    $html .= '<table class="validation-routes-table">';
    $html .= '<thead><tr>';
    $html .= '<th>' . $this->t('Route') . '</th>';
    $html .= '<th>' . $this->t('Path') . '</th>';
    $html .= '<th>' . $this->t('Permission') . '</th>';
    $html .= '<th>' . $this->t('Login Required') . '</th>';
    
    // User columns
    foreach ($users as $user) {
      $html .= '<th class="user-test-column">' . htmlspecialchars($user['label']) . '</th>';
    }
    
    $html .= '</tr></thead><tbody>';
    
    foreach ($routes as $route) {
      $route_id = str_replace('.', '_', $route['name']);
      
      $html .= '<tr data-route="' . htmlspecialchars($route['name']) . '">';
      $html .= '<td><code>' . htmlspecialchars($route['name']) . '</code></td>';
      $html .= '<td><code>' . htmlspecialchars($route['path']) . '</code></td>';
      $html .= '<td>' . ($route['permission'] ? '<code>' . htmlspecialchars($route['permission']) . '</code>' : '-') . '</td>';
      $html .= '<td>' . ($route['requires_login'] ? '✓' : '-') . '</td>';
      
      // Test cells for each user
      foreach ($users as $user_key => $user) {
        $cell_id = $route_id . '_' . $user_key;
        $should_have_access = $this->shouldHaveAccess($route['permission'], $route['requires_login'], $user_key, $route['name']);
        $expected_icon = $should_have_access ? '✓' : '✗';
        $expected_label = $should_have_access ? 'Expected: 200 OK' : 'Expected: 403 Forbidden';
        
        $html .= '<td class="test-cell" id="cell-' . $cell_id . '" title="' . $expected_label . '">';
        $html .= '<span class="expected-result">' . $expected_icon . '</span> ';
        $html .= '<button class="test-btn btn-mini" ';
        $html .= 'data-route="' . htmlspecialchars($route['name']) . '" ';
        $html .= 'data-path="' . htmlspecialchars($route['path']) . '" ';
        $html .= 'data-uid="' . $user['uid'] . '" ';
        $html .= 'data-user="' . htmlspecialchars($user['name']) . '" ';
        $html .= 'data-expected="' . ($should_have_access ? 'allow' : 'deny') . '">';
        $html .= $this->t('Test') . '</button>';
        $html .= '<div class="test-result" id="result-' . $cell_id . '"></div>';
        $html .= '</td>';
      }
      
      $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    $html .= '</div>';

    // Results summary
    $html .= '<div id="test-summary" class="test-summary" style="display:none;">';
    $html .= '<h3>' . $this->t('Test Results Summary') . '</h3>';
    $html .= '<div id="summary-content"></div>';
    $html .= '</div>';

    $html .= '</div>'; // .validation-dashboard

    return $html;
  }

  /**
   * Test route access for specific user.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with test results.
   */
  public function testRoute(Request $request): JsonResponse {
    $route_name = $request->query->get('route');
    $path = $request->query->get('path');
    $uid = (int) $request->query->get('uid');
    $expected = $request->query->get('expected');
    
    if (!$route_name || !$path) {
      return new JsonResponse([
        'success' => false,
        'error' => 'Missing route or path parameter',
      ], 400);
    }

    $result = $this->testRouteAccess($route_name, $path, $uid, $expected);
    
    return new JsonResponse($result);
  }

  /**
   * Test route access for a specific user.
   */
  private function testRouteAccess(string $route_name, string $path, int $uid, ?string $expected = null): array {
    $result = [
      'route' => $route_name,
      'path' => $path,
      'uid' => $uid,
      'status_code' => null,
      'access' => null,
      'error' => null,
      'expected' => $expected,
    ];

    try {
      // Load user
      if ($uid > 0) {
        $user = \Drupal\user\Entity\User::load($uid);
        if (!$user) {
          $result['error'] = 'User not found';
          return $result;
        }
      } else {
        // Anonymous user
        $user = \Drupal\user\Entity\User::getAnonymousUser();
      }

      // Switch to test user
      $this->accountSwitcher->switchTo($user);

      // Try to access the route
      try {
        $url = Url::fromRoute($route_name);
        
        // Check access
        $access = $url->access($user);
        $result['access'] = $access;
        
        if ($access) {
          // Access granted - now try to actually render the route
          try {
            // Replace dynamic parameters with test values
            $test_path = str_replace('{id}', '1', $path);
            
            // Create a subrequest to actually render the page
            $request = Request::create($test_path, 'GET');
            $response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST, FALSE);
            
            $status_code = $response->getStatusCode();
            
            if ($status_code === 200) {
              $result['status_code'] = 200;
              $result['status_text'] = 'OK - Page Rendered Successfully';
              $result['class'] = 'success';
            }
            elseif ($status_code === 302 || $status_code === 303) {
              // Redirects are valid responses (e.g., when enrollment incomplete)
              $result['status_code'] = 200;
              $result['status_text'] = 'OK - Redirected';
              $result['class'] = 'success';
            }
            elseif ($status_code === 500) {
              $result['status_code'] = 500;
              $result['status_text'] = 'Error - Page Failed to Render';
              $result['class'] = 'error';
              $result['error'] = 'HTTP 500 - Internal Server Error';
            }
            else {
              $result['status_code'] = $status_code;
              $result['status_text'] = 'HTTP ' . $status_code;
              $result['class'] = 'error';
            }
          }
          catch (\TypeError $e) {
            $result['status_code'] = 500;
            $result['status_text'] = 'TypeError: ' . substr($e->getMessage(), 0, 100);
            $result['class'] = 'error';
            $result['error'] = 'TypeError: ' . $e->getMessage();
          }
          catch (\Exception $e) {
            $result['status_code'] = 500;
            $result['status_text'] = 'Error: ' . substr($e->getMessage(), 0, 100);
            $result['class'] = 'error';
            $result['error'] = $e->getMessage();
          }
        } else {
          $result['status_code'] = 403;
          $result['status_text'] = 'Forbidden - Access Denied';
          $result['class'] = 'forbidden';
        }
      } catch (\Exception $e) {
        $result['status_code'] = 500;
        $result['status_text'] = 'Error: ' . $e->getMessage();
        $result['class'] = 'error';
        $result['error'] = $e->getMessage();
      }

      // Switch back to original user
      $this->accountSwitcher->switchBack();

    } catch (\Exception $e) {
      $result['error'] = $e->getMessage();
      $result['status_code'] = 500;
      $result['status_text'] = 'Error: ' . $e->getMessage();
      $result['class'] = 'error';
    }

    // Check if result matches expected outcome and log if unexpected
    if ($expected !== null) {
      $actual_result = ($result['status_code'] === 200) ? 'allow' : 'deny';
      
      if ($expected !== $actual_result) {
        // Get user info for logging
        $user_name = 'Unknown';
        if ($uid > 0) {
          $user = \Drupal\user\Entity\User::load($uid);
          if ($user) {
            $user_name = $user->getAccountName();
          }
        } else {
          $user_name = 'Anonymous';
        }
        
        // Log unexpected result
        $this->logger->warning('Unexpected validation result: Route @route for user @user (UID: @uid). Expected @expected but got @actual (Status: @status)', [
          '@route' => $route_name,
          '@user' => $user_name,
          '@uid' => $uid,
          '@expected' => $expected,
          '@actual' => $actual_result,
          '@status' => $result['status_code'] ?? 'N/A',
        ]);
      }
    }

    return $result;
  }

  /**
   * Test questionnaire data flow.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with test results.
   */
  public function testQuestionnaireFlow(): JsonResponse {
    $results = [
      'success' => true,
      'steps' => [],
      'errors' => [],
    ];

    try {
      // Load test user by username (more reliable than hardcoded UID)
      $username = 'firefighter_active';
      $users = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadByProperties(['name' => $username]);
      
      if (empty($users)) {
        $results['errors'][] = "Test user '$username' not found. Run 'drush updatedb' to create validation users.";
        $results['success'] = false;
        return new JsonResponse($results);
      }
      
      $user = reset($users);
      $test_uid = (int) $user->id();

      $results['steps'][] = [
        'step' => 'User Check',
        'status' => 'success',
        'message' => "Test user loaded: {$user->getAccountName()}",
      ];

      // Step 2: Generate test questionnaire data
      $questionnaire_data = $this->generateTestQuestionnaireData($test_uid);
      
      $results['steps'][] = [
        'step' => 'Data Generation',
        'status' => 'success',
        'message' => 'Generated test data for all 9 sections',
      ];

      // Step 3: Submit questionnaire data through actual forms (one for each section)
      $section_results = $this->submitAllQuestionnaireSections($test_uid, $questionnaire_data);
      
      foreach ($section_results as $section_num => $section_result) {
        $results['steps'][] = [
          'step' => "Section {$section_num} Form Submission",
          'status' => $section_result['success'] ? 'success' : 'error',
          'message' => $section_result['message'],
          'errors' => $section_result['errors'] ?? [],
        ];

        if (!$section_result['success']) {
          $results['success'] = false;
          $results['errors'][] = "Section {$section_num} failed: " . $section_result['message'];
        }
      }

      // Step 4: Verify data was saved to database correctly
      $verification = $this->verifyQuestionnaireData($test_uid);
      
      $results['steps'][] = [
        'step' => 'Data Verification',
        'status' => $verification['success'] ? 'success' : 'error',
        'message' => $verification['message'],
        'verified_fields' => $verification['fields'] ?? [],
      ];

      if (!$verification['success']) {
        $results['success'] = false;
      }

    } catch (\Exception $e) {
      $results['success'] = false;
      $results['errors'][] = $e->getMessage();
    }

    return new JsonResponse($results);
  }

  /**
   * Submit questionnaire data through actual form workflow.
   */
  /**
   * Generate test questionnaire data.
   */
  private function generateTestQuestionnaireData(int $uid): array {
    return [
      'demographics' => [
        'race_ethnicity' => ['white', 'hispanic'],
        'race_other' => '',
        'education_level' => 'bachelor',
        'height_inches' => 72,
        'weight_pounds' => 185,
      ],
      'work_history' => [
        'departments' => [
          [
            'department_name' => 'Test City Fire Department',
            'department_fdid' => '12345',
            'department_state' => 'CA',
            'department_city' => 'Test City',
            'start_date' => '2010-06-01',
            'end_date' => '',
            'is_current' => 1,
            'job_titles' => [
              [
                'job_title' => 'Firefighter',
                'employment_type' => 'career',
                'start_date' => '2010-06-01',
                'end_date' => '2015-05-31',
                'responded_to_incidents' => 1,
              ],
              [
                'job_title' => 'Fire Captain',
                'employment_type' => 'career',
                'start_date' => '2015-06-01',
                'end_date' => '',
                'responded_to_incidents' => 1,
              ],
            ],
          ],
        ],
      ],
      'exposure' => [
        'afff_exposure' => 1,
        'afff_years' => 8,
        'afff_frequency' => 'monthly',
        'diesel_exposure' => 1,
        'diesel_years' => 14,
        'major_incidents' => [
          [
            'incident_type' => 'Hazmat',
            'incident_date' => '2015-03-15',
            'exposure_duration' => '6 hours',
          ],
        ],
      ],
      'military' => [
        'military_service' => 1,
        'military_branch' => 'Army',
        'start_date' => '2006-01-01',
        'end_date' => '2010-01-01',
        'military_specialty' => 'Combat Engineer',
        'deployment_locations' => ['Iraq', 'Afghanistan'],
        'exposures' => ['burn_pits', 'diesel'],
      ],
      'other_employment' => [
        'jobs' => [
          [
            'employer' => 'Construction Company',
            'job_title' => 'Carpenter',
            'start_date' => '2004-01-01',
            'end_date' => '2006-01-01',
            'exposures' => ['asbestos', 'wood_dust'],
          ],
        ],
      ],
      'ppe' => [
        'scba_usage' => 'always',
        'glove_usage' => 'always',
        'hood_usage' => 'usually',
        'turnout_cleaning' => 'after_every_fire',
      ],
      'decontamination' => [
        'field_decon' => 1,
        'station_decon' => 1,
        'shower_after_fire' => 'always',
        'gear_drying' => 'dedicated_area',
      ],
      'health' => [
        'cancer_diagnosis' => 0,
        'cancer_details' => [],
        'family_history' => [
          [
            'relation' => 'father',
            'cancer_type' => 'lung',
            'diagnosis_age' => 65,
          ],
        ],
      ],
      'lifestyle' => [
        'smoking_status' => 'never',
        'alcohol_use' => 'occasional',
        'exercise_frequency' => '3-5_per_week',
      ],
    ];
  }

  /**
   * Save test questionnaire data to database.
   */
  /**
   * Verify questionnaire data in database.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with verification results.
   */
  public function verifyQuestionnaireDatabase(): JsonResponse {
    $test_uid = 2;
    $results = $this->verifyQuestionnaireData($test_uid);
    return new JsonResponse($results);
  }

  /**
   * Verify questionnaire data for a user.
   */
  private function verifyQuestionnaireData(int $uid): array {
    try {
      $database = \Drupal::database();

      $record = $database->select('nfr_questionnaire', 'q')
        ->fields('q')
        ->condition('uid', $uid)
        ->execute()
        ->fetchAssoc();

      if (!$record) {
        return [
          'success' => false,
          'message' => "No questionnaire data found for UID: $uid",
        ];
      }

      $verified_fields = [];
      
      // Verify demographics
      if ($record['race_ethnicity']) {
        $race_data = json_decode($record['race_ethnicity'], TRUE);
        $verified_fields['race_ethnicity'] = [
          'status' => 'success',
          'value' => $race_data,
        ];
      }

      if ($record['height_inches']) {
        $verified_fields['height_inches'] = [
          'status' => 'success',
          'value' => $record['height_inches'] . ' inches',
        ];
      }

      if ($record['weight_pounds']) {
        $verified_fields['weight_pounds'] = [
          'status' => 'success',
          'value' => $record['weight_pounds'] . ' lbs',
        ];
      }

      // Verify military service
      if ($record['military_service']) {
        $verified_fields['military_service'] = [
          'status' => 'success',
          'value' => "Branch: {$record['military_branch']}, Years: {$record['military_years']}",
        ];
      }

      // Verify PPE practices
      if ($record['ppe_practices']) {
        $ppe_data = json_decode($record['ppe_practices'], TRUE);
        $verified_fields['ppe_practices'] = [
          'status' => 'success',
          'value' => count($ppe_data) . ' practices recorded',
        ];
      }

      // Verify decontamination
      if ($record['decon_practices']) {
        $decon_data = json_decode($record['decon_practices'], TRUE);
        $verified_fields['decon_practices'] = [
          'status' => 'success',
          'value' => count($decon_data) . ' practices recorded',
        ];
      }

      // Verify health info
      $verified_fields['cancer_diagnosis'] = [
        'status' => 'success',
        'value' => $record['cancer_diagnosis'] ? 'Yes' : 'No',
      ];

      if ($record['family_cancer_history']) {
        $family_data = json_decode($record['family_cancer_history'], TRUE);
        $verified_fields['family_cancer_history'] = [
          'status' => 'success',
          'value' => count($family_data) . ' family members recorded',
        ];
      }

      // Verify lifestyle
      if ($record['smoking_history']) {
        $smoking_data = json_decode($record['smoking_history'], TRUE);
        $verified_fields['smoking_status'] = [
          'status' => 'success',
          'value' => $smoking_data['smoking_status'] ?? 'Unknown',
        ];
      }

      $verified_fields['alcohol_use'] = [
        'status' => 'success',
        'value' => $record['alcohol_use'] ?? 'Not specified',
      ];

      // Verify completion
      $verified_fields['questionnaire_completed'] = [
        'status' => $record['questionnaire_completed'] ? 'success' : 'warning',
        'value' => $record['questionnaire_completed'] ? 'Completed' : 'Incomplete',
      ];

      return [
        'success' => true,
        'message' => 'Verified ' . count($verified_fields) . ' fields in database',
        'fields' => $verified_fields,
        'record' => $record,
      ];

    }
    catch (\Exception $e) {
      return [
        'success' => false,
        'message' => 'Verification error: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Clear test questionnaire data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with clear results.
   */
  public function clearTestData(): JsonResponse {
    try {
      $test_uid = 2;
      $database = \Drupal::database();

      $deleted = $database->delete('nfr_questionnaire')
        ->condition('uid', $test_uid)
        ->execute();

      return new JsonResponse([
        'success' => true,
        'message' => "Cleared test data for UID: $test_uid",
        'rows_deleted' => $deleted,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => false,
        'message' => 'Error clearing data: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Test full enrollment flow with random data using actual form submissions.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with test results.
   */
  public function testFullEnrollmentFlow(): JsonResponse {
    $results = [
      'success' => true,
      'steps' => [],
      'errors' => [],
      'warnings' => [],
    ];

    try {
      $test_uid = 2; // firefighter_active test user
      
      // Step 1: Generate random user profile data
      $profile_data = $this->generateRandomProfileData();
      $results['steps'][] = [
        'step' => 'Profile Data Generation',
        'status' => 'success',
        'message' => 'Generated random profile data',
        'data' => $profile_data,
      ];

      // Step 2: Submit profile through actual form
      $profile_result = $this->submitProfileForm($test_uid, $profile_data);
      $results['steps'][] = [
        'step' => 'Profile Form Submission',
        'status' => $profile_result['success'] ? 'success' : 'error',
        'message' => $profile_result['message'],
        'errors' => $profile_result['errors'] ?? [],
      ];

      if (!$profile_result['success']) {
        $results['success'] = false;
        $results['errors'][] = 'Profile form submission failed: ' . $profile_result['message'];
      }

      // Step 3: Generate random questionnaire data
      $questionnaire_data = $this->generateRandomQuestionnaireData($test_uid);
      $results['steps'][] = [
        'step' => 'Questionnaire Data Generation',
        'status' => 'success',
        'message' => 'Generated random questionnaire data for all 9 sections',
      ];

      // Step 4: Submit all 9 questionnaire sections through actual forms
      $section_results = $this->submitAllQuestionnaireSections($test_uid, $questionnaire_data);
      
      foreach ($section_results as $section_num => $section_result) {
        $results['steps'][] = [
          'step' => "Section {$section_num} Form Submission",
          'status' => $section_result['success'] ? 'success' : 'error',
          'message' => $section_result['message'],
          'errors' => $section_result['errors'] ?? [],
        ];

        if (!$section_result['success']) {
          $results['success'] = false;
          $results['errors'][] = "Section {$section_num} failed: " . $section_result['message'];
        }
      }

      // Step 5: Check for errors in dblog
      $log_check = $this->checkErrorLogs();
      $results['steps'][] = [
        'step' => 'Error Log Check',
        'status' => $log_check['has_errors'] ? 'warning' : 'success',
        'message' => $log_check['message'],
        'error_count' => $log_check['error_count'],
        'recent_errors' => $log_check['recent_errors'] ?? [],
      ];

      if ($log_check['has_errors']) {
        $results['warnings'][] = 'Found ' . $log_check['error_count'] . ' recent errors in system logs';
      }

      // Step 6: Verify both profile and questionnaire data
      $profile_verify = $this->verifyProfileData($test_uid);
      $questionnaire_verify = $this->verifyQuestionnaireData($test_uid);
      
      $results['steps'][] = [
        'step' => 'Data Verification',
        'status' => ($profile_verify['success'] && $questionnaire_verify['success']) ? 'success' : 'error',
        'message' => 'Profile: ' . $profile_verify['message'] . ' | Questionnaire: ' . $questionnaire_verify['message'],
      ];

    } catch (\Exception $e) {
      $results['success'] = false;
      $results['errors'][] = $e->getMessage();
    }

    return new JsonResponse($results);
  }

  /**
   * Create test users for NFR roles.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with created users.
   */
  public function createTestUsers(): JsonResponse {
    $results = [
      'success' => true,
      'users_created' => [],
      'errors' => [],
    ];

    try {
      // First create the specific validation test users with exact UIDs
      $validation_users = [
        2 => ['username' => 'firefighter_active', 'role' => 'firefighter', 'label' => 'Firefighter (Active)'],
        3 => ['username' => 'firefighter_retired', 'role' => 'firefighter', 'label' => 'Firefighter (Retired)'],
        4 => ['username' => 'nfr_admin', 'role' => 'nfr_administrator', 'label' => 'NFR Administrator'],
        5 => ['username' => 'nfr_researcher', 'role' => 'nfr_researcher', 'label' => 'NFR Researcher'],
        6 => ['username' => 'dept_admin', 'role' => 'fire_dept_admin', 'label' => 'Fire Dept Admin'],
      ];

      foreach ($validation_users as $uid => $user_data) {
        $user = $this->createValidationUser($uid, $user_data['username'], $user_data['role'], $user_data['label']);
        $results['users_created'][] = [
          'uid' => $user->id(),
          'username' => $user_data['username'],
          'role' => $user_data['label'],
          'email' => $user->getEmail(),
          'purpose' => 'validation_test',
        ];
      }

      // Then create additional users for bulk testing
      $roles = [
        'nfr_administrator' => 'NFR Administrator',
        'nfr_researcher' => 'NFR Researcher',
        'firefighter' => 'Firefighter',
        'fire_dept_admin' => 'Fire Department Admin',
      ];

      // Create 5 users for each role (skip if validation user exists)
      foreach ($roles as $role_id => $role_label) {
        for ($i = 1; $i <= 5; $i++) {
          $username = strtolower(str_replace(' ', '_', $role_label)) . '_' . $i;
          // Skip if this matches a validation user
          if (in_array($username, array_column($validation_users, 'username'))) {
            continue;
          }
          $user = $this->createUser($username, $role_id, $role_label);
          $results['users_created'][] = [
            'uid' => $user->id(),
            'username' => $username,
            'role' => $role_label,
            'email' => $user->getEmail(),
            'purpose' => 'bulk_test',
          ];
        }
      }

      // Create 150 additional firefighters
      for ($i = 7; $i <= 155; $i++) {
        $username = 'firefighter_' . $i;
        $user = $this->createUser($username, 'firefighter', 'Firefighter');
        $results['users_created'][] = [
          'uid' => $user->id(),
          'username' => $username,
          'role' => 'Firefighter',
          'email' => $user->getEmail(),
          'purpose' => 'bulk_test',
        ];
      }

      $results['total_created'] = count($results['users_created']);
      $results['summary'] = [
        'validation_users' => 5,
        'nfr_administrators' => 5,
        'nfr_researchers' => 5,
        'fire_dept_admins' => 5,
        'firefighters' => 149,
        'total' => count($results['users_created']),
      ];

    } catch (\Exception $e) {
      $results['success'] = false;
      $results['errors'][] = $e->getMessage();
    }

    return new JsonResponse($results);
  }

  /**
   * Create or update a validation test user (Drupal best practice - no forced UIDs).
   */
  private function createValidationUser(int $target_uid, string $username, string $role_id, string $role_label): \Drupal\user\Entity\User {
    // Check if user already exists by username (proper Drupal practice)
    $existing_by_name = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $username]);

    if (!empty($existing_by_name)) {
      $user = reset($existing_by_name);
      // Update role if needed
      if (!$user->hasRole($role_id)) {
        $user->addRole($role_id);
        $user->save();
      }
      return $user;
    }

    // Generate name parts
    $name_map = [
      'firefighter_active' => ['John', 'Smith'],
      'firefighter_retired' => ['Jane', 'Doe'],
      'nfr_admin' => ['Admin', 'User'],
      'nfr_researcher' => ['Research', 'Analyst'],
      'dept_admin' => ['Fire', 'Chief'],
    ];

    $names = $name_map[$username] ?? ['Test', 'User'];

    // Create user using Drupal entity system (let Drupal assign UID)
    $user = \Drupal\user\Entity\User::create([
      'name' => $username,
      'mail' => $username . '@stlouisintegration.com',
      'pass' => 'TestPassword123!',
      'status' => 1,
      'field_first_name' => $names[0],
      'field_last_name' => $names[1],
    ]);

    $user->addRole($role_id);
    $user->save();

    return $user;
  }

  /**
   * Create a single test user.
   */
  private function createUser(string $username, string $role_id, string $role_label): \Drupal\user\Entity\User {
    // Check if user already exists
    $existing = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $username]);

    if (!empty($existing)) {
      return reset($existing);
    }

    // Generate realistic name
    $first_names = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Lisa', 'James', 'Mary', 
                    'William', 'Patricia', 'Thomas', 'Jennifer', 'Charles', 'Linda', 'Daniel', 'Elizabeth'];
    $last_names = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 
                   'Martinez', 'Hernandez', 'Lopez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson'];

    $first_name = $first_names[array_rand($first_names)];
    $last_name = $last_names[array_rand($last_names)];

    // Create user
    $user = \Drupal\user\Entity\User::create([
      'name' => $username,
      'mail' => $username . '@stlouisintegration.com',
      'pass' => 'TestPassword123!',
      'status' => 1,
      'field_first_name' => $first_name,
      'field_last_name' => $last_name,
    ]);

    $user->addRole($role_id);
    $user->save();

    return $user;
  }

  /**
   * Delete all test users.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with deletion results.
   */
  public function deleteTestUsers(): JsonResponse {
    $results = [
      'success' => true,
      'users_deleted' => 0,
      'errors' => [],
    ];

    try {
      // Find all users with test email domain
      $users = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadByProperties(['mail' => '%@test-nfr.org']);

      // Also find by username pattern
      $database = \Drupal::database();
      $uids = $database->query("
        SELECT uid FROM {users_field_data} 
        WHERE mail LIKE '%@test-nfr.org' 
        OR name LIKE 'firefighter_%'
        OR name LIKE 'nfr_administrator_%'
        OR name LIKE 'nfr_researcher_%'
        OR name LIKE 'fire_department_admin_%'
      ")->fetchCol();

      foreach ($uids as $uid) {
        if ($uid > 2) { // Don't delete admin or test user
          $user = \Drupal\user\Entity\User::load($uid);
          if ($user) {
            $user->delete();
            $results['users_deleted']++;
          }
        }
      }

    } catch (\Exception $e) {
      $results['success'] = false;
      $results['errors'][] = $e->getMessage();
    }

    return new JsonResponse($results);
  }

  /**
   * Submit questionnaires for all firefighter users.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with submission results.
   */
  public function submitAllFirefighterQuestionnaires(): JsonResponse {
    $results = [
      'success' => true,
      'total_firefighters' => 0,
      'successful_submissions' => 0,
      'failed_submissions' => 0,
      'user_results' => [],
      'errors' => [],
    ];

    try {
      // Get all firefighter users
      $database = \Drupal::database();
      $firefighter_uids = $database->query("
        SELECT DISTINCT u.uid 
        FROM {users_field_data} u
        JOIN {user__roles} ur ON u.uid = ur.entity_id
        WHERE ur.roles_target_id = 'firefighter'
        AND u.uid > 2
        ORDER BY u.uid
      ")->fetchCol();

      $results['total_firefighters'] = count($firefighter_uids);

      foreach ($firefighter_uids as $uid) {
        $uid = (int) $uid; // Cast to integer
        $user = \Drupal\user\Entity\User::load($uid);
        if (!$user) {
          continue;
        }

        $username = $user->getAccountName();
        
        try {
          // Generate random profile data
          $profile_data = $this->generateRandomProfileData();
          
          // Submit profile
          $profile_result = $this->submitProfileForm($uid, $profile_data);
          
          if (!$profile_result['success']) {
            $results['failed_submissions']++;
            $results['user_results'][] = [
              'uid' => $uid,
              'username' => $username,
              'success' => false,
              'error' => 'Profile submission failed: ' . $profile_result['message'],
            ];
            continue;
          }

          // Generate random questionnaire data
          $questionnaire_data = $this->generateRandomQuestionnaireData($uid);
          
          // Submit all sections
          $section_results = $this->submitAllQuestionnaireSections($uid, $questionnaire_data);
          
          // Check if all sections succeeded
          $all_sections_passed = true;
          foreach ($section_results as $section_result) {
            if (!$section_result['success']) {
              $all_sections_passed = false;
              break;
            }
          }

          if ($all_sections_passed) {
            $results['successful_submissions']++;
            $results['user_results'][] = [
              'uid' => $uid,
              'username' => $username,
              'success' => true,
              'sections_completed' => 9,
            ];
          } else {
            $results['failed_submissions']++;
            $results['user_results'][] = [
              'uid' => $uid,
              'username' => $username,
              'success' => false,
              'error' => 'One or more sections failed validation',
            ];
          }

        } catch (\Exception $e) {
          $results['failed_submissions']++;
          $results['user_results'][] = [
            'uid' => $uid,
            'username' => $username,
            'success' => false,
            'error' => $e->getMessage(),
          ];
        }
      }

      $results['success'] = $results['failed_submissions'] === 0;
      $results['success_rate'] = $results['total_firefighters'] > 0 
        ? round(($results['successful_submissions'] / $results['total_firefighters']) * 100, 2)
        : 0;

    } catch (\Exception $e) {
      $results['success'] = false;
      $results['errors'][] = $e->getMessage();
    }

    return new JsonResponse($results);
  }

  /**
   * Test enrollment with maximum values.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with test results.
   */
  public function testMaxValuesFlow(): JsonResponse {
    return $this->runEnrollmentFlowTest('max', 'Maximum Values Test (Yes to everything, max values)');
  }

  /**
   * Test enrollment with minimum values.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with test results.
   */
  public function testMinValuesFlow(): JsonResponse {
    return $this->runEnrollmentFlowTest('min', 'Minimum Values Test (No to everything, min values)');
  }

  /**
   * Test enrollment with yes answers but minimal values.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with test results.
   */
  public function testYesMinimalFlow(): JsonResponse {
    return $this->runEnrollmentFlowTest('yes_minimal', 'Yes + Minimal Values Test');
  }

  /**
   * Run enrollment flow test with specific data type.
   */
  private function runEnrollmentFlowTest(string $dataType, string $testName): JsonResponse {
    $results = [
      'success' => true,
      'test_type' => $testName,
      'steps' => [],
      'errors' => [],
      'warnings' => [],
    ];

    try {
      $test_uid = 2;
      
      // Generate profile data based on type
      $profile_data = $this->generateProfileData($dataType);
      $results['steps'][] = [
        'step' => 'Profile Data Generation',
        'status' => 'success',
        'message' => "Generated {$dataType} profile data",
        'data' => $profile_data,
      ];

      // Submit profile
      $profile_result = $this->submitProfileForm($test_uid, $profile_data);
      $results['steps'][] = [
        'step' => 'Profile Form Submission',
        'status' => $profile_result['success'] ? 'success' : 'error',
        'message' => $profile_result['message'],
        'errors' => $profile_result['errors'] ?? [],
      ];

      if (!$profile_result['success']) {
        $results['success'] = false;
        $results['errors'][] = 'Profile form submission failed: ' . $profile_result['message'];
      }

      // Generate questionnaire data
      $questionnaire_data = $this->generateQuestionnaireData($test_uid, $dataType);
      $results['steps'][] = [
        'step' => 'Questionnaire Data Generation',
        'status' => 'success',
        'message' => "Generated {$dataType} questionnaire data for all 9 sections",
      ];

      // Submit all sections
      $section_results = $this->submitAllQuestionnaireSections($test_uid, $questionnaire_data);
      
      foreach ($section_results as $section_num => $section_result) {
        $results['steps'][] = [
          'step' => "Section {$section_num} Form Submission",
          'status' => $section_result['success'] ? 'success' : 'error',
          'message' => $section_result['message'],
          'errors' => $section_result['errors'] ?? [],
        ];

        if (!$section_result['success']) {
          $results['success'] = false;
          $results['errors'][] = "Section {$section_num} failed: " . $section_result['message'];
        }
      }

      // Data verification
      $profile_verify = $this->verifyProfileData($test_uid);
      $questionnaire_verify = $this->verifyQuestionnaireData($test_uid);
      
      $results['steps'][] = [
        'step' => 'Data Verification',
        'status' => ($profile_verify['success'] && $questionnaire_verify['success']) ? 'success' : 'error',
        'message' => 'Profile: ' . $profile_verify['message'] . ' | Questionnaire: ' . $questionnaire_verify['message'],
      ];

    } catch (\Exception $e) {
      $results['success'] = false;
      $results['errors'][] = $e->getMessage();
    }

    return new JsonResponse($results);
  }

  /**
   * Generate profile data based on type.
   */
  private function generateProfileData(string $type): array {
    $base_data = [
      'first_name' => 'Test',
      'middle_name' => 'T',
      'last_name' => 'User',
      'date_of_birth' => '1980-01-15',
      'sex' => 'male',
      'ssn_last_4' => '1234',
      'country_of_birth' => 'USA',
      'state_of_birth' => 'CA',
      'city_of_birth' => 'TestCity',
      'address_line1' => '123 Test St',
      'city' => 'TestCity',
      'state' => 'CA',
      'zip_code' => '12345',
      'mobile_phone' => '(555) 123-4567',
      'current_work_status' => 'active',
    ];

    if ($type === 'max') {
      $base_data['date_of_birth'] = '1960-01-01'; // Oldest allowed
    }
    elseif ($type === 'min') {
      $base_data['date_of_birth'] = '2005-01-01'; // Youngest allowed
      $base_data['current_work_status'] = 'retired';
    }

    return $base_data;
  }

  /**
   * Generate questionnaire data based on type.
   */
  private function generateQuestionnaireData(int $uid, string $type): array {
    if ($type === 'max') {
      return $this->generateMaxValuesData();
    }
    elseif ($type === 'min') {
      return $this->generateMinValuesData();
    }
    elseif ($type === 'yes_minimal') {
      return $this->generateYesMinimalData();
    }
    else {
      return $this->generateRandomQuestionnaireData($uid);
    }
  }

  /**
   * Generate maximum values data (yes to everything, max values).
   */
  private function generateMaxValuesData(): array {
    return [
      'demographics' => [
        'race_ethnicity' => ['white', 'black', 'asian', 'hispanic', 'american_indian'],
        'education_level' => 'graduate',
        'marital_status' => 'married',
        'height_inches' => 78,
        'weight_pounds' => 260,
      ],
      'work_history' => [
        'departments' => [
          [
            'department_name' => 'Maximum Test Fire Department',
            'department_fdid' => '99999',
            'department_state' => 'CA',
            'department_city' => 'Los Angeles',
            'start_date' => '1980-01-01',
            'end_date' => '',
            'is_current' => 1,
            'job_titles' => [
              [
                'job_title' => 'Fire Chief',
                'employment_type' => 'career',
                'start_date' => '1980-01-01',
                'end_date' => '',
                'responded_to_incidents' => 1,
              ],
            ],
          ],
        ],
      ],
      'exposure' => [
        'afff_used' => 'yes',
        'afff_years' => 20,
        'afff_frequency' => 'weekly',
        'diesel_exhaust' => 'regularly',
        'diesel_years' => 25,
        'major_incidents' => 'yes',
      ],
      'military' => [
        'served' => 'yes',
        'branch' => 'marines',
        'start_date' => '1978-01-01',
        'end_date' => '1980-01-01',
        'military_specialty' => 'Infantry',
        'deployment_locations' => [],
        'exposures' => [],
      ],
      'other_employment' => [
        'had_other_jobs' => 'yes',
        'jobs' => [
          [
            'occupation' => 'Construction Worker',
            'industry' => 'construction',
            'start_year' => 1975,
            'end_year' => 1980,
            'exposures' => 'asbestos,silica,diesel',
            'exposures_other' => 'Heavy machinery exhaust',
          ],
          [
            'occupation' => 'Paramedic',
            'industry' => 'healthcare',
            'start_year' => 1981,
            'end_year' => 1985,
            'exposures' => 'chemicals,radiation',
            'exposures_other' => '',
          ],
        ],
      ],
      'ppe' => [
        'scba_usage' => 'always',
        'glove_usage' => 'always',
        'hood_usage' => 'always',
        'turnout_cleaning' => 'after_every_fire',
      ],
      'decontamination' => [
        'field_decon' => 1,
        'station_decon' => 1,
        'shower_after_fire' => 'always',
        'gear_drying' => 'dedicated_area',
      ],
      'health' => [
        'cancer_diagnosis' => 0,
        'cancer_details' => [],
        'family_history' => [],
      ],
      'lifestyle' => [
        'smoking_status' => 'current',
        'alcohol_frequency' => '5_plus_per_week',
        'physical_activity_days' => 7,
      ],
    ];
  }

  /**
   * Generate minimum values data (no to everything, min values).
   */
  private function generateMinValuesData(): array {
    return [
      'demographics' => [
        'race_ethnicity' => ['white'],
        'education_level' => 'hs_ged',
        'marital_status' => 'single',
        'height_inches' => 60,
        'weight_pounds' => 140,
      ],
      'work_history' => [
        'departments' => [
          [
            'department_name' => 'Minimal Test Fire Department',
            'department_fdid' => '10000',
            'department_state' => 'CA',
            'department_city' => 'TestCity',
            'start_date' => '2020-01-01',
            'end_date' => '',
            'is_current' => 1,
            'job_titles' => [
              [
                'job_title' => 'Firefighter',
                'employment_type' => 'volunteer',
                'start_date' => '2020-01-01',
                'end_date' => '',
                'responded_to_incidents' => 1,
              ],
            ],
          ],
        ],
      ],
      'exposure' => [
        'afff_used' => 'no',
        'afff_years' => 0,
        'afff_frequency' => 'never',
        'diesel_exhaust' => 'never',
        'diesel_years' => 0,
        'major_incidents' => 'no',
      ],
      'military' => [
        'served' => 'no',
        'branch' => '',
        'start_date' => '',
        'end_date' => '',
        'military_specialty' => '',
        'deployment_locations' => [],
        'exposures' => [],
      ],
      'other_employment' => [
        'had_other_jobs' => 'yes',
        'jobs' => [
          [
            'occupation' => 'Retail Worker',
            'industry' => 'retail',
            'start_year' => 2018,
            'end_year' => 2020,
            'exposures' => '',
            'exposures_other' => '',
          ],
        ],
      ],
      'ppe' => [
        'scba_usage' => 'rarely',
        'glove_usage' => 'sometimes',
        'hood_usage' => 'sometimes',
        'turnout_cleaning' => 'monthly',
      ],
      'decontamination' => [
        'field_decon' => 0,
        'station_decon' => 0,
        'shower_after_fire' => 'sometimes',
        'gear_drying' => 'outside',
      ],
      'health' => [
        'cancer_diagnosis' => 0,
        'cancer_details' => [],
        'family_history' => [],
      ],
      'lifestyle' => [
        'smoking_status' => 'never',
        'alcohol_frequency' => 'never',
        'physical_activity_days' => 0,
      ],
    ];
  }

  /**
   * Generate yes + minimal values data.
   */
  private function generateYesMinimalData(): array {
    return [
      'demographics' => [
        'race_ethnicity' => ['white'],
        'education_level' => 'hs_ged',
        'marital_status' => 'single',
        'height_inches' => 60,
        'weight_pounds' => 140,
      ],
      'work_history' => [
        'departments' => [
          [
            'department_name' => 'Yes Minimal Fire Department',
            'department_fdid' => '50000',
            'department_state' => 'CA',
            'department_city' => 'TestCity',
            'start_date' => '2020-01-01',
            'end_date' => '',
            'is_current' => 1,
            'job_titles' => [
              [
                'job_title' => 'Firefighter',
                'employment_type' => 'career',
                'start_date' => '2020-01-01',
                'end_date' => '',
                'responded_to_incidents' => 1,
              ],
            ],
          ],
        ],
      ],
      'exposure' => [
        'afff_used' => 'yes',
        'afff_years' => 1,
        'afff_frequency' => 'rarely',
        'diesel_exhaust' => 'rarely',
        'diesel_years' => 1,
        'major_incidents' => 'yes',
      ],
      'military' => [
        'served' => 'yes',
        'branch' => 'army',
        'start_date' => '2018-01-01',
        'end_date' => '2020-01-01',
        'military_specialty' => 'Infantry',
        'deployment_locations' => [],
        'exposures' => [],
      ],
      'other_employment' => [
        'had_other_jobs' => 'yes',
        'jobs' => [
          [
            'occupation' => 'Military',
            'industry' => 'military',
            'start_year' => 2016,
            'end_year' => 2018,
            'exposures' => 'diesel,chemicals',
            'exposures_other' => 'Jet fuel',
          ],
        ],
      ],
      'ppe' => [
        'scba_usage' => 'sometimes',
        'glove_usage' => 'sometimes',
        'hood_usage' => 'sometimes',
        'turnout_cleaning' => 'weekly',
      ],
      'decontamination' => [
        'field_decon' => 1,
        'station_decon' => 1,
        'shower_after_fire' => 'sometimes',
        'gear_drying' => 'living_area',
      ],
      'health' => [
        'cancer_diagnosis' => 0,
        'cancer_details' => [],
        'family_history' => [],
      ],
      'lifestyle' => [
        'smoking_status' => 'former',
        'alcohol_frequency' => 'less_than_monthly',
        'physical_activity_days' => 1,
      ],
    ];
  }

  /**
   * Generate random profile data.
   */
  private function generateRandomProfileData(): array {
    $first_names = ['John', 'Michael', 'David', 'James', 'Robert', 'William', 'Sarah', 'Jennifer', 'Maria', 'Lisa'];
    $last_names = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
    $states = ['CA', 'TX', 'FL', 'NY', 'PA', 'IL', 'OH', 'GA', 'NC', 'MI'];
    $cities = ['Springfield', 'Franklin', 'Clinton', 'Madison', 'Georgetown', 'Arlington', 'Salem', 'Fairview', 'Bristol', 'Riverside'];
    
    return [
      'first_name' => $first_names[array_rand($first_names)],
      'middle_name' => chr(65 + rand(0, 25)),
      'last_name' => $last_names[array_rand($last_names)],
      'date_of_birth' => sprintf('%04d-%02d-%02d', rand(1960, 1995), rand(1, 12), rand(1, 28)),
      'sex' => ['male', 'female'][rand(0, 1)],
      'ssn_last_4' => sprintf('%04d', rand(1000, 9999)),
      'country_of_birth' => 'USA',
      'state_of_birth' => $states[array_rand($states)],
      'city_of_birth' => $cities[array_rand($cities)],
      'address_line1' => rand(100, 9999) . ' Main Street',
      'city' => $cities[array_rand($cities)],
      'state' => $states[array_rand($states)],
      'zip_code' => sprintf('%05d', rand(10000, 99999)),
      'mobile_phone' => sprintf('(%03d) %03d-%04d', rand(200, 999), rand(200, 999), rand(1000, 9999)),
      'current_work_status' => ['active', 'retired'][rand(0, 1)],
    ];
  }

  /**
   * Generate random questionnaire data.
   */
  private function generateRandomQuestionnaireData(int $uid): array {
    $races = ['white', 'black', 'asian', 'hispanic', 'american_indian'];
    $education = ['hs_ged', 'some_college', 'associate', 'bachelor', 'graduate'];
    $employment_types = ['career', 'volunteer', 'paid-on-call'];
    $branches = ['Army', 'Navy', 'Air Force', 'Marines', 'Coast Guard'];
    
    // Randomly select 1-3 races
    $num_races = rand(1, 3);
    $selected_races = [];
    for ($i = 0; $i < $num_races; $i++) {
      $selected_races[] = $races[array_rand($races)];
    }
    
    // 30% chance of having other employment, then 30% chance of second job
    $other_jobs = [];
    if (rand(1, 100) <= 30) {
      $occupations = ['Construction Worker', 'Paramedic', 'Police Officer', 'Military', 'Retail Manager', 'Factory Worker', 'Mechanic', 'Electrician'];
      $industries = ['construction', 'healthcare', 'law_enforcement', 'military', 'retail', 'manufacturing', 'automotive', 'trades'];
      $exposures_list = ['asbestos', 'silica', 'diesel', 'chemicals', 'radiation', 'lead', 'solvents'];
      
      // First job (100% if we're in this block)
      $start_year = rand(1990, 2010);
      $end_year = rand($start_year + 1, 2020);
      $occupation = $occupations[array_rand($occupations)];
      $industry = $industries[array_rand($industries)];
      
      // 50% chance of having exposures
      $exposures = '';
      $exposures_other = '';
      if (rand(0, 1)) {
        $num_exposures = rand(1, 3);
        $selected_exposures = [];
        for ($j = 0; $j < $num_exposures; $j++) {
          $selected_exposures[] = $exposures_list[array_rand($exposures_list)];
        }
        $exposures = implode(',', array_unique($selected_exposures));
        if (rand(0, 1)) {
          $exposures_other = 'Other chemical exposure';
        }
      }
      
      $other_jobs[] = [
        'occupation' => $occupation,
        'industry' => $industry,
        'start_year' => $start_year,
        'end_year' => $end_year,
        'exposures' => $exposures,
        'exposures_other' => $exposures_other,
      ];
      
      // 30% chance of second job
      if (rand(1, 100) <= 30) {
        $start_year = rand(1990, 2010);
        $end_year = rand($start_year + 1, 2020);
        $occupation = $occupations[array_rand($occupations)];
        $industry = $industries[array_rand($industries)];
        
        $exposures = '';
        $exposures_other = '';
        if (rand(0, 1)) {
          $num_exposures = rand(1, 3);
          $selected_exposures = [];
          for ($j = 0; $j < $num_exposures; $j++) {
            $selected_exposures[] = $exposures_list[array_rand($exposures_list)];
          }
          $exposures = implode(',', array_unique($selected_exposures));
          if (rand(0, 1)) {
            $exposures_other = 'Other exposure detail';
          }
        }
        
        $other_jobs[] = [
          'occupation' => $occupation,
          'industry' => $industry,
          'start_year' => $start_year,
          'end_year' => $end_year,
          'exposures' => $exposures,
          'exposures_other' => $exposures_other,
        ];
      }
    }
    
    // Work history: First department is always added, 30% chance of second department
    $departments = [
      [
        'department_name' => 'Test Fire Department ' . rand(1, 999),
        'department_fdid' => sprintf('%05d', rand(10000, 99999)),
        'department_state' => ['CA', 'TX', 'NY', 'FL'][rand(0, 3)],
        'department_city' => 'TestCity',
        'start_date' => sprintf('%04d-%02d-01', rand(2005, 2015), rand(1, 12)),
        'end_date' => '',
        'is_current' => 1,
        'job_titles' => [
          [
            'job_title' => 'Firefighter',
            'employment_type' => $employment_types[array_rand($employment_types)],
            'start_date' => sprintf('%04d-%02d-01', rand(2005, 2015), rand(1, 12)),
            'end_date' => '',
            'responded_to_incidents' => 1,
          ],
        ],
      ],
    ];
    
    if (rand(1, 100) <= 30) {
      $departments[] = [
        'department_name' => 'Previous Fire Department ' . rand(1, 999),
        'department_fdid' => sprintf('%05d', rand(10000, 99999)),
        'department_state' => ['CA', 'TX', 'NY', 'FL'][rand(0, 3)],
        'department_city' => 'OldCity',
        'start_date' => sprintf('%04d-%02d-01', rand(1995, 2005), rand(1, 12)),
        'end_date' => sprintf('%04d-%02d-01', rand(2005, 2010), rand(1, 12)),
        'is_current' => 0,
        'job_titles' => [
          [
            'job_title' => 'Firefighter',
            'employment_type' => $employment_types[array_rand($employment_types)],
            'start_date' => sprintf('%04d-%02d-01', rand(1995, 2005), rand(1, 12)),
            'end_date' => sprintf('%04d-%02d-01', rand(2005, 2010), rand(1, 12)),
            'responded_to_incidents' => 1,
          ],
        ],
      ];
    }
    
    // Major incidents: 30% chance of having incidents if afff_used is yes
    $major_incidents_data = [];
    $has_major_incidents = rand(0, 1);
    if ($has_major_incidents && rand(1, 100) <= 30) {
      $incident_types = ['Structure Fire', 'Wildland Fire', 'Chemical Spill', 'HAZMAT Response', 'Vehicle Fire'];
      $durations = ['< 1 hour', '1-4 hours', '4-8 hours', '8-24 hours', '> 24 hours'];
      
      // First incident
      $major_incidents_data[] = [
        'description' => $incident_types[array_rand($incident_types)] . ' - Major incident',
        'incident_date' => sprintf('%04d-%02d-%02d', rand(2000, 2020), rand(1, 12), rand(1, 28)),
        'duration' => $durations[array_rand($durations)],
      ];
      
      // 30% chance of second incident
      if (rand(1, 100) <= 30) {
        $major_incidents_data[] = [
          'description' => $incident_types[array_rand($incident_types)] . ' - Second major incident',
          'incident_date' => sprintf('%04d-%02d-%02d', rand(2000, 2020), rand(1, 12), rand(1, 28)),
          'duration' => $durations[array_rand($durations)],
        ];
      }
    }
    
    // Health: 10% chance of cancer diagnosis, then 30% chance of second diagnosis
    $cancer_details = [];
    if (rand(1, 100) <= 10) {
      $cancer_types = ['Lung', 'Prostate', 'Colon', 'Melanoma', 'Leukemia', 'Lymphoma', 'Kidney', 'Bladder'];
      
      // First diagnosis
      $cancer_details[] = [
        'cancer_type' => $cancer_types[array_rand($cancer_types)],
        'diagnosis_year' => rand(2010, 2023),
      ];
      
      // 30% chance of second diagnosis
      if (rand(1, 100) <= 30) {
        $cancer_details[] = [
          'cancer_type' => $cancer_types[array_rand($cancer_types)],
          'diagnosis_year' => rand(2010, 2023),
        ];
      }
    }
    
    // Family cancer history: 40% chance of family history, 30% chance of second relative
    $family_history = [];
    if (rand(1, 100) <= 40) {
      $relations = ['parent', 'sibling', 'grandparent', 'child', 'aunt_uncle', 'cousin'];
      $cancer_types = ['Lung', 'Breast', 'Prostate', 'Colon', 'Melanoma', 'Leukemia', 'Other'];
      
      // First relative
      $family_history[] = [
        'relation' => $relations[array_rand($relations)],
        'cancer_type' => $cancer_types[array_rand($cancer_types)],
      ];
      
      // 30% chance of second relative
      if (rand(1, 100) <= 30) {
        $family_history[] = [
          'relation' => $relations[array_rand($relations)],
          'cancer_type' => $cancer_types[array_rand($cancer_types)],
        ];
      }
    }
    
    return [
      'demographics' => [
        'race_ethnicity' => array_unique($selected_races),
        'education_level' => $education[array_rand($education)],
        'marital_status' => ['single', 'married', 'divorced', 'widowed', 'separated'][rand(0, 4)],
        'height_inches' => rand(60, 78),
        'weight_pounds' => rand(140, 260),
      ],
      'work_history' => [
        'departments' => $departments,
      ],
      'exposure' => [
        'afff_used' => ['yes', 'no'][rand(0, 1)],
        'afff_years' => rand(0, 20),
        'afff_frequency' => ['never', 'rarely', 'monthly', 'weekly'][rand(0, 3)],
        'diesel_exhaust' => ['regularly', 'sometimes', 'rarely', 'never'][rand(0, 3)],
        'diesel_years' => rand(0, 25),
        'major_incidents' => $has_major_incidents ? 'yes' : 'no',
        'major_incidents_data' => $major_incidents_data,
      ],
      'military' => [
        'served' => ['yes', 'no'][rand(0, 1)],
        'branch' => ['army', 'navy', 'air_force', 'marines', 'coast_guard'][rand(0, 4)],
        'start_date' => sprintf('%04d-01-01', rand(2000, 2010)),
        'end_date' => sprintf('%04d-01-01', rand(2010, 2020)),
        'military_specialty' => 'Infantry',
        'deployment_locations' => [],
        'exposures' => [],
      ],
      'other_employment' => [
        'had_other_jobs' => count($other_jobs) > 0 ? 'yes' : 'no',
        'jobs' => $other_jobs,
      ],
      'ppe' => [
        'scba_usage' => ['always', 'usually', 'sometimes', 'rarely'][rand(0, 3)],
        'glove_usage' => ['always', 'usually', 'sometimes'][rand(0, 2)],
        'hood_usage' => ['always', 'usually', 'sometimes'][rand(0, 2)],
        'turnout_cleaning' => ['after_every_fire', 'weekly', 'monthly'][rand(0, 2)],
      ],
      'decontamination' => [
        'field_decon' => rand(0, 1),
        'station_decon' => rand(0, 1),
        'shower_after_fire' => ['always', 'usually', 'sometimes'][rand(0, 2)],
        'gear_drying' => ['dedicated_area', 'living_area', 'outside'][rand(0, 2)],
      ],
      'health' => [
        'cancer_diagnosis' => count($cancer_details) > 0 ? 1 : 0,
        'cancer_details' => $cancer_details,
        'family_history' => $family_history,
      ],
      'lifestyle' => [
        'smoking_status' => ['never', 'former', 'current'][rand(0, 2)],
        'alcohol_frequency' => ['never', 'less_than_monthly', '1_3_per_month', '1_2_per_week', '3_4_per_week', '5_plus_per_week'][rand(0, 5)],
        'physical_activity_days' => rand(0, 7),
      ],
    ];
  }

  /**
   * Save profile data to database.
   */
  private function saveProfileData(int $uid, array $data): array {
    try {
      $database = \Drupal::database();

      // Check if record exists
      $exists = $database->select('nfr_user_profile', 'p')
        ->fields('p', ['id'])
        ->condition('uid', $uid)
        ->execute()
        ->fetchField();

      $fields = [
        'first_name' => $data['first_name'],
        'middle_name' => $data['middle_name'],
        'last_name' => $data['last_name'],
        'date_of_birth' => $data['date_of_birth'],
        'sex' => $data['sex'],
        'ssn_last_4' => $data['ssn_last_4'],
        'country_of_birth' => $data['country_of_birth'],
        'state_of_birth' => $data['state_of_birth'],
        'city_of_birth' => $data['city_of_birth'],
        'address_line1' => $data['address_line1'],
        'city' => $data['city'],
        'state' => $data['state'],
        'zip_code' => $data['zip_code'],
        'mobile_phone' => $data['mobile_phone'],
        'current_work_status' => $data['current_work_status'],
        'profile_completed' => 1,
        'profile_completed_date' => time(),
        'updated' => time(),
      ];

      if ($exists) {
        $database->update('nfr_user_profile')
          ->fields($fields)
          ->condition('uid', $uid)
          ->execute();

        return [
          'success' => true,
          'message' => 'Profile data updated successfully',
          'record_id' => $exists,
        ];
      }
      else {
        $fields['uid'] = $uid;
        $fields['created'] = time();
        $fields['participant_id'] = 'NFR-' . strtoupper(substr(md5(uniqid((string) $uid, true)), 0, 8));
        
        $record_id = $database->insert('nfr_user_profile')
          ->fields($fields)
          ->execute();

        return [
          'success' => true,
          'message' => 'Profile data inserted successfully',
          'record_id' => $record_id,
        ];
      }
    }
    catch (\Exception $e) {
      return [
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Verify profile data in database.
   */
  private function verifyProfileData(int $uid): array {
    try {
      $database = \Drupal::database();

      $record = $database->select('nfr_user_profile', 'p')
        ->fields('p')
        ->condition('uid', $uid)
        ->execute()
        ->fetchAssoc();

      if (!$record) {
        return [
          'success' => false,
          'message' => "No profile data found for UID: $uid",
        ];
      }

      return [
        'success' => true,
        'message' => 'Profile verified in database',
        'record' => $record,
      ];

    }
    catch (\Exception $e) {
      return [
        'success' => false,
        'message' => 'Verification error: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Submit profile data through actual form validation and submission.
   */
  private function submitProfileForm(int $uid, array $data): array {
    try {
      // Load the user
      $user = \Drupal\user\Entity\User::load($uid);
      if (!$user) {
        return [
          'success' => false,
          'message' => "User $uid not found",
          'errors' => [],
        ];
      }

      // Switch to the test user's context
      $accountSwitcher = \Drupal::service('account_switcher');
      $accountSwitcher->switchTo($user);

      // Build form state with the random data
      $form_state = new \Drupal\Core\Form\FormState();
      $form_state->setValues([
        'first_name' => $data['first_name'],
        'middle_name' => $data['middle_name'],
        'last_name' => $data['last_name'],
        'date_of_birth' => $data['date_of_birth'],
        'sex' => $data['sex'],
        'ssn_last_4' => $data['ssn_last_4'],
        'country_of_birth' => $data['country_of_birth'],
        'state_of_birth' => $data['state_of_birth'],
        'city_of_birth' => $data['city_of_birth'],
        'address_line1' => $data['address_line1'],
        'city' => $data['city'],
        'state' => $data['state'],
        'zip_code' => $data['zip_code'],
        'mobile_phone' => $data['mobile_phone'],
        'current_work_status' => $data['current_work_status'],
        'op' => 'Save and Continue',
      ]);

      // Get the form and submit it programmatically
      $form = \Drupal::formBuilder()->getForm('\Drupal\nfr\Form\NFRUserProfileForm');
      \Drupal::formBuilder()->submitForm('\Drupal\nfr\Form\NFRUserProfileForm', $form_state);

      // Switch back to original user
      $accountSwitcher->switchBack();

      // Check for form errors
      $errors = $form_state->getErrors();
      if (!empty($errors)) {
        return [
          'success' => false,
          'message' => 'Form validation failed',
          'errors' => array_map('strval', $errors),
        ];
      }

      return [
        'success' => true,
        'message' => 'Profile form submitted successfully',
        'errors' => [],
      ];
    }
    catch (\Exception $e) {
      // Make sure to switch back even on error
      if (isset($accountSwitcher)) {
        $accountSwitcher->switchBack();
      }

      return [
        'success' => false,
        'message' => 'Form submission error: ' . $e->getMessage(),
        'errors' => [$e->getMessage()],
      ];
    }
  }

  /**
   * Submit all 9 questionnaire section forms.
   */
  private function submitAllQuestionnaireSections(int $uid, array $data): array {
    $section_forms = [
      1 => '\Drupal\nfr\Form\NFRQuestionnaireSection1Form',
      2 => '\Drupal\nfr\Form\NFRQuestionnaireSection2Form',
      3 => '\Drupal\nfr\Form\NFRQuestionnaireSection3Form',
      4 => '\Drupal\nfr\Form\NFRQuestionnaireSection4Form',
      5 => '\Drupal\nfr\Form\NFRQuestionnaireSection5Form',
      6 => '\Drupal\nfr\Form\NFRQuestionnaireSection6Form',
      7 => '\Drupal\nfr\Form\NFRQuestionnaireSection7Form',
      8 => '\Drupal\nfr\Form\NFRQuestionnaireSection8Form',
      9 => '\Drupal\nfr\Form\NFRQuestionnaireSection9Form',
    ];

    $section_data_map = [
      1 => ['demographics' => $data['demographics'] ?? []],
      2 => [
        'work_history' => [
          'num_departments' => 1,
          'departments' => [
            [
              'department_name' => 'Test Fire Department',
              'state' => 'CA',
              'city' => 'Los Angeles',
              'start_date' => '2010-01-01',
              'num_jobs' => 1,
              'jobs' => [
                [
                  'title' => 'Firefighter',
                  'employment_type' => 'career',
                  'responded_incidents' => 'yes',
                ],
              ],
            ],
          ],
        ],
      ],
      3 => [
        'exposure' => [
          'afff_used' => $data['exposure']['afff_used'] ?? 'no',
          'diesel_exhaust' => $data['exposure']['diesel_exhaust'] ?? 'never',
          'major_incidents' => !empty($data['exposure']['major_incidents']) && is_array($data['exposure']['major_incidents']) ? 'yes' : ($data['exposure']['major_incidents'] ?? 'no'),
        ],
      ],
      4 => [
        'military' => [
          'served' => $data['military']['served'] ?? 'no',
          'branch' => $data['military']['branch'] ?? '',
          'start_date' => $data['military']['start_date'] ?? '',
          'end_date' => $data['military']['end_date'] ?? '',
        ],
      ],
      5 => [
        'other_employment' => [
          'had_other_jobs' => !empty($data['other_employment']['jobs']) ? 'yes' : 'no',
          'jobs' => $data['other_employment']['jobs'] ?? [],
        ],
      ],
      6 => [
        'ppe' => $data['ppe'] ?? [],
      ],
      7 => [
        'decontamination' => array_merge(
          $data['decontamination'] ?? [],
          ['department_had_sops' => ['yes', 'no'][rand(0, 1)]]
        ),
      ],
      8 => [
        'health' => [
          'cancer_diagnosed' => ($data['health']['cancer_diagnosis'] ?? 0) ? 'yes' : 'no',
          'cancer_details' => $data['health']['cancer_details'] ?? [],
        ],
      ],
      9 => [
        'lifestyle' => [
          'smoking_status' => $data['lifestyle']['smoking_status'] ?? 'never',
          'alcohol_frequency' => $data['lifestyle']['alcohol_frequency'] ?? 'never',
          'physical_activity_days' => isset($data['lifestyle']['physical_activity_days']) ? (string)$data['lifestyle']['physical_activity_days'] : '0',
        ],
      ],
    ];

    $results = [];

    try {
      // Load the user
      $user = \Drupal\user\Entity\User::load($uid);
      if (!$user) {
        foreach (range(1, 9) as $section) {
          $results[$section] = [
            'success' => false,
            'message' => "User $uid not found",
            'errors' => [],
          ];
        }
        return $results;
      }

      // Switch to the test user's context
      $accountSwitcher = \Drupal::service('account_switcher');
      $accountSwitcher->switchTo($user);

      // Submit each section form
      foreach ($section_forms as $section_num => $form_class) {
        try {
          $form_state = new \Drupal\Core\Form\FormState();
          $form_state->setValues($section_data_map[$section_num]);
          $form_state->setValue('op', 'Save & Continue');

          \Drupal::formBuilder()->submitForm($form_class, $form_state);

          $errors = $form_state->getErrors();
          if (!empty($errors)) {
            $results[$section_num] = [
              'success' => false,
              'message' => "Section {$section_num} validation failed",
              'errors' => array_map('strval', $errors),
            ];
          }
          else {
            $results[$section_num] = [
              'success' => true,
              'message' => "Section {$section_num} submitted successfully",
              'errors' => [],
            ];
          }
        }
        catch (\Exception $e) {
          $results[$section_num] = [
            'success' => false,
            'message' => "Section {$section_num} error: " . $e->getMessage(),
            'errors' => [$e->getMessage()],
          ];
        }
      }

      // Switch back to original user
      $accountSwitcher->switchBack();

      return $results;
    }
    catch (\Exception $e) {
      // Make sure to switch back even on error
      if (isset($accountSwitcher)) {
        $accountSwitcher->switchBack();
      }

      foreach (range(1, 9) as $section) {
        $results[$section] = [
          'success' => false,
          'message' => 'Overall error: ' . $e->getMessage(),
          'errors' => [$e->getMessage()],
        ];
      }

      return $results;
    }
  }

  /**
   * Submit questionnaire data through actual form validation and submission.
   * @deprecated Use submitAllQuestionnaireSections() instead for new multi-page format.
   */
  private function submitQuestionnaireForm(int $uid, array $data): array {
    try {
      // Load the user
      $user = \Drupal\user\Entity\User::load($uid);
      if (!$user) {
        return [
          'success' => false,
          'message' => "User $uid not found",
          'errors' => [],
        ];
      }

      // Switch to the test user's context
      $accountSwitcher = \Drupal::service('account_switcher');
      $accountSwitcher->switchTo($user);

      // Build form state with the random data
      $form_state = new \Drupal\Core\Form\FormState();
      $form_state->setValues($data);
      $form_state->setValue('op', 'Save and Continue');

      // Get the form and submit it programmatically
      $form = \Drupal::formBuilder()->getForm('\Drupal\nfr\Form\NFRQuestionnaireForm');
      \Drupal::formBuilder()->submitForm('\Drupal\nfr\Form\NFRQuestionnaireForm', $form_state);

      // Switch back to original user
      $accountSwitcher->switchBack();

      // Check for form errors
      $errors = $form_state->getErrors();
      if (!empty($errors)) {
        return [
          'success' => false,
          'message' => 'Form validation failed',
          'errors' => array_map('strval', $errors),
        ];
      }

      return [
        'success' => true,
        'message' => 'Questionnaire form submitted successfully',
        'errors' => [],
      ];
    }
    catch (\Exception $e) {
      // Make sure to switch back even on error
      if (isset($accountSwitcher)) {
        $accountSwitcher->switchBack();
      }
      
      return [
        'success' => false,
        'message' => 'Form submission error: ' . $e->getMessage(),
        'errors' => [$e->getMessage()],
      ];
    }
  }

  /**
   * Check error logs for recent errors.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with log check results.
   */
  public function checkErrorLogsEndpoint(): JsonResponse {
    $results = $this->checkErrorLogs();
    return new JsonResponse($results);
  }

  /**
   * Check dblog for recent errors.
   */
  private function checkErrorLogs(): array {
    try {
      $database = \Drupal::database();

      // Check if dblog table exists
      if (!$database->schema()->tableExists('watchdog')) {
        return [
          'success' => true,
          'has_errors' => false,
          'message' => 'Watchdog table not found - dblog module may not be enabled',
          'error_count' => 0,
        ];
      }

      // Get recent errors (last hour)
      $one_hour_ago = time() - 3600;
      
      $query = $database->select('watchdog', 'w')
        ->fields('w', ['wid', 'type', 'message', 'variables', 'severity', 'timestamp'])
        ->condition('severity', [0, 1, 2, 3], 'IN') // EMERGENCY, ALERT, CRITICAL, ERROR
        ->condition('timestamp', $one_hour_ago, '>')
        ->orderBy('timestamp', 'DESC')
        ->range(0, 10);

      $errors = $query->execute()->fetchAll();

      $error_count = count($errors);
      $recent_errors = [];

      foreach ($errors as $error) {
        // Decode the variables from the watchdog entry
        $variables = [];
        if (!empty($error->variables)) {
          $variables = unserialize($error->variables);
          if (!is_array($variables)) {
            $variables = [];
          }
        }
        
        // Replace placeholders in the message with actual values
        $message = $error->message;
        if (!empty($variables)) {
          foreach ($variables as $key => $value) {
            // Handle different placeholder types
            if (is_string($value) || is_numeric($value)) {
              $message = str_replace($key, (string) $value, $message);
            }
            elseif (is_array($value) || is_object($value)) {
              $message = str_replace($key, print_r($value, TRUE), $message);
            }
          }
        }
        
        $recent_errors[] = [
          'type' => $error->type,
          'message' => substr($message, 0, 500), // Show more of the error message
          'severity' => $error->severity,
          'time' => date('Y-m-d H:i:s', (int) $error->timestamp),
        ];
      }

      return [
        'success' => true,
        'has_errors' => $error_count > 0,
        'message' => $error_count > 0 
          ? "Found $error_count error(s) in the last hour" 
          : 'No errors found in the last hour',
        'error_count' => $error_count,
        'recent_errors' => $recent_errors,
      ];

    }
    catch (\Exception $e) {
      return [
        'success' => false,
        'has_errors' => false,
        'message' => 'Error checking logs: ' . $e->getMessage(),
        'error_count' => 0,
      ];
    }
  }

  /**
   * Redirect from old fill-rates path to new admin location.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function fillRatesRedirect() {
    return $this->redirect('nfr.validation.fill_rates');
  }

  /**
   * Display fill rates for all questionnaire fields.
   */
  public function getFillRates() {
    $connection = \Drupal::database();
    
    try {
      // Get all profile records
      $profile_query = $connection->select('nfr_user_profile', 'p')
        ->fields('p')
        ->condition('p.uid', 2, '>');
      $profile_results = $profile_query->execute();
      $profiles = [];
      foreach ($profile_results as $row) {
        $profiles[$row->uid] = $row;
      }
      
      // Get all questionnaire records with ALL columns
      $query = $connection->select('nfr_questionnaire', 'q')
        ->fields('q')
        ->condition('q.uid', 2, '>');
      
      $results = $query->execute();
      
      // Get all work history from normalized tables
      $work_history_query = $connection->select('nfr_work_history', 'wh')
        ->fields('wh')
        ->condition('wh.uid', 2, '>');
      $work_history_results = $work_history_query->execute();
      
      $user_work_history = [];
      foreach ($work_history_results as $wh) {
        if (!isset($user_work_history[$wh->uid])) {
          $user_work_history[$wh->uid] = [
            'num_departments' => 0,
            'departments' => [],
          ];
        }
        $user_work_history[$wh->uid]['num_departments']++;
        $user_work_history[$wh->uid]['departments'][] = $wh;
      }
      
      // Get all job titles from normalized tables
      $job_titles_query = $connection->select('nfr_job_titles', 'jt')
        ->fields('jt');
      $job_titles_results = $job_titles_query->execute();
      
      $work_history_jobs = [];
      foreach ($job_titles_results as $job) {
        if (!isset($work_history_jobs[$job->work_history_id])) {
          $work_history_jobs[$job->work_history_id] = [];
        }
        $work_history_jobs[$job->work_history_id][] = $job;
      }
      
      // Get major incidents from normalized table
      $major_incidents_query = $connection->select('nfr_major_incidents', 'mi')
        ->fields('mi');
      $major_incidents_results = $major_incidents_query->execute();
      
      $user_major_incidents = [];
      foreach ($major_incidents_results as $incident) {
        if (!isset($user_major_incidents[$incident->uid])) {
          $user_major_incidents[$incident->uid] = [];
        }
        $user_major_incidents[$incident->uid][] = $incident;
      }
      
      // Get other employment from normalized table
      $other_employment_query = $connection->select('nfr_other_employment', 'oe')
        ->fields('oe');
      $other_employment_results = $other_employment_query->execute();
      
      $user_other_employment = [];
      foreach ($other_employment_results as $job) {
        if (!isset($user_other_employment[$job->uid])) {
          $user_other_employment[$job->uid] = [];
        }
        $user_other_employment[$job->uid][] = $job;
      }
      
      // Get cancer diagnoses from normalized table
      $cancer_diagnoses_query = $connection->select('nfr_cancer_diagnoses', 'cd')
        ->fields('cd');
      $cancer_diagnoses_results = $cancer_diagnoses_query->execute();
      
      $user_cancer_diagnoses = [];
      foreach ($cancer_diagnoses_results as $diagnosis) {
        if (!isset($user_cancer_diagnoses[$diagnosis->uid])) {
          $user_cancer_diagnoses[$diagnosis->uid] = [];
        }
        $user_cancer_diagnoses[$diagnosis->uid][] = $diagnosis;
      }
      
      // Get consent records
      $consent_query = $connection->select('nfr_consent', 'c')
        ->fields('c');
      $consent_results = $consent_query->execute();
      
      $user_consents = [];
      foreach ($consent_results as $consent) {
        $user_consents[$consent->uid] = $consent;
      }
      
      // Get section completion records
      $section_completion_query = $connection->select('nfr_section_completion', 'sc')
        ->fields('sc');
      $section_completion_results = $section_completion_query->execute();
      
      $user_section_completion = [];
      foreach ($section_completion_results as $completion) {
        if (!isset($user_section_completion[$completion->uid])) {
          $user_section_completion[$completion->uid] = [];
        }
        $user_section_completion[$completion->uid][$completion->section_number] = $completion;
      }
      
      $total_records = 0;
      $field_counts = [];
      $value_distributions = [];
      
      foreach ($results as $row) {
        $total_records++;
        $uid = $row->uid;
        $profile = $profiles[$uid] ?? NULL;
        
        // PROFILE FIELDS
        if ($profile) {
          if (!empty($profile->first_name)) {
            $field_counts['profile.first_name'] = ($field_counts['profile.first_name'] ?? 0) + 1;
          }
          if (!empty($profile->middle_name)) {
            $field_counts['profile.middle_name'] = ($field_counts['profile.middle_name'] ?? 0) + 1;
          }
          if (!empty($profile->last_name)) {
            $field_counts['profile.last_name'] = ($field_counts['profile.last_name'] ?? 0) + 1;
          }
          if (!empty($profile->date_of_birth)) {
            $field_counts['profile.date_of_birth'] = ($field_counts['profile.date_of_birth'] ?? 0) + 1;
          }
          if (!empty($profile->sex)) {
            $field_counts['profile.sex'] = ($field_counts['profile.sex'] ?? 0) + 1;
            $value_distributions['profile.sex'][$profile->sex] = ($value_distributions['profile.sex'][$profile->sex] ?? 0) + 1;
          }
          if (!empty($profile->ssn_last_4)) {
            $field_counts['profile.ssn_last_4'] = ($field_counts['profile.ssn_last_4'] ?? 0) + 1;
          }
          if (!empty($profile->country_of_birth)) {
            $field_counts['profile.country_of_birth'] = ($field_counts['profile.country_of_birth'] ?? 0) + 1;
            $value_distributions['profile.country_of_birth'][$profile->country_of_birth] = ($value_distributions['profile.country_of_birth'][$profile->country_of_birth] ?? 0) + 1;
          }
          if (!empty($profile->state_of_birth)) {
            $field_counts['profile.state_of_birth'] = ($field_counts['profile.state_of_birth'] ?? 0) + 1;
            $value_distributions['profile.state_of_birth'][$profile->state_of_birth] = ($value_distributions['profile.state_of_birth'][$profile->state_of_birth] ?? 0) + 1;
          }
          if (!empty($profile->city_of_birth)) {
            $field_counts['profile.city_of_birth'] = ($field_counts['profile.city_of_birth'] ?? 0) + 1;
          }
          if (!empty($profile->address_line1)) {
            $field_counts['profile.address_line1'] = ($field_counts['profile.address_line1'] ?? 0) + 1;
          }
          if (!empty($profile->address_line2)) {
            $field_counts['profile.address_line2'] = ($field_counts['profile.address_line2'] ?? 0) + 1;
          }
          if (!empty($profile->city)) {
            $field_counts['profile.city'] = ($field_counts['profile.city'] ?? 0) + 1;
          }
          if (!empty($profile->state)) {
            $field_counts['profile.state'] = ($field_counts['profile.state'] ?? 0) + 1;
            $value_distributions['profile.state'][$profile->state] = ($value_distributions['profile.state'][$profile->state] ?? 0) + 1;
          }
          if (!empty($profile->zip_code)) {
            $field_counts['profile.zip_code'] = ($field_counts['profile.zip_code'] ?? 0) + 1;
          }
          if (!empty($profile->alternate_email)) {
            $field_counts['profile.alternate_email'] = ($field_counts['profile.alternate_email'] ?? 0) + 1;
          }
          if (!empty($profile->mobile_phone)) {
            $field_counts['profile.mobile_phone'] = ($field_counts['profile.mobile_phone'] ?? 0) + 1;
          }
          if (!empty($profile->current_work_status)) {
            $field_counts['profile.current_work_status'] = ($field_counts['profile.current_work_status'] ?? 0) + 1;
            $value_distributions['profile.current_work_status'][$profile->current_work_status] = ($value_distributions['profile.current_work_status'][$profile->current_work_status] ?? 0) + 1;
          }
        }
        
        // QUESTIONNAIRE DIRECT COLUMNS
        if (!empty($row->race_other)) {
          $field_counts['questionnaire.race_other'] = ($field_counts['questionnaire.race_other'] ?? 0) + 1;
        }
        if (isset($row->height_inches) && $row->height_inches > 0) {
          $field_counts['questionnaire.height_inches'] = ($field_counts['questionnaire.height_inches'] ?? 0) + 1;
          $val = $row->height_inches;
          $value_distributions['questionnaire.height_inches'][$val] = ($value_distributions['questionnaire.height_inches'][$val] ?? 0) + 1;
        }
        if (isset($row->weight_pounds) && $row->weight_pounds > 0) {
          $field_counts['questionnaire.weight_pounds'] = ($field_counts['questionnaire.weight_pounds'] ?? 0) + 1;
          $val = $row->weight_pounds;
          $value_distributions['questionnaire.weight_pounds'][$val] = ($value_distributions['questionnaire.weight_pounds'][$val] ?? 0) + 1;
        }
        if (isset($row->military_service) && $row->military_service !== NULL) {
          $field_counts['questionnaire.military_service'] = ($field_counts['questionnaire.military_service'] ?? 0) + 1;
          $val = $row->military_service ? 'yes' : 'no';
          $value_distributions['questionnaire.military_service'][$val] = ($value_distributions['questionnaire.military_service'][$val] ?? 0) + 1;
        }
        if (!empty($row->military_branch)) {
          $field_counts['questionnaire.military_branch'] = ($field_counts['questionnaire.military_branch'] ?? 0) + 1;
          $value_distributions['questionnaire.military_branch'][$row->military_branch] = ($value_distributions['questionnaire.military_branch'][$row->military_branch] ?? 0) + 1;
        }
        if (isset($row->military_years) && $row->military_years > 0) {
          $field_counts['questionnaire.military_years'] = ($field_counts['questionnaire.military_years'] ?? 0) + 1;
          $val = $row->military_years;
          $value_distributions['questionnaire.military_years'][$val] = ($value_distributions['questionnaire.military_years'][$val] ?? 0) + 1;
        }
        if (isset($row->cancer_diagnosis) && $row->cancer_diagnosis !== NULL) {
          $field_counts['questionnaire.cancer_diagnosis'] = ($field_counts['questionnaire.cancer_diagnosis'] ?? 0) + 1;
          $val = $row->cancer_diagnosis ? 'yes' : 'no';
          $value_distributions['questionnaire.cancer_diagnosis'][$val] = ($value_distributions['questionnaire.cancer_diagnosis'][$val] ?? 0) + 1;
        }
        if (!empty($row->alcohol_use)) {
          $field_counts['questionnaire.alcohol_use'] = ($field_counts['questionnaire.alcohol_use'] ?? 0) + 1;
          $value_distributions['questionnaire.alcohol_use'][$row->alcohol_use] = ($value_distributions['questionnaire.alcohol_use'][$row->alcohol_use] ?? 0) + 1;
        }
        
        // DEMOGRAPHICS (from direct columns)
        if (!empty($row->education_level)) {
          $field_counts['demographics.education_level'] = ($field_counts['demographics.education_level'] ?? 0) + 1;
          $val = $row->education_level;
          $value_distributions['demographics.education_level'][$val] = ($value_distributions['demographics.education_level'][$val] ?? 0) + 1;
        }
        if (!empty($row->marital_status)) {
          $field_counts['demographics.marital_status'] = ($field_counts['demographics.marital_status'] ?? 0) + 1;
          $val = $row->marital_status;
          $value_distributions['demographics.marital_status'][$val] = ($value_distributions['demographics.marital_status'][$val] ?? 0) + 1;
        }
        if (!empty($row->race_ethnicity)) {
          $race_data = json_decode($row->race_ethnicity, TRUE);
          if (is_array($race_data)) {
            $race_selections = [];
            foreach ($race_data as $key => $val) {
              if ($val !== 0 && $val !== '0' && !empty($val)) {
                $race_selections[] = $key;
              }
            }
            if (count($race_selections) > 0) {
              $field_counts['demographics.race_ethnicity'] = ($field_counts['demographics.race_ethnicity'] ?? 0) + 1;
              $race_str = implode(', ', $race_selections);
              $value_distributions['demographics.race_ethnicity'][$race_str] = ($value_distributions['demographics.race_ethnicity'][$race_str] ?? 0) + 1;
            }
          }
        }
        
        // WORK HISTORY (from normalized tables)
        $uid_work_history = $user_work_history[$uid] ?? NULL;
        if ($uid_work_history && $uid_work_history['num_departments'] > 0) {
          $field_counts['work_history.num_departments'] = ($field_counts['work_history.num_departments'] ?? 0) + 1;
          $val = $uid_work_history['num_departments'];
          $value_distributions['work_history.num_departments'][$val] = ($value_distributions['work_history.num_departments'][$val] ?? 0) + 1;
          
          // Process first department for field tracking
          $dept = $uid_work_history['departments'][0] ?? NULL;
          if ($dept) {
            if (!empty($dept->department_name)) {
              $field_counts['work_history.department_name'] = ($field_counts['work_history.department_name'] ?? 0) + 1;
            }
            if (!empty($dept->department_state)) {
              $field_counts['work_history.department_state'] = ($field_counts['work_history.department_state'] ?? 0) + 1;
              $value_distributions['work_history.department_state'][$dept->department_state] = ($value_distributions['work_history.department_state'][$dept->department_state] ?? 0) + 1;
            }
            if (!empty($dept->department_city)) {
              $field_counts['work_history.department_city'] = ($field_counts['work_history.department_city'] ?? 0) + 1;
            }
            if (!empty($dept->department_fdid)) {
              $field_counts['work_history.department_fdid'] = ($field_counts['work_history.department_fdid'] ?? 0) + 1;
            }
            if (!empty($dept->start_date)) {
              $field_counts['work_history.start_date'] = ($field_counts['work_history.start_date'] ?? 0) + 1;
            }
            if (!empty($dept->end_date)) {
              $field_counts['work_history.end_date'] = ($field_counts['work_history.end_date'] ?? 0) + 1;
            }
            if (isset($dept->is_current)) {
              $field_counts['work_history.is_current'] = ($field_counts['work_history.is_current'] ?? 0) + 1;
            }
            
            // Get jobs for this department
            $dept_jobs = $work_history_jobs[$dept->id] ?? [];
            if (!empty($dept_jobs)) {
              $job = $dept_jobs[0];
              if (!empty($job->job_title)) {
                $field_counts['work_history.job_title'] = ($field_counts['work_history.job_title'] ?? 0) + 1;
                $value_distributions['work_history.job_title'][$job->job_title] = ($value_distributions['work_history.job_title'][$job->job_title] ?? 0) + 1;
              }
              if (!empty($job->employment_type)) {
                $field_counts['work_history.employment_type'] = ($field_counts['work_history.employment_type'] ?? 0) + 1;
                $value_distributions['work_history.employment_type'][$job->employment_type] = ($value_distributions['work_history.employment_type'][$job->employment_type] ?? 0) + 1;
              }
              if (isset($job->responded_to_incidents)) {
                $field_counts['work_history.responded_incidents'] = ($field_counts['work_history.responded_incidents'] ?? 0) + 1;
                $val = $job->responded_to_incidents ? 'yes' : 'no';
                $value_distributions['work_history.responded_incidents'][$val] = ($value_distributions['work_history.responded_incidents'][$val] ?? 0) + 1;
              }
              if (!empty($job->incident_types)) {
                $field_counts['work_history.incident_types'] = ($field_counts['work_history.incident_types'] ?? 0) + 1;
              }
            }
          }
        }
        
        // EXPOSURE (from direct columns + nfr_major_incidents table)
        if (!empty($row->afff_used)) {
          $field_counts['exposure.afff_used'] = ($field_counts['exposure.afff_used'] ?? 0) + 1;
          $value_distributions['exposure.afff_used'][$row->afff_used] = ($value_distributions['exposure.afff_used'][$row->afff_used] ?? 0) + 1;
        }
        if (isset($row->afff_times) && $row->afff_times > 0) {
          $field_counts['exposure.afff_times'] = ($field_counts['exposure.afff_times'] ?? 0) + 1;
        }
        if (isset($row->afff_first_year) && $row->afff_first_year > 0) {
          $field_counts['exposure.afff_first_year'] = ($field_counts['exposure.afff_first_year'] ?? 0) + 1;
        }
        if (!empty($row->diesel_exhaust)) {
          $field_counts['exposure.diesel_exhaust'] = ($field_counts['exposure.diesel_exhaust'] ?? 0) + 1;
          $value_distributions['exposure.diesel_exhaust'][$row->diesel_exhaust] = ($value_distributions['exposure.diesel_exhaust'][$row->diesel_exhaust] ?? 0) + 1;
        }
        if (isset($row->major_incidents)) {
          $field_counts['exposure.major_incidents'] = ($field_counts['exposure.major_incidents'] ?? 0) + 1;
          $val = $row->major_incidents ? 'yes' : 'no';
          $value_distributions['exposure.major_incidents'][$val] = ($value_distributions['exposure.major_incidents'][$val] ?? 0) + 1;
        }
        if (!empty($row->chemical_activities)) {
          $chem = json_decode($row->chemical_activities, TRUE);
          if (is_array($chem) && count($chem) > 0) {
            $field_counts['exposure.chemical_activities'] = ($field_counts['exposure.chemical_activities'] ?? 0) + 1;
          }
        }
        
        // MAJOR INCIDENTS (from nfr_major_incidents table)
        $user_incidents = $user_major_incidents[$uid] ?? [];
        if (count($user_incidents) > 0) {
          $field_counts['exposure.major_incidents_count'] = ($field_counts['exposure.major_incidents_count'] ?? 0) + 1;
          $value_distributions['exposure.major_incidents_count'][count($user_incidents)] = ($value_distributions['exposure.major_incidents_count'][count($user_incidents)] ?? 0) + 1;
          
          $incident = $user_incidents[0];
          if (!empty($incident->description)) {
            $field_counts['exposure.major_incident_description'] = ($field_counts['exposure.major_incident_description'] ?? 0) + 1;
          }
          if (!empty($incident->incident_date)) {
            $field_counts['exposure.major_incident_date'] = ($field_counts['exposure.major_incident_date'] ?? 0) + 1;
          }
          if (!empty($incident->duration)) {
            $field_counts['exposure.major_incident_duration'] = ($field_counts['exposure.major_incident_duration'] ?? 0) + 1;
            $val = $incident->duration;
            $value_distributions['exposure.major_incident_duration'][$val] = ($value_distributions['exposure.major_incident_duration'][$val] ?? 0) + 1;
          }
        }
        
        // MILITARY (from direct columns)
        if (!empty($row->military_start_date)) {
          $field_counts['military.start_date'] = ($field_counts['military.start_date'] ?? 0) + 1;
        }
        if (!empty($row->military_end_date)) {
          $field_counts['military.end_date'] = ($field_counts['military.end_date'] ?? 0) + 1;
        }
        if (isset($row->military_currently_serving)) {
          $field_counts['military.currently_serving'] = ($field_counts['military.currently_serving'] ?? 0) + 1;
          $val = $row->military_currently_serving ? 'yes' : 'no';
          $value_distributions['military.currently_serving'][$val] = ($value_distributions['military.currently_serving'][$val] ?? 0) + 1;
        }
        if (!empty($row->military_was_firefighter)) {
          $field_counts['military.was_firefighter'] = ($field_counts['military.was_firefighter'] ?? 0) + 1;
          $value_distributions['military.was_firefighter'][$row->military_was_firefighter] = ($value_distributions['military.was_firefighter'][$row->military_was_firefighter] ?? 0) + 1;
        }
        
        // OTHER EMPLOYMENT (from nfr_other_employment table + JSON column)
        $user_other_jobs = $user_other_employment[$uid] ?? [];
        if (count($user_other_jobs) > 0) {
          $field_counts['other_employment.jobs_count_table'] = ($field_counts['other_employment.jobs_count_table'] ?? 0) + 1;
          $value_distributions['other_employment.jobs_count_table'][count($user_other_jobs)] = ($value_distributions['other_employment.jobs_count_table'][count($user_other_jobs)] ?? 0) + 1;
          
          $job = $user_other_jobs[0];
          if (!empty($job->occupation)) {
            $field_counts['other_employment.occupation'] = ($field_counts['other_employment.occupation'] ?? 0) + 1;
            $value_distributions['other_employment.occupation'][$job->occupation] = ($value_distributions['other_employment.occupation'][$job->occupation] ?? 0) + 1;
          }
          if (!empty($job->industry)) {
            $field_counts['other_employment.industry'] = ($field_counts['other_employment.industry'] ?? 0) + 1;
            $value_distributions['other_employment.industry'][$job->industry] = ($value_distributions['other_employment.industry'][$job->industry] ?? 0) + 1;
          }
          if (!empty($job->start_year)) {
            $field_counts['other_employment.start_year'] = ($field_counts['other_employment.start_year'] ?? 0) + 1;
          }
          if (!empty($job->end_year)) {
            $field_counts['other_employment.end_year'] = ($field_counts['other_employment.end_year'] ?? 0) + 1;
          }
          if (!empty($job->exposures)) {
            $field_counts['other_employment.exposures'] = ($field_counts['other_employment.exposures'] ?? 0) + 1;
            $val = $job->exposures;
            $value_distributions['other_employment.exposures'][$val] = ($value_distributions['other_employment.exposures'][$val] ?? 0) + 1;
          }
          if (!empty($job->exposures_other)) {
            $field_counts['other_employment.exposures_other'] = ($field_counts['other_employment.exposures_other'] ?? 0) + 1;
          }
        }
        
        // OTHER EMPLOYMENT (from JSON column - keep existing logic for compatibility)
        if (!empty($row->other_employment_data)) {
          $other_employment = json_decode($row->other_employment_data, TRUE);
          if (is_array($other_employment) && !empty($other_employment['had_other_jobs'])) {
            $field_counts['other_employment.had_other_jobs'] = ($field_counts['other_employment.had_other_jobs'] ?? 0) + 1;
            $val = $other_employment['had_other_jobs'];
            $value_distributions['other_employment.had_other_jobs'][$val] = ($value_distributions['other_employment.had_other_jobs'][$val] ?? 0) + 1;
          }
          if (isset($other_employment['jobs']) && is_array($other_employment['jobs']) && count($other_employment['jobs']) > 0) {
            $field_counts['other_employment.jobs_count'] = ($field_counts['other_employment.jobs_count'] ?? 0) + 1;
            $job = $other_employment['jobs'][0];
            if (!empty($job['job_title'])) {
              $field_counts['other_employment.job_title'] = ($field_counts['other_employment.job_title'] ?? 0) + 1;
            }
            if (!empty($job['had_exposure'])) {
              $field_counts['other_employment.had_exposure'] = ($field_counts['other_employment.had_exposure'] ?? 0) + 1;
              $val = $job['had_exposure'];
              $value_distributions['other_employment.had_exposure'][$val] = ($value_distributions['other_employment.had_exposure'][$val] ?? 0) + 1;
            }
          }
        }
        
        // PPE (from direct columns)
        $ppe_items = [
          'scba' => 'ppe_scba',
          'turnout_coat' => 'ppe_turnout_coat',
          'turnout_pants' => 'ppe_turnout_pants',
          'gloves' => 'ppe_gloves',
          'helmet' => 'ppe_helmet',
          'boots' => 'ppe_boots',
          'nomex_hood' => 'ppe_nomex_hood',
          'wildland_clothing' => 'ppe_wildland_clothing',
        ];
        
        foreach ($ppe_items as $item_key => $column_prefix) {
          $ever_used_col = $column_prefix . '_ever_used';
          $year_col = $column_prefix . '_year_started';
          
          if (isset($row->$ever_used_col) && $row->$ever_used_col !== NULL) {
            $field_counts["ppe.{$item_key}.ever_used"] = ($field_counts["ppe.{$item_key}.ever_used"] ?? 0) + 1;
            $value_distributions["ppe.{$item_key}.ever_used"][$row->$ever_used_col] = ($value_distributions["ppe.{$item_key}.ever_used"][$row->$ever_used_col] ?? 0) + 1;
          }
          if (isset($row->$year_col) && $row->$year_col > 0) {
            $field_counts["ppe.{$item_key}.year_started"] = ($field_counts["ppe.{$item_key}.year_started"] ?? 0) + 1;
          }
        }
        
        // SCBA scenarios
        if (isset($row->ppe_scba_during_suppression) && $row->ppe_scba_during_suppression !== NULL) {
          $field_counts["ppe.scba_during_suppression"] = ($field_counts["ppe.scba_during_suppression"] ?? 0) + 1;
          $value_distributions["ppe.scba_during_suppression"][$row->ppe_scba_during_suppression] = ($value_distributions["ppe.scba_during_suppression"][$row->ppe_scba_during_suppression] ?? 0) + 1;
        }
        if (isset($row->ppe_scba_during_overhaul) && $row->ppe_scba_during_overhaul !== NULL) {
          $field_counts["ppe.scba_during_overhaul"] = ($field_counts["ppe.scba_during_overhaul"] ?? 0) + 1;
          $value_distributions["ppe.scba_during_overhaul"][$row->ppe_scba_during_overhaul] = ($value_distributions["ppe.scba_during_overhaul"][$row->ppe_scba_during_overhaul] ?? 0) + 1;
        }
        
        // DECONTAMINATION (from direct columns)
        $decon_fields = [
          'washed_hands_face' => 'decon_washed_hands_face',
          'changed_gear_at_scene' => 'decon_changed_gear_at_scene',
          'showered_at_station' => 'decon_showered_at_station',
          'laundered_gear' => 'decon_laundered_gear',
          'used_wet_wipes' => 'decon_used_wet_wipes',
        ];
        
        foreach ($decon_fields as $key => $column) {
          if (isset($row->$column) && $row->$column !== NULL) {
            $field_counts["decontamination.{$key}"] = ($field_counts["decontamination.{$key}"] ?? 0) + 1;
            $value_distributions["decontamination.{$key}"][$row->$column] = ($value_distributions["decontamination.{$key}"][$row->$column] ?? 0) + 1;
          }
        }
        
        if (isset($row->decon_department_had_sops) && $row->decon_department_had_sops !== NULL) {
          $field_counts['decontamination.department_had_sops'] = ($field_counts['decontamination.department_had_sops'] ?? 0) + 1;
          $value_distributions['decontamination.department_had_sops'][$row->decon_department_had_sops] = ($value_distributions['decontamination.department_had_sops'][$row->decon_department_had_sops] ?? 0) + 1;
        }
        if (isset($row->decon_sops_year_implemented) && $row->decon_sops_year_implemented > 0) {
          $field_counts['decontamination.sop_year_implemented'] = ($field_counts['decontamination.sop_year_implemented'] ?? 0) + 1;
        }
        
        // HEALTH (from direct columns + nfr_cancer_diagnoses table)
        $health_conditions = ['heart_disease', 'copd', 'asthma', 'diabetes'];
        foreach ($health_conditions as $condition) {
          $column = "health_{$condition}";
          if (isset($row->$column) && $row->$column !== NULL) {
            $field_counts["health.{$condition}"] = ($field_counts["health.{$condition}"] ?? 0) + 1;
            $value_distributions["health.{$condition}"][$row->$column ? 'yes' : 'no'] = ($value_distributions["health.{$condition}"][$row->$column ? 'yes' : 'no'] ?? 0) + 1;
          }
        }
        
        if (!empty($row->family_cancer_history)) {
          $family_history = json_decode($row->family_cancer_history, TRUE);
          if (is_array($family_history) && count($family_history) > 0) {
            $field_counts['health.family_cancer_history'] = ($field_counts['health.family_cancer_history'] ?? 0) + 1;
          }
        }
        
        // CANCER DIAGNOSES (from nfr_cancer_diagnoses table)
        $user_cancers = $user_cancer_diagnoses[$uid] ?? [];
        if (count($user_cancers) > 0) {
          $field_counts['health.cancer_count_table'] = ($field_counts['health.cancer_count_table'] ?? 0) + 1;
          $value_distributions['health.cancer_count_table'][count($user_cancers)] = ($value_distributions['health.cancer_count_table'][count($user_cancers)] ?? 0) + 1;
          
          $diagnosis = $user_cancers[0];
          if (!empty($diagnosis->cancer_type)) {
            $field_counts['health.cancer_type_table'] = ($field_counts['health.cancer_type_table'] ?? 0) + 1;
            $val = $diagnosis->cancer_type;
            $value_distributions['health.cancer_type_table'][$val] = ($value_distributions['health.cancer_type_table'][$val] ?? 0) + 1;
          }
          if (!empty($diagnosis->year_diagnosed)) {
            $field_counts['health.year_diagnosed_table'] = ($field_counts['health.year_diagnosed_table'] ?? 0) + 1;
          }
        }
        
        // CONSENT (from nfr_consent table)
        $consent = $user_consents[$uid] ?? NULL;
        if ($consent) {
          if (isset($consent->consented_to_participate)) {
            $field_counts['consent.participate'] = ($field_counts['consent.participate'] ?? 0) + 1;
            $val = $consent->consented_to_participate ? 'yes' : 'no';
            $value_distributions['consent.participate'][$val] = ($value_distributions['consent.participate'][$val] ?? 0) + 1;
          }
          if (isset($consent->consented_to_registry_linkage)) {
            $field_counts['consent.registry_linkage'] = ($field_counts['consent.registry_linkage'] ?? 0) + 1;
            $val = $consent->consented_to_registry_linkage ? 'yes' : 'no';
            $value_distributions['consent.registry_linkage'][$val] = ($value_distributions['consent.registry_linkage'][$val] ?? 0) + 1;
          }
          if (!empty($consent->electronic_signature)) {
            $field_counts['consent.electronic_signature'] = ($field_counts['consent.electronic_signature'] ?? 0) + 1;
          }
          if (!empty($consent->consent_ip_address)) {
            $field_counts['consent.ip_address'] = ($field_counts['consent.ip_address'] ?? 0) + 1;
          }
          if (!empty($consent->consent_timestamp)) {
            $field_counts['consent.timestamp'] = ($field_counts['consent.timestamp'] ?? 0) + 1;
          }
        }
        
        // SECTION COMPLETION (from nfr_section_completion table)
        $sections_completed = $user_section_completion[$uid] ?? [];
        if (count($sections_completed) > 0) {
          $field_counts['progress.sections_completed_count'] = ($field_counts['progress.sections_completed_count'] ?? 0) + 1;
          $value_distributions['progress.sections_completed_count'][count($sections_completed)] = ($value_distributions['progress.sections_completed_count'][count($sections_completed)] ?? 0) + 1;
          
          for ($i = 1; $i <= 9; $i++) {
            if (isset($sections_completed[$i]) && $sections_completed[$i]->completed) {
              $field_counts["progress.section_{$i}_completed"] = ($field_counts["progress.section_{$i}_completed"] ?? 0) + 1;
              if (!empty($sections_completed[$i]->completed_at)) {
                $field_counts["progress.section_{$i}_completed_at"] = ($field_counts["progress.section_{$i}_completed_at"] ?? 0) + 1;
              }
            }
          }
        }
        
        // LIFESTYLE (from direct columns and smoking_history JSON)
        if (!empty($row->smoking_history)) {
          $smoking = json_decode($row->smoking_history, TRUE);
          if (is_array($smoking)) {
            if (!empty($smoking['smoking_status'])) {
              $field_counts['lifestyle.smoking_status'] = ($field_counts['lifestyle.smoking_status'] ?? 0) + 1;
              $val = $smoking['smoking_status'];
              $value_distributions['lifestyle.smoking_status'][$val] = ($value_distributions['lifestyle.smoking_status'][$val] ?? 0) + 1;
            }
            
            $tobacco_types = ['cigarettes', 'cigars', 'pipes', 'chewing_tobacco', 'e_cigarettes'];
            foreach ($tobacco_types as $type) {
              if (!empty($smoking[$type]['ever_used'])) {
                $field_counts["lifestyle.{$type}_ever_used"] = ($field_counts["lifestyle.{$type}_ever_used"] ?? 0) + 1;
                $val = $smoking[$type]['ever_used'];
                $value_distributions["lifestyle.{$type}_ever_used"][$val] = ($value_distributions["lifestyle.{$type}_ever_used"][$val] ?? 0) + 1;
              }
              if (!empty($smoking[$type]['frequency'])) {
                $field_counts["lifestyle.{$type}_frequency"] = ($field_counts["lifestyle.{$type}_frequency"] ?? 0) + 1;
                $val = $smoking[$type]['frequency'];
                $value_distributions["lifestyle.{$type}_frequency"][$val] = ($value_distributions["lifestyle.{$type}_frequency"][$val] ?? 0) + 1;
              }
            }
          }
        }
        
        // Physical activity from direct column
        if (isset($row->physical_activity_days)) {
          $field_counts['lifestyle.physical_activity_days'] = ($field_counts['lifestyle.physical_activity_days'] ?? 0) + 1;
          $value_distributions['lifestyle.physical_activity_days'][$row->physical_activity_days] = ($value_distributions['lifestyle.physical_activity_days'][$row->physical_activity_days] ?? 0) + 1;
        }
      }
      
      // Sort fields
      ksort($field_counts);
      
      // Calculate summary statistics
      $total_fields = count($field_counts);
      $fields_at_100 = 0;
      $fields_below_100 = [];
      
      foreach ($field_counts as $field => $count) {
        $pct = round(($count / $total_records) * 100, 1);
        if ($pct >= 100.0) {
          $fields_at_100++;
        }
        else {
          $fields_below_100[] = ['field' => $field, 'count' => $count, 'pct' => $pct];
        }
      }
      
      // =============================================================================
      // CALCULATE TABLE-LEVEL STATISTICS
      // =============================================================================
      $table_stats = [];
      
      // Profile table
      $profile_field_count = 0;
      $profile_complete_fields = 0;
      foreach ($field_counts as $field => $count) {
        if (strpos($field, 'profile.') === 0) {
          $profile_field_count++;
          if ($count >= $total_records) {
            $profile_complete_fields++;
          }
        }
      }
      $table_stats['nfr_user_profile'] = [
        'record_count' => count($profiles),
        'field_count' => 26,
        'tracked_fields' => $profile_field_count,
        'complete_fields' => $profile_complete_fields,
        'completeness_pct' => $profile_field_count > 0 ? round(($profile_complete_fields / $profile_field_count) * 100, 1) : 0,
      ];
      
      // Questionnaire direct columns
      $quest_direct_count = 0;
      $quest_direct_complete = 0;
      foreach ($field_counts as $field => $count) {
        if (strpos($field, 'questionnaire.') === 0) {
          $quest_direct_count++;
          if ($count >= $total_records) {
            $quest_direct_complete++;
          }
        }
      }
      $table_stats['nfr_questionnaire_direct'] = [
        'record_count' => $total_records,
        'field_count' => 9,
        'tracked_fields' => $quest_direct_count,
        'complete_fields' => $quest_direct_complete,
        'completeness_pct' => $quest_direct_count > 0 ? round(($quest_direct_complete / $quest_direct_count) * 100, 1) : 0,
      ];
      
      // Work history tables
      $wh_records = count($user_work_history);
      $table_stats['nfr_work_history'] = [
        'record_count' => $wh_records,
        'field_count' => 11,
        'tracked_fields' => 9,
        'complete_fields' => 0,
        'completeness_pct' => 0,
      ];
      
      // Other tables
      $table_stats['nfr_major_incidents'] = [
        'record_count' => count($user_major_incidents),
        'field_count' => 7,
        'tracked_fields' => 4,
      ];
      
      $table_stats['nfr_other_employment'] = [
        'record_count' => count($user_other_employment),
        'field_count' => 9,
        'tracked_fields' => 7,
      ];
      
      $table_stats['nfr_cancer_diagnoses'] = [
        'record_count' => count($user_cancer_diagnoses),
        'field_count' => 6,
        'tracked_fields' => 3,
      ];
      
      $table_stats['nfr_consent'] = [
        'record_count' => count($user_consents),
        'field_count' => 7,
        'tracked_fields' => 5,
      ];
      
      $table_stats['nfr_section_completion'] = [
        'record_count' => count($user_section_completion),
        'field_count' => 5,
        'tracked_fields' => 20,
      ];
      
      // =============================================================================
      // CALCULATE PROFILE DATASET SUMMARY
      // =============================================================================
      $profile_summary = [
        'total_profiles' => count($profiles),
        'sex_distribution' => [],
        'state_distribution' => [],
        'country_distribution' => [],
        'work_status_distribution' => [],
        'age_groups' => [],
      ];
      
      foreach ($profiles as $profile) {
        // Sex distribution
        if (!empty($profile->sex)) {
          $profile_summary['sex_distribution'][$profile->sex] = ($profile_summary['sex_distribution'][$profile->sex] ?? 0) + 1;
        }
        
        // State distribution
        if (!empty($profile->state)) {
          $profile_summary['state_distribution'][$profile->state] = ($profile_summary['state_distribution'][$profile->state] ?? 0) + 1;
        }
        
        // Country distribution
        if (!empty($profile->country_of_birth)) {
          $profile_summary['country_distribution'][$profile->country_of_birth] = ($profile_summary['country_distribution'][$profile->country_of_birth] ?? 0) + 1;
        }
        
        // Work status
        if (!empty($profile->current_work_status)) {
          $profile_summary['work_status_distribution'][$profile->current_work_status] = ($profile_summary['work_status_distribution'][$profile->current_work_status] ?? 0) + 1;
        }
        
        // Age groups (calculate from DOB if available)
        if (!empty($profile->date_of_birth)) {
          $dob = strtotime($profile->date_of_birth);
          if ($dob) {
            $age = floor((time() - $dob) / (365.25 * 24 * 60 * 60));
            if ($age < 30) {
              $age_group = 'Under 30';
            } elseif ($age < 40) {
              $age_group = '30-39';
            } elseif ($age < 50) {
              $age_group = '40-49';
            } elseif ($age < 60) {
              $age_group = '50-59';
            } elseif ($age < 70) {
              $age_group = '60-69';
            } else {
              $age_group = '70+';
            }
            $profile_summary['age_groups'][$age_group] = ($profile_summary['age_groups'][$age_group] ?? 0) + 1;
          }
        }
      }
      
      // =============================================================================
      // CALCULATE SECTION DATASET SUMMARIES
      // =============================================================================
      $section_summaries = [
        'demographics' => ['fields' => 0, 'complete' => 0, 'users_started' => 0, 'users_completed' => 0],
        'work_history' => ['fields' => 0, 'complete' => 0, 'users_started' => 0, 'users_completed' => 0],
        'exposure' => ['fields' => 0, 'complete' => 0, 'users_started' => 0, 'users_completed' => 0],
        'military' => ['fields' => 0, 'complete' => 0, 'users_started' => 0, 'users_completed' => 0],
        'other_employment' => ['fields' => 0, 'complete' => 0, 'users_started' => 0, 'users_completed' => 0],
        'ppe' => ['fields' => 0, 'complete' => 0, 'users_started' => 0, 'users_completed' => 0],
        'decontamination' => ['fields' => 0, 'complete' => 0, 'users_started' => 0, 'users_completed' => 0],
        'health' => ['fields' => 0, 'complete' => 0, 'users_started' => 0, 'users_completed' => 0],
        'lifestyle' => ['fields' => 0, 'complete' => 0, 'users_started' => 0, 'users_completed' => 0],
      ];
      
      foreach ($field_counts as $field => $count) {
        foreach ($section_summaries as $section => &$summary) {
          if (strpos($field, $section . '.') === 0) {
            $summary['fields']++;
            if ($count >= $total_records) {
              $summary['complete']++;
            }
            if ($count > 0) {
              $summary['users_started'] = max($summary['users_started'], $count);
            }
          }
        }
      }
      
      // Calculate section started and completed from nfr_section_completion table
      for ($i = 1; $i <= 9; $i++) {
        $started_count = 0;
        $completed_count = 0;
        foreach ($user_section_completion as $uid => $sections) {
          if (isset($sections[$i])) {
            $started_count++;
            if ($sections[$i]->completed) {
              $completed_count++;
            }
          }
        }
        $section_keys = ['demographics', 'work_history', 'exposure', 'military', 'other_employment', 'ppe', 'decontamination', 'health', 'lifestyle'];
        if (isset($section_keys[$i - 1])) {
          // Use section_completion data if available, otherwise use field tracking data
          if ($started_count > 0) {
            $section_summaries[$section_keys[$i - 1]]['users_started'] = $started_count;
            $section_summaries[$section_keys[$i - 1]]['users_completed'] = $completed_count;
          }
        }
      }
      
      // Build HTML output with Chart.js
      $output = '<div class="container-fluid">';
      $output .= '<div class="card card-forseti mb-4">';
      $output .= '<div class="card-body">';
      $output .= '<h1 class="mb-3">NFR Fill Rate Dashboard</h1>';
      $output .= '<p class="lead"><strong>Total Records Analyzed:</strong> ' . $total_records . '</p>';
      $output .= '<p class="text-muted">This dashboard tracks EVERY field from both the User Profile and Enrollment Questionnaire, showing completion rates and value distributions.</p>';
      $output .= '</div></div>';
      
      // =============================================================================
      // TABLE STATISTICS SECTION
      // =============================================================================
      $output .= '<div class="card card-forseti mb-4" style="border-left: 4px solid #2196F3;">';
      $output .= '<div class="card-body">';
      $output .= '<h2 class="h4 mb-4">📊 Database Table Statistics</h2>';
      $output .= '<p class="mb-3">Summary statistics for all NFR database tables showing record counts and field tracking coverage.</p>';
      
      $output .= '<div class="table-responsive">';
      $output .= '<table class="table table-striped table-hover">';
      $output .= '<thead class="table-dark">';
      $output .= '<tr>';
      $output .= '<th>Table Name</th>';
      $output .= '<th class="text-center">Records</th>';
      $output .= '<th class="text-center">Total Fields</th>';
      $output .= '<th class="text-center">Tracked Fields</th>';
      $output .= '<th class="text-center">Complete Fields</th>';
      $output .= '<th class="text-center">Completeness</th>';
      $output .= '</tr></thead><tbody>';
      
      foreach ($table_stats as $table_name => $stats) {
        $completeness_badge = 'secondary';
        if (isset($stats['completeness_pct'])) {
          $completeness_badge = $stats['completeness_pct'] >= 90 ? 'success' : ($stats['completeness_pct'] >= 75 ? 'warning' : 'danger');
        }
        
        $output .= '<tr>';
        $output .= '<td><code>' . htmlspecialchars($table_name) . '</code></td>';
        $output .= '<td class="text-center">' . number_format($stats['record_count']) . '</td>';
        $output .= '<td class="text-center">' . $stats['field_count'] . '</td>';
        $output .= '<td class="text-center">' . $stats['tracked_fields'] . '</td>';
        $output .= '<td class="text-center">' . ($stats['complete_fields'] ?? 'N/A') . '</td>';
        $output .= '<td class="text-center">';
        if (isset($stats['completeness_pct'])) {
          $output .= '<span class="badge bg-' . $completeness_badge . '">' . $stats['completeness_pct'] . '%</span>';
        } else {
          $output .= '<span class="text-muted">-</span>';
        }
        $output .= '</td>';
        $output .= '</tr>';
      }
      
      $output .= '</tbody></table>';
      $output .= '</div>'; // table-responsive
      $output .= '</div></div>'; // card-body, card
      
      // =============================================================================
      // PROFILE DATASET SUMMARY
      // =============================================================================
      $output .= '<div class="card card-forseti mb-4" style="border-left: 4px solid #9C27B0;">';
      $output .= '<div class="card-body">';
      $output .= '<h2 class="h4 mb-4">👥 Profile Dataset Summary</h2>';
      $output .= '<p class="mb-4"><strong>Total Profiles:</strong> ' . number_format($profile_summary['total_profiles']) . '</p>';
      
      $output .= '<div class="row g-4">';
      
      // Sex Distribution
      if (!empty($profile_summary['sex_distribution'])) {
        $output .= '<div class="col-md-6">';
        $output .= '<div class="card bg-light h-100">';
        $output .= '<div class="card-body">';
        $output .= '<h3 class="h6 mb-3">Sex Distribution</h3>';
        $output .= '<table class="table table-sm table-borderless mb-0">';
        arsort($profile_summary['sex_distribution']);
        foreach ($profile_summary['sex_distribution'] as $sex => $count) {
          $pct = round(($count / $profile_summary['total_profiles']) * 100, 1);
          $output .= '<tr>';
          $output .= '<td>' . htmlspecialchars(ucfirst($sex)) . '</td>';
          $output .= '<td class="text-end"><strong>' . number_format($count) . '</strong></td>';
          $output .= '<td class="text-end text-muted">(' . $pct . '%)</td>';
          $output .= '</tr>';
        }
        $output .= '</table>';
        $output .= '</div></div></div>';
      }
      
      // Age Groups
      if (!empty($profile_summary['age_groups'])) {
        $output .= '<div class="col-md-6">';
        $output .= '<div class="card bg-light h-100">';
        $output .= '<div class="card-body">';
        $output .= '<h3 class="h6 mb-3">Age Distribution</h3>';
        $output .= '<table class="table table-sm table-borderless mb-0">';
        $age_order = ['Under 30', '30-39', '40-49', '50-59', '60-69', '70+'];
        foreach ($age_order as $age_group) {
          if (isset($profile_summary['age_groups'][$age_group])) {
            $count = $profile_summary['age_groups'][$age_group];
            $pct = round(($count / $profile_summary['total_profiles']) * 100, 1);
            $output .= '<tr>';
            $output .= '<td>' . htmlspecialchars($age_group) . '</td>';
            $output .= '<td class="text-end"><strong>' . number_format($count) . '</strong></td>';
            $output .= '<td class="text-end text-muted">(' . $pct . '%)</td>';
            $output .= '</tr>';
          }
        }
        $output .= '</table>';
        $output .= '</div></div></div>';
      }
      
      // State Distribution (Top 10)
      if (!empty($profile_summary['state_distribution'])) {
        $output .= '<div class="col-md-6">';
        $output .= '<div class="card bg-light h-100">';
        $output .= '<div class="card-body">';
        $output .= '<h3 class="h6 mb-3">State Distribution (Top 10)</h3>';
        $output .= '<table class="table table-sm table-borderless mb-0">';
        arsort($profile_summary['state_distribution']);
        $top_states = array_slice($profile_summary['state_distribution'], 0, 10, TRUE);
        foreach ($top_states as $state => $count) {
          $pct = round(($count / $profile_summary['total_profiles']) * 100, 1);
          $output .= '<tr>';
          $output .= '<td>' . htmlspecialchars($state) . '</td>';
          $output .= '<td class="text-end"><strong>' . number_format($count) . '</strong></td>';
          $output .= '<td class="text-end text-muted">(' . $pct . '%)</td>';
          $output .= '</tr>';
        }
        $output .= '</table>';
        $output .= '</div></div></div>';
      }
      
      // Work Status Distribution
      if (!empty($profile_summary['work_status_distribution'])) {
        $output .= '<div class="col-md-6">';
        $output .= '<div class="card bg-light h-100">';
        $output .= '<div class="card-body">';
        $output .= '<h3 class="h6 mb-3">Work Status Distribution</h3>';
        $output .= '<table class="table table-sm table-borderless mb-0">';
        arsort($profile_summary['work_status_distribution']);
        foreach ($profile_summary['work_status_distribution'] as $status => $count) {
          $pct = round(($count / $profile_summary['total_profiles']) * 100, 1);
          $output .= '<tr>';
          $output .= '<td>' . htmlspecialchars(ucwords(str_replace('_', ' ', $status))) . '</td>';
          $output .= '<td class="text-end"><strong>' . number_format($count) . '</strong></td>';
          $output .= '<td class="text-end text-muted">(' . $pct . '%)</td>';
          $output .= '</tr>';
        }
        $output .= '</table>';
        $output .= '</div></div></div>';
      }
      
      $output .= '</div>'; // row
      $output .= '</div></div>'; // card-body, card
      
      // =============================================================================
      // SECTION DATASET SUMMARIES
      // =============================================================================
      $output .= '<div class="card card-forseti mb-4" style="border-left: 4px solid #FF5722;">';
      $output .= '<div class="card-body">';
      $output .= '<h2 class="h4 mb-4">📋 Questionnaire Section Summaries</h2>';
      $output .= '<p class="mb-3">Completion statistics for each questionnaire section showing field coverage and user progress.</p>';
      
      $output .= '<div class="table-responsive">';
      $output .= '<table class="table table-striped table-hover">';
      $output .= '<thead class="table-dark">';
      $output .= '<tr>';
      $output .= '<th>Section</th>';
      $output .= '<th class="text-center">Tracked Fields</th>';
      $output .= '<th class="text-center">Complete Fields</th>';
      $output .= '<th class="text-center">Field Completeness</th>';
      $output .= '<th class="text-center">Users Started</th>';
      $output .= '<th class="text-center">Users Completed</th>';
      $output .= '<th class="text-center">Completion Rate</th>';
      $output .= '</tr></thead><tbody>';
      
      $section_labels = [
        'demographics' => 'Section 1: Demographics',
        'work_history' => 'Section 2: Work History',
        'exposure' => 'Section 3: Exposure',
        'military' => 'Section 4: Military Service',
        'other_employment' => 'Section 5: Other Employment',
        'ppe' => 'Section 6: PPE',
        'decontamination' => 'Section 7: Decontamination',
        'health' => 'Section 8: Health',
        'lifestyle' => 'Section 9: Lifestyle',
      ];
      
      foreach ($section_summaries as $section => $summary) {
        $field_completeness = $summary['fields'] > 0 ? round(($summary['complete'] / $summary['fields']) * 100, 1) : 0;
        $user_completion_rate = $summary['users_started'] > 0 ? round(($summary['users_completed'] / $summary['users_started']) * 100, 1) : 0;
        
        $field_badge = $field_completeness >= 90 ? 'success' : ($field_completeness >= 75 ? 'warning' : 'danger');
        $user_badge = $user_completion_rate >= 90 ? 'success' : ($user_completion_rate >= 75 ? 'warning' : 'danger');
        
        $output .= '<tr>';
        $output .= '<td><strong>' . htmlspecialchars($section_labels[$section] ?? ucwords(str_replace('_', ' ', $section))) . '</strong></td>';
        $output .= '<td class="text-center">' . $summary['fields'] . '</td>';
        $output .= '<td class="text-center">' . $summary['complete'] . '</td>';
        $output .= '<td class="text-center"><span class="badge bg-' . $field_badge . '">' . $field_completeness . '%</span></td>';
        $output .= '<td class="text-center">' . number_format($summary['users_started']) . '</td>';
        $output .= '<td class="text-center">' . number_format($summary['users_completed']) . '</td>';
        $output .= '<td class="text-center"><span class="badge bg-' . $user_badge . '">' . $user_completion_rate . '%</span></td>';
        $output .= '</tr>';
      }
      
      $output .= '</tbody></table>';
      $output .= '</div>'; // table-responsive
      $output .= '</div></div>'; // card-body, card
      
      // Audit Report Section
      $output .= '<div class="card card-forseti mb-4" style="border-left: 4px solid #ffc107;">';
      $output .= '<div class="card-body">';
      $output .= '<h2 class="h4 mb-3">📋 Field Coverage Audit Report</h2>';
      $output .= '<p class="mb-3">Comprehensive field-by-field comparison of requirements vs implementation vs database vs tracking.</p>';
      
      $output .= '<div class="table-responsive">';
      $output .= '<table class="table table-sm table-bordered">';
      $output .= '<thead class="table-dark">';
      $output .= '<tr>';
      $output .= '<th>Section</th>';
      $output .= '<th>Field Name</th>';
      $output .= '<th class="text-center">Required</th>';
      $output .= '<th>Database Storage</th>';
      $output .= '<th class="text-center">Tracked</th>';
      $output .= '<th class="text-center">Fill Rate</th>';
      $output .= '</tr></thead><tbody>';
      
      // Helper function to add row
      $add_row = function($section, $field_name, $required, $db_location, $tracking_key) use (&$output, $field_counts, $total_records) {
        $is_tracked = isset($field_counts[$tracking_key]);
        $fill_rate = $is_tracked ? round(($field_counts[$tracking_key] / $total_records) * 100, 1) : 0;
        $badge_class = $fill_rate >= 100 ? 'success' : ($fill_rate >= 75 ? 'warning' : ($fill_rate > 0 ? 'danger' : 'secondary'));
        
        $output .= '<tr>';
        $output .= '<td class="table-light"><strong>' . htmlspecialchars($section) . '</strong></td>';
        $output .= '<td>' . htmlspecialchars($field_name) . '</td>';
        $output .= '<td class="text-center">' . ($required ? '<span class="badge bg-info">Yes</span>' : 'No') . '</td>';
        $output .= '<td style="font-family: monospace; font-size: 0.85em;">' . htmlspecialchars($db_location) . '</td>';
        $output .= '<td class="text-center">' . ($is_tracked ? '<span class="text-success">✓</span>' : '<span class="text-danger">✗</span>') . '</td>';
        $output .= '<td class="text-center"><span class="badge bg-' . $badge_class . '">' . $fill_rate . '%</span></td>';
        $output .= '</tr>';
      };
      
      // USER PROFILE FIELDS
      $add_row('Profile', 'First Name', true, 'nfr_user_profile.first_name', 'profile.first_name');
      $add_row('Profile', 'Middle Name', false, 'nfr_user_profile.middle_name', 'profile.middle_name');
      $add_row('Profile', 'Last Name', true, 'nfr_user_profile.last_name', 'profile.last_name');
      $add_row('Profile', 'Date of Birth', true, 'nfr_user_profile.date_of_birth', 'profile.date_of_birth');
      $add_row('Profile', 'Sex', true, 'nfr_user_profile.sex', 'profile.sex');
      $add_row('Profile', 'SSN Last 4', false, 'nfr_user_profile.ssn_last_4', 'profile.ssn_last_4');
      $add_row('Profile', 'Country of Birth', true, 'nfr_user_profile.country_of_birth', 'profile.country_of_birth');
      $add_row('Profile', 'State of Birth', false, 'nfr_user_profile.state_of_birth', 'profile.state_of_birth');
      $add_row('Profile', 'City of Birth', false, 'nfr_user_profile.city_of_birth', 'profile.city_of_birth');
      $add_row('Profile', 'Address Line 1', true, 'nfr_user_profile.address_line1', 'profile.address_line1');
      $add_row('Profile', 'Address Line 2', false, 'nfr_user_profile.address_line2', 'profile.address_line2');
      $add_row('Profile', 'City', true, 'nfr_user_profile.city', 'profile.city');
      $add_row('Profile', 'State', true, 'nfr_user_profile.state', 'profile.state');
      $add_row('Profile', 'ZIP Code', true, 'nfr_user_profile.zip_code', 'profile.zip_code');
      $add_row('Profile', 'Alternate Email', false, 'nfr_user_profile.alternate_email', 'profile.alternate_email');
      $add_row('Profile', 'Mobile Phone', false, 'nfr_user_profile.mobile_phone', 'profile.mobile_phone');
      $add_row('Profile', 'Current Work Status', true, 'nfr_user_profile.current_work_status', 'profile.current_work_status');
      
      // DEMOGRAPHICS (direct columns)
      $add_row('Demographics', 'Race/Ethnicity', true, 'nfr_questionnaire.race_ethnicity (JSON)', 'demographics.race_ethnicity');
      $add_row('Demographics', 'Race Other Specify', false, 'nfr_questionnaire.race_other', 'questionnaire.race_other');
      $add_row('Demographics', 'Education Level', true, 'nfr_questionnaire.education_level', 'demographics.education_level');
      $add_row('Demographics', 'Marital Status', true, 'nfr_questionnaire.marital_status', 'demographics.marital_status');
      $add_row('Demographics', 'Height (inches)', true, 'nfr_questionnaire.height_inches', 'questionnaire.height_inches');
      $add_row('Demographics', 'Weight (pounds)', true, 'nfr_questionnaire.weight_pounds', 'questionnaire.weight_pounds');
      
      // WORK HISTORY (from normalized tables)
      $add_row('Work History', 'Number of Departments', true, 'COUNT(DISTINCT nfr_work_history.id)', 'work_history.num_departments');
      $add_row('Work History', 'Department Name', true, 'nfr_work_history.department_name', 'work_history.department_name');
      $add_row('Work History', 'Department State', true, 'nfr_work_history.department_state', 'work_history.department_state');
      $add_row('Work History', 'Department City', true, 'nfr_work_history.department_city', 'work_history.department_city');
      $add_row('Work History', 'Department FDID', false, 'nfr_work_history.department_fdid', 'work_history.department_fdid');
      $add_row('Work History', 'Start Date', true, 'nfr_work_history.start_date', 'work_history.start_date');
      $add_row('Work History', 'End Date', false, 'nfr_work_history.end_date', 'work_history.end_date');
      $add_row('Work History', 'Is Current', false, 'nfr_work_history.is_current', 'work_history.is_current');
      $add_row('Work History', 'Job Title', true, 'nfr_job_titles.job_title', 'work_history.job_title');
      $add_row('Work History', 'Employment Type', true, 'nfr_job_titles.employment_type', 'work_history.employment_type');
      $add_row('Work History', 'Responded to Incidents', true, 'nfr_job_titles.responded_to_incidents', 'work_history.responded_incidents');
      $add_row('Work History', 'Incident Types', false, 'nfr_job_titles.incident_types (JSON)', 'work_history.incident_types');
      
      // EXPOSURE (from direct columns + nfr_major_incidents table)
      $add_row('Exposure', 'AFFF Used', true, 'nfr_questionnaire.afff_used', 'exposure.afff_used');
      $add_row('Exposure', 'AFFF Times Used', false, 'nfr_questionnaire.afff_times', 'exposure.afff_times');
      $add_row('Exposure', 'AFFF First Year', false, 'nfr_questionnaire.afff_first_year', 'exposure.afff_first_year');
      $add_row('Exposure', 'Diesel Exhaust', true, 'nfr_questionnaire.diesel_exhaust', 'exposure.diesel_exhaust');
      $add_row('Exposure', 'Major Incidents Yes/No', true, 'nfr_questionnaire.major_incidents', 'exposure.major_incidents');
      $add_row('Exposure', 'Chemical Activities', false, 'nfr_questionnaire.chemical_activities (JSON)', 'exposure.chemical_activities');
      
      // MAJOR INCIDENTS (normalized table)
      $add_row('Exposure', 'Major Incidents Count', false, 'COUNT(nfr_major_incidents)', 'exposure.major_incidents_count');
      $add_row('Exposure', 'Incident Description', false, 'nfr_major_incidents.description', 'exposure.major_incident_description');
      $add_row('Exposure', 'Incident Date', false, 'nfr_major_incidents.incident_date', 'exposure.major_incident_date');
      $add_row('Exposure', 'Incident Duration', false, 'nfr_major_incidents.duration', 'exposure.major_incident_duration');
      
      // MILITARY (direct columns)
      $add_row('Military', 'Served in Military', true, 'nfr_questionnaire.military_service', 'questionnaire.military_service');
      $add_row('Military', 'Military Branch', false, 'nfr_questionnaire.military_branch', 'questionnaire.military_branch');
      $add_row('Military', 'Military Years', false, 'nfr_questionnaire.military_years', 'questionnaire.military_years');
      $add_row('Military', 'Start Date', false, 'nfr_questionnaire.military_start_date', 'military.start_date');
      $add_row('Military', 'End Date', false, 'nfr_questionnaire.military_end_date', 'military.end_date');
      $add_row('Military', 'Currently Serving', false, 'nfr_questionnaire.military_currently_serving', 'military.currently_serving');
      $add_row('Military', 'Was Firefighter', false, 'nfr_questionnaire.military_was_firefighter', 'military.was_firefighter');
      
      // OTHER EMPLOYMENT (JSON column + normalized table)
      $add_row('Other Employment', 'Had Other Jobs', true, 'nfr_questionnaire.other_employment_data.had_other_jobs (JSON)', 'other_employment.had_other_jobs');
      $add_row('Other Employment', 'Jobs Count (JSON)', false, 'nfr_questionnaire.other_employment_data.jobs (JSON)', 'other_employment.jobs_count');
      $add_row('Other Employment', 'Job Title (JSON)', false, 'nfr_questionnaire.other_employment_data.jobs[].job_title (JSON)', 'other_employment.job_title');
      $add_row('Other Employment', 'Had Exposure (JSON)', false, 'nfr_questionnaire.other_employment_data.jobs[].had_exposure (JSON)', 'other_employment.had_exposure');
      
      // OTHER EMPLOYMENT (normalized table)
      $add_row('Other Employment', 'Jobs Count (Table)', false, 'COUNT(nfr_other_employment)', 'other_employment.jobs_count_table');
      $add_row('Other Employment', 'Occupation', false, 'nfr_other_employment.occupation', 'other_employment.occupation');
      $add_row('Other Employment', 'Industry', false, 'nfr_other_employment.industry', 'other_employment.industry');
      $add_row('Other Employment', 'Start Year', false, 'nfr_other_employment.start_year', 'other_employment.start_year');
      $add_row('Other Employment', 'End Year', false, 'nfr_other_employment.end_year', 'other_employment.end_year');
      $add_row('Other Employment', 'Exposures', false, 'nfr_other_employment.exposures', 'other_employment.exposures');
      $add_row('Other Employment', 'Exposures Other', false, 'nfr_other_employment.exposures_other', 'other_employment.exposures_other');
      
      // PPE (from direct columns: ppe_*_ever_used, ppe_*_year_started)
      $ppe_items = [
        'SCBA' => 'scba',
        'Turnout Coat' => 'turnout_coat',
        'Turnout Pants' => 'turnout_pants',
        'Gloves' => 'gloves',
        'Helmet' => 'helmet',
        'Boots' => 'boots',
        'Nomex Hood' => 'nomex_hood',
        'Wildland Clothing' => 'wildland_clothing'
      ];
      foreach ($ppe_items as $label => $key) {
        $add_row('PPE', $label . ' - Ever Used', true, 'nfr_questionnaire.ppe_' . $key . '_ever_used', 'ppe.' . $key . '.ever_used');
        $add_row('PPE', $label . ' - Year Started', false, 'nfr_questionnaire.ppe_' . $key . '_year_started', 'ppe.' . $key . '.year_started');
      }
      
      $scba_scenarios = [
        'During Suppression', 'During Overhaul'
      ];
      foreach ($scba_scenarios as $idx => $scenario) {
        $key = ['during_suppression', 'during_overhaul'][$idx];
        $add_row('PPE', 'SCBA ' . $scenario, true, 'nfr_questionnaire.ppe_scba_' . $key, 'ppe.scba_' . $key);
      }
      
      // DECONTAMINATION (from direct columns: decon_*)
      $decon_practices = [
        'Washed Hands/Face' => 'washed_hands_face',
        'Changed Gear at Scene' => 'changed_gear_at_scene',
        'Showered at Station' => 'showered_at_station',
        'Laundered Gear' => 'laundered_gear',
        'Used Wet Wipes' => 'used_wet_wipes'
      ];
      foreach ($decon_practices as $label => $key) {
        $add_row('Decontamination', $label, true, 'nfr_questionnaire.decon_' . $key, 'decontamination.' . $key);
      }
      $add_row('Decontamination', 'Department Had SOPs', true, 'nfr_questionnaire.decon_department_had_sops', 'decontamination.department_had_sops');
      $add_row('Decontamination', 'SOP Year Implemented', false, 'nfr_questionnaire.decon_sops_year_implemented', 'decontamination.sop_year_implemented');
      
      // HEALTH (direct columns + normalized table)
      $health_conditions = [
        'Heart Disease' => 'heart_disease',
        'COPD' => 'copd',
        'Asthma' => 'asthma',
        'Diabetes' => 'diabetes'
      ];
      foreach ($health_conditions as $label => $key) {
        $add_row('Health', $label, true, 'nfr_questionnaire.health_' . $key, 'health.' . $key);
      }
      $add_row('Health', 'Cancer Diagnosed', true, 'nfr_questionnaire.cancer_diagnosis', 'questionnaire.cancer_diagnosis');
      $add_row('Health', 'Family Cancer History', false, 'nfr_questionnaire.family_cancer_history (JSON)', 'health.family_cancer_history');
      
      // CANCER DIAGNOSES (normalized table)
      $add_row('Health', 'Cancer Count (Table)', false, 'COUNT(nfr_cancer_diagnoses)', 'health.cancer_count_table');
      $add_row('Health', 'Cancer Type (Table)', false, 'nfr_cancer_diagnoses.cancer_type', 'health.cancer_type_table');
      $add_row('Health', 'Year Diagnosed (Table)', false, 'nfr_cancer_diagnoses.year_diagnosed', 'health.year_diagnosed_table');
      
      // LIFESTYLE (direct column + JSON columns)
      $add_row('Lifestyle', 'Smoking Status', true, 'nfr_questionnaire.smoking_history.smoking_status (JSON)', 'lifestyle.smoking_status');
      
      $tobacco_types = [
        'Cigarettes', 'Cigars', 'Pipes', 'Chewing Tobacco', 'E-Cigarettes'
      ];
      foreach ($tobacco_types as $idx => $type) {
        $key = ['cigarettes', 'cigars', 'pipes', 'chewing_tobacco', 'e_cigarettes'][$idx];
        $add_row('Lifestyle', $type . ' - Ever Used', false, 'nfr_questionnaire.smoking_history.' . $key . '.ever_used (JSON)', 'lifestyle.' . $key . '_ever_used');
        $add_row('Lifestyle', $type . ' - Frequency', false, 'nfr_questionnaire.smoking_history.' . $key . '.frequency (JSON)', 'lifestyle.' . $key . '_frequency');
      }
      
      $add_row('Lifestyle', 'Alcohol Frequency', true, 'nfr_questionnaire.alcohol_use', 'questionnaire.alcohol_use');
      $add_row('Lifestyle', 'Physical Activity Days', true, 'nfr_questionnaire.physical_activity_days', 'lifestyle.physical_activity_days');
      
      // CONSENT (from nfr_consent table)
      $add_row('Consent', 'Consented to Participate', true, 'nfr_consent.consented_to_participate', 'consent.participate');
      $add_row('Consent', 'Consented to Registry Linkage', true, 'nfr_consent.consented_to_registry_linkage', 'consent.registry_linkage');
      $add_row('Consent', 'Electronic Signature', true, 'nfr_consent.electronic_signature', 'consent.electronic_signature');
      $add_row('Consent', 'IP Address', false, 'nfr_consent.consent_ip_address', 'consent.ip_address');
      $add_row('Consent', 'Timestamp', true, 'nfr_consent.consent_timestamp', 'consent.timestamp');
      
      // PROGRESS TRACKING (from nfr_section_completion table)
      $add_row('Progress', 'Sections Completed Count', false, 'COUNT(nfr_section_completion WHERE completed=1)', 'progress.sections_completed_count');
      for ($i = 1; $i <= 9; $i++) {
        $add_row('Progress', "Section {$i} Completed", false, "nfr_section_completion.completed (section={$i})", "progress.section_{$i}_completed");
        $add_row('Progress', "Section {$i} Completed At", false, "nfr_section_completion.completed_at (section={$i})", "progress.section_{$i}_completed_at");
      }
      
      $output .= '</tbody></table>';
      $output .= '</div>'; // table-responsive
      
      $output .= '<div class="alert alert-info mt-3" style="color: #212529;">';
      $output .= '<p class="mb-2"><strong>Legend:</strong></p>';
      $output .= '<p class="mb-2 small">✓ = Field is tracked | ✗ = Field not tracked</p>';
      $output .= '<p class="mb-2 small"><span class="badge bg-success">100%</span> Complete | <span class="badge bg-warning text-dark">75-99%</span> Good | <span class="badge bg-danger">1-74%</span> Incomplete | <span class="badge bg-secondary">0%</span> No data</p>';
      $output .= '<p class="mb-0 small"><strong>Storage Strategy:</strong> The NFR system uses multiple storage strategies for data organization:</p>';
      $output .= '<ul class="small mb-0">';
      $output .= '<li><strong>Profile Data:</strong> Direct columns in <code>nfr_user_profile</code> table (26 fields)</li>';
      $output .= '<li><strong>Questionnaire Data:</strong> Mix of direct columns and JSON fields in <code>nfr_questionnaire</code> table</li>';
      $output .= '<li><strong>Work History (Section 2):</strong> Normalized tables (<code>nfr_work_history</code> → <code>nfr_job_titles</code> → <code>nfr_incident_frequency</code>)</li>';
      $output .= '<li><strong>Exposure (Section 3):</strong> JSON data column + <code>nfr_major_incidents</code> table for detailed incident tracking</li>';
      $output .= '<li><strong>Other Employment (Section 5):</strong> JSON column + <code>nfr_other_employment</code> normalized table</li>';
      $output .= '<li><strong>PPE/Decontamination/Smoking:</strong> Dedicated JSON columns (<code>ppe_practices</code>, <code>decon_practices</code>, <code>smoking_history</code>)</li>';
      $output .= '<li><strong>Cancer Diagnoses:</strong> JSON column + <code>nfr_cancer_diagnoses</code> table for multiple diagnoses per user</li>';
      $output .= '<li><strong>Consent:</strong> <code>nfr_consent</code> table with signature tracking</li>';
      $output .= '<li><strong>Progress:</strong> <code>nfr_section_completion</code> table tracking completion by section</li>';
      $output .= '<li><strong>Additional Details:</strong> <code>data</code> JSON column for exposure, military, health, and lifestyle extended fields</li>';
      $output .= '</ul>';
      $output .= '</div>';
      $output .= '</div></div>'; // card-body, card
      
      // Summary Statistics
      $output .= '<div class="card card-forseti mb-4" style="border-left: 4px solid #4CAF50;">';
      $output .= '<div class="card-body">';
      $output .= '<h2 class="h4 mb-4">📊 Summary Statistics</h2>';
      $output .= '<div class="row g-3">';
      $output .= '<div class="col-md-4"><div class="card bg-light h-100"><div class="card-body text-center">';
      $output .= '<h3 class="h5 text-muted mb-2">Total Fields Tracked</h3>';
      $output .= '<div class="display-6 text-primary">' . $total_fields . '</div>';
      $output .= '</div></div></div>';
      $output .= '<div class="col-md-4"><div class="card bg-light h-100"><div class="card-body text-center">';
      $output .= '<h3 class="h5 text-muted mb-2">Fields at 100%</h3>';
      $output .= '<div class="display-6 text-success">' . $fields_at_100 . '</div>';
      $output .= '</div></div></div>';
      $output .= '<div class="col-md-4"><div class="card bg-light h-100"><div class="card-body text-center">';
      $output .= '<h3 class="h5 text-muted mb-2">Fields Below 100%</h3>';
      $output .= '<div class="display-6 ' . (count($fields_below_100) > 0 ? 'text-danger' : 'text-success') . '">' . count($fields_below_100) . '</div>';
      $output .= '</div></div></div>';
      $output .= '</div>'; // row
      $output .= '</div></div>'; // card-body, card
      
      $sections = [
        'profile' => 'USER PROFILE (5-Minute Form)',
        'questionnaire' => 'QUESTIONNAIRE DIRECT COLUMNS',
        'demographics' => 'DEMOGRAPHICS (Section 1)',
        'work_history' => 'WORK HISTORY (Section 2)',
        'exposure' => 'EXPOSURE (Section 3)',
        'military' => 'MILITARY SERVICE (Section 4)',
        'other_employment' => 'OTHER EMPLOYMENT (Section 5)',
        'ppe' => 'PERSONAL PROTECTIVE EQUIPMENT (Section 6)',
        'decontamination' => 'DECONTAMINATION (Section 7)',
        'health' => 'HEALTH CONDITIONS (Section 8)',
        'lifestyle' => 'LIFESTYLE (Section 9)',
        'consent' => 'CONSENT TRACKING',
        'progress' => 'SECTION COMPLETION TRACKING',
      ];
      
      $chart_data_js = [];
      
      foreach ($sections as $section_key => $section_name) {
        $output .= '<div class="card card-forseti mb-4">';
        $output .= '<div class="card-header">';
        $output .= '<h2 class="h5 mb-0">' . $section_name . '</h2>';
        $output .= '</div>';
        $output .= '<div class="card-body">';
        
        $section_has_fields = FALSE;
        foreach ($field_counts as $field => $count) {
          if (strpos($field, $section_key . '.') === 0) {
            $section_has_fields = TRUE;
            $pct = round(($count / $total_records) * 100, 1);
            $badge_class = $pct >= 100 ? 'success' : ($pct >= 90 ? 'warning' : 'danger');
            
            // Create chart for this field
            $chart_id = 'chart_' . str_replace('.', '_', $field);
            $output .= '<div class="mb-4">';
            $output .= '<h3 class="h6">' . htmlspecialchars($field) . '</h3>';
            $output .= '<p class="mb-2"><strong>Fill Rate:</strong> <span class="badge bg-' . $badge_class . '">' . $count . ' / ' . $total_records . ' (' . $pct . '%)</span></p>';
            
            if (isset($value_distributions[$field])) {
              $output .= '<div style="position: relative; height: 300px; margin-top: 10px;">';
              $output .= '<canvas id="' . $chart_id . '"></canvas>';
              $output .= '</div>';
              
              // Prepare data for this chart
              $labels = array_keys($value_distributions[$field]);
              $values = array_values($value_distributions[$field]);
              
              // Limit very long labels and ensure strings
              $labels = array_map(function($label) {
                $label = (string)$label;
                return strlen($label) > 30 ? substr($label, 0, 27) . '...' : $label;
              }, $labels);
              
              $chart_data_js[] = [
                'id' => $chart_id,
                'labels' => $labels,
                'data' => $values,
                'field' => $field,
              ];
            }
            else {
              $output .= '<p class="text-muted fst-italic">No value distribution data available</p>';
            }
            
            $output .= '</div>';
          }
        }
        
        if (!$section_has_fields) {
          $output .= '<p class="text-muted fst-italic">No fields analyzed in this section</p>';
        }
        
        $output .= '</div></div>'; // card-body, card
      }
      
      // Fields with Incomplete Data Section
      if (count($fields_below_100) > 0) {
        $output .= '<div class="card card-forseti mb-4" style="border-left: 4px solid #F44336;">';
        $output .= '<div class="card-body">';
        $output .= '<h2 class="h4 text-danger mb-3">⚠️ Fields with Incomplete Data</h2>';
        $output .= '<p class="mb-3">The following fields have less than 100% completion. Charts show the distribution of actual responses.</p>';
        
        foreach ($fields_below_100 as $item) {
          $field = $item['field'];
          $count = $item['count'];
          $pct = $item['pct'];
          $missing = $total_records - $count;
          $missing_pct = round(($missing / $total_records) * 100, 1);
          $color = $pct >= 90 ? '#FF9800' : '#F44336';
          
          $chart_id = 'chart_' . str_replace('.', '_', $field);
          
          $output .= '<div class="chart-container" style="border: 2px solid ' . $color . ';">';
          $output .= '<h3 style="color: ' . $color . ';">' . htmlspecialchars($field) . '</h3>';
          $output .= '<div style="background: #fff; padding: 10px; margin: 10px 0; border-radius: 4px;">';
          $output .= '<table style="width: 100%; border-collapse: collapse;">';
          $output .= '<tr>';
          $output .= '<td style="padding: 8px;"><strong>Completed:</strong></td>';
          $output .= '<td style="padding: 8px; color: #4CAF50;">' . $count . ' (' . $pct . '%)</td>';
          $output .= '<td style="padding: 8px;"><strong>Missing:</strong></td>';
          $output .= '<td style="padding: 8px; color: #F44336;">' . $missing . ' (' . $missing_pct . '%)</td>';
          $output .= '<td style="padding: 8px;"><strong>Total:</strong></td>';
          $output .= '<td style="padding: 8px;">' . $total_records . '</td>';
          $output .= '</tr>';
          $output .= '</table>';
          $output .= '</div>';
          
          if (isset($value_distributions[$field])) {
            $output .= '<p><strong>Value Distribution (for completed responses only):</strong></p>';
            $output .= '<div class="chart-wrapper">';
            $output .= '<canvas id="' . $chart_id . '_incomplete"></canvas>';
            $output .= '</div>';
            
            // Add chart data with "_incomplete" suffix
            $labels = array_keys($value_distributions[$field]);
            $values = array_values($value_distributions[$field]);
            $labels = array_map(function($label) {
              $label = (string)$label;
              return strlen($label) > 30 ? substr($label, 0, 27) . '...' : $label;
            }, $labels);
            
            $chart_data_js[] = [
              'id' => $chart_id . '_incomplete',
              'labels' => $labels,
              'data' => $values,
              'field' => $field,
            ];
            
            // Also show a table of values
            $output .= '<div class="table-responsive mt-3">';
            $output .= '<table class="table table-sm table-bordered">';
            $output .= '<thead class="table-light">';
            $output .= '<tr>';
            $output .= '<th>Value</th>';
            $output .= '<th class="text-center">Count</th>';
            $output .= '<th class="text-center">% of Completed</th>';
            $output .= '<th class="text-center">% of Total</th>';
            $output .= '</tr></thead><tbody>';
            
            foreach ($value_distributions[$field] as $val => $val_count) {
              $pct_of_completed = round(($val_count / $count) * 100, 1);
              $pct_of_total = round(($val_count / $total_records) * 100, 1);
              $output .= '<tr>';
              $output .= '<td>' . htmlspecialchars((string)$val) . '</td>';
              $output .= '<td class="text-center">' . $val_count . '</td>';
              $output .= '<td class="text-center">' . $pct_of_completed . '%</td>';
              $output .= '<td class="text-center">' . $pct_of_total . '%</td>';
              $output .= '</tr>';
            }
            
            $output .= '</tbody></table>';
            $output .= '</div>';
          }
          else {
            $output .= '<p class="text-muted fst-italic">No value distribution data available</p>';
          }
          
          $output .= '</div>';
        }
        $output .= '</div></div>'; // card-body, card
      }
      
      $output .= '<p class="mt-3"><a href="/admin/nfr/validation" class="btn btn-secondary">← Back to Validation Dashboard</a></p>';
      $output .= '</div>'; // container-fluid
      
      return [
        '#theme' => 'nfr_admin_page',
        '#page_id' => 'fill-rates',
        '#content' => [
          '#type' => 'inline_template',
          '#template' => $output,
        ],
        '#attached' => [
          'library' => [
            'nfr/admin',
            'nfr/fill_rates',
          ],
          'html_head' => [
            [
              [
                '#type' => 'html_tag',
                '#tag' => 'script',
                '#attributes' => ['src' => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'],
              ],
              'chartjs',
            ],
          ],
          'drupalSettings' => [
            'nfr_fill_rates' => [
              'chart_data' => $chart_data_js,
            ],
          ],
        ],
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        '#theme' => 'nfr_admin_page',
        '#page_id' => 'fill-rates-error',
        '#content' => [
          '#markup' => '<div class="error"><h1>Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><p><a href="/admin/nfr/validation">← Back to Validation Dashboard</a></p></div>',
        ],
        '#attached' => [
          'library' => ['nfr/admin'],
        ],
      ];
    }
  }

  /**
   * Get total number of users in the system.
   */
  private function getTotalUsers(): int {
    $database = \Drupal::database();
    $query = $database->select('users_field_data', 'u')
      ->condition('u.uid', 0, '>')
      ->condition('u.status', 1);
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Get count of test users (users with @stlouisintegration.com email).
   */
  private function getTestUsersCount(): int {
    $database = \Drupal::database();
    $query = $database->select('users_field_data', 'u')
      ->condition('u.uid', 0, '>')
      ->condition('u.status', 1)
      ->condition('u.mail', '%@stlouisintegration.com', 'LIKE');
    return (int) $query->countQuery()->execute()->fetchField();
  }

}
