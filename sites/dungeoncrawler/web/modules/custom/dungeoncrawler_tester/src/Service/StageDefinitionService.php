<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Provides stage definitions for tester runs.
 */
class StageDefinitionService {

  use StringTranslationTrait;

  public function __construct(TranslationInterface $translation, private readonly string $appRoot) {
    $this->stringTranslation = $translation;
  }

  /**
   * Return stage definitions (shared between dashboard and automation).
   */
  public function getDefinitions(): array {
    $root = dirname($this->appRoot);

    return [
      [
        'id' => 'precommit',
        'title' => $this->t('Pre-commit: lint/format + unit'),
        'description' => $this->t('Keep fast checks green before pushing.'),
        'commands' => [
          [
            'label' => $this->t('Unit suite'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--testsuite=unit'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --testsuite=unit',
          ],
        ],
      ],
      [
        'id' => 'precommit-thetest',
        'title' => $this->t('Pre-commit: thetest toggle'),
        'description' => $this->t('Functional check for /thetest page (requires code edit to flip pass/fail).'),
        'commands' => [
          [
            'label' => $this->t('TheTest page functional'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'tests/src/Functional/TheTestPageTest.php'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/TheTestPageTest.php',
          ],
        ],
      ],
      [
        'id' => 'functional-routes',
        'title' => $this->t('Functional routes/controllers'),
        'description' => $this->t('Public, admin, character, campaign, API endpoints.'),
        'commands' => [
          [
            'label' => $this->t('Routes'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'tests/src/Functional/Routes/'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/Routes/',
          ],
          [
            'label' => $this->t('Controllers'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'tests/src/Functional/Controller/'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/Controller/',
          ],
          [
            'label' => $this->t('API group'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--group=api'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --group=api',
          ],
        ],
      ],
      [
        'id' => 'character-workflow',
        'title' => $this->t('Character creation workflow'),
        'description' => $this->t('8-step wizard, validation, persistence.'),
        'commands' => [
          [
            'label' => $this->t('Workflow group'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--group=character-creation'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --group=character-creation',
          ],
        ],
      ],
      [
        'id' => 'entity-campaign',
        'title' => $this->t('Entity/campaign APIs'),
        'description' => $this->t('State validation, access, lifecycle.'),
        'commands' => [
          [
            'label' => $this->t('Entity lifecycle trio'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'tests/src/Functional/CampaignStateAccessTest.php', 'tests/src/Functional/CampaignStateValidationTest.php', 'tests/src/Functional/EntityLifecycleTest.php'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/CampaignStateAccessTest.php tests/src/Functional/CampaignStateValidationTest.php tests/src/Functional/EntityLifecycleTest.php',
          ],
        ],
      ],
      [
        'id' => 'fixtures',
        'title' => $this->t('Cross-check fixtures'),
        'description' => $this->t('PF2e reference + character fixtures up to date.'),
        'commands' => [
          [
            'label' => $this->t('PF2e rules group'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--group=pf2e-rules'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --group=pf2e-rules',
          ],
        ],
      ],
      [
        'id' => 'ci-gate',
        'title' => $this->t('CI gate'),
        'description' => $this->t('All suites green; failures auto-filed.'),
        'commands' => [
          [
            'label' => $this->t('Full suite with coverage'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--coverage-html', 'tests/coverage'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --coverage-html tests/coverage',
          ],
        ],
      ],
      [
        'id' => 'signoff',
        'title' => $this->t('Release sign-off'),
        'description' => $this->t('No open ci-failure/testing-defect blocking issues.'),
        'commands' => [
          [
            'label' => $this->t('Review open defects'),
            'args' => [],
            'cwd' => $root,
            'display' => 'Open GitHub issues (ci-failure, testing-defect)',
            'link' => 'https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Aci-failure+label%3Atesting-defect',
          ],
        ],
      ],
    ];
  }

}
