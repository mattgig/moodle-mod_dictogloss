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
 * Grade override form.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for teachers to override the automatic grade.
 */
class grade_form extends \moodleform {

    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $maxgrade = $this->_customdata['maxgrade'] ?? 0;
        $context = $this->_customdata['context'] ?? \core\context\system::instance();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('text', 'grade', get_string('grade', 'mod_dictogloss'),
            ['size' => 8]);
        $mform->setType('grade', PARAM_RAW_TRIMMED);
        $mform->addElement('static', 'maxgradedescription', '',
            get_string('maxgradedisplay', 'mod_dictogloss', format_float((float) $maxgrade, 2)));

        $mform->addElement('advcheckbox', 'override', get_string('gradeoverridden', 'mod_dictogloss'));
        $mform->setDefault('override', 1);

        $mform->addElement('editor', 'feedback', get_string('feedback', 'mod_dictogloss'),
            ['rows' => 4], ['context' => $context, 'maxfiles' => 0, 'noclean' => 1, 'trusttext' => 0]);
        $mform->setType('feedback', PARAM_RAW);

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'savegrade', get_string('savegrade', 'mod_dictogloss'));
        $buttonarray[] = $mform->createElement('submit', 'savegradenext', get_string('savegradenext', 'mod_dictogloss'));
        $mform->addGroup($buttonarray, 'gradebuttons', '', [' '], false);
    }

    /**
     * Validate the grade input.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $maxgrade = $this->_customdata['maxgrade'] ?? 0;

        if (isset($data['grade']) && $data['grade'] !== '') {
            if (!is_numeric($data['grade'])) {
                $errors['grade'] = get_string('invalidgrade', 'mod_dictogloss');
            } else if ($maxgrade > 0 && ($data['grade'] < 0 || $data['grade'] > $maxgrade)) {
                $errors['grade'] = get_string('invalidgrade', 'mod_dictogloss');
            }
        }

        return $errors;
    }
}
