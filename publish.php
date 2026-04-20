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
 * Publish / revert a portfolio page. POST-only endpoint.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\page;

require_login();
$context = context_system::instance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(new moodle_url('/local/byblos/view.php'));
}

require_sesskey();
require_capability('local/byblos:createpage', $context);

$pageid = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$returnto = optional_param('returnto', 'view', PARAM_ALPHA);

$record = page::get($pageid);
if (!$record || (int) $record->userid !== (int) $USER->id) {
    throw new moodle_exception('accessdenied', 'local_byblos');
}

switch ($action) {
    case 'publish':
        page::set_status($pageid, 'published');
        $msgkey = 'pagepublished';
        break;
    case 'draft':
        page::set_status($pageid, 'draft');
        $msgkey = 'pagereverted';
        break;
    default:
        throw new moodle_exception('invalidaction', 'local_byblos');
}

$returnurl = match ($returnto) {
    'editor' => new moodle_url('/local/byblos/editpage.php', ['id' => $pageid]),
    'view'   => new moodle_url('/local/byblos/page.php', ['id' => $pageid]),
    default  => new moodle_url('/local/byblos/view.php'),
};

redirect(
    $returnurl,
    get_string($msgkey, 'local_byblos'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
