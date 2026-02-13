<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Database\DatabaseException;

/**
 * Controller for company research and intelligence gathering.
 */
class CompanyResearchController extends ControllerBase {
  use JobHunterControllerTrait;

  /**
   * Main company research page.
   *
   * Displays all companies with their associated statistics including
   * job counts and application counts.
   *
   * @return array
   *   A render array for the company research page.
   */
  public function researchPage() {
    $database = \Drupal::database();
    
    try {
      // Get companies with job and application counts in a single optimized query
      // This eliminates the N+1 query problem by using JOINs and aggregation
      $query = $database->select('jobhunter_companies', 'c');
      // Explicitly select the columns we need
      $query->fields('c', ['id', 'name', 'industry', 'location', 'website', 'description', 'notes', 'active']);
      $query->leftJoin('jobhunter_job_requirements', 'j', 'j.company_id = c.id');
      $query->leftJoin('jobhunter_job_applications', 'a', 'a.company_id = c.id');
      $query->addExpression('COUNT(DISTINCT j.id)', 'job_count');
      $query->addExpression('COUNT(DISTINCT a.id)', 'app_count');
      // Group by primary key is sufficient for unique row identification
      $query->groupBy('c.id');
      $query->orderBy('c.name', 'ASC');
      
      $companies = $query->execute()->fetchAll();
    }
    catch (DatabaseException $e) {
      \Drupal::logger('job_hunter')->error('Failed to fetch companies: @error', ['@error' => $e->getMessage()]);
      return [
        '#markup' => $this->t('Unable to load company data. Please try again later.'),
      ];
    }
    
    $company_cards = [];
    foreach ($companies as $company) {
      // Sanitize website URL for security (defense-in-depth approach)
      // 1. stripDangerousProtocols removes dangerous protocol schemes (javascript:, data:, etc.)
      // 2. Html::escape provides additional protection for HTML context output
      $safe_website = NULL;
      if ($company->website) {
        $safe_website = Html::escape(UrlHelper::stripDangerousProtocols($company->website));
      }
      
      $company_cards[] = [
        'id' => $company->id,
        'name' => Html::escape($company->name),
        'industry' => $company->industry ? Html::escape($company->industry) : $this->t('Not specified'),
        'location' => $company->location ? Html::escape($company->location) : $this->t('Not specified'),
        'website' => $safe_website,
        'description' => $company->description ? Html::escape($company->description) : NULL,
        'notes' => $company->notes ? Html::escape($company->notes) : NULL,
        'job_count' => $company->job_count,
        'app_count' => $company->app_count,
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
