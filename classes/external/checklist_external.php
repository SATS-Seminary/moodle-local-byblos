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
use core_external\external_multiple_structure;
use core_external\external_value;
use context_system;
use local_byblos\assignment_finder;
use local_byblos\page;

/**
 * External functions exposing advisory assessment checklists to the editor.
 *
 * Surfaces the per-assignment "checklist" configured on the sibling
 * assignsubmission_byblos plugin, filtered to only the assignments the
 * calling student is actively enrolled in and has not yet missed.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checklist_external extends external_api {
    /**
     * Parameter definition for get_assignment_checklists.
     *
     * @return external_function_parameters
     */
    public static function get_assignment_checklists_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_INT, 'Page ID (must be owned by the calling user)'),
        ]);
    }

    /**
     * Return all active byblos-assignment checklists for the calling user.
     *
     * The pageid parameter is used only to sanity-check that the caller owns
     * the page they're editing; the returned checklists are scoped to the
     * caller's own enrolments, not to anything stored on the page itself.
     *
     * @param int $pageid Portfolio page ID that the user is currently editing.
     * @return array[] List of assignment/checklist rows as associative arrays.
     */
    public static function get_assignment_checklists(int $pageid): array {
        global $USER;

        self::validate_parameters(self::get_assignment_checklists_parameters(), [
            'pageid' => $pageid,
        ]);

        $ctx = context_system::instance();
        self::validate_context($ctx);
        require_capability('local/byblos:createpage', $ctx);

        $pagerec = page::get($pageid);
        if (!$pagerec) {
            throw new \moodle_exception('error:pagenotfound', 'local_byblos');
        }
        if ((int) $pagerec->userid !== (int) $USER->id) {
            throw new \moodle_exception('error:pagenotowned', 'local_byblos');
        }

        $rows = assignment_finder::active_for_user((int) $USER->id);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'assignmentid' => (int) $r['id'],
                'name'         => (string) $r['name'],
                'coursename'   => (string) $r['coursename'],
                'duedate'      => (int) $r['duedate'],
                'items'        => array_values($r['checklist']),
            ];
        }
        return $out;
    }

    /**
     * Return structure for get_assignment_checklists.
     *
     * @return external_multiple_structure
     */
    public static function get_assignment_checklists_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'assignmentid' => new external_value(PARAM_INT, 'Assignment ID'),
                'name'         => new external_value(PARAM_TEXT, 'Assignment name'),
                'coursename'   => new external_value(PARAM_TEXT, 'Course full name'),
                'duedate'      => new external_value(PARAM_INT, 'Due date (unix timestamp, 0 if none)'),
                'items'        => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Checklist item')
                ),
            ])
        );
    }
}
