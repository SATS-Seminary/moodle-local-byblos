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
use context_user;
use moodle_url;

/**
 * External functions for the artefact picker.
 *
 * Exposes the calling user's artefact library as a filterable, searchable
 * list for use by the editor's artefact picker dialog.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class artefact_external extends external_api {
    /**
     * Valid artefact types accepted by the typefilter argument.
     */
    private const VALID_TYPES = [
        'file', 'image', 'text', 'badge', 'course_completion', 'blog_entry',
    ];

    /**
     * Parameter definition for list_artefacts.
     *
     * @return external_function_parameters
     */
    public static function list_artefacts_parameters(): external_function_parameters {
        return new external_function_parameters([
            'typefilter' => new external_value(
                PARAM_ALPHAEXT,
                'Optional artefact type filter; empty string means no filter.',
                VALUE_DEFAULT,
                ''
            ),
            'search' => new external_value(
                PARAM_RAW,
                'Optional case-insensitive substring search over title and description.',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * List the calling user's artefacts, optionally filtered by type and/or search.
     *
     * @param string $typefilter One of the VALID_TYPES keys, or empty string for no filter.
     * @param string $search     Case-insensitive substring to match against title/description.
     * @return array[] List of artefact rows (see list_artefacts_returns for shape).
     */
    public static function list_artefacts(string $typefilter = '', string $search = ''): array {
        global $DB, $USER;

        [
            'typefilter' => $typefilter,
            'search'     => $search,
        ] = self::validate_parameters(self::list_artefacts_parameters(), [
            'typefilter' => $typefilter,
            'search'     => $search,
        ]);

        $ctx = context_system::instance();
        self::validate_context($ctx);
        require_capability('local/byblos:use', $ctx);

        // Build the query. We query directly to work around a column-name
        // quirk in artefact::list_by_user (it references a `type` column
        // that does not exist on the schema), and to support the search
        // clause in a single round-trip.
        $params = ['userid' => (int) $USER->id];
        $where  = 'userid = :userid';

        if ($typefilter !== '' && in_array($typefilter, self::VALID_TYPES, true)) {
            $where .= ' AND artefacttype = :artefacttype';
            $params['artefacttype'] = $typefilter;
        }

        if ($search !== '') {
            $titlelike = $DB->sql_like('title', ':searchtitle', false);
            $desclike  = $DB->sql_like('description', ':searchdesc', false);
            $where .= " AND ({$titlelike} OR {$desclike})";
            $like = '%' . $DB->sql_like_escape($search) . '%';
            $params['searchtitle'] = $like;
            $params['searchdesc']  = $like;
        }

        $records = $DB->get_records_select(
            'local_byblos_artefact',
            $where,
            $params,
            'timecreated DESC'
        );

        $out = [];
        foreach ($records as $record) {
            $out[] = self::format_artefact($record);
        }

        return $out;
    }

    /**
     * Return structure for list_artefacts.
     *
     * @return external_multiple_structure
     */
    public static function list_artefacts_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'           => new external_value(PARAM_INT, 'Artefact ID'),
                'artefacttype' => new external_value(PARAM_ALPHAEXT, 'Artefact type key'),
                'title'        => new external_value(PARAM_RAW, 'Human-readable title (already formatted)'),
                'description'  => new external_value(PARAM_RAW, 'Short description (raw text)'),
                'url'          => new external_value(PARAM_URL, 'Direct content URL, or empty string if none'),
                'thumburl'     => new external_value(PARAM_URL, 'Thumbnail URL for images; empty string otherwise'),
                'timecreated'  => new external_value(PARAM_INT, 'Creation timestamp'),
            ])
        );
    }

    /**
     * Convert a DB record into the WS response shape.
     *
     * @param \stdClass $record A row from local_byblos_artefact.
     * @return array Associative array matching list_artefacts_returns().
     */
    private static function format_artefact(\stdClass $record): array {
        $url = self::resolve_url($record);
        $thumburl = ($record->artefacttype === 'image') ? $url : '';

        return [
            'id'           => (int) $record->id,
            'artefacttype' => (string) $record->artefacttype,
            'title'        => format_string((string) $record->title, true, ['escape' => false]),
            'description'  => (string) ($record->description ?? ''),
            'url'          => $url,
            'thumburl'     => $thumburl,
            'timecreated'  => (int) $record->timecreated,
        ];
    }

    /**
     * Derive a direct URL for an artefact based on its type and source data.
     *
     * @param \stdClass $record A row from local_byblos_artefact.
     * @return string Absolute URL string, or empty string if none resolvable.
     */
    private static function resolve_url(\stdClass $record): string {
        $type      = (string) $record->artefacttype;
        $sourceref = (string) ($record->sourceref ?? '');
        $fileid    = $record->fileid !== null ? (int) $record->fileid : null;

        switch ($type) {
            case 'image':
            case 'file':
                if ($fileid) {
                    $fs = get_file_storage();
                    $file = $fs->get_file_by_id($fileid);
                    if ($file && !$file->is_directory()) {
                        $fileurl = moodle_url::make_pluginfile_url(
                            $file->get_contextid(),
                            $file->get_component(),
                            $file->get_filearea(),
                            $file->get_itemid(),
                            $file->get_filepath(),
                            $file->get_filename(),
                        );
                        return $fileurl->out(false);
                    }
                }
                // Fallback: sourceref may hold an external URL.
                if ($sourceref !== '' && self::looks_like_url($sourceref)) {
                    return $sourceref;
                }
                return '';

            case 'text':
                return self::artefact_php_url((int) $record->id);

            case 'badge':
                if (preg_match('/^badge:(\d+)$/', $sourceref, $m)) {
                    $badgeurl = new moodle_url('/badges/badge.php', ['hash' => $m[1]]);
                    return $badgeurl->out(false);
                }
                return self::artefact_php_url((int) $record->id);

            case 'course_completion':
                if (
                    preg_match('/^course:(\d+)$/', $sourceref, $m)
                    || preg_match('/^course_completion:(\d+)$/', $sourceref, $m)
                ) {
                    $courseurl = new moodle_url('/course/view.php', ['id' => (int) $m[1]]);
                    return $courseurl->out(false);
                }
                return self::artefact_php_url((int) $record->id);

            case 'blog_entry':
                if (preg_match('/^blog(?:_entry)?:(\d+)$/', $sourceref, $m)) {
                    $blogurl = new moodle_url('/blog/index.php', ['entryid' => (int) $m[1]]);
                    return $blogurl->out(false);
                }
                return self::artefact_php_url((int) $record->id);

            default:
                return self::artefact_php_url((int) $record->id);
        }
    }

    /**
     * Build the canonical artefact.php URL for a given artefact.
     *
     * @param int $id Artefact ID.
     * @return string Absolute URL string.
     */
    private static function artefact_php_url(int $id): string {
        return (new moodle_url('/local/byblos/artefact.php', ['id' => $id]))->out(false);
    }

    /**
     * Loose check: does a string look like an HTTP(S) URL?
     *
     * @param string $value Value to inspect.
     * @return bool True if $value starts with http:// or https://.
     */
    private static function looks_like_url(string $value): bool {
        return (bool) preg_match('#^https?://#i', $value);
    }
}
