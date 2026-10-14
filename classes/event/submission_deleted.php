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
 * Submission deleted event.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a teacher deletes a submission.
 */
class submission_deleted extends submission_base {

    /**
     * Initialise the event.
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'd';
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissiondeleted', 'mod_dictogloss');
    }

    /**
     * Get the event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' deleted the dictogloss submission with id '$this->objectid'.";
    }
}
