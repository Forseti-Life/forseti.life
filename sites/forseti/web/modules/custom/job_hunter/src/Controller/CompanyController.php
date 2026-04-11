<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
      // Join application records for current user.
      $query->leftJoin('jobhunter_applications', 'app', 'j.id = app.job_id AND app.uid = :app_uid', [':app_uid' => $current_user_id]);
      $query->addField('app', 'submission_status', 'application_status');
      if ($this->database->schema()->fieldExists('jobhunter_applications', 'ats_platform')) {
        $query->addField('app', 'ats_platform', 'application_ats');
      }
      else {
        $query->addExpression("''", 'application_ats');
      }
      $query->addField('app', 'automation_success', 'application_automation_success');
      
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
    if (!preg_match('/^\/(?!\/)/', $return_to)) {
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

    // Check existing application record (schema-safe across environments).
    $existing_application_query = $this->database->select('jobhunter_applications', 'a')
      ->fields('a', ['id', 'submission_status', 'apply_url', 'selected_apply_option', 'attempt_count', 'confirmation_reference', 'submission_date', 'automation_success', 'admin_review_required'])
      ->condition('uid', $current_user->id())
      ->condition('job_id', $job_id)
      ->orderBy('created', 'DESC')
      ->range(0, 1);
    if ($this->database->schema()->fieldExists('jobhunter_applications', 'ats_platform')) {
      $existing_application_query->addField('a', 'ats_platform');
    }
    else {
      $existing_application_query->addExpression("''", 'ats_platform');
    }
    $existing_application = $existing_application_query
      ->execute()
      ->fetchAssoc();

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

    // Build Apply button HTML — AJAX-powered, no page refresh.
    $apply_url_route = Url::fromRoute('job_hunter.job_apply', ['job_id' => $job_id])->toString();
    $status_url_route = Url::fromRoute('job_hunter.application_status', ['job_id' => $job_id])->toString();
    $csrf_token = \Drupal::csrfToken()->get('jobhunter/jobs/' . (int) $job_id . '/apply');

    if ($existing_application) {
      $app_status = $existing_application['submission_status'];
      $status_labels = [
        'pending'         => ['label' => '⏳ Application Pending', 'class' => 'btn-warning'],
        'processing'      => ['label' => '⚙️ Submitting...', 'class' => 'btn-warning'],
        'submitted'       => ['label' => '✅ Applied', 'class' => 'btn-success'],
        'failed'          => ['label' => '❌ Failed — Retry', 'class' => 'btn-danger'],
        'manual_required' => ['label' => '📋 Apply Manually', 'class' => 'btn-secondary'],
      ];
      $btn_info = $status_labels[$app_status] ?? ['label' => '📤 Apply', 'class' => 'button--primary'];
      $apply_button_html = '<button class="button ' . $btn_info['class'] . ' btn-apply-job" data-job-id="' . $job_id . '" data-apply-url="' . $apply_url_route . '" data-status-url="' . $status_url_route . '" data-token="' . $csrf_token . '">' . $btn_info['label'] . '</button>';
    } else {
      $apply_button_html = '<button class="button button--primary btn-apply-job" data-job-id="' . $job_id . '" data-apply-url="' . $apply_url_route . '" data-status-url="' . $status_url_route . '" data-token="' . $csrf_token . '">📤 Apply</button>';
    }

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
        'apply' => [
          '#markup' => $apply_button_html,
        ],
      ],
    ];

    // Application status panel (shown when application exists).
    if ($existing_application) {
      $app = $existing_application;
      $status_classes = [
        'pending'         => 'status-pending',
        'processing'      => 'status-processing',
        'submitted'       => 'status-completed',
        'failed'          => 'status-failed',
        'manual_required' => 'status-neutral',
      ];
      $status_class = $status_classes[$app['submission_status']] ?? 'status-neutral';
      $status_display = ucwords(str_replace('_', ' ', $app['submission_status']));

      $app_info_parts = [
        '<strong>Status:</strong> <span class="' . $status_class . '">' . htmlspecialchars($status_display) . '</span>',
      ];
      if (!empty($app['ats_platform'])) {
        $app_info_parts[] = '<strong>ATS Platform:</strong> ' . htmlspecialchars(ucfirst($app['ats_platform']));
      }
      if (!empty($app['selected_apply_option'])) {
        $app_info_parts[] = '<strong>Apply Via:</strong> ' . htmlspecialchars($app['selected_apply_option']);
      }
      if (!empty($app['apply_url'])) {
        $app_info_parts[] = '<strong>Apply URL:</strong> <a href="' . htmlspecialchars($app['apply_url']) . '" target="_blank" rel="noopener">' . htmlspecialchars($app['apply_url']) . ' ↗</a>';
      }
      if (!empty($app['confirmation_reference'])) {
        $app_info_parts[] = '<strong>Confirmation:</strong> ' . htmlspecialchars($app['confirmation_reference']);
      }
      if (!empty($app['submission_date'])) {
        $app_info_parts[] = '<strong>Submitted:</strong> ' . htmlspecialchars($app['submission_date']);
      }
      if (!empty($app['attempt_count'])) {
        $app_info_parts[] = '<strong>Attempts:</strong> ' . (int) $app['attempt_count'];
      }

      $content['application_status'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['job-application-status', 'job-info-box']],
        '#markup' => '<h4>📋 Application Status</h4><div class="app-status-details">' . implode('<br>', $app_info_parts) . '</div>',
        '#cache' => ['contexts' => ['user']],
      ];
    }

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
        $source_info[] = '<strong>Resume PDF:</strong> <a href="' . Url::fromRoute('job_hunter.download_tailored_resume_pdf', ['job_id' => $job_id])->toString() . '" target="_blank">Download PDF ↗</a>';
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
          .status-completed, .status-neutral { color: #10b981; font-weight: 600; }
          .status-pending { color: #f59e0b; font-weight: 600; }
          .status-queued { color: #3b82f6; font-weight: 600; }
          .status-processing { color: #8b5cf6; font-weight: 600; }
          .status-failed { color: #ef4444; font-weight: 600; }
          .raw-text { white-space: pre-wrap; font-size: 0.95em; line-height: 1.6; margin: 10px 0; }
          .job-application-status { margin-bottom: 20px; }
          .job-application-status h4 { margin: 0 0 10px 0; color: #333; }
          .app-status-details { line-height: 1.9; }
          .btn-apply-job { background: #667eea; color: #fff; border: none; padding: 8px 18px; border-radius: 4px; cursor: pointer; font-size: 0.95em; }
          .btn-apply-job:hover { background: #5563d0; }
          .btn-apply-job.btn-success { background: #10b981; }
          .btn-apply-job.btn-warning { background: #f59e0b; }
          .btn-apply-job.btn-danger { background: #ef4444; }
          .btn-apply-job.btn-secondary { background: #6b7280; }
          .btn-apply-job:disabled { opacity: 0.6; cursor: not-allowed; }
          #apply-status-msg { margin-top: 8px; font-size: 0.9em; padding: 8px 12px; border-radius: 4px; display: none; }
          #apply-status-msg.success { background: #d1fae5; color: #065f46; display: block; }
          #apply-status-msg.error { background: #fee2e2; color: #991b1b; display: block; }
          #apply-status-msg.info { background: #dbeafe; color: #1e40af; display: block; }
        ',
      ],
      'job_view_styles',
    ];

    // Apply button AJAX handler.
    $content['#attached']['html_head'][] = [
      [
        '#tag' => 'script',
        '#value' => '
(function() {
  document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".btn-apply-job").forEach(function(btn) {
      btn.addEventListener("click", function() {
        var jobId     = btn.dataset.jobId;
        var applyUrl  = btn.dataset.applyUrl;
        var token     = btn.dataset.token;
        var statusEl  = document.getElementById("apply-status-msg");

        btn.disabled = true;
        btn.textContent = "⏳ Processing...";

        fetch(applyUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": token
          },
          credentials: "same-origin"
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.status === "manual_required" || data.ats_platform === "aggregator" || data.ats_platform === "unknown") {
            btn.textContent = "📋 Apply Manually";
            btn.classList.remove("btn-apply-job");
            btn.classList.add("btn-secondary");
            if (statusEl) {
              statusEl.className = "info";
              var link = data.apply_url ? " <a href=\"" + data.apply_url + "\" target=\"_blank\">Open application ↗</a>" : "";
              statusEl.innerHTML = "✅ Tracked! This job requires manual submission." + link;
            }
          } else if (data.success) {
            btn.textContent = "✅ Applied";
            btn.classList.add("btn-success");
            if (statusEl) {
              statusEl.className = "success";
              statusEl.textContent = data.message || "Application submitted!";
            }
          } else {
            btn.textContent = "❌ Failed — Retry";
            btn.classList.add("btn-danger");
            btn.disabled = false;
            if (statusEl) {
              statusEl.className = "error";
              statusEl.textContent = data.error || "Submission failed. Please try again.";
            }
          }
        })
        .catch(function(err) {
          btn.textContent = "❌ Error — Retry";
          btn.disabled = false;
          if (statusEl) {
            statusEl.className = "error";
            statusEl.textContent = "Network error. Please try again.";
          }
        });
      });
    });
  });
})();
        ',
      ],
      'job_apply_js',
    ];

    // Status message container (populated by AJAX).
    $content['apply_status_msg'] = [
      '#markup' => '<div id="apply-status-msg"></div>',
    ];

    // Application Notes block — visible only for saved jobs.
    $uid = (int) $this->currentUser->id();
    $saved_job = $this->database->select('jobhunter_saved_jobs', 'sj')
      ->fields('sj', ['id', 'deadline_date', 'follow_up_date'])
      ->condition('sj.uid', $uid)
      ->condition('sj.job_id', (int) $job_id)
      ->execute()
      ->fetchObject();

    if ($saved_job) {
      $saved_job_id = (int) $saved_job->id;
      $existing_notes = $this->database->select('jobhunter_application_notes', 'an')
        ->fields('an', ['manager_name', 'contact_email', 'last_contact_date', 'notes'])
        ->condition('an.uid', $uid)
        ->condition('an.saved_job_id', $saved_job_id)
        ->execute()
        ->fetchObject();

      $notes_save_url = Url::fromRoute('job_hunter.application_notes_save', ['job_id' => (int) $job_id])->toString();
      $notes_csrf_token = \Drupal::csrfToken()->get('jobhunter/jobs/' . (int) $job_id . '/notes/save');

      $f_manager = htmlspecialchars((string) ($existing_notes->manager_name ?? ''));
      $f_email    = htmlspecialchars((string) ($existing_notes->contact_email ?? ''));
      $f_date     = htmlspecialchars((string) ($existing_notes->last_contact_date ?? ''));
      $f_notes    = htmlspecialchars((string) ($existing_notes->notes ?? ''));

      $content['application_notes'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['application-notes-section']],
        '#markup' => '
<h3>Application Notes</h3>
<div class="application-notes-form">
  <div class="notes-field-row">
    <label for="notes-manager-name">Hiring Manager Name</label>
    <input type="text" id="notes-manager-name" name="manager_name" value="' . $f_manager . '" maxlength="255" placeholder="Optional" />
  </div>
  <div class="notes-field-row">
    <label for="notes-contact-email">Contact Email</label>
    <input type="email" id="notes-contact-email" name="contact_email" value="' . $f_email . '" maxlength="255" placeholder="Optional" />
  </div>
  <div class="notes-field-row">
    <label for="notes-last-contact-date">Last Contact Date</label>
    <input type="date" id="notes-last-contact-date" name="last_contact_date" value="' . $f_date . '" />
  </div>
  <div class="notes-field-row">
    <label for="notes-text">Notes <span class="notes-char-count"></span></label>
    <textarea id="notes-text" name="notes" maxlength="2000" rows="5" placeholder="Optional">' . $f_notes . '</textarea>
  </div>
  <button type="button" class="btn-notes-save" data-save-url="' . $notes_save_url . '" data-token="' . $notes_csrf_token . '">Save Notes</button>
  <div id="notes-status-msg"></div>
</div>',
      ];

      $content['#attached']['html_head'][] = [
        [
          '#tag' => 'style',
          '#value' => '
            .application-notes-section { margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea; }
            .application-notes-section h3 { margin: 0 0 15px 0; color: #333; }
            .notes-field-row { margin-bottom: 14px; }
            .notes-field-row label { display: block; font-weight: 600; color: #555; margin-bottom: 4px; font-size: 0.9em; }
            .notes-field-row input[type="text"],
            .notes-field-row input[type="email"],
            .notes-field-row input[type="date"] { width: 100%; max-width: 400px; padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.95em; }
            .notes-field-row textarea { width: 100%; max-width: 700px; padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.95em; resize: vertical; }
            .notes-char-count { font-size: 0.8em; color: #9ca3af; font-weight: normal; }
            .btn-notes-save { margin-top: 8px; background: #667eea; color: #fff; border: none; padding: 8px 18px; border-radius: 4px; cursor: pointer; font-size: 0.95em; }
            .btn-notes-save:hover { background: #5563d0; }
            .btn-notes-save:disabled { opacity: 0.6; cursor: not-allowed; }
            #notes-status-msg { margin-top: 8px; font-size: 0.9em; padding: 8px 12px; border-radius: 4px; display: none; }
            #notes-status-msg.success { background: #d1fae5; color: #065f46; display: block; }
            #notes-status-msg.error { background: #fee2e2; color: #991b1b; display: block; }
          ',
        ],
        'application_notes_styles',
      ];

      $content['#attached']['html_head'][] = [
        [
          '#tag' => 'script',
          '#value' => '
(function() {
  var textarea = document.getElementById("notes-text");
  var charCount = document.querySelector(".notes-char-count");
  if (textarea && charCount) {
    function updateCount() { charCount.textContent = "(" + textarea.value.length + "/2000)"; }
    textarea.addEventListener("input", updateCount);
    updateCount();
  }
  var saveBtn = document.querySelector(".btn-notes-save");
  if (!saveBtn) { return; }
  saveBtn.addEventListener("click", function() {
    var saveUrl = saveBtn.dataset.saveUrl + "?token=" + encodeURIComponent(saveBtn.dataset.token);
    var statusEl = document.getElementById("notes-status-msg");
    var payload = {
      manager_name: document.getElementById("notes-manager-name").value,
      contact_email: document.getElementById("notes-contact-email").value,
      last_contact_date: document.getElementById("notes-last-contact-date").value,
      notes: document.getElementById("notes-text").value
    };
    saveBtn.disabled = true;
    saveBtn.textContent = "Saving\u2026";
    fetch(saveUrl, {
      method: "POST",
      headers: {"Content-Type": "application/json"},
      credentials: "same-origin",
      body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json().then(function(d) { return {status: r.status, data: d}; }); })
    .then(function(res) {
      saveBtn.disabled = false;
      saveBtn.textContent = "Save Notes";
      if (statusEl) {
        statusEl.className = res.status === 200 ? "success" : "error";
        statusEl.textContent = res.status === 200 ? (res.data.message || "Notes saved.") : (res.data.error || "Save failed.");
        setTimeout(function() { statusEl.className = ""; statusEl.textContent = ""; }, 4000);
      }
    })
    .catch(function() {
      saveBtn.disabled = false;
      saveBtn.textContent = "Save Notes";
      if (statusEl) { statusEl.className = "error"; statusEl.textContent = "Network error. Please try again."; }
    });
  });
})();
          ',
        ],
        'application_notes_js',
      ];
    }

    // Deadline tracker form — visible only for saved jobs where schema columns exist.
    if ($saved_job && $this->database->schema()->fieldExists('jobhunter_saved_jobs', 'deadline_date')) {
      $dl_date  = htmlspecialchars((string) ($saved_job->deadline_date ?? ''));
      $fu_date  = htmlspecialchars((string) ($saved_job->follow_up_date ?? ''));
      $dl_save_url   = Url::fromRoute('job_hunter.deadline_save', ['job_id' => (int) $job_id])->toString();
      $dl_csrf_token = \Drupal::csrfToken()->get('jobhunter/jobs/' . (int) $job_id . '/deadline/save');

      $content['deadline_tracker'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['deadline-tracker-section']],
        '#markup' => '
<h3>&#128197; Application Dates</h3>
<div class="deadline-form">
  <div class="deadline-field-row">
    <label for="deadline-date">Application Deadline</label>
    <input type="date" id="deadline-date" name="deadline_date" value="' . $dl_date . '" />
  </div>
  <div class="deadline-field-row">
    <label for="followup-date">Follow-up Reminder</label>
    <input type="date" id="followup-date" name="follow_up_date" value="' . $fu_date . '" />
  </div>
  <button type="button" class="btn-deadline-save" data-save-url="' . $dl_save_url . '" data-token="' . $dl_csrf_token . '">Save Dates</button>
  <div id="deadline-status-msg"></div>
</div>',
      ];

      $content['#attached']['html_head'][] = [
        [
          '#tag' => 'style',
          '#value' => '
            .deadline-tracker-section { margin-top: 24px; padding: 20px; background: #f0fdf4; border-radius: 8px; border-left: 4px solid #10b981; }
            .deadline-tracker-section h3 { margin: 0 0 14px 0; color: #333; }
            .deadline-field-row { margin-bottom: 12px; }
            .deadline-field-row label { display: block; font-weight: 600; color: #555; margin-bottom: 4px; font-size: 0.9em; }
            .deadline-field-row input[type="date"] { padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.95em; width: 200px; }
            .btn-deadline-save { margin-top: 4px; background: #10b981; color: #fff; border: none; padding: 8px 18px; border-radius: 4px; cursor: pointer; font-size: 0.95em; }
            .btn-deadline-save:hover { background: #059669; }
            .btn-deadline-save:disabled { opacity: 0.6; cursor: not-allowed; }
            #deadline-status-msg { margin-top: 8px; font-size: 0.9em; padding: 8px 12px; border-radius: 4px; display: none; }
            #deadline-status-msg.success { background: #d1fae5; color: #065f46; display: block; }
            #deadline-status-msg.error { background: #fee2e2; color: #991b1b; display: block; }
          ',
        ],
        'deadline_tracker_styles',
      ];

      $content['#attached']['html_head'][] = [
        [
          '#tag' => 'script',
          '#value' => '
(function() {
  var saveBtn = document.querySelector(".btn-deadline-save");
  if (!saveBtn) { return; }
  saveBtn.addEventListener("click", function() {
    var saveUrl = saveBtn.dataset.saveUrl + "?token=" + encodeURIComponent(saveBtn.dataset.token);
    var statusEl = document.getElementById("deadline-status-msg");
    var payload = {
      deadline_date: document.getElementById("deadline-date").value,
      follow_up_date: document.getElementById("followup-date").value
    };
    saveBtn.disabled = true;
    saveBtn.textContent = "Saving\u2026";
    fetch(saveUrl, {
      method: "POST",
      headers: {"Content-Type": "application/json"},
      credentials: "same-origin",
      body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json().then(function(d) { return {status: r.status, data: d}; }); })
    .then(function(res) {
      saveBtn.disabled = false;
      saveBtn.textContent = "Save Dates";
      if (statusEl) {
        statusEl.className = res.status === 200 ? "success" : "error";
        statusEl.textContent = res.status === 200 ? (res.data.message || "Dates saved.") : (res.data.error || "Save failed.");
        setTimeout(function() { statusEl.className = ""; statusEl.textContent = ""; }, 4000);
      }
    })
    .catch(function() {
      saveBtn.disabled = false;
      saveBtn.textContent = "Save Dates";
      if (statusEl) { statusEl.className = "error"; statusEl.textContent = "Network error. Please try again."; }
    });
  });
})();
          ',
        ],
        'deadline_tracker_js',
      ];
    }

    return $this->wrapWithNavigation($content);
  }

  /**
   * Return existing application notes as JSON (GET, no CSRF needed).
   *
   * @param int $job_id
   *   The job_requirements ID.
   */
  public function applicationNotesLoad($job_id): JsonResponse {
    $uid = (int) $this->currentUser->id();
    $job_id = (int) $job_id;

    $saved_job = $this->database->select('jobhunter_saved_jobs', 'sj')
      ->fields('sj', ['id'])
      ->condition('sj.uid', $uid)
      ->condition('sj.job_id', $job_id)
      ->execute()
      ->fetchObject();

    if (!$saved_job) {
      return new JsonResponse(['manager_name' => '', 'contact_email' => '', 'last_contact_date' => '', 'notes' => '']);
    }

    $saved_job_id = (int) $saved_job->id;
    $row = $this->database->select('jobhunter_application_notes', 'an')
      ->fields('an', ['manager_name', 'contact_email', 'last_contact_date', 'notes'])
      ->condition('an.uid', $uid)
      ->condition('an.saved_job_id', $saved_job_id)
      ->execute()
      ->fetchObject();

    return new JsonResponse([
      'manager_name'      => (string) ($row->manager_name ?? ''),
      'contact_email'     => (string) ($row->contact_email ?? ''),
      'last_contact_date' => (string) ($row->last_contact_date ?? ''),
      'notes'             => (string) ($row->notes ?? ''),
    ]);
  }

  /**
   * Save (create or update) application notes (POST, CSRF-protected).
   *
   * @param int $job_id
   *   The job_requirements ID.
   */
  public function applicationNotesSave($job_id): JsonResponse {
    $uid = (int) $this->currentUser->id();
    $job_id = (int) $job_id;
    $request = $this->requestStack->getCurrentRequest();

    // Ownership check: saved_job must belong to this user.
    $saved_job = $this->database->select('jobhunter_saved_jobs', 'sj')
      ->fields('sj', ['id'])
      ->condition('sj.uid', $uid)
      ->condition('sj.job_id', $job_id)
      ->execute()
      ->fetchObject();

    if (!$saved_job) {
      return new JsonResponse(['error' => 'Access denied.'], 403);
    }
    $saved_job_id = (int) $saved_job->id;

    // Parse JSON body.
    $body = json_decode((string) $request->getContent(), TRUE) ?? [];

    $manager_name      = strip_tags((string) ($body['manager_name'] ?? ''));
    $contact_email_raw = (string) ($body['contact_email'] ?? '');
    $last_contact_date = preg_replace('/[^0-9\-]/', '', (string) ($body['last_contact_date'] ?? ''));
    $notes_raw         = (string) ($body['notes'] ?? '');

    // Validate email (AC-6).
    if ($contact_email_raw !== '' && !filter_var($contact_email_raw, FILTER_VALIDATE_EMAIL)) {
      return new JsonResponse(['error' => 'Invalid email address.'], 422);
    }
    $contact_email = $contact_email_raw;

    // Enforce notes length limit (AC-5).
    if (mb_strlen($notes_raw) > 2000) {
      return new JsonResponse(['error' => 'Notes may not exceed 2000 characters.'], 400);
    }
    $notes = strip_tags($notes_raw);

    $now = time();

    $existing_id = $this->database->select('jobhunter_application_notes', 'an')
      ->fields('an', ['id'])
      ->condition('an.uid', $uid)
      ->condition('an.saved_job_id', $saved_job_id)
      ->execute()
      ->fetchField();

    if ($existing_id) {
      $this->database->update('jobhunter_application_notes')
        ->fields([
          'manager_name'      => $manager_name ?: NULL,
          'contact_email'     => $contact_email ?: NULL,
          'last_contact_date' => $last_contact_date ?: NULL,
          'notes'             => $notes ?: NULL,
          'changed'           => $now,
        ])
        ->condition('uid', $uid)
        ->condition('saved_job_id', $saved_job_id)
        ->execute();
    }
    else {
      $this->database->insert('jobhunter_application_notes')
        ->fields([
          'uid'               => $uid,
          'saved_job_id'      => $saved_job_id,
          'manager_name'      => $manager_name ?: NULL,
          'contact_email'     => $contact_email ?: NULL,
          'last_contact_date' => $last_contact_date ?: NULL,
          'notes'             => $notes ?: NULL,
          'created'           => $now,
          'changed'           => $now,
        ])
        ->execute();
    }

    // SEC-5: log only uid and saved_job_id, never PII fields.
    $this->getLogger('job_hunter')->info('Application notes saved: uid=@uid saved_job_id=@sjid', [
      '@uid'  => $uid,
      '@sjid' => $saved_job_id,
    ]);

    return new JsonResponse(['status' => 'ok', 'message' => 'Notes saved.']);
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

    // AC-6: Cross-user access check — users may only view their own job tailoring.
    if ((int) $job->uid !== (int) $this->currentUser->id()) {
      throw new AccessDeniedHttpException();
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
    $save_resume_url = Url::fromRoute('job_hunter.job_tailoring_save_resume', ['job_id' => $job_id])->toString();
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
      '#confidence_score' => $tailored_record ? (int) ($tailored_record->confidence_score ?? 0) : 0,
      '#save_resume_url' => $save_resume_url,
      '#attached' => [
        'library' => [
          'job_hunter/job-hunter-navigation',
          'job_hunter/job-hunter-home',
          'job_hunter/tailor_resume',
        ],
      ],
    ];
    
    return $this->wrapWithNavigation($content);
  }

  /**
   * POST handler: save a completed tailored resume as the user's active resume.
   *
   * Route: job_hunter.job_tailoring_save_resume (POST, CSRF-protected).
   *
   * @param int $job_id
   *   The job whose tailored resume is being saved.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect back to the tailoring page with a status message.
   */
  public function saveResume($job_id) {
    $uid = (int) $this->currentUser->id();

    // Load the tailored resume record (scoped to current user + job).
    $tailored_record = $this->database->select('jobhunter_tailored_resumes', 'tr')
      ->fields('tr', ['id', 'tailoring_status', 'uid'])
      ->condition('tr.uid', $uid)
      ->condition('tr.job_id', (int) $job_id)
      ->execute()
      ->fetchObject();

    if (!$tailored_record) {
      $this->messenger()->addError($this->t('Tailored resume not found.'));
      return new RedirectResponse(Url::fromRoute('job_hunter.job_tailoring', ['job_id' => $job_id])->toString());
    }

    // Ownership double-check (belt-and-suspenders; route already guards).
    if ((int) $tailored_record->uid !== $uid) {
      throw new AccessDeniedHttpException();
    }

    if ($tailored_record->tailoring_status !== 'completed') {
      $this->messenger()->addWarning($this->t('Tailoring must be completed before saving to your profile.'));
      return new RedirectResponse(Url::fromRoute('job_hunter.job_tailoring', ['job_id' => $job_id])->toString());
    }

    // Update jobhunter_job_seeker to point to this tailored resume as active.
    $updated = $this->database->update('jobhunter_job_seeker')
      ->fields(['active_tailored_resume_id' => (int) $tailored_record->id])
      ->condition('uid', $uid)
      ->execute();

    if ($updated > 0) {
      $this->messenger()->addStatus($this->t('Tailored resume saved as your active resume.'));
    }
    else {
      $this->messenger()->addWarning($this->t('Profile record not found; could not save resume. Please complete your profile first.'));
    }

    return new RedirectResponse(Url::fromRoute('job_hunter.job_tailoring', ['job_id' => $job_id])->toString());
  }

  /**
   * Cover letter display page — GET /jobhunter/coverletter/{job_id}.
   */
  public function coverLetter($job_id) {
    $uid = (int) $this->currentUser->id();
    $job_id = (int) $job_id;

    // Load and verify job ownership.
    $job = $this->database->select('jobhunter_job_requirements', 'j')
      ->fields('j', ['id', 'job_title', 'uid'])
      ->condition('j.id', $job_id)
      ->execute()
      ->fetchObject();

    if (!$job) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    if ((int) $job->uid !== $uid) {
      throw new AccessDeniedHttpException();
    }

    // Load existing cover letter record (may be NULL).
    $cover_letter = $this->database->select('jobhunter_cover_letters', 'cl')
      ->fields('cl')
      ->condition('cl.uid', $uid)
      ->condition('cl.job_id', $job_id)
      ->execute()
      ->fetchObject();

    $tailoring_status = $cover_letter ? (string) $cover_letter->tailoring_status : NULL;
    $cover_letter_html = ($tailoring_status === 'completed' && $cover_letter)
      ? (string) ($cover_letter->cover_letter_html ?: '')
      : '';

    $generate_url = Url::fromRoute('job_hunter.cover_letter_generate', ['job_id' => $job_id])->toString();
    $save_url = Url::fromRoute('job_hunter.cover_letter_save', ['job_id' => $job_id])->toString();

    $build = [
      '#theme' => 'cover_letter_display',
      '#job' => $job,
      '#job_id' => $job_id,
      '#cover_letter' => $cover_letter,
      '#tailoring_status' => $tailoring_status,
      '#cover_letter_html' => $cover_letter_html,
      '#pdf_path' => $cover_letter ? $cover_letter->pdf_path : NULL,
      '#generate_url' => $generate_url,
      '#save_url' => $save_url,
      '#attached' => [
        'drupalSettings' => [
          'jobHunterCoverLetter' => [
            'jobId' => $job_id,
            'status' => $tailoring_status,
          ],
        ],
      ],
    ];

    return $this->wrapWithNavigation($build);
  }

  /**
   * Cover letter generate — POST /jobhunter/coverletter/{job_id}/generate.
   *
   * Creates a jobhunter_cover_letters row (status=queued) if one does not exist
   * (or re-enqueues on retry), then enqueues a queue item.
   */
  public function coverLetterGenerate($job_id) {
    $uid = (int) $this->currentUser->id();
    $job_id = (int) $job_id;

    // Verify job ownership.
    $job = $this->database->select('jobhunter_job_requirements', 'j')
      ->fields('j', ['id', 'uid'])
      ->condition('j.id', $job_id)
      ->execute()
      ->fetchObject();

    if (!$job || (int) $job->uid !== $uid) {
      throw new AccessDeniedHttpException();
    }

    try {
      $existing = $this->database->select('jobhunter_cover_letters', 'cl')
        ->fields('cl', ['id', 'tailoring_status'])
        ->condition('cl.uid', $uid)
        ->condition('cl.job_id', $job_id)
        ->execute()
        ->fetchObject();

      $now = time();

      if (!$existing) {
        $this->database->insert('jobhunter_cover_letters')
          ->fields([
            'uid' => $uid,
            'job_id' => $job_id,
            'tailoring_status' => 'queued',
            'created' => $now,
            'updated' => $now,
          ])
          ->execute();
      }
      else {
        $this->database->update('jobhunter_cover_letters')
          ->fields(['tailoring_status' => 'queued', 'updated' => $now])
          ->condition('uid', $uid)
          ->condition('job_id', $job_id)
          ->execute();
      }

      // Enqueue the cover letter generation item.
      $queue = \Drupal::queue('job_hunter_cover_letter_tailoring');
      $queue->createItem([
        'uid' => $uid,
        'job_id' => $job_id,
      ]);

      $this->messenger()->addStatus($this->t('Cover letter generation queued. Check back shortly.'));
    }
    catch (\Exception $e) {
      $this->getLogger('job_hunter')->error('Cover letter enqueue failed for job @id: @error', [
        '@id' => $job_id,
        '@error' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('Failed to queue cover letter generation. Please try again.'));
    }

    return new RedirectResponse(Url::fromRoute('job_hunter.cover_letter', ['job_id' => $job_id])->toString());
  }

  /**
   * Cover letter save — POST /jobhunter/coverletter/{job_id}/save.
   *
   * Links the completed cover letter to the job application record.
   */
  public function coverLetterSave($job_id) {
    $uid = (int) $this->currentUser->id();
    $job_id = (int) $job_id;

    $cover_letter = $this->database->select('jobhunter_cover_letters', 'cl')
      ->fields('cl', ['id', 'uid', 'tailoring_status'])
      ->condition('cl.uid', $uid)
      ->condition('cl.job_id', $job_id)
      ->execute()
      ->fetchObject();

    if (!$cover_letter || (int) $cover_letter->uid !== $uid) {
      throw new AccessDeniedHttpException();
    }

    if ($cover_letter->tailoring_status !== 'completed') {
      $this->messenger()->addWarning($this->t('Cover letter must be completed before saving.'));
      return new RedirectResponse(Url::fromRoute('job_hunter.cover_letter', ['job_id' => $job_id])->toString());
    }

    // Update application record if one exists, else no-op (graceful).
    $updated = $this->database->update('jobhunter_applications')
      ->fields(['cover_letter_id' => (int) $cover_letter->id, 'updated' => time()])
      ->condition('uid', $uid)
      ->condition('job_id', $job_id)
      ->execute();

    if ($updated > 0) {
      $this->messenger()->addStatus($this->t('Cover letter saved to your application.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Cover letter saved. It will be linked when you apply.'));
    }

    return new RedirectResponse(Url::fromRoute('job_hunter.cover_letter', ['job_id' => $job_id])->toString());
  }

  /**
   * Interview prep page — checklist, notes, and AI tips for a saved job.
   *
   * @param int $job_id
   *   The saved job ID (integer enforced by routing pattern \d+).
   *
   * @return array
   *   Render array wrapped in navigation.
   */
  public function interviewPrep($job_id) {
    $uid = (int) $this->currentUser->id();
    $job_id = (int) $job_id;

    $job = $this->database->select('jobhunter_job_requirements', 'j')
      ->fields('j', ['id', 'job_title', 'uid'])
      ->condition('j.id', $job_id)
      ->execute()
      ->fetchObject();

    if (!$job) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    if ((int) $job->uid !== $uid) {
      throw new AccessDeniedHttpException();
    }

    $notes_text = $this->database->select('jobhunter_interview_notes', 'n')
      ->fields('n', ['notes_text'])
      ->condition('n.uid', $uid)
      ->condition('n.job_id', $job_id)
      ->execute()
      ->fetchField();

    $build = [
      '#theme' => 'interview_prep_page',
      '#job' => $job,
      '#job_id' => $job_id,
      '#notes_text' => $notes_text ?: '',
      '#save_url' => Url::fromRoute('job_hunter.interview_prep_save', ['job_id' => $job_id])->toString(),
      '#ai_tips_url' => Url::fromRoute('job_hunter.interview_prep_ai_tips', ['job_id' => $job_id])->toString(),
    ];

    return $this->wrapWithNavigation($build);
  }

  /**
   * Save interview prep notes (POST, CSRF-guarded).
   *
   * @param int $job_id
   *   The saved job ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function interviewPrepSave($job_id) {
    $uid = (int) $this->currentUser->id();
    $job_id = (int) $job_id;
    $request = $this->requestStack->getCurrentRequest();

    $job = $this->database->select('jobhunter_job_requirements', 'j')
      ->fields('j', ['id', 'uid'])
      ->condition('j.id', $job_id)
      ->execute()
      ->fetchObject();

    if (!$job) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    if ((int) $job->uid !== $uid) {
      throw new AccessDeniedHttpException();
    }

    $notes_raw = (string) $request->request->get('notes_text', '');
    if (mb_strlen($notes_raw) > 10000) {
      $this->messenger()->addError($this->t('Notes may not exceed 10,000 characters.'));
      return new RedirectResponse(Url::fromRoute('job_hunter.interview_prep', ['job_id' => $job_id])->toString());
    }
    $notes = strip_tags($notes_raw);

    $existing_id = $this->database->select('jobhunter_interview_notes', 'n')
      ->fields('n', ['id'])
      ->condition('n.uid', $uid)
      ->condition('n.job_id', $job_id)
      ->execute()
      ->fetchField();

    if ($existing_id) {
      $this->database->update('jobhunter_interview_notes')
        ->fields(['notes_text' => $notes, 'updated' => time()])
        ->condition('uid', $uid)
        ->condition('job_id', $job_id)
        ->execute();
    }
    else {
      $this->database->insert('jobhunter_interview_notes')
        ->fields([
          'uid' => $uid,
          'job_id' => $job_id,
          'notes_text' => $notes,
          'updated' => time(),
        ])
        ->execute();
    }

    $this->messenger()->addStatus($this->t('Interview notes saved.'));
    return new RedirectResponse(Url::fromRoute('job_hunter.interview_prep', ['job_id' => $job_id])->toString());
  }

  /**
   * Return AI-generated interview tips as JSON (POST, CSRF-guarded, AJAX).
   *
   * @param int $job_id
   *   The saved job ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function interviewPrepAiTips($job_id) {
    $uid = (int) $this->currentUser->id();
    $job_id = (int) $job_id;

    $job = $this->database->select('jobhunter_job_requirements', 'j')
      ->fields('j', ['id', 'uid', 'job_title', 'job_description'])
      ->condition('j.id', $job_id)
      ->execute()
      ->fetchObject();

    if (!$job || (int) $job->uid !== $uid) {
      return new JsonResponse(['error' => 'Access denied.'], 403);
    }

    $job_title = (string) ($job->job_title ?: 'this role');
    $job_desc_snippet = substr((string) ($job->job_description ?: ''), 0, 500);

    $profile_summary = '';
    try {
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
      if ($user && $user->hasField('field_professional_summary')) {
        $profile_summary = (string) $user->get('field_professional_summary')->getString();
      }
    }
    catch (\Exception $e) {
      // Non-fatal — proceed without profile summary.
    }

    $prompt = "You are a career coach. Give 3-5 concise bullet-point interview preparation tips for a candidate applying for the following position.\n\nJob Title: {$job_title}\n";
    if ($job_desc_snippet) {
      $prompt .= "Job Description (excerpt): {$job_desc_snippet}\n";
    }
    if ($profile_summary) {
      $prompt .= "Candidate Summary: {$profile_summary}\n";
    }
    $prompt .= "\nRespond with ONLY a JSON object: {\"tips\": [\"tip1\", \"tip2\", ...]}";

    try {
      $ai_service = \Drupal::service('ai_conversation.ai_api_service');
      $result = $ai_service->invokeModelDirect(
        $prompt,
        'job_hunter',
        'interview_prep_tips',
        ['job_id' => $job_id],
        ['skip_cache' => FALSE]
      );

      if (!empty($result['success']) && !empty($result['response'])) {
        $raw = trim($result['response']);
        $decoded = json_decode($raw, TRUE);
        if (is_array($decoded) && !empty($decoded['tips'])) {
          return new JsonResponse(['tips' => $decoded['tips']]);
        }
        // Fallback: parse plain-text bullets if JSON parsing fails.
        $lines = array_filter(explode("\n", $raw));
        $tips = array_values(array_filter(
          array_map(fn($l) => trim(ltrim($l, '-•* ')), $lines)
        ));
        return new JsonResponse(['tips' => array_slice($tips, 0, 5)]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('interview_prep_ai_tips error for job @id: @code', [
        '@id' => $job_id,
        '@code' => get_class($e),
      ]);
    }

    return new JsonResponse(['error' => 'Could not generate tips. Please try again later.'], 500);
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

  /**
   * POST handler: initiate automated application for a job.
   *
   * Route: POST /jobhunter/jobs/{job_id}/apply
   */
  public function applyToJob($job_id) {
    $uid = $this->currentUser->id();
    $request = $this->requestStack->getCurrentRequest();
    $return_to = (string) $request->query->get('return_to', '');
    $redirect_mode = $return_to !== '';
    if ($redirect_mode && !preg_match('/^\/(?!\/)/', $return_to)) {
      $return_to = '/jobhunter/my-jobs';
    }

    if (!$uid) {
      if ($redirect_mode) {
        $this->messenger()->addError($this->t('Not authenticated.'));
        return new RedirectResponse('/user/login');
      }
      return new JsonResponse(['success' => FALSE, 'error' => 'Not authenticated.'], 403);
    }

    // Validate CSRF token from header (AJAX) or form field (My Jobs form).
    $token = $request->headers->get('X-CSRF-Token')
      ?: $request->request->get('csrf_token')
      ?: $request->query->get('csrf_token');
    if (!\Drupal::csrfToken()->validate($token, 'jobhunter/jobs/' . (int) $job_id . '/apply')) {
      if ($redirect_mode) {
        $this->messenger()->addError($this->t('Invalid security token. Please refresh the page and try again.'));
        return new RedirectResponse($return_to ?: '/jobhunter/my-jobs');
      }
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid security token.'], 403);
    }

    /** @var \Drupal\job_hunter\Service\ApplicationSubmissionService $submission_service */
    $submission_service = \Drupal::service('job_hunter.application_submission_service');
    /** @var \Drupal\job_hunter\Service\ApplyUrlResolverService $resolver */
    $resolver = \Drupal::service('job_hunter.apply_url_resolver');

    // Load job (only the columns that actually exist on this table).
    $job = $this->database->select('jobhunter_job_requirements', 'j')
      ->fields('j', ['id', 'apply_options', 'job_url', 'job_title'])
      ->condition('id', (int) $job_id)
      ->execute()
      ->fetchAssoc();

    if (!$job) {
      if ($redirect_mode) {
        $this->messenger()->addError($this->t('Job not found.'));
        return new RedirectResponse($return_to ?: '/jobhunter/my-jobs');
      }
      return new JsonResponse(['success' => FALSE, 'error' => 'Job not found.'], 404);
    }

    // Resolve the best apply URL before queuing (result stored on the application record).
    $resolved = $resolver->resolve($job);

    // Submit application — this validates prerequisites internally and queues it.
    $result = $submission_service->submitApplication($uid, (int) $job_id, TRUE);

    if (!$result['success'] && ($result['status'] ?? '') !== 'queued') {
      if ($redirect_mode) {
        $this->messenger()->addError($this->t($result['message'] ?? 'Submission failed.'));
        return new RedirectResponse($return_to ?: '/jobhunter/my-jobs');
      }
      return new JsonResponse([
        'success' => FALSE,
        'error'   => $result['message'] ?? 'Submission failed.',
        'details' => $result['error'] ?? [],
      ], 422);
    }

    // Update the application record with resolved URL and ATS metadata.
    if (!empty($result['application_id'])) {
      $update_fields = [
        'apply_url'             => $resolved['url'],
        'selected_apply_option' => $resolved['selected_option'],
        'metadata'              => json_encode([
          'resolution_steps' => $resolved['resolution_steps'],
          'confidence'       => $resolved['confidence'],
        ]),
        'changed' => date('Y-m-d H:i:s'),
      ];
      if ($this->database->schema()->fieldExists('jobhunter_applications', 'ats_platform')) {
        $update_fields['ats_platform'] = $resolved['ats_platform'];
      }

      $this->database->update('jobhunter_applications')
        ->fields($update_fields)
        ->condition('id', $result['application_id'])
        ->execute();
    }

    // Determine UI response based on ATS platform.
    $platform  = $resolved['ats_platform'];
    $apply_url = $resolved['url'] ?: ($job['job_url'] ?? '');

    if (in_array($platform, ['aggregator', 'unknown', ''])) {
      if ($redirect_mode) {
        $this->messenger()->addWarning($this->t('Application tracked. This job requires manual submission.'));
        return new RedirectResponse($return_to ?: '/jobhunter/my-jobs');
      }
      return new JsonResponse([
        'success'        => TRUE,
        'status'         => 'manual_required',
        'message'        => 'Application tracked. This job requires manual submission.',
        'apply_url'      => $apply_url,
        'ats_platform'   => $platform,
        'application_id' => $result['application_id'] ?? NULL,
      ]);
    }

    if ($redirect_mode) {
      $this->messenger()->addStatus($this->t($result['message'] ?? 'Application queued for submission.'));
      return new RedirectResponse($return_to ?: '/jobhunter/my-jobs');
    }

    return new JsonResponse([
      'success'        => $result['success'],
      'status'         => $result['status'] ?? 'queued',
      'message'        => $result['message'] ?? 'Application queued for submission.',
      'ats_platform'   => $platform,
      'apply_url'      => $apply_url,
      'application_id' => $result['application_id'] ?? NULL,
    ]);
  }

  /**
   * GET handler: return current application status for a job.
   *
   * Route: GET /jobhunter/jobs/{job_id}/application-status
   */
  public function applicationStatus($job_id) {
    $uid = $this->currentUser->id();
    if (!$uid) {
      return new JsonResponse(['error' => 'Not authenticated.'], 403);
    }

    $app_query = $this->database->select('jobhunter_applications', 'a')
      ->fields('a', ['id', 'submission_status', 'apply_url', 'selected_apply_option', 'attempt_count', 'confirmation_reference', 'submission_date', 'automation_success', 'admin_review_required', 'created'])
      ->condition('a.uid', $uid)
      ->condition('a.job_id', (int) $job_id)
      ->orderBy('a.created', 'DESC')
      ->range(0, 1);
    if ($this->database->schema()->fieldExists('jobhunter_applications', 'ats_platform')) {
      $app_query->addField('a', 'ats_platform');
    }
    else {
      $app_query->addExpression("''", 'ats_platform');
    }

    $app = $app_query
      ->execute()
      ->fetchAssoc();

    if (!$app) {
      return new JsonResponse(['applied' => FALSE]);
    }

    // Get attempt history.
    $attempts = $this->database->select('jobhunter_application_attempts', 'at')
      ->fields('at', ['attempted_at', 'ats_detected', 'outcome', 'error_message'])
      ->condition('application_id', $app['id'])
      ->orderBy('attempted_at', 'DESC')
      ->range(0, 5)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return new JsonResponse([
      'applied'                => TRUE,
      'application_id'         => $app['id'],
      'status'                 => $app['submission_status'],
      'ats_platform'           => $app['ats_platform'],
      'apply_url'              => $app['apply_url'],
      'selected_apply_option'  => $app['selected_apply_option'],
      'attempt_count'          => (int) $app['attempt_count'],
      'confirmation_reference' => $app['confirmation_reference'],
      'submission_date'        => $app['submission_date'],
      'automation_success'     => (bool) $app['automation_success'],
      'admin_review_required'  => (bool) $app['admin_review_required'],
      'created'                => $app['created'],
      'attempts'               => $attempts ?: [],
    ]);
  }

  /**
   * Save deadline_date and follow_up_date for a saved job (POST, CSRF-protected).
   *
   * @param int $job_id
   *   The job_requirements ID.
   */
  public function deadlineSave($job_id): JsonResponse {
    $uid    = (int) $this->currentUser->id();
    $job_id = (int) $job_id;

    $ownership = $this->database->select('jobhunter_saved_jobs', 'sj')
      ->fields('sj', ['id'])
      ->condition('sj.uid', $uid)
      ->condition('sj.job_id', $job_id)
      ->execute()
      ->fetchField();

    if (!$ownership) {
      return new JsonResponse(['error' => 'Not found.'], 403);
    }

    $request = $this->requestStack->getCurrentRequest();
    $body    = json_decode($request->getContent(), TRUE) ?? [];

    $deadline_date  = isset($body['deadline_date'])  && $body['deadline_date']  !== '' ? $body['deadline_date']  : NULL;
    $follow_up_date = isset($body['follow_up_date']) && $body['follow_up_date'] !== '' ? $body['follow_up_date'] : NULL;

    if ($deadline_date !== NULL) {
      $parsed = \DateTime::createFromFormat('Y-m-d', $deadline_date);
      if (!$parsed || $parsed->format('Y-m-d') !== $deadline_date) {
        return new JsonResponse(['error' => 'Invalid deadline date format. Use YYYY-MM-DD.'], 400);
      }
    }
    if ($follow_up_date !== NULL) {
      $parsed = \DateTime::createFromFormat('Y-m-d', $follow_up_date);
      if (!$parsed || $parsed->format('Y-m-d') !== $follow_up_date) {
        return new JsonResponse(['error' => 'Invalid follow-up date format. Use YYYY-MM-DD.'], 400);
      }
    }

    $this->database->update('jobhunter_saved_jobs')
      ->fields([
        'deadline_date'  => $deadline_date,
        'follow_up_date' => $follow_up_date,
        'updated'        => time(),
      ])
      ->condition('uid', $uid)
      ->condition('job_id', $job_id)
      ->execute();

    return new JsonResponse(['message' => 'Dates saved.']);
  }

  /**
   * Show all saved jobs with deadline urgency indicators at /jobhunter/status.
   */
  public function statusDashboard(): array {
    $uid  = (int) $this->currentUser->id();
    $today = new \DateTime('today');

    $rows = $this->database->select('jobhunter_saved_jobs', 'sj')
      ->fields('sj', ['job_id', 'deadline_date', 'follow_up_date', 'archived'])
      ->condition('sj.uid', $uid)
      ->condition('sj.archived', 0)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $job_ids = array_column($rows, 'job_id');
    $jobs_by_id = [];
    if ($job_ids) {
      $job_results = $this->database->select('jobhunter_job_requirements', 'jr')
        ->fields('jr', ['id', 'job_title'])
        ->condition('jr.id', $job_ids, 'IN')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($job_results as $j) {
        $jobs_by_id[(int) $j['id']] = $j['job_title'];
      }
      $company_results = $this->database->query(
        'SELECT jr.id as job_id, c.name as company_name
         FROM {jobhunter_job_requirements} jr
         LEFT JOIN {jobhunter_companies} c ON jr.company_id = c.id
         WHERE jr.id IN (:ids[])',
        [':ids[]' => $job_ids]
      )->fetchAll(\PDO::FETCH_ASSOC);
      $companies_by_job = [];
      foreach ($company_results as $cr) {
        $companies_by_job[(int) $cr['job_id']] = $cr['company_name'] ?? '';
      }
    }

    $table_rows = [];
    foreach ($rows as $row) {
      $job_id    = (int) $row['job_id'];
      $job_title = $jobs_by_id[$job_id] ?? 'Unknown Job';
      $company   = $companies_by_job[$job_id] ?? '';
      $dl        = $row['deadline_date'];
      $fu        = $row['follow_up_date'];

      $urgency_class = '';
      $urgency_label = '';
      if ($dl) {
        $dl_dt = new \DateTime($dl);
        $diff  = (int) $today->diff($dl_dt)->days;
        $past  = $dl_dt < $today;
        if ($past) {
          $urgency_class = 'deadline-overdue';
          $urgency_label = 'Overdue';
        }
        elseif ($diff <= 3) {
          $urgency_class = 'deadline-soon';
          $urgency_label = $diff === 0 ? 'Due today' : 'Due in ' . $diff . 'd';
        }
        else {
          $urgency_label = $dl;
        }
      }

      $job_url = Url::fromRoute('job_hunter.view_job', ['job_id' => $job_id])->toString();
      $table_rows[] = [
        ['data' => '<a href="' . $job_url . '">' . htmlspecialchars($job_title) . '</a>', 'allow_html' => TRUE],
        htmlspecialchars($company),
        ['data' => '<span class="' . $urgency_class . '">' . htmlspecialchars($urgency_label ?: '—') . '</span>', 'allow_html' => TRUE],
        htmlspecialchars($fu ?: '—'),
      ];
    }

    $content = [
      '#type' => 'container',
      'heading' => ['#markup' => '<h2>Application Status</h2>'],
    ];

    if ($table_rows) {
      $content['table'] = [
        '#type'   => 'table',
        '#header' => ['Job', 'Company', 'Deadline', 'Follow-up'],
        '#rows'   => $table_rows,
        '#attributes' => ['class' => ['status-dashboard-table']],
      ];
    }
    else {
      $content['empty'] = ['#markup' => '<p>No active saved jobs found.</p>'];
    }

    $content['#attached']['html_head'][] = [
      [
        '#tag'   => 'style',
        '#value' => '
          .status-dashboard-table { width: 100%; border-collapse: collapse; }
          .status-dashboard-table th, .status-dashboard-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; }
          .status-dashboard-table th { background: #f9fafb; font-weight: 600; color: #374151; }
          .deadline-overdue { color: #dc2626; font-weight: 700; }
          .deadline-soon { color: #d97706; font-weight: 600; }
        ',
      ],
      'status_dashboard_styles',
    ];

    return $this->wrapWithNavigation($content);
  }

  /**
   * Show jobs with upcoming deadlines at /jobhunter/deadlines.
   */
  public function deadlinesList(): array {
    $uid   = (int) $this->currentUser->id();
    $today = new \DateTime('today');

    $rows = $this->database->query(
      'SELECT sj.job_id, sj.deadline_date, sj.follow_up_date,
              jr.job_title, c.name AS company_name
       FROM {jobhunter_saved_jobs} sj
       JOIN {jobhunter_job_requirements} jr ON sj.job_id = jr.id
       LEFT JOIN {jobhunter_companies} c ON jr.company_id = c.id
       WHERE sj.uid = :uid AND sj.archived = 0 AND sj.deadline_date IS NOT NULL
       ORDER BY sj.deadline_date ASC',
      [':uid' => $uid]
    )->fetchAll(\PDO::FETCH_ASSOC);

    $table_rows = [];
    foreach ($rows as $row) {
      $job_id = (int) $row['job_id'];
      $dl     = $row['deadline_date'];
      $dl_dt  = new \DateTime($dl);
      $diff   = (int) $today->diff($dl_dt)->days;
      $past   = $dl_dt < $today;

      $urgency_class = '';
      $urgency_label = '';
      if ($past) {
        $urgency_class = 'deadline-overdue';
        $urgency_label = 'Overdue';
      }
      elseif ($diff <= 3) {
        $urgency_class = 'deadline-soon';
        $urgency_label = $diff === 0 ? 'Due today' : 'Due in ' . $diff . 'd';
      }

      $job_url = Url::fromRoute('job_hunter.view_job', ['job_id' => $job_id])->toString();
      $table_rows[] = [
        ['data' => '<a href="' . $job_url . '">' . htmlspecialchars($row['job_title']) . '</a>', 'allow_html' => TRUE],
        htmlspecialchars($row['company_name'] ?? ''),
        $dl,
        ['data' => '<span class="' . $urgency_class . '">' . htmlspecialchars($urgency_label ?: 'OK') . '</span>', 'allow_html' => TRUE],
        htmlspecialchars($row['follow_up_date'] ?? '—'),
      ];
    }

    $content = [
      '#type' => 'container',
      'heading' => ['#markup' => '<h2>Upcoming Deadlines</h2>'],
    ];

    if ($table_rows) {
      $content['table'] = [
        '#type'   => 'table',
        '#header' => ['Job', 'Company', 'Deadline Date', 'Status', 'Follow-up'],
        '#rows'   => $table_rows,
        '#attributes' => ['class' => ['deadlines-table']],
      ];
    }
    else {
      $content['empty'] = ['#markup' => '<p>No jobs with deadlines set. <a href="/jobhunter/my-jobs">View your saved jobs</a> to add deadlines.</p>'];
    }

    $content['#attached']['html_head'][] = [
      [
        '#tag'   => 'style',
        '#value' => '
          .deadlines-table { width: 100%; border-collapse: collapse; }
          .deadlines-table th, .deadlines-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; }
          .deadlines-table th { background: #f9fafb; font-weight: 600; color: #374151; }
          .deadline-overdue { color: #dc2626; font-weight: 700; }
          .deadline-soon { color: #d97706; font-weight: 600; }
        ',
      ],
      'deadlines_list_styles',
    ];

    return $this->wrapWithNavigation($content);
  }

}
