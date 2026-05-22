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

/**
 * External function: live-preview a chart section while the user is editing it.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Renders a chart section from raw configdata JSON and returns the resulting
 * HTML so the editor can show a live preview as the user edits.
 *
 * Same code path as the public renderer — guaranteed identical output to the
 * published page.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_preview_external extends external_api {
    /**
     * Parameters for render_chart_preview.
     *
     * @return external_function_parameters
     */
    public static function render_chart_preview_parameters(): external_function_parameters {
        return new external_function_parameters([
            'configdata' => new external_value(PARAM_RAW, 'Chart configdata as JSON string'),
        ]);
    }

    /**
     * Decode the configdata and run it through the chart renderer.
     *
     * @param string $configdata JSON-encoded chart configdata.
     * @return array {html: string}
     */
    public static function render_chart_preview(string $configdata): array {
        self::validate_parameters(self::render_chart_preview_parameters(), [
            'configdata' => $configdata,
        ]);
        self::validate_context(\context_system::instance());
        require_capability('local/byblos:createpage', \context_system::instance());

        $config = json_decode($configdata, true);
        if (!is_array($config)) {
            $config = [];
        }
        return ['html' => \local_byblos\section_helpers::render_chart($config)];
    }

    /**
     * Return definition for render_chart_preview.
     *
     * @return external_single_structure
     */
    public static function render_chart_preview_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered chart HTML'),
        ]);
    }
}
