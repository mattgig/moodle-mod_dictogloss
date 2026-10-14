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
 * Source reveal, comparison, replay and reconstruction-open state.
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
 * Manages the reveal/comparison/replay/reconstruction state of an activity instance.
 */
class state_service {

    /** Instance flags that may be toggled live. */
    const FLAGS = ['replayopen', 'reconstructionopen', 'revealed', 'peeropen'];

    /** @var activity The activity. */
    protected $activity;
    /** @var \stdClass The dictogloss instance record. */
    protected $instance;

    /**
     * Constructor.
     *
     * @param activity $activity The activity.
     */
    public function __construct(activity $activity) {
        $this->activity = $activity;
        $this->instance = $activity->get_instance();
    }

    /**
     * Return the original text as plain text (HTML tags stripped, entities decoded).
     *
     * The source is stored as rich-text HTML, so it must be converted before being
     * tokenised, compared or shown as sentences.
     *
     * @return string
     */
    public function get_plain_source(): string {
        $html = (string) $this->instance->source;
        if (trim($html) === '') {
            return '';
        }

        // Drop embedded scripts/styles entirely.
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
        // Turn block-level boundaries into newlines, then strip the remaining tags.
        $text = preg_replace('#<\s*(br|/p|/div|/li|/h[1-6]|/tr|/blockquote)\b[^>]*>#i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\p{Zs}\t]+/u', ' ', $text);
        $text = preg_replace('/[\p{Zs}]*\n[\p{Zs}]*/u', "\n", $text);
        $text = preg_replace('/\n{2,}/u', "\n", $text);

        return trim($text);
    }

    /**
     * Return the comparison data for a user's reconstruction.
     *
     * @param int|null $userid The user id.
     * @return array|null
     */
    public function get_comparison($userid = null): ?array {
        $submission = $this->activity->submissions()->get_submission($userid);
        if (!$submission || trim((string) $submission->reconstruction) === '') {
            return null;
        }
        return word_diff::compare($this->get_plain_source(), $submission->reconstruction);
    }

    /**
     * Whether the source text is revealed to the given user.
     *
     * @param int|null $userid The user id.
     * @return bool
     */
    public function is_revealed($userid = null): bool {
        if ((int) $this->instance->revealmode === activity::REVEAL_ONSUBMIT) {
            $submission = $this->activity->submissions()->get_submission($userid);
            return $submission && $submission->status >= activity::STATUS_SUBMITTED;
        }
        return !empty($this->instance->revealed);
    }

    /**
     * Whether the source may be shown to the current user for a given view.
     *
     * @param bool $isteacher Whether the current user manages the activity.
     * @param int|null $userid The user id.
     * @return bool
     */
    public function can_view_source($isteacher = false, $userid = null): bool {
        return $isteacher || $this->is_revealed($userid);
    }

    /**
     * Whether students may listen to the recording again during reconstruction.
     *
     * @return bool
     */
    public function is_replay_available(): bool {
        return !empty($this->instance->allowaudioreplay) || !empty($this->instance->replayopen);
    }

    /**
     * Whether the given user may currently play the audio recording.
     *
     * Mirrors the audio-player visibility in view.php and is used to authorise
     * access to the audio file area in dictogloss_pluginfile().
     *
     * @param int|null $userid The user id (defaults to the current user).
     * @return bool
     */
    public function can_listen($userid = null): bool {
        global $USER;
        if ($userid === null) {
            $userid = $USER->id;
        }

        // Teachers may always preview the recording.
        if (has_capability('mod/dictogloss:manage', $this->activity->get_context())) {
            return true;
        }

        $submission = $this->activity->submissions()->get_submission($userid);
        $status = $submission ? (int) $submission->status : activity::STATUS_NOTSTARTED;

        // No listening once the contribution has been submitted (comparison/rewriting stage).
        if ($status >= activity::STATUS_SUBMITTED) {
            return false;
        }

        // Initial listening / keyword-notes stage.
        if ($status < activity::STATUS_KEYWORDS) {
            return !empty($this->instance->shownotes) || $this->is_replay_available();
        }

        // Separate "listen again" step, before the student starts reconstructing.
        if (!empty($this->instance->replaystage)) {
            return empty($submission->timereconstructionready);
        }

        // During (and after) reconstruction only if replay is explicitly allowed/opened.
        return $this->is_replay_available();
    }

    /**
     * Mark that the student has finished the "listen again" step and is ready to reconstruct.
     *
     * @param int|null $userid The user id.
     * @return \stdClass The submission.
     */
    public function mark_reconstruction_ready($userid = null): \stdClass {
        return $this->activity->submissions()->mark_time_if_empty($userid, 'timereconstructionready');
    }

    /**
     * Whether students may currently start/continue the reconstruction.
     *
     * When "hold reconstruction" is enabled the teacher opens it live.
     *
     * @return bool
     */
    public function is_reconstruction_open(): bool {
        return empty($this->instance->holdreconstruction) || !empty($this->instance->reconstructionopen);
    }

    /**
     * Open or close the live audio replay override.
     *
     * @param bool $open Whether replay is open.
     * @return void
     */
    public function set_replay_open($open): void {
        $this->set_instance_flag('replayopen', $open);
    }

    /**
     * Open or close the live reconstruction hold.
     *
     * @param bool $open Whether the reconstruction is open.
     * @return void
     */
    public function set_reconstruction_open($open): void {
        $this->set_instance_flag('reconstructionopen', $open);
    }

    /**
     * Manually reveal or hide the source text.
     *
     * @param bool $reveal Whether to reveal.
     * @return void
     */
    public function set_revealed($reveal): void {
        $this->set_instance_flag('revealed', $reveal);
    }

    /**
     * Set one of the live instance flags and persist it.
     *
     * @param string $field The flag field (must be in self::FLAGS).
     * @param bool $value The new value.
     * @return void
     */
    public function set_instance_flag($field, $value): void {
        global $DB;
        if (!in_array($field, self::FLAGS, true)) {
            throw new \coding_exception('Unknown activity flag: ' . $field);
        }
        $value = $value ? 1 : 0;
        $now = time();
        // Write only the changed column so concurrent flag changes are not clobbered.
        $DB->update_record('dictogloss', (object) [
            'id' => $this->instance->id,
            $field => $value,
            'timemodified' => $now,
        ]);
        $this->instance->$field = $value;
        $this->instance->timemodified = $now;
    }
}
