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
 * Course portfolios view — shows all pages tagged with a course.
 *
 * Teachers (with viewshared) see all students' pages.
 * Students see only their own pages and pages shared with them.
 *
 * URL: /local/byblos/courseportfolios.php?courseid=X
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\page;
use local_byblos\share;

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$coursecontext = context_course::instance($courseid);

require_login($course);
require_capability('local/byblos:use', context_system::instance());

$pageurl = new moodle_url('/local/byblos/courseportfolios.php', ['courseid' => $courseid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($coursecontext);
$PAGE->set_title(get_string('nav_course_portfolios', 'local_byblos') . ': ' . $course->fullname);
$PAGE->set_heading(get_string('nav_course_portfolios', 'local_byblos'));

// Get all pages tagged with this course.
$allpages = page::get_pages_for_course($courseid);

$canviewall = has_capability('local/byblos:viewshared', context_system::instance());

// Filter pages based on access.
$visiblepages = [];
foreach ($allpages as $p) {
    if ($canviewall) {
        // Teacher/manager sees all pages.
        $visiblepages[] = $p;
    } else if ((int) $p->userid === (int) $USER->id) {
        // Student sees their own pages.
        $visiblepages[] = $p;
    } else if (share::can_view_page($USER->id, $p->id)) {
        // Student sees pages shared with them.
        $visiblepages[] = $p;
    }
}

// Build template data.
$pagecards = [];
foreach ($visiblepages as $p) {
    $owner = $DB->get_record('user', ['id' => $p->userid], 'id, firstname, lastname');
    $pagecards[] = [
        'id'          => $p->id,
        'title'       => $p->title,
        'description' => shorten_text(strip_tags($p->description ?? ''), 120),
        'owner'       => $owner ? fullname($owner) : '',
        'isown'       => ((int) $p->userid === (int) $USER->id),
        'status'      => $p->status,
        'viewurl'     => (new moodle_url('/local/byblos/page.php', ['id' => $p->id]))->out(false),
        'timecreated' => userdate($p->timecreated),
    ];
}

$templatedata = [
    'coursename' => $course->fullname,
    'courseid'   => $courseid,
    'pages'      => $pagecards,
    'haspages'   => !empty($pagecards),
    'noresults'  => empty($pagecards),
    'canviewall' => $canviewall,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/course_portfolios', $templatedata);
echo $OUTPUT->footer();
