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
 * Section model — CRUD for page sections (content blocks).
 *
 * Each section belongs to a page and has a type, sort order, and content.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section {
    /** @var string Database table name. */
    private const TABLE = 'local_byblos_section';

    /**
     * Default configuration per section type.
     *
     * @var array<string, string>
     */
    private const DEFAULT_CONFIGS = [
        'text'           => '{"format":"html"}',
        'artefact_list'  => '{"columns":2,"show_description":true}',
        'image_gallery'  => '{"columns":3,"lightbox":true}',
        'badge_showcase' => '{"layout":"grid"}',
        'embed'          => '{"provider":"oembed"}',
        'heading'        => '{"level":2}',
    ];

    /**
     * Add a new section to a page.
     *
     * @param int    $pageid      Page ID.
     * @param string $sectiontype Section type key.
     * @param int    $sortorder   Position within the page.
     * @param string $configdata  JSON configuration data.
     * @param string $content     Section body content.
     * @return int Newly created section ID.
     */
    public static function add(
        int $pageid,
        string $sectiontype,
        int $sortorder,
        string $configdata = '{}',
        string $content = '',
    ): int {
        return self::create($pageid, $sectiontype, $sortorder, $content, $configdata);
    }

    /**
     * Create a new section within a page.
     *
     * @param int    $pageid      Page ID.
     * @param string $sectiontype Section type (text, image, artefact_list, etc.).
     * @param int    $sortorder   Sort position.
     * @param string $content     Section content (HTML).
     * @param string $configdata  JSON configuration data.
     * @return int Newly created section ID.
     */
    public static function create(
        int $pageid,
        string $sectiontype,
        int $sortorder = 0,
        string $content = '',
        string $configdata = '',
    ): int {
        global $DB;

        $now = time();
        $record = (object) [
            'pageid'       => $pageid,
            'sectiontype'  => $sectiontype,
            'sortorder'    => $sortorder,
            'configdata'   => $configdata,
            'content'      => $content,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Retrieve a single section by ID.
     *
     * @param int $id Section ID.
     * @return \stdClass|null The section record, or null if not found.
     */
    public static function get(int $id): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Update an existing section.
     *
     * @param int   $id   Section ID.
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
     * Delete a section.
     *
     * @param int $id Section ID.
     * @return bool True on success.
     */
    public static function delete(int $id): bool {
        global $DB;

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * List all sections for a page, ordered by sortorder.
     *
     * @param int $pageid Page ID.
     * @return array Array of stdClass records.
     */
    public static function list_for_page(int $pageid): array {
        global $DB;

        return array_values(
            $DB->get_records(self::TABLE, ['pageid' => $pageid], 'sortorder ASC')
        );
    }

    /**
     * Alias for list_for_page.
     *
     * @param int $pageid Page ID.
     * @return array Array of stdClass records.
     */
    public static function get_by_page(int $pageid): array {
        return self::list_for_page($pageid);
    }

    /**
     * Reorder sections by updating their sortorder values.
     *
     * @param array $ordering Associative array of section ID => new sortorder.
     * @return void
     */
    public static function reorder(array $ordering): void {
        global $DB;

        foreach ($ordering as $id => $sortorder) {
            $DB->set_field(self::TABLE, 'sortorder', (int) $sortorder, ['id' => (int) $id]);
        }
    }

    /**
     * Get the default JSON configuration for a section type.
     *
     * @param string $sectiontype Section type key.
     * @return string JSON string with default configuration.
     */
    public static function get_default_config(string $sectiontype): string {
        return self::DEFAULT_CONFIGS[$sectiontype] ?? '{}';
    }

    /**
     * Delete all sections belonging to a page.
     *
     * @param int $pageid Page ID.
     * @return bool True on success.
     */
    public static function delete_by_page(int $pageid): bool {
        global $DB;

        return $DB->delete_records(self::TABLE, ['pageid' => $pageid]);
    }
}
