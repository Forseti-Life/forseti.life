<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for company and job requirement management.
 */
class CompanyController extends ControllerBase {
  use JobHunterControllerTrait;

  /**
   * Valid job status values.
   */
  const VALID_JOB_STATUSES = ['active', 'archived', 'applied', 'interviewing', 'rejected', 'offered'];

  /**
   * Valid AI extraction status values.
   */
  const VALID_AI_STATUSES = ['pending', 'queued', 'processing', 'completed', 'failed'];

  /**
   * Valid tailoring status values.
   */
  const VALID_TAILORING_STATUSES = ['pending', 'queued', 'processing', 'completed', 'failed'];

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a CompanyController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(Connection $database, AccountProxyInterface $current_user, RequestStack $request_stack, FormBuilderInterface $form_builder) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('form_builder')
    );
  }

  /**
   * Safely decode JSON with error handling and logging.
   *
   * @param string|null $json
   *   The JSON string to decode.
   * @param string $context
   *   Context for logging (e.g., 'job requirements', 'tailored resume').
   * @param int|null $id
   *   Optional ID for logging context.
   *
   * @return array|null
   *   The decoded array or NULL on failure.
   */
  protected function safeJsonDecode($json, $context = 'data', $id = NULL) {
    if (empty($json)) {
      return NULL;
    }

    $decoded = json_decode($json, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $log_params = ['@context' => $context, '@error' => json_last_error_msg()];
      if ($id !== NULL) {
        $log_params['@id'] = $id;
        $this->getLogger('job_hunter')->warning(
          'Invalid JSON in @context @id: @error',
          $log_params
        );
      }
      else {
        $this->getLogger('job_hunter')->warning(
          'Invalid JSON in @context: @error',
          $log_params
        );
      }
      return NULL;
    }

    return $decoded;
  }

  /**
   * List all companies.
   * 
   * Note: The fields() call and groupBy() calls must be kept in sync.
   * If you add or remove fields, update both locations.
   */
  public function listCompanies() {
    try {
      // Get all companies with job count in a single query (fixed N+1 issue)
      $query = $this->database->select('jobhunter_companies', 'c');
      // Note: Fields listed here must match the GROUP BY clauses below
      $query->fields('c', ['id', 'name', 'industry', 'location', 'active']);
      $query->leftJoin('jobhunter_job_requirements', 'j', 'c.id = j.company_id');
      $query->addExpression('COUNT(j.id)', 'job_count');
      // GROUP BY all non-aggregated fields from the SELECT clause
      $query->groupBy('c.id');
      $query->groupBy('c.name');
      $query->groupBy('c.industry');
      $query->groupBy('c.location');
      $query->groupBy('c.active');
      $query->orderBy('c.name', 'ASC');
      $companies = $query->execute()->fetchAll();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Unable to load companies. Please try again.'));
      $this->getLogger('job_hunter')->error('Failed to load companies: @error', ['@error' => $e->getMessage()]);
      $companies = [];
    }
    
    // Build table
    $header = [
      $this->t('Company'),
      $this->t('Industry'),
      $this->t('Location'),
      $this->t('Active'),
      $this->t('Jobs'),
      $this->t('Actions'),
    ];
    
    $rows = [];
    foreach ($companies as $company) {
      $job_count = $company->job_count ?? 0;
      
      $rows[] = [
        $company->name,
        $company->industry ?? $this->t('N/A'),
        $company->location ?? $this->t('N/A'),
        $company->active ? $this->t('Yes') : $this->t('No'),
        $job_count,
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('job_hunter.company_edit', ['company_id' => $company->id]),
              ],
              'add_job' => [
                'title' => $this->t('Add Job'),
                'url' => Url::fromRoute('job_hunter.job_paste'),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('job_hunter.company_delete', ['company_id' => $company->id]),
              ],
            ],
          ],
        ],
      ];
    }
    
    $content = [
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Companies'),
      ],
      'add_button' => [
        '#type' => 'link',
        '#title' => $this->t('Add Company'),
        '#url' => Url::fromRoute('job_hunter.bulk_import_companies'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No companies found. Click "Add Company" to add your first target company.'),
        '#attributes' => ['class' => ['companies-table']],
      ],
    ];
    
    return $this->wrapWithNavigation($content);
  }

  /**
   * Delete a company.
   */
  public function deleteCompany($company_id) {
    try {
      // Delete all jobs for this company first
      $this->database->delete('jobhunter_job_requirements')
        ->condition('company_id', $company_id)
        ->execute();
      
      // Delete the company
      $this->database->delete('jobhunter_companies')
        ->condition('id', $company_id)
        ->execute();
      
      $this->messenger()->addMessage($this->t('Company and all associated jobs have been deleted.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to delete company. Please try again.'));
      $this->getLogger('job_hunter')->error('Failed to delete company @id: @error', [
        '@id' => $company_id,
        '@error' => $e->getMessage(),
      ]);
    }
    
    return new RedirectResponse(Url::fromRoute('job_hunter.companies_list')->toString());
  }

  /**
   * List all job requirements.
   */
  public function listJobs() {
    $current_user_id = $this->currentUser->id();
    $request = $this->requestStack->getCurrentRequest();
    
    // Get filter parameters with validation
    $filter_company = $request->query->get('company', '');
    $filter_status = $request->query->get('status', '');
    $filter_ai_status = $request->query->get('ai_status', '');
    $filter_tailoring = $request->query->get('tailoring', '');
    
    // Validate filter values using class constants
    if ($filter_status && !in_array($filter_status, self::VALID_JOB_STATUSES)) {
      $filter_status = '';
    }
    if ($filter_ai_status && !in_array($filter_ai_status, self::VALID_AI_STATUSES)) {
      $filter_ai_status = '';
    }
    if ($filter_tailoring && !in_array($filter_tailoring, self::VALID_TAILORING_STATUSES)) {
      $filter_tailoring = '';
    }
    
    try {
      // Get all jobs with company names and tailoring status
      $query = $this->database->select('jobhunter_job_requirements', 'j')
        ->fields('j');
      $query->leftJoin('jobhunter_companies', 'c', 'j.company_id = c.id');
      $query->addField('c', 'name', 'company_name');
      // Join tailored resumes for current user
      $query->leftJoin('jobhunter_tailored_resumes', 'tr', 'j.id = tr.job_id AND tr.uid = :uid', [':uid' => $current_user_id]);
      $query->addField('tr', 'tailoring_status');
      $query->addField('tr', 'tailored_resume_json');
      $query->addField('tr', 'pdf_path');
      
      // Apply filters
      if (!empty($filter_company)) {
        $query->condition('c.name', '%' . $this->database->escapeLike($filter_company) . '%', 'LIKE');
      }
      if (!empty($filter_status)) {
        $query->condition('j.status', $filter_status);
      }
      if (!empty($filter_ai_status)) {
        $query->condition('j.ai_extraction_status', $filter_ai_status);
      }
      if (!empty($filter_tailoring)) {
        $query->condition('tr.tailoring_status', $filter_tailoring);
      }
      
      $query->orderBy('c.name', 'ASC');
      $query->orderBy('j.job_title', 'ASC');
      $jobs = $query->execute()->fetchAll();
      
      // Get distinct companies for filter dropdown
      $companies_query = $this->database->select('jobhunter_companies', 'c')
        ->fields('c', ['name'])
        ->distinct()
        ->orderBy('name', 'ASC');
      $companies = $companies_query->execute()->fetchCol();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Unable to load jobs. Please try again.'));
      $this->getLogger('job_hunter')->error('Failed to load jobs: @error', ['@error' => $e->getMessage()]);
      $jobs = [];
      $companies = [];
    }
    
    // Build table
    $header = [
      $this->t('Job Title'),
      $this->t('Company'),
      $this->t('Status'),
      $this->t('AI Parsed'),
      $this->t('Tailored'),
      $this->t('Actions'),
    ];
    
    $rows = [];
    foreach ($jobs as $job) {
      // Parse extracted JSON for better title display using helper method
      $extracted = $this->safeJsonDecode($job->extracted_json, 'job requirements', $job->id);
      // Use extracted title if available, fall back to job_title, then to a default
      $job_title = $extracted['job_title'] ?? ($job->job_title ?? 'Job #' . $job->id);
      $company_name = $extracted['company_name'] ?? ($job->company_name ?? 'Unknown');
      
      // Determine AI parsing status
      $has_raw_text = !empty($job->raw_posting_text);
      $has_extracted = !empty($job->extracted_json);
      $ai_status = $job->ai_extraction_status ?? 'pending';
      
      if ($has_extracted) {
        $ai_badge = '<span class="badge badge--success" title="AI parsing complete">✅ Parsed</span>';
      } elseif ($ai_status === 'processing' || $ai_status === 'queued') {
        $ai_badge = '<span class="badge badge--warning" title="AI parsing in progress">⏳ Processing</span>';
      } elseif ($ai_status === 'failed') {
        $ai_badge = '<span class="badge badge--error" title="AI parsing failed">❌ Failed</span>';
      } elseif ($has_raw_text) {
        $ai_badge = '<span class="badge badge--info" title="Has raw text, needs AI parsing">📝 Needs Parsing</span>';
      } else {
        $ai_badge = '<span class="badge badge--neutral" title="No content yet">⚪ No Content</span>';
      }
      
      // Determine tailoring status
      $tailoring_status = $job->tailoring_status ?? NULL;
      $has_tailored_json = !empty($job->tailored_resume_json);
      $has_pdf = !empty($job->pdf_path);
      
      if ($tailoring_status === 'completed' && $has_tailored_json) {
        if ($has_pdf) {
          $tailor_badge = '<span class="badge badge--success" title="Tailored with PDF ready">✅ PDF Ready</span>';
        } else {
          $tailor_badge = '<span class="badge badge--success" title="Resume tailored, generate PDF">✅ Tailored</span>';
        }
      } elseif ($tailoring_status === 'processing' || $tailoring_status === 'queued') {
        $tailor_badge = '<span class="badge badge--warning" title="Tailoring in progress">⏳ Processing</span>';
      } elseif ($tailoring_status === 'failed') {
        $tailor_badge = '<span class="badge badge--error" title="Tailoring failed">❌ Failed</span>';
      } else {
        $tailor_badge = '<span class="badge badge--neutral" title="Not yet tailored">⚪ Not Tailored</span>';
      }
      
      // Build action links
      $tailor_link = [
        '#type' => 'link',
        '#title' => $tailoring_status === 'completed' ? $this->t('View/Edit') : $this->t('Tailor'),
        '#url' => Url::fromRoute('job_hunter.tailor_resume', ['job' => $job->id]),
        '#attributes' => ['class' => ['button', 'button--small', $tailoring_status === 'completed' ? 'button--secondary' : 'button--primary']],
      ];
      
      $rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $job_title,
            '#url' => Url::fromRoute('job_hunter.job_view', ['job_id' => $job->id]),
          ],
        ],
        ['data' => ['#markup' => $company_name]],
        ['data' => ['#markup' => ucfirst($job->status ?: 'active')]],
        ['data' => ['#markup' => $ai_badge]],
        ['data' => ['#markup' => $tailor_badge]],
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'tailor' => [
                'title' => $tailoring_status === 'completed' ? $this->t('View Tailored') : $this->t('Tailor Resume'),
                'url' => Url::fromRoute('job_hunter.tailor_resume', ['job' => $job->id]),
              ],
              'view' => [
                'title' => $this->t('View Job'),
                'url' => Url::fromRoute('job_hunter.job_view', ['job_id' => $job->id]),
              ],
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('job_hunter.job_edit', ['job_id' => $job->id]),
              ],
            ] + ($ai_status === 'failed' && $has_raw_text ? [
              'retry_parsing' => [
                'title' => $this->t('Retry Parsing'),
                'url' => Url::fromRoute('job_hunter.job_retry_parsing', ['job_id' => $job->id]),
                'attributes' => [
                  'class' => ['button--warning'],
                ],
              ],
            ] : []) + [
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('job_hunter.job_delete', ['job_id' => $job->id]),
              ],
            ],
          ],
        ],
      ];
    }
    
    $content = [
      'header' => [
        '#markup' => '<h2>' . $this->t('Job Requirements') . '</h2>',
      ],
      'add_button' => [
        '#type' => 'link',
        '#title' => $this->t('Add Job Posting'),
        '#url' => Url::fromRoute('job_hunter.job_paste'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'filters' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['jobs-filters']],
        'form' => [
          '#type' => 'inline_template',
          '#template' => '
            <div class="filter-form">
              <form method="get" action="{{ action_url }}">
                <div class="filter-row">
                  <div class="filter-field">
                    <label for="company">{{ "Company"|t }}</label>
                    <select name="company" id="company">
                      <option value="">{{ "All Companies"|t }}</option>
                      {% for company in companies %}
                        <option value="{{ company }}"{{ company == filter_company ? " selected" : "" }}>{{ company }}</option>
                      {% endfor %}
                    </select>
                  </div>
                  <div class="filter-field">
                    <label for="status">{{ "Status"|t }}</label>
                    <select name="status" id="status">
                      <option value="">{{ "All Statuses"|t }}</option>
                      <option value="active"{{ filter_status == "active" ? " selected" : "" }}>{{ "Active"|t }}</option>
                      <option value="archived"{{ filter_status == "archived" ? " selected" : "" }}>{{ "Archived"|t }}</option>
                      <option value="applied"{{ filter_status == "applied" ? " selected" : "" }}>{{ "Applied"|t }}</option>
                    </select>
                  </div>
                  <div class="filter-field">
                    <label for="ai_status">{{ "AI Status"|t }}</label>
                    <select name="ai_status" id="ai_status">
                      <option value="">{{ "All AI Statuses"|t }}</option>
                      <option value="completed"{{ filter_ai_status == "completed" ? " selected" : "" }}>{{ "Parsed"|t }}</option>
                      <option value="pending"{{ filter_ai_status == "pending" ? " selected" : "" }}>{{ "Needs Parsing"|t }}</option>
                      <option value="processing"{{ filter_ai_status == "processing" ? " selected" : "" }}>{{ "Processing"|t }}</option>
                      <option value="failed"{{ filter_ai_status == "failed" ? " selected" : "" }}>{{ "Failed"|t }}</option>
                    </select>
                  </div>
                  <div class="filter-field">
                    <label for="tailoring">{{ "Tailoring"|t }}</label>
                    <select name="tailoring" id="tailoring">
                      <option value="">{{ "All Tailoring Statuses"|t }}</option>
                      <option value="completed"{{ filter_tailoring == "completed" ? " selected" : "" }}>{{ "Tailored"|t }}</option>
                      <option value="pending"{{ filter_tailoring == "pending" ? " selected" : "" }}>{{ "Not Tailored"|t }}</option>
                      <option value="processing"{{ filter_tailoring == "processing" ? " selected" : "" }}>{{ "Processing"|t }}</option>
                      <option value="failed"{{ filter_tailoring == "failed" ? " selected" : "" }}>{{ "Failed"|t }}</option>
                    </select>
                  </div>
                  <div class="filter-actions">
                    <button type="submit" class="button button--primary">{{ "Filter"|t }}</button>
                    <a href="{{ action_url }}" class="button button--secondary">{{ "Clear"|t }}</a>
                  </div>
                </div>
              </form>
            </div>',
          '#context' => [
            'action_url' => Url::fromRoute('job_hunter.my_jobs')->toString(),
            'companies' => $companies,
            'filter_company' => $filter_company,
            'filter_status' => $filter_status,
            'filter_ai_status' => $filter_ai_status,
            'filter_tailoring' => $filter_tailoring,
          ],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No job requirements found. Click "Add Job Requirement" to add your first job.'),
        '#attributes' => ['class' => ['jobs-table']],
      ],
    ];
    
    // Add CSS for filters
    $content['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .jobs-filters { margin: 20px 0; }
          .filter-form { background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; }
          .filter-row { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
          .filter-field { display: flex; flex-direction: column; flex: 1; min-width: 150px; }
          .filter-field label { font-weight: 600; margin-bottom: 5px; color: #374151; font-size: 14px; }
          .filter-field select { padding: 8px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; background: white; }
          .filter-field select:focus { outline: none; border-color: #667eea; }
          .filter-actions { display: flex; gap: 10px; align-items: center; }
          .filter-actions .button { margin: 0; }
        ',
      ],
      'jobs_filters_styles',
    ];
    
    return $this->wrapWithNavigation($content);
  }

  /**
   * Delete a job requirement.
   */
  public function deleteJob($job_id) {
    $request = $this->requestStack->getCurrentRequest();
    $return_to = (string) $request->query->get('return_to', '/jobhunter/my-jobs');
    if (strpos($return_to, '/') !== 0) {
      $return_to = '/jobhunter/my-jobs';
    }

    try {
      // Remove only this user's saved-job mapping.
      $this->database->delete('jobhunter_saved_jobs')
        ->condition('uid', (int) $this->currentUser->id())
        ->condition('job_id', (int) $job_id)
        ->execute();

      $this->messenger()->addMessage($this->t('Job removed from My Jobs.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to remove job. Please try again.'));
      $this->getLogger('job_hunter')->error('Failed to remove saved-job mapping for job @id: @error', [
        '@id' => $job_id,
        '@error' => $e->getMessage(),
      ]);
    }
    
    return new RedirectResponse($return_to);
  }

  /**
   * View a job requirement with all extracted data.
   */
  public function viewJob($job_id) {
    try {
      // Load the job
      $job = $this->database->select('jobhunter_job_requirements', 'j')
        ->fields('j')
        ->condition('id', $job_id)
        ->execute()
        ->fetchObject();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Unable to load job. Please try again.'));
      $this->getLogger('job_hunter')->error('Failed to load job @id: @error', [
        '@id' => $job_id,
        '@error' => $e->getMessage(),
      ]);
      return new RedirectResponse(Url::fromRoute('job_hunter.my_jobs')->toString());
    }
    
    if (!$job) {
      $this->messenger()->addError($this->t('Job not found.'));
      return new RedirectResponse(Url::fromRoute('job_hunter.my_jobs')->toString());
    }

    $jobValue = static function (object $row, string $field) {
      return property_exists($row, $field) ? $row->{$field} : NULL;
    };
    
    // Parse JSON data using helper method
    $extracted = $this->safeJsonDecode($jobValue($job, 'extracted_json'), 'job extracted data', $job_id);
    $skills = $this->safeJsonDecode($jobValue($job, 'skills_required_json'), 'job skills', $job_id);
    $keywords = $this->safeJsonDecode($jobValue($job, 'keywords_json'), 'job keywords', $job_id);
    $duplicates = $this->safeJsonDecode($jobValue($job, 'potential_duplicates_json'), 'potential duplicates', $job_id) ?? [];
    
    // Build the content
    $content = [];
    
    // Show duplicate warning if found
    if (!empty($duplicates)) {
      $exact_match = array_filter($duplicates, fn($d) => $d['is_exact_match'] ?? FALSE);
      
      if (!empty($exact_match)) {
        $match = reset($exact_match);
        $content['duplicate_exact'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['messages', 'messages--error', 'duplicate-warning']],
          '#markup' => '<strong>⚠️ Exact Duplicate Found!</strong><br>' .
            'This job appears to be identical to: <a href="' . 
            Url::fromRoute('job_hunter.job_view', ['job_id' => $match['job_id']])->toString() . 
            '"><strong>' . htmlspecialchars($match['job_title']) . '</strong> at ' . 
            htmlspecialchars($match['company']) . ' (Job #' . $match['job_id'] . ')</a>',
        ];
      }
      else {
        $links = [];
        foreach ($duplicates as $dup) {
          $links[] = '<a href="' . 
            Url::fromRoute('job_hunter.job_view', ['job_id' => $dup['job_id']])->toString() . 
            '">' . htmlspecialchars($dup['job_title']) . ' at ' . 
            htmlspecialchars($dup['company']) . ' (' . $dup['similarity_score'] . '% match)</a>';
        }
        $content['duplicate_warning'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['messages', 'messages--warning', 'duplicate-warning']],
          '#markup' => '<strong>📋 Potential Duplicates Found</strong><br>' .
            'This job may be similar to:<br><ul><li>' . implode('</li><li>', $links) . '</li></ul>',
        ];
      }
    }
    
    // Check if user has a tailored resume for this job
    $current_user = $this->currentUser;
    $tailored_resume = $this->database->select('jobhunter_tailored_resumes', 'tr')
      ->fields('tr', ['id', 'tailoring_status', 'pdf_path'])
      ->condition('uid', $current_user->id())
      ->condition('job_id', $job_id)
      ->execute()
      ->fetchObject();

    // Header with edit link
    $raw_title = $jobValue($job, 'job_title');
    $display_title = $extracted['job_title']
      ?? ($raw_title ?: 'Job Requisition #' . $job_id);

    $raw_company = '';
    $company_id = $jobValue($job, 'company_id');
    if ($company_id) {
      $company_node = \Drupal::entityTypeManager()->getStorage('node')->load($company_id);
      if ($company_node) {
        $raw_company = $company_node->getTitle();
      }
    }
    $display_company = $extracted
      ? (($extracted['company_name'] ?? '') . (!empty($extracted['industry']) ? ' — ' . $extracted['industry'] : ''))
      : $raw_company;

    $content['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['job-view-header']],
      'title' => [
        '#markup' => '<h2>' . htmlspecialchars($display_title) . '</h2>',
      ],
      'company' => [
        '#markup' => $display_company ? '<p class="job-company"><strong>' . htmlspecialchars($display_company) . '</strong></p>' : '',
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['job-actions']],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Edit'),
          '#url' => Url::fromRoute('job_hunter.job_edit', ['job_id' => $job_id]),
          '#attributes' => ['class' => ['button']],
        ],
        'tailor' => [
          '#type' => 'link',
          '#title' => $this->t('Tailor My Resume'),
          '#url' => Url::fromRoute('job_hunter.tailor_resume', ['job' => $job_id]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ],
    ];

    // Job source information and links
    $source_info = [];
    $job_url = $jobValue($job, 'job_url');
    $application_url = $jobValue($job, 'application_url');
    $original_url = !empty($job_url) ? $job_url : ($application_url ?? '');
    if (!empty($original_url)) {
      $source_info[] = '<strong>Job URL:</strong> <a href="' . htmlspecialchars($original_url) . '" target="_blank" rel="noopener">' . htmlspecialchars($original_url) . ' ↗</a>';
    }
    if (!empty($jobValue($job, 'external_source'))) {
      $source_info[] = '<strong>Source:</strong> ' . htmlspecialchars((string) $jobValue($job, 'external_source'));
    }
    if (!empty($jobValue($job, 'external_job_id'))) {
      $source_info[] = '<strong>External Job ID:</strong> ' . htmlspecialchars((string) $jobValue($job, 'external_job_id'));
    }
    if (!empty($jobValue($job, 'source_platform'))) {
      $source_info[] = '<strong>Platform:</strong> ' . htmlspecialchars((string) $jobValue($job, 'source_platform'));
    }
    if ($tailored_resume) {
      $status_text = ucfirst($tailored_resume->tailoring_status);
      $status_class = match($tailored_resume->tailoring_status) {
        'completed' => 'status-completed',
        'pending' => 'status-pending',
        'queued' => 'status-queued',
        'processing' => 'status-processing',
        'failed' => 'status-failed',
        default => 'status-unknown',
      };
      $source_info[] = '<strong>Tailored Resume:</strong> <a href="' . Url::fromRoute('job_hunter.tailor_resume', ['job' => $job_id])->toString() . '">View/Edit Tailored Resume</a> <span class="' . $status_class . '">(' . $status_text . ')</span>';
      if ($tailored_resume->tailoring_status === 'completed' && !empty($tailored_resume->pdf_path)) {
        $source_info[] = '<strong>Resume PDF:</strong> <a href="' . Url::fromRoute('job_hunter.resume_pdf', ['job_id' => $job_id])->toString() . '" target="_blank">Download PDF ↗</a>';
      }
    }
    
    if (!empty($source_info)) {
      $content['source_info'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['job-source-info']],
        '#markup' => '<div class="job-info-box">' . implode('<br>', $source_info) . '</div>',
      ];
    }

    // AI extraction status notice when parsing is not yet complete.
    $ai_status = $jobValue($job, 'ai_extraction_status') ?? 'pending';
    if (!$extracted) {
      $status_labels = [
        'pending'    => ['label' => '⏳ AI parsing pending',    'class' => 'messages--warning'],
        'queued'     => ['label' => '⏳ AI parsing queued',     'class' => 'messages--warning'],
        'processing' => ['label' => '⚙️ AI parsing in progress', 'class' => 'messages--warning'],
        'failed'     => ['label' => '⚠️ AI parsing failed', 'class' => 'messages--error'],
      ];
      $badge = $status_labels[$ai_status] ?? ['label' => '⏳ AI parsing not yet run', 'class' => 'messages--warning'];
      $content['ai_status_notice'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', $badge['class']]],
        '#markup' => $badge['label'],
      ];
    }

    // Extracted Job Data section
    if ($extracted) {
      $content['extracted'] = [
        '#type' => 'details',
        '#title' => $this->t('Job Details'),
        '#open' => TRUE,
        '#attributes' => ['class' => ['job-section']],
      ];
      
      // Position info
      if (!empty($extracted['job_title']) || !empty($extracted['employment_type']) || !empty($extracted['experience_years'])) {
        $loc = $extracted['location'] ?? [];
        $location_str = is_array($loc) ? ($loc['full_location'] ?? implode(', ', array_filter([$loc['city'] ?? '', $loc['state'] ?? '']))) : (string) $loc;
        $content['extracted']['position'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['job-subsection']],
          '#markup' => '<h3>Position</h3>' .
            '<dl class="job-details">' .
            '<dt>Title</dt><dd>' . htmlspecialchars($extracted['job_title'] ?? 'N/A') . '</dd>' .
            '<dt>Employment Type</dt><dd>' . htmlspecialchars($extracted['employment_type'] ?? 'N/A') . '</dd>' .
            '<dt>Experience Required</dt><dd>' . htmlspecialchars($extracted['experience_years'] ? $extracted['experience_years'] . ' years' : 'N/A') . '</dd>' .
            '<dt>Remote</dt><dd>' . htmlspecialchars($extracted['remote_option'] ?? 'N/A') . '</dd>' .
            '<dt>Location</dt><dd>' . htmlspecialchars($location_str ?: 'N/A') . '</dd>' .
            '</dl>',
        ];
      }

      // Compensation
      if (!empty($extracted['salary_range']) || !empty($extracted['benefits'])) {
        $benefits = $extracted['benefits'] ?? [];
        $benefits_str = is_array($benefits) ? implode(', ', $benefits) : (string) $benefits;
        $content['extracted']['compensation'] = [
          '#type' => 'container',
          '#markup' => '<h3>Compensation</h3>' .
            '<dl class="job-details">' .
            '<dt>Salary Range</dt><dd>' . htmlspecialchars($extracted['salary_range'] ?? 'N/A') . '</dd>' .
            '<dt>Application Deadline</dt><dd>' . htmlspecialchars($extracted['application_deadline'] ?? 'N/A') . '</dd>' .
            '<dt>Visa Sponsorship</dt><dd>' . ($extracted['visa_sponsorship'] ? 'Yes' : 'No') . '</dd>' .
            '</dl>',
        ];
        if (!empty($benefits_str)) {
          $content['extracted']['benefits'] = [
            '#markup' => '<p><strong>Benefits:</strong> ' . htmlspecialchars($benefits_str) . '</p>',
          ];
        }
      }

      // Requirements
      if (!empty($extracted['requirements'])) {
        $req = $extracted['requirements'];
        $req_items = is_array($req) ? '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $req)) . '</li></ul>' : '<p>' . htmlspecialchars((string) $req) . '</p>';
        $content['extracted']['requirements'] = [
          '#type' => 'container',
          '#markup' => '<h3>Requirements</h3>' . $req_items,
        ];
      }

      // Qualifications (required + preferred)
      if (!empty($extracted['qualifications'])) {
        $qual = $extracted['qualifications'];
        $qual_html = '<h3>Qualifications</h3>';
        if (!empty($qual['required'])) {
          $qual_html .= '<p><strong>Required:</strong></p><ul><li>' . implode('</li><li>', array_map('htmlspecialchars', (array) $qual['required'])) . '</li></ul>';
        }
        if (!empty($qual['preferred'])) {
          $qual_html .= '<p><strong>Preferred:</strong></p><ul><li>' . implode('</li><li>', array_map('htmlspecialchars', (array) $qual['preferred'])) . '</li></ul>';
        }
        $content['extracted']['qualifications'] = [
          '#type' => 'container',
          '#markup' => $qual_html,
        ];
      }

      // Key responsibilities
      if (!empty($extracted['responsibilities'])) {
        $resp = $extracted['responsibilities'];
        $resp_items = is_array($resp) ? '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $resp)) . '</li></ul>' : '<p>' . htmlspecialchars((string) $resp) . '</p>';
        $content['extracted']['responsibilities'] = [
          '#type' => 'container',
          '#markup' => '<h3>Key Responsibilities</h3>' . $resp_items,
        ];
      }

      // Company info
      if (!empty($extracted['company_name']) || !empty($extracted['industry']) || !empty($extracted['company_description'])) {
        $content['extracted']['company_info'] = [
          '#type' => 'container',
          '#markup' => '<h3>Company</h3>' .
            '<dl class="job-details">' .
            '<dt>Name</dt><dd>' . htmlspecialchars($extracted['company_name'] ?? 'N/A') . '</dd>' .
            '<dt>Industry</dt><dd>' . htmlspecialchars($extracted['industry'] ?? 'N/A') . '</dd>' .
            '</dl>' .
            (!empty($extracted['company_description']) ? '<p>' . htmlspecialchars($extracted['company_description']) . '</p>' : ''),
        ];
      }

      // AI-extracted job description narrative
      if (!empty($extracted['job_description'])) {
        $content['extracted']['job_description'] = [
          '#type' => 'container',
          '#markup' => '<h3>Job Description</h3><div class="job-description-text">' . nl2br(htmlspecialchars($extracted['job_description'])) . '</div>',
        ];
      }
    }

    // Original job posting — always show when available (pre-parsed source data)
    $db_description = $jobValue($job, 'job_description');
    $db_requirements = $jobValue($job, 'requirements');
    if (!empty($db_description) || !empty($db_requirements)) {
      $content['original_posting'] = [
        '#type' => 'details',
        '#title' => $this->t('Original Job Posting'),
        '#open' => !$extracted,
        '#attributes' => ['class' => ['job-section']],
      ];
      if (!empty($db_description)) {
        $content['original_posting']['description'] = [
          '#markup' => '<h3>Description</h3><div class="raw-text">' . nl2br(htmlspecialchars($db_description)) . '</div>',
        ];
      }
      if (!empty($db_requirements)) {
        $content['original_posting']['requirements'] = [
          '#markup' => '<h3>Requirements</h3><div class="raw-text">' . nl2br(htmlspecialchars($db_requirements)) . '</div>',
        ];
      }
    }

    // Skills section
    if ($skills) {
      $content['skills'] = [
        '#type' => 'details',
        '#title' => $this->t('Required Skills'),
        '#open' => TRUE,
        '#attributes' => ['class' => ['job-section']],
      ];
      
      // Must-have skills
      if (!empty($skills['must_have'])) {
        $must_items = [];
        foreach ($skills['must_have'] as $skill) {
          $must_items[] = '<strong>' . $skill['skill'] . '</strong>' . 
            (!empty($skill['years']) ? ' (' . $skill['years'] . '+ years)' : '') .
            (!empty($skill['context']) ? ' — ' . $skill['context'] : '');
        }
        $content['skills']['must_have'] = [
          '#markup' => '<h3>Must Have</h3><ul><li>' . implode('</li><li>', $must_items) . '</li></ul>',
        ];
      }
      
      // Nice-to-have skills
      if (!empty($skills['nice_to_have'])) {
        $nice_items = [];
        foreach ($skills['nice_to_have'] as $skill) {
          $nice_items[] = '<strong>' . $skill['skill'] . '</strong>' .
            (!empty($skill['context']) ? ' — ' . $skill['context'] : '');
        }
        $content['skills']['nice_to_have'] = [
          '#markup' => '<h3>Nice to Have</h3><ul><li>' . implode('</li><li>', $nice_items) . '</li></ul>',
        ];
      }
      
      // Tech stack
      if (!empty($skills['tech_stack'])) {
        $stack = $skills['tech_stack'];
        $stack_html = '<h3>Tech Stack</h3><dl class="job-details">';
        if (!empty($stack['languages'])) {
          $stack_html .= '<dt>Languages</dt><dd>' . implode(', ', $stack['languages']) . '</dd>';
        }
        if (!empty($stack['frameworks'])) {
          $stack_html .= '<dt>Frameworks</dt><dd>' . implode(', ', $stack['frameworks']) . '</dd>';
        }
        if (!empty($stack['databases'])) {
          $stack_html .= '<dt>Databases</dt><dd>' . implode(', ', $stack['databases']) . '</dd>';
        }
        if (!empty($stack['cloud'])) {
          $stack_html .= '<dt>Cloud</dt><dd>' . implode(', ', $stack['cloud']) . '</dd>';
        }
        if (!empty($stack['tools'])) {
          $stack_html .= '<dt>Tools</dt><dd>' . implode(', ', $stack['tools']) . '</dd>';
        }
        $stack_html .= '</dl>';
        $content['skills']['tech_stack'] = [
          '#markup' => $stack_html,
        ];
      }
    }
    
    // Keywords section
    if ($keywords) {
      $content['keywords'] = [
        '#type' => 'details',
        '#title' => $this->t('Keywords for Resume Tailoring'),
        '#open' => FALSE,
        '#attributes' => ['class' => ['job-section']],
      ];
      
      if (!empty($keywords['high_frequency'])) {
        $content['keywords']['high_freq'] = [
          '#markup' => '<p><strong>High Frequency:</strong> ' . implode(', ', $keywords['high_frequency']) . '</p>',
        ];
      }
      if (!empty($keywords['action_verbs'])) {
        $content['keywords']['verbs'] = [
          '#markup' => '<p><strong>Action Verbs:</strong> ' . implode(', ', $keywords['action_verbs']) . '</p>',
        ];
      }
      if (!empty($keywords['key_phrases'])) {
        $content['keywords']['phrases'] = [
          '#markup' => '<p><strong>Key Phrases:</strong> ' . implode(', ', $keywords['key_phrases']) . '</p>',
        ];
      }
      if (!empty($keywords['domain_terms'])) {
        $content['keywords']['domain'] = [
          '#markup' => '<p><strong>Domain Terms:</strong> ' . implode(', ', $keywords['domain_terms']) . '</p>',
        ];
      }
    }
    
    // Raw posting (collapsed)
    if ($job->raw_posting_text) {
      $content['raw'] = [
        '#type' => 'details',
        '#title' => $this->t('Original Posting Text'),
        '#open' => FALSE,
        'text' => [
          '#markup' => '<pre style="white-space: pre-wrap; font-size: 12px; background: #f5f5f5; padding: 15px; border-radius: 4px;">' . htmlspecialchars($job->raw_posting_text) . '</pre>',
        ],
      ];
    }
    
    // Add some basic styling
    $content['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .job-details { display: grid; grid-template-columns: 150px 1fr; gap: 8px; margin: 10px 0; }
          .job-details dt { font-weight: 600; color: #555; }
          .job-details dd { margin: 0; }
          .job-section { margin-bottom: 15px; }
          .job-subsection { margin-bottom: 20px; }
          .job-view-header { margin-bottom: 20px; }
          .job-company { color: #666; font-size: 1.1em; margin-top: -10px; }
          .job-source-info { margin-bottom: 20px; }
          .job-info-box { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; border-radius: 4px; }
          .job-info-box strong { color: #333; }
          .job-info-box a { color: #667eea; text-decoration: none; }
          .job-info-box a:hover { text-decoration: underline; }
          .status-completed { color: #10b981; font-weight: 600; }
          .status-pending { color: #f59e0b; font-weight: 600; }
          .status-queued { color: #3b82f6; font-weight: 600; }
          .status-processing { color: #8b5cf6; font-weight: 600; }
          .status-failed { color: #ef4444; font-weight: 600; }
          .raw-text { white-space: pre-wrap; font-size: 0.95em; line-height: 1.6; margin: 10px 0; }
        ',
      ],
      'job_view_styles',
    ];
    
    return $this->wrapWithNavigation($content);
  }

  /**
   * Display the edit job form wrapped in navigation.
   */
  public function editJobForm($job_id) {
    // Build the form
    $form = $this->formBuilder->getForm('Drupal\job_hunter\Form\JobRequirementForm', $job_id);

    return $this->wrapWithNavigation($form);
  }

  /**
   * Combined job view and resume tailoring page.
   */
  public function jobTailoring($job_id) {
    try {
      // Get current user
      $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser->id());
      
      // Load the job
      $job = $this->database->select('jobhunter_job_requirements', 'j')
        ->fields('j')
        ->condition('id', $job_id)
        ->execute()
        ->fetchObject();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Unable to load job. Please try again.'));
      $this->getLogger('job_hunter')->error('Failed to load job @id: @error', [
        '@id' => $job_id,
        '@error' => $e->getMessage(),
      ]);
      return new RedirectResponse(Url::fromRoute('job_hunter.my_jobs')->toString());
    }
    
    if (!$job) {
      $this->messenger()->addError($this->t('Job not found.'));
      return new RedirectResponse(Url::fromRoute('job_hunter.my_jobs')->toString());
    }
    
    // Parse JSON data using helper method
    $extracted = $this->safeJsonDecode($job->extracted_json, 'job extracted data', $job_id) ?? [];
    $skills = $this->safeJsonDecode($job->skills_required_json, 'job skills', $job_id) ?? [];
    $keywords = $this->safeJsonDecode($job->keywords_json, 'job keywords', $job_id) ?? [];
    
    // Load user's tailored resume for this job (if exists)
    $tailored_record = $this->database->select('jobhunter_tailored_resumes', 'tr')
      ->fields('tr')
      ->condition('uid', $user->id())
      ->condition('job_id', $job_id)
      ->execute()
      ->fetchObject();
    
    $tailored = $tailored_record ? $this->safeJsonDecode($tailored_record->tailored_resume_json, 'tailored resume', $job_id) : NULL;
    $tailoring_status = $tailored_record ? $tailored_record->tailoring_status : 'pending';
    
    // Fix stuck queued/processing status
    if ($tailored_record && in_array($tailoring_status, ['queued', 'processing'])) {
      $queue_item = $this->database->select('queue', 'q')
        ->fields('q', ['item_id'])
        ->condition('name', 'job_hunter_resume_tailoring')
        ->condition('data', '%"job_id":' . $job_id . '%', 'LIKE')
        ->execute()
        ->fetchField();
      
      if (!$queue_item) {
        $new_status = $tailored ? 'completed' : 'pending';
        $this->database->update('jobhunter_tailored_resumes')
          ->fields(['tailoring_status' => $new_status])
          ->condition('uid', $user->id())
          ->condition('job_id', $job_id)
          ->execute();
        $tailoring_status = $new_status;
      }
    }
    
    // Get PDF info
    $pdf_path = $tailored_record && !empty($tailored_record->pdf_path) ? $tailored_record->pdf_path : NULL;
    $pdf_generated = $tailored_record && !empty($tailored_record->pdf_generated) ? $tailored_record->pdf_generated : NULL;

    // Get PDF history for this job
    $pdf_history = $this->database->select('jobhunter_pdf_history', 'ph')
      ->fields('ph')
      ->condition('uid', $user->id())
      ->condition('job_id', $job_id)
      ->orderBy('created', 'DESC')
      ->execute()
      ->fetchAll();

    // Load user's job seeker profile
    $job_seeker_profile = $this->database->select('jobhunter_job_seeker', 'js')
      ->fields('js')
      ->condition('uid', $user->id())
      ->execute()
      ->fetchObject();
    
    $profile_json = [];
    if ($job_seeker_profile && !empty($job_seeker_profile->consolidated_profile_json)) {
      $profile_json = $this->safeJsonDecode($job_seeker_profile->consolidated_profile_json, 'job seeker profile', $user->id()) ?? [];
    }

    // Calculate skills gap
    $skills_gap = [];
    if (!empty($skills['must_have']) && !empty($profile_json['skills'])) {
      $user_skills_lower = array_map('strtolower', array_column($profile_json['skills'], 'name'));
      foreach ($skills['must_have'] as $required_skill) {
        $skill_name = $required_skill['skill'] ?? '';
        if ($skill_name && !in_array(strtolower($skill_name), $user_skills_lower)) {
          $skills_gap[] = $required_skill;
        }
      }
    }
    
    // Build combined content
    $content = [
      '#theme' => 'job_tailoring_combined',
      '#job' => $job,
      '#job_id' => $job_id,
      '#job_extracted' => $extracted,
      '#job_skills' => $skills,
      '#job_keywords' => $keywords,
      '#user' => $user,
      '#profile' => $job_seeker_profile,
      '#profile_json' => $profile_json,
      '#skills_gap' => $skills_gap,
      '#tailored_resume' => $tailored,
      '#tailoring_status' => $tailoring_status,
      '#pdf_path' => $pdf_path,
      '#pdf_generated' => $pdf_generated,
      '#pdf_history' => $pdf_history,
      '#attached' => [
        'library' => [
          'job_hunter/job-hunter-navigation',
          'job_hunter/job-hunter-home',
          'job_hunter/tailor_resume',
        ],
      ],
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
    ];
    
    return $build;
  }

  /**
   * Display the add company form wrapped in navigation.
   */
  public function addForm($company_id = NULL) {
    // Build the form
    $form = $this->formBuilder->getForm('Drupal\job_hunter\Form\CompanyForm', $company_id);

    return $this->wrapWithNavigation($form);
  }

  /**
   * Display the bulk import form wrapped in navigation.
   */
  public function bulkImportForm() {
    // Build the form
    $form = $this->formBuilder->getForm('Drupal\job_hunter\Form\BulkCompanyImportForm');

    return $this->wrapWithNavigation($form);
  }

}
