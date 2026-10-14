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
 * Group reconstructions viewed event.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a student pulls the group reconstructions.
 */
class peers_viewed extends \core\event\base {

    /**
     * Initialise the event.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'dictogloss';
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpeersviewed', 'mod_dictogloss');
    }

    /**
     * Get the event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the group reconstructions for the dictogloss " .
            "activity with id '$this->objectid'.";
    }

    /**
     * Get the event URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/dictogloss/view.php', ['id' => $this->contextinstanceid]);
    }
}
