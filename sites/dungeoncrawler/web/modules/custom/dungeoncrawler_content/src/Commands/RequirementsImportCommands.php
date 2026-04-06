<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\Core\Database\Connection;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for importing PF2E requirements from reference markdown files.
 */
class RequirementsImportCommands extends DrushCommands {

  /**
   * Maps filename prefix to [book_id, book_title].
   */
  const BOOK_MAP = [
    'core'    => ['core', 'PF2E Core Rulebook'],
    'chapter' => ['core', 'PF2E Core Rulebook'],
    'apg'     => ['apg', "Advanced Player's Guide"],
    'gmg'     => ['gmg', 'Gamemastery Guide'],
    'gng'     => ['gng', 'Guns and Gears'],
    'som'     => ['som', 'Secrets of Magic'],
    'gam'     => ['gam', 'Gods and Magic'],
    'b1'      => ['b1', 'Bestiary 1'],
    'b2'      => ['b2', 'Bestiary 2'],
    'b3'      => ['b3', 'Bestiary 3'],
  ];

  /**
   * Maps chapter key slug to human-readable chapter title.
   * Derived from EXTRACTION_TRACKER.md.
   */
  const CHAPTER_TITLE_MAP = [
    // Core Rulebook
    'ch01' => 'Chapter 1: Introduction',
    'ch02' => 'Chapter 2: Ancestries & Backgrounds',
    'ch03' => 'Chapter 3: Classes',
    'ch04' => 'Chapter 4: Skills',
    'ch05' => 'Chapter 5: Feats',
    'ch06' => 'Chapter 6: Equipment',
    'ch07' => 'Chapter 7: Spells',
    'ch09' => 'Chapter 9: Playing the Game',
    'ch10' => 'Chapter 10: Game Mastering',
    'ch11' => 'Chapter 11: Crafting & Treasure',
    // APG
    'apg-ch01' => 'Chapter 1: Ancestries & Backgrounds',
    'apg-ch02' => 'Chapter 2: Classes',
    'apg-ch03' => 'Chapter 3: Archetypes',
    'apg-ch04' => 'Chapter 4: Feats',
    'apg-ch05' => 'Chapter 5: Spells',
    'apg-ch06' => 'Chapter 6: Items',
    // GMG
    'gmg-ch01' => 'Chapter 1: Gamemastery Basics',
    'gmg-ch02' => 'Chapter 2: Tools',
    'gmg-ch03' => 'Chapter 3: Subsystems',
    'gmg-ch04' => 'Chapter 4: Variant Rules',
    // Guns and Gears
    'gng-ch01' => 'Chapter 1: Gears Characters',
    'gng-ch02' => 'Chapter 2: Gears Equipment',
    'gng-ch03' => 'Chapter 3: Guns Characters',
    'gng-ch04' => 'Chapter 4: Guns Equipment',
    'gng-ch05' => 'Chapter 5: The Rotating Gear',
    // Secrets of Magic
    'som-ch01' => 'Chapter 1: Essentials of Magic',
    'som-ch02' => 'Chapter 2: Classes',
    'som-ch03' => 'Chapter 3: Spells',
    'som-ch04' => 'Chapter 4: Magic Items',
    'som-ch05' => 'Chapter 5: Book of Unlimited Magic',
    // Gods and Magic (sections)
    'gam-s01' => 'Overview',
    'gam-s02' => 'Gods of the Inner Sea',
    'gam-s03' => 'Demigods and Other Divinities',
    'gam-s04' => 'Philosophies and Spirituality',
    'gam-s05' => 'Character Options',
    'gam-s06' => 'Appendix',
    // Bestiary 1
    'b1-s01' => 'Introduction',
    'b1-s02' => 'Monsters A–Z',
    'b1-s03' => 'Appendix',
    // Bestiary 2
    'b2-s01' => 'Monsters A–Z',
    'b2-s02' => 'Appendix',
    // Bestiary 3
    'b3-s01' => 'Introduction',
    'b3-s02' => 'Monsters A–Z',
    'b3-s03' => 'Appendix',
  ];

  protected Connection $database;

  public function __construct(Connection $database) {
    parent::__construct();
    $this->database = $database;
  }

  /**
   * Import PF2E requirements from reference markdown files into dc_requirements.
   *
   * @param string $refs_dir
   *   Path to the references directory. Defaults to the standard location.
   *
   * @command dungeoncrawler:import-requirements
   * @aliases dc-import-reqs
   * @option force Re-import all records, overwriting existing data.
   * @usage dungeoncrawler:import-requirements
   *   Import requirements using default references path.
   * @usage dungeoncrawler:import-requirements /path/to/references
   *   Import from a custom path.
   */
  public function importRequirements(
    string $refs_dir = '/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references',
    array $options = ['force' => FALSE]
  ): void {
    if (!is_dir($refs_dir)) {
      $this->logger()->error("References directory not found: {$refs_dir}");
      return;
    }

    $files = glob($refs_dir . '/*.md');
    if (empty($files)) {
      $this->logger()->error("No .md files found in: {$refs_dir}");
      return;
    }

    sort($files);
    $inserted = 0;
    $skipped = 0;
    $updated = 0;

    foreach ($files as $filepath) {
      $basename = basename($filepath);
      [$book_id, $book_title, $chapter_key, $chapter_title] = $this->resolveBookChapter($basename);

      if ($book_id === NULL) {
        $this->logger()->warning("Could not resolve book for file: {$basename}, skipping.");
        continue;
      }

      $requirements = $this->parseRequirements($filepath);

      foreach ($requirements as $req) {
        $hash = hash('sha256', $basename . '::' . $req['req_text']);
        $exists = $this->database->select('dc_requirements', 'r')
          ->fields('r', ['id', 'status'])
          ->condition('req_hash', $hash)
          ->execute()
          ->fetchAssoc();

        if ($exists && !$options['force']) {
          $skipped++;
          continue;
        }

        $record = [
          'book_id'        => $book_id,
          'book_title'     => $book_title,
          'chapter_key'    => $chapter_key,
          'chapter_title'  => $chapter_title,
          'section'        => $req['section'],
          'paragraph_title'=> $req['paragraph_title'],
          'req_text'       => $req['req_text'],
          'req_hash'       => $hash,
          'status'         => $exists['status'] ?? 'pending',
          'source_file'    => $basename,
          'updated_at'     => $exists ? (int) $this->database->select('dc_requirements', 'r')->fields('r', ['updated_at'])->condition('req_hash', $hash)->execute()->fetchField() : 0,
          'updated_by'     => 0,
        ];

        if ($exists) {
          $this->database->update('dc_requirements')
            ->fields($record)
            ->condition('req_hash', $hash)
            ->execute();
          $updated++;
        }
        else {
          $this->database->insert('dc_requirements')->fields($record)->execute();
          $inserted++;
        }
      }

      $this->logger()->info("Processed {$basename}: " . count($requirements) . ' requirements.');
    }

    $this->logger()->success("Import complete. Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}.");
  }

  /**
   * Resolve book metadata from a reference markdown filename.
   *
   * @return array [book_id, book_title, chapter_key, chapter_title] or [NULL,...]
   */
  private function resolveBookChapter(string $basename): array {
    // Strip .md extension.
    $name = preg_replace('/\.md$/', '', $basename);

    // Special case: chapter-01-introduction.md → core ch01
    if (preg_match('/^chapter-(\d+)-/', $name, $m)) {
      $num = str_pad($m[1], 2, '0', STR_PAD_LEFT);
      $key = 'ch' . $num;
      return [
        'core',
        'PF2E Core Rulebook',
        $key,
        self::CHAPTER_TITLE_MAP[$key] ?? ucwords(str_replace('-', ' ', $name)),
      ];
    }

    // Standard pattern: {prefix}-{chNN|sNN}-{slug}
    // prefix may be multi-segment for APG etc.
    // Match book prefix (first segment before - followed by ch/s + digits).
    if (!preg_match('/^([a-z0-9]+)-((?:ch|s)\d+)/', $name, $m)) {
      return [NULL, NULL, NULL, NULL];
    }

    $prefix = $m[1];
    $chapter_segment = $m[2]; // e.g. ch01, s02

    if (!isset(self::BOOK_MAP[$prefix])) {
      return [NULL, NULL, NULL, NULL];
    }

    [$book_id, $book_title] = self::BOOK_MAP[$prefix];

    // Build a scoped chapter key for the title lookup.
    // For core, key is just chNN. For others, key is prefix-chNN.
    $scoped_key = ($prefix === 'core') ? $chapter_segment : "{$prefix}-{$chapter_segment}";
    $chapter_title = self::CHAPTER_TITLE_MAP[$scoped_key]
      ?? self::CHAPTER_TITLE_MAP[$chapter_segment]
      ?? ucwords(str_replace('-', ' ', $chapter_segment));

    return [$book_id, $book_title, $chapter_segment, $chapter_title];
  }

  /**
   * Parse requirements from a reference markdown file.
   *
   * @return array[]
   *   Array of ['section', 'paragraph_title', 'req_text'].
   */
  private function parseRequirements(string $filepath): array {
    $lines = file($filepath, FILE_IGNORE_NEW_LINES);
    $requirements = [];
    $current_section = '';
    $current_paragraph = '';

    foreach ($lines as $line) {
      // Section heading: ## SECTION: <name>
      if (preg_match('/^##\s+SECTION:\s+(.+)/', $line, $m)) {
        $current_section = trim($m[1]);
        $current_paragraph = '';
        continue;
      }

      // Also handle plain ## <heading> (some files use this instead of SECTION:)
      if (preg_match('/^##\s+(?!SECTION:)(.+)/', $line, $m)) {
        $current_section = trim($m[1]);
        $current_paragraph = '';
        continue;
      }

      // Paragraph heading: ### Paragraph — <name>
      if (preg_match('/^###\s+Paragraph\s+[—–-]+\s*(.+)/', $line, $m)) {
        $current_paragraph = trim($m[1]);
        continue;
      }

      // Also handle plain ### <heading>
      if (preg_match('/^###\s+(.+)/', $line, $m)) {
        $current_paragraph = trim($m[1]);
        continue;
      }

      // Requirement line: - REQ: <text>
      if (preg_match('/^-\s+REQ:\s+(.+)/', $line, $m)) {
        $requirements[] = [
          'section'        => $current_section ?: 'General',
          'paragraph_title'=> $current_paragraph,
          'req_text'       => trim($m[1]),
        ];
        continue;
      }

      // Some files use "- REQ " without colon or with different spacing
      if (preg_match('/^[-*]\s+REQ\b[:\s]+(.+)/', $line, $m)) {
        $requirements[] = [
          'section'        => $current_section ?: 'General',
          'paragraph_title'=> $current_paragraph,
          'req_text'       => trim($m[1]),
        ];
      }
    }

    return $requirements;
  }

}
