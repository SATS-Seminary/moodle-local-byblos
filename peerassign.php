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
 * Peer review management page for teachers.
 *
 * URL: /local/byblos/peerassign.php?assignmentid=N
 *
 * Lets a teacher see every submitting student, add/remove peer reviewers
 * manually, trigger a random allocation across submitters, or allocate
 * within Moodle groups.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\peer;
use local_byblos\submission as byblos_submission;

require_login();

$assignmentid = required_param('assignmentid', PARAM_INT);

$assign = $DB->get_record('assign', ['id' => $assignmentid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $assign->course], '*', MUST_EXIST);
$assignctx = context_module::instance($cm->id);

require_capability('mod/assign:grade', $assignctx);

$pageurl = new moodle_url('/local/byblos/peerassign.php', ['assignmentid' => $assignmentid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($assignctx);
$PAGE->set_pagelayout('incourse');
$PAGE->set_course($course);
$PAGE->set_cm($cm);
$PAGE->set_title(get_string('peerassign_title', 'local_byblos'));
$PAGE->set_heading(format_string($course->fullname));

// Collect enrolled users with the 'student' archetype for reviewer/reviewee pools.
$enrolled = get_enrolled_users($assignctx, 'mod/assign:submit', 0, 'u.*', 'u.lastname, u.firstname');

// Handle POST actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    $notice = '';

    if ($action === 'manual') {
        $reviewerid = required_param('reviewerid', PARAM_INT);
        $revieweeid = required_param('revieweeid', PARAM_INT);
        // Attach existing submission if any.
        $existingsub = $DB->get_record('local_byblos_submission', [
            'assignmentid' => $assignmentid,
            'userid'       => $revieweeid,
        ]);
        $subid = $existingsub ? (int) $existingsub->id : null;
        peer::assign($assignmentid, $reviewerid, $revieweeid, $subid);
        $notice = get_string('peerassign_added', 'local_byblos');
    } else if ($action === 'random') {
        $peers = max(1, (int) required_param('peercount', PARAM_INT));
        $submissions = byblos_submission::list_for_assignment($assignmentid);
        $candidates = array_values(array_unique(array_map(
            static fn($s): int => (int) $s->userid,
            $submissions
        )));
        peer::assign_random($assignmentid, $candidates, $peers);
        $notice = get_string('peerassign_added', 'local_byblos');
    } else if ($action === 'group') {
        $groups = groups_get_all_groups($course->id);
        foreach ($groups as $g) {
            $members = array_map('intval', array_keys(groups_get_members($g->id, 'u.id') ?: []));
            if (!empty($members)) {
                peer::assign_group($assignmentid, $members);
            }
        }
        $notice = get_string('peerassign_added', 'local_byblos');
    } else if ($action === 'remove') {
        $rowid = required_param('rowid', PARAM_INT);
        peer::delete($rowid);
        $notice = get_string('peerassign_removed', 'local_byblos');
    }

    redirect($pageurl, $notice, null, \core\output\notification::NOTIFY_SUCCESS);
}

// Build data for the template.
$submissions = byblos_submission::list_for_assignment($assignmentid);
$submap = [];
foreach ($submissions as $s) {
    $submap[(int) $s->userid] = $s;
}

$revieweedata = [];
foreach ($submissions as $s) {
    $user = $DB->get_record('user', ['id' => $s->userid]);
    if (!$user) {
        continue;
    }
    $rows = peer::reviews_of($assignmentid, (int) $s->userid);
    $reviewers = [];
    foreach ($rows as $r) {
        $reviewer = $DB->get_record('user', ['id' => $r->reviewerid]);
        $reviewers[] = [
            'rowid'         => (int) $r->id,
            'reviewername'  => $reviewer ? fullname($reviewer) : '#' . $r->reviewerid,
            'status'        => $r->status,
            'statuslabel'   => get_string('peerstatus_' . $r->status, 'local_byblos'),
            'timeassigned'  => userdate((int) $r->timeassigned),
        ];
    }
    $revieweedata[] = [
        'userid'       => (int) $s->userid,
        'fullname'     => fullname($user),
        'submissionid' => (int) $s->id,
        'reviewurl'    => (new moodle_url(
            '/local/byblos/review.php',
            ['submissionid' => (int) $s->id]
        ))->out(false),
        'reviewers'    => $reviewers,
        'hasreviewers' => !empty($reviewers),
    ];
}

// Reviewer options (all enrolled students).
$revieweropts = [];
foreach ($enrolled as $u) {
    $revieweropts[] = [
        'id'   => (int) $u->id,
        'name' => fullname($u),
    ];
}

// Reviewee options (students who have a byblos submission).
$revieweeopts = [];
foreach ($revieweedata as $r) {
    $revieweeopts[] = [
        'id'   => $r['userid'],
        'name' => $r['fullname'],
    ];
}

$templatedata = [
    'assignmentname' => format_string($assign->name),
    'assignmentid'   => $assignmentid,
    'actionurl'      => $pageurl->out(false),
    'sesskey'        => sesskey(),
    'reviewees'      => $revieweedata,
    'hasreviewees'   => !empty($revieweedata),
    'revieweropts'   => $revieweropts,
    'revieweeopts'   => $revieweeopts,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/peerassign', $templatedata);
echo $OUTPUT->footer();
