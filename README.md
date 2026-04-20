# βyblos — Native ePortfolio for Moodle

**βyblos** is a full-featured ePortfolio plugin for Moodle 5.0+ that gives students a Wix/Squarespace-style page builder to create, curate, and share their learning portfolios — entirely within Moodle.

Built as a native Moodle alternative to Mahara, βyblos requires no external services, no separate logins, and no additional infrastructure. Install the plugin, and every authenticated user gets a portfolio.

## Features

### For Students
- **Template Gallery** — pick from 8 professionally designed page templates (Personal Portfolio, Academic CV, Project Showcase, Creative Work, Learning Journey, Professional Profile, Research Portfolio, Simple Page) and customise from there
- **Section-Based Page Builder** — Wix-style inline editor with 20 section types covering layout, storytelling, academic content, media, and multi-page navigation (see [Section Types](#section-types) below)
- **6 Visual Themes** — Clean, Academic, Modern Dark, Creative, Corporate, and Streaming (Netflix-inspired dark theme). Switch themes per page with live preview
- **6 Layouts** — Single column, two equal, wide-left, wide-right, three-column, and hero + two-column
- **Image Upload** — drag-and-drop or browse. Images stored securely via Moodle's file API
- **Auto-Import** — course completions and issued badges are automatically imported as portfolio artefacts
- **Sharing with scoped pickers** — share with specific users, courses, or groups drawn from your current enrolments (no IDs to track down), or generate a public secret URL if permitted
- **Collections with multi-page navigation** — group related pages into an ordered collection with a home page, auto-generated nav pills, and a `pagenav` section for in-page navigation
- **Group collections** — collections bound to a Moodle group let every member contribute their own pages to a shared canvas. Adding a page is explicit consent; only the creator can rename or delete
- **Preview as viewer** — one click shows the collection as a shared viewer will see it, with sibling pages clickable through the nav strip
- **Export** — download pages as self-contained HTML or PDF

### For Teachers
- **Course Portfolios** — view all student portfolios tagged with your course from a single page
- **Shared Access** — students share work with teachers; teachers see a consolidated view
- **Assignment submissions** — students submit a portfolio page or whole collection as a `mod_assign` submission via the `assignsubmission_byblos` subplugin, with live / snapshot / live-until-locked modes
- **Peer review with advanced grading** — assignments can use numeric, star, or rubric scoring. Rubric mode reads Moodle's advanced-grading rubric and renders it as an interactive criterion × level grid; advisory score is the sum of selected levels
- **Inline comments** — teachers and peers leave anchored comments against any section; visibility controls (`teacher_only`, `after_submit`, `on_grade_release`) govern what students see and when
- **Collection-level submissions** — a multi-page collection can be submitted as a single snapshot covering every member page
- **Completion Integration** — set "create N portfolio pages" as a course completion condition

### For Administrators
- **Granular Capabilities** — control who can create, share, share publicly, view shared work, manage templates, or manage all portfolios
- **Site-Wide Settings** — default theme, default layout, max artefacts/pages per user, enable/disable public sharing, auto-import toggle
- **GDPR Compliant** — full privacy API implementation with data export and deletion
- **Event Logging** — page_created, page_viewed, page_shared, artefact_created, portfolio_exported events for analytics and reporting

## Requirements

- Moodle 5.0+ (2024100700)
- PHP 8.1+
- MySQL/MariaDB with utf8mb4 or PostgreSQL

## Installation

1. Copy the `local_byblos` folder to `{moodleroot}/local/byblos/`
2. Visit **Site administration → Notifications** to trigger the database installation
3. Configure at **Site administration → Plugins → Local plugins → βyblos**
4. Users will see "βyblos" in their user menu dropdown

## Configuration

| Setting | Default | Description |
|---------|---------|-------------|
| Enable βyblos | Yes | Master on/off switch |
| Default theme | Clean | Theme applied to new pages |
| Default layout | Single column | Layout applied to new pages |
| Allow public sharing | No | Whether users can generate public URLs |
| Max artefacts per user | 500 | 0 = unlimited |
| Max pages per user | 50 | 0 = unlimited |
| Allow PDF export | Yes | Enable PDF download option |
| Auto-import | Yes | Auto-create artefacts from badges/completions |
| Completion pages | 0 | Pages required for course completion (0 = disabled) |

## Capabilities

| Capability | Description | Default roles |
|------------|-------------|---------------|
| `local/byblos:use` | Access the portfolio | All authenticated users |
| `local/byblos:createpage` | Create and edit own pages | All authenticated users |
| `local/byblos:share` | Share with users/courses/groups | All authenticated users |
| `local/byblos:sharepublic` | Generate public share links | Manager |
| `local/byblos:viewshared` | View other users' shared portfolios | Teacher, Editing teacher, Manager |
| `local/byblos:managetemplates` | Manage site-wide templates | Manager |
| `local/byblos:manageall` | View/edit any user's portfolio | Manager |

## Templates

| Template | Sections | Recommended Theme |
|----------|----------|-------------------|
| Personal Portfolio | Hero, About, Skills, Gallery, Social | Clean |
| Academic CV | Header, Education, Qualifications, Badges, Publications, Contact | Academic |
| Project Showcase | Hero, Description, Gallery, Technical, Reflection | Creative |
| Creative Work | Gallery, Statement, Timeline, Contact | Creative |
| Learning Journey | Timeline, Reflections, Badges, Completions, Goals | Academic |
| Professional Profile | Hero, Experience, Skills, Certifications, Social | Corporate |
| Research Portfolio | Abstract, Literature, Methodology, Findings, References | Academic |
| Simple Page | Heading, Text, Image, CTA | Clean |

## Section Types

| Category | Section | Purpose |
|----------|---------|---------|
| Layout | Hero | Full-width banner with background colour/image, photo, name, title, subtitle |
| Layout | Divider | Themed separator between sections |
| Layout | CTA | Call-to-action card with title, body, and button |
| Storytelling | Text | Rich-text block (TinyMCE) |
| Storytelling | Text + Image | Side-by-side text and image with alignment options |
| Storytelling | Quote | Pull-quote with attribution |
| Storytelling | Timeline | Chronological entries with dates, titles, descriptions |
| Media | Gallery | Responsive image grid |
| Media | YouTube | Embedded video with full/centered/left/right alignment + optional body text |
| Media | Files | Attached-file list in list / tile / thumbs modes |
| Academic | Skills | Themed proficiency bars or tag cloud |
| Academic | Cloud | Weighted tag cloud (topics, interests) |
| Academic | Stats | Numeric stat tiles (publications, citations, years) |
| Academic | Chart | Server-rendered SVG bar or pie chart |
| Academic | Citations | Formatted reference list (APA/MLA/Chicago/Harvard) |
| Moodle data | Badges | Auto-imported Moodle badges as artefacts |
| Moodle data | Completions | Course completions as artefacts |
| Moodle data | Social | Links to professional profiles (LinkedIn, ORCID, etc.) |
| Navigation | PageNav | Tabs / pills / cards / next-prev nav between pages in a collection or hand-picked list |
| Advanced | Custom | Raw HTML block for advanced users |

## Themes

- **Clean** — white background, system fonts, subtle shadows
- **Academic** — cream background, serif headings, navy accents
- **Modern Dark** — charcoal background, light text, vibrant accents
- **Creative** — bold purple headers, gradient sections, rounded cards
- **Corporate** — tight spacing, blue-grey palette, minimal borders
- **Streaming** — dark (#0d0d0d) background, teal (#00d4aa) accents, hover-zoom effects, cinematic typography

## Privacy & GDPR

βyblos implements Moodle's Privacy API across all 8 database tables. User data can be exported and deleted through Moodle's standard privacy tools (Site administration → Users → Privacy and policies).

## Events

All portfolio actions fire standard Moodle events for logging and analytics:
- `\local_byblos\event\page_created`
- `\local_byblos\event\page_viewed`
- `\local_byblos\event\page_shared`
- `\local_byblos\event\artefact_created`
- `\local_byblos\event\portfolio_exported`

## Collections and navigation

A **collection** groups an ordered set of pages into a mini-site. Each collection has:

- A **home page** — the entry point shown when previewing or when a viewer follows a collection share link. Promote any page to home with one click.
- **Reordering** — up/down arrows on each page card in the collection view; order drives the nav pill strip on the page view.
- **Auto nav strip** — when a viewer opens any page in a collection, a pill nav appears showing every sibling page they have access to. The owner's normal view hides it (the dashboard already surfaces their pages).
- **PageNav sections** — drop an in-page nav block (tabs/pills/cards/next-prev) anywhere on a page, bound to a collection or a hand-picked page list.
- **Preview collection** — opens the home page in embedded viewer layout with owner chrome removed, so the owner can click through and confirm exactly what a shared viewer experiences.

### Group collections

Any collection can be bound to a Moodle group. Once bound:

- Every member of the group sees the collection on their dashboard and can add **their own** pages to it from the page editor's "Add to collection" dropdown.
- Adding a page is explicit consent — anyone with access to the collection can now view that page. Removing the page from the collection revokes that specific access.
- Group members can reorder pages and set the home page.
- The **creator** retains exclusive rights to rename, delete, or change sharing on the collection. Page *content* always stays with the individual page owner; one member can't edit another's page.

This makes group portfolios (cohort projects, study circles, team assignments) work without duplicate page creation or manual share-chains.

## Sharing

Share scope dropdowns are scoped to what's actually reachable from your enrolments:

- **User** — choose any user who shares a course with you.
- **Course** — choose any course you're enrolled in.
- **Group** — choose any group you belong to in any of those courses.
- **Public** — generate a secret URL (requires the `local/byblos:sharepublic` capability and the site-wide `allowpublic` setting).

A single **collection share** fans out automatically to every page in the collection — viewers can navigate the full mini-site without needing per-page share records.

## Roadmap

- [ ] PDF export polish
- [ ] Additional templates
- [ ] Dashboard block for recent portfolio activity
- [ ] Auto-generate group collections when a `mod_assign` + `assignsubmission_byblos` assignment uses group mode (deferred, captured in TODO.md)

## License

This plugin is licensed under the [GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0.html), the same license as Moodle itself.

## Credits

Developed by the South African Theological Seminary (SATS) IT team.
