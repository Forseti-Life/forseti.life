<?php

namespace Drupal\jobhunter_tester\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Url;

/**
 * Controller for testing Job Hunter routes.
 */
class JobHunterTesterController extends ControllerBase {

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a JobHunterTesterController object.
   */
  public function __construct(RouteProviderInterface $route_provider, ClientInterface $http_client, RequestStack $request_stack) {
    $this->routeProvider = $route_provider;
    $this->httpClient = $http_client;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router.route_provider'),
      $container->get('http_client'),
      $container->get('request_stack')
    );
  }

  /**
   * Test all Job Hunter routes.
   */
  public function testPage() {
    $build = [];
    
    // Get base URL
    $request = $this->requestStack->getCurrentRequest();
    $base_url = $request->getSchemeAndHttpHost();
    
    // Get all routes
    $all_routes = $this->routeProvider->getAllRoutes();
    $job_hunter_routes = [];
    
    // Filter for job_hunter routes
    foreach ($all_routes as $route_name => $route) {
      if (strpos($route_name, 'job_hunter.') === 0) {
        $path = $route->getPath();
        $methods = $route->getMethods();
        
        // Only test GET routes (skip POST, PUT, DELETE)
        if (empty($methods) || in_array('GET', $methods)) {
          $job_hunter_routes[$route_name] = [
            'name' => $route_name,
            'path' => $path,
            'title' => $route->getDefault('_title') ?? 'No Title',
          ];
        }
      }
    }
    
    // Sort routes by name
    ksort($job_hunter_routes);
    
    $build['summary'] = [
      '#markup' => '<div class="messages messages--status">' .
        '<h2>Job Hunter Route Testing</h2>' .
        '<p>Found ' . count($job_hunter_routes) . ' GET-accessible Job Hunter routes.</p>' .
        '<p><strong>Base URL:</strong> ' . $base_url . '</p>' .
        '</div>',
    ];
    
    // Test each route
    $results = [];
    foreach ($job_hunter_routes as $route_info) {
      $url = $base_url . $route_info['path'];
      $status = 'pending';
      $status_code = null;
      $error_message = '';
      
      // Skip routes with parameters (they need specific values)
      if (strpos($route_info['path'], '{') !== FALSE) {
        $status = 'skipped';
        $error_message = 'Route requires parameters';
      }
      else {
        try {
          // Make HTTP request with current session cookies
          $response = $this->httpClient->request('GET', $url, [
            'cookies' => TRUE,
            'allow_redirects' => TRUE,
            'http_errors' => FALSE,
            'timeout' => 10,
          ]);
          
          $status_code = $response->getStatusCode();
          
          if ($status_code === 200) {
            $status = 'success';
          }
          elseif ($status_code >= 300 && $status_code < 400) {
            $status = 'redirect';
            $error_message = 'Redirected (code: ' . $status_code . ')';
          }
          elseif ($status_code === 403) {
            $status = 'forbidden';
            $error_message = 'Access Denied (403)';
          }
          elseif ($status_code === 404) {
            $status = 'not-found';
            $error_message = 'Not Found (404)';
          }
          else {
            $status = 'error';
            $error_message = 'HTTP ' . $status_code;
          }
        }
        catch (\Exception $e) {
          $status = 'error';
          $error_message = $e->getMessage();
        }
      }
      
      $results[] = [
        'name' => $route_info['name'],
        'path' => $route_info['path'],
        'url' => $url,
        'title' => $route_info['title'],
        'status' => $status,
        'status_code' => $status_code,
        'error_message' => $error_message,
      ];
    }
    
    // Count results
    $success_count = count(array_filter($results, fn($r) => $r['status'] === 'success'));
    $error_count = count(array_filter($results, fn($r) => $r['status'] === 'error'));
    $forbidden_count = count(array_filter($results, fn($r) => $r['status'] === 'forbidden'));
    $not_found_count = count(array_filter($results, fn($r) => $r['status'] === 'not-found'));
    $redirect_count = count(array_filter($results, fn($r) => $r['status'] === 'redirect'));
    $skipped_count = count(array_filter($results, fn($r) => $r['status'] === 'skipped'));
    
    $build['stats'] = [
      '#markup' => '<div style="background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px;">' .
        '<h3>Test Results Summary</h3>' .
        '<ul style="list-style: none; padding: 0;">' .
        '<li style="color: green;">✓ Success (200): ' . $success_count . '</li>' .
        '<li style="color: orange;">⚠ Redirects: ' . $redirect_count . '</li>' .
        '<li style="color: red;">✗ Errors: ' . $error_count . '</li>' .
        '<li style="color: orange;">⊗ Forbidden (403): ' . $forbidden_count . '</li>' .
        '<li style="color: red;">⊗ Not Found (404): ' . $not_found_count . '</li>' .
        '<li style="color: gray;">− Skipped (parameters): ' . $skipped_count . '</li>' .
        '</ul>' .
        '</div>',
    ];
    
    // Build results table
    $rows = [];
    foreach ($results as $result) {
      $status_color = match($result['status']) {
        'success' => 'green',
        'redirect' => 'orange',
        'forbidden' => 'orange',
        'not-found' => 'red',
        'error' => 'red',
        'skipped' => 'gray',
        default => 'black',
      };
      
      $status_icon = match($result['status']) {
        'success' => '✓',
        'redirect' => '↗',
        'forbidden' => '⊗',
        'not-found' => '⊗',
        'error' => '✗',
        'skipped' => '−',
        default => '?',
      };
      
      $url_link = $result['status'] !== 'skipped' 
        ? '<a href="' . $result['url'] . '" target="_blank">' . $result['path'] . '</a>'
        : $result['path'];
      
      $rows[] = [
        'data' => [
          ['data' => $status_icon . ' ' . strtoupper($result['status']), 'style' => 'color: ' . $status_color . '; font-weight: bold;'],
          ['data' => $result['status_code'] ?? '−'],
          ['data' => $result['name']],
          ['data' => ['#markup' => $url_link]],
          ['data' => $result['title']],
          ['data' => $result['error_message']],
        ],
      ];
    }
    
    $build['results'] = [
      '#type' => 'table',
      '#header' => [
        'Status',
        'Code',
        'Route Name',
        'Path',
        'Title',
        'Error',
      ],
      '#rows' => $rows,
      '#attributes' => [
        'style' => 'width: 100%; margin-top: 20px;',
      ],
      '#attached' => [
        'library' => [
          'system/admin',
        ],
      ],
    ];
    
    return $build;
  }

}
