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

defined('MOODLE_INTERNAL') || die();

/**
 * Course completion artefact type — auto-imported completion evidence.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_completion extends artefact_type {

    /**
     * Get the machine-readable type name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'course_completion';
    }

    /**
     * Get the localised display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('artefacttype_course_completion', 'local_byblos');
    }

    /**
     * Get the icon identifier.
     *
     * @return string
     */
    public function get_icon(): string {
        return 'i/completion-auto-y';
    }

    /**
     * Render a course completion artefact to HTML.
     *
     * Shows a completion badge-style card with course name and a
     * check icon.
     *
     * @param \stdClass $artefact The artefact record.
     * @return string HTML output.
     */
    public function render(\stdClass $artefact): string {
        global $OUTPUT;

        $icon = $OUTPUT->pix_icon('i/completion-auto-y', get_string('completed', 'completion'));
        $title = \html_writer::span(s($artefact->title), 'byblos-completion-title');
        $desc = '';
        if (!empty($artefact->description)) {
            $desc = \html_writer::tag('p', s($artefact->description), ['class' => 'byblos-completion-desc']);
        }

        return \html_writer::div(
            $icon . $title . $desc,
            'byblos-artefact byblos-artefact-completion',
        );
    }
}
