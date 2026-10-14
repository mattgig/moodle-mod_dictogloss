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
 * Group and grouping scoping for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

use mod_dictogloss\activity;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves the groups/groupings in scope and the group filter options.
 */
class scope_resolver {

    /** @var \stdClass The course module record. */
    protected $cm;
    /** @var \stdClass The course record. */
    protected $course;

    /**
     * Constructor.
     *
     * @param activity $activity The activity.
     */
    public function __construct(activity $activity) {
        $this->cm = $activity->get_cm();
        $this->course = $activity->get_course();
    }

    /**
     * Return group ids in scope for the given mode.
     *
     * @param int $userid The user id.
     * @param string $mode 'group' or 'grouping'.
     * @return array
     */
    public function get_scope_groupids($userid, $mode = 'group'): array {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $usergroups = groups_get_user_groups($this->course->id, $userid);
        // Moodle 4.x/5.x returns an array keyed by grouping id (plus a '0' bucket with all
        // the user's groups); older versions returned an object with a ->groups property.
        if (is_object($usergroups) && isset($usergroups->groups)) {
            $usergroups = (array) $usergroups->groups;
        }
        if (!is_array($usergroups)) {
            $usergroups = [];
        }

        $activitygrouping = isset($this->cm->groupingid) ? (int) $this->cm->groupingid : 0;
        $groupids = [];

        // When the activity is bound to a grouping, only groups in that grouping apply.
        // This is what lets a teacher scope pairs inside a larger course group.
        if ($activitygrouping > 0) {
            if ($mode === 'grouping') {
                foreach (groups_get_all_groups($this->course->id, 0, $activitygrouping, 'g.id') as $group) {
                    $groupids[$group->id] = $group->id;
                }
            } else if (!empty($usergroups[$activitygrouping])) {
                foreach ($usergroups[$activitygrouping] as $groupid) {
                    $groupids[$groupid] = $groupid;
                }
            }
            return array_values($groupids);
        }

        if ($mode === 'grouping') {
            // All groups in each grouping the user belongs to.
            foreach (array_keys($usergroups) as $groupingid) {
                if ((string) $groupingid === '0') {
                    continue;
                }
                foreach (groups_get_all_groups($this->course->id, 0, $groupingid, 'g.id') as $group) {
                    $groupids[$group->id] = $group->id;
                }
            }
        } else if (!empty($usergroups['0'])) {
            // The '0' bucket holds every group the user belongs to.
            foreach ($usergroups['0'] as $groupid) {
                $groupids[$groupid] = $groupid;
            }
        } else {
            // Fallback for the legacy structure: union of every bucket.
            foreach ($usergroups as $groups) {
                foreach ((array) $groups as $groupid) {
                    $groupids[$groupid] = $groupid;
                }
            }
        }

        return array_values($groupids);
    }

    /**
     * Return the group id used to store the given user's submission.
     *
     * @param int|null $userid The user id.
     * @return int
     */
    public function get_group_id($userid = null): int {
        global $USER;
        if ($userid === null) {
            $userid = $USER->id;
        }
        $groups = $this->get_scope_groupids($userid, 'group');
        if (!$groups) {
            return 0;
        }
        // Deterministic primary group for storage when the user is in several groups.
        sort($groups, SORT_NUMERIC);
        return (int) $groups[0];
    }

    /**
     * Return a map of userid => list of group names.
     *
     * @param array $userids User ids.
     * @return array
     */
    public function get_group_names_for_users(array $userids): array {
        global $DB;
        if (!$userids) {
            return [];
        }
        list($insql, $params) = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_QM);
        $sql = "SELECT gm.userid, g.name
                  FROM {groups_members} gm
                  JOIN {groups} g ON g.id = gm.groupid
                 WHERE gm.userid $insql
              ORDER BY g.name";
        $map = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $map[(int) $record->userid][] = $record->name;
        }
        return $map;
    }

    /**
     * Build the options for a "filter by group" select.
     *
     * @param int $groupingid Restrict to a grouping (0 for all groups).
     * @return array Group id => name, with 0 => "All groups".
     */
    public function get_group_filter_options($groupingid = 0): array {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $context = \core\context\module::instance($this->cm->id);
        if (!has_capability('moodle/site:accessallgroups', $context)
                && groups_get_activity_groupmode($this->cm, $this->course) == SEPARATEGROUPS) {
            // Restricted teachers may only filter by the groups they can access.
            $options = [];
            foreach (groups_get_activity_allowed_groups($this->cm) as $group) {
                $options[(int) $group->id] = $group->name;
            }
            return $options;
        }

        $options = [0 => get_string('allgroups', 'mod_dictogloss')];
        foreach (groups_get_all_groups($this->course->id, 0, (int) $groupingid, 'g.id, g.name') as $group) {
            $options[(int) $group->id] = $group->name;
        }
        return $options;
    }

    /**
     * Return the user ids the current viewer may see, or null when unrestricted.
     *
     * Implements the separate-groups boundary for teacher-facing lists: a user
     * without moodle/site:accessallgroups only sees members of the groups they
     * belong to in this activity.
     *
     * @return array|null A list of user ids, or null when there is no restriction.
     */
    public function get_visible_userids(): ?array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');

        $context = \core\context\module::instance($this->cm->id);
        if (has_capability('moodle/site:accessallgroups', $context)) {
            return null;
        }
        if (groups_get_activity_groupmode($this->cm, $this->course) != SEPARATEGROUPS) {
            return null;
        }

        $allowed = groups_get_activity_allowed_groups($this->cm);
        if (!$allowed) {
            return [];
        }
        $groupids = array_map('intval', array_keys($allowed));
        list($insql, $params) = $DB->get_in_or_equal($groupids, SQL_PARAMS_QM);
        return array_map('intval',
            $DB->get_fieldset_select('groups_members', 'DISTINCT userid', "groupid $insql", $params));
    }
}
