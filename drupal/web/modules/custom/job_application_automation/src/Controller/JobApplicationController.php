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
   * Returns a home page for authenticated users.
   *
   * @return array
   *   A simple renderable array with basic information about the module.
   */
  public function home() {
    $current_user = $this->currentUser();
    
    $build = [];
    $build['#markup'] = '<div class="job-application-home">';
    $build['#markup'] .= '<h2>Welcome to Job Application Automation</h2>';
    $build['#markup'] .= '<p>Hello, ' . $current_user->getDisplayName() . '!</p>';
    $build['#markup'] .= '<p>This system helps you track and manage job applications efficiently.</p>';
    
    // Add some basic navigation for authenticated users
    $build['#markup'] .= '<h3>Available Actions:</h3>';
    $build['#markup'] .= '<ul>';
    
    // Check if user has permission to view job applications
    if ($current_user->hasPermission('view job applications')) {
      $build['#markup'] .= '<li>View your job applications</li>';
    }
    
    // Check if user has permission to create job applications
    if ($current_user->hasPermission('create job applications')) {
      $add_url = Url::fromRoute('job_application_automation.add');
      $add_link = Link::fromTextAndUrl('Create new job application', $add_url);
      $build['#markup'] .= '<li>' . $add_link->toString() . '</li>';
    }
    
    // Check if user has admin permissions
    if ($current_user->hasPermission('administer job applications')) {
      $dashboard_url = Url::fromRoute('job_application_automation.dashboard');
      $dashboard_link = Link::fromTextAndUrl('Access Admin Dashboard', $dashboard_url);
      $build['#markup'] .= '<li>' . $dashboard_link->toString() . '</li>';
    }
    
    $build['#markup'] .= '</ul>';
    
    // Add some basic information about the system
    $build['#markup'] .= '<h3>Features:</h3>';
    $build['#markup'] .= '<ul>';
    $build['#markup'] .= '<li>Track job application status and progress</li>';
    $build['#markup'] .= '<li>Automated workflow management</li>';
    $build['#markup'] .= '<li>Integration with external job boards</li>';
    $build['#markup'] .= '<li>Comprehensive reporting and analytics</li>';
    $build['#markup'] .= '</ul>';
    
    $build['#markup'] .= '</div>';
    
    return $build;
  }

  /**
   * Returns an administrative dashboard for job applications.
   *
   * @return array
   *   A renderable array for the administrative dashboard.
   */
  public function dashboard() {
    $build = [];
    $build['#markup'] = '<div class="job-application-dashboard">';
    $build['#markup'] .= '<h2>Job Application Administrative Dashboard</h2>';
    $build['#markup'] .= '<p>Welcome to the administrative interface for job application management.</p>';
    
    // Add dashboard sections
    $build['#markup'] .= '<div class="dashboard-sections">';
    
    $build['#markup'] .= '<div class="dashboard-section">';
    $build['#markup'] .= '<h3>Quick Stats</h3>';
    $build['#markup'] .= '<p>Total Applications: <strong>0</strong></p>';
    $build['#markup'] .= '<p>Active Applications: <strong>0</strong></p>';
    $build['#markup'] .= '<p>Completed Applications: <strong>0</strong></p>';
    $build['#markup'] .= '</div>';
    
    $build['#markup'] .= '<div class="dashboard-section">';
    $build['#markup'] .= '<h3>Recent Activity</h3>';
    $build['#markup'] .= '<p>No recent activity to display.</p>';
    $build['#markup'] .= '</div>';
    
    $build['#markup'] .= '<div class="dashboard-section">';
    $build['#markup'] .= '<h3>Administrative Actions</h3>';
    
    // Add action links
    $add_url = Url::fromRoute('job_application_automation.add');
    $add_link = Link::fromTextAndUrl('Add New Job Application', $add_url);
    $build['#markup'] .= '<p>' . $add_link->toString() . '</p>';
    
    $build['#markup'] .= '<p><em>Additional management features will be available as the module develops.</em></p>';
    $build['#markup'] .= '</div>';
    
    $build['#markup'] .= '</div>'; // Close dashboard-sections
    $build['#markup'] .= '</div>'; // Close dashboard
    
    return $build;
  }

}