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

namespace local_byblos\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use context_system;
use context_user;
use local_byblos\file_manager;
use local_byblos\page;

defined('MOODLE_INTERNAL') || die();

/**
 * External function for uploading images to portfolio pages via AJAX.
 *
 * Accepts a Moodle draft-area item ID (from file picker / direct upload
 * into the draft area) and moves the file to permanent storage under
 * the user's context.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_external extends external_api {

    /**
     * Describe parameters for upload_image.
     *
     * @return external_function_parameters
     */
    public static function upload_image_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_INT, 'Portfolio page ID'),
            'draftitemid' => new external_value(PARAM_INT, 'Draft area item ID containing the uploaded file'),
        ]);
    }

    /**
     * Upload an image to a portfolio page.
     *
     * Validates that the current user owns the page and has the
     * `local/byblos:createpage` capability, then moves the file
     * from the draft area to permanent storage.
     *
     * @param int $pageid       Portfolio page ID.
     * @param int $draftitemid  Draft area item ID.
     * @return array{url: string, filename: string} The pluginfile URL and filename.
     * @throws \moodle_exception On validation failure.
     */
    public static function upload_image(int $pageid, int $draftitemid): array {
        global $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::upload_image_parameters(), [
            'pageid' => $pageid,
            'draftitemid' => $draftitemid,
        ]);
        $pageid = $params['pageid'];
        $draftitemid = $params['draftitemid'];

        // Context and capability check.
        $contextsystem = context_system::instance();
        self::validate_context($contextsystem);
        require_capability('local/byblos:createpage', $contextsystem);

        // Verify user owns the page.
        $page = page::get($pageid);
        if (!$page) {
            throw new \moodle_exception('error:pagenotfound', 'local_byblos');
        }
        if ((int) $page->userid !== (int) $USER->id) {
            throw new \moodle_exception('error:notpageowner', 'local_byblos');
        }

        // Save the file.
        $storedfile = file_manager::save_uploaded_image($pageid, $USER->id, $draftitemid);
        if (!$storedfile) {
            throw new \moodle_exception('error:nouploadedfile', 'local_byblos');
        }

        // Build the serving URL.
        $context = context_user::instance($USER->id);
        $url = file_manager::get_image_url($context->id, $pageid, $storedfile->get_filename());

        return [
            'url' => $url->out(false),
            'filename' => $storedfile->get_filename(),
        ];
    }

    /**
     * Describe the return value for upload_image.
     *
     * @return external_single_structure
     */
    public static function upload_image_returns(): external_single_structure {
        return new external_single_structure([
            'url' => new external_value(PARAM_URL, 'The pluginfile.php URL for the uploaded image'),
            'filename' => new external_value(PARAM_FILE, 'The stored filename'),
        ]);
    }
}
