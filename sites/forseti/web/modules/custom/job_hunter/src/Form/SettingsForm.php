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
      '#description' => $this->t('Paste the contents of your Google Cloud service account JSON key file here. Get your key from the <a href="@url" target="_blank">Google Cloud Console</a>.', [
        '@url' => 'https://console.cloud.google.com/talent-solution/connect-service-accounts?project=forseti-483518',
      ]),
      '#default_value' => $config->get('google_cloud_credentials') ?? '',
      '#rows' => 5,
      '#required' => FALSE,
    ];

    $form['google_cloud_settings']['test_credentials'] = [
      '#type' => 'button',
      '#value' => $this->t('Test API Connection'),
      '#ajax' => [
        'callback' => '::testGoogleCloudCredentials',
        'wrapper' => 'google-cloud-test-result',
      ],
    ];

    $form['google_cloud_settings']['test_result'] = [
      '#type' => 'markup',
      '#markup' => '<div id="google-cloud-test-result"></div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback to test Google Cloud credentials.
   */
  public function testGoogleCloudCredentials(array &$form, FormStateInterface $form_state) {
    $credentials_json = $form_state->getValue('google_cloud_credentials');
    
    if (empty($credentials_json)) {
      $form['google_cloud_settings']['test_result']['#markup'] = '<div id="google-cloud-test-result" class="messages messages--error">Please enter your service account credentials first.</div>';
      return $form['google_cloud_settings']['test_result'];
    }

    // Temporarily save and test the credentials
    $temp_config = \Drupal::configFactory()->getEditable('job_hunter.settings');
    $old_creds = $temp_config->get('google_cloud_credentials');
    $temp_config->set('google_cloud_credentials', $credentials_json)->save();

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

    // Restore old credentials
    $temp_config->set('google_cloud_credentials', $old_creds)->save();

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
      ->save();

    parent::submitForm($form, $form_state);
  }

}
