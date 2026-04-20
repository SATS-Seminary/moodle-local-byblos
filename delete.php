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
 * Delete handler — POST-only endpoint for deleting pages, collections, or artefacts.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\page;
use local_byblos\collection;
use local_byblos\artefact;

require_login();
$context = context_system::instance();

// POST only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(new moodle_url('/local/byblos/view.php'));
}

require_sesskey();
require_capability('local/byblos:createpage', $context);

$action = required_param('action', PARAM_ALPHA);
$id     = required_param('id', PARAM_INT);

switch ($action) {
    case 'page':
        $record = page::get($id);
        if (!$record || (int) $record->userid !== (int) $USER->id) {
            throw new moodle_exception('accessdenied', 'local_byblos');
        }
        page::delete($id);
        redirect(
            new moodle_url('/local/byblos/view.php', ['tab' => 'pages']),
            get_string('pagedeleted', 'local_byblos'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'collection':
        $record = collection::get($id);
        if (!$record || (int) $record->userid !== (int) $USER->id) {
            throw new moodle_exception('accessdenied', 'local_byblos');
        }
        collection::delete($id);
        redirect(
            new moodle_url('/local/byblos/view.php', ['tab' => 'collections']),
            get_string('collectiondeleted', 'local_byblos'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'artefact':
        $record = artefact::get($id);
        if (!$record || (int) $record->userid !== (int) $USER->id) {
            throw new moodle_exception('accessdenied', 'local_byblos');
        }
        artefact::delete($id);
        redirect(
            new moodle_url('/local/byblos/view.php', ['tab' => 'artefacts']),
            get_string('artefactdeleted', 'local_byblos'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    default:
        throw new moodle_exception('invalidaction', 'local_byblos');
}
