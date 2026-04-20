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
 * Output-layer hook callbacks for local_byblos.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Inject a "Your peer reviews" banner at the top of the page body when the
     * current user is on an assign module page and has peer-review rows for it.
     *
     * The banner lists pending reviews as clickable links (plus a collapsed
     * count of completed reviews so students can revisit their own feedback).
     * This is a student-facing counterpart to the teacher's "Manage peer
     * reviewers" tab; it bypasses the `modulesettings` nav node which is only
     * available to users with admin capabilities on the activity.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function render_peer_review_banner(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE, $USER, $DB;

        // Only fire on assignment view pages.
        $cm = $PAGE->cm;
        if (!$cm || $cm->modname !== 'assign') {
            return;
        }
        if ($PAGE->pagetype !== 'mod-assign-view') {
            return;
        }

        $assignid = (int) $cm->instance;

        // Is byblos + peer review enabled on this assignment?
        $peerenabled = $DB->get_record('assign_plugin_config', [
            'assignment' => $assignid,
            'plugin'     => 'byblos',
            'subtype'    => 'assignsubmission',
            'name'       => 'peerenabled',
        ]);
        if (!$peerenabled || $peerenabled->value !== '1') {
            return;
        }

        // Find peer_assignment rows where the current user is the reviewer.
        $rows = $DB->get_records('local_byblos_peer_assignment', [
            'assignmentid' => $assignid,
            'reviewerid'   => $USER->id,
        ], 'status DESC, timeassigned ASC');
        if (empty($rows)) {
            return;
        }

        $pending = [];
        $completed = 0;
        foreach ($rows as $r) {
            if ($r->status === 'pending') {
                $pending[] = $r;
            } else if ($r->status === 'complete') {
                $completed++;
            }
        }

        $items = [];
        foreach ($pending as $r) {
            $reviewee = $DB->get_record('user', ['id' => $r->revieweeuserid]);
            $name = $reviewee ? fullname($reviewee) : '#' . $r->revieweeuserid;
            // Resolve a submission id — first from the peer_assignment row,
            // falling back to a direct lookup (mirrors the dashboard fallback).
            $submissionid = (int) ($r->submissionid ?? 0);
            if (!$submissionid) {
                $sub = $DB->get_record('local_byblos_submission', [
                    'assignmentid' => $assignid,
                    'userid'       => $r->revieweeuserid,
                ]);
                $submissionid = $sub ? (int) $sub->id : 0;
            }

            if ($submissionid) {
                $url = new \moodle_url('/local/byblos/review.php', ['submissionid' => $submissionid]);
                $items[] = '<li>' . \html_writer::link($url, get_string(
                    'peerbanner_review_link',
                    'local_byblos',
                    (object) ['name' => s($name)]
                )) . '</li>';
            } else {
                $items[] = '<li class="text-muted">' . s(get_string(
                    'peerbanner_waiting',
                    'local_byblos',
                    s($name)
                )) . '</li>';
            }
        }

        // Nothing to show if neither a pending nor a recently-completed review.
        if (empty($items) && $completed === 0) {
            return;
        }

        $heading = get_string('peerbanner_heading', 'local_byblos', (object) [
            'pending' => count($pending),
            'completed' => $completed,
        ]);

        $html = '<div class="alert alert-info byblos-peer-banner d-flex align-items-start mb-3" role="note">'
            . '<i class="fa fa-comments-o fa-2x mr-3 mt-1" aria-hidden="true"></i>'
            . '<div class="flex-grow-1">'
            . '<strong>' . s($heading) . '</strong>';

        if (!empty($items)) {
            $html .= '<ul class="mb-1 mt-1">' . implode('', $items) . '</ul>';
        } else {
            $html .= '<div class="small mt-1">'
                . s(get_string('peerbanner_allcomplete', 'local_byblos'))
                . '</div>';
        }

        if ($completed > 0) {
            $listurl = new \moodle_url(
                '/local/byblos/view.php',
                ['tab' => 'reviews', 'assignmentid' => $assignid]
            );
            $html .= '<div class="small text-muted mt-1">'
                . \html_writer::link(
                    $listurl,
                    get_string('peerbanner_viewall', 'local_byblos', $completed)
                )
                . '</div>';
        }

        $html .= '</div></div>';

        $hook->add_html($html);
    }

    /**
     * Add a "My Portfolio" link to the user menu dropdown.
     *
     * The item is inserted by the hook AFTER any admin-configured
     * `$CFG->customusermenuitems` (which is where "Private files" lives),
     * so it naturally lands below Private files when that is present.
     *
     * @param \core_user\hook\extend_user_menu $hook
     */
    public static function extend_user_menu(\core_user\hook\extend_user_menu $hook): void {
        // Only show to users who can actually use the plugin.
        if (!isloggedin() || isguestuser()) {
            return;
        }
        if (!has_capability('local/byblos:use', \context_system::instance())) {
            return;
        }

        $item = new \stdClass();
        $item->itemtype = 'link';
        $item->url = new \moodle_url('/local/byblos/view.php');
        $item->title = get_string('nav_myportfolio', 'local_byblos');
        $item->titleidentifier = 'myportfolio,local_byblos';
        $item->pix = 'i/files';

        $hook->add_navitem($item);
    }
}
