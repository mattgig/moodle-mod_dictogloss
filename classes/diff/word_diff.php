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
 * Word-level comparison utilities for the Dictogloss module.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\diff;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides tokenisation, sentence splitting and word-level alignment.
 */
class word_diff {

    /**
     * Split text into tokens, preserving the raw token and a normalised form.
     *
     * @param string $text The text to tokenise.
     * @return array List of ['raw' => string, 'norm' => string].
     */
    public static function tokenize(string $text): array {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $parts = preg_split("/[^\p{L}\p{N}']+/u", $text, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = [];
        foreach ($parts as $part) {
            $norm = self::normalise_token($part);
            if ($norm === '') {
                continue;
            }
            $tokens[] = ['raw' => $part, 'norm' => $norm];
        }
        return $tokens;
    }

    /**
     * Return the normalised form of a token.
     *
     * @param string $word The token.
     * @return string
     */
    public static function normalise_token(string $word): string {
        $word = trim($word);
        $word = trim($word, "'");
        return \core_text::strtolower($word);
    }

    /**
     * Split text into sentences.
     *
     * @param string $text The text to split.
     * @return array List of trimmed non-empty sentences.
     */
    public static function split_sentences(string $text): array {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/(?<=[.!?])\s+|\n+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $sentences[] = $part;
            }
        }
        return $sentences;
    }

    /**
     * Return the normalised token set of a sentence.
     *
     * @param string $sentence The sentence.
     * @return array Unique normalised tokens.
     */
    public static function sentence_tokens(string $sentence): array {
        $tokens = [];
        foreach (self::tokenize($sentence) as $token) {
            $tokens[$token['norm']] = true;
        }
        return array_keys($tokens);
    }

    /**
     * Return a set of normalised tokens keyed by token value.
     *
     * @param string $text The text.
     * @return array Set of normalised tokens (token => true).
     */
    public static function token_set(string $text): array {
        $set = [];
        foreach (self::tokenize($text) as $token) {
            $set[$token['norm']] = true;
        }
        return $set;
    }

    /**
     * Split text into word/punctuation segments, tagging words against a reference set.
     *
     * @param string $text The text to split.
     * @param array $referenceset Reference tokens (normalised token => true).
     * @param string $presentstatus Status for words present in the reference set.
     * @param string $absentstatus Status for words absent from the reference set.
     * @return array List of ['text' => string, 'status' => string].
     */
    public static function segments(string $text, array $referenceset, string $presentstatus,
            string $absentstatus): array {
        $parts = preg_split('/([\p{L}\p{N}\']+)/u', $text, -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $words = [];
        foreach ($parts as $segment) {
            if (preg_match('/^[\p{L}\p{N}\']+$/u', $segment)) {
                $norm = self::normalise_token($segment);
                $status = isset($referenceset[$norm]) ? $presentstatus : $absentstatus;
            } else {
                $status = 'plain';
            }
            $words[] = ['text' => $segment, 'status' => $status];
        }
        return $words;
    }

    /**
     * Compute the Jaccard similarity between two sets of normalised tokens.
     *
     * @param array $a First token set.
     * @param array $b Second token set.
     * @return float Value between 0.0 and 1.0.
     */
    public static function jaccard(array $a, array $b): float {
        $a = array_unique($a);
        $b = array_unique($b);
        if (!$a && !$b) {
            return 1.0;
        }
        if (!$a || !$b) {
            return 0.0;
        }
        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));
        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Compare a reconstruction with the source text using a word-level LCS.
     *
     * The score uses the Dice coefficient over the longest common subsequence:
     * score = 2 * matches / (source_tokens + student_tokens) * 100.
     *
     * @param string $source The original text.
     * @param string $student The student's reconstruction.
     * @return array Comparison data.
     */
    public static function compare(string $source, string $student): array {
        $sourcetokens = self::tokenize($source);
        $studenttokens = self::tokenize($student);

        // Bound the LCS table size to keep memory usage predictable on very long texts.
        $maxtokens = 600;
        if (count($sourcetokens) > $maxtokens) {
            $sourcetokens = array_slice($sourcetokens, 0, $maxtokens);
        }
        if (count($studenttokens) > $maxtokens) {
            $studenttokens = array_slice($studenttokens, 0, $maxtokens);
        }

        $n = count($sourcetokens);
        $m = count($studenttokens);

        $sourcewords = array_map(fn($t) => $t['norm'], $sourcetokens);
        $studentwords = array_map(fn($t) => $t['norm'], $studenttokens);

        // Build the LCS length table.
        $lcs = [];
        for ($i = 0; $i <= $n; $i++) {
            $lcs[$i] = array_fill(0, $m + 1, 0);
        }
        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                if ($sourcewords[$i - 1] === $studentwords[$j - 1]) {
                    $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
                }
            }
        }
        $matches = $n && $m ? $lcs[$n][$m] : 0;

        // Backtrack to mark matched/missing/extra tokens for display.
        $sourcestatus = array_fill(0, max($n, 1), 'missing');
        $studentstatus = array_fill(0, max($m, 1), 'extra');
        $i = $n;
        $j = $m;
        while ($i > 0 && $j > 0) {
            if ($sourcewords[$i - 1] === $studentwords[$j - 1]) {
                $sourcestatus[$i - 1] = 'matched';
                $studentstatus[$j - 1] = 'matched';
                $i--;
                $j--;
            } else if ($lcs[$i - 1][$j] >= $lcs[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        $total = $n + $m;
        $score = $total > 0 ? (2 * $matches / $total) * 100 : 100.0;

        return [
            'score' => round($score, 1),
            'matches' => $matches,
            'missing' => $n - $matches,
            'extra' => $m - $matches,
            'source_total' => $n,
            'student_total' => $m,
            'source_tokens' => self::build_tokens($sourcetokens, $sourcestatus),
            'student_tokens' => self::build_tokens($studenttokens, $studentstatus),
        ];
    }

    /**
     * Build a token list decorated with alignment statuses.
     *
     * @param array $tokens The token list.
     * @param array $statuses The statuses indexed by position.
     * @return array
     */
    private static function build_tokens(array $tokens, array $statuses): array {
        $out = [];
        foreach ($tokens as $index => $token) {
            $out[] = [
                'raw' => $token['raw'],
                'status' => $statuses[$index] ?? 'missing',
            ];
        }
        return $out;
    }
}
