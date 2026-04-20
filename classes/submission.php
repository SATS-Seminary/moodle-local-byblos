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

defined('MOODLE_INTERNAL') || die();

/**
 * Submission model — the byblos-side record of a portfolio assignment submission.
 *
 * Linked 1:1 to an mdl_assign_submission row via assignsubmissionid. Tracks
 * the student's chosen page or collection, snapshot mode, and optional snapshot
 * reference. Comments, peer-review assignments and grade release hang off this.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission {

    /** @var string Database table. */
    private const TABLE = 'local_byblos_submission';

    /** @var string[] Allowed snapshot modes. */
    public const SNAPSHOT_MODES = ['snapshot_on_submit', 'live', 'live_until_locked'];

    /**
     * Upsert a submission record for a given (assignment, assignsubmission) tuple.
     *
     * If a submission already exists for the assignsubmissionid, it is updated;
     * otherwise a new row is created. Does NOT capture a snapshot — call
     * capture_snapshot_if_needed() separately at the right lifecycle moment.
     *
     * @param int       $assignmentid         mdl_assign.id
     * @param int       $assignsubmissionid   mdl_assign_submission.id
     * @param int       $userid               Submitter user ID.
     * @param int|null  $pageid               Chosen page ID, or null.
     * @param int|null  $collectionid         Chosen collection ID, or null.
     * @param string    $snapshotmode         One of SNAPSHOT_MODES.
     * @return int The submission row ID.
     */
    public static function upsert(
        int $assignmentid,
        int $assignsubmissionid,
        int $userid,
        ?int $pageid,
        ?int $collectionid,
        string $snapshotmode,
    ): int {
        global $DB;

        if (!in_array($snapshotmode, self::SNAPSHOT_MODES, true)) {
            throw new \coding_exception("Invalid snapshot mode: {$snapshotmode}");
        }

        $now = time();
        $existing = $DB->get_record(self::TABLE, ['assignsubmissionid' => $assignsubmissionid]);

        if ($existing) {
            $existing->pageid       = $pageid;
            $existing->collectionid = $collectionid;
            $existing->snapshotmode = $snapshotmode;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return (int) $existing->id;
        }

        return (int) $DB->insert_record(self::TABLE, (object) [
            'pageid'             => $pageid,
            'collectionid'       => $collectionid,
            'assignmentid'       => $assignmentid,
            'assignsubmissionid' => $assignsubmissionid,
            'userid'             => $userid,
            'snapshotmode'       => $snapshotmode,
            'snapshotid'         => null,
            'timecreated'        => $now,
            'timemodified'       => $now,
        ]);
    }

    /**
     * Retrieve a submission row by ID.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Retrieve a submission by the underlying mod_assign submission row ID.
     *
     * @param int $assignsubmissionid
     * @return \stdClass|null
     */
    public static function get_by_assign_submission(int $assignsubmissionid): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['assignsubmissionid' => $assignsubmissionid]) ?: null;
    }

    /**
     * Capture a snapshot for this submission if the mode requires one at this moment.
     *
     * Call this on submit for snapshot_on_submit; on assignment lock for
     * live_until_locked. No-op for live mode or if a snapshot already exists.
     *
     * @param int  $submissionid
     * @param bool $locking True when the assignment is transitioning to "locked"
     *                     (used by live_until_locked mode). Ignored otherwise.
     * @return int|null Snapshot ID if captured, null otherwise.
     */
    public static function capture_snapshot_if_needed(int $submissionid, bool $locking = false): ?int {
        global $DB;

        $sub = self::get($submissionid);
        if (!$sub || (!$sub->pageid && !$sub->collectionid)) {
            return null;
        }
        if ($sub->snapshotid) {
            return (int) $sub->snapshotid; // Already captured.
        }

        $shouldcapture = match ($sub->snapshotmode) {
            'snapshot_on_submit' => true,
            'live_until_locked'  => $locking,
            'live'               => false,
            default              => false,
        };
        if (!$shouldcapture) {
            return null;
        }

        $snapid = $sub->collectionid
            ? snapshot::capture_collection((int) $sub->collectionid)
            : snapshot::capture((int) $sub->pageid);
        $DB->set_field(self::TABLE, 'snapshotid', $snapid, ['id' => $submissionid]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $submissionid]);

        return $snapid;
    }

    /**
     * List all submissions for a given assignment.
     *
     * @param int $assignmentid
     * @return \stdClass[]
     */
    public static function list_for_assignment(int $assignmentid): array {
        global $DB;

        return array_values(
            $DB->get_records(self::TABLE, ['assignmentid' => $assignmentid], 'timecreated DESC')
        );
    }

    /**
     * Delete a submission and any associated snapshot, comments and peer assignments.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        global $DB;

        $sub = self::get($id);
        if (!$sub) {
            return false;
        }
        if ($sub->snapshotid) {
            snapshot::delete((int) $sub->snapshotid);
        }
        $DB->delete_records('local_byblos_comment', ['submissionid' => $id]);
        $DB->set_field('local_byblos_peer_assignment', 'submissionid', null, ['submissionid' => $id]);

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }
}
