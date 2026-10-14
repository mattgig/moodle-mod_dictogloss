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
 * GET action handling for the Dictogloss view page.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles the small action links on the Dictogloss view page.
 */
class view_actions {

    /**
     * Reopen a handed-in contribution (teacher only).
     *
     * Redirects and exits when handled.
     *
     * @param \mod_dictogloss\activity $activity The activity.
     * @param bool $isteacher Whether the current user can manage the activity.
     * @param string $action The requested action.
     * @param \moodle_url $returnurl The URL to redirect back to.
     * @return void
     */
    public static function maybe_reopen(\mod_dictogloss\activity $activity, $isteacher, $action,
            \moodle_url $returnurl): void {
        if (!$isteacher || $action !== 'reopen') {
            return;
        }
        require_sesskey();
        $targetuserid = required_param('userid', PARAM_INT);
        if (!$activity->can_access_user($targetuserid)) {
            redirect($returnurl, get_string('nopermissions', 'error', get_string('reopen', 'mod_dictogloss')),
                null, \core\output\notification::NOTIFY_ERROR);
        }
        $activity->submissions()->reopen_submission($targetuserid);
        redirect($returnurl, get_string('reopenedsuccess', 'mod_dictogloss'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    /**
     * Advance a student from the "listen again" step to the reconstruction.
     *
     * Redirects and exits when handled.
     *
     * @param \mod_dictogloss\activity $activity The activity.
     * @param bool $isown Whether the page is the current user's own contribution.
     * @param bool $canedit Whether the current user can edit the contribution.
     * @param string $action The requested action.
     * @param int $viewuserid The user whose contribution is shown.
     * @param \moodle_url $returnurl The URL to redirect back to.
     * @return void
     */
    public static function maybe_ready_reconstruction(\mod_dictogloss\activity $activity, $isown, $canedit,
            $action, $viewuserid, \moodle_url $returnurl): void {
        if (!$isown || !$canedit || $action !== 'readyreconstruction') {
            return;
        }
        require_sesskey();
        $activity->state()->mark_reconstruction_ready($viewuserid);
        redirect($returnurl, get_string('saved', 'mod_dictogloss'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}
