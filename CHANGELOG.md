# Changelog

All notable changes to the βyblos ePortfolio plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
