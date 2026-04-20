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
 * Portfolio page completion observer.
 *
 * Listens to the page_created event and checks whether the user now meets
 * a configurable minimum page count. If the threshold is reached, triggers
 * a course completion update via the completion API.
 *
 * The required page count is stored in plugin config: local_byblos/completion_pages.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos\completion;

use local_byblos\event\page_created;

defined('MOODLE_INTERNAL') || die();

/**
 * Observer that checks portfolio page count against a completion threshold.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byblos_completion {

    /**
     * Handle the page_created event.
     *
     * Counts the user's total portfolio pages and, for every course the user
     * is enrolled in that uses completion, triggers a completion re-check.
     *
     * @param page_created $event The page_created event.
     * @return void
     */
    public static function page_created_handler(page_created $event): void {
        global $DB;

        $userid = $event->userid;

        // Get the required minimum number of pages from plugin config.
        $requiredpages = (int) get_config('local_byblos', 'completion_pages');
        if ($requiredpages <= 0) {
            // No completion rule configured — nothing to do.
            return;
        }

        // Count the user's portfolio pages.
        $pagecount = $DB->count_records('local_byblos_page', ['userid' => $userid]);
        if ($pagecount < $requiredpages) {
            // User has not yet reached the threshold.
            return;
        }

        // The user has met the threshold. Trigger completion update for all
        // enrolled courses that have completion enabled. We look for courses
        // that have pages tagged to them by this user.
        $sql = "SELECT DISTINCT pc.courseid
                  FROM {local_byblos_page_course} pc
                  JOIN {local_byblos_page} p ON p.id = pc.pageid
                 WHERE p.userid = :userid";

        $courseids = $DB->get_fieldset_sql($sql, ['userid' => $userid]);

        foreach ($courseids as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid, 'enablecompletion' => 1]);
            if (!$course) {
                continue;
            }

            // Rebuild completion cache for the user in this course.
            $completion = new \completion_info($course);
            if ($completion->is_enabled()) {
                // Mark the course criteria as re-evaluated for this user.
                $completion->invalidatecache($courseid, $userid);
            }
        }
    }

    /**
     * Check whether a user meets the portfolio page completion criteria.
     *
     * This is a utility method that can be called from custom completion
     * criteria implementations or external checks.
     *
     * @param int $userid The user ID.
     * @param int $requiredpages The minimum number of pages required.
     * @return bool True if the user has created at least $requiredpages pages.
     */
    public static function is_complete(int $userid, int $requiredpages = 0): bool {
        global $DB;

        if ($requiredpages <= 0) {
            $requiredpages = (int) get_config('local_byblos', 'completion_pages');
        }
        if ($requiredpages <= 0) {
            return true; // No threshold = always complete.
        }

        $pagecount = $DB->count_records('local_byblos_page', ['userid' => $userid]);
        return $pagecount >= $requiredpages;
    }
}
