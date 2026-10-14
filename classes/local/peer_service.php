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
 * Sharing of group reconstructions.
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
 * Provides the group reconstructions a student may pull, and the pull rules.
 */
class peer_service {

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
     * Whether sharing of group reconstructions is configured at all.
     *
     * @return bool
     */
    public function is_peers_enabled(): bool {
        return !empty($this->instance->peerscope)
            && $this->instance->peerscope !== 'off'
            && (string) $this->instance->peersource !== 'none';
    }

    /**
     * Open or close group reconstructions on the spot.
     *
     * @param bool $open Whether to open.
     * @return void
     */
    public function set_peers_open($open): void {
        $this->activity->state()->set_instance_flag('peeropen', $open);
    }

    /**
     * Return the status of a student's ability to pull peers' reconstructions.
     *
     * @param int|null $userid The user id.
     * @return array ['ok' => bool, 'reason' => string|null, 'wait' => int]
     */
    public function get_peers_pull_status($userid = null): array {
        global $USER;
        if ($userid === null) {
            $userid = $USER->id;
        }

        if (!$this->is_peers_enabled()) {
            return ['ok' => false, 'reason' => 'off', 'wait' => 0];
        }
        if (empty($this->instance->peeropen)) {
            return ['ok' => false, 'reason' => 'closed', 'wait' => 0];
        }

        $submission = $this->activity->submissions()->get_submission($userid);
        $wordcount = 0;
        if ($submission && trim((string) $submission->reconstruction) !== '') {
            $wordcount = count(word_diff::tokenize((string) $submission->reconstruction));
        }
        if ($wordcount < max(0, (int) $this->instance->peermindraft)) {
            return ['ok' => false, 'reason' => 'draft', 'wait' => 0];
        }

        $cooldown = max(0, (int) $this->instance->peercooldown);
        if ($submission && !empty($submission->timepulledpeers)) {
            $elapsed = time() - (int) $submission->timepulledpeers;
            if ($elapsed < $cooldown) {
                return ['ok' => false, 'reason' => 'cooldown', 'wait' => $cooldown - $elapsed];
            }
        }

        return ['ok' => true, 'reason' => null, 'wait' => 0];
    }

    /**
     * Record that a student pulled peers' reconstructions.
     *
     * @param int|null $userid The user id.
     * @return void
     */
    public function mark_peers_pulled($userid = null): void {
        global $DB;
        $submission = $this->activity->submissions()->ensure_submission($userid);
        $now = time();
        $count = (int) $submission->peerpullcount + 1;
        $DB->update_record('dictogloss_submissions', (object) [
            'id' => $submission->id,
            'timepulledpeers' => $now,
            'peerpullcount' => $count,
            'timemodified' => $now,
        ]);
        $submission->timepulledpeers = $now;
        $submission->peerpullcount = $count;
        $submission->timemodified = $now;
    }

    /**
     * Return the reconstructions to share with a student, including their own.
     *
     * Returns raw data; the output layer decides how to label contributors.
     *
     * @param int|null $userid The viewing user id.
     * @return array List of ['userid' => int, 'fullname' => string, 'isown' => bool, 'text' => string].
     */
    public function get_peer_reconstructions($userid = null): array {
        global $DB, $USER;
        if ($userid === null) {
            $userid = $USER->id;
        }

        $source = $this->instance->peersource;
        if ($source === 'none') {
            return [];
        }

        $scope = $this->instance->peerscope;
        $params = ['dictogloss' => $this->instance->id];
        $sql = "SELECT s.userid, s.reconstruction, s.status, u.firstname, u.lastname, u.firstnamephonetic,
                       u.lastnamephonetic, u.middlename, u.alternatename
                  FROM {dictogloss_submissions} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.dictogloss = :dictogloss AND s.reconstruction IS NOT NULL AND s.reconstruction <> ''";

        if ($scope === 'random') {
            $userids = $this->activity->random()->get_random_scope_userids($userid, activity::RANDOM_FEATURE_PEERS);
            if ($userids) {
                // Only the student's own reconstruction and their random partners' are shared.
                list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'prnd');
                $sql .= " AND s.userid $insql";
                $params = array_merge($params, $inparams);
            } else {
                $sql .= " AND s.userid = :userid";
                $params['userid'] = $userid;
            }
        } else if ($scope !== 'class') {
            $groupids = $this->activity->scope()->get_scope_groupids($userid, $scope === 'grouping' ? 'grouping' : 'group');
            if ($groupids) {
                list($insql, $inparams) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'pgrp');
                $sql .= " AND s.userid IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid $insql)";
                $params = array_merge($params, $inparams);
            } else {
                // No groups: only the user's own submission is in scope.
                $sql .= " AND s.userid = :userid";
                $params['userid'] = $userid;
            }
        }

        if ($source === 'draft') {
            $sql .= " AND s.status >= :draftstatus AND s.status < :submitted";
            $params['draftstatus'] = activity::STATUS_RECONSTRUCTION;
            $params['submitted'] = activity::STATUS_SUBMITTED;
        } else if ($source === 'submitted') {
            $sql .= " AND s.status >= :submitted";
            $params['submitted'] = activity::STATUS_SUBMITTED;
        } else {
            // Both drafts and submitted.
            $sql .= " AND s.status >= :draftstatus";
            $params['draftstatus'] = activity::STATUS_RECONSTRUCTION;
        }

        $sql .= " ORDER BY s.userid ASC";
        $records = $DB->get_records_sql($sql, $params);

        $peers = [];
        foreach ($records as $record) {
            $user = new \stdClass();
            $user->firstname = $record->firstname;
            $user->lastname = $record->lastname;
            $user->firstnamephonetic = $record->firstnamephonetic;
            $user->lastnamephonetic = $record->lastnamephonetic;
            $user->middlename = $record->middlename;
            $user->alternatename = $record->alternatename;

            $peers[] = [
                'userid' => (int) $record->userid,
                'fullname' => fullname($user),
                'isown' => ((int) $record->userid === (int) $userid),
                'text' => (string) $record->reconstruction,
            ];
        }

        return $peers;
    }
}
