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
 * Seed a full end-to-end test case for Byblos assessment + peer review.
 *
 * Creates (idempotently):
 *  - 4 test students (byblos_student_1..4) and enrols them in a test course
 *  - 4 varied portfolios (academic, creative, journal, project themes)
 *  - An assignment with assignsubmission_byblos enabled, peer review (random N=2),
 *    an advisory checklist, and a rubric under Advanced Grading
 *  - A submitted byblos_submission per student
 *  - Random peer assignments across the 4 students (each reviews 2 peers)
 *
 * Usage: php local/byblos/cli/seed_test_data.php
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

global $CFG, $DB, $USER;

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Idempotent line logger.
 *
 * @param string $msg Message text.
 */
function seed_log(string $msg): void {
    echo '[seed] ' . $msg . PHP_EOL;
}

/**
 * Find or create a manual-auth user.
 *
 * @param string $username
 * @param string $firstname
 * @param string $lastname
 * @param string $email
 * @return int userid
 */
function seed_user(string $username, string $firstname, string $lastname, string $email): int {
    global $DB, $CFG;

    $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if ($existing) {
        seed_log("user '$username' exists (id={$existing->id})");
        return (int) $existing->id;
    }

    $user = (object) [
        'auth'         => 'manual',
        'confirmed'    => 1,
        'mnethostid'   => $CFG->mnet_localhost_id,
        'username'     => $username,
        'password'     => 'Byblos123!',
        'firstname'    => $firstname,
        'lastname'     => $lastname,
        'email'        => $email,
        'city'         => 'Testville',
        'country'      => 'ZA',
        'lang'         => 'en',
        'timezone'     => '99',
    ];
    $id = user_create_user($user, true, false);
    seed_log("created user '$username' id={$id}");
    return $id;
}

/**
 * Find or create a course.
 *
 * @param string $shortname
 * @param string $fullname
 * @return \stdClass Course record.
 */
function seed_course(string $shortname, string $fullname): \stdClass {
    global $DB;

    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if ($course) {
        seed_log("course '$shortname' exists (id={$course->id})");
        return $course;
    }
    $category = $DB->get_record('course_categories', [], '*', IGNORE_MULTIPLE);
    $data = (object) [
        'shortname' => $shortname,
        'fullname'  => $fullname,
        'category'  => $category ? $category->id : 1,
        'format'    => 'topics',
        'numsections' => 3,
        'visible'   => 1,
        'startdate' => time(),
    ];
    $course = create_course($data);
    seed_log("created course '$shortname' id={$course->id}");
    return $course;
}

/**
 * Enrol a user in a course with a given role shortname.
 *
 * @param int    $userid
 * @param int    $courseid
 * @param string $roleshortname
 * @return void
 */
function seed_enrol(int $userid, int $courseid, string $roleshortname): void {
    global $DB;

    $role = $DB->get_record('role', ['shortname' => $roleshortname], '*', MUST_EXIST);
    $enrol = enrol_get_plugin('manual');
    $instances = enrol_get_instances($courseid, true);
    $manualinstance = null;
    foreach ($instances as $instance) {
        if ($instance->enrol === 'manual') {
            $manualinstance = $instance;
            break;
        }
    }
    if (!$manualinstance) {
        $course = get_course($courseid);
        $manualinstance = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $courseid]);
        if (!$manualinstance) {
            $enrol->add_default_instance($course);
            $manualinstance = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $courseid]);
        }
    }

    // Check if already enrolled as this role.
    $ctx = \context_course::instance($courseid);
    if (user_has_role_assignment($userid, $role->id, $ctx->id)) {
        return;
    }

    $enrol->enrol_user($manualinstance, $userid, $role->id);
    seed_log("enrolled user $userid into course $courseid as $roleshortname");
}

/**
 * Create a portfolio page with a handful of sections for a given student.
 *
 * Pages are identified by (userid, title) and reused if already present.
 *
 * @param int    $userid
 * @param string $title
 * @param string $description
 * @param string $themekey
 * @param array  $sections Array of ['sectiontype' => ..., 'configdata' => [...], 'content' => ''].
 * @return int pageid
 */
function seed_portfolio(
    int $userid,
    string $title,
    string $description,
    string $themekey,
    array $sections
): int {
    global $DB;

    $existing = $DB->get_record('local_byblos_page', ['userid' => $userid, 'title' => $title]);
    if ($existing) {
        seed_log("portfolio '$title' exists for user $userid (id={$existing->id})");
        return (int) $existing->id;
    }

    $pageid = \local_byblos\page::create($userid, $title, $description, 'single', $themekey);
    \local_byblos\page::set_status($pageid, 'published');

    $sortorder = 0;
    foreach ($sections as $sectiondef) {
        \local_byblos\section::create(
            $pageid,
            $sectiondef['sectiontype'],
            $sortorder++,
            isset($sectiondef['configdata']) ? json_encode($sectiondef['configdata']) : null,
            $sectiondef['content'] ?? ''
        );
    }
    seed_log("created portfolio '$title' id={$pageid} for user $userid with " . count($sections) . ' sections');
    return $pageid;
}

/**
 * Create (or return) the byblos test course assignment with peer review enabled.
 *
 * @param \stdClass $course
 * @return \stdClass Assign instance record.
 */
function seed_assignment(\stdClass $course): \stdClass {
    global $DB, $CFG;

    $existing = $DB->get_record('assign', ['course' => $course->id, 'name' => 'Portfolio peer review']);
    if ($existing) {
        seed_log("assignment 'Portfolio peer review' exists (id={$existing->id})");
        return $existing;
    }

    // Look up the module record id for 'assign'.
    $module = $DB->get_record('modules', ['name' => 'assign'], '*', MUST_EXIST);

    // Create via create_module() — requires a course-module-style stdClass with all
    // assign fields plus course, module, visible, section, etc.
    $data = (object) [
        'course'             => $course->id,
        'modulename'         => 'assign',
        'module'             => $module->id,
        'name'               => 'Portfolio peer review',
        'introeditor'        => [
            'text'   => '<p>Submit your portfolio for peer review. Your classmates will leave inline '
                        . 'comments, and your teacher will grade using the rubric.</p>',
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ],
        'section'            => 0,
        'visible'            => 1,
        'visibleoncoursepage' => 1,
        'duedate'            => time() + 14 * DAYSECS,
        'allowsubmissionsfromdate' => time() - DAYSECS,
        'gradingduedate'     => 0,
        'cutoffdate'         => 0,
        'alwaysshowdescription' => 1,
        'submissiondrafts'   => 0,
        'requiresubmissionstatement' => 0,
        'sendnotifications'  => 0,
        'sendlatenotifications' => 0,
        'sendstudentnotifications' => 1,
        'grade'              => 100,
        'gradecat'           => 0,
        'teamsubmission'     => 0,
        'requireallteammemberssubmit' => 0,
        'teamsubmissiongroupingid' => 0,
        'blindmarking'       => 0,
        'hidegrader'         => 0,
        'revealidentities'   => 0,
        'attemptreopenmethod' => 'none',
        'maxattempts'        => -1,
        'markingworkflow'    => 0,
        'markingallocation'  => 0,
        'markingworkflowstate' => '',
        'completion'         => 0,
        'completionview'     => 0,
        'completionexpected' => 0,

        // Submission plugin enables.
        'assignsubmission_byblos_enabled' => 1,
        'assignsubmission_byblos_allowedunit' => 'page',
        'assignsubmission_byblos_snapshotmode' => 'snapshot_on_submit',
        'assignsubmission_byblos_peerenabled' => 1,
        'assignsubmission_byblos_peermode' => 'random',
        'assignsubmission_byblos_peercount' => 2,
        'assignsubmission_byblos_peervisibility' => 'after_submit',
        'assignsubmission_byblos_peerscoremode' => 'numeric',
        'assignsubmission_byblos_checklist' => implode("\n", [
            'Have you included a reflection on your learning?',
            'Have you linked to at least 3 artefacts or pieces of evidence?',
            'Does the portfolio have a clear title and introduction?',
            'Have you proofread for spelling and grammar?',
        ]),
        'assignsubmission_onlinetext_enabled' => 0,
        'assignsubmission_onlinetext_wordlimit' => 0,
        'assignsubmission_onlinetext_wordlimit_enabled' => 0,
        'assignsubmission_file_enabled' => 0,
        'assignsubmission_file_maxfiles' => 0,
        'assignsubmission_file_maxsizebytes' => 0,
        'assignsubmission_file_filetypes' => '',

        // Feedback plugin enables.
        'assignfeedback_comments_enabled' => 1,
        'assignfeedback_comments_commentinline' => 0,
        'assignfeedback_file_enabled' => 0,
    ];

    $cm = create_module($data);
    $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
    seed_log("created assignment 'Portfolio peer review' id={$assign->id} (cm={$cm->coursemodule})");
    return $assign;
}

/**
 * Build the 4-criterion rubric definition expected by gradingform_rubric_controller.
 * Each criterion and each level inside gets an explicit sortorder as the DB
 * schema requires.
 *
 * @return array
 */
function seed_rubric_criteria(): array {
    $blueprint = [
        'Clarity of ideas' => [
            ['Unclear or confusing.', 0],
            ['Ideas emerging but inconsistent.', 2],
            ['Mostly clear with some lapses.', 4],
            ['Consistently clear and well-expressed.', 6],
        ],
        'Evidence and examples' => [
            ['Little or no evidence supplied.', 0],
            ['Some evidence, weakly linked.', 2],
            ['Solid evidence supporting most claims.', 4],
            ['Rich, varied evidence throughout.', 6],
        ],
        'Reflection and learning' => [
            ['No reflection visible.', 0],
            ['Surface-level reflection.', 2],
            ['Thoughtful reflection on key experiences.', 4],
            ['Deep, critical reflection throughout.', 6],
        ],
        'Presentation and structure' => [
            ['Disorganised, hard to follow.', 0],
            ['Basic structure, uneven polish.', 2],
            ['Well-structured and readable.', 4],
            ['Polished, professional presentation.', 6],
        ],
    ];

    $criteria = [];
    $csort = 0;
    $cnum = 0;
    foreach ($blueprint as $description => $leveldefs) {
        $cnum++;
        $levels = [];
        $lsort = 0;
        $lnum = 0;
        foreach ($leveldefs as [$def, $score]) {
            $lnum++;
            $levels['NEWID' . $cnum . $lnum] = [
                'definition'       => $def,
                'definitionformat' => FORMAT_PLAIN,
                'score'            => $score,
                'sortorder'        => $lsort++,
            ];
        }
        $criteria['NEWID' . $cnum] = [
            'description'       => $description,
            'descriptionformat' => FORMAT_PLAIN,
            'sortorder'         => $csort++,
            'levels'            => $levels,
        ];
    }
    return $criteria;
}

/**
 * Attach a 4-criterion rubric to the given assignment under Advanced Grading.
 *
 * @param \stdClass $assign Assign record.
 * @return void
 */
function seed_rubric(\stdClass $assign): void {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/grade/grading/form/rubric/lib.php');

    $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course, false, MUST_EXIST);
    $context = \context_module::instance($cm->id);

    $manager = get_grading_manager($context, 'mod_assign', 'submissions');

    // Make rubric the active grading method for this assign area.
    if ($manager->get_active_method() !== 'rubric') {
        $manager->set_active_method('rubric');
    }
    $controller = $manager->get_controller('rubric');

    // If a previous run left a definition behind, check it has criteria.
    // An empty definition (from a crashed earlier attempt) must be wiped before
    // we can re-populate — update_definition() can't add criteria to a row that
    // believes it is already defined.
    if ($controller->is_form_defined()) {
        $def = $controller->get_definition();
        $critcount = $def && isset($def->rubric_criteria) ? count($def->rubric_criteria) : 0;
        if ($critcount > 0) {
            seed_log("rubric already defined on assignment {$assign->id} ({$critcount} criteria)");
            return;
        }
        seed_log("rubric definition exists but is empty — wiping for re-seed");
        $DB->delete_records('grading_definitions', ['id' => $def->id]);
        // Force the controller to forget the stale cached definition.
        $controller = $manager->get_controller('rubric');
    }

    $newdefinition = (object) [
        'name' => 'Portfolio rubric',
        'description_editor' => [
            'text' => '<p>Scores each portfolio against four criteria. Use this to guide your peer comments '
                    . 'and your final grade.</p>',
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ],
        'status' => gradingform_rubric_controller::DEFINITION_STATUS_READY,
        'rubric' => [
            'criteria' => seed_rubric_criteria(),
            'options' => [
                'sortlevelsasc' => 1,
                'lockzeropoints' => 1,
                'showdescriptionteacher' => 1,
                'showdescriptionstudent' => 1,
                'showscoreteacher' => 1,
                'showscorestudent' => 1,
                'enableremarks' => 1,
                'showremarksstudent' => 1,
            ],
        ],
    ];

    $controller->update_definition($newdefinition);
    seed_log("attached rubric to assignment {$assign->id}");
}

/**
 * Make a user submit a byblos page to the given assignment.
 *
 * Writes both the mod_assign_submission row and the local_byblos_submission row,
 * captures a snapshot, and wires peer_assignment.submissionid for any pre-existing rows.
 *
 * @param \stdClass $assign Assign record.
 * @param int       $userid
 * @param int       $pageid
 * @return int local_byblos_submission id
 */
function seed_submit(\stdClass $assign, int $userid, int $pageid): int {
    global $DB;

    // Create or find the assign_submission row.
    $existing = $DB->get_record('assign_submission', [
        'assignment' => $assign->id,
        'userid'     => $userid,
        'groupid'    => 0,
    ]);
    if ($existing) {
        $assignsubmissionid = (int) $existing->id;
        // Keep status as-is; make sure it's at least 'submitted'.
        if ($existing->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            $existing->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
            $existing->timemodified = time();
            $DB->update_record('assign_submission', $existing);
        }
    } else {
        $record = (object) [
            'assignment'   => $assign->id,
            'userid'       => $userid,
            'timecreated'  => time(),
            'timemodified' => time(),
            'status'       => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            'groupid'      => 0,
            'attemptnumber' => 0,
            'latest'       => 1,
        ];
        $assignsubmissionid = (int) $DB->insert_record('assign_submission', $record);
    }

    $byblossubid = \local_byblos\submission::upsert(
        (int) $assign->id,
        $assignsubmissionid,
        $userid,
        $pageid,
        null,
        'snapshot_on_submit'
    );

    \local_byblos\submission::capture_snapshot_if_needed($byblossubid, false);
    \local_byblos\peer::attach_submission((int) $assign->id, $userid, $byblossubid);

    seed_log("submitted page $pageid for user $userid (byblos_submission id=$byblossubid)");
    return $byblossubid;
}

// ============================================================================
// Run.
// ============================================================================

// Avoid CLI-auth prompts — execute as admin.
$admin = get_admin();
if (!$admin) {
    throw new \moodle_exception('No admin user on this site.');
}
\core\session\manager::set_user($admin);

seed_log('--- Byblos test-data seed starting ---');

// 1. Users.
$students = [
    ['byblos_student_1', 'Amara',  'Okafor',   'amara@example.test'],
    ['byblos_student_2', 'Ben',    'Schneider', 'ben@example.test'],
    ['byblos_student_3', 'Chidi',  'Anyanwu',  'chidi@example.test'],
    ['byblos_student_4', 'Dana',   'Mbeki',    'dana@example.test'],
];
$studentids = [];
foreach ($students as [$uname, $fn, $ln, $email]) {
    $studentids[$uname] = seed_user($uname, $fn, $ln, $email);
}

// 2. Course.
$course = seed_course('BYB101', 'Byblos Test — Intro to Portfolios');

// 3. Enrolments.
foreach ($studentids as $userid) {
    seed_enrol($userid, $course->id, 'student');
}
// Also enrol the admin as an editing teacher so they can manage peer reviewers.
seed_enrol($admin->id, $course->id, 'editingteacher');

// 4. Four varied portfolios.
$amaraid = seed_portfolio(
    $studentids['byblos_student_1'],
    'My Academic Journey',
    'A year in review of my postgraduate coursework.',
    'academic',
    [
        ['sectiontype' => 'hero', 'configdata' => [
            'name' => 'Amara Okafor',
            'title' => 'Academic portfolio',
            'subtitle' => 'MA Research · 2026',
            'bg_color' => '#1a365d',
        ]],
        ['sectiontype' => 'text', 'configdata' => [
            'heading' => 'About this portfolio',
            'body' => '<p>I present selected coursework from my MA in African Studies, with reflections on '
                    . 'my methodology and learning throughout the year.</p>',
        ]],
        ['sectiontype' => 'timeline', 'configdata' => [
            'heading' => 'Milestones',
            'items' => [
                ['date' => 'Feb 2026', 'title' => 'Literature review approved', 'description' => 'Supervisor signed off on scope.'],
                ['date' => 'Jun 2026', 'title' => 'Fieldwork completed', 'description' => '14 semi-structured interviews collected.'],
                ['date' => 'Oct 2026', 'title' => 'Draft thesis submitted', 'description' => 'Full draft sent to advisory committee.'],
            ],
        ]],
        ['sectiontype' => 'text', 'configdata' => [
            'heading' => 'Reflection',
            'body' => '<p>My greatest growth this year has been in ethnographic interviewing. Early attempts '
                    . 'were over-structured; I learned to follow the informant. This shift revealed themes '
                    . 'I would otherwise have missed.</p>',
        ]],
    ]
);

$benid = seed_portfolio(
    $studentids['byblos_student_2'],
    'Design & Practice',
    'A showcase of my creative projects from this semester.',
    'creative',
    [
        ['sectiontype' => 'hero', 'configdata' => [
            'name' => 'Ben Schneider',
            'title' => 'Designer / Maker',
            'subtitle' => 'Portfolio 2026',
            'bg_color' => '#7c3aed',
        ]],
        ['sectiontype' => 'text', 'configdata' => [
            'heading' => 'Statement',
            'body' => '<p>I work at the intersection of code and visual design. This portfolio gathers projects '
                    . 'where those two practices overlap.</p>',
        ]],
        ['sectiontype' => 'gallery', 'configdata' => [
            'columns' => 3,
            'items' => [
                ['title' => 'Typographic study I', 'description' => 'Variable font exploration.', 'image_url' => ''],
                ['title' => 'Generative grid', 'description' => 'P5.js animation.', 'image_url' => ''],
                ['title' => 'Book cover', 'description' => 'Redesign of a classic novel.', 'image_url' => ''],
            ],
        ]],
        ['sectiontype' => 'cta', 'configdata' => [
            'heading' => 'Let\'s talk',
            'body' => 'Reach out if you\'d like to collaborate.',
            'button_text' => 'Contact',
            'button_url' => '#',
            'bg_color' => '#7c3aed',
        ]],
    ]
);

$chidiid = seed_portfolio(
    $studentids['byblos_student_3'],
    'Learning Journal',
    'Weekly reflections across the term.',
    'clean',
    [
        ['sectiontype' => 'hero', 'configdata' => [
            'name' => 'Chidi Anyanwu',
            'title' => 'Learning journal',
            'subtitle' => 'Spring term 2026',
            'bg_color' => '#16a085',
        ]],
        ['sectiontype' => 'text', 'configdata' => [
            'heading' => 'Week 3 — Getting stuck',
            'body' => '<p>This week I hit a wall with the statistics module. After two false starts, I found '
                    . 'that drawing the sample spaces on paper first helped enormously.</p>',
        ]],
        ['sectiontype' => 'text', 'configdata' => [
            'heading' => 'Week 6 — A breakthrough',
            'body' => '<p>Today it clicked why we use degrees of freedom the way we do. Explaining it out loud '
                    . 'to a classmate sealed the understanding.</p>',
        ]],
        ['sectiontype' => 'text', 'configdata' => [
            'heading' => 'Week 10 — What\'s next',
            'body' => '<p>I want to keep the journal habit into next term. Five minutes a day is genuinely '
                    . 'worth it.</p>',
        ]],
    ]
);

$danaid = seed_portfolio(
    $studentids['byblos_student_4'],
    'Capstone Project: Smart Bin',
    'Full report on my engineering capstone.',
    'modern-dark',
    [
        ['sectiontype' => 'hero', 'configdata' => [
            'name' => 'Dana Mbeki',
            'title' => 'Smart Bin',
            'subtitle' => 'Engineering capstone project',
            'bg_color' => '#1e1e2e',
        ]],
        ['sectiontype' => 'text', 'configdata' => [
            'heading' => 'Abstract',
            'body' => '<p>This project presents a low-cost connected waste bin that uses ultrasonic sensing '
                    . 'to report fill level over LoRaWAN. Field-tested over 6 weeks on campus.</p>',
        ]],
        ['sectiontype' => 'text_image', 'configdata' => [
            'heading' => 'Hardware',
            'body' => '<p>ESP32 microcontroller, HC-SR04 ultrasonic sensor, TTGO LoRa module. Total BOM: $34.</p>',
            'image_url' => '',
            'image_alt' => 'Exploded view',
            'reversed' => false,
        ]],
        ['sectiontype' => 'text', 'configdata' => [
            'heading' => 'Results',
            'body' => '<p>Across 42 days, bin fill was reported within ±3cm of a manual measurement in 97% '
                    . 'of readings. Power draw averaged 11mA, projecting a 10-month battery life on 2x 18650.</p>',
        ]],
        ['sectiontype' => 'text', 'configdata' => [
            'heading' => 'Reflection',
            'body' => '<p>The hardest part was reliable LoRaWAN handoff — I underestimated RF shadowing inside '
                    . 'the metal bin. Future work: dual-antenna diversity and over-the-air firmware updates.</p>',
        ]],
    ]
);

$pagemap = [
    $studentids['byblos_student_1'] => $amaraid,
    $studentids['byblos_student_2'] => $benid,
    $studentids['byblos_student_3'] => $chidiid,
    $studentids['byblos_student_4'] => $danaid,
];

// 5. Assignment.
$assign = seed_assignment($course);

// 6. Rubric on the assignment.
seed_rubric($assign);

// 7. Each student submits their portfolio.
foreach ($pagemap as $userid => $pageid) {
    seed_submit($assign, $userid, $pageid);
}

// 8. Random peer-reviewer assignment (N=2 per student).
$candidates = array_values($studentids);
$written = \local_byblos\peer::assign_random((int) $assign->id, $candidates, 2);
seed_log("peer assignments written: $written");

seed_log('--- Seed complete ---');
seed_log('');
seed_log('Log in as any of:');
foreach ($students as [$uname]) {
    seed_log("  $uname / Byblos123!");
}
seed_log('');
seed_log("Assignment: '{$assign->name}' in course '{$course->shortname}' (id={$assign->id})");
seed_log('');
seed_log('Try: log in as byblos_student_1, open Dashboard → Reviews to do,');
seed_log('then open one of the assigned reviews. Leave comments, submit the review.');
