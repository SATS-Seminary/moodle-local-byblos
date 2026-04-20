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
 * Blog entry artefact type — reflective journal / blog content.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blog_entry extends artefact_type {

    /**
     * Get the machine-readable type name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'blog_entry';
    }

    /**
     * Get the localised display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('artefacttype_blog_entry', 'local_byblos');
    }

    /**
     * Get the icon identifier.
     *
     * @return string
     */
    public function get_icon(): string {
        return 'i/rss';
    }

    /**
     * Render a blog entry artefact to HTML.
     *
     * Displays the title as a heading, description as a summary, and
     * the full content body below.
     *
     * @param \stdClass $artefact The artefact record.
     * @return string HTML output.
     */
    public function render(\stdClass $artefact): string {
        $heading = \html_writer::tag('h4', s($artefact->title), ['class' => 'byblos-blog-title']);

        $summary = '';
        if (!empty($artefact->description)) {
            $summary = \html_writer::tag('p', s($artefact->description), [
                'class' => 'byblos-blog-summary text-muted',
            ]);
        }

        $body = '';
        if (!empty($artefact->content)) {
            $body = \html_writer::div(
                format_text($artefact->content, FORMAT_HTML),
                'byblos-blog-body',
            );
        }

        return \html_writer::div(
            $heading . $summary . $body,
            'byblos-artefact byblos-artefact-blog',
        );
    }
}
