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
 * Autosave field handling for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Applies an autosave field update to a submission.
 */
class autosave_handler {

    /**
     * Save one autosaved field for the current user.
     *
     * @param \mod_dictogloss\activity $activity The activity.
     * @param string $field The field name (keywords|reconstruction|followup|played).
     * @param string $value The field value.
     * @return array JSON-ready result.
     */
    public static function handle(\mod_dictogloss\activity $activity, $field, $value): array {
        global $USER;

        // No edits after the contribution has been handed in.
        $submission = $activity->submissions()->get_submission($USER->id);
        $status = $submission ? (int) $submission->status : \mod_dictogloss\activity::STATUS_NOTSTARTED;
        if ($status >= \mod_dictogloss\activity::STATUS_FINALISED) {
            return ['ok' => false, 'reason' => 'finalised'];
        }

        $instance = $activity->get_instance();
        switch ($field) {
            case 'keywords':
                if (empty($instance->shownotes)) {
                    return ['ok' => false, 'reason' => 'off'];
                }
                if ($status >= \mod_dictogloss\activity::STATUS_SUBMITTED) {
                    // Notes are frozen once the reconstruction has been submitted.
                    return ['ok' => false, 'reason' => 'submitted'];
                }
                $activity->submissions()->save_keywords($value, $USER->id);
                break;
            case 'reconstruction':
                if ($status >= \mod_dictogloss\activity::STATUS_SUBMITTED) {
                    // The reconstruction is frozen once it has been submitted (and the source revealed).
                    return ['ok' => false, 'reason' => 'submitted'];
                }
                if (!$activity->state()->is_reconstruction_open()) {
                    return ['ok' => false, 'reason' => 'hold'];
                }
                $activity->submissions()->save_reconstruction($value, $USER->id);
                break;
            case 'followup':
                if (empty($instance->allowfollowup)) {
                    return ['ok' => false, 'reason' => 'off'];
                }
                if ($status < \mod_dictogloss\activity::STATUS_SUBMITTED) {
                    return ['ok' => false, 'reason' => 'stage'];
                }
                $activity->submissions()->save_followup($value, $USER->id);
                break;
            case 'played':
                $activity->submissions()->mark_played($USER->id);
                break;
            default:
                throw new \moodle_exception('invalidfield', 'mod_dictogloss');
        }

        return ['ok' => true, 'time' => time()];
    }
}
