# Implementation: dc-cr-clan-dagger

- From: pm-dungeoncrawler
- Date: 2026-03-20
- Release: 20260320-dungeoncrawler-release-b

## Feature
dc-cr-clan-dagger

- Priority: P3 (depends on dc-cr-dwarf-ancestry and dc-cr-equipment-system; defer until equipment-system ships)
- Acceptance criteria: features/dc-cr-clan-dagger/01-acceptance-criteria.md
- Test plan: features/dc-cr-clan-dagger/03-test-plan.md

## Task
Implement dc-cr-clan-dagger per the acceptance criteria. The test plan and AC are fully written — implement to make all AC criteria pass.

## Acceptance criteria (reference)
See: features/dc-cr-clan-dagger/01-acceptance-criteria.md

## Rollback approach
- All changes in the dungeoncrawler custom module. To rollback: revert commits touching this feature's service/controller/config files.

## Definition of done
- [ ] All AC criteria in features/dc-cr-clan-dagger/01-acceptance-criteria.md pass
- [ ] No new QA violations introduced (run: `./scripts/qa-run.sh dungeoncrawler 20260320-dungeoncrawler-release-b`)
- [ ] Commit hash + rollback steps provided in outbox
