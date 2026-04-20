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
 * Event: portfolio page shared.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos\event;

use core\event\base;
use moodle_url;

/**
 * Fired when a user shares a portfolio page.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_shared extends base {
    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['objecttable'] = 'local_byblos_share';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Return the event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_page_shared', 'local_byblos');
    }

    /**
     * Return the event description.
     *
     * @return string
     */
    public function get_description(): string {
        $sharetype = $this->other['sharetype'] ?? 'unknown';
        $sharevalue = $this->other['sharevalue'] ?? '';
        return "The user with id '{$this->userid}' shared a portfolio item (share id '{$this->objectid}') " .
               "with type '{$sharetype}' and value '{$sharevalue}'.";
    }

    /**
     * Return the URL to the sharing management page.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        if (!empty($this->other['pageid'])) {
            return new moodle_url('/local/byblos/share.php', [
                'id' => $this->other['pageid'],
                'type' => 'page',
            ]);
        }
        if (!empty($this->other['collectionid'])) {
            return new moodle_url('/local/byblos/share.php', [
                'id' => $this->other['collectionid'],
                'type' => 'collection',
            ]);
        }
        return new moodle_url('/local/byblos/index.php');
    }

    /**
     * Return the mapping of objectid.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'local_byblos_share', 'restore' => base::NOT_MAPPED];
    }

    /**
     * Custom validation for the 'other' field.
     *
     * @return void
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['sharetype'])) {
            throw new \coding_exception('The \'sharetype\' value must be set in other.');
        }
        if (!array_key_exists('sharevalue', $this->other)) {
            throw new \coding_exception('The \'sharevalue\' value must be set in other.');
        }
    }
}
