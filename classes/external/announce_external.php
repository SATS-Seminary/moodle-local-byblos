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
 * External function: list courses the caller can post announcements in.
 *
 * Feeds the "Get announcement link" picker on the page view.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Lists courses where the current user can post an announcement.
 *
 * Filter on `moodle/course:update` — the standard "is a teacher in this
 * course" capability held by editing teachers and managers. They can post
 * to the course announcements forum, so they should also be able to
 * generate a turnstile link attributed to that course.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class announce_external extends external_api {
    /**
     * Parameters for list_postable_courses.
     *
     * @return external_function_parameters
     */
    public static function list_postable_courses_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return courses where the current user can post announcements.
     *
     * @return array[] List of {id, fullname, shortname}.
     */
    public static function list_postable_courses(): array {
        global $USER;

        self::validate_context(\context_system::instance());
        require_capability('local/byblos:use', \context_system::instance());

        $mycourses = enrol_get_my_courses(['id', 'fullname', 'shortname'], 'fullname ASC');
        $out = [];
        foreach ($mycourses as $course) {
            $cid = (int) $course->id;
            if ($cid === SITEID) {
                continue;
            }
            $ctx = \context_course::instance($cid, IGNORE_MISSING);
            if (!$ctx) {
                continue;
            }
            if (!has_capability('moodle/course:update', $ctx, $USER)) {
                continue;
            }
            $out[] = [
                'id'        => $cid,
                'fullname'  => format_string($course->fullname),
                'shortname' => format_string($course->shortname),
            ];
        }
        return $out;
    }

    /**
     * Return definition for list_postable_courses.
     *
     * @return external_multiple_structure
     */
    public static function list_postable_courses_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'        => new external_value(PARAM_INT, 'Course ID'),
                'fullname'  => new external_value(PARAM_RAW, 'Course fullname'),
                'shortname' => new external_value(PARAM_RAW, 'Course shortname'),
            ])
        );
    }
}
