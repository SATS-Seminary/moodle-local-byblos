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
 * Collection model — CRUD for portfolio collections.
 *
 * A collection groups portfolio pages together in a defined order.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collection {

    /** @var string Database table name. */
    private const TABLE = 'local_byblos_collection';

    /** @var string Collection-page mapping table. */
    private const TABLE_PAGE = 'local_byblos_collection_page';

    /**
     * Create a new collection.
     *
     * @param int    $userid      Owner user ID.
     * @param string $title       Collection title.
     * @param string $description Collection description.
     * @param int    $groupid     Optional Moodle group ID to bind the collection to (0 = personal).
     * @return int Newly created collection ID.
     */
    public static function create(
        int $userid,
        string $title,
        string $description = '',
        int $groupid = 0
    ): int {
        global $DB;

        $now = time();
        $record = (object) [
            'userid'       => $userid,
            'groupid'      => $groupid,
            'title'        => $title,
            'description'  => $description,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Is this collection bound to a Moodle group?
     *
     * @param \stdClass $coll Collection record.
     * @return bool
     */
    public static function is_group_collection(\stdClass $coll): bool {
        return !empty($coll->groupid);
    }

    /**
     * Can the user manage the collection's metadata (rename, delete, change group)?
     *
     * Only the collection's creator (userid) has this right, even in group collections.
     *
     * @param int       $userid User to check.
     * @param \stdClass $coll   Collection record.
     * @return bool
     */
    public static function can_manage_metadata(int $userid, \stdClass $coll): bool {
        return (int) $coll->userid === $userid;
    }

    /**
     * Can the user contribute to the collection (add their own pages, reorder)?
     *
     * Creator always can; group members can too when the collection has a groupid.
     *
     * @param int       $userid User to check.
     * @param \stdClass $coll   Collection record.
     * @return bool
     */
    public static function can_contribute(int $userid, \stdClass $coll): bool {
        if ((int) $coll->userid === $userid) {
            return true;
        }
        if (!empty($coll->groupid) && groups_is_member((int) $coll->groupid, $userid)) {
            return true;
        }
        return false;
    }

    /**
     * Retrieve a single collection by ID.
     *
     * @param int $id Collection ID.
     * @return \stdClass|null The collection record, or null if not found.
     */
    public static function get(int $id): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Update an existing collection.
     *
     * @param int   $id   Collection ID.
     * @param array $data Associative array of column => value pairs.
     * @return bool True on success.
     */
    public static function update(int $id, array $data): bool {
        global $DB;

        $data['id'] = $id;
        $data['timemodified'] = time();

        return $DB->update_record(self::TABLE, (object) $data);
    }

    /**
     * Delete a collection and its page mappings.
     *
     * Does NOT delete the pages themselves.
     *
     * @param int $id Collection ID.
     * @return bool True on success.
     */
    public static function delete(int $id): bool {
        global $DB;

        $DB->delete_records(self::TABLE_PAGE, ['collectionid' => $id]);
        $DB->delete_records('local_byblos_share', ['collectionid' => $id]);

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * List all collections belonging to a user.
     *
     * @param int $userid Owner user ID.
     * @return array Array of stdClass records ordered by timecreated DESC.
     */
    public static function list_by_user(int $userid): array {
        global $DB;

        return array_values(
            $DB->get_records(self::TABLE, ['userid' => $userid], 'timecreated DESC')
        );
    }

    /**
     * List collections the user can contribute to: their own collections + group
     * collections for groups they're a member of. Each record has `is_creator`
     * and `is_group` flags set for easy UI labelling.
     *
     * @param int $userid
     * @return \stdClass[]
     */
    public static function list_contributable_for_user(int $userid): array {
        global $DB;

        $personal = self::list_by_user($userid);
        $groupcolls = [];

        $groupids = [];
        foreach (groups_get_user_groups(0, $userid) as $coursegroups) {
            foreach ($coursegroups as $gid) {
                $groupids[(int) $gid] = true;
            }
        }
        if ($groupids) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($groupids), SQL_PARAMS_NAMED, 'gid');
            $groupcolls = $DB->get_records_select(
                self::TABLE,
                "groupid $insql AND userid <> :uid",
                $params + ['uid' => $userid],
                'timecreated DESC'
            );
        }

        $out = [];
        foreach ($personal as $c) {
            $c->is_creator = true;
            $c->is_group   = !empty($c->groupid);
            $out[(int) $c->id] = $c;
        }
        foreach ($groupcolls as $c) {
            if (isset($out[(int) $c->id])) {
                continue;
            }
            $c->is_creator = false;
            $c->is_group   = true;
            $out[(int) $c->id] = $c;
        }
        return array_values($out);
    }

    /**
     * Add a page to a collection.
     *
     * Auto-marks the new row as primary when the page currently belongs to no
     * collection. When $setprimary is true and the page already has other
     * collections, the primary flag is moved to this (collection, page) row.
     *
     * @param int  $collectionid Collection ID.
     * @param int  $pageid       Page ID.
     * @param int  $sortorder    Sort position.
     * @param bool $setprimary   Force this row to become the primary collection for the page.
     * @return void
     */
    public static function add_page(
        int $collectionid,
        int $pageid,
        int $sortorder = 0,
        bool $setprimary = false
    ): void {
        global $DB;

        $existing = $DB->record_exists(self::TABLE_PAGE, [
            'collectionid' => $collectionid,
            'pageid'       => $pageid,
        ]);

        // Is this the page's very first collection entry? If so it auto-becomes primary.
        $isfirst = !$DB->record_exists(self::TABLE_PAGE, ['pageid' => $pageid]);
        $primaryflag = ($isfirst || $setprimary) ? 1 : 0;

        if (!$existing) {
            $DB->insert_record(self::TABLE_PAGE, (object) [
                'collectionid' => $collectionid,
                'pageid'       => $pageid,
                'sortorder'    => $sortorder,
                'is_primary'   => $primaryflag,
            ]);
        } else if ($setprimary) {
            // Row exists — if caller asked to promote it, flip the flag on.
            $DB->set_field(
                self::TABLE_PAGE,
                'is_primary',
                1,
                ['collectionid' => $collectionid, 'pageid' => $pageid]
            );
        }

        // If this row was flagged primary (newly or promoted), clear all other primaries for the page.
        if ($primaryflag === 1 || $setprimary) {
            self::clear_other_primaries($pageid, $collectionid);
        }
    }

    /**
     * Clear the is_primary flag on every collection_page row for a given page,
     * except the one matching $keepcollectionid.
     *
     * @param int $pageid           Page ID.
     * @param int $keepcollectionid Collection ID whose row should keep is_primary=1 (0 = clear all).
     * @return void
     */
    private static function clear_other_primaries(int $pageid, int $keepcollectionid = 0): void {
        global $DB;

        if ($keepcollectionid > 0) {
            $DB->execute(
                "UPDATE {" . self::TABLE_PAGE . "}
                    SET is_primary = 0
                  WHERE pageid = :pid AND collectionid <> :cid",
                ['pid' => $pageid, 'cid' => $keepcollectionid]
            );
        } else {
            $DB->set_field(self::TABLE_PAGE, 'is_primary', 0, ['pageid' => $pageid]);
        }
    }

    /**
     * Remove a page from a collection.
     *
     * @param int $collectionid Collection ID.
     * @param int $pageid       Page ID.
     * @return void
     */
    public static function remove_page(int $collectionid, int $pageid): void {
        global $DB;

        $DB->delete_records(self::TABLE_PAGE, ['collectionid' => $collectionid, 'pageid' => $pageid]);
    }

    /**
     * Get ordered pages in a collection.
     *
     * @param int $collectionid Collection ID.
     * @return array Array of page stdClass records ordered by sortorder.
     */
    public static function get_pages(int $collectionid): array {
        global $DB;

        $sql = "SELECT p.*, cp.sortorder
                  FROM {local_byblos_page} p
                  JOIN {local_byblos_collection_page} cp ON cp.pageid = p.id
                 WHERE cp.collectionid = :collectionid
              ORDER BY cp.sortorder ASC";

        return array_values($DB->get_records_sql($sql, ['collectionid' => $collectionid]));
    }

    /**
     * Count pages in a collection.
     *
     * @param int $collectionid Collection ID.
     * @return int Page count.
     */
    public static function count_pages(int $collectionid): int {
        global $DB;

        return $DB->count_records(self::TABLE_PAGE, ['collectionid' => $collectionid]);
    }

    /**
     * Move one page within a collection's sort order.
     *
     * @param int    $collectionid Collection ID.
     * @param int    $pageid       Page to move.
     * @param string $direction    'up', 'down', or 'top'.
     * @return void
     */
    public static function move_page(int $collectionid, int $pageid, string $direction): void {
        $pages = self::get_pages($collectionid);
        $ids = array_map(fn($p) => (int) $p->id, $pages);
        $pos = array_search($pageid, $ids, true);
        if ($pos === false) {
            return;
        }

        if ($direction === 'up' && $pos > 0) {
            [$ids[$pos - 1], $ids[$pos]] = [$ids[$pos], $ids[$pos - 1]];
        } else if ($direction === 'down' && $pos < count($ids) - 1) {
            [$ids[$pos], $ids[$pos + 1]] = [$ids[$pos + 1], $ids[$pos]];
        } else if ($direction === 'top' && $pos > 0) {
            $moved = $ids[$pos];
            array_splice($ids, $pos, 1);
            array_unshift($ids, $moved);
        } else {
            return;
        }

        $ordering = [];
        foreach ($ids as $i => $pid) {
            $ordering[$pid] = $i;
        }
        self::reorder_pages($collectionid, $ordering);
    }

    /**
     * Reorder pages within a collection.
     *
     * @param int   $collectionid Collection ID.
     * @param array $ordering     Associative array of page ID => new sortorder.
     * @return void
     */
    public static function reorder_pages(int $collectionid, array $ordering): void {
        global $DB;

        foreach ($ordering as $pageid => $sortorder) {
            $DB->set_field(
                self::TABLE_PAGE,
                'sortorder',
                (int) $sortorder,
                ['collectionid' => $collectionid, 'pageid' => (int) $pageid],
            );
        }
    }

    /**
     * Get every collection a page is currently in, with an is_primary property
     * on each record. Primary collection (if any) comes first; the rest are
     * ordered by collection_page.id ASC.
     *
     * @param int $pageid Page ID.
     * @return array Array of stdClass collection records (full columns + is_primary).
     */
    public static function get_for_page(int $pageid): array {
        global $DB;

        $sql = "SELECT c.*, cp.is_primary, cp.id AS cp_id
                  FROM {" . self::TABLE_PAGE . "} cp
                  JOIN {" . self::TABLE . "} c ON c.id = cp.collectionid
                 WHERE cp.pageid = :pageid
              ORDER BY cp.is_primary DESC, cp.id ASC";

        $rows = $DB->get_records_sql($sql, ['pageid' => $pageid]);
        foreach ($rows as $r) {
            $r->is_primary = (int) $r->is_primary;
            unset($r->cp_id);
        }
        return array_values($rows);
    }

    /**
     * Return the single primary collection record for a page, or null if none
     * exists or no row is flagged primary.
     *
     * @param int $pageid Page ID.
     * @return \stdClass|null Collection record with is_primary=1, or null.
     */
    public static function get_primary_for_page(int $pageid): ?\stdClass {
        global $DB;

        $sql = "SELECT c.*
                  FROM {" . self::TABLE_PAGE . "} cp
                  JOIN {" . self::TABLE . "} c ON c.id = cp.collectionid
                 WHERE cp.pageid = :pageid AND cp.is_primary = 1
                 LIMIT 1";

        $rec = $DB->get_record_sql($sql, ['pageid' => $pageid]);
        return $rec ?: null;
    }

    /**
     * Mark one collection as the primary for a page; clear is_primary on all
     * other (pageid, *) rows. No-op (returns false) if no such row exists.
     *
     * @param int $pageid       Page ID.
     * @param int $collectionid Collection ID to promote.
     * @return bool True if the primary flag was set, false if the mapping didn't exist.
     */
    public static function set_primary_for_page(int $pageid, int $collectionid): bool {
        global $DB;

        if (!$DB->record_exists(self::TABLE_PAGE, ['pageid' => $pageid, 'collectionid' => $collectionid])) {
            return false;
        }

        $DB->set_field(
            self::TABLE_PAGE,
            'is_primary',
            1,
            ['pageid' => $pageid, 'collectionid' => $collectionid]
        );
        self::clear_other_primaries($pageid, $collectionid);
        return true;
    }
}
