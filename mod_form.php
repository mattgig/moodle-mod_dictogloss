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
 * Activity settings form for the Dictogloss module.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/dictogloss/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * Dictogloss module settings form.
 */
class mod_dictogloss_mod_form extends moodleform_mod {

    /**
     * Define the form.
     */
    public function definition() {
        global $CFG;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('dictoglossname', 'mod_dictogloss'), ['size' => 64]);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'dictoglossname', 'mod_dictogloss');

        $this->standard_intro_elements();

        $mform->addElement('header', 'contentsection', get_string('dictoglossfieldset', 'mod_dictogloss'));

        $mform->addElement('editor', 'instructions', get_string('instructions', 'mod_dictogloss'),
            ['rows' => 5], dictogloss_get_editor_options($this->context));
        $mform->setType('instructions', PARAM_RAW);
        $mform->addHelpButton('instructions', 'instructions', 'mod_dictogloss');

        $mform->addElement('editor', 'source', get_string('source', 'mod_dictogloss'),
            ['rows' => 10], dictogloss_get_editor_options($this->context));
        $mform->setType('source', PARAM_RAW);
        $mform->addHelpButton('source', 'source', 'mod_dictogloss');
        $mform->addRule('source', get_string('required'), 'required', null, 'client');

        $mform->addElement('filemanager', 'audio', get_string('audiofiles', 'mod_dictogloss'),
            null, dictogloss_get_filemanager_options($this->context));
        $mform->addHelpButton('audio', 'audio', 'mod_dictogloss');

        $mform->addElement('header', 'behaviour', get_string('modulename', 'mod_dictogloss'));

        $mform->addElement('selectyesno', 'shownotes', get_string('shownotes', 'mod_dictogloss'));
        $mform->setDefault('shownotes', 1);
        $mform->addHelpButton('shownotes', 'shownotes', 'mod_dictogloss');

        $cloudoptions = [
            'group' => get_string('wordcloudgroup', 'mod_dictogloss'),
            'grouping' => get_string('wordcloudgrouping', 'mod_dictogloss'),
            'individual' => get_string('wordcloudindividual', 'mod_dictogloss'),
            'random' => get_string('wordcloudrandom', 'mod_dictogloss'),
        ];
        $mform->addElement('select', 'wordcloudmode', get_string('wordcloudmode', 'mod_dictogloss'), $cloudoptions);
        $mform->setDefault('wordcloudmode', 'group');
        $mform->addHelpButton('wordcloudmode', 'wordcloudmode', 'mod_dictogloss');
        // The cloud is built from keyword notes, so the source only applies when note-taking is on.
        $mform->hideIf('wordcloudmode', 'shownotes', 'eq', 0);

        $mform->addElement('selectyesno', 'replaystage', get_string('replaystage', 'mod_dictogloss'));
        $mform->setDefault('replaystage', 0);
        $mform->addHelpButton('replaystage', 'replaystage', 'mod_dictogloss');

        $mform->addElement('selectyesno', 'holdreconstruction', get_string('holdreconstruction', 'mod_dictogloss'));
        $mform->setDefault('holdreconstruction', 0);
        $mform->addHelpButton('holdreconstruction', 'holdreconstruction', 'mod_dictogloss');

        $mform->addElement('selectyesno', 'allowaudioreplay', get_string('allowaudioreplay', 'mod_dictogloss'));
        $mform->setDefault('allowaudioreplay', 0);
        $mform->addHelpButton('allowaudioreplay', 'allowaudioreplay', 'mod_dictogloss');
        // Replay during reconstruction has no effect once a separate listen-again step is used.
        $mform->hideIf('allowaudioreplay', 'replaystage', 'eq', 1);

        $mform->addElement('text', 'similaritythreshold',
            get_string('similaritythreshold', 'mod_dictogloss'), ['size' => 4]);
        $mform->setType('similaritythreshold', PARAM_INT);
        $defaultthreshold = get_config('mod_dictogloss', 'defaultthreshold');
        $mform->setDefault('similaritythreshold', ($defaultthreshold === false) ? 70 : $defaultthreshold);
        $mform->addHelpButton('similaritythreshold', 'similaritythreshold', 'mod_dictogloss');

        $revealoptions = [
            \mod_dictogloss\activity::REVEAL_ONSUBMIT => get_string('revealonsubmit', 'mod_dictogloss'),
            \mod_dictogloss\activity::REVEAL_MANUAL => get_string('revealmanual', 'mod_dictogloss'),
        ];
        $mform->addElement('select', 'revealmode', get_string('revealmode', 'mod_dictogloss'), $revealoptions);
        $mform->setDefault('revealmode', \mod_dictogloss\activity::REVEAL_ONSUBMIT);
        $mform->addHelpButton('revealmode', 'revealmode', 'mod_dictogloss');

        $mform->addElement('selectyesno', 'allowfollowup', get_string('allowfollowup', 'mod_dictogloss'));
        $mform->setDefault('allowfollowup', 0);
        $mform->addHelpButton('allowfollowup', 'allowfollowup', 'mod_dictogloss');

        // Collaborative group reconstructions.
        $mform->addElement('header', 'peerssettings', get_string('peersheading', 'mod_dictogloss'));

        $mform->addElement('select', 'peerscope', get_string('peerscope', 'mod_dictogloss'), [
            'off' => get_string('peerscope_off', 'mod_dictogloss'),
            'group' => get_string('peerscope_group', 'mod_dictogloss'),
            'grouping' => get_string('peerscope_grouping', 'mod_dictogloss'),
            'class' => get_string('peerscope_class', 'mod_dictogloss'),
            'random' => get_string('peerscope_random', 'mod_dictogloss'),
        ]);
        $mform->setDefault('peerscope', 'off');
        $mform->addHelpButton('peerscope', 'peerscope', 'mod_dictogloss');

        $mform->addElement('select', 'peersource', get_string('peersource', 'mod_dictogloss'), [
            'both' => get_string('peersource_both', 'mod_dictogloss'),
            'draft' => get_string('peersource_draft', 'mod_dictogloss'),
            'submitted' => get_string('peersource_submitted', 'mod_dictogloss'),
            'none' => get_string('peersource_none', 'mod_dictogloss'),
        ]);
        $mform->setDefault('peersource', 'both');
        $mform->addHelpButton('peersource', 'peersource', 'mod_dictogloss');
        $mform->hideIf('peersource', 'peerscope', 'eq', 'off');

        $mform->addElement('text', 'peermindraft', get_string('peermindraft', 'mod_dictogloss'), ['size' => 5]);
        $mform->setType('peermindraft', PARAM_INT);
        $mform->setDefault('peermindraft', 20);
        $mform->addHelpButton('peermindraft', 'peermindraft', 'mod_dictogloss');
        $mform->hideIf('peermindraft', 'peerscope', 'eq', 'off');
        $mform->hideIf('peermindraft', 'peersource', 'eq', 'none');

        $mform->addElement('text', 'peercooldown', get_string('peercooldown', 'mod_dictogloss'), ['size' => 5]);
        $mform->setType('peercooldown', PARAM_INT);
        $mform->setDefault('peercooldown', 60);
        $mform->addHelpButton('peercooldown', 'peercooldown', 'mod_dictogloss');
        $mform->hideIf('peercooldown', 'peerscope', 'eq', 'off');
        $mform->hideIf('peercooldown', 'peersource', 'eq', 'none');

        $mform->addElement('selectyesno', 'peersshownames', get_string('peersshownames', 'mod_dictogloss'));
        $mform->setDefault('peersshownames', 0);
        $mform->addHelpButton('peersshownames', 'peersshownames', 'mod_dictogloss');
        $mform->hideIf('peersshownames', 'peerscope', 'eq', 'off');
        $mform->hideIf('peersshownames', 'peersource', 'eq', 'none');

        $mform->addElement('selectyesno', 'allowcopy', get_string('allowcopy', 'mod_dictogloss'));
        $mform->setDefault('allowcopy', 1);
        $mform->addHelpButton('allowcopy', 'allowcopy', 'mod_dictogloss');
        $mform->hideIf('allowcopy', 'peerscope', 'eq', 'off');
        $mform->hideIf('allowcopy', 'peersource', 'eq', 'none');

        // Random assignment (used when either scope above is set to "Random").
        $mform->addElement('header', 'randomheading', get_string('randomheading', 'mod_dictogloss'));

        $mform->addElement('selectyesno', 'randomshared', get_string('randomshared', 'mod_dictogloss'));
        $mform->setDefault('randomshared', 0);
        $mform->addHelpButton('randomshared', 'randomshared', 'mod_dictogloss');

        $courseid = $this->get_course()->id;
        $activitygroupingid = (!empty($this->_cm) && !empty($this->_cm->groupingid)) ? (int) $this->_cm->groupingid : 0;

        $pooloptions = [
            'class' => get_string('randompool_class', 'mod_dictogloss'),
            'grouping' => get_string('randompool_grouping', 'mod_dictogloss'),
            'group' => get_string('randompool_group', 'mod_dictogloss'),
        ];

        $groupingoptions = [0 => get_string('none')];
        foreach (groups_get_all_groupings($courseid) as $grouping) {
            $groupingoptions[(int) $grouping->id] = format_string($grouping->name);
        }

        $groupoptions = [0 => get_string('none')];
        foreach (groups_get_all_groups($courseid, 0, $activitygroupingid, 'g.id, g.name') as $group) {
            $groupoptions[(int) $group->id] = format_string($group->name);
        }

        // Dedicated shared source pool, shown when "use the same random source" is on.
        $mform->addElement('select', 'randomsharedpool', get_string('randompool', 'mod_dictogloss'), $pooloptions);
        $mform->setDefault('randomsharedpool', 'class');
        $mform->hideIf('randomsharedpool', 'randomshared', 'eq', 0);

        $mform->addElement('select', 'randomsharedgroupingid', get_string('randomgrouping', 'mod_dictogloss'),
            $groupingoptions);
        $mform->setDefault('randomsharedgroupingid', 0);
        $mform->hideIf('randomsharedgroupingid', 'randomshared', 'eq', 0);
        $mform->hideIf('randomsharedgroupingid', 'randomsharedpool', 'neq', 'grouping');

        $mform->addElement('select', 'randomsharedgroupid', get_string('randomgroup', 'mod_dictogloss'), $groupoptions);
        $mform->setDefault('randomsharedgroupid', 0);
        $mform->hideIf('randomsharedgroupid', 'randomshared', 'eq', 0);
        $mform->hideIf('randomsharedgroupid', 'randomsharedpool', 'neq', 'group');

        // Word cloud random assignment.
        $mform->addElement('text', 'wordcloudrandomsize', get_string('randomsize', 'mod_dictogloss'), ['size' => 4]);
        $mform->setType('wordcloudrandomsize', PARAM_INT);
        $mform->setDefault('wordcloudrandomsize', 3);
        $mform->addHelpButton('wordcloudrandomsize', 'randomsize', 'mod_dictogloss');
        $mform->hideIf('wordcloudrandomsize', 'wordcloudmode', 'neq', 'random');
        $mform->hideIf('wordcloudrandomsize', 'shownotes', 'eq', 0);

        $mform->addElement('select', 'wordcloudrandompool', get_string('randompool', 'mod_dictogloss'), $pooloptions);
        $mform->setDefault('wordcloudrandompool', 'class');
        $mform->hideIf('wordcloudrandompool', 'wordcloudmode', 'neq', 'random');
        $mform->hideIf('wordcloudrandompool', 'shownotes', 'eq', 0);
        $mform->hideIf('wordcloudrandompool', 'randomshared', 'eq', 1);

        $mform->addElement('select', 'wordcloudrandomgroupingid', get_string('randomgrouping', 'mod_dictogloss'),
            $groupingoptions);
        $mform->setDefault('wordcloudrandomgroupingid', 0);
        $mform->hideIf('wordcloudrandomgroupingid', 'wordcloudmode', 'neq', 'random');
        $mform->hideIf('wordcloudrandomgroupingid', 'shownotes', 'eq', 0);
        $mform->hideIf('wordcloudrandomgroupingid', 'wordcloudrandompool', 'neq', 'grouping');
        $mform->hideIf('wordcloudrandomgroupingid', 'randomshared', 'eq', 1);

        $mform->addElement('select', 'wordcloudrandomgroupid', get_string('randomgroup', 'mod_dictogloss'),
            $groupoptions);
        $mform->setDefault('wordcloudrandomgroupid', 0);
        $mform->hideIf('wordcloudrandomgroupid', 'wordcloudmode', 'neq', 'random');
        $mform->hideIf('wordcloudrandomgroupid', 'shownotes', 'eq', 0);
        $mform->hideIf('wordcloudrandomgroupid', 'wordcloudrandompool', 'neq', 'group');
        $mform->hideIf('wordcloudrandomgroupid', 'randomshared', 'eq', 1);

        // Peer sharing random assignment (its own pool unless shared).
        $mform->addElement('text', 'peersrandomsize', get_string('randomsize', 'mod_dictogloss'), ['size' => 4]);
        $mform->setType('peersrandomsize', PARAM_INT);
        $mform->setDefault('peersrandomsize', 2);
        $mform->addHelpButton('peersrandomsize', 'randomsize', 'mod_dictogloss');
        $mform->hideIf('peersrandomsize', 'peerscope', 'neq', 'random');

        $mform->addElement('select', 'peersrandompool', get_string('randompool', 'mod_dictogloss'), $pooloptions);
        $mform->setDefault('peersrandompool', 'class');
        $mform->hideIf('peersrandompool', 'peerscope', 'neq', 'random');
        $mform->hideIf('peersrandompool', 'randomshared', 'eq', 1);

        $mform->addElement('select', 'peersrandomgroupingid', get_string('randomgrouping', 'mod_dictogloss'),
            $groupingoptions);
        $mform->setDefault('peersrandomgroupingid', 0);
        $mform->hideIf('peersrandomgroupingid', 'peerscope', 'neq', 'random');
        $mform->hideIf('peersrandomgroupingid', 'peersrandompool', 'neq', 'grouping');
        $mform->hideIf('peersrandomgroupingid', 'randomshared', 'eq', 1);

        $mform->addElement('select', 'peersrandomgroupid', get_string('randomgroup', 'mod_dictogloss'), $groupoptions);
        $mform->setDefault('peersrandomgroupid', 0);
        $mform->hideIf('peersrandomgroupid', 'peerscope', 'neq', 'random');
        $mform->hideIf('peersrandomgroupid', 'peersrandompool', 'neq', 'group');
        $mform->hideIf('peersrandomgroupid', 'randomshared', 'eq', 1);

        // Grading.
        $mform->addElement('header', 'gradesheading', get_string('gradesheading', 'mod_dictogloss'));

        $mform->addElement('selectyesno', 'autograding', get_string('autograding', 'mod_dictogloss'));
        $mform->setDefault('autograding', 1);
        $mform->addHelpButton('autograding', 'autograding', 'mod_dictogloss');

        $showoptions = [
            \mod_dictogloss\activity::GRADE_AFTER_RELEASE => get_string('gradeshow_afterrelease', 'mod_dictogloss'),
            \mod_dictogloss\activity::GRADE_ON_SUBMIT => get_string('gradeshow_onsubmit', 'mod_dictogloss'),
        ];
        $mform->addElement('select', 'gradeshow', get_string('gradeshow', 'mod_dictogloss'), $showoptions);
        $mform->setDefault('gradeshow', \mod_dictogloss\activity::GRADE_AFTER_RELEASE);
        $mform->addHelpButton('gradeshow', 'gradeshow', 'mod_dictogloss');

        // The Random assignment section is only relevant when a feature actually uses random assignment.
        global $PAGE;
        $PAGE->requires->js_call_amd('mod_dictogloss/formdeps', 'init');

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Add module-specific completion rules.
     *
     * @return array The names of the completion rule elements.
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();
        $elementname = 'completionsubmit' . $suffix;
        $mform->addElement('advcheckbox', $elementname, '',
            get_string('completionsubmit', 'mod_dictogloss'));
        return [$elementname];
    }

    /**
     * Return whether a completion rule is enabled by default.
     *
     * @param array $data The form data.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit' . $this->get_suffix()]);
    }

    /**
     * Prepare editor and file manager fields before the form is displayed.
     *
     * @param array $defaultvalues The default values.
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        if (!$this->current->instance) {
            return;
        }

        // Prepare the editor fields manually: data_preprocessing() receives an array,
        // while the *_standard_* file helpers expect an object.
        foreach (['instructions', 'source'] as $field) {
            $draftid = file_get_submitted_draft_itemid($field);
            $text = $defaultvalues[$field] ?? '';
            $text = file_prepare_draft_area($draftid, $this->context->id, 'mod_dictogloss', $field, 0,
                dictogloss_get_editor_options($this->context), $text);
            $defaultvalues[$field] = [
                'text' => $text,
                'format' => $defaultvalues[$field . 'format'] ?? FORMAT_HTML,
                'itemid' => $draftid,
            ];
        }

        // Prepare the audio file manager draft area.
        $draftid = file_get_submitted_draft_itemid('audio');
        file_prepare_draft_area($draftid, $this->context->id, 'mod_dictogloss', 'audio', 0,
            dictogloss_get_filemanager_options($this->context));
        $defaultvalues['audio'] = $draftid;
    }

    /**
     * Validate the form.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['similaritythreshold'])) {
            $threshold = (int) $data['similaritythreshold'];
            if ($threshold < 0 || $threshold > 100) {
                $errors['similaritythreshold'] = get_string('invalidthreshold', 'mod_dictogloss');
            }
        }

        $wordcloudrandom = ($data['wordcloudmode'] ?? '') === 'random';
        $peersrandom = ($data['peerscope'] ?? '') === 'random';
        $randomshared = !empty($data['randomshared']);

        if ($wordcloudrandom && (empty($data['wordcloudrandomsize']) || (int) $data['wordcloudrandomsize'] < 2)) {
            $errors['wordcloudrandomsize'] = get_string('invalidrandomsize', 'mod_dictogloss');
        }
        if ($peersrandom && (empty($data['peersrandomsize']) || (int) $data['peersrandomsize'] < 2)) {
            $errors['peersrandomsize'] = get_string('invalidrandomsize', 'mod_dictogloss');
        }

        if ($wordcloudrandom || $peersrandom) {
            if ($randomshared) {
                $this->validate_random_pool($errors, $data,
                    'randomsharedpool', 'randomsharedgroupingid', 'randomsharedgroupid');
            } else {
                if ($wordcloudrandom) {
                    $this->validate_random_pool($errors, $data,
                        'wordcloudrandompool', 'wordcloudrandomgroupingid', 'wordcloudrandomgroupid');
                }
                if ($peersrandom) {
                    $this->validate_random_pool($errors, $data,
                        'peersrandompool', 'peersrandomgroupingid', 'peersrandomgroupid');
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a random source pool selection.
     *
     * @param array $errors The errors array (modified by reference).
     * @param array $data The submitted data.
     * @param string $poolkey The pool type field name.
     * @param string $groupingkey The grouping id field name.
     * @param string $groupkey The group id field name.
     * @return void
     */
    protected function validate_random_pool(array &$errors, array $data, $poolkey, $groupingkey, $groupkey) {
        $pool = $data[$poolkey] ?? '';
        if ($pool === 'grouping') {
            if (empty($data[$groupingkey])) {
                $errors[$groupingkey] = get_string('required');
            }
            return;
        }
        if ($pool !== 'group') {
            return;
        }

        $groupid = (int) ($data[$groupkey] ?? 0);
        if (!$groupid) {
            $errors[$groupkey] = get_string('required');
            return;
        }

        // The chosen group must belong to the grouping the activity is restricted to (if any).
        $activitygroupingid = (int) ($data['groupingid'] ?? 0);
        if ($activitygroupingid) {
            $allowed = groups_get_all_groups($this->get_course()->id, 0, $activitygroupingid, 'g.id');
            if (!isset($allowed[$groupid])) {
                $errors[$groupkey] = get_string('randomgroupnotingrouping', 'mod_dictogloss');
            }
        }
    }
}
