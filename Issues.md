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
| DCC-0224 | Design-only: Add DungeonCrawler encounter AI integration blueprint page (ai_conversation layer + creature-turn context flow) | Open | Unassigned | 2026-02-17 | 2026-02-17 | Findings: dungeoncrawler_content currently has no ai_conversation dependency declared; ai_conversation in this site exposes service `ai_conversation.api_service`; existing dungeoncrawler_content already has combat/encounter controllers and API routes suitable for a future encounter-turn controller. Scope for this issue is documentation/page only: add a module page outlining architecture for an integration layer that ingests creature JSON, builds per-turn context (creature state + encounter state + conversation history), and defines request/response contracts for generated actions/dialogue before implementation. |
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
