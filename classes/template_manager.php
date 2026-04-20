<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Page template manager for portfolio pages.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos;

// phpcs:disable moodle.Files.LineLength
// Rationale: this file holds the seed JSON bodies for the built-in page templates.
// The placeholder copy inside each template is deliberately authored as readable
// continuous prose rather than fragmented string concatenation, so line-length
// errors here reflect the content, not structural code that would be hard to read.

/**
 * Manages pre-designed page templates.
 *
 * Each template defines a page structure with pre-configured sections and
 * placeholder content that users can customise.
 */
class template_manager {
    /**
     * Returns all available page template definitions.
     *
     * Each template is an associative array with:
     * - key: Machine name.
     * - name: Lang string key for human-readable name.
     * - description: Lang string key for description.
     * - icon: FontAwesome icon class.
     * - theme: Recommended theme key.
     * - sections: Array of section definitions (sectiontype, configdata, content).
     *
     * @return array[] All template definitions keyed by template key.
     */
    public static function get_all(): array {
        return [
            'personal-portfolio' => self::template_personal_portfolio(),
            'academic-cv' => self::template_academic_cv(),
            'project-showcase' => self::template_project_showcase(),
            'creative-work' => self::template_creative_work(),
            'learning-journey' => self::template_learning_journey(),
            'professional-profile' => self::template_professional_profile(),
            'research-portfolio' => self::template_research_portfolio(),
            'simple-page' => self::template_simple_page(),
        ];
    }

    /**
     * Returns a single template definition by key.
     *
     * @param string $key The template key.
     * @return array|null The template definition, or null if not found.
     */
    public static function get(string $key): ?array {
        $all = self::get_all();
        return $all[$key] ?? null;
    }

    /**
     * Creates a new portfolio page from a template.
     *
     * Inserts a page record with the template's recommended theme and layout,
     * then inserts all template sections with their placeholder content.
     *
     * @param int $userid The user who owns the page.
     * @param string $templatekey The template key to use.
     * @param string $title Optional page title override. Defaults to template name string.
     * @return int The ID of the newly created page.
     * @throws \coding_exception If the template key is invalid.
     * @throws \dml_exception On database error.
     */
    public static function create_page_from_template(int $userid, string $templatekey, string $title = ''): int {
        global $DB;

        $template = self::get($templatekey);
        if ($template === null) {
            throw new \coding_exception('Invalid template key: ' . $templatekey);
        }

        $now = time();

        // Use provided title or fall back to the template name string.
        if ($title === '') {
            $title = get_string($template['name'], 'local_byblos');
        }

        // Insert the page.
        $page = new \stdClass();
        $page->userid = $userid;
        $page->title = $title;
        $page->description = get_string($template['description'], 'local_byblos');
        $page->layoutkey = 'single';
        $page->themekey = $template['theme'];
        $page->status = 'draft';
        $page->timecreated = $now;
        $page->timemodified = $now;
        $pageid = $DB->insert_record('local_byblos_page', $page);

        // Insert sections from template.
        $sortorder = 0;
        foreach ($template['sections'] as $sectiondef) {
            $section = new \stdClass();
            $section->pageid = $pageid;
            $section->sectiontype = $sectiondef['sectiontype'];
            $section->sortorder = $sortorder;
            $section->configdata = $sectiondef['configdata'];
            $section->content = $sectiondef['content'] ?? '';
            $section->timecreated = $now;
            $section->timemodified = $now;
            $DB->insert_record('local_byblos_section', $section);
            $sortorder++;
        }

        return (int) $pageid;
    }

    /**
     * Personal Portfolio template.
     *
     * Hero banner, about section, skills, gallery, and social links.
     *
     * @return array Template definition.
     */
    private static function template_personal_portfolio(): array {
        return [
            'key' => 'personal-portfolio',
            'name' => 'template_personal_portfolio',
            'description' => 'template_personal_portfolio_desc',
            'icon' => 'fa-user-circle',
            'theme' => 'clean',
            'sections' => [
                [
                    'sectiontype' => 'hero',
                    'configdata' => json_encode([
                        'name' => 'Alex Morgan',
                        'title' => 'Curious mind, careful maker',
                        'subtitle' => 'Third-year Humanities student building a portfolio of ideas, writing, and side projects.',
                        'bg_color' => '#1f2a44',
                        'bg_image' => '',
                        'photo_url' => (new \moodle_url('/local/byblos/pix/avatars/profile4.jpeg'))->out(false),
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'About me',
                        'body' => '<p>I am a student who thinks best at the edges between disciplines - where literature meets data, where teaching meets design. This page is where I keep the work I am proud of and the notes I go back to. If you are a mentor, collaborator, or future employer, thank you for taking the time to look.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'skills',
                    'configdata' => json_encode([
                        'heading' => 'What I bring',
                        'skills' => [
                            ['name' => 'Academic writing', 'level' => 85],
                            ['name' => 'Qualitative research', 'level' => 80],
                            ['name' => 'Public speaking', 'level' => 75],
                            ['name' => 'Collaboration & facilitation', 'level' => 90],
                            ['name' => 'Digital tools (Office, Moodle, Zotero)', 'level' => 80],
                            ['name' => 'Second language (French, intermediate)', 'level' => 60],
                        ],
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'divider',
                    'configdata' => json_encode(['style' => 'solid']),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'gallery',
                    'configdata' => json_encode([
                        'heading' => 'Selected work',
                        'columns' => 3,
                        'items' => [
                            ['image_url' => '', 'title' => 'Undergraduate thesis', 'caption' => 'Memory and identity in post-war fiction'],
                            ['image_url' => '', 'title' => 'Community podcast', 'caption' => 'A six-episode series on local oral history'],
                            ['image_url' => '', 'title' => 'Tutoring journal', 'caption' => 'Notes from a year of peer tutoring'],
                            ['image_url' => '', 'title' => 'Short essays', 'caption' => 'Reviews and reflections, updated monthly'],
                        ],
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'What I am learning right now',
                        'body' => '<p>This semester I am exploring archival research methods, improving my academic writing, and starting a small reading group on narrative theology. I believe in learning in public - so expect this page to grow as I do.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'cta',
                    'configdata' => json_encode([
                        'heading' => 'Let us talk',
                        'body' => 'If anything on this page sparked a thought, I would love to hear from you - whether it is a research lead, a collaboration, or a coffee.',
                        'button_text' => 'Get in touch',
                        'button_url' => '#',
                        'bg_color' => '#1f2a44',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'social',
                    'configdata' => json_encode([
                        'links' => [
                            ['platform' => 'linkedin', 'url' => ''],
                            ['platform' => 'github', 'url' => ''],
                            ['platform' => 'globe', 'url' => ''],
                        ],
                    ]),
                    'content' => '',
                ],
            ],
        ];
    }

    /**
     * Academic CV template.
     *
     * Header, education timeline, qualifications, badges, publications, contact.
     *
     * @return array Template definition.
     */
    private static function template_academic_cv(): array {
        return [
            'key' => 'academic-cv',
            'name' => 'template_academic_cv',
            'description' => 'template_academic_cv_desc',
            'icon' => 'fa-graduation-cap',
            'theme' => 'academic',
            'sections' => [
                [
                    'sectiontype' => 'hero',
                    'configdata' => json_encode([
                        'name' => 'Dr. Jordan Cole',
                        'title' => 'Curriculum Vitae',
                        'subtitle' => 'Early-career researcher in systematic theology and digital humanities',
                        'bg_color' => '#14253f',
                        'bg_image' => '',
                        'photo_url' => (new \moodle_url('/local/byblos/pix/avatars/profile3.jpeg'))->out(false),
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Research interests',
                        'body' => '<p>Twentieth-century theology, narrative ethics, digital methods in the humanities, and the pedagogy of online learning. My current work examines how communities of practice form around open educational resources.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'timeline',
                    'configdata' => json_encode([
                        'heading' => 'Education',
                        'items' => [
                            ['date' => '2024 - Present', 'title' => 'PhD candidate, Theology', 'description' => 'University of Edinburgh. Thesis: "Communities of Practice in Digital Theological Education." Supervisor: Prof. M. Thompson.'],
                            ['date' => '2022 - 2023', 'title' => 'MA, Theology and Religious Studies', 'description' => 'University of Edinburgh. Distinction. Dissertation on narrative ethics in post-secular contexts.'],
                            ['date' => '2019 - 2022', 'title' => 'BA (Hons), Philosophy and Theology', 'description' => 'University of Cape Town. First class. Minor in Digital Humanities.'],
                            ['date' => '2021', 'title' => 'Visiting scholar', 'description' => 'Tyndale House, Cambridge. Summer research fellowship.'],
                        ],
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'timeline',
                    'configdata' => json_encode([
                        'heading' => 'Teaching & academic roles',
                        'items' => [
                            ['date' => '2024 - Present', 'title' => 'Graduate tutor', 'description' => 'Introduction to Theology (undergraduate, 60 students). Seminar design and assessment.'],
                            ['date' => '2023 - 2024', 'title' => 'Research assistant', 'description' => 'Digital Humanities Lab: text-encoding and corpus curation.'],
                            ['date' => '2022 - 2023', 'title' => 'Peer mentor', 'description' => 'Postgraduate writing group facilitator.'],
                        ],
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'divider',
                    'configdata' => json_encode(['style' => 'solid']),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Selected publications',
                        'body' => '<ol><li>Cole, J. (2025). <em>Reading together at a distance: narrative pedagogy in online theology</em>. Journal of Theological Education, 12(2), 45-63.</li><li>Cole, J. (2024). <em>Open resources and the shape of the seminar</em>. Teaching Theology and Religion, 27(1), 12-28.</li><li>Cole, J. &amp; Daniels, R. (2023). <em>Annotating the digital archive</em>. Digital Humanities Quarterly, 17(4).</li></ol>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Grants, awards & fellowships',
                        'body' => '<ul><li>AHRC Doctoral Scholarship (2024 - 2027)</li><li>Tyndale House Summer Fellowship (2021)</li><li>University Medal for Undergraduate Research (2022)</li><li>Best Graduate Paper, Digital Humanities Congress (2024)</li></ul>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'badges',
                    'configdata' => json_encode(['heading' => 'Badges & certifications', 'show' => true]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'social',
                    'configdata' => json_encode([
                        'links' => [
                            ['platform' => 'linkedin', 'url' => ''],
                            ['platform' => 'globe', 'url' => ''],
                            ['platform' => 'twitter', 'url' => ''],
                        ],
                    ]),
                    'content' => '',
                ],
            ],
        ];
    }

    /**
     * Project Showcase template.
     *
     * Hero, overview, gallery, technical details, outcomes.
     *
     * @return array Template definition.
     */
    private static function template_project_showcase(): array {
        return [
            'key' => 'project-showcase',
            'name' => 'template_project_showcase',
            'description' => 'template_project_showcase_desc',
            'icon' => 'fa-rocket',
            'theme' => 'modern-dark',
            'sections' => [
                [
                    'sectiontype' => 'hero',
                    'configdata' => json_encode([
                        'name' => 'Atlas',
                        'title' => 'A map for the first week of class',
                        'subtitle' => 'An onboarding app that turned a 40-page handbook into a five-minute conversation.',
                        'bg_color' => '#0b0f1a',
                        'bg_image' => '',
                        'photo_url' => (new \moodle_url('/local/byblos/pix/avatars/profile2.jpeg'))->out(false),
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'The problem',
                        'body' => '<p>Every semester, new students were arriving overwhelmed - dozens of logins, inconsistent instructions, and a handbook no-one reads. The support desk was answering the same twelve questions hundreds of times. We wanted to meet students where they already were: on their phones, in a hurry, needing the right answer right now.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text_image',
                    'configdata' => json_encode([
                        'heading' => 'What we built',
                        'body' => '<p>A progressive web app with a guided onboarding flow, a searchable knowledge base, and a smart help button that routes to the right person. Built mobile-first, tested with 24 first-year students across three iterations.</p>',
                        'image_url' => '',
                        'image_alt' => 'Screenshots of the onboarding flow',
                        'reversed' => false,
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'gallery',
                    'configdata' => json_encode([
                        'heading' => 'Process & artefacts',
                        'columns' => 2,
                        'items' => [
                            ['image_url' => '', 'title' => 'User research', 'caption' => 'Twelve interviews, three personas'],
                            ['image_url' => '', 'title' => 'Wireframes', 'caption' => 'From whiteboard to Figma in a weekend'],
                            ['image_url' => '', 'title' => 'Usability testing', 'caption' => 'Two rounds, 24 participants'],
                            ['image_url' => '', 'title' => 'Launch day', 'caption' => 'First cohort, August 2025'],
                        ],
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'skills',
                    'configdata' => json_encode([
                        'heading' => 'My role & stack',
                        'skills' => [
                            ['name' => 'Product design (Figma)', 'level' => 85],
                            ['name' => 'Frontend (React, TypeScript)', 'level' => 80],
                            ['name' => 'User research & testing', 'level' => 75],
                            ['name' => 'Accessibility (WCAG 2.2 AA)', 'level' => 70],
                        ],
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'divider',
                    'configdata' => json_encode(['style' => 'solid']),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Results',
                        'body' => '<ul><li>Support tickets in the first two weeks dropped by 62%.</li><li>Average time-to-first-login fell from 38 minutes to 6 minutes.</li><li>Net promoter score from the first-year cohort: +47.</li></ul>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'What I would do differently',
                        'body' => '<p>We shipped before we had analytics in place, which cost us a clean before/after picture. Next time I would wire up instrumentation on day one, and I would bring the support team into the design sessions earlier - they knew the real questions long before we did.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'cta',
                    'configdata' => json_encode([
                        'heading' => 'Want the full case study?',
                        'body' => 'Happy to walk through the research data, wireframes, and retrospective notes.',
                        'button_text' => 'Request case study',
                        'button_url' => '#',
                        'bg_color' => '#4f46e5',
                    ]),
                    'content' => '',
                ],
            ],
        ];
    }

    /**
     * Creative Work template.
     *
     * Hero, gallery, artist statement, exhibition timeline, social.
     *
     * @return array Template definition.
     */
    private static function template_creative_work(): array {
        return [
            'key' => 'creative-work',
            'name' => 'template_creative_work',
            'description' => 'template_creative_work_desc',
            'icon' => 'fa-paint-brush',
            'theme' => 'creative',
            'sections' => [
                [
                    'sectiontype' => 'hero',
                    'configdata' => json_encode([
                        'name' => 'Maya Okonkwo',
                        'title' => 'Colour, pattern, and the slow work of making.',
                        'subtitle' => 'Illustrator and printmaker based in Johannesburg.',
                        'bg_color' => '#b5179e',
                        'bg_image' => '',
                        'photo_url' => (new \moodle_url('/local/byblos/pix/avatars/Profile5.jpeg'))->out(false),
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'gallery',
                    'configdata' => json_encode([
                        'heading' => 'Selected works',
                        'columns' => 3,
                        'items' => [
                            ['image_url' => '', 'title' => 'Morning Rooms', 'caption' => 'Risograph, two-colour, 2025'],
                            ['image_url' => '', 'title' => 'Field Notes', 'caption' => 'Ink on cotton paper, 2024'],
                            ['image_url' => '', 'title' => 'After the Rain', 'caption' => 'Linocut series, 2024'],
                            ['image_url' => '', 'title' => 'Small Gods', 'caption' => 'Digital illustration, 2023'],
                            ['image_url' => '', 'title' => 'Carry', 'caption' => 'Mixed media, 2023'],
                            ['image_url' => '', 'title' => 'Quiet Light', 'caption' => 'Screenprint edition of 20, 2022'],
                        ],
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Artist statement',
                        'body' => '<p>I make images about the small, mostly invisible rituals that hold a day together - a cup of tea, a note on a fridge, a window left open. My work is rooted in printmaking, but I am just as likely to be drawing on my tablet at a coffee shop. I care about craft, patience, and the possibility that something ordinary, looked at slowly, will surprise us.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text_image',
                    'configdata' => json_encode([
                        'heading' => 'In the studio',
                        'body' => '<p>I work in a shared studio space in Braamfontein, mostly in the mornings. My current series uses layered risograph prints to explore the textures of domestic interiors - it is an attempt to draw attention instead of objects.</p>',
                        'image_url' => '',
                        'image_alt' => 'Studio bench with prints and inks',
                        'reversed' => true,
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'timeline',
                    'configdata' => json_encode([
                        'heading' => 'Exhibitions & features',
                        'items' => [
                            ['date' => '2025', 'title' => 'Morning Rooms', 'description' => 'Solo show, SMAC Gallery, Cape Town.'],
                            ['date' => '2024', 'title' => 'Print Fair Joburg', 'description' => 'Group exhibition and fair.'],
                            ['date' => '2024', 'title' => 'Feature: It\'s Nice That', 'description' => '"Ten African illustrators to watch."'],
                            ['date' => '2023', 'title' => 'Field Notes', 'description' => 'Two-person show, The Fourth, Stellenbosch.'],
                        ],
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'divider',
                    'configdata' => json_encode(['style' => 'solid']),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'cta',
                    'configdata' => json_encode([
                        'heading' => 'Commissions & prints',
                        'body' => 'Open for editorial illustration and select commission work. Signed prints available directly from the studio.',
                        'button_text' => 'Email the studio',
                        'button_url' => '#',
                        'bg_color' => '#b5179e',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'social',
                    'configdata' => json_encode([
                        'links' => [
                            ['platform' => 'instagram', 'url' => ''],
                            ['platform' => 'behance', 'url' => ''],
                            ['platform' => 'globe', 'url' => ''],
                        ],
                    ]),
                    'content' => '',
                ],
            ],
        ];
    }

    /**
     * Learning Journey template.
     *
     * Hero, milestones timeline, reflections, badges, completions, goals.
     *
     * @return array Template definition.
     */
    private static function template_learning_journey(): array {
        return [
            'key' => 'learning-journey',
            'name' => 'template_learning_journey',
            'description' => 'template_learning_journey_desc',
            'icon' => 'fa-road',
            'theme' => 'clean',
            'sections' => [
                [
                    'sectiontype' => 'hero',
                    'configdata' => json_encode([
                        'name' => 'My learning journey',
                        'title' => 'What I did not know a year ago',
                        'subtitle' => 'A living record of small steps, honest setbacks, and the moments things started to click.',
                        'bg_color' => '#0f766e',
                        'bg_image' => '',
                        'photo_url' => '',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Why I am keeping this page',
                        'body' => '<p>Learning rarely feels like progress in the moment - it feels like confusion, and then, much later, obvious. This page is where I slow down enough to notice what is actually changing. I update it roughly once a month.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'timeline',
                    'configdata' => json_encode([
                        'heading' => 'Milestones so far',
                        'items' => [
                            ['date' => 'February 2025', 'title' => 'Enrolled in the programme', 'description' => 'Walked in nervous about whether I belonged in postgraduate study. Left the induction with a reading list and a sense that I could do this.'],
                            ['date' => 'April 2025', 'title' => 'First assignment submitted', 'description' => 'Got a lower mark than I hoped, along with the most useful feedback I have ever received. Rewrote my notes system that weekend.'],
                            ['date' => 'July 2025', 'title' => 'First conference poster', 'description' => 'Presented early research at the postgraduate symposium. Three people asked good questions I could not answer - now I can.'],
                            ['date' => 'October 2025', 'title' => 'Something clicked', 'description' => 'After months of rereading, the core argument of my topic finally fit together. I wrote 2,000 words in an afternoon.'],
                            ['date' => 'Present', 'title' => 'Working on the thesis proposal', 'description' => 'Narrowing the question. Learning to let go of the interesting tangents.'],
                        ],
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'divider',
                    'configdata' => json_encode(['style' => 'solid']),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Reflections',
                        'body' => '<p><strong>What surprised me:</strong> how much of learning is about relationships - with classmates, with supervisors, with the books I return to.</p><p><strong>What was hard:</strong> admitting when I did not understand something. I used to nod along. I am slowly learning to ask.</p><p><strong>What I would tell past me:</strong> read less, reread more. Take notes by hand when the idea is hard.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'completions',
                    'configdata' => json_encode(['heading' => 'Courses completed', 'show' => true]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'badges',
                    'configdata' => json_encode(['heading' => 'Badges earned along the way', 'show' => true]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Goals & next steps',
                        'body' => '<ul><li>Submit the thesis proposal by end of term.</li><li>Run a reading group on narrative theology next semester.</li><li>Write one short public-facing post every month.</li><li>Long-term: apply for a doctoral programme in 2027.</li></ul>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'cta',
                    'configdata' => json_encode([
                        'heading' => 'Learning in public',
                        'body' => 'If you are on a similar journey, I would love to swap notes. Reading recommendations especially welcome.',
                        'button_text' => 'Say hello',
                        'button_url' => '#',
                        'bg_color' => '#0f766e',
                    ]),
                    'content' => '',
                ],
            ],
        ];
    }

    /**
     * Professional Profile template.
     *
     * Hero, summary, experience timeline, skills, recommendations, certifications, social.
     *
     * @return array Template definition.
     */
    private static function template_professional_profile(): array {
        return [
            'key' => 'professional-profile',
            'name' => 'template_professional_profile',
            'description' => 'template_professional_profile_desc',
            'icon' => 'fa-briefcase',
            'theme' => 'corporate',
            'sections' => [
                [
                    'sectiontype' => 'hero',
                    'configdata' => json_encode([
                        'name' => 'Samuel Rivera',
                        'title' => 'Programme Manager, EdTech & Learning Design',
                        'subtitle' => 'Helping institutions ship learning experiences students actually finish.',
                        'bg_color' => '#0f172a',
                        'bg_image' => '',
                        'photo_url' => (new \moodle_url('/local/byblos/pix/avatars/profile1.jpeg'))->out(false),
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Professional summary',
                        'body' => '<p>Programme manager with eight years of experience leading cross-functional teams at the intersection of education, technology, and operations. I translate ambiguous strategy into shippable plans, with a track record of delivering complex initiatives on time and under budget. Strongest where learning design, stakeholder management, and delivery meet.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'timeline',
                    'configdata' => json_encode([
                        'heading' => 'Experience',
                        'items' => [
                            ['date' => '2023 - Present', 'title' => 'Senior Programme Manager, Northbridge Learning', 'description' => 'Lead a 12-person programme delivering the institution\'s flagship online postgraduate portfolio. Grew enrolment 34% in two years; reduced time-to-launch for new courses from 9 months to 5.'],
                            ['date' => '2020 - 2023', 'title' => 'Programme Manager, Northbridge Learning', 'description' => 'Owned delivery of four degree programmes. Introduced a shared intake process that cut cross-team rework by an estimated 40%.'],
                            ['date' => '2017 - 2020', 'title' => 'Learning Experience Designer, Open College Group', 'description' => 'Designed and shipped 22 courses across three faculties. Built the template library still in use today.'],
                            ['date' => '2015 - 2017', 'title' => 'Instructional Designer (contract), various', 'description' => 'Contract work for three universities and two NGOs.'],
                        ],
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'skills',
                    'configdata' => json_encode([
                        'heading' => 'Core skills',
                        'skills' => [
                            ['name' => 'Programme & project management', 'level' => 92],
                            ['name' => 'Stakeholder & vendor management', 'level' => 88],
                            ['name' => 'Learning experience design', 'level' => 85],
                            ['name' => 'Data-informed decision-making', 'level' => 78],
                            ['name' => 'Facilitation & coaching', 'level' => 82],
                            ['name' => 'Moodle, Articulate, Figma', 'level' => 80],
                        ],
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'divider',
                    'configdata' => json_encode(['style' => 'solid']),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'What colleagues say',
                        'body' => '<blockquote class="blockquote"><p>"Samuel is the rare programme manager who is equally trusted by engineering, faculty, and the executive team. He brings the room with him."</p><footer class="blockquote-footer">Priya Naidoo, Director of Digital Learning, Northbridge</footer></blockquote><blockquote class="blockquote"><p>"The most organised, least bureaucratic person I have ever worked with. Ships things."</p><footer class="blockquote-footer">Tom Weaver, Head of Engineering</footer></blockquote>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'badges',
                    'configdata' => json_encode(['heading' => 'Certifications', 'show' => true]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'cta',
                    'configdata' => json_encode([
                        'heading' => 'Open to new conversations',
                        'body' => 'Currently exploring senior programme roles in learning, edtech, or non-profit delivery. If that sounds like your team, I would love to chat.',
                        'button_text' => 'Contact me',
                        'button_url' => '#',
                        'bg_color' => '#0f172a',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'social',
                    'configdata' => json_encode([
                        'links' => [
                            ['platform' => 'linkedin', 'url' => ''],
                            ['platform' => 'github', 'url' => ''],
                            ['platform' => 'globe', 'url' => ''],
                        ],
                    ]),
                    'content' => '',
                ],
            ],
        ];
    }

    /**
     * Research Portfolio template.
     *
     * Hero, abstract, literature review, methodology, findings, references, appendix.
     *
     * @return array Template definition.
     */
    private static function template_research_portfolio(): array {
        return [
            'key' => 'research-portfolio',
            'name' => 'template_research_portfolio',
            'description' => 'template_research_portfolio_desc',
            'icon' => 'fa-flask',
            'theme' => 'academic',
            'sections' => [
                [
                    'sectiontype' => 'hero',
                    'configdata' => json_encode([
                        'name' => 'Reading alone, together',
                        'title' => 'Communities of practice in online theological education',
                        'subtitle' => 'A mixed-methods study of postgraduate cohorts, 2023 - 2025.',
                        'bg_color' => '#14253f',
                        'bg_image' => '',
                        'photo_url' => '',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Abstract',
                        'body' => '<p>Online postgraduate theology programmes promise flexibility but often deliver isolation. This study examined how four cohorts (n = 187) formed - or failed to form - into communities of practice across a two-year programme. Using cohort-level analytics, semi-structured interviews (n = 24), and a discourse analysis of forum activity, we identified three distinct trajectories of community formation and the specific design interventions that supported each. Results suggest that small, sustained peer-review practices are more predictive of perceived belonging than any single platform feature. Implications for learning design and institutional policy are discussed.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Research question & hypotheses',
                        'body' => '<p><strong>RQ.</strong> What conditions enable genuine communities of practice to form in fully online postgraduate theology programmes?</p><ul><li><strong>H1.</strong> Sustained peer-review routines correlate more strongly with perceived belonging than total time-on-platform.</li><li><strong>H2.</strong> Cohort size has a non-linear effect on community formation, with an upper threshold around 35 learners.</li><li><strong>H3.</strong> Instructor presence has diminishing returns once baseline responsiveness is established.</li></ul>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Literature review',
                        'body' => '<p>Building on Wenger\'s (1998) original framing of communities of practice and Garrison et al.\'s (2000) Community of Inquiry model, recent work in online theological education (Smith &amp; Patel, 2022; Okafor, 2024) has begun to question whether "presence" alone accounts for the thin belonging many students report. This study extends that line by focusing on the <em>practices</em>, not the platforms.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Methodology',
                        'body' => '<p>Mixed-methods, sequential explanatory design. <strong>Phase 1:</strong> cohort-level LMS analytics (engagement, post frequency, reply depth) across four cohorts over 18 months. <strong>Phase 2:</strong> 24 semi-structured interviews with a stratified sample of learners, transcribed and coded in NVivo using a constructivist grounded theory approach. <strong>Phase 3:</strong> targeted discourse analysis of peer-review forum threads (n = 312). Ethics approval granted by the institutional review board (ref. 2023/184).</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'divider',
                    'configdata' => json_encode(['style' => 'solid']),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text_image',
                    'configdata' => json_encode([
                        'heading' => 'Key findings',
                        'body' => '<p><strong>1.</strong> Cohorts with weekly peer-review practices reported belonging scores 2.4x higher than cohorts with equivalent instructor contact but no peer routines.</p><p><strong>2.</strong> Community formation followed one of three trajectories - "early bonding", "late emergence", or "fragmented". The trajectory was largely set within the first four weeks.</p><p><strong>3.</strong> Above 35 learners, cohorts tended to fragment into stable sub-groups of 4-7, regardless of facilitation style.</p>',
                        'image_url' => '',
                        'image_alt' => 'Chart showing belonging scores across the three cohort trajectories',
                        'reversed' => true,
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Discussion & implications',
                        'body' => '<p>The findings suggest that learning designers should prioritise small, repeatable peer-review routines from week one, treat cohort size as a design variable rather than an administrative one, and resist the assumption that more instructor presence is always better. For institutions, the results argue for sub-cohort structures above a threshold of roughly 35.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'References',
                        'body' => '<ol><li>Garrison, D. R., Anderson, T., &amp; Archer, W. (2000). <em>Critical inquiry in a text-based environment</em>. The Internet and Higher Education, 2(2-3), 87-105.</li><li>Okafor, C. (2024). <em>Belonging at a distance</em>. Journal of Theological Education Online, 8(1), 1-22.</li><li>Smith, R., &amp; Patel, A. (2022). <em>Presence and practice</em>. Teaching Theology and Religion, 25(3), 201-218.</li><li>Wenger, E. (1998). <em>Communities of practice: Learning, meaning, and identity</em>. Cambridge University Press.</li></ol>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'cta',
                    'configdata' => json_encode([
                        'heading' => 'Appendices & data',
                        'body' => 'Interview protocols, coding book, and anonymised analytics summaries are available on request.',
                        'button_text' => 'Request materials',
                        'button_url' => '#',
                        'bg_color' => '#14253f',
                    ]),
                    'content' => '',
                ],
            ],
        ];
    }

    /**
     * Simple Page template.
     *
     * Minimal starting point with text, text+image, and CTA.
     *
     * @return array Template definition.
     */
    private static function template_simple_page(): array {
        return [
            'key' => 'simple-page',
            'name' => 'template_simple_page',
            'description' => 'template_simple_page_desc',
            'icon' => 'fa-file-text-o',
            'theme' => 'clean',
            'sections' => [
                [
                    'sectiontype' => 'hero',
                    'configdata' => json_encode([
                        'name' => 'A quiet page',
                        'title' => 'Say one thing, clearly.',
                        'subtitle' => 'A minimal starting point. Keep what you need, remove the rest.',
                        'bg_color' => '#1f2937',
                        'bg_image' => '',
                        'photo_url' => '',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'Introduction',
                        'body' => '<p>This template is a blank canvas with good manners. It gives you a hero, a couple of content blocks, and a call to action - enough to publish something useful in an afternoon. Replace this text with whatever you want to say first: a welcome, a summary, a single good idea.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text_image',
                    'configdata' => json_encode([
                        'heading' => 'Tell the story',
                        'body' => '<p>Use this block to pair a short piece of writing with a visual - a photo, a screenshot, a diagram. Readers scan: an image earns you a second look, and the text earns the third. Flip the layout in the editor if you want the image on the other side.</p>',
                        'image_url' => '',
                        'image_alt' => 'A supporting image',
                        'reversed' => false,
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'divider',
                    'configdata' => json_encode(['style' => 'solid']),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'text',
                    'configdata' => json_encode([
                        'heading' => 'One more thought',
                        'body' => '<p>Add as many sections as you need, but not more. The strongest pages leave something out. Use the editor to try other block types - gallery, timeline, skills - when you are ready.</p>',
                    ]),
                    'content' => '',
                ],
                [
                    'sectiontype' => 'cta',
                    'configdata' => json_encode([
                        'heading' => 'Ready when you are',
                        'body' => 'When the page is ready to share, publish it and send the link.',
                        'button_text' => 'Get in touch',
                        'button_url' => '#',
                        'bg_color' => '#1f2937',
                    ]),
                    'content' => '',
                ],
            ],
        ];
    }
}
