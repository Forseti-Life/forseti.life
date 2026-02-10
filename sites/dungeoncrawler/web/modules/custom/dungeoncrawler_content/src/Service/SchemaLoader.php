<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Service for loading and managing character creation schemas.
 */
class SchemaLoader {

  /**
   * Base path to schema files.
   */
  private string $schemaPath;

  public function __construct() {
    $this->schemaPath = \Drupal::service('extension.list.module')
      ->getPath('dungeoncrawler_content') . '/config/schemas';
  }

  /**
   * Load schema for a specific step.
   *
   * @param int $step
   *   The step number (1-8).
   *
   * @return array|null
   *   Decoded schema data or NULL if not found.
   */
  public function loadStepSchema(int $step): ?array {
    $file = "{$this->schemaPath}/character_options_step{$step}.json";
    
    if (!file_exists($file)) {
      \Drupal::logger('dungeoncrawler_content')->error('Schema file not found: @file', ['@file' => $file]);
      return NULL;
    }

    $content = file_get_contents($file);
    $schema = json_decode($content, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      \Drupal::logger('dungeoncrawler_content')->error('Invalid JSON in schema: @error', ['@error' => json_last_error_msg()]);
      return NULL;
    }

    return $schema;
  }

  /**
   * Load the master character schema.
   *
   * @return array|null
   *   Decoded schema data or NULL if not found.
   */
  public function loadCharacterSchema(): ?array {
    $file = "{$this->schemaPath}/character.schema.json";
    
    if (!file_exists($file)) {
      return NULL;
    }

    $content = file_get_contents($file);
    return json_decode($content, TRUE);
  }

  /**
   * Get field configuration for a specific step.
   *
   * @param int $step
   *   The step number.
   *
   * @return array
   *   Field definitions from schema.
   */
  public function getStepFields(int $step): array {
    $schema = $this->loadStepSchema($step);
    return $schema['properties']['fields']['properties'] ?? [];
  }

  /**
   * Get navigation rules for a step.
   *
   * @param int $step
   *   The step number.
   *
   * @return array
   *   Navigation configuration.
   */
  public function getStepNavigation(int $step): array {
    $schema = $this->loadStepSchema($step);
    return $schema['properties']['navigation']['properties'] ?? [];
  }

  /**
   * Get tips for a step.
   *
   * @param int $step
   *   The step number.
   *
   * @return array
   *   Array of tip strings.
   */
  public function getStepTips(int $step): array {
    $schema = $this->loadStepSchema($step);
    return $schema['properties']['tips']['default'] ?? [];
  }

  /**
   * Validate step data against schema.
   *
   * @param int $step
   *   The step number.
   * @param array $data
   *   The data to validate.
   *
   * @return array
   *   Array with 'valid' boolean and optional 'errors' array.
   */
  public function validateStepData(int $step, array $data): array {
    $schema = $this->loadStepSchema($step);
    if (!$schema) {
      return ['valid' => FALSE, 'errors' => ['Schema not found']];
    }

    $errors = [];
    $fields = $schema['properties']['fields']['properties'] ?? [];

    foreach ($fields as $field_name => $field_config) {
      $properties = $field_config['properties'] ?? [];
      $required = $properties['required']['const'] ?? FALSE;

      if ($required && empty($data[$field_name])) {
        $validation = $properties['validation']['properties'] ?? [];
        $error_msg = $validation['error_message']['const'] ?? "Field {$field_name} is required.";
        $errors[] = $error_msg;
      }
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }

}
