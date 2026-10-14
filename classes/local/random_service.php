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
 * Random partner assignment for the word cloud and peer scopes.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

use mod_dictogloss\activity;

defined('MOODLE_INTERNAL') || die();

/**
 * Assigns and stores random partners for the word cloud and peer scopes.
 */
class random_service {

    /** @var activity The activity. */
    protected $activity;
    /** @var \stdClass The dictogloss instance record. */
    protected $instance;
    /** @var \stdClass The course record. */
    protected $course;
    /** @var \core\context\module The module context. */
    protected $context;
    /** @var array Per-request cache of pool user ids keyed by pool signature. */
    protected $randompoolcache = [];
    /** @var array Per-request cache of partner user ids keyed by feature and user. */
    protected $randompartnercache = [];

    /**
     * Constructor.
     *
     * @param activity $activity The activity.
     */
    public function __construct(activity $activity) {
        $this->activity = $activity;
        $this->instance = $activity->get_instance();
        $this->course = $activity->get_course();
        $this->context = $activity->get_context();
    }

    /**
     * Return the random assignment settings for a feature.
     *
     * Sizes are always per-feature. The source pool is the dedicated shared pool when random
     * sharing is on, otherwise the feature's own pool.
     *
     * @param string $feature activity::RANDOM_FEATURE_WORDCLOUD or RANDOM_FEATURE_PEERS.
     * @return array ['size' => int, 'pool' => string, 'groupingid' => int, 'groupid' => int]
     */
    public function get_random_settings($feature): array {
        if (!empty($this->instance->randomshared)) {
            return [
                'size' => $this->get_random_size($feature),
                'pool' => (string) $this->instance->randomsharedpool,
                'groupingid' => (int) $this->instance->randomsharedgroupingid,
                'groupid' => (int) $this->instance->randomsharedgroupid,
            ];
        }
        if ($feature === activity::RANDOM_FEATURE_PEERS) {
            return [
                'size' => (int) $this->instance->peersrandomsize,
                'pool' => (string) $this->instance->peersrandompool,
                'groupingid' => (int) $this->instance->peersrandomgroupingid,
                'groupid' => (int) $this->instance->peersrandomgroupid,
            ];
        }
        return [
            'size' => (int) $this->instance->wordcloudrandomsize,
            'pool' => (string) $this->instance->wordcloudrandompool,
            'groupingid' => (int) $this->instance->wordcloudrandomgroupingid,
            'groupid' => (int) $this->instance->wordcloudrandomgroupid,
        ];
    }

    /**
     * Return the configured team size for a feature.
     *
     * @param string $feature activity::RANDOM_FEATURE_WORDCLOUD or RANDOM_FEATURE_PEERS.
     * @return int
     */
    protected function get_random_size($feature): int {
        return ($feature === activity::RANDOM_FEATURE_PEERS)
            ? (int) $this->instance->peersrandomsize
            : (int) $this->instance->wordcloudrandomsize;
    }

    /**
     * Return the pool user ids for a feature, memoised per request.
     *
     * @param string $feature activity::RANDOM_FEATURE_WORDCLOUD or RANDOM_FEATURE_PEERS.
     * @return array
     */
    protected function get_random_pool($feature): array {
        $settings = $this->get_random_settings($feature);
        $key = $settings['pool'] . ':' . $settings['groupingid'] . ':' . $settings['groupid'];
        if (!array_key_exists($key, $this->randompoolcache)) {
            $this->randompoolcache[$key] = $this->get_random_pool_userids(
                $settings['pool'], $settings['groupingid'], $settings['groupid']);
        }
        return $this->randompoolcache[$key];
    }

    /**
     * Return the user ids eligible to be chosen as random partners for a pool.
     *
     * @param string $pool 'class', 'grouping' or 'group'.
     * @param int $groupingid The grouping id when the pool is a grouping.
     * @param int $groupid The group id when the pool is a group.
     * @return array
     */
    protected function get_random_pool_userids($pool, $groupingid = 0, $groupid = 0): array {
        global $DB;

        // Capability-aware enrolled users as a subquery, so each pool is a single query.
        list($enrolledsql, $params) = get_enrolled_sql($this->context, 'mod/dictogloss:submit', 0, true);

        if ($pool === 'grouping' && $groupingid) {
            $sql = "SELECT DISTINCT ue.id
                      FROM ($enrolledsql) ue
                      JOIN {groups_members} gm ON gm.userid = ue.id
                      JOIN {groupings_groups} gg ON gg.groupid = gm.groupid
                     WHERE gg.groupingid = :groupingid";
            $params['groupingid'] = $groupingid;
        } else if ($pool === 'group' && $groupid) {
            $sql = "SELECT DISTINCT ue.id
                      FROM ($enrolledsql) ue
                      JOIN {groups_members} gm ON gm.userid = ue.id
                     WHERE gm.groupid = :groupid";
            $params['groupid'] = $groupid;
        } else {
            $sql = "SELECT DISTINCT ue.id FROM ($enrolledsql) ue";
        }

        $userids = array_map('intval', array_values($DB->get_fieldset_sql($sql, $params)));
        sort($userids, SORT_NUMERIC);
        return $userids;
    }

    /**
     * Whether random partners have already been generated for a feature.
     *
     * @param string $feature activity::RANDOM_FEATURE_WORDCLOUD or RANDOM_FEATURE_PEERS.
     * @return bool
     */
    public function has_random_partners($feature): bool {
        global $DB;
        return $DB->record_exists('dictogloss_randompartners',
            ['dictogloss' => $this->instance->id, 'feature' => $feature]);
    }

    /**
     * Generate random partners for a feature if none are stored yet.
     *
     * @param string $feature activity::RANDOM_FEATURE_WORDCLOUD or RANDOM_FEATURE_PEERS.
     * @return void
     */
    public function ensure_random_partners($feature): void {
        if (!$this->has_random_partners($feature)) {
            $this->generate_random_partners($feature);
        }
    }

    /**
     * Regenerate the random partners for a feature.
     *
     * @param string $feature activity::RANDOM_FEATURE_WORDCLOUD or RANDOM_FEATURE_PEERS.
     * @return void
     */
    public function reshuffle_random_partners($feature): void {
        global $DB;
        $DB->delete_records('dictogloss_randompartners',
            ['dictogloss' => $this->instance->id, 'feature' => $feature]);
        $this->randompartnercache = [];
        $this->generate_random_partners($feature);
    }

    /**
     * Assign each student in the pool a set of randomly chosen partners.
     *
     * Partners are chosen independently: two students need not list one another.
     *
     * @param string $feature activity::RANDOM_FEATURE_WORDCLOUD or RANDOM_FEATURE_PEERS.
     * @return void
     */
    public function generate_random_partners($feature): void {
        global $DB;
        $settings = $this->get_random_settings($feature);
        $size = max(1, (int) $settings['size']);
        $pool = $this->get_random_pool($feature);

        if ($size < 2 || count($pool) < 2) {
            return;
        }

        $now = time();
        foreach ($pool as $userid) {
            $userid = (int) $userid;
            $candidates = array_values(array_filter($pool, fn($candidate) => (int) $candidate !== $userid));
            shuffle($candidates);
            $take = min($size - 1, count($candidates));
            for ($i = 0; $i < $take; $i++) {
                try {
                    $DB->insert_record('dictogloss_randompartners', (object) [
                        'dictogloss' => $this->instance->id,
                        'feature' => $feature,
                        'userid' => $userid,
                        'partnerid' => (int) $candidates[$i],
                        'timecreated' => $now,
                    ]);
                } catch (\dml_write_exception $e) {
                    // Duplicate row created by a concurrent generation; ignore it.
                }
            }
        }
    }

    /**
     * Return the partners assigned to a user for a feature, including the user.
     *
     * @param int $userid The user id.
     * @param string $feature activity::RANDOM_FEATURE_WORDCLOUD or RANDOM_FEATURE_PEERS.
     * @return array List of user ids.
     */
    public function get_random_partner_ids($userid, $feature): array {
        global $DB;
        $key = $feature . ':' . (int) $userid;
        if (isset($this->randompartnercache[$key])) {
            return $this->randompartnercache[$key];
        }

        $this->ensure_random_partners($feature);
        $partners = $DB->get_fieldset_select('dictogloss_randompartners', 'partnerid',
            'dictogloss = ? AND feature = ? AND userid = ?',
            [$this->instance->id, $feature, (int) $userid]);
        $ids = array_map('intval', $partners);
        $ids[] = (int) $userid;
        $ids = array_values(array_unique($ids));

        $this->randompartnercache[$key] = $ids;
        return $ids;
    }

    /**
     * Return the user ids in scope for a user's random assignment (self + partners).
     *
     * Falls back to the user alone when they are not part of the configured pool.
     *
     * @param int $userid The user id.
     * @param string $feature activity::RANDOM_FEATURE_WORDCLOUD or RANDOM_FEATURE_PEERS.
     * @return array
     */
    public function get_random_scope_userids($userid, $feature): array {
        $pool = $this->get_random_pool($feature);
        if (!in_array((int) $userid, $pool, true)) {
            return [(int) $userid];
        }
        return $this->get_random_partner_ids($userid, $feature);
    }

    /**
     * Return the generated teams for a feature, for teacher display.
     *
     * @param string $feature activity::RANDOM_FEATURE_WORDCLOUD or RANDOM_FEATURE_PEERS.
     * @return array List of ['userid', 'fullname', 'partners' => [fullname, ...]].
     */
    public function get_random_teams($feature): array {
        global $DB;
        $this->ensure_random_partners($feature);
        $pool = $this->get_random_pool($feature);

        $rows = $DB->get_records('dictogloss_randompartners',
            ['dictogloss' => $this->instance->id, 'feature' => $feature]);
        $partnerids = [];
        foreach ($rows as $row) {
            // Key by partner id so any accidental duplicates collapse.
            $partnerids[(int) $row->userid][(int) $row->partnerid] = (int) $row->partnerid;
        }

        $allids = $pool;
        foreach ($partnerids as $partners) {
            $allids = array_merge($allids, $partners);
        }
        $names = $this->get_user_fullnames(array_unique($allids));

        $teams = [];
        foreach ($pool as $uid) {
            $uid = (int) $uid;
            $partners = [];
            foreach ($partnerids[$uid] ?? [] as $partnerid) {
                $partners[] = $names[$partnerid] ?? ('#' . $partnerid);
            }
            $teams[] = [
                'userid' => $uid,
                'fullname' => $names[$uid] ?? ('#' . $uid),
                'partners' => $partners,
            ];
        }
        return $teams;
    }

    /**
     * Return a map of userid => full name for the given user ids.
     *
     * @param array $userids The user ids.
     * @return array
     */
    public function get_user_fullnames(array $userids): array {
        global $DB;
        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (!$userids) {
            return [];
        }
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_QM);
        $fields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';
        $names = [];
        foreach ($DB->get_records_select('user', "id $insql", $params, '', $fields) as $user) {
            $names[(int) $user->id] = fullname($user);
        }
        return $names;
    }
}
