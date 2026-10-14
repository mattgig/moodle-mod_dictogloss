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
 * Behat step definitions for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;

/**
 * Steps supporting the Dictogloss acceptance tests.
 */
class behat_mod_dictogloss extends behat_base {

    /**
     * Create dictogloss activities from a table.
     *
     * The course column holds the course shortname; every other column is passed
     * straight to the mod_dictogloss generator.
     *
     * @Given /^the following dictogloss activities exist:$/
     * @param TableNode $table The activity definitions.
     */
    public function the_following_dictogloss_activities_exist(TableNode $table) {
        global $DB;

        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_dictogloss');
        foreach ($table->getHash() as $row) {
            $course = $DB->get_record('course', ['shortname' => $row['course']], '*', MUST_EXIST);
            $row['course'] = $course->id;
            $generator->create_instance($row);
        }
    }
}
