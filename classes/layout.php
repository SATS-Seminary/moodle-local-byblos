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
 * Layout definitions for portfolio pages.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides pre-defined page layout configurations based on Bootstrap 4 grid columns.
 */
class layout {

    /**
     * Returns all available layout definitions.
     *
     * Each layout is an associative array with:
     * - key: Machine name.
     * - name: Lang string key for human-readable name.
     * - description: Lang string key for description.
     * - columns: Array of Bootstrap grid column widths (sum to 12).
     * - icon: FontAwesome icon class.
     * - has_hero_row: Whether the first row is a full-width hero row.
     *
     * @return array[] All layout definitions keyed by layout key.
     */
    public static function get_all(): array {
        return [
            'single' => [
                'key' => 'single',
                'name' => 'layout_single',
                'description' => 'layout_single_desc',
                'columns' => [12],
                'icon' => 'fa-square',
                'has_hero_row' => false,
            ],
            'two-equal' => [
                'key' => 'two-equal',
                'name' => 'layout_two_equal',
                'description' => 'layout_two_equal_desc',
                'columns' => [6, 6],
                'icon' => 'fa-columns',
                'has_hero_row' => false,
            ],
            'two-wide-left' => [
                'key' => 'two-wide-left',
                'name' => 'layout_two_wide_left',
                'description' => 'layout_two_wide_left_desc',
                'columns' => [8, 4],
                'icon' => 'fa-indent',
                'has_hero_row' => false,
            ],
            'two-wide-right' => [
                'key' => 'two-wide-right',
                'name' => 'layout_two_wide_right',
                'description' => 'layout_two_wide_right_desc',
                'columns' => [4, 8],
                'icon' => 'fa-dedent',
                'has_hero_row' => false,
            ],
            'three-equal' => [
                'key' => 'three-equal',
                'name' => 'layout_three_equal',
                'description' => 'layout_three_equal_desc',
                'columns' => [4, 4, 4],
                'icon' => 'fa-th',
                'has_hero_row' => false,
            ],
            'hero-two' => [
                'key' => 'hero-two',
                'name' => 'layout_hero_two',
                'description' => 'layout_hero_two_desc',
                'columns' => [6, 6],
                'icon' => 'fa-window-maximize',
                'has_hero_row' => true,
            ],
        ];
    }

    /**
     * Returns a single layout definition by key.
     *
     * Falls back to 'single' if the key is not found.
     *
     * @param string $key The layout key.
     * @return array The layout definition.
     */
    public static function get(string $key): array {
        $all = self::get_all();
        return $all[$key] ?? $all['single'];
    }
}
