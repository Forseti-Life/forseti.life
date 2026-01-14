<?php

namespace Drupal\institutional_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for Institutional Management pages.
 */
class InstitutionalController extends ControllerBase {

  /**
   * Landing page for institutional management.
   *
   * @return array
   *   A render array.
   */
  public function landing() {
    $build = [];

    // Hero Section
    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bg-primary', 'text-white', 'py-5', 'mb-5', 'rounded']],
      'inner' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['container', 'text-center']],
        'title' => [
          '#markup' => '<h1 class="display-4 fw-bold mb-3">' . $this->t('Family Safety Groups') . '</h1>',
        ],
        'subtitle' => [
          '#markup' => '<p class="lead mb-4">' . $this->t('Keep your family safe together with coordinated safety monitoring and real-time alerts') . '</p>',
        ],
        'cta' => [
          '#markup' => '<div class="mt-4">
            <a href="/group/add/family" class="btn btn-light btn-lg">
              <i class="fas fa-users me-2"></i>' . $this->t('Create Your Family Group') . '
            </a>
          </div>',
        ],
      ],
    ];

    // Features Overview
    $build['features'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'my-5']],
      'title' => [
        '#markup' => '<h2 class="text-center mb-5">' . $this->t('Family Safety Features') . '</h2>',
      ],
      'grid' => [
        '#markup' => '
          <div class="row g-4">
            <div class="col-md-6 col-lg-3">
              <div class="card h-100 text-center p-4">
                <i class="fas fa-bell fa-3x text-primary mb-3"></i>
                <h5>' . $this->t('Real-Time Alerts') . '</h5>
                <p class="text-muted">' . $this->t('Get notified when family members enter dangerous areas') . '</p>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="card h-100 text-center p-4">
                <i class="fas fa-map-marker-alt fa-3x text-primary mb-3"></i>
                <h5>' . $this->t('Location Tracking') . '</h5>
                <p class="text-muted">' . $this->t('See where your family members are and get safety scores for their locations') . '</p>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="card h-100 text-center p-4">
                <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                <h5>' . $this->t('Coordinated Safety') . '</h5>
                <p class="text-muted">' . $this->t('Share safety information and coordinate as a family unit') . '</p>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="card h-100 text-center p-4">
                <i class="fas fa-lock fa-3x text-primary mb-3"></i>
                <h5>' . $this->t('Privacy Protected') . '</h5>
                <p class="text-muted">' . $this->t('Your family data stays private and secure within your group') . '</p>
              </div>
            </div>
          </div>',
      ],
    ];

    return $build;
  }

  /**
   * Dashboard page for institutional management.
   *
   * @return array
   *   A render array.
   */
  public function dashboard() {
    // Check if user has institutional group membership
    // This will be implemented after Group integration
    
    $build = [];
    
    $build['content'] = [
      '#markup' => '<div class="container my-5"><h2>' . $this->t('Institution Dashboard') . '</h2><p>' . $this->t('Dashboard content coming soon...') . '</p></div>',
    ];

    return $build;
  }

  /**
   * Display user's groups page.
   *
   * @return array
   *   A render array.
   */
  public function myGroups() {
    $build = [];
    $current_user = \Drupal::currentUser();
    
    // Get the group membership loader service.
    $group_membership_loader = \Drupal::service('group.membership_loader');
    
    // Load all groups for the current user.
    $user_groups = $group_membership_loader->loadByUser($current_user);
    
    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'my-5']],
      'title' => [
        '#markup' => '<h1 class="mb-4">' . $this->t('My Groups') . '</h1>',
      ],
    ];
    
    if (empty($user_groups)) {
      $build['empty'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['container', 'mb-5']],
        'message' => [
          '#markup' => '<div class="alert alert-info">' . 
            $this->t('You are not a member of any groups yet.') . 
            '</div>',
        ],
        'actions' => [
          '#markup' => '<p><a href="/group/add/family" class="btn btn-primary">' . $this->t('Create a Family Group') . '</a> ' .
            '<a href="/group/add/institution" class="btn btn-secondary">' . $this->t('Create an Institution') . '</a></p>',
        ],
      ];
    }
    else {
      // Build a list of groups.
      $groups_list = [];
      
      foreach ($user_groups as $group_membership) {
        $group = $group_membership->getGroup();
        $group_type = $group->getGroupType();
        
        // Get user's roles in this group.
        $roles = [];
        foreach ($group_membership->getRoles() as $role) {
          $roles[] = $role->label();
        }
        
        $created_date = \Drupal::service('date.formatter')->format($group->getCreatedTime(), 'medium');
        
        $groups_list[] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['card', 'mb-3']],
          'card_body' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card-body']],
            'content' => [
              '#markup' => '<h5 class="card-title"><a href="' . $group->toUrl()->toString() . '">' . 
                $group->label() . '</a></h5>' .
                '<p class="card-text text-muted mb-2">' . 
                '<span class="badge bg-secondary me-2">' . $group_type->label() . '</span>' .
                (!empty($roles) ? '<span class="badge bg-info">' . implode(', ', $roles) . '</span>' : '') .
                '</p>' .
                '<p class="card-text text-muted small">Created: ' . $created_date . '</p>' .
                '<a href="' . $group->toUrl()->toString() . '" class="btn btn-sm btn-primary">' . 
                $this->t('View Group') . '</a> ' .
                '<a href="/group/' . $group->id() . '/map" class="btn btn-sm btn-success">' . 
                $this->t('View Map') . '</a> ' .
                '<a href="' . $group->toUrl('edit-form')->toString() . '" class="btn btn-sm btn-outline-secondary">' . 
                $this->t('Edit') . '</a>',
            ],
          ],
        ];
      }
      
      $build['groups'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['container', 'mb-5']],
        'list' => $groups_list,
      ];
      
      $build['create_new'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['container', 'mt-4']],
        'actions' => [
          '#markup' => '<h3 class="mb-3">' . $this->t('Create New Group') . '</h3>' .
            '<p><a href="/group/add/family" class="btn btn-primary">' . $this->t('Create a Family Group') . '</a> ' .
            '<a href="/group/add/institution" class="btn btn-secondary">' . $this->t('Create an Institution') . '</a></p>',
        ],
      ];
    }

    return $build;
  }

}
