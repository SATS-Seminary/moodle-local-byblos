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
 * Peer-review model — maps reviewers to reviewees for an assignment.
 *
 * Rows are created by one of three assignment modes:
 *  - manual: teacher picks reviewers directly.
 *  - random: system assigns N reviewers per reviewee, avoiding self-review.
 *  - group:  reviewers are the reviewee's group peers.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer {

    /** @var string Database table. */
    private const TABLE = 'local_byblos_peer_assignment';

    /** @var string[] Valid assignment modes. */
    public const MODES = ['manual', 'random', 'group'];

    /** @var string[] Valid review statuses. */
    public const STATUSES = ['pending', 'complete', 'declined'];

    /**
     * Create a peer-review assignment.
     *
     * Silently skips self-review (reviewer == reviewee). Uses the unique index
     * on (assignmentid, reviewerid, revieweeuserid) to deduplicate — returns
     * the existing row ID if the pair is already assigned.
     *
     * @param int      $assignmentid
     * @param int      $reviewerid
     * @param int      $revieweeuserid
     * @param int|null $submissionid Optional byblos submission ID if already known.
     * @return int|null The row ID, or null when skipped (self-review).
     */
    public static function assign(
        int $assignmentid,
        int $reviewerid,
        int $revieweeuserid,
        ?int $submissionid = null,
    ): ?int {
        global $DB;

        if ($reviewerid === $revieweeuserid) {
            return null;
        }

        $existing = $DB->get_record(self::TABLE, [
            'assignmentid'   => $assignmentid,
            'reviewerid'     => $reviewerid,
            'revieweeuserid' => $revieweeuserid,
        ]);
        if ($existing) {
            if ($submissionid && !$existing->submissionid) {
                $DB->set_field(self::TABLE, 'submissionid', $submissionid, ['id' => $existing->id]);
            }
            return (int) $existing->id;
        }

        return (int) $DB->insert_record(self::TABLE, (object) [
            'assignmentid'    => $assignmentid,
            'reviewerid'      => $reviewerid,
            'revieweeuserid'  => $revieweeuserid,
            'submissionid'    => $submissionid,
            'status'          => 'pending',
            'advisoryscore'   => null,
            'timeassigned'    => time(),
            'timecompleted'   => 0,
        ]);
    }

    /**
     * Randomly assign N reviewers to each candidate, avoiding self-review.
     *
     * Attempts to spread load: each reviewer is used no more than
     * ceil(count * N / count) = N times.
     *
     * @param int   $assignmentid
     * @param int[] $candidates User IDs eligible as both reviewers and reviewees.
     * @param int   $peers      Number of reviewers per reviewee.
     * @return int Number of rows written.
     */
    public static function assign_random(int $assignmentid, array $candidates, int $peers): int {
        if ($peers < 1 || count($candidates) < 2) {
            return 0;
        }

        $written = 0;
        foreach ($candidates as $reviewee) {
            $pool = array_values(array_filter($candidates, static fn($u): bool => $u !== $reviewee));
            shuffle($pool);
            $picked = array_slice($pool, 0, min($peers, count($pool)));
            foreach ($picked as $reviewer) {
                if (self::assign($assignmentid, (int) $reviewer, (int) $reviewee) !== null) {
                    $written++;
                }
            }
        }
        return $written;
    }

    /**
     * Assign reviewers within each group (every member reviews every other member).
     *
     * @param int   $assignmentid
     * @param int[] $groupuserids User IDs all belonging to the same group.
     * @return int Number of rows written.
     */
    public static function assign_group(int $assignmentid, array $groupuserids): int {
        $written = 0;
        foreach ($groupuserids as $reviewer) {
            foreach ($groupuserids as $reviewee) {
                if (self::assign($assignmentid, (int) $reviewer, (int) $reviewee) !== null) {
                    $written++;
                }
            }
        }
        return $written;
    }

    /**
     * List all reviews a user needs to complete for a given assignment.
     *
     * @param int $assignmentid
     * @param int $reviewerid
     * @return \stdClass[]
     */
    public static function queue_for_reviewer(int $assignmentid, int $reviewerid): array {
        global $DB;

        return array_values($DB->get_records(self::TABLE, [
            'assignmentid' => $assignmentid,
            'reviewerid'   => $reviewerid,
        ], 'status ASC, timeassigned ASC'));
    }

    /**
     * List all pending peer reviews assigned to a user across every assignment.
     *
     * Used by the "Reviews to do" dashboard tab.
     *
     * @param int $reviewerid
     * @return \stdClass[]
     */
    public static function all_pending_for_reviewer(int $reviewerid): array {
        global $DB;

        return array_values($DB->get_records(self::TABLE, [
            'reviewerid' => $reviewerid,
            'status'     => 'pending',
        ], 'timeassigned ASC'));
    }

    /**
     * List all reviews someone has received for a given assignment.
     *
     * @param int $assignmentid
     * @param int $revieweeuserid
     * @return \stdClass[]
     */
    public static function reviews_of(int $assignmentid, int $revieweeuserid): array {
        global $DB;

        return array_values($DB->get_records(self::TABLE, [
            'assignmentid'   => $assignmentid,
            'revieweeuserid' => $revieweeuserid,
        ], 'timeassigned ASC'));
    }

    /**
     * Mark a review as complete, optionally with an advisory score.
     *
     * @param int        $id
     * @param float|null $advisoryscore
     * @return bool
     */
    public static function mark_complete(int $id, ?float $advisoryscore = null): bool {
        global $DB;

        return $DB->update_record(self::TABLE, (object) [
            'id'            => $id,
            'status'        => 'complete',
            'advisoryscore' => $advisoryscore,
            'timecompleted' => time(),
        ]);
    }

    /**
     * Link a peer-review row to a byblos submission once the reviewee submits.
     *
     * @param int $assignmentid
     * @param int $revieweeuserid
     * @param int $submissionid
     * @return void
     */
    public static function attach_submission(int $assignmentid, int $revieweeuserid, int $submissionid): void {
        global $DB;

        $rows = $DB->get_records(self::TABLE, [
            'assignmentid'   => $assignmentid,
            'revieweeuserid' => $revieweeuserid,
        ]);
        foreach ($rows as $row) {
            if (!$row->submissionid) {
                $DB->set_field(self::TABLE, 'submissionid', $submissionid, ['id' => $row->id]);
            }
        }
    }

    /**
     * Delete a peer-assignment row.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        global $DB;

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Load the advanced-grading rubric definition for an assignment, if any.
     *
     * Returns null when the assignment doesn't use rubric grading or no
     * rubric is currently defined/active.
     *
     * @param int $assignmentid mod_assign instance id.
     * @return array|null ['criteria' => [['id','description','levels'=>[['id','score','definition']]]], 'maxscore' => float]
     */
    public static function load_rubric_definition(int $assignmentid): ?array {
        global $DB;

        $assign = $DB->get_record('assign', ['id' => $assignmentid]);
        if (!$assign) {
            return null;
        }
        $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course);
        if (!$cm) {
            return null;
        }
        $context = \context_module::instance($cm->id);
        $gm = get_grading_manager($context, 'mod_assign', 'submissions');
        if ($gm->get_active_method() !== 'rubric') {
            return null;
        }

        $definition = $DB->get_record('grading_definitions', [
            'areaid' => $gm->get_area_id(),
            'method' => 'rubric',
            'status' => 20, // gradingform_controller::DEFINITION_STATUS_READY.
        ]);
        if (!$definition) {
            return null;
        }

        $criteria = $DB->get_records(
            'gradingform_rubric_criteria',
            ['definitionid' => $definition->id],
            'sortorder ASC'
        );
        if (!$criteria) {
            return null;
        }

        $out = ['criteria' => [], 'maxscore' => 0.0];
        foreach ($criteria as $c) {
            $levels = $DB->get_records(
                'gradingform_rubric_levels',
                ['criterionid' => $c->id],
                'score ASC'
            );
            $maxlevel = 0.0;
            $lvls = [];
            foreach ($levels as $l) {
                $lvls[] = [
                    'id'         => (int) $l->id,
                    'score'      => (float) $l->score,
                    'definition' => (string) $l->definition,
                ];
                $maxlevel = max($maxlevel, (float) $l->score);
            }
            $out['criteria'][] = [
                'id'          => (int) $c->id,
                'description' => (string) $c->description,
                'levels'      => $lvls,
            ];
            $out['maxscore'] += $maxlevel;
        }
        return $out;
    }
}
