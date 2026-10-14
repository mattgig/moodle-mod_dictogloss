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
 * Restore structure step for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the restore structure for the Dictogloss activity.
 */
class restore_dictogloss_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the structure of the restore.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('dictogloss', '/activity/dictogloss');
        $paths[] = new restore_path_element('dictogloss_wordoverride',
            '/activity/dictogloss/wordoverrides/wordoverride');

        if ($userinfo) {
            $paths[] = new restore_path_element('dictogloss_submission',
                '/activity/dictogloss/submissions/submission');
            $paths[] = new restore_path_element('dictogloss_keyword',
                '/activity/dictogloss/submissions/submission/keywordtokens/keyword');
            $paths[] = new restore_path_element('dictogloss_randompartner',
                '/activity/dictogloss/randompartners/randompartner');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process a dictogloss element.
     *
     * @param array $data The data.
     */
    protected function process_dictogloss($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        // Remap the random pool group/grouping references to the restored course.
        foreach (['randomsharedgroupid', 'wordcloudrandomgroupid', 'peersrandomgroupid'] as $field) {
            if (isset($data->$field)) {
                $data->$field = $this->get_mappingid('group', $data->$field) ?: 0;
            }
        }
        foreach (['randomsharedgroupingid', 'wordcloudrandomgroupingid', 'peersrandomgroupingid'] as $field) {
            if (isset($data->$field)) {
                $data->$field = $this->get_mappingid('grouping', $data->$field) ?: 0;
            }
        }

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('dictogloss', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process a submission element.
     *
     * @param array $data The data.
     */
    protected function process_dictogloss_submission($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->dictogloss = $this->get_new_parentid('dictogloss');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (empty($data->userid)) {
            // The owning user was not included in the restore; skip this contribution.
            return;
        }
        $data->groupid = $this->get_mappingid('group', $data->groupid) ?: 0;
        $data->grader = $this->get_mappingid('user', $data->grader) ?: 0;
        $data->timekeywords = $this->apply_date_offset($data->timekeywords);
        $data->timereconstruction = $this->apply_date_offset($data->timereconstruction);
        $data->timefollowup = $this->apply_date_offset($data->timefollowup);
        if (isset($data->timereconstructionready)) {
            $data->timereconstructionready = $this->apply_date_offset($data->timereconstructionready);
        }
        if (isset($data->timeplayed)) {
            $data->timeplayed = $this->apply_date_offset($data->timeplayed);
        }
        if (isset($data->timepulledpeers)) {
            $data->timepulledpeers = $this->apply_date_offset($data->timepulledpeers);
        }
        if (isset($data->timefinalised)) {
            $data->timefinalised = $this->apply_date_offset($data->timefinalised);
        }
        if (!isset($data->gradeoverridden)) {
            $data->gradeoverridden = 0;
        }
        if (!isset($data->timegraded)) {
            $data->timegraded = 0;
        }
        $data->timegraded = $this->apply_date_offset($data->timegraded);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('dictogloss_submissions', $data);
        $this->set_mapping('dictogloss_submission', $oldid, $newitemid);
    }

    /**
     * Process a keyword element.
     *
     * @param array $data The data.
     */
    protected function process_dictogloss_keyword($data) {
        global $DB;

        $data = (object) $data;
        $data->dictogloss = $this->get_new_parentid('dictogloss');
        $data->submissionid = $this->get_new_parentid('dictogloss_submission');
        if (empty($data->submissionid)) {
            // The parent contribution was skipped; skip its keyword tokens too.
            return;
        }
        $data->userid = $this->get_mappingid('user', $data->userid) ?: 0;
        $data->groupid = $this->get_mappingid('group', $data->groupid) ?: 0;
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $DB->insert_record('dictogloss_keywords', $data);
    }

    /**
     * Process a word override element.
     *
     * @param array $data The data.
     */
    protected function process_dictogloss_wordoverride($data) {
        global $DB;

        $data = (object) $data;
        $data->dictogloss = $this->get_new_parentid('dictogloss');
        $data->moderatedby = $this->get_mappingid('user', $data->moderatedby) ?: 0;
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('dictogloss_wordoverrides', $data);
    }

    /**
     * Process a random partner element.
     *
     * @param array $data The data.
     */
    protected function process_dictogloss_randompartner($data) {
        global $DB;

        $data = (object) $data;
        $data->dictogloss = $this->get_new_parentid('dictogloss');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->partnerid = $this->get_mappingid('user', $data->partnerid);

        // Skip rows that reference users who were not restored.
        if (empty($data->userid) || empty($data->partnerid)) {
            return;
        }

        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $DB->insert_record('dictogloss_randompartners', $data);
    }

    /**
     * Restore files after the structure has been processed.
     */
    protected function after_execute() {
        $this->add_related_files('mod_dictogloss', 'intro', null);
        $this->add_related_files('mod_dictogloss', 'instructions', null);
        $this->add_related_files('mod_dictogloss', 'source', null);
        $this->add_related_files('mod_dictogloss', 'audio', null);
    }
}
