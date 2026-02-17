<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Focused controller surface for issue/PR report and automation routes.
 */
class TestingDashboardIssueAutomationController extends TestingDashboardController {

	/**
	 * Date formatter for issue report metadata timestamps.
	 */
	protected DateFormatterInterface $dateFormatter;

	/**
	 * Standard close comment for dead-value PR cleanup.
	 */
	private const DEAD_VALUE_COMMENT = 'Dead value: this PR has no diff from main and no changed files. Closing this PR and associated issue.';

	/**
	 * Standard close comment for bulk no-action cleanup.
	 */
	private const BULK_CLOSE_COMMENT = 'Bulk close from testing issue/PR report: no additional implementation action required.';

	/**
	 * Staleness cutoff (days) for bulk stale-issue cleanup query.
	 */
	private const BULK_STALE_DAYS = 60;

	/**
	 * GitHub API timeout in seconds.
	 */
	private const GITHUB_API_TIMEOUT = 10;

	/**
	 * Build a URL from route name with a safe path fallback.
	 */
	protected function safeRouteUrl(string $routeName, string $fallbackPath): string {
		try {
			return Url::fromRoute($routeName)->toString();
		}
		catch (RouteNotFoundException $exception) {
			$this->logger->warning('Missing route @route while building dashboard URL. Falling back to @path. Error: @message', [
				'@route' => $routeName,
				'@path' => $fallbackPath,
				'@message' => $exception->getMessage(),
			]);
			return Url::fromUserInput($fallbackPath)->toString();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container): static {
		$instance = parent::create($container);
		$instance->dateFormatter = $container->get('date.formatter');
		return $instance;
	}

	/**
	 * Build process and decision logic guidance for issue-pr-report triage.
	 */
	protected function buildIssuePrReportDecisionLogicSection(): array {
		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['issue-pr-report-decision-logic', 'issue-report-item']],
			'title' => [
				'#type' => 'html_tag',
				'#tag' => 'h3',
				'#value' => $this->t('Process & Decision Logic'),
			],
			'summary' => [
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#attributes' => ['class' => ['text-muted-light']],
				'#value' => $this->t('Use this sequence to review open PRs from lowest number upward and make consistent close/keep decisions.'),
			],
			'steps_title' => [
				'#type' => 'html_tag',
				'#tag' => 'h4',
				'#value' => $this->t('Triage Steps'),
			],
			'steps' => [
				'#theme' => 'item_list',
				'#items' => [
					(string) $this->t('Process PRs in ascending number order to keep operational cleanup deterministic.'),
					(string) $this->t('Inspect PR state, draft status, merge state, linked issues, checks, and changed files before mutation.'),
					(string) $this->t('Treat no-file-change PRs as no-op candidates; close PRs with rationale comments and keep/open linked issues for separate issue triage when needed.'),
					(string) $this->t('Use bulk close queries only for review-safe classes (for example dead-value PRs, merged-resolution issues, and explicit non-action labels).'),
					(string) $this->t('After each close action, verify resulting PR/issue state via GitHub API before proceeding to the next item.'),
				],
			],
			'decisions_title' => [
				'#type' => 'html_tag',
				'#tag' => 'h4',
				'#value' => $this->t('Decision Rules'),
			],
			'decisions' => [
				'#theme' => 'item_list',
				'#items' => [
					(string) $this->t('Close PR + linked issue when the PR is clearly superseded and linked issue scope is already resolved by merged code.'),
					(string) $this->t('Close PR only when the PR is a no-op (no file changes) but linked issue still needs independent review.'),
					(string) $this->t('Keep PR open when there is actionable code and unresolved blockers (failing checks, unresolved conflicts, or missing review signal).'),
				],
			],
		];
	}

	/**
	 * Build top-of-page bulk-close query section.
	 */
	protected function buildBulkCloseQuerySection(string $repo, array $issues, array $prs, array $tokenCandidates): array {
		$definitions = $this->buildBulkCloseQueryDefinitions($repo, $issues, $prs, $tokenCandidates);

		$cards = [];
		foreach ($definitions as $definition) {
			$cards[] = [
				'#type' => 'container',
				'#attributes' => ['class' => ['issue-card', 'issue-report-item', 'bulk-query-card']],
				'title' => [
					'#type' => 'html_tag',
					'#tag' => 'h3',
					'#value' => (string) ($definition['title'] ?? ''),
				],
				'summary' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['text-muted-light']],
					'#value' => (string) ($definition['summary'] ?? ''),
				],
				'query_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => (string) $this->t('Query'),
				],
				'query' => [
					'#type' => 'html_tag',
					'#tag' => 'pre',
					'#attributes' => ['class' => ['command-snippet']],
					'#value' => (string) ($definition['query'] ?? ''),
				],
				'impact_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => (string) $this->t('Expected Impact'),
				],
				'impact' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['text-muted-light']],
					'#value' => (string) ($definition['expected_impact'] ?? ''),
				],
				'actions' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['issue-report-actions']],
					'run' => [
						'#type' => 'html_tag',
						'#tag' => 'button',
						'#attributes' => [
							'type' => 'button',
							'class' => ['button', 'button--small', 'dc-bulk-query-run-btn'],
							'data-query-id' => (string) ($definition['id'] ?? ''),
							'data-query-title' => (string) ($definition['title'] ?? ''),
						],
						'#value' => (string) $this->t('Run close query'),
					],
				],
			];
		}

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['bulk-close-queries']],
			'title' => [
				'#type' => 'html_tag',
				'#tag' => 'h2',
				'#value' => $this->t('Bulk Close Queries (No-Action Candidates)'),
			],
			'help' => [
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#attributes' => ['class' => ['text-muted-light']],
				'#value' => $this->t('Run these review-safe queries to bulk close stale/no-action issue and PR candidates. Validate results in GitHub after each run.'),
			],
			'cards' => [
				'#theme' => 'item_list',
				'#items' => $cards,
			],
		];
	}

	/**
	 * Build bulk-close query definitions with live expected impact counts.
	 */
	protected function buildBulkCloseQueryDefinitions(string $repo, array $issues, array $prs, array $tokenCandidates): array {
		$openIssueNumbers = [];
		foreach ($issues as $issue) {
			$issueNumber = (int) ($issue['number'] ?? 0);
			if ($issueNumber > 0) {
				$openIssueNumbers[$issueNumber] = TRUE;
			}
		}

		$deadValueCandidates = $this->collectDeadValuePrCandidates($repo, $prs, $tokenCandidates, $openIssueNumbers);
		$mergedLinkedIssues = $this->collectOpenIssuesReferencedByMergedPrs($repo, $issues, $tokenCandidates);
		$nonActionIssues = $this->collectNonActionOpenIssues($issues);
		$openPrsClosedRefs = $this->collectOpenPrsReferencingOnlyClosedIssues($prs, $openIssueNumbers);
		$staleTestingIssues = $this->collectStaleUnassignedTestingIssues($issues);
		$staleCutoffDate = date('Y-m-d', strtotime('-' . self::BULK_STALE_DAYS . ' days'));

		return [
			[
				'id' => 'dead_value_prs',
				'title' => (string) $this->t('Dead-value PRs (no diff from main)'),
				'summary' => (string) $this->t('Closes open PRs that have zero changed files and zero additions/deletions against main.'),
				'query' => 'is:pr is:open base:main changed-files:0',
				'expected_impact' => (string) $this->t('Will close @count PR(s). Linked open issues referenced in PR text will also be closed when present.', ['@count' => count($deadValueCandidates)]),
			],
			[
				'id' => 'issues_resolved_by_merged_pr',
				'title' => (string) $this->t('Open issues referenced by merged PRs'),
				'summary' => (string) $this->t('Closes open issues that are already referenced by merged pull requests.'),
				'query' => 'is:issue is:open linked:pr + merged PR reference check',
				'expected_impact' => (string) $this->t('Will close @count open issue(s) that appear already resolved by merged code.', ['@count' => count($mergedLinkedIssues)]),
			],
			[
				'id' => 'non_action_labeled_issues',
				'title' => (string) $this->t('Open issues labeled duplicate/invalid/wontfix'),
				'summary' => (string) $this->t('Closes open issues already labeled as non-action outcomes.'),
				'query' => 'is:issue is:open (label:duplicate OR label:invalid OR label:wontfix)',
				'expected_impact' => (string) $this->t('Will close @count issue(s) with non-action resolution labels.', ['@count' => count($nonActionIssues)]),
			],
			[
				'id' => 'open_prs_with_only_closed_issue_refs',
				'title' => (string) $this->t('Open PRs referencing only closed issues'),
				'summary' => (string) $this->t('Closes open PRs whose referenced issue numbers are all already closed.'),
				'query' => 'is:pr is:open "fixes #" + all referenced issues closed',
				'expected_impact' => (string) $this->t('Will close @count PR(s) with only closed issue references.', ['@count' => count($openPrsClosedRefs)]),
			],
			[
				'id' => 'stale_unassigned_testing_issues',
				'title' => (string) $this->t('Stale unassigned testing issues'),
				'summary' => (string) $this->t('Closes stale, unassigned testing-defect operational issues that have no active owner.'),
				'query' => 'is:issue is:open no:assignee updated:<' . $staleCutoffDate . ' (label:testing OR label:testing-defect OR label:ci-failure OR label:program-defect OR label:tester)',
				'expected_impact' => (string) $this->t('Will close @count stale issue(s) with no assignee and testing-defect labels.', ['@count' => count($staleTestingIssues)]),
			],
		];
	}

	/**
	 * Collect dead-value PR candidates and referenced open issues.
	 */
	protected function collectDeadValuePrCandidates(string $repo, array $prs, array $tokenCandidates, array $openIssueNumbers): array {
		$candidates = [];

		foreach ($prs as $pr) {
			$prNumber = (int) ($pr['number'] ?? 0);
			if ($prNumber <= 0) {
				continue;
			}

			$details = $this->fetchPullRequestDetails($repo, $tokenCandidates, $prNumber);
			if (empty($details)) {
				continue;
			}

			$normalized = [
				'base_ref' => (string) ($details['base']['ref'] ?? ''),
				'changed_files' => (int) ($details['changed_files'] ?? 0),
				'additions' => (int) ($details['additions'] ?? 0),
				'deletions' => (int) ($details['deletions'] ?? 0),
			];

			if (!$this->isDeadValuePr($normalized)) {
				continue;
			}

			$issueRefs = [];
			$refs = $this->extractIssueReferencesFromPr([
				'title' => (string) ($details['title'] ?? ''),
				'body' => (string) ($details['body'] ?? ''),
			]);
			foreach ($refs as $issueNumber) {
				if (!empty($openIssueNumbers[$issueNumber])) {
					$issueRefs[$issueNumber] = TRUE;
				}
			}

			$candidates[] = [
				'pr_number' => $prNumber,
				'issue_numbers' => array_values(array_map('intval', array_keys($issueRefs))),
			];
		}

		return $candidates;
	}

	/**
	 * Collect open issue numbers referenced by merged PRs.
	 */
	protected function collectOpenIssuesReferencedByMergedPrs(string $repo, array $issues, array $tokenCandidates): array {
		$openIssueNumbers = [];
		foreach ($issues as $issue) {
			$issueNumber = (int) ($issue['number'] ?? 0);
			if ($issueNumber > 0) {
				$openIssueNumbers[$issueNumber] = TRUE;
			}
		}

		$payload = $this->fetchClosedPullRequestsForReport($repo, $tokenCandidates, FALSE);
		$closedPrs = $payload['items'] ?? [];
		$candidates = [];

		foreach ($closedPrs as $pr) {
			if (empty($pr['merged_at'])) {
				continue;
			}
			$refs = $this->extractIssueReferencesFromPr($pr);
			foreach ($refs as $issueNumber) {
				if (!empty($openIssueNumbers[$issueNumber])) {
					$candidates[$issueNumber] = TRUE;
				}
			}
		}

		return array_values(array_map('intval', array_keys($candidates)));
	}

	/**
	 * Collect open issue numbers already marked duplicate/invalid/wontfix.
	 */
	protected function collectNonActionOpenIssues(array $issues): array {
		$candidates = [];
		$nonActionLabels = ['duplicate', 'invalid', 'wontfix'];

		foreach ($issues as $issue) {
			$issueNumber = (int) ($issue['number'] ?? 0);
			if ($issueNumber <= 0) {
				continue;
			}

			$labels = array_map(static fn(string $label): string => strtolower(trim($label)), (array) ($issue['labels'] ?? []));
			if (!empty(array_intersect($labels, $nonActionLabels))) {
				$candidates[$issueNumber] = TRUE;
			}
		}

		return array_values(array_map('intval', array_keys($candidates)));
	}

	/**
	 * Collect open PR numbers where every referenced issue is already closed.
	 */
	protected function collectOpenPrsReferencingOnlyClosedIssues(array $prs, array $openIssueNumbers): array {
		$candidates = [];

		foreach ($prs as $pr) {
			$prNumber = (int) ($pr['number'] ?? 0);
			if ($prNumber <= 0) {
				continue;
			}

			$refs = $this->extractIssueReferencesFromPr($pr);
			if (empty($refs)) {
				continue;
			}

			$allClosed = TRUE;
			foreach ($refs as $issueNumber) {
				if (!empty($openIssueNumbers[$issueNumber])) {
					$allClosed = FALSE;
					break;
				}
			}

			if ($allClosed) {
				$candidates[$prNumber] = TRUE;
			}
		}

		return array_values(array_map('intval', array_keys($candidates)));
	}

	/**
	 * Collect stale unassigned testing-related open issues.
	 */
	protected function collectStaleUnassignedTestingIssues(array $issues): array {
		$candidates = [];

		foreach ($issues as $issue) {
			$issueNumber = (int) ($issue['number'] ?? 0);
			if ($issueNumber <= 0) {
				continue;
			}

			$assignees = (array) ($issue['assignees'] ?? []);
			$labels = array_map(static fn(string $label): string => strtolower(trim($label)), (array) ($issue['labels'] ?? []));
			$staleDays = (int) ($issue['stale_days'] ?? 0);

			if (!empty($assignees)) {
				continue;
			}
			if ($staleDays < self::BULK_STALE_DAYS) {
				continue;
			}
			if (empty(array_intersect($labels, self::TESTING_ISSUE_LABELS))) {
				continue;
			}

			$candidates[$issueNumber] = TRUE;
		}

		return array_values(array_map('intval', array_keys($candidates)));
	}

	/**
	 * Render open issue/PR report grouped by issue with orphaned PR section.
	 */
	public function issuePrReport(): array {
		$reportData = $this->loadIssuePrReportData(FALSE);
		$repo = (string) ($reportData['repo'] ?? '');
		$tokenCandidates = (array) ($reportData['token_candidates'] ?? []);
		$issues = (array) ($reportData['issues'] ?? []);
		$prs = (array) ($reportData['prs'] ?? []);

		usort($issues, static fn(array $left, array $right): int => ((int) ($left['number'] ?? 0)) <=> ((int) ($right['number'] ?? 0)));
		usort($prs, static fn(array $left, array $right): int => ((int) ($left['number'] ?? 0)) <=> ((int) ($right['number'] ?? 0)));

		$openIssueNumbers = [];
		foreach ($issues as $issue) {
			$openIssueNumbers[(int) ($issue['number'] ?? 0)] = TRUE;
		}

		$openPrByNumber = [];
		foreach ($prs as $pr) {
			$prNumber = (int) ($pr['number'] ?? 0);
			if ($prNumber > 0) {
				$openPrByNumber[$prNumber] = $pr;
			}
		}

		$linkedPrsByIssue = [];
		$strictIssueNumbersByPr = [];

		foreach ($issues as $issue) {
			$issueNumber = (int) ($issue['number'] ?? 0);
			if ($issueNumber <= 0) {
				continue;
			}

			$timelineLinkedPrs = $this->fetchLinkedOpenPrNumbersForIssueFromTimeline($repo, $tokenCandidates, $issueNumber, $openPrByNumber, FALSE);
			foreach ($timelineLinkedPrs as $prNumber) {
				if (isset($openPrByNumber[$prNumber])) {
					$linkedPrsByIssue[$issueNumber][] = $openPrByNumber[$prNumber];
					$strictIssueNumbersByPr[$prNumber][$issueNumber] = TRUE;
				}
			}
		}

		$orphanedPrs = [];
		foreach ($prs as $pr) {
			$prNumber = (int) ($pr['number'] ?? 0);
			$references = [];
			if ($prNumber > 0 && !empty($strictIssueNumbersByPr[$prNumber])) {
				$references = array_values(array_map('intval', array_keys($strictIssueNumbersByPr[$prNumber])));
			}
			else {
				$references = $this->extractIssueReferencesFromPr($pr);
			}

			$linked = [];
			foreach ($references as $issueNumber) {
				if (!empty($openIssueNumbers[$issueNumber])) {
					$linked[$issueNumber] = TRUE;
				}
			}

			if (!empty($linked)) {
				foreach (array_keys($linked) as $issueNumber) {
					if (!$this->isPrAlreadyLinkedToIssue($linkedPrsByIssue[$issueNumber] ?? [], $pr)) {
						$linkedPrsByIssue[$issueNumber][] = $pr;
					}
				}
			}
			else {
				$orphanedPrs[] = $pr;
			}
		}

		$issueItems = [];
		foreach ($issues as $issue) {
			$issueNumber = (int) ($issue['number'] ?? 0);
			if ($issueNumber <= 0) {
				continue;
			}

			$issueUrl = (string) ($issue['html_url'] ?? '');
			$issueTitle = (string) ($issue['title'] ?? '');

			$linkedPrs = $linkedPrsByIssue[$issueNumber] ?? [];
			$linkedPrItems = [];
			$issueConcerns = [];
			$issueNextSteps = [];

			if (empty($linkedPrs)) {
				$issueConcerns[] = (string) $this->t('No linked open PR.');
				$issueNextSteps[] = (string) $this->t('Create or relink a PR and include "Fixes #@issue" in the PR description.', ['@issue' => $issueNumber]);
			}

			if (($issue['stale_days'] ?? 0) >= 14) {
				$issueConcerns[] = (string) $this->t('Issue has not been updated in @days days.', ['@days' => (int) $issue['stale_days']]);
				$issueNextSteps[] = (string) $this->t('Reconfirm owner and post status update.');
			}

			foreach ($linkedPrs as $pr) {
				$prNumber = (int) ($pr['number'] ?? 0);
				$prTitle = (string) ($pr['title'] ?? '');
				$prUrl = (string) ($pr['html_url'] ?? '');

				$blockers = $this->describePrBlockers($pr);
				$nextStep = $this->suggestPrNextStep($pr, $blockers);
				$baseRef = (string) ($pr['base_ref'] ?? '');
				$headRef = (string) ($pr['head_ref'] ?? '');
				$changeSummary = $this->t('@files files, +@add/-@del', [
					'@files' => (int) ($pr['changed_files'] ?? 0),
					'@add' => (int) ($pr['additions'] ?? 0),
					'@del' => (int) ($pr['deletions'] ?? 0),
				]);

				$line = [
					'#type' => 'container',
					'#attributes' => ['class' => ['issue-report-pr-item']],
					'pr' => Link::fromTextAndUrl($this->t('PR #@number: @title', ['@number' => $prNumber, '@title' => $prTitle]), Url::fromUri($prUrl))->toRenderable(),
					'details' => [
						'#type' => 'html_tag',
						'#tag' => 'div',
						'#attributes' => ['class' => ['text-muted-light']],
						'#value' => (string) $this->t('base: @base · head: @head · diff: @diff', ['@base' => $baseRef, '@head' => $headRef, '@diff' => $changeSummary]),
					],
				];

				if (!empty($blockers)) {
					$line['blockers'] = [
						'#type' => 'html_tag',
						'#tag' => 'div',
						'#attributes' => ['class' => ['text-muted-light']],
						'#value' => (string) $this->t('Blockers: @blockers', ['@blockers' => implode('; ', $blockers)]),
					];
					foreach ($blockers as $blocker) {
						$issueConcerns[] = $blocker;
					}
				}

				$line['next'] = [
					'#type' => 'html_tag',
					'#tag' => 'div',
					'#attributes' => ['class' => ['text-muted-light']],
					'#value' => (string) $this->t('Next: @next', ['@next' => $nextStep]),
				];

				if ($this->isDeadValuePr($pr)) {
					$line['dead_close_action'] = [
						'#type' => 'container',
						'#attributes' => ['class' => ['issue-report-actions', 'issue-report-item-actions']],
						'button' => [
							'#type' => 'html_tag',
							'#tag' => 'button',
							'#attributes' => [
								'type' => 'button',
								'class' => ['button', 'button--small', 'dc-dead-close-btn'],
								'data-pr-number' => (string) $prNumber,
								'data-issue-number' => (string) $issueNumber,
							],
							'#value' => (string) $this->t('Close dead PR + issue'),
						],
					];
				}

				$linkedPrItems[] = $line;
			}

			if (empty($issueNextSteps) && !empty($linkedPrs)) {
				$issueNextSteps[] = (string) $this->t('Advance linked PR through review and merge checks.');
			}

			$issueItems[] = [
				'#type' => 'container',
				'#attributes' => ['class' => ['issue-card', 'issue-report-item']],
				'issue' => [
					'#type' => 'html_tag',
					'#tag' => 'h3',
					'link' => Link::fromTextAndUrl($this->t('#@number @title', ['@number' => $issueNumber, '@title' => $issueTitle]), Url::fromUri($issueUrl))->toRenderable(),
				],
				'linked_prs_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => $this->t('Linked open PRs'),
				],
				'linked_prs' => [
					'#theme' => 'item_list',
					'#items' => $linkedPrItems,
					'#empty' => $this->t('No linked open PRs.'),
				],
				'state_blockers_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => $this->t('State / Blockers'),
				],
				'state_blockers' => [
					'#theme' => 'item_list',
					'#items' => !empty($issueConcerns) ? array_values(array_unique($issueConcerns)) : [(string) $this->t('No immediate blockers detected.')],
				],
				'next_steps_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => $this->t('Next Step'),
				],
				'next_steps' => [
					'#theme' => 'item_list',
					'#items' => !empty($issueNextSteps) ? array_values(array_unique($issueNextSteps)) : [(string) $this->t('No action required.')],
				],
			];
		}

		$orphanedItems = [];
		foreach ($orphanedPrs as $pr) {
			$blockers = $this->describePrBlockers($pr);

			$orphanedItems[] = [
				'#type' => 'container',
				'#attributes' => ['class' => ['issue-card', 'issue-report-item']],
				'pr' => [
					'#type' => 'html_tag',
					'#tag' => 'h3',
					'link' => Link::fromTextAndUrl($this->t('PR #@number: @title', [
						'@number' => (int) ($pr['number'] ?? 0),
						'@title' => (string) ($pr['title'] ?? ''),
					]), Url::fromUri((string) ($pr['html_url'] ?? '')))->toRenderable(),
				],
				'base_head' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['text-muted-light']],
					'#value' => (string) $this->t('base: @base · head: @head · diff: @files files, +@add/-@del', [
						'@base' => (string) ($pr['base_ref'] ?? ''),
						'@head' => (string) ($pr['head_ref'] ?? ''),
						'@files' => (int) ($pr['changed_files'] ?? 0),
						'@add' => (int) ($pr['additions'] ?? 0),
						'@del' => (int) ($pr['deletions'] ?? 0),
					]),
				],
				'blockers_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => $this->t('Blockers'),
				],
				'blockers' => [
					'#theme' => 'item_list',
					'#items' => !empty($blockers) ? $blockers : [(string) $this->t('No immediate blockers detected.')],
				],
				'next_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => $this->t('Next Step'),
				],
				'next' => [
					'#theme' => 'item_list',
					'#items' => [(string) $this->suggestPrNextStep($pr, $blockers)],
				],
			];

			if ($this->isDeadValuePr($pr)) {
				$orphanedItems[array_key_last($orphanedItems)]['actions'] = [
					'#type' => 'container',
					'#attributes' => ['class' => ['issue-report-actions', 'issue-report-item-actions']],
					'button' => [
						'#type' => 'html_tag',
						'#tag' => 'button',
						'#attributes' => [
							'type' => 'button',
							'class' => ['button', 'button--small', 'dc-dead-close-btn'],
							'data-pr-number' => (string) ((int) ($pr['number'] ?? 0)),
							'data-issue-number' => '0',
						],
						'#value' => (string) $this->t('Close dead PR'),
					],
				];
			}
		}

		$metaItems = [
			$this->t('Repository: @repo', ['@repo' => $repo]),
			$this->t('Open issues: @count', ['@count' => count($issues)]),
			$this->t('Open PRs: @count', ['@count' => count($prs)]),
			$this->t('Orphaned PRs: @count', ['@count' => count($orphanedPrs)]),
			$this->t('Linking strategy: issue timeline cross-references first, PR text fallback second.'),
			$this->t('Generated: @time', ['@time' => $this->dateFormatter->format(time(), 'short')]),
		];

		if (!empty($issuePayload['error'])) {
			$metaItems[] = $this->t('Issue fetch warning: @msg', ['@msg' => (string) $issuePayload['error']]);
		}
		if (!empty($prPayload['error'])) {
			$metaItems[] = $this->t('PR fetch warning: @msg', ['@msg' => (string) $prPayload['error']]);
		}

		$bulkQuerySection = $this->buildBulkCloseQuerySection($repo, $issues, $prs, $tokenCandidates);

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['tester-issue-pr-report', 'dungeoncrawler-testing-dashboard']],
			'#cache' => [
				'contexts' => ['user.permissions'],
				'max-age' => self::GITHUB_CACHE_TTL,
			],
			'#attached' => [
				'library' => [
					'dungeoncrawler_tester/dashboard',
				],
				'drupalSettings' => [
					'dungeoncrawlerTester' => [
						'csrfToken' => $this->csrfToken->get('rest'),
						'routes' => [
							'deadClose' => $this->safeRouteUrl('dungeoncrawler_tester.dead_value_close', '/dungeoncrawler/testing/issue-pr-report/dead-value-close'),
							'bulkCloseQuery' => $this->safeRouteUrl('dungeoncrawler_tester.bulk_close_query_run', '/dungeoncrawler/testing/issue-pr-report/bulk-close-query-run'),
						],
					],
				],
			],
			'bulk_queries' => $bulkQuerySection,
			'intro' => [
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#attributes' => ['class' => ['text-muted-light']],
				'#value' => $this->t('Open issue-first report with linked PRs, blockers, and next steps. Uses existing GitHub repo issue/pull endpoints already used by dashboard signals.'),
			],
			'decision_logic' => $this->buildIssuePrReportDecisionLogicSection(),
			'meta' => [
				'#theme' => 'item_list',
				'#items' => $metaItems,
			],
			'issues_title' => [
				'#type' => 'html_tag',
				'#tag' => 'h3',
				'#value' => $this->t('Open Issues (with linked PRs)'),
			],
			'issues_list' => [
				'#theme' => 'item_list',
				'#items' => $issueItems,
				'#empty' => $this->t('No open issues found.'),
			],
			'orphaned_title' => [
				'#type' => 'html_tag',
				'#tag' => 'h3',
				'#value' => $this->t('Orphaned Open PRs'),
			],
			'orphaned_help' => [
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#attributes' => ['class' => ['text-muted-light']],
				'#value' => $this->t('Orphaned PRs are open PRs without a detected reference to any currently open issue.'),
			],
			'orphaned_list' => [
				'#theme' => 'item_list',
				'#items' => $orphanedItems,
				'#empty' => $this->t('No orphaned open PRs found.'),
			],
		];
	}

	/**
	 * AJAX: run one bulk-close query and execute close mutations.
	 */
	public function runBulkCloseQueryAjax(Request $request): JsonResponse {
		if (!$this->currentUser()->hasPermission('administer site configuration')) {
			return $this->errorJsonResponse('Access denied', 403);
		}

		$payload = json_decode((string) $request->getContent(), TRUE);
		if (!is_array($payload)) {
			$payload = [];
		}

		$queryId = trim((string) ($payload['query_id'] ?? ''));
		if ($queryId === '') {
			return $this->errorJsonResponse('Missing query id.', 400);
		}

		$reportData = $this->loadIssuePrReportData(FALSE);
		$repo = (string) ($reportData['repo'] ?? '');
		$token = $reportData['token'] ?? NULL;
		$tokenCandidates = (array) ($reportData['token_candidates'] ?? []);
		$issues = (array) ($reportData['issues'] ?? []);
		$prs = (array) ($reportData['prs'] ?? []);

		if (!$token || empty($tokenCandidates)) {
			return $this->errorJsonResponse('GitHub token is not configured.', 400);
		}

		$openIssueNumbers = [];
		foreach ($issues as $issue) {
			$issueNumber = (int) ($issue['number'] ?? 0);
			if ($issueNumber > 0) {
				$openIssueNumbers[$issueNumber] = TRUE;
			}
		}

		$result = [
			'prs_closed' => 0,
			'issues_closed' => 0,
			'errors' => [],
		];

		switch ($queryId) {
			case 'dead_value_prs':
				$candidates = $this->collectDeadValuePrCandidates($repo, $prs, $tokenCandidates, $openIssueNumbers);
				foreach ($candidates as $candidate) {
					$prNumber = (int) ($candidate['pr_number'] ?? 0);
					if ($prNumber <= 0) {
						continue;
					}

					$this->recordCloseOutcome($result, $this->closePullRequestWithComment($repo, $token, $prNumber, self::DEAD_VALUE_COMMENT), 'pr', $prNumber);
						$this->closeIssueNumbersWithComment($result, $repo, $token, (array) ($candidate['issue_numbers'] ?? []), self::DEAD_VALUE_COMMENT);
				}
				break;

			case 'issues_resolved_by_merged_pr':
				$issueNumbers = $this->collectOpenIssuesReferencedByMergedPrs($repo, $issues, $tokenCandidates);
					$this->closeIssueNumbersWithComment($result, $repo, $token, $issueNumbers, self::BULK_CLOSE_COMMENT);
				break;

			case 'non_action_labeled_issues':
				$issueNumbers = $this->collectNonActionOpenIssues($issues);
					$this->closeIssueNumbersWithComment($result, $repo, $token, $issueNumbers, self::BULK_CLOSE_COMMENT);
				break;

			case 'open_prs_with_only_closed_issue_refs':
				$prNumbers = $this->collectOpenPrsReferencingOnlyClosedIssues($prs, $openIssueNumbers);
					$this->closePullRequestNumbersWithComment($result, $repo, $token, $prNumbers, self::BULK_CLOSE_COMMENT);
				break;

			case 'stale_unassigned_testing_issues':
				$issueNumbers = $this->collectStaleUnassignedTestingIssues($issues);
					$this->closeIssueNumbersWithComment($result, $repo, $token, $issueNumbers, self::BULK_CLOSE_COMMENT);
				break;

			default:
				return $this->errorJsonResponse('Unknown bulk query id.', 400);
		}

		$errorCount = count($result['errors']);
		$message = "Bulk query complete. Closed {$result['prs_closed']} PR(s) and {$result['issues_closed']} issue(s).";
		if ($errorCount > 0) {
			$message .= " {$errorCount} item(s) had errors; check logs.";
		}

		return new JsonResponse([
			'success' => TRUE,
			'message' => $message,
			'prs_closed' => $result['prs_closed'],
			'issues_closed' => $result['issues_closed'],
			'errors' => $result['errors'],
		]);
	}

	/**
	 * AJAX: close dead-value PR and optionally linked issue without page reload.
	 */
	public function closeDeadValueAjax(Request $request): JsonResponse {
		if (!$this->currentUser()->hasPermission('administer site configuration')) {
			return $this->errorJsonResponse('Access denied', 403);
		}

		$payload = json_decode((string) $request->getContent(), TRUE);
		if (!is_array($payload)) {
			$payload = [];
		}

		$prNumber = (int) ($payload['pr_number'] ?? 0);
		$issueNumber = (int) ($payload['issue_number'] ?? 0);

		if ($prNumber <= 0) {
			return $this->errorJsonResponse('Missing PR number.', 400);
		}

		$githubContext = $this->loadIssueAutomationContext();
		$repo = (string) ($githubContext['repo'] ?? '');
		$token = $githubContext['token'] ?? NULL;
		if (!$token) {
			return $this->errorJsonResponse('GitHub token is not configured.', 400);
		}

		$prResponse = $this->requestGitHubJson("https://api.github.com/repos/{$repo}/pulls/{$prNumber}", $token);
		if (!empty($prResponse['error']) || !is_array($prResponse['items'])) {
			return $this->errorJsonResponse('Unable to load PR details.', 500);
		}

		$pr = [
			'base_ref' => (string) (($prResponse['items']['base']['ref'] ?? '')),
			'changed_files' => (int) ($prResponse['items']['changed_files'] ?? 0),
			'additions' => (int) ($prResponse['items']['additions'] ?? 0),
			'deletions' => (int) ($prResponse['items']['deletions'] ?? 0),
		];

		if (!$this->isDeadValuePr($pr)) {
			return $this->errorJsonResponse('PR is no longer dead-value; refresh and review.', 409);
		}

		$prClosed = $this->closePullRequestWithComment($repo, $token, $prNumber, self::DEAD_VALUE_COMMENT);

		$issueCommented = TRUE;
		$issueClosed = TRUE;
		if ($issueNumber > 0 && $issueNumber !== $prNumber) {
			$issueClosed = $this->closeIssueWithComment($repo, $token, $issueNumber, self::DEAD_VALUE_COMMENT);
			$issueCommented = $issueClosed;
		}

		if (!$prClosed || !$issueCommented || !$issueClosed) {
			return $this->errorJsonResponse('Close action completed with warnings. Check logs for details.', 500);
		}

		return new JsonResponse([
			'success' => TRUE,
			'message' => $issueNumber > 0
				? "Closed dead-value PR #{$prNumber} and issue #{$issueNumber}."
				: "Closed dead-value PR #{$prNumber}.",
		]);
	}

	/**
	 * Build a standardized JSON error response payload.
	 */
	private function errorJsonResponse(string $message, int $statusCode): JsonResponse {
		return new JsonResponse([
			'success' => FALSE,
			'message' => $message,
		], $statusCode);
	}

	/**
	 * Comment on and close an issue.
	 */
	private function closeIssueWithComment(string $repo, string $token, int $issueNumber, string $comment): bool {
		if ($issueNumber <= 0) {
			return FALSE;
		}

		$commented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/comments", $token, ['body' => $comment]);
		$closed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}", $token, ['state' => 'closed']);

		return $commented && $closed;
	}

	/**
	 * Comment on and close a pull request.
	 */
	private function closePullRequestWithComment(string $repo, string $token, int $prNumber, string $comment): bool {
		if ($prNumber <= 0) {
			return FALSE;
		}

		$commented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$prNumber}/comments", $token, ['body' => $comment]);
		$closed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/pulls/{$prNumber}", $token, ['state' => 'closed']);

		return $commented && $closed;
	}

	/**
	 * Record close operation outcome in bulk-close summary counters.
	 */
	private function recordCloseOutcome(array &$result, bool $success, string $itemType, int $itemNumber): void {
		if ($itemNumber <= 0) {
			return;
		}

		if ($success) {
			if ($itemType === 'pr') {
				$result['prs_closed']++;
			}
			else {
				$result['issues_closed']++;
			}
			return;
		}

		$label = $itemType === 'pr' ? 'PR' : 'Issue';
		$result['errors'][] = "{$label} #{$itemNumber}";
	}

	/**
	 * Close and annotate a list of issue numbers.
	 */
	private function closeIssueNumbersWithComment(array &$result, string $repo, string $token, array $issueNumbers, string $comment): void {
		foreach ($issueNumbers as $issueNumber) {
			$issueNumber = (int) $issueNumber;
			if ($issueNumber <= 0) {
				continue;
			}

			$this->recordCloseOutcome($result, $this->closeIssueWithComment($repo, $token, $issueNumber, $comment), 'issue', $issueNumber);
		}
	}

	/**
	 * Close and annotate a list of pull request numbers.
	 */
	private function closePullRequestNumbersWithComment(array &$result, string $repo, string $token, array $prNumbers, string $comment): void {
		foreach ($prNumbers as $prNumber) {
			$prNumber = (int) $prNumber;
			if ($prNumber <= 0) {
				continue;
			}

			$this->recordCloseOutcome($result, $this->closePullRequestWithComment($repo, $token, $prNumber, $comment), 'pr', $prNumber);
		}
	}

	/**
	 * Fetch full PR details by number.
	 */
	protected function fetchPullRequestDetails(string $repo, array $tokenCandidates, int $prNumber): ?array {
		if ($prNumber <= 0) {
			return NULL;
		}

		$response = $this->requestGitHubJsonWithFallback("https://api.github.com/repos/{$repo}/pulls/{$prNumber}", $tokenCandidates, [], FALSE);
		if (!empty($response['error']) || !is_array($response['items'])) {
			return NULL;
		}

		return $response['items'];
	}

	/**
	 * Extract issue number references from a PR title/body.
	 */
	protected function extractIssueReferencesFromPr(array $pr): array {
		$references = [];
		$text = trim(((string) ($pr['title'] ?? '')) . "\n" . ((string) ($pr['body'] ?? '')));
		if ($text === '') {
			return [];
		}

		preg_match_all('/#(\d+)/', $text, $matches);
		foreach ($matches[1] ?? [] as $value) {
			$number = (int) $value;
			if ($number > 0) {
				$references[$number] = TRUE;
			}
		}

		return array_values(array_map('intval', array_keys($references)));
	}

	/**
	 * Check whether a PR is already linked in an issue group.
	 */
	protected function isPrAlreadyLinkedToIssue(array $linkedPrs, array $candidatePr): bool {
		$candidateNumber = (int) ($candidatePr['number'] ?? 0);
		if ($candidateNumber <= 0) {
			return FALSE;
		}

		foreach ($linkedPrs as $existingPr) {
			if ((int) ($existingPr['number'] ?? 0) === $candidateNumber) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Determine blocking conditions for a PR.
	 */
	protected function describePrBlockers(array $pr): array {
		$blockers = [];

		if (!empty($pr['draft'])) {
			$blockers[] = (string) $this->t('Draft PR');
		}

		$baseRef = (string) ($pr['base_ref'] ?? '');
		if ($baseRef !== '' && $baseRef !== 'main') {
			$blockers[] = (string) $this->t('Base branch is @base (expected main)', ['@base' => $baseRef]);
		}

		$mergeableState = (string) ($pr['mergeable_state'] ?? 'unknown');
		if ($mergeableState !== '' && $mergeableState !== 'unknown' && !in_array($mergeableState, ['clean', 'has_hooks'], TRUE)) {
			$blockers[] = (string) $this->t('Merge state is @state', ['@state' => $mergeableState]);
		}

		return $blockers;
	}

	/**
	 * Suggest next step for PR progression based on blockers.
	 */
	protected function suggestPrNextStep(array $pr, array $blockers): string {
		if (!empty($pr['draft'])) {
			return (string) $this->t('Move PR out of draft when ready for review.');
		}

		$baseRef = (string) ($pr['base_ref'] ?? '');
		if ($baseRef !== '' && $baseRef !== 'main') {
			return (string) $this->t('Retarget or rebase PR onto main before merge queue checks.');
		}

		if (!empty($blockers)) {
			return (string) $this->t('Resolve blockers, rerun checks, and re-evaluate mergeability.');
		}

		return (string) $this->t('Request/complete review and merge when checks are green.');
	}

	/**
	 * Determine if PR has no effective code value compared to main.
	 */
	protected function isDeadValuePr(array $pr): bool {
		$baseRef = (string) ($pr['base_ref'] ?? '');
		$changedFiles = (int) ($pr['changed_files'] ?? 0);
		$additions = (int) ($pr['additions'] ?? 0);
		$deletions = (int) ($pr['deletions'] ?? 0);

		return $baseRef === 'main'
			&& $changedFiles === 0
			&& $additions === 0
			&& $deletions === 0;
	}

	/**
	 * Execute a GitHub mutation request with JSON payload.
	 */
	protected function requestGitHubMutation(string $method, string $url, string $token, array $json): bool {
		$ok = $this->githubClient->mutate($method, $url, $json, $token, self::GITHUB_API_TIMEOUT);
		if (!$ok) {
			$this->logger->error('Dead-value close mutation failed for @url.', [
				'@url' => $url,
			]);
		}
		return $ok;
	}

	/**
	 * Execute a GitHub API JSON request and normalize response shape.
	 */
	protected function requestGitHubJson(string $url, ?string $token, array $extraHeaders = []): array {
		return $this->githubClient->requestJson($url, $token, $extraHeaders, FALSE);
	}

	/**
	 * Load an issue-report cache payload when available.
	 */
	private function getCachedIssueReportArray(string $cacheKey, bool $useCache): ?array {
		if (!$useCache) {
			return NULL;
		}

		$cache = $this->cacheBackend->get($cacheKey);
		if (!$cache || !is_array($cache->data)) {
			return NULL;
		}

		return $cache->data;
	}

	/**
	 * Normalize a GitHub JSON response payload to a list of items.
	 */
	private function extractGitHubItems(array $response): array {
		return is_array($response['items'] ?? NULL) ? $response['items'] : [];
	}

	/**
	 * Load normalized GitHub context for issue automation actions.
	 */
	private function loadIssueAutomationContext(): array {
		$githubContext = $this->resolveGitHubContext();
		return [
			'repo' => (string) ($githubContext['repo'] ?? ''),
			'token' => $githubContext['token'] ?? NULL,
			'token_candidates' => is_array($githubContext['token_candidates'] ?? NULL) ? $githubContext['token_candidates'] : [],
		];
	}

	/**
	 * Load GitHub context and fresh issue/PR report payloads.
	 */
	private function loadIssuePrReportData(bool $useCache = FALSE): array {
		$githubContext = $this->loadIssueAutomationContext();
		$repo = (string) ($githubContext['repo'] ?? '');
		$token = $githubContext['token'] ?? NULL;
		$tokenCandidates = (array) ($githubContext['token_candidates'] ?? []);

		$issuePayload = $this->fetchOpenIssuesForReport($repo, $tokenCandidates, $useCache);
		$prPayload = $this->fetchOpenPullRequestsForReport($repo, $tokenCandidates, $useCache);

		return [
			'repo' => $repo,
			'token' => $token,
			'token_candidates' => $tokenCandidates,
			'issues' => $this->extractGitHubItems($issuePayload),
			'prs' => $this->extractGitHubItems($prPayload),
		];
	}

	/**
	 * Fetch open issues for reporting.
	 */
	protected function fetchOpenIssuesForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
		if (empty($tokenCandidates)) {
			return ['items' => [], 'error' => (string) $this->t('No GitHub token configured.')];
		}

		$cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.open_issues.' . $repo;
		$cached = $this->getCachedIssueReportArray($cacheKey, $useCache);
		if ($cached !== NULL) {
			return $cached;
		}

		$url = "https://api.github.com/repos/{$repo}/issues?state=open&per_page=100";
		$response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [], TRUE);
		if (!empty($response['error'])) {
			return ['items' => [], 'error' => $response['error']];
		}

		$items = [];
		$payload = $this->extractGitHubItems($response);
		foreach ($payload as $issue) {
			if (!is_array($issue) || !empty($issue['pull_request'])) {
				continue;
			}

			$labels = [];
			foreach ((array) ($issue['labels'] ?? []) as $label) {
				if (is_array($label) && !empty($label['name'])) {
					$labels[] = (string) $label['name'];
				}
			}

			$assignees = [];
			foreach ((array) ($issue['assignees'] ?? []) as $assignee) {
				if (is_array($assignee) && !empty($assignee['login'])) {
					$assignees[] = (string) $assignee['login'];
				}
			}

			$updatedAt = (string) ($issue['updated_at'] ?? '');
			$updatedTs = $updatedAt !== '' ? strtotime($updatedAt) : FALSE;
			$staleDays = is_int($updatedTs) ? (int) floor((time() - $updatedTs) / 86400) : 0;

			$items[] = [
				'number' => (int) ($issue['number'] ?? 0),
				'title' => (string) ($issue['title'] ?? ''),
				'html_url' => (string) ($issue['html_url'] ?? ''),
				'labels' => $labels,
				'assignees' => $assignees,
				'updated_at' => $updatedAt,
				'stale_days' => max(0, $staleDays),
			];
		}

		$result = ['items' => $items, 'error' => NULL];
		if ($useCache) {
			$this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
		}
		return $result;
	}

	/**
	 * Fetch open pull requests for reporting.
	 */
	protected function fetchOpenPullRequestsForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
		if (empty($tokenCandidates)) {
			return ['items' => [], 'error' => (string) $this->t('No GitHub token configured.')];
		}

		$cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.open_prs.' . $repo;
		$cached = $this->getCachedIssueReportArray($cacheKey, $useCache);
		if ($cached !== NULL) {
			return $cached;
		}

		$url = "https://api.github.com/repos/{$repo}/pulls?state=open&per_page=100";
		$response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [], TRUE);
		if (!empty($response['error'])) {
			return ['items' => [], 'error' => $response['error']];
		}

		$items = [];
		$payload = $this->extractGitHubItems($response);
		foreach ($payload as $pr) {
			if (!is_array($pr)) {
				continue;
			}

			$items[] = [
				'number' => (int) ($pr['number'] ?? 0),
				'title' => (string) ($pr['title'] ?? ''),
				'html_url' => (string) ($pr['html_url'] ?? ''),
				'draft' => !empty($pr['draft']),
				'base_ref' => (string) ($pr['base']['ref'] ?? ''),
				'head_ref' => (string) ($pr['head']['ref'] ?? ''),
				'mergeable_state' => strtolower((string) ($pr['mergeable_state'] ?? 'unknown')),
				'changed_files' => (int) ($pr['changed_files'] ?? 0),
				'additions' => (int) ($pr['additions'] ?? 0),
				'deletions' => (int) ($pr['deletions'] ?? 0),
				'body' => (string) ($pr['body'] ?? ''),
			];
		}

		$result = ['items' => $items, 'error' => NULL];
		if ($useCache) {
			$this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
		}
		return $result;
	}

	/**
	 * Fetch closed pull requests for merged-reference analysis.
	 */
	protected function fetchClosedPullRequestsForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
		if (empty($tokenCandidates)) {
			return ['items' => [], 'error' => (string) $this->t('No GitHub token configured.')];
		}

		$cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.closed_prs.' . $repo;
		$cached = $this->getCachedIssueReportArray($cacheKey, $useCache);
		if ($cached !== NULL) {
			return $cached;
		}

		$url = "https://api.github.com/repos/{$repo}/pulls?state=closed&per_page=100";
		$response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [], TRUE);
		if (!empty($response['error'])) {
			return ['items' => [], 'error' => $response['error']];
		}

		$items = [];
		$payload = $this->extractGitHubItems($response);
		foreach ($payload as $pr) {
			if (!is_array($pr)) {
				continue;
			}

			$items[] = [
				'number' => (int) ($pr['number'] ?? 0),
				'title' => (string) ($pr['title'] ?? ''),
				'body' => (string) ($pr['body'] ?? ''),
				'merged_at' => (string) ($pr['merged_at'] ?? ''),
			];
		}

		$result = ['items' => $items, 'error' => NULL];
		if ($useCache) {
			$this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
		}

		return $result;
	}

	/**
	 * Fetch linked open PR numbers from an issue timeline.
	 */
	protected function fetchLinkedOpenPrNumbersForIssueFromTimeline(string $repo, array $tokenCandidates, int $issueNumber, array $openPrByNumber, bool $useCache = TRUE): array {
		if (empty($tokenCandidates) || $issueNumber <= 0) {
			return [];
		}

		$cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.issue_timeline_links.' . $repo . '.' . $issueNumber;
		$cached = $this->getCachedIssueReportArray($cacheKey, $useCache);
		if ($cached !== NULL) {
			return $cached;
		}

		$url = "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/timeline?per_page=100";
		$response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [
			'Accept' => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
		], TRUE);

		if (!empty($response['error'])) {
			return [];
		}

		$linkedPrNumbers = [];
		$payload = $this->extractGitHubItems($response);
		foreach ($payload as $event) {
			if (!is_array($event)) {
				continue;
			}

			$eventType = (string) ($event['event'] ?? '');
			if ($eventType !== 'cross-referenced' && $eventType !== 'connected') {
				continue;
			}

			$sourceIssue = $event['source']['issue'] ?? NULL;
			if (!is_array($sourceIssue) || empty($sourceIssue['pull_request'])) {
				continue;
			}

			$prNumber = (int) ($sourceIssue['number'] ?? 0);
			if ($prNumber > 0 && isset($openPrByNumber[$prNumber])) {
				$linkedPrNumbers[$prNumber] = TRUE;
			}
		}

		$result = array_values(array_map('intval', array_keys($linkedPrNumbers)));
		if ($useCache) {
			$this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
		}

		return $result;
	}

}
