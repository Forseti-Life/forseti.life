<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\dungeoncrawler_content\Service\ContentSeederService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for seeding module content from packaged JSON files.
 */
class ContentSeederCommands extends DrushCommands {

  /**
   * The content seeder service.
   */
  protected ContentSeederService $seeder;

  /**
   * Constructs a ContentSeederCommands object.
   */
  public function __construct(ContentSeederService $seeder) {
    parent::__construct();
    $this->seeder = $seeder;
  }

  /**
   * Seed all packaged content (templates, encounters, quests, images, etc.).
   *
   * Imports JSON seed files from the module's content/ directory into the
   * database. Skips existing records by default unless --force is used.
   *
   * @param array $options
   *   Command options.
   *
   * @command dungeoncrawler_content:seed
   * @option force Overwrite existing records with seed data.
   * @usage dungeoncrawler_content:seed
   *   Seed all content, skipping existing records.
   * @usage dungeoncrawler_content:seed --force
   *   Re-seed all content, overwriting existing records.
   * @aliases dc:seed
   */
  public function seed(array $options = ['force' => FALSE]): int {
    $force = (bool) $options['force'];

    $this->io()->title('Seeding Dungeon Crawler Content');

    if ($force) {
      $this->io()->caution('Force mode: existing records will be overwritten.');
    }

    $summary = $this->seeder->seedAll($force);

    $this->io()->section('Results');
    $rows = [];
    $total = 0;
    foreach ($summary as $category => $count) {
      $rows[] = [str_replace('_', ' ', ucfirst($category)), $count];
      $total += $count;
    }
    $rows[] = ['TOTAL', $total];

    $this->io()->table(['Category', 'Seeded'], $rows);

    if ($total > 0) {
      $this->io()->success("Seeded {$total} records.");
    }
    else {
      $this->io()->note('No new records to seed (all content already exists). Use --force to overwrite.');
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Re-export current DB content to packaged JSON seed files.
   *
   * Development utility to sync database state back into the module's
   * content/ directory after manual content changes or AI generation.
   *
   * @command dungeoncrawler_content:seed-export
   * @usage dungeoncrawler_content:seed-export
   *   Export current DB templates to content/ JSON files.
   * @aliases dc:seed-export
   */
  public function export(): int {
    $this->io()->title('Exporting Content to JSON Seed Files');

    $summary = $this->seeder->exportAll();

    $rows = [];
    $total = 0;
    foreach ($summary as $category => $count) {
      $rows[] = [str_replace('_', ' ', ucfirst($category)), $count];
      $total += $count;
    }

    $this->io()->table(['Category', 'Exported'], $rows);
    $this->io()->success("Exported {$total} records to content/ directory.");

    return self::EXIT_SUCCESS;
  }

}
