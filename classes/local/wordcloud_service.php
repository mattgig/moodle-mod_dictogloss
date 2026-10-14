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
 * Word cloud aggregation and moderation.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

use mod_dictogloss\activity;

defined('MOODLE_INTERNAL') || die();

/**
 * Aggregates keyword contributions into a word cloud and moderates entries.
 */
class wordcloud_service {

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
     * Return the aggregated word cloud entries for a user.
     *
     * @param int|null $userid The user id.
     * @return array List of ['word' => string, 'count' => int, 'own' => bool].
     */
    public function get_wordcloud_entries($userid = null): array {
        global $DB, $USER;
        if ($userid === null) {
            $userid = $USER->id;
        }

        $submission = $this->activity->submissions()->get_submission($userid);
        $ownset = [];
        if ($submission) {
            foreach ($DB->get_fieldset_select('dictogloss_keywords', 'word', 'submissionid = ?', [$submission->id]) as $word) {
                $ownset[$word] = true;
            }
        }

        $params = ['dictogloss' => $this->instance->id];
        $mode = $this->instance->wordcloudmode;

        if ($mode === 'individual') {
            $sql = "SELECT k.word, COUNT(*) AS c
                      FROM {dictogloss_keywords} k
                     WHERE k.dictogloss = :dictogloss AND k.submissionid = :sid
                  GROUP BY k.word";
            $params['sid'] = $submission ? $submission->id : 0;
        } else if ($mode === 'random') {
            $userids = $this->activity->random()->get_random_scope_userids($userid, activity::RANDOM_FEATURE_WORDCLOUD);
            if ($userids) {
                // The cloud aggregates the student's own keywords plus those of their random partners.
                list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'rnd');
                $sql = "SELECT k.word, COUNT(DISTINCT k.id) AS c
                          FROM {dictogloss_keywords} k
                          JOIN {dictogloss_submissions} s ON s.id = k.submissionid
                         WHERE k.dictogloss = :dictogloss AND s.userid $insql
                      GROUP BY k.word";
                $params = array_merge($params, $inparams);
            } else {
                $sql = "SELECT k.word, COUNT(*) AS c
                          FROM {dictogloss_keywords} k
                         WHERE k.dictogloss = :dictogloss AND k.submissionid = :sid
                      GROUP BY k.word";
                $params['sid'] = $submission ? $submission->id : 0;
            }
        } else {
            $groupids = $this->activity->scope()->get_scope_groupids($userid, $mode);
            if ($groupids) {
                // Match by actual group membership so a contributor who is in several
                // groups is visible in each of them.
                list($insql, $inparams) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'grp');
                $sql = "SELECT k.word, COUNT(DISTINCT k.id) AS c
                          FROM {dictogloss_keywords} k
                          JOIN {dictogloss_submissions} s ON s.id = k.submissionid
                          JOIN {groups_members} gm ON gm.userid = s.userid
                         WHERE k.dictogloss = :dictogloss AND gm.groupid $insql
                      GROUP BY k.word";
                $params = array_merge($params, $inparams);
            } else {
                $sql = "SELECT k.word, COUNT(*) AS c
                          FROM {dictogloss_keywords} k
                         WHERE k.dictogloss = :dictogloss AND k.submissionid = :sid
                      GROUP BY k.word";
                $params['sid'] = $submission ? $submission->id : 0;
            }
        }

        $rows = $DB->get_records_sql($sql, $params);
        $overrides = $this->get_word_overrides();

        $aggregated = [];
        foreach ($rows as $row) {
            $word = $row->word;
            $own = isset($ownset[$word]);
            if (isset($overrides[$word])) {
                $override = $overrides[$word];
                if ($override->action === 'hide') {
                    continue;
                }
                if ($override->action === 'rename' && $override->replacement !== null && $override->replacement !== '') {
                    $word = \core_text::strtolower(trim($override->replacement));
                }
            }
            if (!isset($aggregated[$word])) {
                $aggregated[$word] = ['word' => $word, 'count' => 0, 'own' => false];
            }
            $aggregated[$word]['count'] += (int) $row->c;
            $aggregated[$word]['own'] = $aggregated[$word]['own'] || $own;
        }

        $entries = array_values($aggregated);
        usort($entries, fn($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['word'], $b['word']));

        return $entries;
    }

    /**
     * Persist teacher hide/rename decisions for word cloud entries.
     *
     * @param array $words The normalised words (by row).
     * @param array $hides Hide flags by row index.
     * @param array $renames Replacement words by row index.
     * @param int $moderatedby The moderating user id.
     * @return void
     */
    public function save_moderation(array $words, array $hides, array $renames, $moderatedby): void {
        global $DB;
        foreach ($words as $index => $word) {
            $word = \core_text::strtolower(trim((string) $word));
            if ($word === '') {
                continue;
            }
            $DB->delete_records('dictogloss_wordoverrides', ['dictogloss' => $this->instance->id, 'word' => $word]);

            $hide = !empty($hides[$index]);
            $rename = trim((string) ($renames[$index] ?? ''));

            if ($hide || $rename !== '') {
                $override = new \stdClass();
                $override->dictogloss = $this->instance->id;
                $override->word = $word;
                $override->action = $hide ? 'hide' : 'rename';
                $override->replacement = $hide ? null : $rename;
                $override->moderatedby = $moderatedby;
                $override->timemodified = time();
                $DB->insert_record('dictogloss_wordoverrides', $override);
            }
        }
    }

    /**
     * Return word overrides for this activity keyed by word.
     *
     * @return array
     */
    public function get_word_overrides(): array {
        global $DB;
        $overrides = [];
        $records = $DB->get_records('dictogloss_wordoverrides', ['dictogloss' => $this->instance->id]);
        foreach ($records as $record) {
            $overrides[$record->word] = $record;
        }
        return $overrides;
    }

    /**
     * Permanently delete the given keyword tokens (and any overrides) from this activity.
     *
     * @param array $words List of normalised words.
     * @param int|null $groupid Optional group scope.
     * @param array|null $userids Optional explicit user scope (takes precedence).
     * @return int The number of words processed.
     */
    public function delete_keywords(array $words, $groupid = null, $userids = null): int {
        global $DB;

        // Resolve the set of submission ids the deletion applies to (null = whole activity).
        $submissionids = null;
        if ($userids !== null) {
            if (!$userids) {
                return 0;
            }
            list($insql, $inparams) = $DB->get_in_or_equal(array_map('intval', $userids), SQL_PARAMS_QM);
            $submissionids = $DB->get_fieldset_sql(
                "SELECT s.id
                   FROM {dictogloss_submissions} s
                  WHERE s.dictogloss = ? AND s.userid $insql",
                array_merge([$this->instance->id], $inparams));
        } else if ($groupid) {
            $submissionids = $DB->get_fieldset_sql(
                "SELECT s.id
                   FROM {dictogloss_submissions} s
                   JOIN {groups_members} gm ON gm.userid = s.userid
                  WHERE s.dictogloss = :dictogloss AND gm.groupid = :groupid",
                ['dictogloss' => $this->instance->id, 'groupid' => $groupid]);
        }

        $count = 0;
        foreach ($words as $word) {
            $word = \core_text::strtolower(trim((string) $word));
            if ($word === '') {
                continue;
            }

            if ($submissionids !== null) {
                if ($submissionids) {
                    list($insql, $inparams) = $DB->get_in_or_equal($submissionids, SQL_PARAMS_QM);
                    $params = array_merge([$this->instance->id, $word], $inparams);
                    $DB->delete_records_select('dictogloss_keywords',
                        "dictogloss = ? AND word = ? AND submissionid $insql", $params);
                }
            } else {
                $DB->delete_records('dictogloss_keywords',
                    ['dictogloss' => $this->instance->id, 'word' => $word]);
                $DB->delete_records('dictogloss_wordoverrides',
                    ['dictogloss' => $this->instance->id, 'word' => $word]);
            }
            $count++;
        }

        return $count;
    }

    /**
     * Aggregate keyword counts for the teacher overview.
     *
     * @param int|null $groupid Optional group filter.
     * @param array|null $userids Optional explicit list of user ids (takes precedence).
     * @return array
     */
    public function get_keyword_aggregate($groupid = null, $userids = null): array {
        global $DB;
        $params = ['dictogloss' => $this->instance->id];
        if ($userids !== null) {
            if (!$userids) {
                return [];
            }
            list($insql, $inparams) = $DB->get_in_or_equal(array_map('intval', $userids), SQL_PARAMS_NAMED, 'tke');
            $sql = "SELECT k.word, COUNT(DISTINCT k.id) AS c
                      FROM {dictogloss_keywords} k
                      JOIN {dictogloss_submissions} s ON s.id = k.submissionid
                     WHERE k.dictogloss = :dictogloss AND s.userid $insql
                  GROUP BY k.word
                  ORDER BY c DESC, k.word ASC";
            $params = array_merge($params, $inparams);
        } else if ($groupid) {
            $sql = "SELECT k.word, COUNT(DISTINCT k.id) AS c
                      FROM {dictogloss_keywords} k
                      JOIN {dictogloss_submissions} s ON s.id = k.submissionid
                      JOIN {groups_members} gm ON gm.userid = s.userid
                     WHERE k.dictogloss = :dictogloss AND gm.groupid = :groupid
                  GROUP BY k.word
                  ORDER BY c DESC, k.word ASC";
            $params['groupid'] = $groupid;
        } else {
            $sql = "SELECT k.word, COUNT(*) AS c
                      FROM {dictogloss_keywords} k
                     WHERE k.dictogloss = :dictogloss
                  GROUP BY k.word
                  ORDER BY c DESC, k.word ASC";
        }
        return $DB->get_records_sql($sql, $params);
    }
}
