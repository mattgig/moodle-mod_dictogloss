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
 * Submission workflow, grading and submission events.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

use mod_dictogloss\activity;
use mod_dictogloss\diff\word_diff;

defined('MOODLE_INTERNAL') || die();

/**
 * Owns the student submission lifecycle, hand-in and grading.
 */
class submission_service {

    /** @var activity The activity. */
    protected $activity;
    /** @var \stdClass The course module record. */
    protected $cm;
    /** @var \stdClass The dictogloss instance record. */
    protected $instance;
    /** @var \core\context\module The module context. */
    protected $context;

    /**
     * Constructor.
     *
     * @param activity $activity The activity.
     */
    public function __construct(activity $activity) {
        $this->activity = $activity;
        $this->cm = $activity->get_cm();
        $this->instance = $activity->get_instance();
        $this->context = $activity->get_context();
    }

    /**
     * Get a user's submission record.
     *
     * @param int|null $userid The user id.
     * @return \stdClass|false
     */
    public function get_submission($userid = null) {
        global $DB, $USER;
        if ($userid === null) {
            $userid = $USER->id;
        }
        return $DB->get_record('dictogloss_submissions',
            ['dictogloss' => $this->instance->id, 'userid' => $userid]);
    }

    /**
     * Get or create a user's submission record.
     *
     * @param int|null $userid The user id.
     * @return \stdClass
     */
    public function ensure_submission($userid = null): \stdClass {
        global $DB, $USER;
        if ($userid === null) {
            $userid = $USER->id;
        }
        if ($submission = $this->get_submission($userid)) {
            return $submission;
        }
        $submission = new \stdClass();
        $submission->dictogloss = $this->instance->id;
        $submission->userid = $userid;
        $submission->groupid = $this->activity->scope()->get_group_id($userid);
        $submission->keywords = '';
        $submission->keywordsformat = FORMAT_PLAIN;
        $submission->reconstruction = '';
        $submission->reconstructionformat = FORMAT_PLAIN;
        $submission->followup = '';
        $submission->followupformat = FORMAT_PLAIN;
        $submission->status = activity::STATUS_NOTSTARTED;
        $submission->timekeywords = 0;
        $submission->timereconstruction = 0;
        $submission->timefollowup = 0;
        $submission->timereconstructionready = 0;
        $submission->timeplayed = 0;
        $submission->timepulledpeers = 0;
        $submission->peerpullcount = 0;
        $submission->timefinalised = 0;
        $submission->timecreated = time();
        $submission->timemodified = time();
        try {
            $submission->id = $DB->insert_record('dictogloss_submissions', $submission);
        } catch (\dml_write_exception $e) {
            // Another request created the row concurrently (unique index dictoglossuserid);
            // return the row that won the race.
            $existing = $this->get_submission($userid);
            if ($existing) {
                return $existing;
            }
            throw $e;
        }
        // Re-read the row so the returned object contains every column: event snapshots
        // (add_record_snapshot) require the full field list to match the table schema.
        return $this->get_submission($userid) ?: $submission;
    }

    /**
     * Set a time field on a submission the first time it happens.
     *
     * @param int|null $userid The user id.
     * @param string $field The time field name.
     * @return \stdClass The submission.
     */
    public function mark_time_if_empty($userid, $field): \stdClass {
        global $DB;
        $submission = $this->ensure_submission($userid);
        if (empty($submission->$field)) {
            $now = time();
            $DB->update_record('dictogloss_submissions', (object) [
                'id' => $submission->id,
                $field => $now,
                'timemodified' => $now,
            ]);
            $submission->$field = $now;
            $submission->timemodified = $now;
        }
        return $submission;
    }

    /**
     * Record that the given user has started listening to the audio.
     *
     * @param int|null $userid The user id.
     * @return \stdClass The submission.
     */
    public function mark_played($userid = null): \stdClass {
        return $this->mark_time_if_empty($userid, 'timeplayed');
    }

    /**
     * Save the keyword notes for a user and refresh the normalised keyword rows.
     *
     * @param string $rawkeywords The raw keyword text.
     * @param int|null $userid The user id.
     * @return \stdClass The updated submission.
     */
    public function save_keywords($rawkeywords, $userid = null): \stdClass {
        global $DB;
        $submission = $this->ensure_submission($userid);

        // Keep the group id current so peer word clouds can find these keywords.
        $currentgroupid = $this->activity->scope()->get_group_id($submission->userid);
        $groupchanged = ((int) $submission->groupid !== (int) $currentgroupid);

        // Nothing to do when neither the text nor the group has changed (frequent autosave calls).
        if ((string) $submission->keywords === (string) $rawkeywords
                && $submission->status >= activity::STATUS_KEYWORDS
                && !$groupchanged) {
            return $submission;
        }
        if ($groupchanged) {
            $submission->groupid = $currentgroupid;
        }

        $now = time();
        $newstatus = max(activity::STATUS_KEYWORDS, (int) $submission->status);

        // Update the row and refresh the normalised tokens atomically.
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('dictogloss_submissions', (object) [
            'id' => $submission->id,
            'groupid' => $submission->groupid,
            'keywords' => $rawkeywords,
            'keywordsformat' => FORMAT_PLAIN,
            'timekeywords' => $now,
            'status' => $newstatus,
            'timemodified' => $now,
        ]);

        $DB->delete_records('dictogloss_keywords', ['submissionid' => $submission->id]);
        $seen = [];
        foreach (word_diff::tokenize($rawkeywords) as $token) {
            if (isset($seen[$token['norm']])) {
                continue;
            }
            $seen[$token['norm']] = true;
            $record = new \stdClass();
            $record->dictogloss = $this->instance->id;
            $record->submissionid = $submission->id;
            $record->userid = $submission->userid;
            $record->groupid = $submission->groupid;
            $record->word = $token['norm'];
            $record->timecreated = $now;
            $DB->insert_record('dictogloss_keywords', $record);
        }
        $transaction->allow_commit();

        $submission->keywords = $rawkeywords;
        $submission->keywordsformat = FORMAT_PLAIN;
        $submission->timekeywords = $now;
        $submission->status = $newstatus;
        $submission->timemodified = $now;

        $this->trigger_submission_event('keywords_saved', $submission);

        return $submission;
    }

    /**
     * Save a plain-text draft field (reconstruction or follow-up) on a submission.
     *
     * Only the changed columns are written so a concurrent save of another field
     * (e.g. keywords) is not clobbered by a stale in-memory record.
     *
     * @param \stdClass $submission The submission.
     * @param string $field The text field name.
     * @param string $text The text.
     * @param int|null $status An optional new status to set at the same time.
     * @return \stdClass The updated submission.
     */
    protected function save_draft_text(\stdClass $submission, $field, $text, $status = null): \stdClass {
        global $DB;
        $changed = ((string) $submission->$field !== (string) $text);
        if (!$changed && $status === null) {
            // Nothing to write (frequent autosave calls with unchanged text).
            return $submission;
        }
        $now = time();
        $update = (object) [
            'id' => $submission->id,
            $field => $text,
            $field . 'format' => FORMAT_PLAIN,
            'time' . $field => $now,
            'timemodified' => $now,
        ];
        if ($status !== null) {
            $update->status = $status;
            $submission->status = $status;
        }
        $DB->update_record('dictogloss_submissions', $update);

        $submission->$field = $text;
        $submission->{$field . 'format'} = FORMAT_PLAIN;
        $submission->{'time' . $field} = $now;
        $submission->timemodified = $now;

        if ($changed) {
            $this->trigger_submission_event('submission_updated', $submission);
        }
        return $submission;
    }

    /**
     * Save a reconstruction draft.
     *
     * @param string $text The reconstruction text.
     * @param int|null $userid The user id.
     * @return \stdClass The updated submission.
     */
    public function save_reconstruction($text, $userid = null): \stdClass {
        $submission = $this->ensure_submission($userid);
        $status = ((int) $submission->status < activity::STATUS_RECONSTRUCTION)
            ? activity::STATUS_RECONSTRUCTION
            : null;
        return $this->save_draft_text($submission, 'reconstruction', $text, $status);
    }

    /**
     * Save the optional follow-up rewrite.
     *
     * @param string $text The follow-up text.
     * @param int|null $userid The user id.
     * @return \stdClass The updated submission.
     */
    public function save_followup($text, $userid = null): \stdClass {
        $submission = $this->ensure_submission($userid);
        return $this->save_draft_text($submission, 'followup', $text);
    }

    /**
     * Mark the reconstruction as submitted and (re)calculate the automatic grade.
     *
     * @param int|null $userid The user id.
     * @return \stdClass The updated submission.
     */
    public function submit_reconstruction($userid = null): \stdClass {
        global $DB;
        $submission = $this->ensure_submission($userid);
        $previouslysubmitted = ((int) $submission->status >= activity::STATUS_SUBMITTED);

        $now = time();
        $DB->update_record('dictogloss_submissions', (object) [
            'id' => $submission->id,
            'status' => activity::STATUS_SUBMITTED,
            'timemodified' => $now,
        ]);
        $submission->status = activity::STATUS_SUBMITTED;
        $submission->timemodified = $now;

        $this->activity->grader()->recalculate($submission);
        $this->refresh_completion($submission->userid);
        $this->trigger_submission_event($previouslysubmitted ? 'submission_updated' : 'submission_created', $submission);

        return $this->get_submission($submission->userid);
    }

    /**
     * Hand in the whole contribution (locks further editing).
     *
     * @param int|null $userid The user id.
     * @return \stdClass The updated submission.
     */
    public function finalise_submission($userid = null): \stdClass {
        global $DB;
        $submission = $this->ensure_submission($userid);

        $now = time();
        $DB->update_record('dictogloss_submissions', (object) [
            'id' => $submission->id,
            'status' => activity::STATUS_FINALISED,
            'timefinalised' => $now,
            'timemodified' => $now,
        ]);
        $submission->status = activity::STATUS_FINALISED;
        $submission->timefinalised = $now;
        $submission->timemodified = $now;

        $this->refresh_completion($submission->userid);

        if (!empty($this->instance->grade)) {
            $this->activity->grader()->update($submission->userid);
        }

        $this->trigger_submission_event('submission_finalised', $submission);

        return $this->get_submission($submission->userid);
    }

    /**
     * Reopen a handed-in contribution so the student can revise and hand in again.
     *
     * @param int $userid The user id.
     * @return \stdClass|null The updated submission.
     */
    public function reopen_submission($userid): ?\stdClass {
        global $DB;
        $submission = $this->get_submission($userid);
        if (!$submission) {
            return null;
        }

        $now = time();
        $DB->update_record('dictogloss_submissions', (object) [
            'id' => $submission->id,
            'status' => activity::STATUS_RECONSTRUCTION,
            'timefinalised' => 0,
            'timemodified' => $now,
        ]);
        $submission->status = activity::STATUS_RECONSTRUCTION;
        $submission->timefinalised = 0;
        $submission->timemodified = $now;

        $this->refresh_completion($submission->userid);
        $this->trigger_submission_event('submission_reopened', $submission);

        return $submission;
    }

    /**
     * Whether a submission has been handed in.
     *
     * @param int|null $userid The user id.
     * @return bool
     */
    public function is_finalised($userid = null): bool {
        $submission = $this->get_submission($userid);
        return $submission && $submission->status >= activity::STATUS_FINALISED;
    }

    /**
     * Delete a single user's contribution (keywords, reconstruction, follow-up and grade).
     *
     * @param int $userid The user id.
     * @return bool Whether a submission was deleted.
     */
    public function delete_submission($userid): bool {
        global $DB;

        $submission = $this->get_submission($userid);
        if (!$submission) {
            return false;
        }

        $DB->delete_records('dictogloss_keywords', ['submissionid' => $submission->id]);
        $DB->delete_records('dictogloss_submissions', ['id' => $submission->id]);

        if (!empty($this->instance->grade)) {
            $this->activity->grader()->update($userid, true);
        }

        $this->refresh_completion($userid);
        $this->trigger_submission_event('submission_deleted', $submission);

        return true;
    }

    /**
     * Save a teacher grade override.
     *
     * @param int $userid The user id.
     * @param float|null $grade The scaled grade.
     * @param string $feedback The feedback text.
     * @param bool $override Whether this is an override.
     * @return void
     */
    public function save_grade($userid, $grade, $feedback = '', $override = true): void {
        global $DB, $USER;
        $submission = $this->ensure_submission($userid);

        $now = time();
        $DB->update_record('dictogloss_submissions', (object) [
            'id' => $submission->id,
            'grade' => $grade,
            'gradeoverridden' => $override ? 1 : 0,
            'grader' => $USER->id,
            'feedback' => $feedback,
            'feedbackformat' => FORMAT_HTML,
            'timegraded' => $now,
            'timemodified' => $now,
        ]);
        $submission->grade = $grade;
        $submission->gradeoverridden = $override ? 1 : 0;
        $submission->grader = $USER->id;
        $submission->feedback = $feedback;
        $submission->feedbackformat = FORMAT_HTML;
        $submission->timegraded = $now;
        $submission->timemodified = $now;

        if (!empty($this->instance->grade)) {
            $this->activity->grader()->update($userid);
        }

        $this->trigger_submission_event('submission_graded', $submission);
        if ($override) {
            $this->trigger_submission_event('grade_overridden', $submission);
        }
    }

    /**
     * Return the grade a user should currently see, or null.
     *
     * @param int|null $userid The user id.
     * @return \stdClass|null
     */
    public function get_visible_grade($userid = null): ?\stdClass {
        $submission = $this->get_submission($userid);
        if (!$submission || $submission->status < activity::STATUS_SUBMITTED) {
            return null;
        }
        if ((int) $this->instance->gradeshow === activity::GRADE_ON_SUBMIT) {
            return $submission;
        }
        if ($this->activity->state()->is_revealed($userid)) {
            return $submission;
        }
        return null;
    }

    /**
     * Recompute the module completion state for a user.
     *
     * Uses COMPLETION_UNKNOWN so the configured completion rules (rather than a
     * forced state) decide the outcome.
     *
     * @param int $userid The user id.
     * @return void
     */
    protected function refresh_completion($userid): void {
        $course = get_course($this->cm->course);
        $completion = new \completion_info($course);
        if (!$completion->is_enabled($this->cm)) {
            return;
        }
        $completion->update_state($this->cm, COMPLETION_UNKNOWN, $userid);
    }

    /**
     * Trigger a submission related event.
     *
     * @param string $eventname The event class short name.
     * @param \stdClass $submission The submission.
     * @return void
     */
    protected function trigger_submission_event($eventname, $submission): void {
        $class = '\\mod_dictogloss\\event\\' . $eventname;
        if (!class_exists($class)) {
            return;
        }
        $event = $class::create([
            'objectid' => $submission->id,
            'context' => $this->context,
            'relateduserid' => $submission->userid,
            'other' => ['dictoglossid' => $this->instance->id],
        ]);
        $event->add_record_snapshot($event->objecttable, $submission);
        $event->trigger();
    }
}
