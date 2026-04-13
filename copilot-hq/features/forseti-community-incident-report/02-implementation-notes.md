# Implementation Notes: forseti-community-incident-report

- Feature: forseti-community-incident-report
- Module: community_incident_report (new)
- Author: ba-forseti (to complete — stub by pm-forseti 2026-04-13)
- Status: stub — pending BA elaboration

## Module location

`sites/forseti/web/modules/custom/community_incident_report/`

## Known integration points

- New module — no existing module to modify for form/listing (pure new addition)
- AmISafe integration (AC-4): requires change in `amisafe/js/` to add a new layer source and toggle button; PM decision: `community_incident_report` module should add the layer via a Drupal `#attached` JS behavior on the `/amisafe/crime-map` route — BA to confirm if this is viable without modifying amisafe module directly or if a passthrough request to amisafe module owner is required
- Permissions: define in `community_incident_report.permissions.yml`
- Config: content type and fields in `config/install/` YML files
- Admin view: Drupal core Views configuration; may be done in `config/install/views.view.community_reports_admin.yml`

## Outstanding BA work required

- [ ] Confirm AmISafe JS integration approach (attach behavior vs. passthrough request to amisafe module)
- [ ] Confirm lat/lng storage approach for first version (plain address text only per feature.md notes — no geocoding)
- [ ] Confirm taxonomy term list for `incident_type` field (exact machine names)
- [ ] Confirm whether photo field should be an `image` field type or a `file` field type (feature.md says image)
- [ ] Specify the Drupal form class or route approach for `/community/report` (custom Form class recommended)
- [ ] Specify node access setup for anonymous-view / authenticated-submit split
