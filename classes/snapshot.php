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
 * Snapshot model — freeze a page (and its sections) at a moment in time.
 *
 * Stored as JSON so the snapshot is immune to later schema or content changes
 * on the source page. Read back by submissions when snapshot mode is active.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class snapshot {

    /** @var string Database table name. */
    private const TABLE = 'local_byblos_snapshot';

    /**
     * Capture a page and its sections into a new snapshot row.
     *
     * @param int $pageid Page ID to snapshot.
     * @return int Newly created snapshot ID.
     * @throws \coding_exception If the page does not exist.
     */
    public static function capture(int $pageid): int {
        global $DB;

        $pagerec = page::get($pageid);
        if ($pagerec === null) {
            throw new \coding_exception("Cannot snapshot missing page {$pageid}");
        }

        $payload = [
            'version'  => 1,
            'page'     => self::extract_page($pagerec),
            'sections' => self::extract_sections($pageid),
        ];

        return (int) $DB->insert_record(self::TABLE, (object) [
            'pageid'       => $pageid,
            'capturedjson' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'timecreated'  => time(),
        ]);
    }

    /**
     * Capture every page in a collection into a single multi-page snapshot row.
     *
     * Payload shape (version 2):
     *   { version: 2, collectionid, primary_pageid,
     *     pages: [ { page, sections }, ... ] }
     *
     * The snapshot row's pageid column points at the first page so existing
     * code that joins on pageid still gets a reasonable anchor.
     *
     * @param int $collectionid Collection ID to snapshot.
     * @return int Newly created snapshot ID.
     * @throws \coding_exception If the collection is missing or empty.
     */
    public static function capture_collection(int $collectionid): int {
        global $DB;

        $colpages = collection::get_pages($collectionid);
        if (empty($colpages)) {
            throw new \coding_exception("Cannot snapshot empty collection {$collectionid}");
        }

        $payload = [
            'version'        => 2,
            'collectionid'   => $collectionid,
            'primary_pageid' => (int) $colpages[0]->id,
            'pages'          => array_map(static function (\stdClass $p): array {
                return [
                    'page'     => self::extract_page($p),
                    'sections' => self::extract_sections((int) $p->id),
                ];
            }, $colpages),
        ];

        return (int) $DB->insert_record(self::TABLE, (object) [
            'pageid'       => (int) $colpages[0]->id,
            'capturedjson' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'timecreated'  => time(),
        ]);
    }

    /**
     * @param \stdClass $pagerec Page record.
     * @return array Extracted page row.
     */
    private static function extract_page(\stdClass $pagerec): array {
        return [
            'id'           => (int) $pagerec->id,
            'userid'       => (int) $pagerec->userid,
            'title'        => $pagerec->title,
            'description'  => $pagerec->description,
            'layoutkey'    => $pagerec->layoutkey,
            'themekey'     => $pagerec->themekey,
            'status'       => $pagerec->status,
            'timecreated'  => (int) $pagerec->timecreated,
            'timemodified' => (int) $pagerec->timemodified,
        ];
    }

    /**
     * @param int $pageid Page ID.
     * @return array Section rows for the page.
     */
    private static function extract_sections(int $pageid): array {
        return array_map(static function (\stdClass $s): array {
            return [
                'id'          => (int) $s->id,
                'sectiontype' => $s->sectiontype,
                'sortorder'   => (int) $s->sortorder,
                'configdata'  => $s->configdata,
                'content'     => $s->content,
            ];
        }, section::get_by_page($pageid));
    }

    /**
     * Retrieve a snapshot by ID.
     *
     * @param int $id Snapshot ID.
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Decode the captured JSON payload.
     *
     * @param int $id Snapshot ID.
     * @return array|null Decoded payload, or null if the snapshot is missing.
     */
    public static function payload(int $id): ?array {
        $rec = self::get($id);
        if ($rec === null) {
            return null;
        }
        return json_decode($rec->capturedjson, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Delete a snapshot.
     *
     * @param int $id Snapshot ID.
     * @return bool
     */
    public static function delete(int $id): bool {
        global $DB;

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }
}
