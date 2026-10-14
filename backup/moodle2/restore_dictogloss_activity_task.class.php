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
 * Restore task for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/dictogloss/backup/moodle2/restore_dictogloss_stepslib.php');

/**
 * Provides the steps to restore one Dictogloss instance.
 */
class restore_dictogloss_activity_task extends restore_activity_task {

    /**
     * Define module-specific settings (none).
     */
    protected function define_my_settings() {
    }

    /**
     * Define the restore steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_dictogloss_activity_structure_step('dictogloss_structure', 'dictogloss.xml'));
    }

    /**
     * Define the contents that may contain encoded links.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('dictogloss', ['intro', 'instructions', 'source'], 'dictogloss'),
        ];
    }

    /**
     * Define the decode rules for links.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('DICTOGLOSSVIEW', '/mod/dictogloss/view.php?id=$1', 'course_module'),
            new restore_decode_rule('DICTOGLOSSINDEX', '/mod/dictogloss/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Define the restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('dictogloss', 'view', 'view.php?id={course_module}', '{dictogloss}'),
        ];
    }
}
