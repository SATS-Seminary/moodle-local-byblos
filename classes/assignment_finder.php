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

namespace local_byblos;

/**
 * Helper to find active byblos-enabled assignments for a given student.
 *
 * Used by the page editor to surface assessment checklists configured on
 * the sibling assignsubmission_byblos plugin, as advisory guidance for
 * students while they build a portfolio page.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignment_finder {
    /**
     * Return all active byblos-enabled assignments the user is currently
     * enrolled in (as a student) whose deadline has not passed and which
     * define a non-empty checklist.
     *
     * "Active" means:
     *  - the assign row exists (Moodle core has no soft-delete for assign, so
     *    we rely on the row being present and its course still being visible)
     *  - the byblos submission plugin is enabled on this assign (assign_plugin_config
     *    row with plugin='byblos', subtype='assignsubmission', name='enabled', value=1)
     *  - duedate is 0 (no deadline) OR duedate > time()
     *  - the user has an active user_enrolments row on an active enrol instance in the course
     *
     * @param int $userid The student user ID.
     * @return array List of associative arrays with keys:
     *               id (int), name (string), courseid (int), coursename (string),
     *               duedate (int), checklist (string[]).
     */
    public static function active_for_user(int $userid): array {
        global $DB;

        $now = time();

        // Join assign + course + user_enrolments/enrol so we only get assignments
        // where the user has an active enrolment in the owning course. We filter
        // the byblos "enabled" config via an EXISTS subquery, and pull the
        // "checklist" config value via a LEFT JOIN so we can reject empty ones.
        $sql = "SELECT DISTINCT a.id AS assignid,
                       a.name AS assignname,
                       a.duedate AS duedate,
                       a.course AS courseid,
                       c.fullname AS coursename,
                       apc_cl.value AS checklistraw
                  FROM {assign} a
                  JOIN {course} c ON c.id = a.course
                  JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                                           AND ue.userid = :userid
                                           AND ue.status = 0
                  JOIN {assign_plugin_config} apc_en
                       ON apc_en.assignment = a.id
                      AND apc_en.plugin = 'byblos'
                      AND apc_en.subtype = 'assignsubmission'
                      AND apc_en.name = 'enabled'
                      AND apc_en.value = '1'
             LEFT JOIN {assign_plugin_config} apc_cl
                       ON apc_cl.assignment = a.id
                      AND apc_cl.plugin = 'byblos'
                      AND apc_cl.subtype = 'assignsubmission'
                      AND apc_cl.name = 'checklist'
                 WHERE c.visible = 1
                   AND (a.duedate = 0 OR a.duedate > :now)
              ORDER BY a.duedate ASC, a.name ASC";

        $records = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'now'    => $now,
        ]);

        $out = [];
        foreach ($records as $r) {
            $items = self::parse_checklist((string) ($r->checklistraw ?? ''));
            if (empty($items)) {
                continue;
            }
            $out[] = [
                'id'         => (int) $r->assignid,
                'name'       => (string) $r->assignname,
                'courseid'   => (int) $r->courseid,
                'coursename' => (string) $r->coursename,
                'duedate'    => (int) $r->duedate,
                'checklist'  => $items,
            ];
        }

        return $out;
    }

    /**
     * Parse a raw checklist textarea value into a list of non-empty trimmed lines.
     *
     * @param string $raw Raw textarea value (one item per line).
     * @return string[] List of checklist items.
     */
    private static function parse_checklist(string $raw): array {
        if ($raw === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $items = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t !== '') {
                $items[] = $t;
            }
        }
        return $items;
    }
}
