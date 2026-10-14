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
 * Sentence alignment and clustering for the reconstruction overview.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\overview;

use mod_dictogloss\diff\word_diff;

defined('MOODLE_INTERNAL') || die();

/**
 * Aligns and clusters reconstructed sentences so similar options can be shown together.
 */
class sentence_clusterer {

    /**
     * Align and cluster reconstructions.
     *
     * @param array $reconstructions List of ['author' => string, 'sentences' => string[]].
     * @param string|null $source The original text, if available for alignment.
     * @param float $threshold Jaccard threshold (0.0 to 1.0) for merging options.
     * @return array ['has_anchor' => bool, 'slots' => array].
     */
    public static function cluster(array $reconstructions, ?string $source, float $threshold): array {
        $threshold = max(0.0, min(1.0, $threshold));

        $anchors = [];
        if ($source !== null && trim($source) !== '') {
            $anchors = word_diff::split_sentences($source);
        }

        // Without an original text, fall back to positional alignment.
        if (empty($anchors)) {
            return self::cluster_by_position($reconstructions, $threshold);
        }

        // Pre-compute the anchor token sets once to avoid tokenising them repeatedly.
        $anchortokens = [];
        $slots = [];
        foreach ($anchors as $index => $anchor) {
            $anchortokens[$index] = word_diff::sentence_tokens($anchor);
            $slots[$index] = ['anchor' => $anchor, 'entries' => []];
        }
        $extra = ['anchor' => null, 'entries' => []];

        foreach ($reconstructions as $reconstruction) {
            $author = $reconstruction['author'] ?? '';
            $authorid = $reconstruction['authorid'] ?? 0;
            foreach ($reconstruction['sentences'] as $sentence) {
                $tokens = word_diff::sentence_tokens($sentence);
                $bestindex = null;
                $bestscore = 0.0;
                foreach ($anchortokens as $index => $anchorset) {
                    $score = word_diff::jaccard($tokens, $anchorset);
                    if ($score > $bestscore) {
                        $bestscore = $score;
                        $bestindex = $index;
                    }
                }
                $entry = ['text' => $sentence, 'author' => $author, 'authorid' => $authorid, 'tokens' => $tokens];
                if ($bestindex !== null && $bestscore >= 0.1) {
                    $slots[$bestindex]['entries'][] = $entry;
                } else {
                    $extra['entries'][] = $entry;
                }
            }
        }

        $output = [];
        foreach ($slots as $slot) {
            $output[] = [
                'anchor' => $slot['anchor'],
                'options' => self::cluster_entries($slot['entries'], $threshold),
            ];
        }
        if (!empty($extra['entries'])) {
            $output[] = [
                'anchor' => null,
                'options' => self::cluster_entries($extra['entries'], $threshold),
            ];
        }

        return ['has_anchor' => true, 'slots' => $output];
    }

    /**
     * Cluster by positional index when no original text is available.
     *
     * @param array $reconstructions List of ['author', 'sentences'].
     * @param float $threshold Jaccard threshold.
     * @return array
     */
    private static function cluster_by_position(array $reconstructions, float $threshold): array {
        $byindex = [];
        foreach ($reconstructions as $reconstruction) {
            $author = $reconstruction['author'] ?? '';
            $authorid = $reconstruction['authorid'] ?? 0;
            foreach (array_values($reconstruction['sentences']) as $index => $sentence) {
                $byindex[$index][] = [
                    'text' => $sentence,
                    'author' => $author,
                    'authorid' => $authorid,
                    'tokens' => word_diff::sentence_tokens($sentence),
                ];
            }
        }
        ksort($byindex);

        $slots = [];
        foreach ($byindex as $entries) {
            $slots[] = [
                'anchor' => null,
                'options' => self::cluster_entries($entries, $threshold),
            ];
        }
        return ['has_anchor' => false, 'slots' => $slots];
    }

    /**
     * Cluster a set of sentence entries into options.
     *
     * @param array $entries List of ['text', 'author', 'tokens'].
     * @param float $threshold Jaccard threshold.
     * @return array
     */
    private static function cluster_entries(array $entries, float $threshold): array {
        $clusters = [];
        foreach ($entries as $entry) {
            $matched = false;
            foreach ($clusters as $key => $cluster) {
                if (word_diff::jaccard($entry['tokens'], $cluster['tokens']) >= $threshold) {
                    $clusters[$key]['count']++;
                    $clusters[$key]['authors'][$entry['authorid']] = $entry['author'];
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $clusters[] = [
                    'text' => $entry['text'],
                    'tokens' => $entry['tokens'],
                    'count' => 1,
                    'authors' => [$entry['authorid'] => $entry['author']],
                ];
            }
        }

        $options = [];
        foreach ($clusters as $cluster) {
            $authors = [];
            foreach ($cluster['authors'] as $authorid => $authorname) {
                $authors[] = ['id' => $authorid, 'name' => $authorname];
            }
            $options[] = [
                'text' => $cluster['text'],
                'count' => $cluster['count'],
                'authors' => $authors,
            ];
        }
        usort($options, fn($a, $b) => $b['count'] <=> $a['count']);

        return $options;
    }
}
