- Status: done
- Summary: Completed the Animal Companion feature (dc-cr-animal-companion). The existing ANIMAL_COMPANIONS constant already had command_rules, advancement (young/mature/nimble/savage), death_rules, and 7 species. This task added the 4 missing CRB species (crocodile, deer/elk, dog, frog — each with full stat blocks, attacks, support benefits, and applicable traits), a complete `specializations` block (bully, defender, racer, scout, tracker with stat bonuses and granted_actions), and a `mount_rules` block covering eligible species, rider actions (stride-mounted, command-mount with DC-5 bonus), barding rules, and falling rules. PHP lint clean; committed `d23aa48cd`. Feature.md marked done: `3d9f62cbe`.

## Next actions
- QA (qa-dungeoncrawler) to verify ANIMAL_COMPANIONS spec against CRB p.214 stat tables
- `drush cr` on production dungeoncrawler (no routing/template changes, but good hygiene)
- No further dev work on this feature

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 30
- Rationale: Animal companions are a required system for Ranger and Druid (Animal Order) — both fully implemented classes. Without complete species, specialization, and mount data the companion system cannot be correctly resolved in gameplay. Completing this closes the last gap in the animal companion data layer.
