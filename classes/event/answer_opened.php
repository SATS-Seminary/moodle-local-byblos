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
 * Event: announcement answer page opened.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos\event;

use core\event\base;
use moodle_url;

/**
 * Fired when a student clicks an announcement turnstile link and is bounced to
 * a Byblos portfolio page.
 *
 * Logged in the *course* context so the click feeds the course's normal log
 * reports (Course → Reports → Logs). $other carries the resolved Byblos page id
 * so reports can drill back to the destination without a join.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer_opened extends base {
    /**
     * Initialise the event metadata.
     *
     * Read-only ('r') because the click doesn't mutate state; participating
     * education level because this is a student engagement signal, not admin
     * activity.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        // No objecttable — the destination is a Byblos page, but the log entry
        // belongs to the course context, not the page record.
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_answer_opened', 'local_byblos');
    }

    /**
     * Return a human-readable description of the click for log views.
     *
     * @return string
     */
    public function get_description(): string {
        $pageid = (int) ($this->other['pageid'] ?? 0);
        return "The user with id '{$this->userid}' opened the Byblos answer page "
            . "with id '{$pageid}' from course id '{$this->courseid}'.";
    }

    /**
     * Return the URL to the destination Byblos page so log views can link back.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        $pageid = (int) ($this->other['pageid'] ?? 0);
        return new moodle_url('/local/byblos/page.php', ['id' => $pageid]);
    }
}
