<?php

namespace Drupal\copilot_agent_tracker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Process\Process;

final class LlmManagementForm extends FormBase {

  public function getFormId(): string {
    return 'copilot_agent_tracker_llm_management_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $local_models = $this->getLocalModelIds();

    $options = [];
    foreach ($local_models as $model_id) {
      $options[$model_id] = $model_id;
    }

    if ($options === []) {
      $options['Qwen/Qwen2.5-1.5B-Instruct'] = 'Qwen/Qwen2.5-1.5B-Instruct (not detected in local HF cache)';
      $this->messenger()->addWarning($this->t('No local Hugging Face model snapshots were detected under your cache path. You can still submit a test and it may download on first run.'));
    }

    $default_model = (string) $form_state->getValue('model');
    if ($default_model === '' || !isset($options[$default_model])) {
      $default_model = (string) array_key_first($options);
    }

    $python_bin = $this->getPythonBin();
    $runner_script = $this->getRunnerScript();
    $cache_root = $this->getRuntimeCacheRoot();

    $form['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Environment and runtime'),
      '#open' => TRUE,
      'items' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Development environment page for local LLM testing.'),
          $this->t('Python runtime: @bin', ['@bin' => $python_bin]),
          $this->t('LLM runner script: @script', ['@script' => $runner_script]),
          $this->t('HF cache root: @cache', ['@cache' => $cache_root]),
          $this->t('Detected local model count: @count', ['@count' => (string) count($local_models)]),
        ],
      ],
    ];

    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Local model'),
      '#options' => $options,
      '#default_value' => $default_model,
      '#required' => TRUE,
      '#description' => $this->t('Models are discovered from the local Hugging Face cache snapshots directory.'),
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#rows' => 6,
      '#required' => TRUE,
      '#default_value' => (string) $form_state->getValue('prompt', ''),
      '#description' => $this->t('Enter a simple test string to generate model output.'),
    ];

    $form['max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Max length'),
      '#default_value' => (int) $form_state->getValue('max_length', 160),
      '#min' => 32,
      '#max' => 512,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run test'),
      '#button_type' => 'primary',
    ];

    $result = $form_state->get('llm_test_result');
    if (is_array($result)) {
      $form['result'] = [
        '#type' => 'details',
        '#title' => $this->t('Model output'),
        '#open' => TRUE,
      ];

      $form['result']['meta'] = [
        '#markup' => '<p><strong>Model:</strong> ' . $this->t('@m', ['@m' => (string) ($result['model'] ?? '')]) . '<br><strong>Runtime:</strong> ' . $this->t('@s sec', ['@s' => (string) ($result['runtime_seconds'] ?? '')]) . '</p>',
      ];

      $form['result']['output'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Generated output'),
        '#default_value' => (string) ($result['output'] ?? ''),
        '#rows' => 14,
        '#attributes' => ['readonly' => 'readonly'],
      ];
    }

    $form['#cache'] = ['max-age' => 0];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $prompt = trim((string) $form_state->getValue('prompt'));
    if ($prompt === '') {
      $form_state->setErrorByName('prompt', $this->t('Prompt cannot be empty.'));
    }

    $max_length = (int) $form_state->getValue('max_length');
    if ($max_length < 32 || $max_length > 512) {
      $form_state->setErrorByName('max_length', $this->t('Max length must be between 32 and 512.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $model = trim((string) $form_state->getValue('model'));
    $prompt = trim((string) $form_state->getValue('prompt'));
    $max_length = (int) $form_state->getValue('max_length');

    $python_bin = $this->getPythonBin();
    $runner_script = $this->getRunnerScript();
    $cache_root = $this->getRuntimeCacheRoot();

    if (!is_file($python_bin)) {
      $this->messenger()->addError($this->t('Python runtime not found: @path', ['@path' => $python_bin]));
      $this->getLogger('copilot_agent_tracker')->error('LLM management test failed: Python runtime not found at @path', [
        '@path' => $python_bin,
      ]);
      return;
    }
    if (!is_file($runner_script)) {
      $this->messenger()->addError($this->t('LLM runner script not found: @path', ['@path' => $runner_script]));
      $this->getLogger('copilot_agent_tracker')->error('LLM management test failed: runner script not found at @path', [
        '@path' => $runner_script,
      ]);
      return;
    }

    $hub_cache = $cache_root . '/hub';
    $transformers_cache = $cache_root . '/transformers';
    $lock_cache = $cache_root . '/locks';

    foreach ([$cache_root, $hub_cache, $transformers_cache, $lock_cache] as $dir) {
      if (!is_dir($dir)) {
        @mkdir($dir, 0775, TRUE);
      }
      if (!is_dir($dir) || !is_writable($dir)) {
        $this->messenger()->addError($this->t('LLM cache directory is not writable: @dir', ['@dir' => $dir]));
        $this->getLogger('copilot_agent_tracker')->error('LLM management test failed: cache directory is not writable @dir', [
          '@dir' => $dir,
        ]);
        return;
      }
    }

    $command = [
      $python_bin,
      $runner_script,
      '--model',
      $model,
      '--prompt',
      $prompt,
      '--max-length',
      (string) $max_length,
    ];

    $process = new Process(
      $command,
      NULL,
      [
        'HF_HOME' => $cache_root,
        'HUGGINGFACE_HUB_CACHE' => $hub_cache,
        'TRANSFORMERS_CACHE' => $transformers_cache,
        'HF_HUB_CACHE' => $hub_cache,
        'HF_HUB_DISABLE_TELEMETRY' => '1',
      ]
    );
    $process->setTimeout(240);

    $start = microtime(TRUE);

    try {
      $process->run();
      $runtime = microtime(TRUE) - $start;

      if (!$process->isSuccessful()) {
        $error = trim((string) $process->getErrorOutput());
        if ($error === '') {
          $error = trim((string) $process->getOutput());
        }
        if ($error === '') {
          $error = 'Unknown LLM process failure.';
        }

        $this->messenger()->addError($this->t('LLM test failed: @msg', ['@msg' => mb_substr($error, 0, 400)]));
        $this->getLogger('copilot_agent_tracker')->error('LLM management process failure: model=@model max_length=@max_length error=@error', [
          '@model' => $model,
          '@max_length' => (string) $max_length,
          '@error' => mb_substr($error, 0, 2000),
        ]);
        return;
      }

      $stdout = (string) $process->getOutput();
      $generated = $this->extractGeneratedText($stdout);
      if ($generated === '') {
        $generated = trim($stdout);
      }

      $form_state->set('llm_test_result', [
        'model' => $model,
        'runtime_seconds' => number_format($runtime, 2),
        'output' => $generated,
      ]);
      $form_state->setRebuild(TRUE);
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('LLM test failed: @msg', ['@msg' => mb_substr($e->getMessage(), 0, 400)]));
      $this->getLogger('copilot_agent_tracker')->error('LLM management exception: model=@model max_length=@max_length error=@error', [
        '@model' => $model,
        '@max_length' => (string) $max_length,
        '@error' => mb_substr($e->getMessage(), 0, 2000),
      ]);
    }
  }

  private function getLocalModelIds(): array {
    $hub_dirs = $this->getHubCacheDirs();
    $models = [];

    foreach ($hub_dirs as $hub_dir) {
      if (!is_dir($hub_dir)) {
        continue;
      }

      $entries = glob($hub_dir . '/models--*', GLOB_ONLYDIR) ?: [];
      foreach ($entries as $entry) {
        $base = basename($entry);
        if (!str_starts_with($base, 'models--')) {
          continue;
        }

        $raw = substr($base, 8);
        $parts = explode('--', $raw, 2);
        if (count($parts) !== 2) {
          continue;
        }

        $org = trim($parts[0]);
        $repo = trim($parts[1]);
        if ($org === '' || $repo === '') {
          continue;
        }

        $snapshots = $entry . '/snapshots';
        $has_snapshot = is_dir($snapshots) && (glob($snapshots . '/*', GLOB_ONLYDIR) ?: []) !== [];
        if (!$has_snapshot) {
          continue;
        }

        $models[] = $org . '/' . $repo;
      }
    }

    sort($models, SORT_NATURAL | SORT_FLAG_CASE);
    return array_values(array_unique($models));
  }

  private function getHubCacheDirs(): array {
    $dirs = [];

    $runtime_root = $this->getRuntimeCacheRoot();
    $dirs[] = rtrim($runtime_root, '/') . '/hub';

    $env_hf_home = trim((string) getenv('HF_HOME'));
    if ($env_hf_home !== '') {
      $dirs[] = rtrim($env_hf_home, '/') . '/hub';
    }

    $home = trim((string) getenv('HOME'));
    if ($home === '') {
      $home = '/home/keithaumiller';
    }
    $dirs[] = rtrim($home, '/') . '/.cache/huggingface/hub';

    $dirs[] = '/home/keithaumiller/.cache/huggingface/hub';

    $dirs = array_filter(array_map('strval', $dirs), static fn (string $d): bool => $d !== '');
    return array_values(array_unique($dirs));
  }

  private function getPythonBin(): string {
    $override = trim((string) getenv('COPILOT_HQ_LLM_PYTHON_BIN'));
    if ($override !== '') {
      return $override;
    }

    return '/home/keithaumiller/copilot-sessions-hq/orchestrator/.venv/bin/python';
  }

  private function getRunnerScript(): string {
    $override = trim((string) getenv('COPILOT_HQ_LLM_RUNNER_SCRIPT'));
    if ($override !== '') {
      return $override;
    }

    return '/home/keithaumiller/copilot-sessions-hq/models/huggingface/main.py';
  }

  private function getRuntimeCacheRoot(): string {
    $override = trim((string) getenv('COPILOT_HQ_LLM_HF_CACHE_DIR'));
    if ($override !== '') {
      return rtrim($override, '/');
    }

    $tmp = rtrim(sys_get_temp_dir(), '/');
    if ($tmp === '') {
      $tmp = '/tmp';
    }

    return $tmp . '/copilot-agent-tracker-hf-cache';
  }

  private function extractGeneratedText(string $output): string {
    $pattern = '/Generated text:\s*-+\s*(.*?)\s*-+\s*$/s';
    if (preg_match($pattern, $output, $matches) === 1) {
      return trim((string) ($matches[1] ?? ''));
    }

    return '';
  }

}
