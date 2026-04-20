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
use local_byblos\comment;
use local_byblos\peer;
use local_byblos\submission;

defined('MOODLE_INTERNAL') || die();

/**
 * External functions for inline comment CRUD on submissions.
 *
 * Authorisation model:
 *  - Owner of a submission (the student) may read comments but not write
 *    them via this API (they see them as feedback; replies TBD).
 *  - A teacher with grading capability on the linked assignment may CRUD
 *    their own comments (role = 'teacher').
 *  - A peer reviewer assigned to this submission may CRUD their own
 *    comments (role = 'peer').
 *  - A user may always delete/update their own comments.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comment_external extends external_api {

    /**
     * Resolve the caller's role on a given submission, or throw if they have no access.
     *
     * Returns one of 'teacher', 'peer', 'self'.
     *
     * @param \stdClass $sub Submission record.
     * @return string
     * @throws \moodle_exception
     */
    private static function resolve_role(\stdClass $sub): string {
        global $USER, $DB;

        // Owner of the submission → 'self'.
        if ((int) $sub->userid === (int) $USER->id) {
            return 'self';
        }

        // Teacher role: has grade capability on the linked assign cm.
        // Look up the course module from the assign row.
        $assign = $DB->get_record('assign', ['id' => $sub->assignmentid]);
        if ($assign) {
            $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course);
            if ($cm) {
                $ctx = \context_module::instance($cm->id);
                if (has_capability('mod/assign:grade', $ctx)) {
                    return 'teacher';
                }
            }
        }

        // Peer role: has a pending/complete peer assignment for this submission.
        $pa = $DB->get_record('local_byblos_peer_assignment', [
            'assignmentid'   => $sub->assignmentid,
            'reviewerid'     => $USER->id,
            'revieweeuserid' => $sub->userid,
        ]);
        if ($pa) {
            return 'peer';
        }

        throw new \moodle_exception('error:nopermission', 'local_byblos');
    }

    // ------------------------------------------------------------------
    // add_comment
    // ------------------------------------------------------------------

    /**
     * Parameter definition for add_comment.
     *
     * @return external_function_parameters
     */
    public static function add_comment_parameters(): external_function_parameters {
        return new external_function_parameters([
            'submissionid' => new external_value(PARAM_INT, 'Submission ID'),
            'anchorkey'    => new external_value(PARAM_TEXT, "Anchor ('page', 'section:42', etc.)"),
            'body'         => new external_value(PARAM_RAW, 'Comment body (plain text)'),
        ]);
    }

    /**
     * Create a new inline comment on a submission.
     *
     * @param int    $submissionid
     * @param string $anchorkey
     * @param string $body
     * @return array{id:int, role:string}
     */
    public static function add_comment(int $submissionid, string $anchorkey, string $body): array {
        global $USER;

        self::validate_parameters(self::add_comment_parameters(), [
            'submissionid' => $submissionid,
            'anchorkey'    => $anchorkey,
            'body'         => $body,
        ]);
        self::validate_context(context_system::instance());

        $sub = submission::get($submissionid);
        if (!$sub) {
            throw new \moodle_exception('error:submissionnotfound', 'local_byblos');
        }

        $role = self::resolve_role($sub);
        if ($role === 'self') {
            // Students don't post feedback; they read it.
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }

        $id = comment::create($submissionid, $anchorkey, (int) $USER->id, $role, trim($body));
        return ['id' => $id, 'role' => $role];
    }

    /**
     * Return structure for add_comment.
     *
     * @return external_single_structure
     */
    public static function add_comment_returns(): external_single_structure {
        return new external_single_structure([
            'id'   => new external_value(PARAM_INT, 'New comment ID'),
            'role' => new external_value(PARAM_ALPHA, 'Role used: teacher|peer'),
        ]);
    }

    // ------------------------------------------------------------------
    // update_comment
    // ------------------------------------------------------------------

    public static function update_comment_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id'   => new external_value(PARAM_INT, 'Comment ID'),
            'body' => new external_value(PARAM_RAW, 'New comment body'),
        ]);
    }

    /**
     * Update one's own comment body.
     *
     * @param int    $id
     * @param string $body
     * @return array{ok:bool}
     */
    public static function update_comment(int $id, string $body): array {
        global $USER;

        self::validate_parameters(self::update_comment_parameters(), ['id' => $id, 'body' => $body]);
        self::validate_context(context_system::instance());

        $c = comment::get($id);
        if (!$c) {
            throw new \moodle_exception('error:commentnotfound', 'local_byblos');
        }
        if ((int) $c->authorid !== (int) $USER->id) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }

        comment::update_body($id, trim($body));
        return ['ok' => true];
    }

    public static function update_comment_returns(): external_single_structure {
        return new external_single_structure(['ok' => new external_value(PARAM_BOOL, 'Success')]);
    }

    // ------------------------------------------------------------------
    // delete_comment
    // ------------------------------------------------------------------

    public static function delete_comment_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Comment ID'),
        ]);
    }

    /**
     * Delete one's own comment (or any comment if you hold teacher capability).
     *
     * @param int $id
     * @return array{ok:bool}
     */
    public static function delete_comment(int $id): array {
        global $USER;

        self::validate_parameters(self::delete_comment_parameters(), ['id' => $id]);
        self::validate_context(context_system::instance());

        $c = comment::get($id);
        if (!$c) {
            throw new \moodle_exception('error:commentnotfound', 'local_byblos');
        }

        $canmoderate = false;
        if ((int) $c->authorid !== (int) $USER->id) {
            $sub = submission::get((int) $c->submissionid);
            if ($sub) {
                $role = self::resolve_role($sub);
                $canmoderate = ($role === 'teacher');
            }
            if (!$canmoderate) {
                throw new \moodle_exception('error:nopermission', 'local_byblos');
            }
        }

        comment::delete($id);
        return ['ok' => true];
    }

    public static function delete_comment_returns(): external_single_structure {
        return new external_single_structure(['ok' => new external_value(PARAM_BOOL, 'Success')]);
    }

    // ------------------------------------------------------------------
    // list_comments
    // ------------------------------------------------------------------

    public static function list_comments_parameters(): external_function_parameters {
        return new external_function_parameters([
            'submissionid' => new external_value(PARAM_INT, 'Submission ID'),
        ]);
    }

    /**
     * List all comments on a submission. Viewer must be the owner, a teacher
     * with grade capability, or an assigned peer reviewer.
     *
     * @param int $submissionid
     * @return array[] List of comment rows as associative arrays.
     */
    public static function list_comments(int $submissionid): array {
        global $DB;

        self::validate_parameters(self::list_comments_parameters(), ['submissionid' => $submissionid]);
        self::validate_context(context_system::instance());

        $sub = submission::get($submissionid);
        if (!$sub) {
            throw new \moodle_exception('error:submissionnotfound', 'local_byblos');
        }

        // resolve_role throws if no access.
        $role = self::resolve_role($sub);

        $rows = comment::list_for_submission($submissionid);

        // Self (the reviewee) may have peer comments hidden depending on assignment config.
        if ($role === 'self') {
            $visibility = peer_review_external::get_peer_visibility((int) $sub->assignmentid);
            $rows = self::filter_peer_comments_for_self(
                $rows,
                $sub,
                $visibility
            );
        }

        return array_map(static function (\stdClass $c): array {
            return [
                'id'           => (int) $c->id,
                'submissionid' => (int) $c->submissionid,
                'anchorkey'    => $c->anchorkey,
                'authorid'     => (int) $c->authorid,
                'role'         => $c->role,
                'body'         => $c->body,
                'timecreated'  => (int) $c->timecreated,
                'timemodified' => (int) $c->timemodified,
            ];
        }, array_values($rows));
    }

    /**
     * Filter peer-authored comments out of the list when the reviewee shouldn't see them yet.
     *
     * Visibility rules:
     *  - teacher_only      → peer comments always hidden.
     *  - after_submit      → include only peer comments whose authoring peer
     *                        has a 'complete' peer_assignment row for this reviewee.
     *  - on_grade_release  → include peer comments only after the mod_assign
     *                        grade for this student has been released.
     *
     * Non-peer comments (teacher, self) pass through untouched.
     *
     * @param \stdClass[] $rows
     * @param \stdClass $sub The submission record.
     * @param string $visibility
     * @return \stdClass[]
     */
    private static function filter_peer_comments_for_self(
        array $rows,
        \stdClass $sub,
        string $visibility
    ): array {
        global $DB;

        if ($visibility === 'teacher_only') {
            return array_filter($rows, static fn($c): bool => $c->role !== 'peer');
        }

        if ($visibility === 'on_grade_release') {
            $released = self::grade_released($sub);
            if (!$released) {
                return array_filter($rows, static fn($c): bool => $c->role !== 'peer');
            }
            return $rows;
        }

        // Default: after_submit — include peer comments only once the authoring
        // reviewer has completed their review.
        $completepeers = $DB->get_fieldset_select(
            'local_byblos_peer_assignment',
            'reviewerid',
            'assignmentid = :aid AND revieweeuserid = :rid AND status = :st',
            [
                'aid' => $sub->assignmentid,
                'rid' => $sub->userid,
                'st'  => 'complete',
            ]
        );
        $completeset = array_flip(array_map('intval', $completepeers));

        return array_filter($rows, static function (\stdClass $c) use ($completeset): bool {
            if ($c->role !== 'peer') {
                return true;
            }
            return isset($completeset[(int) $c->authorid]);
        });
    }

    /**
     * Has the mod_assign grade for this student been released?
     *
     * When markingworkflow is in use, this requires workflowstate=released.
     * When workflow is disabled, we treat the grade as "released" as soon as
     * a graded mdl_assign_grades row with a grader exists.
     *
     * @param \stdClass $sub
     * @return bool
     */
    private static function grade_released(\stdClass $sub): bool {
        global $DB;

        $assign = $DB->get_record('assign', ['id' => $sub->assignmentid]);
        if (!$assign) {
            return false;
        }

        if (!empty($assign->markingworkflow)) {
            $flags = $DB->get_record('assign_user_flags', [
                'assignment' => $assign->id,
                'userid'     => $sub->userid,
            ]);
            return $flags && $flags->workflowstate === 'released';
        }

        // Without markingworkflow, Moodle has no "released" state distinct from "graded".
        // Treat as released when: a grader recorded a real grade (not the -1 "no grade" sentinel).
        $grade = $DB->get_record('assign_grades', [
            'assignment' => $assign->id,
            'userid'     => $sub->userid,
        ]);
        if (!$grade || (int) $grade->grader <= 0) {
            return false;
        }
        return ((float) $grade->grade) !== -1.0;
    }

    public static function list_comments_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'           => new external_value(PARAM_INT, 'Comment ID'),
                'submissionid' => new external_value(PARAM_INT, 'Submission ID'),
                'anchorkey'    => new external_value(PARAM_TEXT, 'Anchor key'),
                'authorid'     => new external_value(PARAM_INT, 'Author user ID'),
                'role'         => new external_value(PARAM_ALPHA, 'Role: teacher|peer|self'),
                'body'         => new external_value(PARAM_RAW, 'Comment body'),
                'timecreated'  => new external_value(PARAM_INT, 'Created timestamp'),
                'timemodified' => new external_value(PARAM_INT, 'Modified timestamp'),
            ])
        );
    }
}
