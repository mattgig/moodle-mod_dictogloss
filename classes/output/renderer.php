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
 * Output renderer for the Dictogloss module.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\output;

use mod_dictogloss\activity;
use mod_dictogloss\diff\word_diff;
use mod_dictogloss\local\status;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders every Dictogloss UI fragment from templates.
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the stage navigation.
     *
     * @param array $stages List of stage descriptors.
     * @return string
     */
    public function render_stage_nav(array $stages): string {
        return $this->render_from_template('mod_dictogloss/stage_nav', [
            'stages' => $stages,
            'currentlabel' => get_string('stagecurrent', 'mod_dictogloss'),
            'donelabel' => get_string('stagedone', 'mod_dictogloss'),
        ]);
    }

    /**
     * Render the word cloud section (heading, refresh button and cloud).
     *
     * @param activity $activity The activity.
     * @param int|null $userid The viewing user id.
     * @param bool $canrefresh Whether to show the refresh button.
     * @return string
     */
    public function render_wordcloud_section(activity $activity, $userid = null, $canrefresh = false): string {
        $entries = $activity->wordcloud()->get_wordcloud_entries($userid);

        $max = 0;
        foreach ($entries as $entry) {
            $max = max($max, (int) $entry['count']);
        }

        $cloudentries = [];
        foreach ($entries as $entry) {
            $count = (int) $entry['count'];
            // Weight bucket (1..6) drives the font size class defined in styles.css.
            $weight = ($max <= 1) ? 3 : max(1, min(6, (int) ceil($count / $max * 6)));
            $cloudentries[] = [
                'word' => $entry['word'],
                'count' => $count,
                'weight' => $weight,
                'own' => !empty($entry['own']),
            ];
        }

        return $this->render_from_template('mod_dictogloss/wordcloud_section', [
            'id' => 'mod-dictogloss-wordcloud',
            'heading' => get_string('wordcloudheading', 'mod_dictogloss'),
            'refreshlabel' => get_string('refreshwordcloud', 'mod_dictogloss'),
            'buttonid' => 'mod-dictogloss-refresh-cloud',
            'canrefresh' => $canrefresh,
            'hasentries' => !empty($cloudentries),
            'entries' => $cloudentries,
            'empty' => get_string('wordcloudempty', 'mod_dictogloss'),
        ]);
    }

    /**
     * Render the student comparison view.
     *
     * @param array $comparison Comparison data from word_diff.
     * @return string
     */
    public function render_comparison(array $comparison): string {
        $labels = [
            'matched' => get_string('legendmatched', 'mod_dictogloss'),
            'missing' => get_string('legendmissing', 'mod_dictogloss'),
            'extra' => get_string('legendextra', 'mod_dictogloss'),
        ];
        $decorate = static function (array $tokens) use ($labels): array {
            foreach ($tokens as $i => $token) {
                $tokens[$i]['label'] = $labels[$token['status']] ?? '';
            }
            return $tokens;
        };
        return $this->render_from_template('mod_dictogloss/comparison', [
            'source_tokens' => $decorate($comparison['source_tokens']),
            'student_tokens' => $decorate($comparison['student_tokens']),
            'legendmatched' => get_string('legendmatched', 'mod_dictogloss'),
            'legendmissing' => get_string('legendmissing', 'mod_dictogloss'),
            'legendextra' => get_string('legendextra', 'mod_dictogloss'),
        ]);
    }

    /**
     * Render text with each word tagged as matching the reference or not.
     *
     * @param string $text The text to render.
     * @param string|null $anchor The reference text (or null).
     * @return string
     */
    public function render_coloured_text($text, $anchor = null): string {
        $referenceset = ($anchor === null) ? [] : word_diff::token_set((string) $anchor);
        $segments = ($anchor === null)
            ? word_diff::segments((string) $text, [], 'plain', 'plain')
            : word_diff::segments((string) $text, $referenceset, 'matched', 'extra');

        $html = '';
        foreach ($segments as $word) {
            $html .= \html_writer::tag('span', s($word['text']),
                ['class' => 'dictogloss-word dictogloss-word-' . $word['status']]);
        }
        return $html;
    }

    /**
     * Render the teacher monitor table.
     *
     * @param array $rows Monitor rows.
     * @param int $cmid The course module id.
     * @param int $groupfilter Selected group id.
     * @return string
     */
    public function render_monitor(array $rows, $cmid, $groupfilter = 0): string {
        $monitorrows = [];
        foreach ($rows as $row) {
            $monitorrows[] = [
                'userid' => $row['userid'],
                'fullname' => $row['fullname'],
                'statuslabel' => status::name((int) $row['status']),
                'timemodified' => userdate($row['timemodified']),
                'keywords' => (string) $row['keywords'],
                'reconstruction' => (string) $row['reconstruction'],
                'followup' => (string) $row['followup'],
                'grade' => $row['grade'] === null ? get_string('nograde', 'mod_dictogloss') : format_float($row['grade'], 2),
                'autograde' => $row['autograde'] === null ? '' : format_float($row['autograde'], 1) . '%',
                'overridden' => !empty($row['gradeoverridden']),
                'url' => (new \moodle_url('/mod/dictogloss/view.php',
                    ['id' => $cmid, 'userid' => $row['userid']]))->out(false),
            ];
        }
        return $this->render_from_template('mod_dictogloss/monitor', [
            'rows' => $monitorrows,
            'hasrows' => !empty($monitorrows),
            'cmid' => $cmid,
            'groupfilter' => $groupfilter,
            'bulkaction' => (new \moodle_url('/mod/dictogloss/report.php'))->out_omit_querystring(),
            'sesskey' => sesskey(),
        ]);
    }

    /**
     * Render the sentence-variant overview.
     *
     * @param array $overview The clustered overview.
     * @return string
     */
    public function render_variants(array $overview): string {
        $maxauthors = 8;

        $slots = [];
        foreach ($overview['slots'] as $slot) {
            $total = 0;
            foreach ($slot['options'] as $option) {
                $total += (int) $option['count'];
            }

            $hasanchor = ($slot['anchor'] !== null);
            $anchortokens = $hasanchor ? word_diff::sentence_tokens((string) $slot['anchor']) : [];
            $anchorset = $hasanchor ? word_diff::token_set((string) $slot['anchor']) : [];

            $options = [];
            $optionindex = 0;
            foreach ($slot['options'] as $option) {
                $count = (int) $option['count'];

                // Overall similarity to the original (used for the screen-reader marker).
                // Uses the cheaper Jaccard overlap rather than a full LCS compare per option.
                $score = 0;
                if ($hasanchor) {
                    $score = (int) round(word_diff::jaccard($anchortokens,
                        word_diff::sentence_tokens((string) $option['text'])) * 100);
                }

                $authors = [];
                foreach ($option['authors'] as $author) {
                    $authors[] = [
                        'name' => $author['name'],
                        'group' => implode(', ', $author['groups'] ?? []),
                    ];
                }
                $shown = array_slice($authors, 0, $maxauthors);

                $options[] = [
                    'optionindex' => $optionindex,
                    'words' => ($hasanchor)
                        ? word_diff::segments((string) $option['text'], $anchorset,
                            'matched', 'extra')
                        : word_diff::segments((string) $option['text'], [], 'plain', 'plain'),
                    'count' => $count,
                    'hascolor' => $hasanchor,
                    'score' => $score,
                    'authors' => $shown,
                    'hasauthors' => !empty($authors),
                    'moreauthors' => count($authors) - count($shown),
                ];
                $optionindex++;
            }

            $slots[] = [
                'anchor' => $slot['anchor'],
                'hasanchor' => $hasanchor,
                'optioncount' => count($slot['options']),
                'totalcount' => $total,
                'options' => $options,
            ];
        }

        return $this->render_from_template('mod_dictogloss/variants_overview', [
            'slots' => $slots,
            'hasslots' => !empty($slots),
            'anchorlabel' => get_string('teachersversion', 'mod_dictogloss'),
            'additionallabel' => get_string('variantsadditional', 'mod_dictogloss'),
            'distinctlabel' => get_string('distinctoptions', 'mod_dictogloss'),
            'responseslabel' => get_string('responsescount', 'mod_dictogloss'),
            'focuslabel' => get_string('focus', 'mod_dictogloss'),
        ]);
    }

    /**
     * Render the audio player(s) for an activity.
     *
     * @param array $files List of stored audio files.
     * @param \context $context The module context.
     * @param string $label The introductory label.
     * @param string $intro Optional muted intro text.
     * @param string $classes Extra wrapper classes.
     * @return string
     */
    public function render_audio(array $files, $context, $label, $intro = '', $classes = 'mb-4'): string {
        $urls = [];
        foreach ($files as $file) {
            $urls[] = \moodle_url::make_pluginfile_url($context->id, 'mod_dictogloss', 'audio',
                0, '/', $file->get_filename())->out(false);
        }
        return $this->render_from_template('mod_dictogloss/audio_player', [
            'label' => $label,
            'intro' => $intro,
            'hasintro' => ($intro !== ''),
            'classes' => $classes,
            'urls' => $urls,
        ]);
    }

    /**
     * Render the student-facing "group reconstructions" panel (clusters + full texts).
     *
     * @param array $peers Peers' reconstructions (from peer_service::get_peer_reconstructions()).
     * @param string $owntext The viewer's own reconstruction, used as the comparison reference.
     * @param int $ownuserid The viewer's user id.
     * @param float $threshold Clustering threshold (0..1).
     * @param bool $allowcopy Whether copying is allowed.
     * @param bool $shownames Whether to show other students' names.
     * @return string
     */
    public function render_peers(array $peers, $owntext, $ownuserid, $threshold, $allowcopy,
            $shownames = false): string {
        $ownset = word_diff::token_set((string) $owntext);

        // Accessible labels for each word status (colour/weight alone is not accessible).
        $wordlabels = [
            'have' => get_string('peerhave', 'mod_dictogloss'),
            'new' => get_string('peernew', 'mod_dictogloss'),
        ];
        $decoratewords = static function (array $words) use ($wordlabels): array {
            foreach ($words as $index => $word) {
                $words[$index]['label'] = $wordlabels[$word['status']] ?? '';
            }
            return $words;
        };

        // Build display labels: own, real name, or an anonymous letter.
        $labels = [];
        $anonymousindex = 0;
        foreach ($peers as $peer) {
            if ($peer['isown']) {
                $labels[$peer['userid']] = get_string('peerown', 'mod_dictogloss');
            } else if ($shownames) {
                $labels[$peer['userid']] = $peer['fullname'];
            } else {
                $anonymousindex++;
                $labels[$peer['userid']] = get_string('peeranonymous', 'mod_dictogloss') . ' '
                    . self::index_to_letters($anonymousindex);
            }
        }

        $reconstructions = [];
        foreach ($peers as $peer) {
            $reconstructions[] = [
                'author' => $labels[$peer['userid']],
                'authorid' => $peer['userid'],
                'sentences' => word_diff::split_sentences($peer['text']),
            ];
        }
        $cluster = \mod_dictogloss\overview\sentence_clusterer::cluster($reconstructions, (string) $owntext, $threshold);

        $slots = [];
        $slotindex = 0;
        foreach ($cluster['slots'] as $slot) {
            $options = [];
            foreach ($slot['options'] as $option) {
                $isown = false;
                foreach ($option['authors'] as $author) {
                    if ((int) $author['id'] === (int) $ownuserid) {
                        $isown = true;
                        break;
                    }
                }
                $options[] = [
                    'text' => $option['text'],
                    'words' => $decoratewords(word_diff::segments($option['text'], $ownset, 'have', 'new')),
                    'count' => $option['count'],
                    'isown' => $isown,
                ];
            }
            $slots[] = [
                'index' => $slotindex,
                'anchor' => $slot['anchor'],
                'hasanchor' => $slot['anchor'] !== null,
                'optioncount' => count($slot['options']),
                'options' => $options,
            ];
            $slotindex++;
        }

        // Full texts.
        $fulltexts = [];
        $fulltextindex = 0;
        foreach ($peers as $peer) {
            $sentences = [];
            foreach (word_diff::split_sentences($peer['text']) as $sentence) {
                $sentences[] = [
                    'text' => $sentence,
                    'words' => $decoratewords(word_diff::segments($sentence, $ownset, 'have', 'new')),
                ];
            }
            $fulltexts[] = [
                'index' => $fulltextindex,
                'label' => $labels[$peer['userid']],
                'isown' => $peer['isown'],
                'showadd' => !$peer['isown'],
                'sentences' => $sentences,
            ];
            $fulltextindex++;
        }

        return $this->render_from_template('mod_dictogloss/peers', [
            'slots' => $slots,
            'hasslots' => !empty($slots),
            'peers' => $fulltexts,
            'haspeers' => !empty($fulltexts),
            'allowcopy' => $allowcopy,
            'clusterslabel' => get_string('peerclusters', 'mod_dictogloss'),
            'fulltextslabel' => get_string('peerfulltexts', 'mod_dictogloss'),
            'addlabel' => get_string('peeradd', 'mod_dictogloss'),
            'ownlabel' => get_string('peerown', 'mod_dictogloss'),
            'additionallabel' => get_string('variantsadditional', 'mod_dictogloss'),
            'empty' => get_string('peersnone', 'mod_dictogloss'),
            'legendhavelabel' => get_string('peerhave', 'mod_dictogloss'),
            'legendnewlabel' => get_string('peernew', 'mod_dictogloss'),
        ]);
    }

    /**
     * Render the teacher per-student navigation (previous/next + picker).
     *
     * @param array $contributors Contributor rows (userid, fullname).
     * @param int $viewuserid The currently shown user id.
     * @param int $cmid The course module id.
     * @return string
     */
    public function render_contribution_nav(array $contributors, $viewuserid, $cmid): string {
        if (!$contributors) {
            return '';
        }

        $ids = array_map(fn($row) => (int) $row['userid'], $contributors);
        $index = array_search((int) $viewuserid, $ids, true);

        $prevurl = null;
        $nexturl = null;
        if ($index !== false) {
            if ($index > 0) {
                $prevurl = (new \moodle_url('/mod/dictogloss/view.php', ['id' => $cmid, 'userid' => $ids[$index - 1]]))->out(false);
            }
            if ($index < count($ids) - 1) {
                $nexturl = (new \moodle_url('/mod/dictogloss/view.php', ['id' => $cmid, 'userid' => $ids[$index + 1]]))->out(false);
            }
        }

        $options = [];
        foreach ($contributors as $row) {
            $options[] = [
                'value' => (int) $row['userid'],
                'label' => $row['fullname'],
                'selected' => ((int) $row['userid'] === (int) $viewuserid),
            ];
        }

        return $this->render_from_template('mod_dictogloss/contribution_nav', [
            'cmid' => $cmid,
            'prevurl' => $prevurl,
            'nexturl' => $nexturl,
            'haspicker' => true,
            'options' => $options,
            'viewuserid' => (int) $viewuserid,
            'prevlabel' => get_string('previousstudent', 'mod_dictogloss'),
            'nextlabel' => get_string('nextstudent', 'mod_dictogloss'),
            'selectlabel' => get_string('selectstudent', 'mod_dictogloss'),
            'applylabel' => get_string('filterapply', 'mod_dictogloss'),
            'formaction' => (new \moodle_url('/mod/dictogloss/view.php'))->out_omit_querystring(),
        ]);
    }

    /**
     * Render the teacher per-student controls (delete / reopen).
     *
     * @param int $cmid The course module id.
     * @param int $userid The target user id.
     * @param bool $finalised Whether the contribution is handed in.
     * @return string
     */
    public function render_teacher_controls($cmid, $userid, $finalised): string {
        return $this->render_from_template('mod_dictogloss/teacher_controls', [
            'cmid' => $cmid,
            'userid' => $userid,
            'finalised' => $finalised,
            'sesskey' => sesskey(),
            'deletelabel' => get_string('deletesubmission', 'mod_dictogloss'),
            'deleteconfirm' => get_string('deletesubmission_confirm', 'mod_dictogloss'),
            'reopenlabel' => get_string('reopen', 'mod_dictogloss'),
            'reopenhelp' => get_string('reopen_help', 'mod_dictogloss'),
            'reportaction' => (new \moodle_url('/mod/dictogloss/report.php'))->out_omit_querystring(),
            'viewaction' => (new \moodle_url('/mod/dictogloss/view.php'))->out_omit_querystring(),
        ]);
    }

    /**
     * Render the results-summary tiles and group filter.
     *
     * @param array $stats List of ['label', 'value'] tiles.
     * @param array $filteroptions Group/team filter options (value => label).
     * @param string $filtername Filter param name ('group' or 'teamuser').
     * @param mixed $filtervalue Selected filter value.
     * @param string $filterlabel Filter field label.
     * @param int $cmid The course module id.
     * @param string $formaction The form action URL.
     * @return string
     */
    public function render_results_summary(array $stats, array $filteroptions, $filtername, $filtervalue,
            $filterlabel, $cmid, $formaction): string {
        $options = [];
        foreach ($filteroptions as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label, 'selected' => ((string) $value === (string) $filtervalue)];
        }
        return $this->render_from_template('mod_dictogloss/results_summary', [
            'stats' => $stats,
            'options' => $options,
            'filtername' => $filtername,
            'filterlabel' => $filterlabel,
            'applylabel' => get_string('filterapply', 'mod_dictogloss'),
            'cmid' => $cmid,
            'formaction' => $formaction,
        ]);
    }

    /**
     * Render the live controls page body (switches + random teams).
     *
     * @param array $data Template data (switches, randomfeatures, teams, shared, labels).
     * @return string
     */
    public function render_live_controls(array $data): string {
        return $this->render_from_template('mod_dictogloss/live_controls', $data);
    }

    /**
     * Convert 1-based index to spreadsheet-style letters (1 => A, 27 => AA).
     *
     * @param int $index The index.
     * @return string
     */
    protected static function index_to_letters($index): string {
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = intdiv($index, 26);
        }
        return $letters;
    }
}
