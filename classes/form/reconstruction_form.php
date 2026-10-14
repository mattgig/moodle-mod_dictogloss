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
 * Reconstruction entry form.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for reconstructing the text and submitting it.
 */
class reconstruction_form extends \moodleform {

    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('textarea', 'reconstruction', get_string('reconstruction', 'mod_dictogloss'),
            ['rows' => 12, 'cols' => 60]);
        $mform->setType('reconstruction', PARAM_RAW);
        $mform->addHelpButton('reconstruction', 'reconstruction', 'mod_dictogloss');

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'savereconstruction',
            get_string('savereconstruction', 'mod_dictogloss'));
        $buttonarray[] = $mform->createElement('submit', 'submitreconstruction',
            get_string('submitreconstruction', 'mod_dictogloss'));
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }
}
