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
 * POST action handling for the Dictogloss results page.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles the bulk reopen/delete actions submitted to results.php.
 */
class results_actions {

    /**
     * Process any submitted bulk contribution action.
     *
     * Redirects and exits when an action has been handled.
     *
     * @param \mod_dictogloss\activity $activity The activity.
     * @param \moodle_url $returnurl The URL to redirect back to.
     * @return void
     */
    public static function process(\mod_dictogloss\activity $activity, \moodle_url $returnurl): void {
        $action = optional_param('action', '', PARAM_ALPHA);
        if ($action !== 'deleteselected' && $action !== 'reopenselected') {
            return;
        }

        // These are destructive actions and must be POSTed.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        // Viewing reports is a read capability; deleting/reopening requires write access.
        require_capability('mod/dictogloss:manage', $activity->get_context());
        require_sesskey();
        $selected = optional_param_array('selected', [], PARAM_INT);

        $changed = 0;
        foreach ($selected as $targetuserid) {
            if ($action === 'reopenselected') {
                if ($activity->submissions()->reopen_submission((int) $targetuserid)) {
                    $changed++;
                }
            } else if ($activity->submissions()->delete_submission((int) $targetuserid)) {
                $changed++;
            }
        }

        if ($changed === 0) {
            redirect($returnurl, get_string('noselection', 'mod_dictogloss'), null,
                \core\output\notification::NOTIFY_ERROR);
        }

        $message = ($action === 'reopenselected')
            ? get_string('reopenedselectedsuccess', 'mod_dictogloss')
            : get_string('submissiondeleted', 'mod_dictogloss');
        redirect($returnurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
}
