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
 * Backup structure step for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the backup structure for the Dictogloss activity.
 */
class backup_dictogloss_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure of the backup.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $dictogloss = new backup_nested_element('dictogloss', ['id'], [
            'name', 'intro', 'introformat', 'instructions', 'instructionsformat',
            'source', 'sourceformat', 'revealmode', 'revealed', 'wordcloudmode',
            'similaritythreshold', 'shownotes', 'allowfollowup', 'allowaudioreplay', 'replayopen',
            'holdreconstruction', 'reconstructionopen', 'replaystage',
            'peerscope', 'peersource', 'peercooldown', 'peermindraft', 'peersshownames', 'allowcopy', 'peeropen',
            'randomshared', 'wordcloudrandomsize', 'wordcloudrandompool', 'wordcloudrandomgroupingid',
            'wordcloudrandomgroupid', 'peersrandomsize', 'peersrandompool', 'peersrandomgroupingid',
            'peersrandomgroupid',
            'randomsharedpool', 'randomsharedgroupingid', 'randomsharedgroupid',
            'grade', 'autograding', 'gradeshow', 'completionsubmit',
            'timecreated', 'timemodified',
        ]);

        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', ['id'], [
            'userid', 'groupid', 'keywords', 'keywordsformat', 'reconstruction',
            'reconstructionformat',             'followup', 'followupformat', 'status',
            'timekeywords', 'timereconstruction', 'timefollowup', 'timereconstructionready',
            'timeplayed', 'timepulledpeers', 'peerpullcount', 'timefinalised', 'autograde',
            'grade', 'gradeoverridden', 'grader', 'feedback', 'feedbackformat',
            'timegraded', 'timecreated', 'timemodified',
        ]);

        $keywordtokens = new backup_nested_element('keywordtokens');
        $keyword = new backup_nested_element('keyword', ['id'], [
            'submissionid', 'userid', 'groupid', 'word', 'timecreated',
        ]);

        $overrides = new backup_nested_element('wordoverrides');
        $override = new backup_nested_element('wordoverride', ['id'], [
            'word', 'action', 'replacement', 'moderatedby', 'timemodified',
        ]);

        $randompartners = new backup_nested_element('randompartners');
        $randompartner = new backup_nested_element('randompartner', ['id'], [
            'feature', 'userid', 'partnerid', 'timecreated',
        ]);

        $dictogloss->add_child($submissions);
        $submissions->add_child($submission);
        $submission->add_child($keywordtokens);
        $keywordtokens->add_child($keyword);
        $dictogloss->add_child($overrides);
        $overrides->add_child($override);
        $dictogloss->add_child($randompartners);
        $randompartners->add_child($randompartner);

        $dictogloss->set_source_table('dictogloss', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $submission->set_source_table('dictogloss_submissions', ['dictogloss' => backup::VAR_PARENTID]);
            $keyword->set_source_table('dictogloss_keywords', ['submissionid' => backup::VAR_PARENTID]);
            $randompartner->set_source_table('dictogloss_randompartners', ['dictogloss' => backup::VAR_PARENTID]);
        }

        $override->set_source_table('dictogloss_wordoverrides', ['dictogloss' => backup::VAR_PARENTID]);

        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('group', 'groupid');
        $submission->annotate_ids('user', 'grader');
        $keyword->annotate_ids('user', 'userid');
        $keyword->annotate_ids('group', 'groupid');
        $override->annotate_ids('user', 'moderatedby');
        $randompartner->annotate_ids('user', 'userid');
        $randompartner->annotate_ids('user', 'partnerid');

        // Random pool group/grouping references stored on the instance itself.
        $dictogloss->annotate_ids('group', 'randomsharedgroupid');
        $dictogloss->annotate_ids('group', 'wordcloudrandomgroupid');
        $dictogloss->annotate_ids('group', 'peersrandomgroupid');
        $dictogloss->annotate_ids('grouping', 'randomsharedgroupingid');
        $dictogloss->annotate_ids('grouping', 'wordcloudrandomgroupingid');
        $dictogloss->annotate_ids('grouping', 'peersrandomgroupingid');

        $dictogloss->annotate_files('mod_dictogloss', 'intro', null);
        $dictogloss->annotate_files('mod_dictogloss', 'instructions', null);
        $dictogloss->annotate_files('mod_dictogloss', 'source', null);
        $dictogloss->annotate_files('mod_dictogloss', 'audio', null);

        return $this->prepare_activity_structure($dictogloss);
    }
}
