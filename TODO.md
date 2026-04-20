# Byblos — roadmap / backlog

Non-urgent work items. Add new items at the top; strike through or remove done ones.

---

## Group collections — shipped 2026-04-20

Shipped in this pass:

- Nullable `groupid` on `local_byblos_collection` + upgrade step.
- `collection::can_manage_metadata` / `can_contribute` / `is_group_collection`
  permission helpers.
- `share::can_view_collection` honours `groupid` so group members auto-view
  without explicit share records.
- `share::can_view_page` already chains via collection membership, so group
  viewership propagates to member pages.
- Dashboard + editor "create collection" UI gains a group picker.
- `collection_view` shows group badge + contributor attribution + per-page
  remove button for page owners.
- `list_user_collections` WS returns personal + contributable group collections
  with `is_group` / `is_creator` / `group_name` flags; new
  `list_user_groups` WS feeds the pickers.

**Still deferred:**

- **mod_assign auto-create.** When a teacher enables a `mod_assign` +
  `assignsubmission_byblos` assignment with group mode on, auto-generate one
  group collection per group. Lives in the assignsubmission subplugin.
- **Ad-hoc multi-user collections.** Would need `local_byblos_collection_member`
  table (not yet created). Out of scope for now; groups cover the main use case.

---

## Other deferred items

- Full TinyMCE rubric rendering for peer review (currently a JSON textarea fallback
  per Phase 4 report).
- Collection-level snapshots for assign submissions (currently snapshots only fire
  on page-backed submissions).
- Fix `assign_plugin_config` `peervisibility=on_grade_release` heuristic — current
  implementation uses `assign_grades.grader > 0` as a proxy; may false-positive when
  markingworkflow is off.
- Replace initials-style SVG avatars with realistic placeholder photos (done: user
  dropped in 5 JPEGs).
- Badge URL resolution in `artefact_external` uses the spec'd `?hash=<badgeid>` pattern;
  auto-imported badges store `badge:<issuedid>` which isn't a hash token. Links
  currently fall back to the artefact.php URL for those.
