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
 * Human readable submission status labels.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

use mod_dictogloss\activity;

defined('MOODLE_INTERNAL') || die();

/**
 * Maps submission status constants to display labels.
 */
class status {

    /**
     * Return a human readable status label for a status constant.
     *
     * @param int $status The status constant.
     * @return string
     */
    public static function name($status): string {
        switch ((int) $status) {
            case activity::STATUS_KEYWORDS:
            case activity::STATUS_RECONSTRUCTION:
                return get_string('statusinprogress', 'mod_dictogloss');
            case activity::STATUS_SUBMITTED:
                return get_string('statussubmitted', 'mod_dictogloss');
            case activity::STATUS_FINALISED:
                return get_string('statusfinalised', 'mod_dictogloss');
            default:
                return get_string('statusnotstarted', 'mod_dictogloss');
        }
    }
}
