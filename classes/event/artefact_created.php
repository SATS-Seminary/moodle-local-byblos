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
 * Event: artefact created.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos\event;

use core\event\base;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Fired when a user creates a new artefact.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class artefact_created extends base {

    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['objecttable'] = 'local_byblos_artefact';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Return the event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_artefact_created', 'local_byblos');
    }

    /**
     * Return the event description.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' created an artefact with id '{$this->objectid}'.";
    }

    /**
     * Return the URL to the artefact.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url('/local/byblos/artefact.php', ['id' => $this->objectid]);
    }

    /**
     * Return the mapping of objectid.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'local_byblos_artefact', 'restore' => base::NOT_MAPPED];
    }
}
