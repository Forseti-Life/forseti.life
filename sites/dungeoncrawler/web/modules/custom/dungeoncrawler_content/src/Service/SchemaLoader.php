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

  /**
   * Get class data by class ID.
   *
   * @param string $classId
   *   Class identifier (e.g., 'fighter', 'wizard', 'rogue').
   *
   * @return array
   *   Class data including hit_points, key_ability, proficiencies.
   *
   * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
   *   Section: "Mock Service Designs" - SchemaLoader Service
   *
   * @see docs/dungeoncrawler/testing/fixtures/schemas/classes_test.json
   *   Example class data structure
   *
   * @see docs/dungeoncrawler/issues/issue-1-character-class-hp-design.md
   *   Original design for class HP lookup
   *
   * TODO: Implement schema loading from step 2 schema for class data
   */
  public function getClassData(string $classId): array {
    // PSEUDOCODE:
    // 1. Load step 2 schema (character_options_step2.json)
    // 2. Extract classes array
    // 3. Find class by ID
    // 4. Validate structure
    // 5. Return class data with hit_points
    
    throw new \Exception('Not yet implemented - see issue-1-character-class-hp-design.md');
  }

  /**
   * Get ancestry data by ancestry ID.
   *
   * @param string $ancestryId
   *   Ancestry identifier (e.g., 'human', 'elf', 'dwarf').
   *
   * @return array
   *   Ancestry data including HP bonus, size, speed, ability boosts.
   *
   * @see docs/dungeoncrawler/testing/fixtures/schemas/ancestries_test.json
   *   Example ancestry data structure
   *
   * TODO: Implement ancestry schema loading from step 1 schema
   */
  public function getAncestryData(string $ancestryId): array {
    // PSEUDOCODE:
    // 1. Load step 1 schema (character_options_step1.json)
    // 2. Extract ancestries array
    // 3. Find ancestry by ID
    // 4. Return ancestry data
    
    throw new \Exception('Not yet implemented - see testing strategy design');
  }

  /**
   * Get background data by background ID.
   *
   * @param string $backgroundId
   *   Background identifier (e.g., 'warrior', 'scholar').
   *
   * @return array
   *   Background data including ability boosts, skill training.
   *
   * @see docs/dungeoncrawler/testing/fixtures/schemas/backgrounds_test.json
   *   Example background data structure
   *
   * TODO: Implement background schema loading
   */
  public function getBackgroundData(string $backgroundId): array {
    // PSEUDOCODE:
    // 1. Load appropriate schema file
    // 2. Find background by ID
    // 3. Return background data
    
    throw new \Exception('Not yet implemented - see testing strategy design');
  }

  /**
   * Validate schema structure.
   *
   * @param array $schema
   *   Schema data to validate.
   * @param string $schemaType
   *   Type of schema (classes, ancestries, backgrounds).
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   *
   * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
   *   Section: "Mock Service Designs" for validation patterns
   *
   * TODO: Implement schema validation based on type
   */
  public function validateSchemaStructure(array $schema, string $schemaType): bool {
    // PSEUDOCODE:
    // 1. Check required fields based on schema type
    // 2. Validate data types
    // 3. Check for required nested structures
    // 4. Return validation result
    
    throw new \Exception('Not yet implemented - see schema validation design');
  }

}
