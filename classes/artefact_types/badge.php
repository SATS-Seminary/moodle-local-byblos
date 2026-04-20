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
 * Badge artefact type — auto-imported Moodle badge evidence.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badge extends artefact_type {
    /**
     * Get the machine-readable type name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'badge';
    }

    /**
     * Get the localised display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('artefacttype_badge', 'local_byblos');
    }

    /**
     * Get the icon identifier.
     *
     * @return string
     */
    public function get_icon(): string {
        return 'i/badge';
    }

    /**
     * Render a badge artefact to HTML.
     *
     * Shows the badge name with an icon and optional description.
     * If a sourceref is present, extracts the badge ID to link to
     * the core badge page.
     *
     * @param \stdClass $artefact The artefact record.
     * @return string HTML output.
     */
    public function render(\stdClass $artefact): string {
        global $OUTPUT;

        $icon = $OUTPUT->pix_icon('i/badge', get_string('badges', 'badges'));

        $title = s($artefact->title);

        // Try to link to the badge detail page if sourceref is available.
        if (!empty($artefact->sourceref) && str_starts_with($artefact->sourceref, 'badge:')) {
            $issuedid = (int) substr($artefact->sourceref, 6);
            if ($issuedid > 0) {
                $url = new \moodle_url('/badges/badge.php', ['hash' => $issuedid]);
                $title = \html_writer::link($url, $title, ['class' => 'byblos-badge-link']);
            }
        }

        $titlehtml = \html_writer::span($title, 'byblos-badge-title');
        $desc = '';
        if (!empty($artefact->description)) {
            $desc = \html_writer::tag('p', s($artefact->description), ['class' => 'byblos-badge-desc']);
        }

        return \html_writer::div(
            $icon . $titlehtml . $desc,
            'byblos-artefact byblos-artefact-badge',
        );
    }
}
