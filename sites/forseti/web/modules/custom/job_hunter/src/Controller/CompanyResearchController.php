<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Controller for company research and intelligence gathering.
 */
class CompanyResearchController extends ControllerBase {
  use JobHunterControllerTrait;

  /**
   * Main company research page.
   */
  public function researchPage() {
    $database = \Drupal::database();
    
    // Get companies with job counts
    $query = $database->select('jobhunter_companies', 'c')
      ->fields('c')
      ->orderBy('name', 'ASC');
    $companies = $query->execute()->fetchAll();
    
    $company_cards = [];
    foreach ($companies as $company) {
      // Count jobs for this company
      $job_count = $database->select('jobhunter_job_requirements', 'j')
        ->condition('company_id', $company->id)
        ->countQuery()
        ->execute()
        ->fetchField();
      
      // Count applications for this company
      $app_count = $database->select('jobhunter_job_applications', 'a')
        ->fields('a', ['id'])
        ->condition('company_id', $company->id)
        ->countQuery()
        ->execute()
        ->fetchField();
      
      $company_cards[] = [
        'id' => $company->id,
        'name' => $company->name,
        'industry' => $company->industry ?: $this->t('Not specified'),
        'location' => $company->location ?: $this->t('Not specified'),
        'website' => $company->website,
        'description' => $company->description,
        'notes' => $company->notes,
        'job_count' => $job_count,
        'app_count' => $app_count,
        'active' => $company->active,
      ];
    }
    
    $content = [
      '#theme' => 'company_research_page',
      '#companies' => $company_cards,
      '#attached' => [
        'library' => [
          'job_hunter/company-research',
        ],
      ],
    ];
    
    return $this->wrapWithNavigation($content);
  }

}
