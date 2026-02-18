# Repository Issues Tracker

Internal issue tracker for repository work when GitHub issue creation is unavailable or rate-limited.
This file is also the backup tracker when CLI interface access is denied for creating GitHub issues.

## Status Values
- **Open**: Work is not completed.

## Active Issues

### dungeoncrawler_tester

#### config

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### css

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### root docs/meta

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### js

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### scripts

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### src

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCT-0001 | Testing dashboard flow tracking depends on disabled GitHub context | Open | Copilot | 2026-02-18 | 2026-02-18 | Code issue: TestingDashboardController computes PR/workflow GitHub metrics in buildLifecycleTrackingSection(), but resolveGitHubContext() hard-returns local/Issues.md with token NULL and requestGitHubJsonWithFallback() returns disabled. Result: PR automation cards remain unavailable and lifecycle inference mixes disabled GitHub paths with local-only runtime. |
| DCT-0002 | Issue automation documentation route aliases to generic triage content | Open | Copilot | 2026-02-18 | 2026-02-18 | Code issue: /dungeoncrawler/testing/documentation/issue-automation maps to docsIssueAutomation(), which aliases docsFailureTriage() rather than dedicated issue-automation documentation. Route title/menu imply specialized automation docs that are not implemented. |
#### templates

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### tests

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
### dungeoncrawler_content

#### root docs

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### characters

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### config

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### content

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### css

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### module root files

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### js

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### project root/meta

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### src

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### templates

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### tests

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|


|---|---|---|---|---|---|

## Update Workflow
1. Add new items under **Active Issues** with status **Open**.
2. Keep **Last Updated** current when scope/status changes.
4. Link related commits/PRs/issues in **Notes** when available.
