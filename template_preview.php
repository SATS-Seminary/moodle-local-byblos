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
 * Render a template's placeholder content as a preview page.
 *
 * Shown inside an iframe on the new-page wizard. Uses the embedded page
 * layout so the iframe body contains only the rendered portfolio (no
 * Moodle chrome). Stateless — no DB writes.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\template_manager;
use local_byblos\renderer as byblos_renderer;

require_login();

$key = required_param('key', PARAM_SAFEDIR);

$template = template_manager::get($key);
if ($template === null) {
    throw new moodle_exception('invalidtemplate', 'local_byblos');
}

$context = context_system::instance();
require_capability('local/byblos:use', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/byblos/template_preview.php', ['key' => $key]));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string($template['name'], 'local_byblos'));
$PAGE->set_heading('');
$PAGE->add_body_class('byblos-body');
$PAGE->add_body_class('byblos-body-pageview');
$PAGE->add_body_class('byblos-body-embedded');

// Build fake page + section records from the template definition. No DB writes.
$fakepage = (object) [
    'id'           => 0,
    'userid'       => (int) $USER->id,
    'title'        => get_string($template['name'], 'local_byblos'),
    'description'  => '',
    'layoutkey'    => 'single',
    'themekey'     => $template['theme'] ?? 'clean',
    'status'       => 'draft',
    'timecreated'  => time(),
    'timemodified' => time(),
];

$fakesections = [];
$sortorder = 0;
foreach ($template['sections'] as $sectiondef) {
    $fakesections[] = (object) [
        'id'           => -1 * ($sortorder + 1), // Negative — never collides with real section IDs.
        'pageid'       => 0,
        'sectiontype'  => $sectiondef['sectiontype'],
        'sortorder'    => $sortorder++,
        'configdata'   => $sectiondef['configdata'] ?? '{}',
        'content'      => $sectiondef['content'] ?? '',
        'timecreated'  => time(),
        'timemodified' => time(),
    ];
}

$renderedcontent = byblos_renderer::render_page_from_parts(
    $fakepage,
    $fakesections,
    (int) $USER->id
);

echo $OUTPUT->header();
echo \html_writer::div(
    \html_writer::div($renderedcontent, 'byblos-rendered-content'),
    'byblos-page-view',
    ['data-theme' => $fakepage->themekey]
);
echo $OUTPUT->footer();
