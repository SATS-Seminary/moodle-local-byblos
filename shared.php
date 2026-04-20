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
 * Shared-with-me page: lists pages and collections shared with the current user.
 *
 * URL: /local/byblos/shared.php
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\share;

require_login();

$context = context_system::instance();
require_capability('local/byblos:use', $context);

$pageurl = new moodle_url('/local/byblos/shared.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('nav_shared', 'local_byblos'));
$PAGE->set_heading(get_string('nav_shared', 'local_byblos'));

// Get items shared with the current user.
$shared = share::list_shared_with_user($USER->id);

// Build page cards.
$pagecards = [];
foreach ($shared['pages'] as $p) {
    $owner = $DB->get_record('user', ['id' => $p->userid], 'id, firstname, lastname');
    $pagecards[] = [
        'id'          => $p->id,
        'title'       => $p->title,
        'description' => shorten_text(strip_tags($p->description ?? ''), 120),
        'owner'       => $owner ? fullname($owner) : '',
        'status'      => $p->status,
        'viewurl'     => (new moodle_url('/local/byblos/page.php', ['id' => $p->id]))->out(false),
        'timecreated' => userdate($p->timecreated),
    ];
}

// Build collection cards.
$collectioncards = [];
foreach ($shared['collections'] as $c) {
    $owner = $DB->get_record('user', ['id' => $c->userid], 'id, firstname, lastname');
    $collectioncards[] = [
        'id'          => $c->id,
        'title'       => $c->title,
        'description' => shorten_text(strip_tags($c->description ?? ''), 120),
        'owner'       => $owner ? fullname($owner) : '',
        'viewurl'     => (new moodle_url('/local/byblos/collection.php', ['id' => $c->id]))->out(false),
        'timecreated' => userdate($c->timecreated),
    ];
}

$templatedata = [
    'pages'           => $pagecards,
    'haspages'        => !empty($pagecards),
    'collections'     => $collectioncards,
    'hascollections'  => !empty($collectioncards),
    'noresults'       => empty($pagecards) && empty($collectioncards),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/shared_with_me', $templatedata);
echo $OUTPUT->footer();
