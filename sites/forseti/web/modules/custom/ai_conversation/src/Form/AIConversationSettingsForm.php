<?php

namespace Drupal\ai_conversation\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure AI Conversation settings.
 */
class AIConversationSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ai_conversation.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_conversation_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ai_conversation.settings');

    // Show current credential status
    $aws_access_key_config = $config->get('aws_access_key_id');
    $aws_access_key_env = getenv('AWS_ACCESS_KEY_ID');
    $aws_region_config = $config->get('aws_region');
    $aws_region_env = getenv('AWS_DEFAULT_REGION');

    $credential_status = [];
    if (!empty($aws_access_key_config)) {
      $credential_status[] = 'Using configured AWS credentials';
    } elseif (!empty($aws_access_key_env)) {
      $credential_status[] = 'Using AWS_ACCESS_KEY_ID environment variable';
    } else {
      $credential_status[] = 'No AWS credentials found';
    }

    if (!empty($aws_region_config)) {
      $credential_status[] = 'Region from configuration: ' . $aws_region_config;
    } elseif (!empty($aws_region_env)) {
      $credential_status[] = 'Region from AWS_DEFAULT_REGION: ' . $aws_region_env;
    } else {
      $credential_status[] = 'Using default region: us-west-2';
    }

    $form['credential_status'] = [
      '#type' => 'item',
      '#title' => $this->t('Current Status'),
      '#markup' => '<div class="messages messages--status"><ul><li>' . implode('</li><li>', $credential_status) . '</li></ul></div>',
    ];

    $form['aws_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('AWS Bedrock Settings'),
      '#description' => $this->t('Configure your AWS credentials to connect to Bedrock AI services. Leave fields empty to use environment variables (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION).'),
    ];

    $form['aws_settings']['aws_access_key_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AWS Access Key ID'),
      '#default_value' => $config->get('aws_access_key_id'),
      '#description' => $this->t('Your AWS Access Key ID with permissions to use Bedrock. Leave empty to use AWS_ACCESS_KEY_ID environment variable.'),
      '#required' => FALSE,
    ];

    $form['aws_settings']['aws_secret_access_key'] = [
      '#type' => 'password',
      '#title' => $this->t('AWS Secret Access Key'),
      '#default_value' => $config->get('aws_secret_access_key'),
      '#description' => $this->t('Your AWS Secret Access Key. Leave blank to keep current value or use AWS_SECRET_ACCESS_KEY environment variable.'),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => FALSE,
    ];

    $form['aws_settings']['aws_region'] = [
      '#type' => 'select',
      '#title' => $this->t('AWS Region'),
      '#default_value' => $config->get('aws_region') ?: 'us-west-2',
      '#options' => [
        'us-east-1' => 'US East (N. Virginia)',
        'us-west-2' => 'US West (Oregon)',
        'eu-west-1' => 'Europe (Ireland)',
        'ap-southeast-2' => 'Asia Pacific (Sydney)',
      ],
      '#description' => $this->t('The AWS region where Bedrock is available.'),
      '#required' => TRUE,
    ];

    $form['aws_settings']['aws_model'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Model'),
      '#default_value' => $config->get('aws_model') ?: 'anthropic.claude-3-5-sonnet-20240620-v1:0',
      '#options' => [
        'anthropic.claude-3-5-sonnet-20240620-v1:0' => 'Claude 3.5 Sonnet',
        'anthropic.claude-3-haiku-20240307-v1:0' => 'Claude 3 Haiku',
        'anthropic.claude-v2:1' => 'Claude 2.1',
        'anthropic.claude-v2' => 'Claude 2.0',
      ],
      '#description' => $this->t('The AI model to use for conversations.'),
      '#required' => TRUE,
    ];

    $form['aws_settings']['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System Prompt'),
      '#default_value' => $config->get('system_prompt'),
      '#description' => $this->t('The system prompt that defines the AI assistant\'s role, personality, and knowledge context.'),
      '#rows' => 15,
      '#required' => FALSE,
    ];

    $form['conversation_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Conversation Settings'),
    ];

    $form['conversation_settings']['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Tokens (Default)'),
      '#default_value' => $config->get('max_tokens') ?: 50000,
      '#description' => $this->t('Default maximum number of tokens for AI responses. Used when no operation-specific limit is set.'),
      '#min' => 100,
      '#max' => 100000,
      '#required' => TRUE,
    ];

    // Operation-Specific Token Limits
    $form['operation_tokens'] = [
      '#type' => 'details',
      '#title' => $this->t('Operation-Specific Token Limits'),
      '#description' => $this->t('Configure maximum tokens for specific operations. These override the default max_tokens setting for their respective operations.'),
      '#open' => TRUE,
    ];

    $form['operation_tokens']['max_tokens_resume_tailoring'] = [
      '#type' => 'number',
      '#title' => $this->t('Resume Tailoring'),
      '#default_value' => $config->get('max_tokens_resume_tailoring') ?: 30000,
      '#description' => $this->t('Maximum tokens for resume tailoring operations. Needs large output for complete resume JSON.'),
      '#min' => 8000,
      '#max' => 100000,
      '#required' => TRUE,
    ];

    $form['operation_tokens']['max_tokens_resume_parsing'] = [
      '#type' => 'number',
      '#title' => $this->t('Resume Parsing'),
      '#default_value' => $config->get('max_tokens_resume_parsing') ?: 20000,
      '#description' => $this->t('Maximum tokens for parsing and extracting resume data.'),
      '#min' => 5000,
      '#max' => 100000,
      '#required' => TRUE,
    ];

    $form['operation_tokens']['max_tokens_cover_letter'] = [
      '#type' => 'number',
      '#title' => $this->t('Cover Letter Generation'),
      '#default_value' => $config->get('max_tokens_cover_letter') ?: 4000,
      '#description' => $this->t('Maximum tokens for generating cover letters.'),
      '#min' => 1000,
      '#max' => 50000,
      '#required' => TRUE,
    ];

    $form['operation_tokens']['max_tokens_job_parsing'] = [
      '#type' => 'number',
      '#title' => $this->t('Job Parsing'),
      '#default_value' => $config->get('max_tokens_job_parsing') ?: 8000,
      '#description' => $this->t('Maximum tokens for parsing job descriptions and requirements.'),
      '#min' => 2000,
      '#max' => 50000,
      '#required' => TRUE,
    ];

    $form['conversation_settings']['max_recent_messages'] = [
      '#type' => 'number',
      '#title' => $this->t('Recent Messages'),
      '#default_value' => $config->get('max_recent_messages') ?: 20,
      '#description' => $this->t('Number of recent messages to keep in memory.'),
      '#min' => 5,
      '#max' => 50,
      '#required' => TRUE,
    ];

    $form['conversation_settings']['summary_frequency'] = [
      '#type' => 'number',
      '#title' => $this->t('Summary Frequency'),
      '#default_value' => $config->get('summary_frequency') ?: 10,
      '#description' => $this->t('Create a summary every N messages.'),
      '#min' => 5,
      '#max' => 50,
      '#required' => TRUE,
    ];

    $form['conversation_settings']['max_tokens_before_summary'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens Before Summary'),
      '#description' => $this->t('Maximum estimated tokens in conversation context before triggering summary update.'),
      '#default_value' => $config->get('max_tokens_before_summary') ?: 6000,
      '#min' => 2000,
      '#max' => 15000,
      '#required' => TRUE,
    ];

    $form['conversation_settings']['enable_auto_summary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Automatic Summary Updates'),
      '#description' => $this->t('Automatically update conversation summaries when thresholds are reached.'),
      '#default_value' => $config->get('enable_auto_summary') ?? TRUE,
    ];

    // Debug settings
    $form['debug_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Debug Settings'),
      '#open' => FALSE,
    ];

    $form['debug_settings']['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Debug Mode'),
      '#description' => $this->t('Log detailed information about summary generation and token usage.'),
      '#default_value' => $config->get('debug_mode') ?? FALSE,
    ];

    $form['debug_settings']['show_stats'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Conversation Statistics'),
      '#description' => $this->t('Display conversation statistics in the chat interface.'),
      '#default_value' => $config->get('show_stats') ?? TRUE,
    ];

    // Connection test
    $form['connection_test'] = [
      '#type' => 'details',
      '#title' => $this->t('Connection Test'),
      '#open' => FALSE,
    ];

    $form['connection_test']['test_connection'] = [
      '#type' => 'button',
      '#value' => $this->t('Test AWS Bedrock Connection'),
      '#ajax' => [
        'callback' => '::testConnection',
        'wrapper' => 'connection-test-result',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $form['connection_test']['connection_result'] = [
      '#type' => 'markup',
      '#markup' => '<div id="connection-test-result"></div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback for connection test.
   */
  public function testConnection(array &$form, FormStateInterface $form_state) {
    $ai_service = \Drupal::service('ai_conversation.ai_api_service');
    $result = $ai_service->testConnection();

    $class = $result['success'] ? 'messages messages--status' : 'messages messages--error';
    $icon = $result['success'] ? '✅' : '❌';

    $markup = '<div id="connection-test-result" class="' . $class . '">';
    $markup .= '<strong>' . $icon . ' ' . $result['message'] . '</strong>';
    $markup .= '</div>';

    return [
      '#type' => 'markup',
      '#markup' => $markup,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $max_tokens = $form_state->getValue('max_tokens');
    $max_tokens_before_summary = $form_state->getValue('max_tokens_before_summary');

    if ($max_tokens_before_summary <= $max_tokens) {
      $form_state->setErrorByName('max_tokens_before_summary', 
        $this->t('Max tokens before summary must be greater than max tokens for responses.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ai_conversation.settings');
    
    $config->set('aws_access_key_id', $form_state->getValue('aws_access_key_id'));
    
    // Only update secret if a new value was provided
    $secret = $form_state->getValue('aws_secret_access_key');
    if (!empty($secret)) {
      $config->set('aws_secret_access_key', $secret);
    }
    
    $config->set('aws_region', $form_state->getValue('aws_region'))
      ->set('aws_model', $form_state->getValue('aws_model'))
      ->set('system_prompt', $form_state->getValue('system_prompt'))
      ->set('max_tokens', $form_state->getValue('max_tokens'))
      ->set('max_tokens_resume_tailoring', $form_state->getValue('max_tokens_resume_tailoring'))
      ->set('max_tokens_resume_parsing', $form_state->getValue('max_tokens_resume_parsing'))
      ->set('max_tokens_cover_letter', $form_state->getValue('max_tokens_cover_letter'))
      ->set('max_tokens_job_parsing', $form_state->getValue('max_tokens_job_parsing'))
      ->set('max_recent_messages', $form_state->getValue('max_recent_messages'))
      ->set('summary_frequency', $form_state->getValue('summary_frequency'))
      ->set('max_tokens_before_summary', $form_state->getValue('max_tokens_before_summary'))
      ->set('enable_auto_summary', $form_state->getValue('enable_auto_summary'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->set('show_stats', $form_state->getValue('show_stats'))
      ->save();

    parent::submitForm($form, $form_state);

    $this->messenger()->addStatus($this->t('AI Conversation settings have been saved.'));
  }

}