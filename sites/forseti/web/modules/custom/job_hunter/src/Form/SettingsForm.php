<?php

namespace Drupal\job_hunter\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Job Application Automation module settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['job_hunter.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'job_hunter_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('job_hunter.settings');

    $form['resume_tailoring'] = [
      '#type' => 'details',
      '#title' => $this->t('Resume Tailoring Settings'),
      '#open' => TRUE,
    ];

    // Load the entity if we have an ID stored
    $default_resume = NULL;
    $resume_node_id = $config->get('original_resume_node_id');
    if ($resume_node_id && is_numeric($resume_node_id)) {
      $resume_node = \Drupal\node\Entity\Node::load($resume_node_id);
      if ($resume_node && $resume_node->access('view')) {
        $default_resume = $resume_node;
      }
    }

    $form['resume_tailoring']['original_resume_node_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Original Resume Node'),
      '#description' => $this->t('Select the resume node that contains the master resume content. This will be used as the base for all tailored resumes. Leave empty to search for a node titled "Original Resume".'),
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['resume'],
      ],
      '#default_value' => $default_resume,
    ];

    $form['resume_tailoring']['enable_automatic_tailoring'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Automatic Resume Tailoring'),
      '#description' => $this->t('When enabled, a tailored resume will be automatically generated when a new job posting is created.'),
      '#default_value' => $config->get('enable_automatic_tailoring') ?? TRUE,
    ];

    $form['ai_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Service Configuration'),
      '#open' => FALSE,
    ];

    $form['ai_settings']['ai_service_region'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AWS Region'),
      '#description' => $this->t('The AWS region for Bedrock service (e.g., us-west-2).'),
      '#default_value' => $config->get('ai_service_region') ?? 'us-west-2',
      '#required' => TRUE,
    ];

    $form['ai_settings']['ai_model_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AI Model ID'),
      '#description' => $this->t('The AWS Bedrock model ID to use for resume tailoring.'),
      '#default_value' => $config->get('ai_model_id') ?? 'anthropic.claude-3-5-sonnet-20240620-v1:0',
      '#required' => TRUE,
    ];

    $form['ai_settings']['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens'),
      '#description' => $this->t('Maximum number of tokens for AI generation.'),
      '#default_value' => $config->get('max_tokens') ?? 20000,
      '#required' => TRUE,
      '#min' => 1000,
      '#max' => 50000,
    ];

    $form['google_cloud_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Google Cloud Talent Solution API'),
      '#description' => $this->t('<p>Configure Google Cloud Talent Solution API for advanced job search capabilities.</p><p><strong>Project:</strong> forseti-483518<br><strong>Service Account:</strong> forseti-life@forseti-483518.iam.gserviceaccount.com</p><p>See the <a href="@doc_url" target="_blank">documentation</a> for setup instructions.</p>', [
        '@doc_url' => '/jobhunter/documentation/google-jobs-integration',
      ]),
      '#open' => FALSE,
    ];

    $form['google_cloud_settings']['google_cloud_credentials'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Service Account JSON Key'),
      '#description' => $this->t('Paste the contents of your Google Cloud service account JSON key file here. Get your key from the <a href="@url" target="_blank">Google Cloud Console</a>.<br><br><strong>Note:</strong> You can use the same JSON key for both development and production environments. The key identifies your project and permissions, not the environment.', [
        '@url' => 'https://console.cloud.google.com/talent-solution/connect-service-accounts?project=forseti-483518',
      ]),
      '#default_value' => $config->get('google_cloud_credentials') ?? '',
      '#rows' => 12,
      '#required' => FALSE,
      '#attributes' => [
        'placeholder' => '{"type": "service_account", "project_id": "forseti-483518", ...}',
        'style' => 'font-family: monospace; font-size: 0.9em;',
      ],
    ];

    $form['google_cloud_settings']['tenant_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tenant Name'),
      '#description' => $this->t('The full tenant resource name (e.g., projects/forseti-483518/tenants/76d39aae-4a00-0000-0000-00527559cb6e). Use the "List Tenants" button below to find your tenant name.'),
      '#default_value' => $config->get('tenant_name') ?? '',
      '#required' => FALSE,
      '#attributes' => [
        'placeholder' => 'projects/forseti-483518/tenants/76d39aae-4a00-0000-0000-00527559cb6e',
      ],
    ];

    $form['google_cloud_settings']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['style' => 'display: flex; gap: 10px; margin-top: 10px;'],
    ];

    $form['google_cloud_settings']['actions']['test_credentials'] = [
      '#type' => 'button',
      '#value' => $this->t('Test API Connection'),
      '#ajax' => [
        'callback' => '::testGoogleCloudCredentials',
        'wrapper' => 'google-cloud-test-result',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Testing...')],
      ],
    ];

    $form['google_cloud_settings']['actions']['create_tenant'] = [
      '#type' => 'button',
      '#value' => $this->t('Create Tenant'),
      '#ajax' => [
        'callback' => '::createGoogleCloudTenant',
        'wrapper' => 'google-cloud-test-result',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Creating...')],
      ],
      '#attributes' => ['class' => ['button--primary']],
    ];

    $form['google_cloud_settings']['actions']['list_tenants'] = [
      '#type' => 'button',
      '#value' => $this->t('List Tenants'),
      '#ajax' => [
        'callback' => '::listGoogleCloudTenants',
        'wrapper' => 'google-cloud-test-result',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Loading...')],
      ],
    ];

    $form['google_cloud_settings']['test_result'] = [
      '#type' => 'markup',
      '#markup' => '<div id="google-cloud-test-result" style="margin-top: 15px; padding: 15px; border: 2px solid #ddd; border-radius: 4px; background: #f9f9f9;"><em style="color: #666;">Click a button above to test...</em></div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback to test Google Cloud credentials.
   */
  public function testGoogleCloudCredentials(array &$form, FormStateInterface $form_state) {
    $credentials_json = $form_state->getValue('google_cloud_credentials');
    $tenant_name = $form_state->getValue('tenant_name');
    
    if (empty($credentials_json)) {
      $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" class="messages messages--error">Please enter your service account credentials first.</div>';
      return $form['google_cloud_settings']['test_result'];
    }

    if (empty($tenant_name)) {
      $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" class="messages messages--error">Please enter the tenant name first. Use the "List Tenants" or "Create Tenant" button to get a tenant.</div>';
      return $form['google_cloud_settings']['test_result'];
    }

    // Temporarily save and test the credentials
    $temp_config = \Drupal::configFactory()->getEditable('job_hunter.settings');
    $old_creds = $temp_config->get('google_cloud_credentials');
    $old_tenant = $temp_config->get('tenant_name');
    $temp_config
      ->set('google_cloud_credentials', $credentials_json)
      ->set('tenant_name', $tenant_name)
      ->save();

    try {
      $service = \Drupal::service('job_hunter.cloud_talent_solution');
      $valid = $service->checkApiCredentials();
      
      if ($valid) {
        $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" class="messages messages--status">✓ Successfully connected to Cloud Talent Solution API!</div>';
      }
      else {
        $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" class="messages messages--error">✗ Connection test failed. Please check your credentials and project configuration.</div>';
      }
    }
    catch (\Exception $e) {
      $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" class="messages messages--error">✗ Error: ' . $e->getMessage() . '</div>';
    }

    // Restore old values
    $temp_config
      ->set('google_cloud_credentials', $old_creds)
      ->set('tenant_name', $old_tenant)
      ->save();

    return $form['google_cloud_settings']['test_result'];
  }

  /**
   * AJAX callback to create Google Cloud Talent Solution tenant.
   */
  public function createGoogleCloudTenant(array &$form, FormStateInterface $form_state) {
    $credentials_json = $form_state->getValue('google_cloud_credentials');
    
    if (empty($credentials_json)) {
      $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" style="margin-top: 15px; padding: 15px; border: 2px solid #d32f2f; border-radius: 4px; background: #ffebee;"><strong style="color: #d32f2f;">✗ ERROR:</strong> Enter credentials first.</div>';
      return $form['google_cloud_settings']['test_result'];
    }

    try {
      $credentials = json_decode($credentials_json, true);
      if (!$credentials || !isset($credentials['project_id'])) {
        throw new \Exception('Invalid JSON credentials format');
      }

      $client = new \Google\Auth\Credentials\ServiceAccountCredentials(
        'https://www.googleapis.com/auth/cloud-platform',
        $credentials
      );
      $token = $client->fetchAuthToken();
      $httpClient = \Drupal::httpClient();

      $project_id = $credentials['project_id'];
      
      // Check if tenant already exists
      try {
        $list_response = $httpClient->get("https://jobs.googleapis.com/v4/projects/{$project_id}/tenants", [
          'headers' => ['Authorization' => 'Bearer ' . $token['access_token']],
        ]);
        $existing_tenants = json_decode($list_response->getBody()->getContents(), true);
        
        if (!empty($existing_tenants['tenants'])) {
          $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" style="margin-top: 15px; padding: 15px; border: 2px solid #f57c00; border-radius: 4px; background: #fff3e0;"><strong style="color: #f57c00; font-size: 16px;">⚠ ALREADY EXISTS</strong><br>Found ' . count($existing_tenants['tenants']) . ' tenant(s). Use "List Tenants" to view.</div>';
          return $form['google_cloud_settings']['test_result'];
        }
      } catch (\Exception $e) {
        // Continue with creation if listing fails
      }
      
      // Create the tenant
      $response = $httpClient->post("https://jobs.googleapis.com/v4/projects/{$project_id}/tenants", [
        'headers' => ['Authorization' => 'Bearer ' . $token['access_token']],
        'json' => [
          'externalId' => 'forseti-jobhunter',
          'usageType' => 'GENERAL_PURPOSE',
        ]
      ]);

      $tenant_data = json_decode($response->getBody()->getContents(), true);
      $tenant_name = $tenant_data['name'] ?? 'unknown';
      
      $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" style="margin-top: 15px; padding: 15px; border: 2px solid #388e3c; border-radius: 4px; background: #e8f5e9;"><strong style="color: #388e3c; font-size: 16px;">✓ CREATED!</strong><br><code>' . htmlspecialchars($tenant_name) . '</code></div>';
    }
    catch (\Exception $e) {
      $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" style="margin-top: 15px; padding: 15px; border: 2px solid #d32f2f; border-radius: 4px; background: #ffebee;"><strong style="color: #d32f2f;">✗ ERROR:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    return $form['google_cloud_settings']['test_result'];
  }

  /**
   * AJAX callback to list Google Cloud Talent Solution tenants.
   */
  public function listGoogleCloudTenants(array &$form, FormStateInterface $form_state) {
    $credentials_json = $form_state->getValue('google_cloud_credentials');
    
    if (empty($credentials_json)) {
      $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" style="margin-top: 15px; padding: 15px; border: 2px solid #d32f2f; border-radius: 4px; background: #ffebee;"><strong style="color: #d32f2f;">✗ ERROR:</strong> Enter credentials first.</div>';
      return $form['google_cloud_settings']['test_result'];
    }

    try {
      $credentials = json_decode($credentials_json, true);
      if (!$credentials || !isset($credentials['project_id'])) {
        throw new \Exception('Invalid JSON credentials format');
      }

      // Create authenticated HTTP client using Google Auth
      $auth = new \Google\Auth\Credentials\ServiceAccountCredentials(
        'https://www.googleapis.com/auth/cloud-platform',
        $credentials
      );
      $token = $auth->fetchAuthToken();
      
      $httpClient = \Drupal::httpClient();
      $project_id = $credentials['project_id'];
      $response = $httpClient->get("https://jobs.googleapis.com/v4/projects/{$project_id}/tenants", [
        'headers' => ['Authorization' => 'Bearer ' . $token['access_token']],
      ]);

      $tenants_data = json_decode($response->getBody()->getContents(), true);
      $tenants = $tenants_data['tenants'] ?? [];
      
      if (empty($tenants)) {
        $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" style="margin-top: 15px; padding: 15px; border: 2px solid #f57c00; border-radius: 4px; background: #fff3e0;"><strong style="color: #f57c00; font-size: 16px;">⚠ NO TENANTS</strong><br>Click "Create Tenant" to create one.</div>';
      } else {
        $output = '<div id="google-cloud-test-result" style="margin-top: 15px; padding: 15px; border: 2px solid #388e3c; border-radius: 4px; background: #e8f5e9;">';
        $output .= '<strong style="color: #388e3c; font-size: 16px;">✓ FOUND ' . count($tenants) . ' TENANT(S)</strong><ul style="margin-top: 10px; list-style: none; padding: 0;">';
        foreach ($tenants as $tenant) {
          $output .= '<li style="margin: 8px 0; padding: 8px; background: white; border-radius: 4px;"><code>' . htmlspecialchars($tenant['name'] ?? 'N/A') . '</code></li>';
        }
        $output .= '</ul></div>';
        $form['google_cloud_settings']['test_result']['#markup'] = $output;
      }
    }
    catch (\Exception $e) {
      $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" style="margin-top: 15px; padding: 15px; border: 2px solid #d32f2f; border-radius: 4px; background: #ffebee;"><strong style="color: #d32f2f;">✗ ERROR:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    return $form['google_cloud_settings']['test_result'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('job_hunter.settings')
      ->set('original_resume_node_id', $form_state->getValue('original_resume_node_id'))
      ->set('enable_automatic_tailoring', $form_state->getValue('enable_automatic_tailoring'))
      ->set('ai_service_region', $form_state->getValue('ai_service_region'))
      ->set('ai_model_id', $form_state->getValue('ai_model_id'))
      ->set('max_tokens', $form_state->getValue('max_tokens'))
      ->set('google_cloud_credentials', $form_state->getValue('google_cloud_credentials'))
      ->set('tenant_name', $form_state->getValue('tenant_name'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
