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
 * Domain root for a Dictogloss activity instance.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss;

use mod_dictogloss\local\grader;
use mod_dictogloss\local\overview_service;
use mod_dictogloss\local\peer_service;
use mod_dictogloss\local\random_service;
use mod_dictogloss\local\scope_resolver;
use mod_dictogloss\local\state_service;
use mod_dictogloss\local\submission_service;
use mod_dictogloss\local\wordcloud_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Aggregate root for a Dictogloss instance; exposes the domain services.
 */
class activity {

    /** No work started yet. */
    const STATUS_NOTSTARTED = 0;
    /** Keywords have been saved. */
    const STATUS_KEYWORDS = 1;
    /** A reconstruction draft has been saved. */
    const STATUS_RECONSTRUCTION = 2;
    /** The reconstruction has been submitted. */
    const STATUS_SUBMITTED = 3;
    /** The whole contribution has been handed in. */
    const STATUS_FINALISED = 4;

    /** Reveal the source automatically when the reconstruction is submitted. */
    const REVEAL_ONSUBMIT = 1;
    /** The teacher reveals the source manually. */
    const REVEAL_MANUAL = 2;

    /** Show grades immediately after submission. */
    const GRADE_ON_SUBMIT = 1;
    /** Show grades only after the comparison is released. */
    const GRADE_AFTER_RELEASE = 0;

    /** Random assignment feature: the word cloud source. */
    const RANDOM_FEATURE_WORDCLOUD = 'wordcloud';
    /** Random assignment feature: peer reconstructions. */
    const RANDOM_FEATURE_PEERS = 'peers';

    /** @var \stdClass The course module record. */
    protected $cm;
    /** @var \stdClass The course record. */
    protected $course;
    /** @var \stdClass The Dictogloss instance record. */
    protected $instance;
    /** @var \core\context\module The module context. */
    protected $context;
    /** @var array Lazily constructed domain services. */
    protected $services = [];

    /**
     * Constructor.
     *
     * @param \stdClass $cm The course module.
     * @param \stdClass $course The course.
     * @param \stdClass $instance The dictogloss instance.
     */
    public function __construct($cm, $course, $instance) {
        $this->cm = $cm;
        $this->course = $course;
        $this->instance = $instance;
        $this->context = \core\context\module::instance($cm->id);
    }

    /**
     * Return the course module record.
     *
     * @return \stdClass|\core_course\cm_info
     */
    public function get_cm() {
        return $this->cm;
    }

    /**
     * Return the course record.
     *
     * @return \stdClass
     */
    public function get_course() {
        return $this->course;
    }

    /**
     * Return the dictogloss instance record.
     *
     * @return \stdClass
     */
    public function get_instance() {
        return $this->instance;
    }

    /**
     * Return the module context.
     *
     * @return \core\context\module
     */
    public function get_context(): \core\context\module {
        return $this->context;
    }

    /**
     * Lazily build and cache a domain service.
     *
     * @param string $key The service key.
     * @param callable $factory The factory callable.
     * @return mixed
     */
    protected function service(string $key, callable $factory) {
        if (!isset($this->services[$key])) {
            $this->services[$key] = $factory();
        }
        return $this->services[$key];
    }

    /**
     * Group/grouping scoping service.
     *
     * @return scope_resolver
     */
    public function scope(): scope_resolver {
        return $this->service('scope', fn() => new scope_resolver($this));
    }

    /**
     * Reveal/comparison/replay state service.
     *
     * @return state_service
     */
    public function state(): state_service {
        return $this->service('state', fn() => new state_service($this));
    }

    /**
     * Submission workflow service.
     *
     * @return submission_service
     */
    public function submissions(): submission_service {
        return $this->service('submissions', fn() => new submission_service($this));
    }

    /**
     * Grading service.
     *
     * @return grader
     */
    public function grader(): grader {
        return $this->service('grader', fn() => new grader($this));
    }

    /**
     * Random assignment service.
     *
     * @return random_service
     */
    public function random(): random_service {
        return $this->service('random', fn() => new random_service($this));
    }

    /**
     * Word cloud service.
     *
     * @return wordcloud_service
     */
    public function wordcloud(): wordcloud_service {
        return $this->service('wordcloud', fn() => new wordcloud_service($this));
    }

    /**
     * Peer reconstructions service.
     *
     * @return peer_service
     */
    public function peers(): peer_service {
        return $this->service('peers', fn() => new peer_service($this));
    }

    /**
     * Teacher overview service.
     *
     * @return overview_service
     */
    public function overview(): overview_service {
        return $this->service('overview', fn() => new overview_service($this));
    }

    /**
     * Whether the given user can submit.
     *
     * @param int|null $userid The user id, defaults to the current user.
     * @return bool
     */
    public function can_submit($userid = null): bool {
        return has_capability('mod/dictogloss:submit', $this->context, $userid);
    }

    /**
     * Return the audio files attached to the activity.
     *
     * @return array
     */
    public function get_audio_files(): array {
        $fs = get_file_storage();
        return $fs->get_area_files($this->context->id, 'mod_dictogloss', 'audio',
            0, 'filename', false);
    }

    /**
     * Whether the activity has an audio recording.
     *
     * @return bool
     */
    public function has_audio(): bool {
        return !empty($this->get_audio_files());
    }

    /**
     * The normalised sentence-clustering threshold (0..1).
     *
     * @return float
     */
    public function threshold(): float {
        return max(0, min(100, (int) $this->instance->similaritythreshold)) / 100;
    }

    /**
     * Whether the word cloud source is set to random assignment.
     *
     * @return bool
     */
    public function is_wordcloud_random(): bool {
        return (string) $this->instance->wordcloudmode === 'random';
    }

    /**
     * Whether peer sharing is set to random assignment.
     *
     * @return bool
     */
    public function is_peers_random(): bool {
        return (string) $this->instance->peerscope === 'random';
    }
}
