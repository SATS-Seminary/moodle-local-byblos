# Changelog

All notable changes to the βyblos ePortfolio plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-04-21

### Added

#### Announcement turnstile
- `go.php` endpoint that authenticates a student against a course, fires a per-student `answer_opened` log event in the course context, then redirects to a Byblos portfolio page. Destination is resolved server-side from an integer page id; the resolved URL is asserted to live under `$CFG->wwwroot`, and visibility is enforced via `share::can_view_page()` (same gate as the canonical page viewer). No request parameter can produce an off-site redirect.
- `\local_byblos\event\answer_opened` (CRUD `r`, LEVEL_PARTICIPATING, no `objecttable`, page id carried in `other`).
- "Get announcement link" picker on the page view, gated on `moodle/course:update` in at least one of the viewer's courses. Popover lists postable courses, builds the turnstile URL live, and offers copy-to-clipboard.
- New external WS `local_byblos_list_postable_courses`.

#### Chart widget — major feature expansion
- **Axis labels** (X / Y) for bar and line charts.
- **Unit suffix** appended to every value display and to the y-axis grid ticks (`%`, `hrs`, `$`, …).
- **Caption / source footer** rendered as italic small print below the chart.
- **Show / hide value labels** toggle.
- **Multiple series** — comma-separated series names; each data row gains N value inputs; bar charts render as grouped pairs, line charts as multiple polylines, both with an inline legend. Pie/donut ignore series (slices always derive from the first series).
- **Per-item colour override** picker on each row.
- **Bar orientation** toggle — horizontal (existing) or vertical columns.
- **Sort order** — as entered / largest first / smallest first.
- **Y-axis grid** with four tick lines + value labels.
- All new options are additive; existing single-series `{label, value}` charts render unchanged.

#### Chart editor — redesigned UI
- Two-pane layout: tabbed form on the left, **live preview** pane on the right that re-renders via the new `local_byblos_render_chart_preview` WS (debounced 220 ms).
- Visual chart-type picker — four SVG-icon tiles (bar / line / pie / donut) replacing the dropdown.
- Tabs: **Data / Appearance / Labels / Advanced**; opens on Data.
- Data rows become a proper table with column headers and **drag-to-reorder** handles.
- "Base Colour" renamed to **Palette colour** with inline help explaining its three remaining roles.

#### Gallery editor — redesigned UI
- Visual thumbnail grid that mirrors the published card layout (column-count tiles drive the grid template).
- Per-tile hover overlay with title caption and a quick-remove `✕`.
- Trailing **+ Add** tile that spawns a fresh data tile and opens the file picker immediately.
- Inline detail panel below the grid for editing title / description / image of the selected tile (writes back to the tile's hidden inputs in real time, refreshing the thumbnail).
- Drag-to-reorder data tiles; the add tile re-pins to the end after each drop.

#### Skills widget — named proficiency levels
- Replaced the meaningless 0–100 percentage with a 1–5 named scale: **Novice / Beginner / Intermediate / Proficient / Expert**. Bar fills proportionally (1=20% … 5=100%).
- Editor input format swapped from `Name:0-100` to `Name:1-5` with the level legend inline in the field hint.
- Renderer clamps legacy values >5 to Expert so old pages still render a recognisable bar.
- Template seed data converted to the new scale.

#### Custom HTML section — security and editor overhaul
- Server-side sanitisation: both the public and editor-preview renderers now pipe content through `format_text(…, FORMAT_HTML, ['noclean' => false])`. HTMLPurifier strips `<script>`, all `on*` event handlers, `javascript:` URLs, `<iframe>`/`<object>`/`<embed>`, `<meta http-equiv="refresh">`, and tightens inline `style`.
- New capability `local/byblos:editcustomhtml` (default: editingteacher + manager, with `RISK_XSS`). The custom-HTML tile is hidden from the section picker for users without it, and the `add_section` WS rejects the type at the API boundary.
- Edit field switched from a rich-text editor to a dark code editor with live HTML syntax highlighting (in-house regex tokeniser — no new dependencies). Tab inserts two spaces; horizontal scroll is preserved between the textarea and the highlighted overlay.
- Visible "HTML is sanitised on save" notice above the field listing what gets stripped.

#### Stats section
- Default placeholder values rewritten to be unmistakably placeholder (number `0`, label "Replace with your own count"). Removes the risk of pretend-real `98% Satisfaction`-style filler landing on a published page.

### Changed
- Public-share view (`publicview.php`) renders sections through `renderer::render_section()` instead of feeding the raw `content` column to the template, so shared pages now show the actual hero / text / gallery / chart instead of empty `<div class="byblos-section">` stubs.
- README updated to reflect the announcement-turnstile flow and the redesigned chart / gallery / skills / custom-HTML editors.

### Fixed
- Inline page title editor was eating space characters: the keydown handler on the heading element was `preventDefault`-ing on every Enter/Space event, including those that bubbled up from the inline `<input>`. Now guarded so the handler only fires when the heading itself is the event target.
- `moodle/course:announce` capability used by the announcement-link picker doesn't exist in Moodle core; replaced with `moodle/course:update` so the trigger actually appears for editing teachers / managers.
- `publicview.php` footer rendered `[[pluginfullname]]` (missing lang key); switched to the existing `pluginname` key.

### Security
- Documented threat model for the custom-HTML section in code comments (stored XSS via `<script>`, event handlers, `javascript:` URLs, `data:` SVG payloads, `<iframe>` phishing, `<form>` action hijacking, meta-refresh redirects, CSS-based phishing / keylogging). Mitigations land at sanitisation (HTMLPurifier) + capability gate (`local/byblos:editcustomhtml`).

## [1.0.0] - 2026-04-16

### Added

#### Core
- Plugin scaffold for Moodle 5.0+ (PHP 8.1+)
- 8 database tables with XMLDB schema (`local_byblos_artefact`, `_page`, `_section`, `_collection`, `_collection_page`, `_share`, `_page_course`, `_submission`)
- 7 capabilities with role-based defaults (`use`, `createpage`, `share`, `sharepublic`, `viewshared`, `managetemplates`, `manageall`)
- 9 admin settings (enable, default theme/layout, sharing, limits, auto-import, completion, PDF export)
- ~340 English language strings
- Full Moodle coding standards compliance

#### Artefacts
- 6 artefact types: text, file, image, course completion, badge, blog entry
- Artefact CRUD with type registry
- Auto-import of issued badges from `badge_issued` table
- Auto-import of course completions from `course_completions` table
- Deduplication via `sourceref` field

#### Page Builder
- 8 pre-designed page templates: Personal Portfolio, Academic CV, Project Showcase, Creative Work, Learning Journey, Professional Profile, Research Portfolio, Simple Page
- 12 section types: hero, text, text + image, gallery, skills, timeline, badges, completions, social links, call-to-action, divider, custom HTML
- 6 layouts: single column, two equal, wide-left, wide-right, three-column, hero + two-column
- 6 visual themes: Clean, Academic, Modern Dark, Creative, Corporate, Streaming
- Wix/Squarespace-style inline section editor (AJAX-driven, no page reloads)
- AMD JavaScript modules (`editor.js`, `editor_inline.js`) with Moodle external function integration
- Contenteditable inline editing for text sections with floating formatting toolbar
- Section reorder via up/down controls
- Theme picker with live preview
- Section type picker modal (12 types with icons and descriptions)

#### Image Upload
- Drag-and-drop and file browse upload
- Moodle native file API integration (`file_storage`, `stored_file`, `pluginfile.php`)
- Image MIME type validation (JPEG, PNG, GIF, WebP, SVG)
- 10MB file size limit
- AMD upload widget module (`upload.js`)
- Upload widgets in hero, text + image, and gallery section editors

#### Sharing
- Share pages/collections with specific users
- Share with course participants (all enrolled users)
- Share with group members
- Public token-based sharing (64-character hex token, gated by `sharepublic` capability)
- Share management page with add/remove controls
- "Shared with me" page listing received shares
- Public view page (no authentication required)

#### Course Integration
- Pages can be tagged with one or more courses
- Course Portfolios page shows all student pages for a course
- Teachers with `viewshared` capability see all tagged pages
- Students see their own pages + pages shared with them

#### Collections
- Group related pages into ordered collections
- Add/remove/reorder pages within collections
- Collection view with page navigation

#### Navigation
- "βyblos" link in user dropdown menu (all authenticated users)
- "Course Portfolios" link in course navigation (for enrolled users)
- Profile page link via `myprofile_navigation`

#### Events & Completion
- 5 Moodle events: `page_created`, `page_viewed`, `page_shared`, `artefact_created`, `portfolio_exported`
- Course completion integration: "created N portfolio pages" as completion condition
- Event observer on `page_created` triggers completion check

#### Privacy (GDPR)
- Full Privacy API implementation across all 8 database tables
- `get_metadata()` declares all stored personal data fields
- `export_user_data()` exports artefacts, pages, sections, collections, shares, submissions
- `delete_data_for_user()` cascading delete across all related records
- `get_contexts_for_userid()` for context discovery

#### Themes (CSS)
- 936-line `styles.css` with all 6 themes scoped to `.byblos-theme-{key}`
- Streaming theme: dark background (#0d0d0d), teal accent (#00d4aa), hover-zoom effects, cinematic typography
- Section-type-specific styles (hero banners, timeline tracks, skills bars, gallery grids)
- Editor styles (toolbars, drop zones, inline edit highlights)
- `!important` on key properties for Bootstrap 4 cascade compatibility

### Not Yet Implemented (Roadmap)
- Assignment submission integration (`assignsubmission_byblos`)
- PDF export
- Additional page templates
- Dashboard block
- Rubric-based portfolio assessment

[1.0.0]: https://github.com/sats/moodle-local_byblos/releases/tag/v1.0.0
