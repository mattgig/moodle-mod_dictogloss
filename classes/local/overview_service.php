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
 * Teacher monitor and sentence-variant overview.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

use mod_dictogloss\activity;
use mod_dictogloss\diff\word_diff;
use mod_dictogloss\overview\sentence_clusterer;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the teacher monitor rows and the sentence-variant overview.
 */
class overview_service {

    /** Safety cap on rows loaded for the monitor/variants overview queries. */
    const OVERVIEW_ROW_LIMIT = 5000;

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
     * Apply a group/user scope to a submissions query.
     *
     * @param string $sql SQL fragment to append to (by reference).
     * @param array $params Query params (by reference).
     * @param int|null $groupid Optional group filter.
     * @param array|null $userids Optional explicit user ids (takes precedence).
     * @return void
     */
    protected function apply_scope(&$sql, &$params, $groupid, $userids): void {
        global $DB;
        if ($userids !== null) {
            list($insql, $inparams) = $DB->get_in_or_equal(array_map('intval', $userids), SQL_PARAMS_NAMED, 'tusr');
            $sql .= " AND s.userid $insql";
            $params = array_merge($params, $inparams);
        } else if ($groupid) {
            $sql .= self::group_scope_sql();
            $params['groupid'] = $groupid;
        }
    }

    /**
     * The SQL fragment restricting a submissions query to one group.
     *
     * Expects a :groupid bound parameter.
     *
     * @return string
     */
    protected static function group_scope_sql(): string {
        return " AND s.userid IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid = :groupid)";
    }

    /**
     * Return a lightweight list of contributors (id + name only) for navigation.
     *
     * @param int|null $groupid Optional group filter.
     * @param array|null $userids Optional explicit list of user ids (takes precedence).
     * @return array List of ['userid' => int, 'fullname' => string].
     */
    public function get_contributors($groupid = null, $userids = null): array {
        global $DB;
        if ($userids !== null && !$userids) {
            return [];
        }
        $params = ['dictogloss' => $this->instance->id];
        $sql = "SELECT s.userid, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename
                  FROM {dictogloss_submissions} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.dictogloss = :dictogloss";
        $this->apply_scope($sql, $params, $groupid, $userids);
        $sql .= " ORDER BY u.lastname ASC, u.firstname ASC";

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params, 0, self::OVERVIEW_ROW_LIMIT) as $record) {
            $rows[] = ['userid' => (int) $record->userid, 'fullname' => fullname($record)];
        }
        return $rows;
    }

    /**
     * Return monitor rows for all contributions.
     *
     * @param int|null $groupid Optional group filter.
     * @param array|null $userids Optional explicit list of user ids (takes precedence).
     * @return array
     */
    public function get_monitor_rows($groupid = null, $userids = null): array {
        global $DB;
        if ($userids !== null && !$userids) {
            return [];
        }
        $params = ['dictogloss' => $this->instance->id];
        $sql = "SELECT s.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.picture, u.imagealt
                  FROM {dictogloss_submissions} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.dictogloss = :dictogloss";
        $this->apply_scope($sql, $params, $groupid, $userids);
        $sql .= " ORDER BY u.lastname ASC, u.firstname ASC";
        $records = $DB->get_records_sql($sql, $params, 0, self::OVERVIEW_ROW_LIMIT);

        $rows = [];
        foreach ($records as $record) {
            $userpicture = new \stdClass();
            $userpicture->id = $record->userid;
            $userpicture->firstname = $record->firstname;
            $userpicture->lastname = $record->lastname;
            $userpicture->firstnamephonetic = $record->firstnamephonetic;
            $userpicture->lastnamephonetic = $record->lastnamephonetic;
            $userpicture->middlename = $record->middlename;
            $userpicture->alternatename = $record->alternatename;
            $userpicture->picture = $record->picture;
            $userpicture->imagealt = $record->imagealt;
            $userpicture->email = '';

            $rows[] = [
                'userid' => (int) $record->userid,
                'fullname' => fullname($userpicture),
                'groupid' => (int) $record->groupid,
                'status' => (int) $record->status,
                'keywords' => $record->keywords,
                'reconstruction' => $record->reconstruction,
                'followup' => $record->followup,
                'autograde' => $record->autograde,
                'grade' => $record->grade,
                'gradeoverridden' => (int) $record->gradeoverridden,
                'timemodified' => (int) $record->timemodified,
            ];
        }
        return $rows;
    }

    /**
     * Return the summary statistics for the results page.
     *
     * @param int $groupid Optional group filter.
     * @param array|null $userids Optional explicit list of user ids (takes precedence; [] means none).
     * @return array ['enrolled', 'notstarted', 'inprogress', 'submitted', 'finalised', 'average'].
     */
    public function get_results_stats($groupid = 0, $userids = null): array {
        global $DB;

        if ($userids !== null && !$userids) {
            return ['enrolled' => 0, 'notstarted' => 0, 'inprogress' => 0,
                'submitted' => 0, 'finalised' => 0, 'average' => null];
        }

        $courseid = $this->activity->get_course()->id;
        $params = ['courseid' => $courseid];
        $from = "{user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid";
        $where = "u.deleted = 0 AND ue.status = 0";
        $scopeclause = "";
        if ($userids !== null) {
            list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'vis');
            $scopeclause = " AND u.id $insql";
            $params = array_merge($params, $inparams);
        }
        if ($groupid) {
            // Applied in addition to any user scope so the group filter still narrows the result.
            $scopeclause .= " AND u.id IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid = :groupid)";
            $params['groupid'] = $groupid;
        }
        $enrolled = (int) $DB->count_records_sql("SELECT COUNT(DISTINCT u.id) FROM $from WHERE $where $scopeclause", $params);

        // One grouped query provides both the per-status counts and the grade aggregate.
        $sparams = ['dictoglossid' => $this->instance->id];
        $statussql = "SELECT s.status,
                             COUNT(*) AS c,
                             SUM(CASE WHEN s.grade IS NOT NULL THEN s.grade ELSE 0 END) AS gradesum,
                             SUM(CASE WHEN s.grade IS NOT NULL THEN 1 ELSE 0 END) AS gradecount
                        FROM {dictogloss_submissions} s
                       WHERE s.dictogloss = :dictoglossid";
        if ($userids !== null) {
            list($sinsql, $sinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'viss');
            $statussql .= " AND s.userid $sinsql";
            $sparams = array_merge($sparams, $sinparams);
        }
        if ($groupid) {
            $statussql .= self::group_scope_sql();
            $sparams['groupid'] = $groupid;
        }
        $statussql .= " GROUP BY s.status";

        $statuscounts = [];
        $gradesum = 0.0;
        $gradecount = 0;
        foreach ($DB->get_records_sql($statussql, $sparams) as $record) {
            $statuscounts[(int) $record->status] = (int) $record->c;
            $gradesum += (float) $record->gradesum;
            $gradecount += (int) $record->gradecount;
        }

        $inprogress = ($statuscounts[activity::STATUS_KEYWORDS] ?? 0) + ($statuscounts[activity::STATUS_RECONSTRUCTION] ?? 0);
        $submitted = $statuscounts[activity::STATUS_SUBMITTED] ?? 0;
        $finalised = $statuscounts[activity::STATUS_FINALISED] ?? 0;
        $average = $gradecount > 0 ? $gradesum / $gradecount : null;

        return [
            'enrolled' => $enrolled,
            'notstarted' => max(0, $enrolled - $inprogress - $submitted - $finalised),
            'inprogress' => $inprogress,
            'submitted' => $submitted,
            'finalised' => $finalised,
            'average' => $average,
        ];
    }

    /**
     * Build the sentence-variant overview for the teacher.
     *
     * @param int|null $groupid Optional group filter.
     * @param array|null $userids Optional explicit list of user ids (takes precedence).
     * @return array
     */
    public function get_variants_overview($groupid = null, $userids = null): array {
        global $DB;
        if ($userids !== null && !$userids) {
            return ['slots' => []];
        }
        $params = ['dictogloss' => $this->instance->id];
        $sql = "SELECT s.userid, s.reconstruction, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename
                  FROM {dictogloss_submissions} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.dictogloss = :dictogloss AND s.reconstruction IS NOT NULL AND s.reconstruction <> ''";
        $this->apply_scope($sql, $params, $groupid, $userids);
        $records = $DB->get_records_sql($sql, $params, 0, self::OVERVIEW_ROW_LIMIT);

        $reconstructions = [];
        $authorids = [];
        foreach ($records as $record) {
            $user = new \stdClass();
            $user->firstname = $record->firstname;
            $user->lastname = $record->lastname;
            $user->firstnamephonetic = $record->firstnamephonetic;
            $user->lastnamephonetic = $record->lastnamephonetic;
            $user->middlename = $record->middlename;
            $user->alternatename = $record->alternatename;
            $userid = (int) $record->userid;
            $authorids[$userid] = $userid;
            $reconstructions[] = [
                'author' => fullname($user),
                'authorid' => $userid,
                'sentences' => word_diff::split_sentences((string) $record->reconstruction),
            ];
        }

        $groupmap = $this->activity->scope()->get_group_names_for_users($authorids);
        $overview = sentence_clusterer::cluster($reconstructions, $this->activity->state()->get_plain_source(),
            $this->activity->threshold());

        // Attach the group name(s) to each author so the overview can group contributors.
        foreach ($overview['slots'] as $slotindex => $slot) {
            foreach ($slot['options'] as $optionindex => $option) {
                foreach ($option['authors'] as $authorindex => $author) {
                    $overview['slots'][$slotindex]['options'][$optionindex]['authors'][$authorindex]['groups'] =
                        $groupmap[$author['id']] ?? [];
                }
            }
        }

        return $overview;
    }
}
