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
use local_byblos\collection;
use local_byblos\page;

/**
 * External functions for the multi-page portfolio collection feature.
 *
 * Used by the editor "Add to collection" control (Layer 3), the page-picker
 * for the pagenav widget (Layer 2), and the Layer 1 nav strip. All endpoints
 * are scoped to the current user — cross-user ownership is rejected.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collection_external extends external_api {
    /**
     * Ensure the given collection exists and belongs to the current user.
     *
     * @param int $collectionid Collection ID.
     * @return \stdClass Collection record.
     * @throws \moodle_exception
     */
    private static function require_owned_collection(int $collectionid): \stdClass {
        global $USER;

        $c = collection::get($collectionid);
        if (!$c || (int) $c->userid !== (int) $USER->id) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }
        return $c;
    }

    /**
     * Ensure the given page exists and belongs to the current user.
     *
     * @param int $pageid Page ID.
     * @return \stdClass Page record.
     * @throws \moodle_exception
     */
    private static function require_owned_page(int $pageid): \stdClass {
        global $USER;

        $p = page::get($pageid);
        if (!$p || (int) $p->userid !== (int) $USER->id) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }
        return $p;
    }

    /**
     * Return the current primary-collection id for a page (0 if none).
     *
     * @param int $pageid Page ID.
     * @return int
     */
    private static function primary_id_for_page(int $pageid): int {
        $primary = collection::get_primary_for_page($pageid);
        return $primary ? (int) $primary->id : 0;
    }

    /**
     * Parameter definition for list_user_collections.
     *
     * @return external_function_parameters
     */
    public static function list_user_collections_parameters(): external_function_parameters {
        return new external_function_parameters([
            'withpageid' => new external_value(
                PARAM_INT,
                'Optional page ID; when > 0 each row also reports contains_page + is_primary for that page.',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * List all collections owned by the current user.
     *
     * @param int $withpageid When > 0, report contains_page + is_primary relative to this page.
     * @return array[]
     */
    public static function list_user_collections(int $withpageid = 0): array {
        global $USER, $DB;

        self::validate_parameters(self::list_user_collections_parameters(), [
            'withpageid' => $withpageid,
        ]);
        self::validate_context(context_system::instance());
        require_capability('local/byblos:use', context_system::instance());

        // Personal collections + any group collections the user can contribute to.
        $collections = collection::list_contributable_for_user((int) $USER->id);

        // Pre-fetch membership + primary flags for the requested page, if any.
        $membership = [];
        $primarycollectionid = 0;
        if ($withpageid > 0) {
            // Verify the page is owned by the caller — we don't leak existence
            // of pages the user doesn't own.
            self::require_owned_page($withpageid);

            $rows = $DB->get_records('local_byblos_collection_page', ['pageid' => $withpageid]);
            foreach ($rows as $r) {
                $membership[(int) $r->collectionid] = (int) $r->is_primary;
                if ((int) $r->is_primary === 1) {
                    $primarycollectionid = (int) $r->collectionid;
                }
            }
        }

        $groupnames = [];
        $groupids = array_unique(array_filter(array_map(fn($c) => (int) ($c->groupid ?? 0), $collections)));
        if ($groupids) {
            [$insql, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'gid');
            foreach ($DB->get_records_select('groups', "id $insql", $params, '', 'id, name') as $g) {
                $groupnames[(int) $g->id] = format_string($g->name);
            }
        }

        $out = [];
        foreach ($collections as $c) {
            $cid = (int) $c->id;
            $contains = $withpageid > 0 ? array_key_exists($cid, $membership) : false;
            $isprimary = $withpageid > 0 && $contains ? ($membership[$cid] === 1) : false;

            $out[] = [
                'id'            => $cid,
                'title'         => format_string($c->title, true, ['escape' => false]),
                'description'   => (string) ($c->description ?? ''),
                'pagecount'     => collection::count_pages($cid),
                'contains_page' => $contains,
                'is_primary'    => $isprimary,
                'is_group'      => !empty($c->groupid),
                'is_creator'    => !empty($c->is_creator),
                'group_name'    => !empty($c->groupid) ? ($groupnames[(int) $c->groupid] ?? '') : '',
            ];
        }
        return $out;
    }

    /**
     * Return structure for list_user_collections.
     *
     * @return external_multiple_structure
     */
    public static function list_user_collections_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'            => new external_value(PARAM_INT, 'Collection ID'),
                'title'         => new external_value(PARAM_RAW, 'Collection title'),
                'description'   => new external_value(PARAM_RAW, 'Collection description'),
                'pagecount'     => new external_value(PARAM_INT, 'Number of pages in the collection'),
                'contains_page' => new external_value(PARAM_BOOL, 'Whether withpageid belongs to this collection'),
                'is_primary'    => new external_value(PARAM_BOOL, 'Whether this is the primary collection for withpageid'),
                'is_group'      => new external_value(PARAM_BOOL, 'Whether the collection is bound to a Moodle group'),
                'is_creator'    => new external_value(PARAM_BOOL, 'Whether the caller created this collection'),
                'group_name'    => new external_value(PARAM_RAW, 'Name of the bound group (empty if personal)'),
            ])
        );
    }

    /**
     * Parameter definition for list_user_pages.
     *
     * @return external_function_parameters
     */
    public static function list_user_pages_parameters(): external_function_parameters {
        return new external_function_parameters([
            'excludepageid' => new external_value(
                PARAM_INT,
                'Optional page ID to exclude (e.g. the page owning a pagenav widget).',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * List all pages owned by the current user, ordered by timemodified DESC.
     *
     * @param int $excludepageid Page ID to omit from the list (> 0 to use).
     * @return array[]
     */
    public static function list_user_pages(int $excludepageid = 0): array {
        global $USER, $DB;

        self::validate_parameters(self::list_user_pages_parameters(), [
            'excludepageid' => $excludepageid,
        ]);
        self::validate_context(context_system::instance());
        require_capability('local/byblos:use', context_system::instance());

        $params = ['userid' => (int) $USER->id];
        $where = 'userid = :userid';
        if ($excludepageid > 0) {
            $where .= ' AND id <> :exclude';
            $params['exclude'] = $excludepageid;
        }

        $rows = $DB->get_records_select(
            'local_byblos_page',
            $where,
            $params,
            'timemodified DESC'
        );

        $out = [];
        foreach ($rows as $p) {
            $out[] = [
                'id'          => (int) $p->id,
                'title'       => format_string($p->title, true, ['escape' => false]),
                'themekey'    => (string) $p->themekey,
                'status'      => (string) $p->status,
                'timecreated' => (int) $p->timecreated,
            ];
        }
        return $out;
    }

    /**
     * Return structure for list_user_pages.
     *
     * @return external_multiple_structure
     */
    public static function list_user_pages_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'          => new external_value(PARAM_INT, 'Page ID'),
                'title'       => new external_value(PARAM_RAW, 'Page title'),
                'themekey'    => new external_value(PARAM_ALPHANUMEXT, 'Theme key'),
                'status'      => new external_value(PARAM_ALPHA, 'Page status'),
                'timecreated' => new external_value(PARAM_INT, 'Created timestamp'),
            ])
        );
    }

    /**
     * Parameter definition for add_page_to_collection.
     *
     * @return external_function_parameters
     */
    public static function add_page_to_collection_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid'       => new external_value(PARAM_INT, 'Page ID'),
            'collectionid' => new external_value(PARAM_INT, 'Collection ID'),
            'setprimary'   => new external_value(
                PARAM_BOOL,
                'Force this collection to become the primary for the page.',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Add a page to a collection. Auto-primary rules:
     *  - If this is the page's first collection, the new row becomes primary.
     *  - If $setprimary is true, this row becomes primary and any other
     *    (pageid, *) row has is_primary cleared.
     *
     * @param int  $pageid
     * @param int  $collectionid
     * @param bool $setprimary
     * @return array{ok:bool, primary_collectionid:int}
     */
    public static function add_page_to_collection(int $pageid, int $collectionid, bool $setprimary = false): array {
        self::validate_parameters(self::add_page_to_collection_parameters(), [
            'pageid'       => $pageid,
            'collectionid' => $collectionid,
            'setprimary'   => $setprimary,
        ]);
        self::validate_context(context_system::instance());
        require_capability('local/byblos:use', context_system::instance());

        self::require_owned_page($pageid);
        self::require_owned_collection($collectionid);

        collection::add_page($collectionid, $pageid, 0, $setprimary);

        return [
            'ok'                   => true,
            'primary_collectionid' => self::primary_id_for_page($pageid),
        ];
    }

    /**
     * Return structure for add_page_to_collection.
     *
     * @return external_single_structure
     */
    public static function add_page_to_collection_returns(): external_single_structure {
        return new external_single_structure([
            'ok'                   => new external_value(PARAM_BOOL, 'Success'),
            'primary_collectionid' => new external_value(PARAM_INT, 'Primary collection ID after op (0 if none)'),
        ]);
    }

    /**
     * Parameter definition for remove_page_from_collection.
     *
     * @return external_function_parameters
     */
    public static function remove_page_from_collection_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid'       => new external_value(PARAM_INT, 'Page ID'),
            'collectionid' => new external_value(PARAM_INT, 'Collection ID'),
        ]);
    }

    /**
     * Remove a page from a collection. If the removed row was primary, pick
     * another collection for the page (most recent by collection_page.id) and
     * promote it to primary.
     *
     * @param int $pageid
     * @param int $collectionid
     * @return array{ok:bool, primary_collectionid:int}
     */
    public static function remove_page_from_collection(int $pageid, int $collectionid): array {
        global $DB;

        self::validate_parameters(self::remove_page_from_collection_parameters(), [
            'pageid'       => $pageid,
            'collectionid' => $collectionid,
        ]);
        self::validate_context(context_system::instance());
        require_capability('local/byblos:use', context_system::instance());

        self::require_owned_page($pageid);
        self::require_owned_collection($collectionid);

        $row = $DB->get_record('local_byblos_collection_page', [
            'pageid'       => $pageid,
            'collectionid' => $collectionid,
        ]);
        $wasprimary = $row && (int) $row->is_primary === 1;

        collection::remove_page($collectionid, $pageid);

        // If we removed the primary, promote the most recent remaining row (highest id).
        if ($wasprimary) {
            $next = $DB->get_records(
                'local_byblos_collection_page',
                ['pageid' => $pageid],
                'id DESC',
                '*',
                0,
                1
            );
            if (!empty($next)) {
                $pick = reset($next);
                collection::set_primary_for_page($pageid, (int) $pick->collectionid);
            }
        }

        return [
            'ok'                   => true,
            'primary_collectionid' => self::primary_id_for_page($pageid),
        ];
    }

    /**
     * Return structure for remove_page_from_collection.
     *
     * @return external_single_structure
     */
    public static function remove_page_from_collection_returns(): external_single_structure {
        return new external_single_structure([
            'ok'                   => new external_value(PARAM_BOOL, 'Success'),
            'primary_collectionid' => new external_value(PARAM_INT, 'Primary collection ID after op (0 if none)'),
        ]);
    }

    /**
     * Parameter definition for set_primary_collection.
     *
     * @return external_function_parameters
     */
    public static function set_primary_collection_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid'       => new external_value(PARAM_INT, 'Page ID'),
            'collectionid' => new external_value(PARAM_INT, 'Collection ID to promote'),
        ]);
    }

    /**
     * Mark a (pageid, collectionid) row as the primary; clear all others for the page.
     *
     * @param int $pageid
     * @param int $collectionid
     * @return array{ok:bool, primary_collectionid:int}
     */
    public static function set_primary_collection(int $pageid, int $collectionid): array {
        self::validate_parameters(self::set_primary_collection_parameters(), [
            'pageid'       => $pageid,
            'collectionid' => $collectionid,
        ]);
        self::validate_context(context_system::instance());
        require_capability('local/byblos:use', context_system::instance());

        self::require_owned_page($pageid);
        self::require_owned_collection($collectionid);

        if (!collection::set_primary_for_page($pageid, $collectionid)) {
            throw new \moodle_exception('error:pagenotincollection', 'local_byblos');
        }

        return [
            'ok'                   => true,
            'primary_collectionid' => self::primary_id_for_page($pageid),
        ];
    }

    /**
     * Return structure for set_primary_collection.
     *
     * @return external_single_structure
     */
    public static function set_primary_collection_returns(): external_single_structure {
        return new external_single_structure([
            'ok'                   => new external_value(PARAM_BOOL, 'Success'),
            'primary_collectionid' => new external_value(PARAM_INT, 'Primary collection ID after op (0 if none)'),
        ]);
    }

    /**
     * Parameter definition for create_collection.
     *
     * @return external_function_parameters
     */
    public static function create_collection_parameters(): external_function_parameters {
        return new external_function_parameters([
            'title'       => new external_value(PARAM_TEXT, 'Collection title'),
            'description' => new external_value(PARAM_RAW, 'Collection description', VALUE_DEFAULT, ''),
            'addpageid'   => new external_value(
                PARAM_INT,
                'Optional page to add on creation (auto-primary if page has no other collection).',
                VALUE_DEFAULT,
                0
            ),
            'groupid'     => new external_value(
                PARAM_INT,
                'Optional Moodle group ID to bind the collection to (caller must be a member). 0 = personal.',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Create a collection owned by the current user. If $addpageid > 0, also
     * add that page to the new collection (auto-promoting to primary when it
     * is the page's first collection membership).
     *
     * @param string $title
     * @param string $description
     * @param int    $addpageid
     * @param int    $groupid
     * @return array{collectionid:int, primary_collectionid:int}
     */
    public static function create_collection(
        string $title,
        string $description = '',
        int $addpageid = 0,
        int $groupid = 0
    ): array {
        global $USER;

        self::validate_parameters(self::create_collection_parameters(), [
            'title'       => $title,
            'description' => $description,
            'addpageid'   => $addpageid,
            'groupid'     => $groupid,
        ]);
        self::validate_context(context_system::instance());
        require_capability('local/byblos:use', context_system::instance());

        $title = trim($title);
        if ($title === '') {
            throw new \moodle_exception('error:invalidtitle', 'local_byblos');
        }

        if ($groupid > 0 && !groups_is_member($groupid, (int) $USER->id)) {
            throw new \moodle_exception('error:notgroupmember', 'local_byblos');
        }

        $collectionid = collection::create((int) $USER->id, $title, $description, $groupid);

        $primarycollectionid = 0;
        if ($addpageid > 0) {
            self::require_owned_page($addpageid);
            // Add_page() auto-marks primary when this is the page's first collection.
            collection::add_page($collectionid, $addpageid, 0, false);
            $primarycollectionid = self::primary_id_for_page($addpageid);
        }

        return [
            'collectionid'         => (int) $collectionid,
            'primary_collectionid' => $primarycollectionid,
        ];
    }

    /**
     * Return structure for create_collection.
     *
     * @return external_single_structure
     */
    public static function create_collection_returns(): external_single_structure {
        return new external_single_structure([
            'collectionid'         => new external_value(PARAM_INT, 'New collection ID'),
            'primary_collectionid' => new external_value(PARAM_INT, 'Primary collection ID for the added page (0 if none)'),
        ]);
    }

    /**
     * Parameter definition for list_user_groups.
     *
     * @return external_function_parameters
     */
    public static function list_user_groups_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return the Moodle groups the current user is a member of, for the
     * "Share with group" picker in the create-collection UI.
     *
     * @return array[]
     */
    public static function list_user_groups(): array {
        global $USER, $DB;

        self::validate_context(context_system::instance());
        require_capability('local/byblos:use', context_system::instance());

        $ids = [];
        foreach (groups_get_user_groups(0, (int) $USER->id) as $coursegroups) {
            foreach ($coursegroups as $gid) {
                $ids[(int) $gid] = true;
            }
        }
        if (!$ids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($ids), SQL_PARAMS_NAMED, 'gid');
        $groups = $DB->get_records_select(
            'groups',
            "id $insql",
            $params,
            'name ASC',
            'id, courseid, name'
        );

        $courseids = array_unique(array_map(fn($g) => (int) $g->courseid, $groups));
        $courses = [];
        if ($courseids) {
            [$cinsql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            foreach ($DB->get_records_select('course', "id $cinsql", $cparams, '', 'id, shortname') as $c) {
                $courses[(int) $c->id] = format_string($c->shortname);
            }
        }

        return array_values(array_map(static function (\stdClass $g) use ($courses): array {
            return [
                'id'         => (int) $g->id,
                'name'       => format_string($g->name),
                'courseid'   => (int) $g->courseid,
                'coursecode' => $courses[(int) $g->courseid] ?? '',
            ];
        }, $groups));
    }

    /**
     * Return structure for list_user_groups.
     *
     * @return external_multiple_structure
     */
    public static function list_user_groups_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'         => new external_value(PARAM_INT, 'Group ID'),
                'name'       => new external_value(PARAM_RAW, 'Group name'),
                'courseid'   => new external_value(PARAM_INT, 'Course ID the group belongs to'),
                'coursecode' => new external_value(PARAM_RAW, 'Course shortname for disambiguation'),
            ])
        );
    }
}
