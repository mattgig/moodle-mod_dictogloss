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
 * Base class for Dictogloss submission events.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared behaviour for events tied to a dictogloss submission.
 */
abstract class submission_base extends \core\event\base {

    /**
     * Initialise the shared event data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'dictogloss_submissions';
    }

    /**
     * Get the event URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/dictogloss/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Map the submission id when restoring logs.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'dictogloss_submissions', 'restore' => 'dictogloss_submission'];
    }

    /**
     * Map the dictogloss instance id (stored in "other") when restoring logs.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return ['dictoglossid' => ['db' => 'dictogloss', 'restore' => 'dictogloss']];
    }
}
