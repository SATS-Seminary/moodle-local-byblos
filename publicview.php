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
 * Public view of a shared portfolio page or collection.
 *
 * This page does NOT require authentication. Access is granted via a
 * secret token in the URL.
 *
 * URL: /local/byblos/publicview.php?token=XXXX
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php'); // @codingStandardsIgnoreLine Public token-authenticated endpoint; auth via share token below.

use local_byblos\share;
use local_byblos\page;
use local_byblos\collection;
use local_byblos\section;

$token = required_param('token', PARAM_ALPHANUM);

// Validate public sharing is enabled.
if (!get_config('local_byblos', 'allowpublic')) {
    throw new moodle_exception('error_public_sharing_disabled', 'local_byblos');
}

// Resolve the token.
$sharerecord = share::get_by_token($token);
if (!$sharerecord || $sharerecord->sharetype !== 'public') {
    throw new moodle_exception('error_page_not_found', 'local_byblos');
}

$context = context_system::instance();
$pageurl = new moodle_url('/local/byblos/publicview.php', ['token' => $token]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded'); // Minimal chrome — no nav, no editing.

// Determine whether this is a page or collection share.
if (!empty($sharerecord->pageid)) {
    $portfoliopage = page::get((int) $sharerecord->pageid);
    if (!$portfoliopage) {
        throw new moodle_exception('error_page_not_found', 'local_byblos');
    }

    $owner = $DB->get_record('user', ['id' => $portfoliopage->userid], 'id, firstname, lastname');

    // Load sections for the page.
    $sections = section::list_for_page($portfoliopage->id);
    $sectiondata = [];
    foreach ($sections as $s) {
        $sectiondata[] = [
            'sectiontype' => $s->sectiontype,
            'content'     => format_text($s->content, FORMAT_HTML),
            'sortorder'   => $s->sortorder,
        ];
    }

    $PAGE->set_title($portfoliopage->title);

    $templatedata = [
        'is_page'       => true,
        'is_collection' => false,
        'title'         => $portfoliopage->title,
        'description'   => format_text($portfoliopage->description ?? '', FORMAT_HTML),
        'owner'         => $owner ? fullname($owner) : '',
        'sections'      => $sectiondata,
        'hassections'   => !empty($sectiondata),
        'timecreated'   => userdate($portfoliopage->timecreated),
    ];
} else if (!empty($sharerecord->collectionid)) {
    $coll = collection::get((int) $sharerecord->collectionid);
    if (!$coll) {
        throw new moodle_exception('error_collection_not_found', 'local_byblos');
    }

    $owner = $DB->get_record('user', ['id' => $coll->userid], 'id, firstname, lastname');

    // Load pages in the collection.
    $collpages = collection::get_pages($coll->id);
    $pagedata = [];
    foreach ($collpages as $cp) {
        $sections = section::list_for_page($cp->id);
        $sectiondata = [];
        foreach ($sections as $s) {
            $sectiondata[] = [
                'sectiontype' => $s->sectiontype,
                'content'     => format_text($s->content, FORMAT_HTML),
                'sortorder'   => $s->sortorder,
            ];
        }
        $pagedata[] = [
            'title'       => $cp->title,
            'description' => format_text($cp->description ?? '', FORMAT_HTML),
            'sections'    => $sectiondata,
            'hassections' => !empty($sectiondata),
        ];
    }

    $PAGE->set_title($coll->title);

    $templatedata = [
        'is_page'       => false,
        'is_collection' => true,
        'title'         => $coll->title,
        'description'   => format_text($coll->description ?? '', FORMAT_HTML),
        'owner'         => $owner ? fullname($owner) : '',
        'pages'         => $pagedata,
        'haspages'      => !empty($pagedata),
        'timecreated'   => userdate($coll->timecreated),
    ];
} else {
    throw new moodle_exception('error_page_not_found', 'local_byblos');
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/public_view', $templatedata);
echo $OUTPUT->footer();
