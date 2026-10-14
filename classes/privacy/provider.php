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
 * Privacy provider for the Dictogloss module.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe the personal data stored by the module.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('dictogloss_submissions', [
            'userid' => 'privacy:metadata:dictogloss_submissions:userid',
            'keywords' => 'privacy:metadata:dictogloss_submissions:keywords',
            'reconstruction' => 'privacy:metadata:dictogloss_submissions:reconstruction',
            'followup' => 'privacy:metadata:dictogloss_submissions:followup',
            'grade' => 'privacy:metadata:dictogloss_submissions:grade',
            'grader' => 'privacy:metadata:dictogloss_submissions:grader',
            'feedback' => 'privacy:metadata:dictogloss_submissions:feedback',
            'timecreated' => 'privacy:metadata:dictogloss_submissions:timecreated',
            'timemodified' => 'privacy:metadata:dictogloss_submissions:timemodified',
        ], 'privacy:metadata:dictogloss_submissions');

        $collection->add_database_table('dictogloss_keywords', [
            'userid' => 'privacy:metadata:dictogloss_keywords:userid',
            'groupid' => 'privacy:metadata:dictogloss_keywords:groupid',
            'word' => 'privacy:metadata:dictogloss_keywords:word',
            'timecreated' => 'privacy:metadata:dictogloss_keywords:timecreated',
        ], 'privacy:metadata:dictogloss_keywords');

        $collection->add_database_table('dictogloss_randompartners', [
            'userid' => 'privacy:metadata:dictogloss_randompartners:userid',
            'partnerid' => 'privacy:metadata:dictogloss_randompartners:partnerid',
            'feature' => 'privacy:metadata:dictogloss_randompartners:feature',
            'timecreated' => 'privacy:metadata:dictogloss_randompartners:timecreated',
        ], 'privacy:metadata:dictogloss_randompartners');

        $collection->add_database_table('dictogloss_wordoverrides', [
            'word' => 'privacy:metadata:dictogloss_wordoverrides:word',
            'action' => 'privacy:metadata:dictogloss_wordoverrides:action',
            'replacement' => 'privacy:metadata:dictogloss_wordoverrides:replacement',
            'moderatedby' => 'privacy:metadata:dictogloss_wordoverrides:moderatedby',
            'timemodified' => 'privacy:metadata:dictogloss_wordoverrides:timemodified',
        ], 'privacy:metadata:dictogloss_wordoverrides');

        return $collection;
    }

    /**
     * Get the contexts containing data for a user.
     *
     * @param int $userid The user id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {dictogloss} d ON d.id = cm.instance
                 WHERE d.id IN (
                           SELECT s.dictogloss FROM {dictogloss_submissions} s WHERE s.userid = :subuserid
                           UNION
                           SELECT rp.dictogloss FROM {dictogloss_randompartners} rp
                            WHERE rp.userid = :rpuserid OR rp.partnerid = :rppartnerid
                           UNION
                           SELECT wo.dictogloss FROM {dictogloss_wordoverrides} wo
                            WHERE wo.moderatedby = :wouserid
                       )";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'dictogloss',
            'subuserid' => $userid,
            'rpuserid' => $userid,
            'rppartnerid' => $userid,
            'wouserid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Get the users with data in a context.
     *
     * @param userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \core\context\module) {
            return;
        }
        $sql = "SELECT x.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {dictogloss} d ON d.id = cm.instance
                  JOIN (
                          SELECT s.userid AS userid, s.dictogloss AS dictogloss
                            FROM {dictogloss_submissions} s
                          UNION
                          SELECT rp.userid AS userid, rp.dictogloss AS dictogloss
                            FROM {dictogloss_randompartners} rp
                          UNION
                          SELECT rp.partnerid AS userid, rp.dictogloss AS dictogloss
                            FROM {dictogloss_randompartners} rp
                          UNION
                          SELECT wo.moderatedby AS userid, wo.dictogloss AS dictogloss
                            FROM {dictogloss_wordoverrides} wo
                      ) x ON x.dictogloss = d.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'dictogloss',
            'cmid' => $context->instanceid,
        ]);
    }

    /**
     * Export a user's data.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $sql = "SELECT s.*
                      FROM {dictogloss_submissions} s
                      JOIN {dictogloss} d ON d.id = s.dictogloss
                      JOIN {course_modules} cm ON cm.instance = d.id
                     WHERE cm.id = :cmid AND s.userid = :userid";
            $submissions = $DB->get_records_sql($sql, [
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ]);

            foreach ($submissions as $index => $submission) {
                $data = (object) [
                    'keywords' => $submission->keywords,
                    'reconstruction' => $submission->reconstruction,
                    'followup' => $submission->followup,
                    'grade' => $submission->grade,
                    'feedback' => $submission->feedback,
                    'timecreated' => transform::datetime($submission->timecreated),
                    'timemodified' => transform::datetime($submission->timemodified),
                ];
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:submissions', 'mod_dictogloss'), $index + 1],
                    $data
                );
            }

            // Random partner assignments that reference this user (either side).
            $partners = $DB->get_records_sql(
                "SELECT rp.id, rp.feature, rp.userid, rp.partnerid, rp.timecreated
                   FROM {dictogloss_randompartners} rp
                   JOIN {dictogloss} d ON d.id = rp.dictogloss
                   JOIN {course_modules} cm ON cm.instance = d.id
                  WHERE cm.id = :cmid AND (rp.userid = :userid OR rp.partnerid = :userid)",
                ['cmid' => $context->instanceid, 'userid' => $userid]);
            if ($partners) {
                $partnerdata = [];
                foreach ($partners as $partner) {
                    $partnerdata[] = (object) [
                        'feature' => $partner->feature,
                        'userid' => $partner->userid,
                        'partnerid' => $partner->partnerid,
                        'timecreated' => transform::datetime($partner->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:randompartners', 'mod_dictogloss')],
                    (object) ['partners' => $partnerdata]
                );
            }

            // Word cloud moderation performed by this user.
            $overrides = $DB->get_records_sql(
                "SELECT wo.id, wo.word, wo.action, wo.replacement, wo.timemodified
                   FROM {dictogloss_wordoverrides} wo
                   JOIN {dictogloss} d ON d.id = wo.dictogloss
                   JOIN {course_modules} cm ON cm.instance = d.id
                  WHERE cm.id = :cmid AND wo.moderatedby = :userid",
                ['cmid' => $context->instanceid, 'userid' => $userid]);
            if ($overrides) {
                $overridedata = [];
                foreach ($overrides as $override) {
                    $overridedata[] = (object) [
                        'word' => $override->word,
                        'action' => $override->action,
                        'replacement' => $override->replacement,
                        'timemodified' => transform::datetime($override->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:wordoverrides', 'mod_dictogloss')],
                    (object) ['overrides' => $overridedata]
                );
            }
        }
    }

    /**
     * Delete all user data in a context.
     *
     * @param \core\context\context $context The context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\core\context\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $dictoglossid = $DB->get_field_sql(
            "SELECT d.id FROM {dictogloss} d JOIN {course_modules} cm ON cm.instance = d.id WHERE cm.id = ?",
            [$context->instanceid]);
        if (!$dictoglossid) {
            return;
        }
        self::delete_activity_data($dictoglossid);
    }

    /**
     * Delete a user's data.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $dictoglossid = $DB->get_field_sql(
                "SELECT d.id FROM {dictogloss} d JOIN {course_modules} cm ON cm.instance = d.id WHERE cm.id = ?",
                [$context->instanceid]);
            if (!$dictoglossid) {
                continue;
            }
            self::delete_user_data($dictoglossid, $userid);
        }
    }

    /**
     * Delete data for a list of users in a context.
     *
     * @param approved_userlist $userlist The approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $dictoglossid = $DB->get_field_sql(
            "SELECT d.id FROM {dictogloss} d JOIN {course_modules} cm ON cm.instance = d.id WHERE cm.id = ?",
            [$context->instanceid]);
        if (!$dictoglossid) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_data($dictoglossid, $userid);
        }
    }

    /**
     * Delete all submission data for an activity instance.
     *
     * @param int $dictoglossid The instance id.
     * @return void
     */
    protected static function delete_activity_data($dictoglossid) {
        global $DB;
        $submissionids = $DB->get_fieldset_select('dictogloss_submissions', 'id', 'dictogloss = ?', [$dictoglossid]);
        if ($submissionids) {
            list($insql, $params) = $DB->get_in_or_equal($submissionids, SQL_PARAMS_QM);
            $DB->delete_records_select('dictogloss_keywords', "submissionid $insql", $params);
        }
        $DB->delete_records('dictogloss_submissions', ['dictogloss' => $dictoglossid]);
        $DB->delete_records('dictogloss_randompartners', ['dictogloss' => $dictoglossid]);
        $DB->delete_records('dictogloss_wordoverrides', ['dictogloss' => $dictoglossid]);
    }

    /**
     * Delete a single user's submission data.
     *
     * @param int $dictoglossid The instance id.
     * @param int $userid The user id.
     * @return void
     */
    protected static function delete_user_data($dictoglossid, $userid) {
        global $DB;
        $submission = $DB->get_record('dictogloss_submissions',
            ['dictogloss' => $dictoglossid, 'userid' => $userid]);
        if ($submission) {
            $DB->delete_records('dictogloss_keywords', ['submissionid' => $submission->id]);
            $DB->delete_records('dictogloss_submissions', ['id' => $submission->id]);
        }
        $DB->delete_records_select('dictogloss_randompartners',
            'dictogloss = ? AND (userid = ? OR partnerid = ?)',
            [$dictoglossid, $userid, $userid]);
        $DB->delete_records('dictogloss_wordoverrides',
            ['dictogloss' => $dictoglossid, 'moderatedby' => $userid]);
    }
}
