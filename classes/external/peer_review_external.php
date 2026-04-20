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
use local_byblos\peer;

/**
 * External functions for the peer review submission workflow.
 *
 * Lets a peer reviewer mark their assigned review as complete, optionally
 * attaching an advisory score and/or rubric payload. Also dispatches a
 * notification to the reviewee when the assignment's peer visibility setting
 * allows it.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_review_external extends external_api {
    /**
     * Parameter definition for submit_peer_review.
     *
     * @return external_function_parameters
     */
    public static function submit_peer_review_parameters(): external_function_parameters {
        return new external_function_parameters([
            'peerassignmentid' => new external_value(PARAM_INT, 'local_byblos_peer_assignment.id'),
            'advisoryscore'    => new external_value(PARAM_FLOAT, 'Advisory score', VALUE_DEFAULT, null, NULL_ALLOWED),
            'rubricdata'       => new external_value(PARAM_RAW, 'Rubric payload (JSON)', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    /**
     * Mark a peer review as complete.
     *
     * The caller must match the reviewerid on the peer_assignment row.
     * A submission must already be attached (i.e., the reviewee has submitted).
     *
     * @param int $peerassignmentid
     * @param float|null $advisoryscore
     * @param string|null $rubricdata
     * @return array{ok:bool}
     */
    public static function submit_peer_review(
        int $peerassignmentid,
        ?float $advisoryscore = null,
        ?string $rubricdata = null
    ): array {
        global $DB, $USER;

        self::validate_parameters(self::submit_peer_review_parameters(), [
            'peerassignmentid' => $peerassignmentid,
            'advisoryscore'    => $advisoryscore,
            'rubricdata'       => $rubricdata,
        ]);
        self::validate_context(context_system::instance());

        $pa = $DB->get_record('local_byblos_peer_assignment', ['id' => $peerassignmentid]);
        if (!$pa) {
            throw new \moodle_exception('error:peernotfound', 'local_byblos');
        }
        if ((int) $pa->reviewerid !== (int) $USER->id) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }
        if (empty($pa->submissionid)) {
            // Reviewee hasn't actually submitted yet.
            throw new \moodle_exception('error:submissionnotfound', 'local_byblos');
        }

        peer::mark_complete((int) $pa->id, $advisoryscore);

        if ($rubricdata !== null) {
            $DB->set_field(
                'local_byblos_peer_assignment',
                'rubricdata',
                $rubricdata,
                ['id' => $pa->id]
            );
        }

        // Notify the reviewee unless the assignment is configured teacher-only.
        $visibility = self::get_peer_visibility((int) $pa->assignmentid);
        if ($visibility !== 'teacher_only') {
            self::send_complete_notification($pa);
        }

        return ['ok' => true];
    }

    /**
     * Return structure for submit_peer_review.
     *
     * @return external_single_structure
     */
    public static function submit_peer_review_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }

    /**
     * Look up the effective peer_visibility setting for an assignment.
     *
     * Falls back to 'after_submit' when unset.
     *
     * @param int $assignmentid mdl_assign.id
     * @return string One of after_submit | on_grade_release | teacher_only.
     */
    public static function get_peer_visibility(int $assignmentid): string {
        global $DB;

        $rec = $DB->get_record('assign_plugin_config', [
            'assignment' => $assignmentid,
            'plugin'     => 'byblos',
            'subtype'    => 'assignsubmission',
            'name'       => 'peervisibility',
        ]);
        if ($rec && !empty($rec->value)) {
            return $rec->value;
        }
        return 'after_submit';
    }

    /**
     * Send a "peer review submitted" message to the reviewee.
     *
     * Best-effort; silently returns on failure to avoid blocking the API call.
     *
     * @param \stdClass $pa The peer_assignment row.
     * @return void
     */
    private static function send_complete_notification(\stdClass $pa): void {
        global $DB;

        $reviewer = $DB->get_record('user', ['id' => $pa->reviewerid]);
        $reviewee = $DB->get_record('user', ['id' => $pa->revieweeuserid]);
        if (!$reviewer || !$reviewee) {
            return;
        }

        $assign = $DB->get_record('assign', ['id' => $pa->assignmentid]);
        $assignname = $assign ? format_string($assign->name) : '';

        $a = (object) [
            'reviewer' => fullname($reviewer),
            'assignment' => $assignname,
        ];

        $subject = get_string('msg_peerreviewcomplete_subject', 'local_byblos', $a);
        $body = get_string('msg_peerreviewcomplete_body', 'local_byblos', $a);

        $message = new \core\message\message();
        $message->component = 'local_byblos';
        $message->name = 'peerreviewcomplete';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $reviewee;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . s($body) . '</p>';
        $message->smallmessage = $subject;
        $message->notification = 1;

        if ($pa->submissionid) {
            $url = new \moodle_url('/local/byblos/review.php', ['submissionid' => $pa->submissionid]);
            $message->contexturl = $url->out(false);
            $message->contexturlname = $assignname;
        }

        message_send($message);
    }
}
