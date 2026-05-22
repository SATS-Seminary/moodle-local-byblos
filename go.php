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
 * Announcement turnstile.
 *
 * Course announcements link to a Byblos portfolio page that lives outside any
 * single course (so its URL is stable across terms). A direct link to the page
 * would never touch the course context, so the click wouldn't show up in
 * Course → Reports → Logs. This endpoint bridges that gap:
 *
 *   /local/byblos/go.php?course=<COURSEID>&page=<BYBLOSPAGEID>
 *
 * It authenticates the student against the course, writes one answer_opened
 * event in the course context, then redirects them on to the page.
 *
 * Security:
 *  - The destination is resolved server-side from the integer page id; no URL
 *    is ever taken from the request.
 *  - The resolved URL is asserted to live under $CFG->wwwroot before redirect.
 *    Anything outside that prefix throws — guaranteed no open redirect.
 *  - share::can_view_page() enforces the same visibility rules as page.php so
 *    the turnstile cannot reach a page the viewer wouldn't otherwise see.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\event\answer_opened;
use local_byblos\page;
use local_byblos\share;

$courseid = required_param('course', PARAM_INT);
$pageid   = required_param('page', PARAM_INT);

// Authenticate against the course; non-enrolled students are stopped here and
// neither the event nor the redirect happens.
$course = get_course($courseid);
require_login($course);

$coursecontext = context_course::instance($course->id);
require_capability('local/byblos:use', $coursecontext);

// Resolve the page id server-side. share::can_view_page() honours owner /
// per-user / course / group / collection-fanout share rules, matching page.php.
$pagerecord = page::get($pageid);
if (!$pagerecord || !share::can_view_page((int) $USER->id, $pageid)) {
    throw new moodle_exception('invalidpage', 'local_byblos');
}

$target = new moodle_url('/local/byblos/page.php', ['id' => $pageid]);
$targetstr = $target->out(false);

// Belt-and-braces: the URL we built is rooted at $CFG->wwwroot by construction,
// but assert it anyway so the turnstile remains safe if someone later swaps
// the lookup for one that yields off-site URLs.
if (strpos($targetstr, $CFG->wwwroot) !== 0) {
    throw new moodle_exception('untrustedtarget', 'local_byblos');
}

// Fire one log event in the course context, then bounce.
$event = answer_opened::create([
    'context' => $coursecontext,
    'courseid' => $course->id,
    'other' => ['pageid' => (int) $pageid],
]);
$event->trigger();

redirect($target);
