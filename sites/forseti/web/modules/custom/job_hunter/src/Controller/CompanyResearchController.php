<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Database\DatabaseException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Controller for company research and intelligence gathering.
 */
class CompanyResearchController extends ControllerBase {
  use JobHunterControllerTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a CompanyResearchController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Main company research page.
   *
   * Displays all companies with their associated statistics including
   * job counts and application counts, plus company research data.
   *
   * @return array
   *   A render array for the company research page.
   */
  public function researchPage() {
    try {
      // Get companies with job and application counts in a single optimized query
      // This eliminates the N+1 query problem by using JOINs and aggregation
      $query = $this->database->select('jobhunter_companies', 'c');
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
    
    // Get all company research data in one query
    $research_data = [];
    if (!empty($companies)) {
      $company_names = array_map(function($company) {
        return $company->name;
      }, $companies);
      
      try {
        $research_results = $this->database->select('company_research_results', 'r')
          ->fields('r', ['id', 'company_name', 'ats_platform', 'automation_readiness', 'created_at', 'research_date'])
          ->condition('company_name', $company_names, 'IN')
          ->orderBy('created_at', 'DESC')
          ->execute()
          ->fetchAll();
        
        // Index by company name (keep most recent)
        foreach ($research_results as $result) {
          if (!isset($research_data[$result->company_name])) {
            $research_data[$result->company_name] = $result;
          }
        }
      }
      catch (DatabaseException $e) {
        \Drupal::logger('job_hunter')->warning('Failed to fetch research data: @error', ['@error' => $e->getMessage()]);
      }
    }
    
    $company_cards = [];
    $cache_ttl = 2592000; // 30 days
    $current_time = time();
    
    foreach ($companies as $company) {
      // Sanitize website URL for security (defense-in-depth approach)
      // 1. stripDangerousProtocols removes dangerous protocol schemes (javascript:, data:, etc.)
      // 2. Html::escape provides additional protection for HTML context output
      $safe_website = NULL;
      if ($company->website) {
        $safe_website = Html::escape(UrlHelper::stripDangerousProtocols($company->website));
      }
      
      // Get research data for this company
      $research = isset($research_data[$company->name]) ? $research_data[$company->name] : NULL;
      $has_research = $research !== NULL;
      $research_stale = FALSE;
      
      if ($has_research) {
        $research_age = $current_time - $research->created_at;
        $research_stale = $research_age > $cache_ttl;
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
        // Research data
        'has_research' => $has_research,
        'research_stale' => $research_stale,
        'research_id' => $has_research ? $research->id : NULL,
        'ats_platform' => $has_research ? Html::escape($research->ats_platform ?? '') : NULL,
        'automation_readiness' => $has_research ? Html::escape($research->automation_readiness ?? '') : NULL,
        'research_date' => $has_research ? $research->research_date : NULL,
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
