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
 * Visual theme definitions for portfolio pages.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides pre-defined visual themes that can be applied to portfolio pages.
 *
 * Each theme defines colour scheme, typography, and accent colours.
 * CSS classes are scoped to .byblos-theme-{key} in styles.css.
 */
class theme {

    /**
     * Returns all available theme definitions.
     *
     * Each theme is an associative array with:
     * - key: Machine name.
     * - name: Lang string key for human-readable name.
     * - description: Lang string key for description.
     * - css_class: CSS class added to the page wrapper.
     * - accent_color: Primary accent hex colour.
     * - bg_color: Background hex colour.
     * - text_color: Main text hex colour.
     *
     * @return array[] All theme definitions keyed by theme key.
     */
    public static function get_all(): array {
        return [
            'clean' => [
                'key' => 'clean',
                'name' => 'theme_clean',
                'description' => 'theme_clean_desc',
                'css_class' => 'byblos-theme-clean',
                'accent_color' => '#0d6efd',
                'bg_color' => '#ffffff',
                'text_color' => '#212529',
            ],
            'academic' => [
                'key' => 'academic',
                'name' => 'theme_academic',
                'description' => 'theme_academic_desc',
                'css_class' => 'byblos-theme-academic',
                'accent_color' => '#1a365d',
                'bg_color' => '#fdf6e3',
                'text_color' => '#3d3d3d',
            ],
            'modern-dark' => [
                'key' => 'modern-dark',
                'name' => 'theme_modern_dark',
                'description' => 'theme_modern_dark_desc',
                'css_class' => 'byblos-theme-modern-dark',
                'accent_color' => '#f38ba8',
                'bg_color' => '#1e1e2e',
                'text_color' => '#cdd6f4',
            ],
            'creative' => [
                'key' => 'creative',
                'name' => 'theme_creative',
                'description' => 'theme_creative_desc',
                'css_class' => 'byblos-theme-creative',
                'accent_color' => '#7c3aed',
                'bg_color' => '#ffffff',
                'text_color' => '#1e293b',
            ],
            'corporate' => [
                'key' => 'corporate',
                'name' => 'theme_corporate',
                'description' => 'theme_corporate_desc',
                'css_class' => 'byblos-theme-corporate',
                'accent_color' => '#0ea5e9',
                'bg_color' => '#ffffff',
                'text_color' => '#334155',
            ],
            'streaming' => [
                'key' => 'streaming',
                'name' => 'theme_streaming',
                'description' => 'theme_streaming_desc',
                'css_class' => 'byblos-theme-streaming',
                'accent_color' => '#00d4aa',
                'bg_color' => '#0d0d0d',
                'text_color' => '#e5e5e5',
            ],
        ];
    }

    /**
     * Returns a single theme definition by key.
     *
     * Falls back to 'clean' if the key is not found.
     *
     * @param string $key The theme key.
     * @return array The theme definition.
     */
    public static function get(string $key): array {
        $all = self::get_all();
        return $all[$key] ?? $all['clean'];
    }

    /**
     * Returns the accent colour for a given theme key.
     *
     * Useful for inline styling of progress bars, timeline dots, etc.
     *
     * @param string $key The theme key.
     * @return string Hex colour string.
     */
    public static function get_accent_color(string $key): string {
        $theme = self::get($key);
        return $theme['accent_color'];
    }
}
