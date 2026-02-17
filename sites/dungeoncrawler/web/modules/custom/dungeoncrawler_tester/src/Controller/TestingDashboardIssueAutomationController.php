<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Focused controller surface for issue/PR report and automation routes.
 */
class TestingDashboardIssueAutomationController extends TestingDashboardController {

	/**
	 * Standard close comment for dead-value PR cleanup.
	 */
	private const DEAD_VALUE_COMMENT = 'Dead value: this PR has no diff from main and no changed files. Closing this PR and associated issue.';

	/**
	 * Standard close comment for bulk no-action cleanup.
	 */
	private const BULK_CLOSE_COMMENT = 'Bulk close from testing issue/PR report: no additional implementation action required.';

	/**
	 * Render open issue/PR report grouped by issue with orphaned PR section.
	 */
	public function issuePrReport(): array {
		$githubContext = $this->resolveGitHubContext();
		$repo = $githubContext['repo'];
		$tokenCandidates = $githubContext['token_candidates'] ?? [];

		$issuePayload = $this->fetchOpenIssuesForReport($repo, $tokenCandidates, FALSE);
		$prPayload = $this->fetchOpenPullRequestsForReport($repo, $tokenCandidates, FALSE);

		$issues = $issuePayload['items'] ?? [];
		$prs = $prPayload['items'] ?? [];

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
			return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
		}

		$payload = json_decode((string) $request->getContent(), TRUE);
		if (!is_array($payload)) {
			$payload = [];
		}

		$queryId = trim((string) ($payload['query_id'] ?? ''));
		if ($queryId === '') {
			return new JsonResponse(['success' => FALSE, 'message' => 'Missing query id.'], 400);
		}

		$githubContext = $this->resolveGitHubContext();
		$repo = $githubContext['repo'];
		$token = $githubContext['token'];
		$tokenCandidates = $githubContext['token_candidates'] ?? [];

		if (!$token || empty($tokenCandidates)) {
			return new JsonResponse(['success' => FALSE, 'message' => 'GitHub token is not configured.'], 400);
		}

		$issuePayload = $this->fetchOpenIssuesForReport($repo, $tokenCandidates, FALSE);
		$prPayload = $this->fetchOpenPullRequestsForReport($repo, $tokenCandidates, FALSE);
		$issues = $issuePayload['items'] ?? [];
		$prs = $prPayload['items'] ?? [];

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

					$prCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$prNumber}/comments", $token, ['body' => self::DEAD_VALUE_COMMENT]);
					$prClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/pulls/{$prNumber}", $token, ['state' => 'closed']);
					if ($prCommented && $prClosed) {
						$result['prs_closed']++;
					}
					else {
						$result['errors'][] = "PR #{$prNumber}";
					}

					foreach ($candidate['issue_numbers'] ?? [] as $issueNumber) {
						$issueNumber = (int) $issueNumber;
						if ($issueNumber <= 0) {
							continue;
						}
						$issueCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/comments", $token, ['body' => self::DEAD_VALUE_COMMENT]);
						$issueClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}", $token, ['state' => 'closed']);
						if ($issueCommented && $issueClosed) {
							$result['issues_closed']++;
						}
						else {
							$result['errors'][] = "Issue #{$issueNumber}";
						}
					}
				}
				break;

			case 'issues_resolved_by_merged_pr':
				$issueNumbers = $this->collectOpenIssuesReferencedByMergedPrs($repo, $issues, $tokenCandidates);
				foreach ($issueNumbers as $issueNumber) {
					$issueCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/comments", $token, ['body' => self::BULK_CLOSE_COMMENT]);
					$issueClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}", $token, ['state' => 'closed']);
					if ($issueCommented && $issueClosed) {
						$result['issues_closed']++;
					}
					else {
						$result['errors'][] = "Issue #{$issueNumber}";
					}
				}
				break;

			case 'non_action_labeled_issues':
				$issueNumbers = $this->collectNonActionOpenIssues($issues);
				foreach ($issueNumbers as $issueNumber) {
					$issueCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/comments", $token, ['body' => self::BULK_CLOSE_COMMENT]);
					$issueClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}", $token, ['state' => 'closed']);
					if ($issueCommented && $issueClosed) {
						$result['issues_closed']++;
					}
					else {
						$result['errors'][] = "Issue #{$issueNumber}";
					}
				}
				break;

			case 'open_prs_with_only_closed_issue_refs':
				$prNumbers = $this->collectOpenPrsReferencingOnlyClosedIssues($prs, $openIssueNumbers);
				foreach ($prNumbers as $prNumber) {
					$prCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$prNumber}/comments", $token, ['body' => self::BULK_CLOSE_COMMENT]);
					$prClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/pulls/{$prNumber}", $token, ['state' => 'closed']);
					if ($prCommented && $prClosed) {
						$result['prs_closed']++;
					}
					else {
						$result['errors'][] = "PR #{$prNumber}";
					}
				}
				break;

			case 'stale_unassigned_testing_issues':
				$issueNumbers = $this->collectStaleUnassignedTestingIssues($issues);
				foreach ($issueNumbers as $issueNumber) {
					$issueCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/comments", $token, ['body' => self::BULK_CLOSE_COMMENT]);
					$issueClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}", $token, ['state' => 'closed']);
					if ($issueCommented && $issueClosed) {
						$result['issues_closed']++;
					}
					else {
						$result['errors'][] = "Issue #{$issueNumber}";
					}
				}
				break;

			default:
				return new JsonResponse(['success' => FALSE, 'message' => 'Unknown bulk query id.'], 400);
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
			return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
		}

		$payload = json_decode((string) $request->getContent(), TRUE);
		if (!is_array($payload)) {
			$payload = [];
		}

		$prNumber = (int) ($payload['pr_number'] ?? 0);
		$issueNumber = (int) ($payload['issue_number'] ?? 0);

		if ($prNumber <= 0) {
			return new JsonResponse(['success' => FALSE, 'message' => 'Missing PR number.'], 400);
		}

		$githubContext = $this->resolveGitHubContext();
		$repo = $githubContext['repo'];
		$token = $githubContext['token'];
		if (!$token) {
			return new JsonResponse(['success' => FALSE, 'message' => 'GitHub token is not configured.'], 400);
		}

		$prResponse = $this->requestGitHubJson("https://api.github.com/repos/{$repo}/pulls/{$prNumber}", $token);
		if (!empty($prResponse['error']) || !is_array($prResponse['items'])) {
			return new JsonResponse(['success' => FALSE, 'message' => 'Unable to load PR details.'], 500);
		}

		$pr = [
			'base_ref' => (string) (($prResponse['items']['base']['ref'] ?? '')),
			'changed_files' => (int) ($prResponse['items']['changed_files'] ?? 0),
			'additions' => (int) ($prResponse['items']['additions'] ?? 0),
			'deletions' => (int) ($prResponse['items']['deletions'] ?? 0),
		];

		if (!$this->isDeadValuePr($pr)) {
			return new JsonResponse(['success' => FALSE, 'message' => 'PR is no longer dead-value; refresh and review.'], 409);
		}

		$base = "https://api.github.com/repos/{$repo}";

		$prCommented = $this->requestGitHubMutation('POST', $base . "/issues/{$prNumber}/comments", $token, ['body' => self::DEAD_VALUE_COMMENT]);
		$prClosed = $this->requestGitHubMutation('PATCH', $base . "/pulls/{$prNumber}", $token, ['state' => 'closed']);

		$issueCommented = TRUE;
		$issueClosed = TRUE;
		if ($issueNumber > 0 && $issueNumber !== $prNumber) {
			$issueCommented = $this->requestGitHubMutation('POST', $base . "/issues/{$issueNumber}/comments", $token, ['body' => self::DEAD_VALUE_COMMENT]);
			$issueClosed = $this->requestGitHubMutation('PATCH', $base . "/issues/{$issueNumber}", $token, ['state' => 'closed']);
		}

		if (!$prCommented || !$prClosed || !$issueCommented || !$issueClosed) {
			return new JsonResponse(['success' => FALSE, 'message' => 'Close action completed with warnings. Check logs for details.'], 500);
		}

		return new JsonResponse([
			'success' => TRUE,
			'message' => $issueNumber > 0
				? "Closed dead-value PR #{$prNumber} and issue #{$issueNumber}."
				: "Closed dead-value PR #{$prNumber}.",
		]);
	}

}
