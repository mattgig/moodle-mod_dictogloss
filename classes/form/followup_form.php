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
 * Follow-up rewrite form.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for the optional follow-up rewrite.
 */
class followup_form extends \moodleform {

    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('textarea', 'followup', get_string('followup', 'mod_dictogloss'),
            ['rows' => 10, 'cols' => 60]);
        $mform->setType('followup', PARAM_RAW);
        $mform->addHelpButton('followup', 'followup', 'mod_dictogloss');

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'savefollowup', get_string('savefollowup', 'mod_dictogloss'));
        $buttonarray[] = $mform->createElement('submit', 'finalise', get_string('handin', 'mod_dictogloss'));
        $mform->addGroup($buttonarray, 'followupbuttons', '', [' '], false);
    }
}
