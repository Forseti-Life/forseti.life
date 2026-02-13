<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for loading and managing game content from JSON schemas.
 *
 * This service handles importing, validating, and updating game content
 * (creatures, items, traps, hazards) from JSON files into the database.
 *
 * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
 *   Section: Service Layer Design > ContentRegistry Service
 */
class ContentRegistry {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Base path to content files.
   *
   * @var string
   */
  protected $contentPath;

  /**
   * Constructs a ContentRegistry object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->loggerFactory = $logger_factory;
    
    // Path: sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/content/
    $this->contentPath = \Drupal::service('extension.list.module')
      ->getPath('dungeoncrawler_content') . '/content';
  }

  /**
   * Load all content from JSON files into database.
   *
   * Should be run during module installation/update.
   *
   * @param string|null $content_type
   *   Load specific type ('creature', 'item', 'trap', 'hazard') or all if NULL.
   *
   * @return int
   *   Number of items loaded.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 133: importContentFromJson method specification
   */
  public function importContentFromJson(?string $content_type = NULL): int {
    $logger = $this->loggerFactory->get('dungeoncrawler_content');
    $count = 0;
    
    $types_to_load = $content_type ? [$content_type] : $this->getContentTypes();
    
    foreach ($types_to_load as $type) {
      $type_dir = $this->contentPath . '/' . $type . 's';
      
      if (!is_dir($type_dir)) {
        $logger->warning('Content directory not found: @dir', ['@dir' => $type_dir]);
        continue;
      }
      
      // Recursively scan for JSON files
      $files = $this->scanForJsonFiles($type_dir);
      
      foreach ($files as $file) {
        try {
          $content_data = $this->loadJsonFile($file);
          
          if (!isset($content_data['content_id']) || !isset($content_data['name'])) {
            $logger->error('Invalid content in @file: missing content_id or name', ['@file' => $file]);
            continue;
          }
          
          // Validate content
          $validation = $this->validateContent($type, $content_data);
          if (!$validation['valid']) {
            $logger->error('Validation failed for @file: @errors', [
              '@file' => $file,
              '@errors' => implode(', ', $validation['errors']),
            ]);
            continue;
          }
          
          // Insert or update in database
          $this->database->merge('dungeoncrawler_content_registry')
            ->keys([
              'content_type' => $type,
              'content_id' => $content_data['content_id'],
            ])
            ->fields([
              'name' => $content_data['name'],
              'level' => $content_data['level'] ?? NULL,
              'rarity' => $content_data['rarity'] ?? NULL,
              'tags' => isset($content_data['tags']) ? json_encode($content_data['tags']) : NULL,
              'schema_data' => json_encode($content_data),
              'source_file' => str_replace($this->contentPath . '/', '', $file),
              'version' => $content_data['version'] ?? '1.0',
              'updated' => time(),
            ])
            ->expression('created', 'COALESCE(created, :time)', [':time' => time()])
            ->execute();
          
          $count++;
          
        } catch (\Exception $e) {
          $logger->error('Error loading @file: @message', [
            '@file' => $file,
            '@message' => $e->getMessage(),
          ]);
        }
      }
    }
    
    $logger->notice('Imported @count content items', ['@count' => $count]);
    return $count;
  }
  
  /**
   * Recursively scan directory for JSON files.
   *
   * @param string $dir
   *   Directory to scan.
   *
   * @return array
   *   Array of file paths.
   */
  protected function scanForJsonFiles(string $dir): array {
    $files = [];
    
    if (!is_dir($dir)) {
      return $files;
    }
    
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
      if ($file->isFile() && $file->getExtension() === 'json') {
        $files[] = $file->getPathname();
      }
    }
    
    return $files;
  }
  
  /**
   * Load and parse JSON file.
   *
   * @param string $file
   *   File path.
   *
   * @return array
   *   Parsed JSON data.
   *
   * @throws \Exception
   *   If file cannot be read or parsed.
   */
  protected function loadJsonFile(string $file): array {
    if (!file_exists($file)) {
      throw new \Exception("File not found: {$file}");
    }
    
    $content = file_get_contents($file);
    if ($content === FALSE) {
      throw new \Exception("Cannot read file: {$file}");
    }
    
    $data = json_decode($content, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Invalid JSON in {$file}: " . json_last_error_msg());
    }
    
    return $data;
  }

  /**
   * Get content by ID and type.
   *
   * @param string $content_type
   *   Content type: 'creature', 'item', 'trap', 'hazard'.
   * @param string $content_id
   *   Unique identifier (e.g., 'goblin_warrior').
   *
   * @return array|null
   *   Full schema data or NULL if not found.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 144: getContent method specification
   */
  public function getContent(string $content_type, string $content_id): ?array {
    $result = $this->database->select('dungeoncrawler_content_registry', 'c')
      ->fields('c', ['schema_data'])
      ->condition('content_type', $content_type)
      ->condition('content_id', $content_id)
      ->execute()
      ->fetchField();
    
    if ($result === FALSE) {
      return NULL;
    }
    
    $data = json_decode($result, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->loggerFactory->get('dungeoncrawler_content')
        ->error('Invalid JSON in database for @type/@id', [
          '@type' => $content_type,
          '@id' => $content_id,
        ]);
      return NULL;
    }
    
    return $data;
  }

  /**
   * Validate content against schema.
   *
   * @param string $content_type
   *   Content type to validate.
   * @param array $content_data
   *   Content data to validate.
   *
   * @return array
   *   Array with 'valid' boolean and optional 'errors' array.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 151: validateContent method specification
   *   Section: Content Validation Schema (lines 1080-1164)
   */
  public function validateContent(string $content_type, array $content_data): array {
    $errors = [];
    
    // Basic validation - check required fields
    if (empty($content_data['content_id'])) {
      $errors[] = 'Missing required field: content_id';
    }
    
    if (empty($content_data['name'])) {
      $errors[] = 'Missing required field: name';
    }
    
    if (empty($content_data['type'])) {
      $errors[] = 'Missing required field: type';
    } elseif ($content_data['type'] !== $content_type) {
      $errors[] = "Type mismatch: expected '{$content_type}', got '{$content_data['type']}'";
    }
    
    // Type-specific validation
    switch ($content_type) {
      case 'creature':
        $errors = array_merge($errors, $this->validateCreature($content_data));
        break;
        
      case 'item':
        $errors = array_merge($errors, $this->validateItem($content_data));
        break;
        
      case 'trap':
        $errors = array_merge($errors, $this->validateTrap($content_data));
        break;
    }
    
    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }
  
  /**
   * Validate creature-specific fields.
   *
   * @param array $data
   *   Creature data.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function validateCreature(array $data): array {
    $errors = [];
    
    // Level validation
    if (!isset($data['level']) || !is_numeric($data['level'])) {
      $errors[] = 'Creature must have a numeric level';
    } elseif ($data['level'] < -1 || $data['level'] > 25) {
      $errors[] = 'Creature level must be between -1 and 25';
    }
    
    // Abilities validation
    if (empty($data['abilities']) || !is_array($data['abilities'])) {
      $errors[] = 'Creature must have abilities array';
    } else {
      $required_abilities = ['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA'];
      foreach ($required_abilities as $ability) {
        if (!isset($data['abilities'][$ability])) {
          $errors[] = "Missing ability: {$ability}";
        }
      }
    }
    
    // Stats validation
    if (empty($data['stats']) || !is_array($data['stats'])) {
      $errors[] = 'Creature must have stats array';
    } else {
      $required_stats = ['ac', 'hp', 'fortitude', 'reflex', 'will'];
      foreach ($required_stats as $stat) {
        if (!isset($data['stats'][$stat])) {
          $errors[] = "Missing stat: {$stat}";
        }
      }
    }
    
    return $errors;
  }
  
  /**
   * Validate item-specific fields.
   *
   * @param array $data
   *   Item data.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function validateItem(array $data): array {
    $errors = [];
    
    if (!isset($data['item_category'])) {
      $errors[] = 'Item must have item_category';
    }
    
    if (!isset($data['level']) || !is_numeric($data['level'])) {
      $errors[] = 'Item must have a numeric level';
    } elseif ($data['level'] < 0 || $data['level'] > 25) {
      $errors[] = 'Item level must be between 0 and 25';
    }
    
    return $errors;
  }
  
  /**
   * Validate trap-specific fields.
   *
   * @param array $data
   *   Trap data.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function validateTrap(array $data): array {
    $errors = [];
    
    if (!isset($data['stealth_dc']) || !is_numeric($data['stealth_dc'])) {
      $errors[] = 'Trap must have numeric stealth_dc';
    }
    
    if (!isset($data['disable_dc']) || !is_numeric($data['disable_dc'])) {
      $errors[] = 'Trap must have numeric disable_dc';
    }
    
    return $errors;
  }

  /**
   * Update content in registry.
   *
   * @param string $content_type
   *   Content type.
   * @param string $content_id
   *   Content identifier.
   * @param array $content_data
   *   Updated content data.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 161: updateContent method specification
   */
  public function updateContent(string $content_type, string $content_id, array $content_data): bool {
    // Validate content first
    $validation = $this->validateContent($content_type, $content_data);
    if (!$validation['valid']) {
      $this->loggerFactory->get('dungeoncrawler_content')
        ->error('Cannot update invalid content @type/@id: @errors', [
          '@type' => $content_type,
          '@id' => $content_id,
          '@errors' => implode(', ', $validation['errors']),
        ]);
      return FALSE;
    }
    
    try {
      $num_updated = $this->database->update('dungeoncrawler_content_registry')
        ->fields([
          'name' => $content_data['name'],
          'level' => $content_data['level'] ?? NULL,
          'rarity' => $content_data['rarity'] ?? NULL,
          'tags' => isset($content_data['tags']) ? json_encode($content_data['tags']) : NULL,
          'schema_data' => json_encode($content_data),
          'version' => $content_data['version'] ?? '1.0',
          'updated' => time(),
        ])
        ->condition('content_type', $content_type)
        ->condition('content_id', $content_id)
        ->execute();
      
      return $num_updated > 0;
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('dungeoncrawler_content')
        ->error('Error updating content @type/@id: @message', [
          '@type' => $content_type,
          '@id' => $content_id,
          '@message' => $e->getMessage(),
        ]);
      return FALSE;
    }
  }

  /**
   * Delete content from registry.
   *
   * @param string $content_type
   *   Content type.
   * @param string $content_id
   *   Content identifier.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function deleteContent(string $content_type, string $content_id): bool {
    try {
      $num_deleted = $this->database->delete('dungeoncrawler_content_registry')
        ->condition('content_type', $content_type)
        ->condition('content_id', $content_id)
        ->execute();
      
      return $num_deleted > 0;
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('dungeoncrawler_content')
        ->error('Error deleting content @type/@id: @message', [
          '@type' => $content_type,
          '@id' => $content_id,
          '@message' => $e->getMessage(),
        ]);
      return FALSE;
    }
  }

  /**
   * Get all content types.
   *
   * @return array
   *   Array of content type names.
   */
  public function getContentTypes(): array {
    return ['creature', 'item', 'trap', 'hazard'];
  }

}
