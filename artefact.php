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
 * View, create, or edit a single artefact.
 *
 * URL: /local/byblos/artefact.php?id=X (view)
 *      /local/byblos/artefact.php?action=edit&id=X (edit)
 *      /local/byblos/artefact.php?action=edit (create new)
 *      POST to save.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\artefact as artefact_model;

require_login();
$context = context_system::instance();
require_capability('local/byblos:use', $context);

$id     = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$PAGE->set_context($context);

// Handle POST: save artefact.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    require_capability('local/byblos:createpage', $context);

    $saveid = optional_param('id', 0, PARAM_INT);
    $type   = required_param('type', PARAM_ALPHANUMEXT);
    $title  = required_param('title', PARAM_TEXT);
    $desc   = optional_param('description', '', PARAM_TEXT);
    $content = optional_param('content', '', PARAM_RAW);

    if ($saveid > 0) {
        // Update existing.
        $existing = artefact_model::get($saveid);
        if (!$existing || (int) $existing->userid !== (int) $USER->id) {
            throw new moodle_exception('accessdenied', 'local_byblos');
        }
        artefact_model::update($saveid, [
            'type'        => $type,
            'title'       => $title,
            'description' => $desc,
            'content'     => $content,
        ]);
        $redirectid = $saveid;
    } else {
        // Create new.
        $redirectid = artefact_model::create($USER->id, $type, $title, $desc, $content);
    }

    redirect(
        new moodle_url('/local/byblos/artefact.php', ['id' => $redirectid]),
        get_string('artefactsaved', 'local_byblos'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Edit mode.
if ($action === 'edit') {
    require_capability('local/byblos:createpage', $context);

    $artefact = null;
    if ($id > 0) {
        $artefact = artefact_model::get($id);
        if (!$artefact || (int) $artefact->userid !== (int) $USER->id) {
            throw new moodle_exception('accessdenied', 'local_byblos');
        }
        $PAGE->set_title(get_string('editartefact', 'local_byblos'));
        $PAGE->set_heading(get_string('editartefact', 'local_byblos'));
    } else {
        $PAGE->set_title(get_string('newartefact', 'local_byblos'));
        $PAGE->set_heading(get_string('newartefact', 'local_byblos'));
    }

    $PAGE->set_url(new moodle_url('/local/byblos/artefact.php', ['action' => 'edit', 'id' => $id]));

    // Artefact types for the selector.
    $types = ['text', 'file', 'image', 'badge', 'course_completion', 'blog_entry'];
    $typedata = [];
    foreach ($types as $t) {
        $typedata[] = [
            'value'    => $t,
            'label'    => get_string('type_' . $t, 'local_byblos'),
            'selected' => ($artefact && $artefact->type === $t),
        ];
    }

    $data = [
        'id'          => $artefact ? $artefact->id : 0,
        'title'       => $artefact ? $artefact->title : '',
        'description' => $artefact ? $artefact->description : '',
        'content'     => $artefact ? $artefact->content : '',
        'types'       => $typedata,
        'is_edit'     => ($id > 0),
        'actionurl'   => (new moodle_url('/local/byblos/artefact.php'))->out(false),
        'cancelurl'   => ($id > 0)
            ? (new moodle_url('/local/byblos/artefact.php', ['id' => $id]))->out(false)
            : (new moodle_url('/local/byblos/view.php', ['tab' => 'artefacts']))->out(false),
        'sesskey'     => sesskey(),
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_byblos/artefact_edit', $data);
    echo $OUTPUT->footer();
    exit;
}

// View mode (default).
if ($id <= 0) {
    redirect(new moodle_url('/local/byblos/artefacts.php'));
}

$artefact = artefact_model::get($id);
if (!$artefact) {
    throw new moodle_exception('artefactnotfound', 'local_byblos');
}

$isowner = ((int) $artefact->userid === (int) $USER->id);

$PAGE->set_url(new moodle_url('/local/byblos/artefact.php', ['id' => $id]));
$PAGE->set_title(format_string($artefact->title));
$PAGE->set_heading(format_string($artefact->title));

$data = [
    'artefact' => [
        'id'          => $artefact->id,
        'title'       => format_string($artefact->title, true, ['escape' => false]),
        'type'        => $artefact->artefacttype,
        'typelabel'   => get_string('type_' . $artefact->artefacttype, 'local_byblos'),
        'description' => format_text($artefact->description, FORMAT_HTML),
        'content'     => format_text($artefact->content, FORMAT_HTML),
        'has_content' => !empty(trim($artefact->content ?? '')),
        'timecreated' => userdate($artefact->timecreated),
    ],
    'isowner'  => $isowner,
    'editurl'  => (new moodle_url('/local/byblos/artefact.php', ['id' => $id, 'action' => 'edit']))->out(false),
    'deleteurl' => (new moodle_url('/local/byblos/delete.php'))->out(false),
    'dashurl'  => (new moodle_url('/local/byblos/view.php', ['tab' => 'artefacts']))->out(false),
    'sesskey'  => sesskey(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/artefact_view', $data);
echo $OUTPUT->footer();
