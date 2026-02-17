<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Focused controller surface for tester documentation routes.
 */
class TestingDashboardDocsController extends TestingDashboardController {

	/**
	 * Cache TTL for docs pages.
	 */
	private const DOCS_CACHE_TTL = 600;

	/**
	 * Render tester documentation home page.
	 */
	public function documentationHome(): array {
		$coreLinks = [
			Link::fromTextAndUrl($this->t('Getting Started'), Url::fromRoute('dungeoncrawler_tester.docs_getting_started')),
			Link::fromTextAndUrl($this->t('Test Execution Playbook'), Url::fromRoute('dungeoncrawler_tester.docs_execution_playbook')),
			Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
			Link::fromTextAndUrl($this->t('Automated Testing Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_process_flow')),
			Link::fromTextAndUrl($this->t('SDLC Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_sdlc_process_flow')),
			Link::fromTextAndUrl($this->t('Release Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_release_process_flow')),
		];

		$strategyLinks = [
			Link::fromTextAndUrl($this->t('Legacy: Module README page'), Url::fromRoute('dungeoncrawler_tester.docs_module_readme')),
			Link::fromTextAndUrl($this->t('Legacy: Testing Module README page'), Url::fromRoute('dungeoncrawler_tester.docs_testing_module_readme')),
			Link::fromTextAndUrl($this->t('Legacy: Tests README page'), Url::fromRoute('dungeoncrawler_tester.docs_tests_readme')),
		];

		$liveLinks = [
			Link::fromTextAndUrl($this->t('Testing Dashboard'), Url::fromRoute('dungeoncrawler_tester.dashboard')),
			Link::fromTextAndUrl($this->t('Tester Queue Management'), Url::fromRoute('dungeoncrawler_tester.queue_management')),
			Link::fromTextAndUrl($this->t('Tester Settings'), Url::fromRoute('dungeoncrawler_tester.settings')),
			Link::fromTextAndUrl($this->t('Copilot Issue Automation page'), Url::fromRoute('dungeoncrawler_tester.docs_issue_automation')),
			Link::fromTextAndUrl($this->t('GitHub Issues (testing-related)'), Url::fromUri('https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting')),
		];

		$coreItems = $this->renderLinkItems($coreLinks);
		$strategyItems = $this->renderLinkItems($strategyLinks);
		$liveItems = $this->renderLinkItems($liveLinks);

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-home']],
			'#cache' => [
				'contexts' => ['user.permissions'],
				'max-age' => self::DOCS_CACHE_TTL,
			],
			'row' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['row', 'justify-content-center']],
				'col' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['col-lg-10']],
					'header_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h2',
							'#value' => $this->t('Tester Documentation Home'),
						],
						'intro' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light', 'mb-0']],
							'#value' => $this->t('Central entry point for all Dungeon Crawler tester documentation, testing strategy references, and live workflow links.'),
						],
					],
					'core_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'core_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Standard Testing Documentation'),
						],
						'core_list' => [
							'#theme' => 'item_list',
							'#items' => $coreItems,
						],
					],
					'strategy_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'strategy_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Legacy Route Aliases (Compatibility)'),
						],
						'strategy_list' => [
							'#theme' => 'item_list',
							'#items' => $strategyItems,
						],
					],
					'live_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
						'live_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Live Workflow Links'),
						],
						'live_list' => [
							'#theme' => 'item_list',
							'#items' => $liveItems,
						],
					],
				],
			],
		];
	}

	/**
	 * Render consolidated getting started documentation page.
	 */
	public function docsGettingStarted(): array {
		return $this->buildDocPage(
			$this->t('Getting Started'),
			$this->t('Standard onboarding path for the Dungeon Crawler testing module.'),
			[
				$this->t('Scope: this module owns test harnesses, test suites, and testing dashboard integrations.'),
				$this->t('Entry points: start at /dungeoncrawler/testing for dashboard controls and linked documentation.'),
				$this->t('Prerequisites: tester settings configured with repository/token when failure issue automation is needed.'),
				$this->t('First run: execute focused tests first, then broader suites as confidence increases.'),
			],
			[
				Link::fromTextAndUrl($this->t('Test Execution Playbook'), Url::fromRoute('dungeoncrawler_tester.docs_execution_playbook')),
				Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
			]
		);
	}

	/**
	 * Render consolidated test execution playbook.
	 */
	public function docsExecutionPlaybook(): array {
		return $this->buildDocPage(
			$this->t('Test Execution Playbook'),
			$this->t('Standard structure for planning and running test stages.'),
			[
				$this->t('Plan by stage: pre-commit checks, focused functional coverage, then full confidence runs.'),
				$this->t('Use dashboard command snippets to keep local runs aligned with expected workflows.'),
				$this->t('On failure, capture output and stage context before rerunning to preserve root-cause evidence.'),
				$this->t('Use queue management to pause, resume, and verify stage progression intentionally.'),
			],
			[
				Link::fromTextAndUrl($this->t('Testing Dashboard'), Url::fromRoute('dungeoncrawler_tester.dashboard')),
				Link::fromTextAndUrl($this->t('Tester Queue Management'), Url::fromRoute('dungeoncrawler_tester.queue_management')),
			]
		);
	}

	/**
	 * Render consolidated failure triage and issue workflow page.
	 */
	public function docsFailureTriage(): array {
		return $this->buildDocPage(
			$this->t('Failure Triage and Issue Workflow'),
			$this->t('Standard response flow for failed stages and GitHub issue automation.'),
			[
				$this->t('Triage sequence: identify failing stage, inspect output, validate reproducibility, and scope impact.'),
				$this->t('Issue lifecycle: open issue on failure, attach stage context, and track remediation until stage pass.'),
				$this->t('Assignment behavior: Copilot assignment attempts API identifiers then CLI fallback for compatibility.'),
				$this->t('Operational controls: keep labels consistent for CI failures, testing defects, and program defects.'),
			],
			[
				Link::fromTextAndUrl($this->t('Tester Settings'), Url::fromRoute('dungeoncrawler_tester.settings')),
				Link::fromTextAndUrl($this->t('GitHub Issues (testing-related)'), Url::fromUri('https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting')),
			]
		);
	}

	/**
	 * Legacy route alias for module overview docs.
	 */
	public function docsModuleReadme(): array {
		return $this->docsGettingStarted();
	}

	/**
	 * Legacy route alias for testing module guide docs.
	 */
	public function docsTestingModuleReadme(): array {
		return $this->docsGettingStarted();
	}

	/**
	 * Legacy route alias for tests README docs.
	 */
	public function docsTestsReadme(): array {
		return $this->docsExecutionPlaybook();
	}

	/**
	 * Legacy route alias for strategy design docs.
	 */
	public function docsStrategyDesign(): array {
		return $this->docsExecutionPlaybook();
	}

	/**
	 * Legacy route alias for quick start docs.
	 */
	public function docsQuickStart(): array {
		return $this->docsExecutionPlaybook();
	}

	/**
	 * Legacy route alias for issues directory docs.
	 */
	public function docsIssuesDirectory(): array {
		return $this->docsFailureTriage();
	}

	/**
	 * Legacy route alias for issue automation docs.
	 */
	public function docsIssueAutomation(): array {
		return $this->docsFailureTriage();
	}

	/**
	 * Build a shared docs page layout.
	 */
	private function buildDocPage(string $title, string $intro, array $items, array $relatedLinks = []): array {
		$related = $this->renderLinkItems($relatedLinks);

		$backToHome = Link::fromTextAndUrl(
			$this->t('Back to Documentation Home'),
			Url::fromRoute('dungeoncrawler_tester.documentation_home')
		);
		$related = array_merge($related, $this->renderLinkItems([$backToHome]));

		$itemMarkup = [];
		foreach ($items as $item) {
			$itemMarkup[] = ['#markup' => $item];
		}

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-page']],
			'#cache' => [
				'contexts' => ['user.permissions'],
				'max-age' => self::DOCS_CACHE_TTL,
			],
			'row' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['row', 'justify-content-center']],
				'col' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['col-lg-10']],
					'summary_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h2',
							'#value' => $title,
						],
						'intro' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light', 'mb-0']],
							'#value' => $intro,
						],
					],
					'details_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'items_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Key Points'),
						],
						'items' => [
							'#theme' => 'item_list',
							'#items' => $itemMarkup,
						],
					],
					'related_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
						'related_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Related Links'),
						],
						'related' => [
							'#theme' => 'item_list',
							'#items' => $related,
						],
					],
				],
			],
		];
	}

	/**
	 * Convert links to themed render arrays.
	 */
	private function renderLinkItems(array $links): array {
		$items = [];

		foreach ($links as $link) {
			$render = $link->toRenderable();
			$render['#attributes']['class'][] = 'link-cyan';
			$items[] = $render;
		}

		return $items;
	}

}
