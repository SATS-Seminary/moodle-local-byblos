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
 * Template gallery — choose a template and create a new portfolio page.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\template_manager;

require_login();
$context = context_system::instance();
require_capability('local/byblos:createpage', $context);

$PAGE->set_url(new moodle_url('/local/byblos/newpage.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('newpage', 'local_byblos'));
$PAGE->set_heading(get_string('newpage', 'local_byblos'));
$PAGE->add_body_class('byblos-body');
$PAGE->add_body_class('byblos-body-newpage');

// Handle POST: create page from selected template.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    // Template keys use hyphens (e.g. 'personal-portfolio'), so PARAM_RAW with validation.
    $templatekey = required_param('template', PARAM_RAW);
    $templatekey = clean_param($templatekey, PARAM_SAFEDIR);
    $title = optional_param('title', '', PARAM_TEXT);

    $pageid = template_manager::create_page_from_template($USER->id, $templatekey, $title);

    // Redirect to page view (editor will be created in a later wave).
    $editurl = new moodle_url('/local/byblos/page.php', ['id' => $pageid]);
    redirect($editurl);
}

// GET: render template gallery.
$templates = template_manager::get_all();

$templatedata = [];
foreach ($templates as $tpl) {
    $sectioncount = count($tpl['sections']);
    $templatedata[] = [
        'key'               => $tpl['key'],
        'name'              => get_string($tpl['name'], 'local_byblos'),
        'description'       => get_string($tpl['description'], 'local_byblos'),
        'icon'              => $tpl['icon'],
        'sectioncount'      => $sectioncount,
        'sectionlabel'      => ($sectioncount === 1)
            ? get_string('section_singular', 'local_byblos')
            : get_string('sections', 'local_byblos', $sectioncount),
        'recommended_theme' => $tpl['theme'],
        'has_recommended'   => !empty($tpl['theme']),
        'is_blank'          => ($tpl['key'] === 'simple-page'),
        'previewurl'        => (new moodle_url(
            '/local/byblos/template_preview.php',
            ['key' => $tpl['key']]
        ))->out(false),
    ];
}

$data = [
    'templates'  => $templatedata,
    'actionurl'  => (new moodle_url('/local/byblos/newpage.php'))->out(false),
    'sesskey'    => sesskey(),
    'dashurl'    => (new moodle_url('/local/byblos/view.php'))->out(false),
];

$PAGE->requires->js_call_amd('local_byblos/newpage', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/newpage', $data);
echo $OUTPUT->footer();
