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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/dictogloss/lib.php');

use mod_dictogloss\activity;
use mod_dictogloss\overview\sentence_clusterer;

/**
 * Tests for the reveal/grade/submission services.
 */
class mod_dictogloss_services_testcase extends advanced_testcase {

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

        list($onSubmit, $onSubmitUser) = $this->setup_activity([
            'revealmode' => activity::REVEAL_ONSUBMIT,
        ]);
        $this->assertFalse($onSubmit->state()->is_revealed($onSubmitUser->id));
        $onSubmit->submissions()->save_reconstruction('A short text.', $onSubmitUser->id);
        $onSubmit->submissions()->submit_reconstruction($onSubmitUser->id);
        $this->assertTrue($onSubmit->state()->is_revealed($onSubmitUser->id));
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
        list($onSubmit, $onSubmitUser) = $this->setup_activity([
            'grade' => 100,
            'gradeshow' => activity::GRADE_ON_SUBMIT,
        ]);
        $onSubmit->submissions()->save_reconstruction('Text.', $onSubmitUser->id);
        $onSubmit->submissions()->submit_reconstruction($onSubmitUser->id);
        $this->assertNotNull($onSubmit->submissions()->get_visible_grade($onSubmitUser->id));

        list($afterRelease, $user) = $this->setup_activity([
            'grade' => 100,
            'gradeshow' => activity::GRADE_AFTER_RELEASE,
            'revealmode' => activity::REVEAL_MANUAL,
        ]);
        $afterRelease->submissions()->save_reconstruction('Text.', $user->id);
        $afterRelease->submissions()->submit_reconstruction($user->id);
        $this->assertNull($afterRelease->submissions()->get_visible_grade($user->id));
        $afterRelease->state()->set_revealed(true);
        $this->assertNotNull($afterRelease->submissions()->get_visible_grade($user->id));
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
}
