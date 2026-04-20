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

namespace local_byblos\artefact_types;

use local_byblos\artefact_type;

/**
 * Text artefact type — plain or HTML text content.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text extends artefact_type {
    /**
     * Get the machine-readable type name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'text';
    }

    /**
     * Get the localised display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('artefacttype_text', 'local_byblos');
    }

    /**
     * Get the icon identifier.
     *
     * @return string
     */
    public function get_icon(): string {
        return 'f/text';
    }

    /**
     * Render a text artefact to HTML.
     *
     * @param \stdClass $artefact The artefact record.
     * @return string HTML output.
     */
    public function render(\stdClass $artefact): string {
        return \html_writer::div(
            format_text($artefact->content, FORMAT_HTML),
            'byblos-artefact byblos-artefact-text',
        );
    }
}
