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
 * Service-level tests for the Dictogloss module.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss;

use mod_dictogloss\overview\sentence_clusterer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/dictogloss/lib.php');

/**
 * Service-level tests for the Dictogloss module.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_dictogloss\activity
 * @covers     \mod_dictogloss\local\submission_service
 * @covers     \mod_dictogloss\local\state_service
 * @covers     \mod_dictogloss\local\grader
 * @covers     \mod_dictogloss\completion\custom_completion
 * @covers     \mod_dictogloss\overview\sentence_clusterer
 */
final class services_test extends \advanced_testcase {

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a course, a student and an activity.
     *
     * @param array $settings Extra dictogloss settings.
     * @return array [activity, user, course]
     */
    protected function setup_activity(array $settings = []): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $instance = $this->getDataGenerator()->create_module('dictogloss',
            array_merge(['course' => $course->id], $settings));
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);
        $this->setUser($user);
        return [new activity($cm, $course, $instance), $user, $course];
    }

    /**
     * Test the two reveal modes.
     */
    public function test_reveal_modes() {
        list($activity, $user) = $this->setup_activity([
            'revealmode' => activity::REVEAL_MANUAL,
            'revealed' => 0,
        ]);
        $this->assertFalse($activity->state()->is_revealed($user->id));
        $activity->state()->set_revealed(true);
        $this->assertTrue($activity->state()->is_revealed($user->id));

        list($onsubmit, $onsubmituser) = $this->setup_activity([
            'revealmode' => activity::REVEAL_ONSUBMIT,
        ]);
        $this->assertFalse($onsubmit->state()->is_revealed($onsubmituser->id));
        $onsubmit->submissions()->save_reconstruction('A short text.', $onsubmituser->id);
        $onsubmit->submissions()->submit_reconstruction($onsubmituser->id);
        $this->assertTrue($onsubmit->state()->is_revealed($onsubmituser->id));
    }

    /**
     * Test that the source text is converted to plain text.
     */
    public function test_plain_source_strips_html() {
        list($activity) = $this->setup_activity(['source' => '<p>The <b>cat</b> sat.</p>']);
        $this->assertSame('The cat sat.', $activity->state()->get_plain_source());

        list($withscript) = $this->setup_activity([
            'source' => '<div>Hello <b>world</b><script>alert("x")</script></div>',
        ]);
        $this->assertSame('Hello world', $withscript->state()->get_plain_source());
    }

    /**
     * Test grader edge cases.
     */
    public function test_grader_edge_cases() {
        list($off, $offuser) = $this->setup_activity(['autograding' => 0, 'grade' => 100]);
        $offsub = $off->submissions()->ensure_submission($offuser->id);
        $offsub->reconstruction = 'Something';
        $this->assertNull($off->grader()->recalculate($offsub));

        list($empty, $emptyuser) = $this->setup_activity(['autograding' => 1, 'grade' => 100]);
        $emptysub = $empty->submissions()->ensure_submission($emptyuser->id);
        $this->assertNull($empty->grader()->recalculate($emptysub));

        list($overridden, $overrideuser) = $this->setup_activity([
            'autograding' => 1,
            'grade' => 100,
            'source' => 'The quick brown fox.',
        ]);
        $overridden->submissions()->save_reconstruction('The quick brown fox.', $overrideuser->id);
        $overriddensub = $overridden->submissions()->get_submission($overrideuser->id);
        $overridden->grader()->recalculate($overriddensub);
        $overridden->submissions()->save_grade($overrideuser->id, 55, 'Nice work', true);

        $overriddensub = $overridden->submissions()->get_submission($overrideuser->id);
        $this->assertEquals(100.0, (float) $overriddensub->autograde);
        $this->assertEquals(55.0, (float) $overriddensub->grade);
        $this->assertEquals(1, (int) $overriddensub->gradeoverridden);

        // Recalculating must not overwrite a teacher override.
        $overridden->grader()->recalculate($overriddensub);
        $overriddensub = $overridden->submissions()->get_submission($overrideuser->id);
        $this->assertEquals(55.0, (float) $overriddensub->grade);
    }

    /**
     * Test hand-in, reopen and delete.
     */
    public function test_finalise_reopen_delete() {
        list($activity, $user) = $this->setup_activity();

        $activity->submissions()->save_reconstruction('Some text.', $user->id);
        $activity->submissions()->submit_reconstruction($user->id);
        $this->assertFalse($activity->submissions()->is_finalised($user->id));

        $activity->submissions()->finalise_submission($user->id);
        $this->assertTrue($activity->submissions()->is_finalised($user->id));

        $activity->submissions()->reopen_submission($user->id);
        $submission = $activity->submissions()->get_submission($user->id);
        $this->assertEquals(activity::STATUS_RECONSTRUCTION, (int) $submission->status);
        $this->assertEquals(0, (int) $submission->timefinalised);

        $this->assertTrue($activity->submissions()->delete_submission($user->id));
        $this->assertFalse($activity->submissions()->get_submission($user->id));
    }

    /**
     * Test the visible grade visibility rules.
     */
    public function test_visible_grade() {
        list($onsubmit, $onsubmituser) = $this->setup_activity([
            'grade' => 100,
            'gradeshow' => activity::GRADE_ON_SUBMIT,
        ]);
        $onsubmit->submissions()->save_reconstruction('Text.', $onsubmituser->id);
        $onsubmit->submissions()->submit_reconstruction($onsubmituser->id);
        $this->assertNotNull($onsubmit->submissions()->get_visible_grade($onsubmituser->id));

        list($afterrelease, $user) = $this->setup_activity([
            'grade' => 100,
            'gradeshow' => activity::GRADE_AFTER_RELEASE,
            'revealmode' => activity::REVEAL_MANUAL,
        ]);
        $afterrelease->submissions()->save_reconstruction('Text.', $user->id);
        $afterrelease->submissions()->submit_reconstruction($user->id);
        $this->assertNull($afterrelease->submissions()->get_visible_grade($user->id));
        $afterrelease->state()->set_revealed(true);
        $this->assertNotNull($afterrelease->submissions()->get_visible_grade($user->id));
    }

    /**
     * Test that sentences with no matching anchor fall into the "additional" bucket.
     */
    public function test_clusterer_extra_bucket() {
        $result = sentence_clusterer::cluster([
            ['author' => 'A', 'authorid' => 1, 'sentences' => ['Zebras eat bananas in winter.']],
        ], 'The quick brown fox jumps over the lazy dog.', 0.7);

        $this->assertTrue($result['has_anchor']);
        $extra = array_values(array_filter($result['slots'], fn($slot) => $slot['anchor'] === null));
        $this->assertNotEmpty($extra);
        $this->assertSame('Zebras eat bananas in winter.', $extra[0]['options'][0]['text']);
    }

    /**
     * The custom completion rule completes only on hand-in and reverts on reopen.
     */
    public function test_custom_completion_rule() {
        list($activity, $user, $course) = $this->setup_activity(['completionsubmit' => 1]);
        $cm = \cm_info::create(get_coursemodule_from_instance('dictogloss',
            $activity->get_instance()->id, $course->id));
        $completion = new \mod_dictogloss\completion\custom_completion($cm, $user->id);

        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionsubmit'));

        $activity->submissions()->save_reconstruction('Some text.', $user->id);
        $activity->submissions()->submit_reconstruction($user->id);
        // The rule requires a hand-in, not just a submission.
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionsubmit'));

        $activity->submissions()->finalise_submission($user->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_state('completionsubmit'));

        $activity->submissions()->reopen_submission($user->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionsubmit'));
    }

    /**
     * The audio listening permission mirrors the stage/settings.
     */
    public function test_can_listen() {
        // Default: listening stage (no notes yet) -> allowed.
        list($activity, $user) = $this->setup_activity(['shownotes' => 1]);
        $this->assertTrue($activity->state()->can_listen($user->id));

        // Notes saved, no replay option -> not allowed.
        $activity->submissions()->save_keywords('alpha beta', $user->id);
        $this->assertFalse($activity->state()->can_listen($user->id));

        // Replay during reconstruction (automatic) -> allowed.
        list($replay, $replayuser) = $this->setup_activity(['allowaudioreplay' => 1]);
        $replay->submissions()->save_keywords('alpha', $replayuser->id);
        $replay->submissions()->save_reconstruction('alpha text', $replayuser->id);
        $this->assertTrue($replay->state()->can_listen($replayuser->id));

        // The live replay override behaves the same even without the static setting.
        list($live, $liveuser) = $this->setup_activity();
        $live->submissions()->save_keywords('alpha', $liveuser->id);
        $live->submissions()->save_reconstruction('alpha', $liveuser->id);
        $this->assertFalse($live->state()->can_listen($liveuser->id));
        $live->state()->set_replay_open(true);
        $this->assertTrue($live->state()->can_listen($liveuser->id));

        // Submitted -> never allowed.
        $replay->submissions()->submit_reconstruction($replayuser->id);
        $this->assertFalse($replay->state()->can_listen($replayuser->id));

        // Separate "listen again" step: allowed until the student starts reconstructing.
        list($stage, $stageuser) = $this->setup_activity(['replaystage' => 1]);
        $stage->submissions()->save_keywords('alpha', $stageuser->id);
        $this->assertTrue($stage->state()->can_listen($stageuser->id));
        $stage->state()->mark_reconstruction_ready($stageuser->id);
        $this->assertFalse($stage->state()->can_listen($stageuser->id));

        // Teachers may always listen.
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $activity->get_course()->id, 'editingteacher');
        $this->setUser($teacher);
        $this->assertTrue($activity->state()->can_listen($user->id));
    }

    /**
     * Saving an empty field on a brand-new submission must not raise an event-snapshot warning.
     */
    public function test_empty_first_save_snapshot() {
        list($activity, $user) = $this->setup_activity();
        $this->setUser($user);

        $this->resetDebugging();
        $activity->submissions()->save_keywords('', $user->id);
        $this->assertDebuggingNotCalled();

        // Reconstruction on the (now existing) submission.
        $this->resetDebugging();
        $activity->submissions()->save_reconstruction('', $user->id);
        $this->assertDebuggingNotCalled();

        // Follow-up rewrite.
        $this->resetDebugging();
        $activity->submissions()->save_followup('', $user->id);
        $this->assertDebuggingNotCalled();
    }
}
