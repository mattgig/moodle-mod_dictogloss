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
 * POST action handling for the Dictogloss monitoring report.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles the teacher moderation/deletion actions submitted to report.php.
 */
class report_actions {

    /**
     * Process any submitted moderation/deletion action.
     *
     * Redirects and exits when an action has been handled.
     *
     * @param \mod_dictogloss\activity $activity The activity.
     * @param \core\context\module $context The module context.
     * @param \stdClass $instance The dictogloss instance.
     * @param int $groupfilter The selected group filter (0 for none).
     * @param array|null $wordclouduserids Explicit user scope for the word cloud, if any.
     * @param \moodle_url $returnurl The URL to redirect back to.
     * @return void
     */
    public static function process(\mod_dictogloss\activity $activity, $context, $instance,
            $groupfilter, $wordclouduserids, \moodle_url $returnurl): void {
        global $DB, $USER;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = optional_param('action', '', PARAM_ALPHA);

        // Delete a single student's contribution.
        if ($action === 'deletesubmission') {
            require_sesskey();
            $targetuserid = required_param('userid', PARAM_INT);
            $activity->submissions()->delete_submission($targetuserid);
            redirect($returnurl, get_string('submissiondeleted', 'mod_dictogloss'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }

        // Delete the selected contributions.
        if ($action === 'deleteselected') {
            require_sesskey();
            $selected = optional_param_array('selected', [], PARAM_INT);
            $deleted = 0;
            foreach ($selected as $targetuserid) {
                if ($activity->submissions()->delete_submission((int) $targetuserid)) {
                    $deleted++;
                }
            }
            if ($deleted === 0) {
                redirect($returnurl, get_string('noselection', 'mod_dictogloss'), null,
                    \core\output\notification::NOTIFY_ERROR);
            }
            redirect($returnurl, get_string('submissiondeleted', 'mod_dictogloss'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }

        // Delete selected keyword entries.
        if (isset($_POST['deletewords'])) {
            require_sesskey();
            $words = optional_param_array('selectedwords', [], PARAM_RAW);
            if (!$words) {
                redirect($returnurl, get_string('noselection', 'mod_dictogloss'), null,
                    \core\output\notification::NOTIFY_ERROR);
            }
            $activity->wordcloud()->delete_keywords($words, $groupfilter ?: null, $wordclouduserids);

            $event = \mod_dictogloss\event\words_moderated::create([
                'objectid' => $instance->id,
                'context' => $context,
                'userid' => $USER->id,
            ]);
            $event->trigger();

            redirect($returnurl, get_string('keywordsdeleted', 'mod_dictogloss'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }

        // Handle word cloud moderation.
        if (isset($_POST['savemoderation'])) {
            require_sesskey();

            $words = optional_param_array('word', [], PARAM_TEXT);
            $hides = optional_param_array('hide', [], PARAM_INT);
            $renames = optional_param_array('rename', [], PARAM_RAW);

            $activity->wordcloud()->save_moderation($words, $hides, $renames, $USER->id);

            $event = \mod_dictogloss\event\words_moderated::create([
                'objectid' => $instance->id,
                'context' => $context,
                'userid' => $USER->id,
            ]);
            $event->trigger();

            redirect($returnurl, get_string('saved', 'mod_dictogloss'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}
