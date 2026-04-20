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
use local_byblos\page;
use local_byblos\section;
use local_byblos\section_renderer;

/**
 * External functions for section CRUD in the portfolio page editor.
 *
 * All functions validate that the calling user owns the target page
 * (or holds local/byblos:manageall) and has local/byblos:createpage.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_external extends external_api {
    // Shared helpers.

    /**
     * Valid section types matching MoodleGo definitions.
     */
    private const VALID_TYPES = [
        'hero', 'text', 'text_image', 'gallery', 'skills', 'timeline',
        'badges', 'completions', 'social', 'cta', 'divider', 'custom',
        'chart', 'cloud', 'quote', 'stats', 'citations', 'files', 'youtube',
        'pagenav',
    ];

    /**
     * Validate context, capability, and page ownership.
     *
     * @param int $pageid Page ID.
     * @return \stdClass The page record.
     * @throws \moodle_exception
     */
    private static function require_page_owner(int $pageid): \stdClass {
        global $USER;

        $ctx = context_system::instance();
        self::validate_context($ctx);
        require_capability('local/byblos:createpage', $ctx);

        $page = page::get($pageid);
        if (!$page) {
            throw new \moodle_exception('error:pagenotfound', 'local_byblos');
        }

        if ((int) $page->userid !== (int) $USER->id && !has_capability('local/byblos:manageall', $ctx)) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }

        return $page;
    }

    /**
     * Validate that the section belongs to the given page and the user owns it.
     *
     * @param int $sectionid Section ID.
     * @return array{0: \stdClass, 1: \stdClass} [section, page]
     * @throws \moodle_exception
     */
    private static function require_section_owner(int $sectionid): array {
        $sec = section::get($sectionid);
        if (!$sec) {
            throw new \moodle_exception('error:sectionnotfound', 'local_byblos');
        }
        $page = self::require_page_owner((int) $sec->pageid);
        return [$sec, $page];
    }

    /**
     * Parameters for add_section.
     *
     * @return external_function_parameters
     */
    public static function add_section_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid'      => new external_value(PARAM_INT, 'Page ID'),
            'sectiontype' => new external_value(PARAM_ALPHANUMEXT, 'Section type key'),
            'sortorder'   => new external_value(PARAM_INT, 'Desired sort position', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Add a new section to a page.
     *
     * Inserts the section with default configdata for its type and shifts
     * existing sections to make room at the requested sort position.
     *
     * @param int    $pageid
     * @param string $sectiontype
     * @param int    $sortorder
     * @return array{id: int, rendered: string}
     */
    public static function add_section(int $pageid, string $sectiontype, int $sortorder = 0): array {
        $params = self::validate_parameters(self::add_section_parameters(), [
            'pageid'      => $pageid,
            'sectiontype' => $sectiontype,
            'sortorder'   => $sortorder,
        ]);

        $pageid      = $params['pageid'];
        $sectiontype = $params['sectiontype'];
        $sortorder   = $params['sortorder'];

        if (!in_array($sectiontype, self::VALID_TYPES, true)) {
            throw new \moodle_exception('error:invalidsectiontype', 'local_byblos');
        }

        $page = self::require_page_owner($pageid);

        // Shift existing sections at or after the insert position.
        $existing = section::list_for_page($pageid);
        $reorder = [];
        foreach ($existing as $s) {
            if ((int) $s->sortorder >= $sortorder) {
                $reorder[(int) $s->id] = (int) $s->sortorder + 1;
            }
        }
        if (!empty($reorder)) {
            section::reorder($reorder);
        }

        // Get default config for the type.
        $configdata = self::default_config_for_type($sectiontype);

        $id = section::add($pageid, $sectiontype, $sortorder, $configdata);

        // Render the new section.
        $sec = section::get($id);
        $rendered = section_renderer::render($sec, $page->themekey);

        return [
            'id'       => $id,
            'rendered' => $rendered,
        ];
    }

    /**
     * Return definition for add_section.
     *
     * @return external_single_structure
     */
    public static function add_section_returns(): external_single_structure {
        return new external_single_structure([
            'id'       => new external_value(PARAM_INT, 'New section ID'),
            'rendered' => new external_value(PARAM_RAW, 'Rendered HTML for the section'),
        ]);
    }

    /**
     * Parameters for update_section.
     *
     * @return external_function_parameters
     */
    public static function update_section_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sectionid'  => new external_value(PARAM_INT, 'Section ID'),
            'configdata' => new external_value(PARAM_RAW, 'JSON configdata', VALUE_DEFAULT, '{}'),
            'content'    => new external_value(PARAM_RAW, 'Content body', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Update a section's configdata and content.
     *
     * @param int    $sectionid
     * @param string $configdata
     * @param string $content
     * @return array{rendered: string}
     */
    public static function update_section(int $sectionid, string $configdata = '{}', string $content = ''): array {
        $params = self::validate_parameters(self::update_section_parameters(), [
            'sectionid'  => $sectionid,
            'configdata' => $configdata,
            'content'    => $content,
        ]);

        [$sec, $page] = self::require_section_owner($params['sectionid']);

        // Validate JSON.
        $decoded = json_decode($params['configdata']);
        if ($decoded === null && $params['configdata'] !== '{}' && $params['configdata'] !== 'null') {
            throw new \moodle_exception('error:invalidparam', 'local_byblos');
        }

        section::update((int) $sec->id, [
            'configdata' => $params['configdata'],
            'content'    => $params['content'],
        ]);

        // Re-fetch to render.
        $sec = section::get((int) $sec->id);
        $rendered = section_renderer::render($sec, $page->themekey);

        return [
            'rendered' => $rendered,
        ];
    }

    /**
     * Return definition for update_section.
     *
     * @return external_single_structure
     */
    public static function update_section_returns(): external_single_structure {
        return new external_single_structure([
            'rendered' => new external_value(PARAM_RAW, 'Rendered HTML for the updated section'),
        ]);
    }

    /**
     * Parameters for delete_section.
     *
     * @return external_function_parameters
     */
    public static function delete_section_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sectionid' => new external_value(PARAM_INT, 'Section ID'),
        ]);
    }

    /**
     * Delete a section.
     *
     * @param int $sectionid
     * @return array{success: bool}
     */
    public static function delete_section(int $sectionid): array {
        $params = self::validate_parameters(self::delete_section_parameters(), [
            'sectionid' => $sectionid,
        ]);

        [$sec, $page] = self::require_section_owner($params['sectionid']);

        section::delete((int) $sec->id);

        return ['success' => true];
    }

    /**
     * Return definition for delete_section.
     *
     * @return external_single_structure
     */
    public static function delete_section_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True on success'),
        ]);
    }

    /**
     * Parameters for reorder_sections.
     *
     * @return external_function_parameters
     */
    public static function reorder_sections_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid'   => new external_value(PARAM_INT, 'Page ID'),
            'ordering' => new external_value(PARAM_RAW, 'JSON array of {sectionid, sortorder} objects'),
        ]);
    }

    /**
     * Reorder sections within a page.
     *
     * @param int    $pageid
     * @param string $ordering JSON string
     * @return array{success: bool}
     */
    public static function reorder_sections(int $pageid, string $ordering): array {
        $params = self::validate_parameters(self::reorder_sections_parameters(), [
            'pageid'   => $pageid,
            'ordering' => $ordering,
        ]);

        self::require_page_owner($params['pageid']);

        $items = json_decode($params['ordering'], true);
        if (!is_array($items)) {
            throw new \moodle_exception('error:invalidparam', 'local_byblos');
        }

        $reorder = [];
        foreach ($items as $item) {
            if (!isset($item['sectionid']) || !isset($item['sortorder'])) {
                continue;
            }
            $reorder[(int) $item['sectionid']] = (int) $item['sortorder'];
        }

        if (!empty($reorder)) {
            section::reorder($reorder);
        }

        return ['success' => true];
    }

    /**
     * Return definition for reorder_sections.
     *
     * @return external_single_structure
     */
    public static function reorder_sections_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True on success'),
        ]);
    }

    /**
     * Parameters for save_page_settings.
     *
     * @return external_function_parameters
     */
    public static function save_page_settings_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid'    => new external_value(PARAM_INT, 'Page ID'),
            'layoutkey' => new external_value(PARAM_ALPHANUMEXT, 'Layout key', VALUE_DEFAULT, null, NULL_ALLOWED),
            'themekey'  => new external_value(PARAM_ALPHANUMEXT, 'Theme key', VALUE_DEFAULT, null, NULL_ALLOWED),
            'title'     => new external_value(PARAM_TEXT, 'Page title', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    /**
     * Save page layout, theme and/or title.
     *
     * Any parameter passed as null is left unchanged; callers only need to
     * supply the fields they want to update.
     *
     * @param int         $pageid
     * @param string|null $layoutkey
     * @param string|null $themekey
     * @param string|null $title
     * @return array{success: bool}
     */
    public static function save_page_settings(
        int $pageid,
        ?string $layoutkey = null,
        ?string $themekey = null,
        ?string $title = null
    ): array {
        $params = self::validate_parameters(self::save_page_settings_parameters(), [
            'pageid'    => $pageid,
            'layoutkey' => $layoutkey,
            'themekey'  => $themekey,
            'title'     => $title,
        ]);

        self::require_page_owner($params['pageid']);

        $updates = [];
        if ($params['layoutkey'] !== null) {
            $updates['layoutkey'] = $params['layoutkey'];
        }
        if ($params['themekey'] !== null) {
            $updates['themekey'] = $params['themekey'];
        }
        if ($params['title'] !== null) {
            $trim = trim($params['title']);
            if ($trim === '') {
                throw new \moodle_exception('error:invalidparam', 'local_byblos');
            }
            // Cap at schema length (local_byblos_page.title: CHAR 255).
            $updates['title'] = \core_text::substr($trim, 0, 255);
        }
        if (!empty($updates)) {
            page::update($params['pageid'], $updates);
        }

        return ['success' => true];
    }

    /**
     * Return definition for save_page_settings.
     *
     * @return external_single_structure
     */
    public static function save_page_settings_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True on success'),
        ]);
    }
    // Default configdata helper.

    /**
     * Return default configdata JSON for a section type.
     *
     * Mirrors MoodleGo's DefaultConfigForSectionType().
     *
     * @param string $stype Section type key.
     * @return string JSON string.
     */
    private static function default_config_for_type(string $stype): string {
        // phpcs:disable moodle.Files.LineLength
        $defaults = [
            'hero'        => '{"name":"Your Name","title":"Your Title","subtitle":"A short tagline","bg_color":"#2c3e50","bg_image":"","photo_url":""}',
            'text'        => '{"heading":"Section Heading","body":"<p>Add your content here...</p>"}',
            'text_image'  => '{"heading":"","body":"<p>Describe your work here...</p>","image_url":"","image_alt":"","reversed":false}',
            'gallery'     => '{"columns":3,"items":[]}',
            'skills'      => '{"heading":"Skills","skills":[{"name":"Skill 1","level":75},{"name":"Skill 2","level":60}]}',
            'timeline'    => '{"heading":"Timeline","items":[{"date":"2024","title":"Event Title","description":"Description of the event"}]}',
            'badges'      => '{"heading":"My Badges","show":true}',
            'completions' => '{"heading":"Completed Courses","show":true}',
            'social'      => '{"links":[{"platform":"linkedin","url":""},{"platform":"github","url":""},{"platform":"twitter","url":""}]}',
            'cta'         => '{"heading":"Get in Touch","body":"I am open to opportunities and collaboration.","button_text":"Contact Me","button_url":"#","bg_color":"#0d6efd"}',
            'divider'     => '{"style":"line","spacing":"2rem"}',
            'custom'      => '{}',
            'chart'       => '{"heading":"Chart","type":"bar","color":"#0d6efd","items":[{"label":"A","value":40},{"label":"B","value":72},{"label":"C","value":55}]}',
            'cloud'       => '{"heading":"Key terms","color":"#0d6efd","items":[{"text":"reflection","weight":8},{"text":"evidence","weight":6},{"text":"learning","weight":9}]}',
            'quote'       => '{"body":"<p>Add a meaningful quotation here.</p>","attribution":"","source":""}',
            'stats'       => '{"heading":"At a glance","items":[{"number":"42","label":"Sessions","description":""},{"number":"6 mo","label":"Project duration","description":""},{"number":"98%","label":"Satisfaction","description":""}]}',
            'citations'   => '{"heading":"References","style":"apa","items":[]}',
            'files'       => '{"heading":"Files","display":"list","items":[]}',
            'youtube'     => '{"heading":"","url":"","description":"","start":0,"alignment":"full","body":""}',
            'pagenav'     => '{"heading":"Related pages","source":"collection","collectionid":0,"pageids":[],"display":"pills","show_descriptions":false}',
        ];
        // phpcs:enable moodle.Files.LineLength

        return $defaults[$stype] ?? '{}';
    }
}
