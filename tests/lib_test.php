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
 * Unit tests for the Dictogloss module.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss;

use mod_dictogloss\diff\word_diff;
use mod_dictogloss\overview\sentence_clusterer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/dictogloss/lib.php');

/**
 * Test cases for the Dictogloss module.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_dictogloss\activity
 * @covers     \mod_dictogloss\local\submission_service
 * @covers     \mod_dictogloss\local\wordcloud_service
 * @covers     \mod_dictogloss\local\peer_service
 * @covers     \mod_dictogloss\local\random_service
 * @covers     \mod_dictogloss\local\state_service
 * @covers     \mod_dictogloss\local\grader
 * @covers     \mod_dictogloss\local\overview_service
 * @covers     \mod_dictogloss\diff\word_diff
 * @covers     \mod_dictogloss\overview\sentence_clusterer
 */
final class lib_test extends \advanced_testcase {

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the supported features.
     */
    public function test_supports() {
        $this->assertTrue(dictogloss_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(dictogloss_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(dictogloss_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertNull(dictogloss_supports('unsupported_feature'));
    }

    /**
     * Test adding and deleting an instance.
     */
    public function test_add_and_delete_instance() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();

        $id = dictogloss_add_instance((object) [
            'course' => $course->id,
            'name' => 'Test',
            'intro' => '',
            'introformat' => FORMAT_PLAIN,
            'source' => 'Hello world.',
            'sourceformat' => FORMAT_PLAIN,
        ]);

        $this->assertIsInt($id);
        $this->assertTrue($DB->record_exists('dictogloss', ['id' => $id]));

        $this->assertTrue(dictogloss_delete_instance($id));
        $this->assertFalse($DB->record_exists('dictogloss', ['id' => $id]));
    }

    /**
     * Test the student workflow: keywords, reconstruction and submission.
     */
    public function test_submission_workflow() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $instance = $this->getDataGenerator()->create_module('dictogloss', [
            'course' => $course->id,
            'source' => 'The quick brown fox jumps over the lazy dog.',
            'autograding' => 1,
            'grade' => 100,
        ]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);
        $this->setUser($user);

        $activity = new activity($cm, $course, $instance);

        $activity->submissions()->save_keywords('quick fox lazy dog', $user->id);
        $submission = $activity->submissions()->get_submission($user->id);
        $this->assertEquals(activity::STATUS_KEYWORDS, $submission->status);

        $keywords = $DB->get_records('dictogloss_keywords', ['submissionid' => $submission->id]);
        $this->assertCount(4, $keywords);

        $activity->submissions()->save_reconstruction('The quick brown fox jumps over the lazy dog.', $user->id);
        $activity->submissions()->submit_reconstruction($user->id);
        $submission = $activity->submissions()->get_submission($user->id);
        $this->assertEquals(activity::STATUS_SUBMITTED, $submission->status);
        $this->assertEquals(100.0, (float) $submission->autograde);

        $grades = dictogloss_get_user_grades($instance, $user->id);
        $this->assertArrayHasKey($user->id, $grades);
    }

    /**
     * Test word cloud aggregation within a group.
     */
    public function test_wordcloud_aggregation() {
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user1->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user2->id]);

        $instance = $this->getDataGenerator()->create_module('dictogloss', [
            'course' => $course->id,
            'wordcloudmode' => 'group',
        ]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);

        $activity = new activity($cm, $course, $instance);
        $activity->submissions()->save_keywords('apple banana', $user1->id);
        $activity->submissions()->save_keywords('apple cherry', $user2->id);

        $entries = $activity->wordcloud()->get_wordcloud_entries($user1->id);
        $byword = [];
        foreach ($entries as $entry) {
            $byword[$entry['word']] = $entry;
        }
        // A peer's word must be visible in the shared group cloud.
        $this->assertEquals(2, $byword['apple']['count']);
        $this->assertTrue($byword['apple']['own']);
        $this->assertTrue($byword['banana']['own']);
        $this->assertEquals(1, $byword['cherry']['count']);
        $this->assertFalse($byword['cherry']['own']);
    }

    /**
     * Test the word-level comparison and score.
     */
    public function test_word_diff() {
        $identical = word_diff::compare('The cat sat.', 'The cat sat.');
        $this->assertEquals(100.0, $identical['score']);

        $partial = word_diff::compare('The cat sat.', 'The dog sat.');
        $this->assertEquals(2, $partial['matches']);
        $this->assertEquals(1, $partial['missing']);
        $this->assertEquals(1, $partial['extra']);
    }

    /**
     * Test sentence clustering of near-identical options.
     */
    public function test_sentence_clusterer() {
        $reconstructions = [
            ['author' => 'Alice', 'sentences' => ['The cat sat on the mat.']],
            ['author' => 'Bob', 'sentences' => ['The cat sat on the mat.']],
            ['author' => 'Carol', 'sentences' => ['A dog ran in the park.']],
        ];
        $result = sentence_clusterer::cluster($reconstructions, 'The cat sat on the mat.', 0.7);
        $this->assertTrue($result['has_anchor']);
        $firstslot = $result['slots'][0];
        $this->assertEquals(2, $firstslot['options'][0]['count']);
    }

    /**
     * Test the audio replay availability (setting or live override).
     */
    public function test_audio_replay_availability() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('dictogloss', [
            'course' => $course->id,
            'allowaudioreplay' => 0,
            'replayopen' => 0,
        ]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);

        $activity = new activity($cm, $course, $instance);
        $this->assertFalse($activity->state()->is_replay_available());

        $activity->state()->set_replay_open(true);
        $instance = $DB->get_record('dictogloss', ['id' => $instance->id]);
        $activity = new activity($cm, $course, $instance);
        $this->assertTrue($activity->state()->is_replay_available());
    }

    /**
     * Test the reconstruction hold (setting + live override).
     */
    public function test_reconstruction_hold() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('dictogloss', [
            'course' => $course->id,
            'holdreconstruction' => 1,
            'reconstructionopen' => 0,
        ]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);

        $activity = new activity($cm, $course, $instance);
        $this->assertFalse($activity->state()->is_reconstruction_open());

        $activity->state()->set_reconstruction_open(true);
        $instance = $DB->get_record('dictogloss', ['id' => $instance->id]);
        $activity = new activity($cm, $course, $instance);
        $this->assertTrue($activity->state()->is_reconstruction_open());
    }

    /**
     * Test advancing from the "listen again" step to reconstruction.
     */
    public function test_reconstruction_ready_flow() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $instance = $this->getDataGenerator()->create_module('dictogloss', [
            'course' => $course->id,
            'shownotes' => 1,
        ]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);

        $activity = new activity($cm, $course, $instance);
        $activity->submissions()->save_keywords('alpha beta', $user->id);

        $submission = $activity->submissions()->get_submission($user->id);
        $this->assertEquals(0, (int) $submission->timereconstructionready);

        $activity->state()->mark_reconstruction_ready($user->id);
        $submission = $activity->submissions()->get_submission($user->id);
        $this->assertNotEmpty($submission->timereconstructionready);
    }

    /**
     * Test pulling group reconstructions: gating and scope.
     */
    public function test_peers_pull_flow() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user1->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user2->id]);

        $instance = $this->getDataGenerator()->create_module('dictogloss', [
            'course' => $course->id,
            'peerscope' => 'group',
            'peersource' => 'both',
            'peercooldown' => 0,
            'peermindraft' => 1,
            'peeropen' => 0,
        ]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);
        $activity = new activity($cm, $course, $instance);

        // Closed until the teacher opens it.
        $status = $activity->peers()->get_peers_pull_status($user1->id);
        $this->assertFalse($status['ok']);
        $this->assertEquals('closed', $status['reason']);

        $activity->peers()->set_peers_open(true);
        $instance = $DB->get_record('dictogloss', ['id' => $instance->id]);
        $activity = new activity($cm, $course, $instance);

        // Requires an own draft.
        $status = $activity->peers()->get_peers_pull_status($user1->id);
        $this->assertEquals('draft', $status['reason']);

        $activity->submissions()->save_reconstruction('The cat sat on the mat.', $user1->id);
        $activity->submissions()->save_reconstruction('The cat sat on the mat.', $user2->id);

        $status = $activity->peers()->get_peers_pull_status($user1->id);
        $this->assertTrue($status['ok']);

        $peers = $activity->peers()->get_peer_reconstructions($user1->id);
        $this->assertCount(2, $peers);
        $own = array_filter($peers, fn($peer) => $peer['isown']);
        $this->assertCount(1, $own);
    }

    /**
     * Test handing in the entire contribution and reopening it.
     */
    public function test_finalise_and_reopen() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $instance = $this->getDataGenerator()->create_module('dictogloss', [
            'course' => $course->id,
            'allowfollowup' => 0,
        ]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);

        $activity = new activity($cm, $course, $instance);
        $activity->submissions()->save_keywords('alpha beta', $user->id);
        $activity->submissions()->save_reconstruction('alpha beta gamma', $user->id);
        $activity->submissions()->submit_reconstruction($user->id);

        $submission = $activity->submissions()->get_submission($user->id);
        $this->assertEquals(activity::STATUS_SUBMITTED, (int) $submission->status);

        $activity->submissions()->finalise_submission($user->id);
        $submission = $activity->submissions()->get_submission($user->id);
        $this->assertEquals(activity::STATUS_FINALISED, (int) $submission->status);
        $this->assertNotEmpty($submission->timefinalised);
        $this->assertTrue($activity->submissions()->is_finalised($user->id));

        $activity->submissions()->reopen_submission($user->id);
        $submission = $activity->submissions()->get_submission($user->id);
        $this->assertEquals(activity::STATUS_RECONSTRUCTION, (int) $submission->status);
        $this->assertEmpty($submission->timefinalised);
        $this->assertFalse($activity->submissions()->is_finalised($user->id));
    }

    /**
     * Test random partner assignment for the word cloud and peer scopes.
     */
    public function test_random_partner_assignment() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
            $users[] = $user;
        }
        $instance = $this->getDataGenerator()->create_module('dictogloss', [
            'course' => $course->id,
            'wordcloudmode' => 'random',
            'peerscope' => 'random',
            'wordcloudrandomsize' => 3,
            'peersrandomsize' => 2,
            'wordcloudrandompool' => 'class',
            'peersrandompool' => 'class',
        ]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);
        $activity = new activity($cm, $course, $instance);

        // Assignment is generated lazily.
        $this->assertFalse($activity->random()->has_random_partners('wordcloud'));
        $activity->random()->ensure_random_partners('wordcloud');
        $this->assertTrue($activity->random()->has_random_partners('wordcloud'));

        $rows = $DB->get_records('dictogloss_randompartners',
            ['dictogloss' => $instance->id, 'feature' => 'wordcloud']);
        $byuser = [];
        foreach ($rows as $row) {
            $byuser[(int) $row->userid][] = (int) $row->partnerid;
        }

        foreach ($users as $user) {
            $this->assertArrayHasKey($user->id, $byuser);
            $partners = $byuser[$user->id];
            $this->assertCount(2, $partners);
            $this->assertNotContains($user->id, $partners);
            $this->assertCount(2, array_unique($partners));
        }

        // The scope always includes the student plus their distinct partners.
        $scope = $activity->random()->get_random_scope_userids($users[0]->id, activity::RANDOM_FEATURE_WORDCLOUD);
        $this->assertCount(3, $scope);

        // Re-shuffling keeps an assignment in place.
        $activity->random()->reshuffle_random_partners(activity::RANDOM_FEATURE_WORDCLOUD);
        $this->assertTrue($activity->random()->has_random_partners(activity::RANDOM_FEATURE_WORDCLOUD));
    }

    /**
     * Test the dedicated shared pool and group-pool scoping.
     */
    public function test_random_shared_group_pool() {
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $member1 = $this->getDataGenerator()->create_user();
        $member2 = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        foreach ([$member1, $member2, $outsider] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        }
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $member1->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $member2->id]);

        $instance = $this->getDataGenerator()->create_module('dictogloss', [
            'course' => $course->id,
            'wordcloudmode' => 'random',
            'peerscope' => 'random',
            'wordcloudrandomsize' => 3,
            'peersrandomsize' => 3,
            'randomshared' => 1,
            'randomsharedpool' => 'group',
            'randomsharedgroupid' => $group->id,
        ]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);
        $activity = new activity($cm, $course, $instance);

        // Both features resolve to the shared group pool.
        $settings = $activity->random()->get_random_settings(activity::RANDOM_FEATURE_PEERS);
        $this->assertEquals('group', $settings['pool']);
        $this->assertEquals($group->id, $settings['groupid']);

        // A group member's scope stays within the group.
        $scope = $activity->random()->get_random_scope_userids($member1->id, activity::RANDOM_FEATURE_PEERS);
        $this->assertContains($member1->id, $scope);
        $this->assertContains($member2->id, $scope);
        $this->assertNotContains($outsider->id, $scope);

        // A student outside the pool only ever sees themselves.
        $this->assertEquals([$outsider->id],
            $activity->random()->get_random_scope_userids($outsider->id, activity::RANDOM_FEATURE_PEERS));

        $activity->random()->reshuffle_random_partners(activity::RANDOM_FEATURE_PEERS);
        $this->assertTrue($activity->random()->has_random_partners(activity::RANDOM_FEATURE_PEERS));
    }

    /**
     * Test that the random word cloud aggregates a student's partners.
     */
    public function test_random_wordcloud_scope() {
        $course = $this->getDataGenerator()->create_course();
        $users = [];
        for ($i = 0; $i < 3; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
            $users[] = $user;
        }
        $instance = $this->getDataGenerator()->create_module('dictogloss', [
            'course' => $course->id,
            'wordcloudmode' => 'random',
            'wordcloudrandomsize' => 4,
            'wordcloudrandompool' => 'class',
        ]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);
        $activity = new activity($cm, $course, $instance);

        // Save the same keyword plus a unique one for each student.
        foreach ($users as $index => $user) {
            $activity->submissions()->save_keywords('apple unique' . $index, $user->id);
        }

        $entries = $activity->wordcloud()->get_wordcloud_entries($users[0]->id);
        $byword = [];
        foreach ($entries as $entry) {
            $byword[$entry['word']] = $entry;
        }
        // With size 4 every student is partnered with all others, so "apple" is shared by all three.
        $this->assertEquals(3, $byword['apple']['count']);
    }

    /**
     * Test the teacher sentence-variant overview data.
     */
    public function test_variants_overview() {
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        $instance = $this->getDataGenerator()->create_module('dictogloss', [
            'course' => $course->id,
            'source' => 'The cat sat on the mat. It was warm.',
        ]);
        $cm = get_coursemodule_from_instance('dictogloss', $instance->id);
        $activity = new activity($cm, $course, $instance);

        $activity->submissions()->save_reconstruction('The cat sat on the mat. It was warm.', $user1->id);
        $activity->submissions()->save_reconstruction('The cat sat on the mat. It was warm.', $user2->id);

        $overview = $activity->overview()->get_variants_overview();
        $this->assertArrayHasKey('slots', $overview);
        $this->assertNotEmpty($overview['slots']);
        $this->assertGreaterThanOrEqual(1, count($overview['slots'][0]['options']));
    }
}
