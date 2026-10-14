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
 * Automatic similarity grading for the Dictogloss activity.
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
 * Calculates the automatic grade and pushes grades to the gradebook.
 */
class grader {

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
     * Push grades to the gradebook for the activity (or one user).
     *
     * @param int $userid Optional single user id.
     * @param bool $nullifnone Whether to push a null grade when there is no grade.
     * @return void
     */
    public function update($userid = 0, $nullifnone = true): void {
        dictogloss_update_grades($this->instance, $userid, $nullifnone);
    }

    /**
     * Recalculate and store the automatic grade for a submission.
     *
     * @param \stdClass $submission The submission.
     * @return float|null The similarity percentage, if calculable.
     */
    public function recalculate($submission): ?float {
        global $DB;

        if (empty($this->instance->autograding) || trim((string) $submission->reconstruction) === '') {
            return null;
        }

        $comparison = word_diff::compare($this->activity->state()->get_plain_source(), $submission->reconstruction);
        $score = (float) $comparison['score'];

        $update = (object) [
            'id' => $submission->id,
            'autograde' => $score,
        ];
        $submission->autograde = $score;

        if (empty($submission->gradeoverridden) && !empty($this->instance->grade)) {
            $submission->grade = round($score / 100 * (float) $this->instance->grade, 5);
            $update->grade = $submission->grade;
        }
        $DB->update_record('dictogloss_submissions', $update);

        if (!empty($this->instance->grade)) {
            $this->update($submission->userid);
        }

        return $score;
    }
}
