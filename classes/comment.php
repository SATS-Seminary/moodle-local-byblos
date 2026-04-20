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
 * Inline comment model — feedback anchored to a submission at an arbitrary target.
 *
 * Comments are keyed by a free-form anchorkey so the same model serves page-level,
 * section-level, and artefact-level feedback without schema churn.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comment {

    /** @var string Database table. */
    private const TABLE = 'local_byblos_comment';

    /** @var string[] Valid roles. */
    public const ROLES = ['teacher', 'peer', 'self'];

    /**
     * Build an anchor key from a target type and ID.
     *
     * @param string   $type Anchor type: 'page', 'section', 'artefact'.
     * @param int|null $id   Target ID, or null for page-level anchors.
     * @return string
     */
    public static function anchor(string $type, ?int $id = null): string {
        return $id === null ? $type : $type . ':' . $id;
    }

    /**
     * Create a new comment.
     *
     * @param int    $submissionid
     * @param string $anchorkey
     * @param int    $authorid
     * @param string $role
     * @param string $body
     * @return int Newly created comment ID.
     */
    public static function create(
        int $submissionid,
        string $anchorkey,
        int $authorid,
        string $role,
        string $body,
    ): int {
        global $DB;

        if (!in_array($role, self::ROLES, true)) {
            throw new \coding_exception("Invalid role: {$role}");
        }

        $now = time();
        return (int) $DB->insert_record(self::TABLE, (object) [
            'submissionid' => $submissionid,
            'anchorkey'    => $anchorkey,
            'authorid'     => $authorid,
            'role'         => $role,
            'body'         => $body,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Retrieve a comment by ID.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Update the body of an existing comment.
     *
     * @param int    $id
     * @param string $body
     * @return bool
     */
    public static function update_body(int $id, string $body): bool {
        global $DB;

        return $DB->update_record(self::TABLE, (object) [
            'id' => $id,
            'body' => $body,
            'timemodified' => time(),
        ]);
    }

    /**
     * Delete a comment.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        global $DB;

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * List all comments for a submission, ordered by anchor then timestamp.
     *
     * @param int $submissionid
     * @return \stdClass[]
     */
    public static function list_for_submission(int $submissionid): array {
        global $DB;

        return array_values(
            $DB->get_records(
                self::TABLE,
                ['submissionid' => $submissionid],
                'anchorkey ASC, timecreated ASC'
            )
        );
    }

    /**
     * List comments matching a specific anchor for a submission.
     *
     * @param int    $submissionid
     * @param string $anchorkey
     * @return \stdClass[]
     */
    public static function list_for_anchor(int $submissionid, string $anchorkey): array {
        global $DB;

        return array_values(
            $DB->get_records(
                self::TABLE,
                ['submissionid' => $submissionid, 'anchorkey' => $anchorkey],
                'timecreated ASC'
            )
        );
    }
}
