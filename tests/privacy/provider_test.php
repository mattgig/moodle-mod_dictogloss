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

namespace mod_dictogloss\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use mod_dictogloss\activity;

/**
 * Privacy provider tests for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_dictogloss\privacy\provider
 */
final class provider_test extends provider_testcase {

    /**
     * Create a course, a student, a teacher and an activity, and return the details.
     *
     * @return array [context, activity, student, teacher]
     */
    protected function setup_instance(): array {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'teacher');

        $instance = $this->getDataGenerator()->create_module('dictogloss', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);
        $context = \core\context\module::instance($cm->id);

        $activity = new activity($cm, $course, $instance);
        return [$context, $activity, $student, $teacher];
    }

    /**
     * Store the full set of personal data for a student and a teacher.
     *
     * @param activity $activity The activity.
     * @param \stdClass $student The student.
     * @param \stdClass $teacher The teacher.
     */
    protected function seed_data(activity $activity, $student, $teacher): void {
        global $DB;

        $this->setUser($student);
        $activity->submissions()->save_keywords('alpha beta', $student->id);
        $activity->submissions()->save_reconstruction('alpha beta gamma', $student->id);
        $activity->submissions()->submit_reconstruction($student->id);
        $submission = $activity->submissions()->get_submission($student->id);

        // The teacher graded it.
        $DB->set_field('dictogloss_submissions', 'grader', $teacher->id, ['id' => $submission->id]);

        // A random partner assignment referencing the student.
        $DB->insert_record('dictogloss_randompartners', (object) [
            'dictogloss' => $activity->get_instance()->id,
            'feature' => 'peers',
            'userid' => $student->id,
            'partnerid' => $teacher->id,
            'timecreated' => time(),
        ]);

        // Teacher word-cloud moderation.
        $DB->insert_record('dictogloss_wordoverrides', (object) [
            'dictogloss' => $activity->get_instance()->id,
            'word' => 'alpha',
            'action' => 'hide',
            'replacement' => null,
            'moderatedby' => $teacher->id,
            'timemodified' => time(),
        ]);
    }

    /**
     * The data is discoverable and exportable without error.
     */
    public function test_export_user_data() {
        $this->resetAfterTest();
        list($context, $activity, $student, $teacher) = $this->setup_instance();
        $this->seed_data($activity, $student, $teacher);

        // Both the student and the teacher (as grader/moderator) are found in the activity.
        $studentcontexts = provider::get_contexts_for_userid($student->id);
        $this->assertContains((int) $context->id, $studentcontexts->get_contextids());

        $teachercontexts = provider::get_contexts_for_userid($teacher->id);
        $this->assertContains((int) $context->id, $teachercontexts->get_contextids());

        // Exporting must complete without throwing (regression: duplicated SQL placeholder).
        $this->export_context_data_for_user($student->id, $context, 'mod_dictogloss');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * A context lists every user with data, including the grader/moderator.
     */
    public function test_get_users_in_context() {
        $this->resetAfterTest();
        list($context, $activity, $student, $teacher) = $this->setup_instance();
        $this->seed_data($activity, $student, $teacher);

        $userlist = new userlist($context, 'mod_dictogloss');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();

        $this->assertContains((int) $student->id, $userids);
        $this->assertContains((int) $teacher->id, $userids);
    }

    /**
     * Deleting a user removes their submission and references.
     */
    public function test_delete_data_for_user() {
        global $DB;
        $this->resetAfterTest();
        list($context, $activity, $student, $teacher) = $this->setup_instance();
        $this->seed_data($activity, $student, $teacher);

        provider::delete_data_for_user(new approved_contextlist($student, 'mod_dictogloss', [$context->id]));

        $this->assertFalse($DB->record_exists('dictogloss_submissions',
            ['dictogloss' => $activity->get_instance()->id, 'userid' => $student->id]));
        $this->assertFalse($DB->record_exists('dictogloss_randompartners',
            ['dictogloss' => $activity->get_instance()->id, 'userid' => $student->id]));
    }

    /**
     * Deleting all data in a context clears every table for the activity.
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $this->resetAfterTest();
        list($context, $activity, $student, $teacher) = $this->setup_instance();
        $this->seed_data($activity, $student, $teacher);
        $dictoglossid = $activity->get_instance()->id;

        provider::delete_data_for_all_users_in_context($context);

        $this->assertFalse($DB->record_exists('dictogloss_submissions', ['dictogloss' => $dictoglossid]));
        $this->assertFalse($DB->record_exists('dictogloss_keywords', ['dictogloss' => $dictoglossid]));
        $this->assertFalse($DB->record_exists('dictogloss_randompartners', ['dictogloss' => $dictoglossid]));
        $this->assertFalse($DB->record_exists('dictogloss_wordoverrides', ['dictogloss' => $dictoglossid]));
    }
}
