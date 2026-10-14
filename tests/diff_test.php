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
 * Unit tests for the Dictogloss text-comparison helpers.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss;

use mod_dictogloss\diff\word_diff;
use mod_dictogloss\overview\sentence_clusterer;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the Dictogloss text-comparison helpers.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_dictogloss\diff\word_diff
 * @covers     \mod_dictogloss\overview\sentence_clusterer
 */
final class diff_test extends \advanced_testcase {

    /**
     * Tokenisation normalises case, trims apostrophes and drops punctuation.
     */
    public function test_tokenize() {
        $tokens = word_diff::tokenize("Hello, 'World'!");
        $this->assertCount(2, $tokens);
        $this->assertEquals('Hello', $tokens[0]['raw']);
        $this->assertEquals('hello', $tokens[0]['norm']);
        $this->assertEquals('world', $tokens[1]['norm']);
        $this->assertSame([], word_diff::tokenize('   '));
    }

    /**
     * Sentence splitting handles terminal punctuation and newlines.
     */
    public function test_split_sentences() {
        $this->assertEquals(['One.', 'Two!', 'Three?'], word_diff::split_sentences('One. Two! Three?'));
        $this->assertEquals(['First', 'Second'], word_diff::split_sentences("First\nSecond"));
        $this->assertSame([], word_diff::split_sentences(''));
    }

    /**
     * Jaccard similarity behaves at the boundaries.
     */
    public function test_jaccard() {
        $this->assertEquals(1.0, word_diff::jaccard(['a', 'b'], ['a', 'b']));
        $this->assertEquals(0.0, word_diff::jaccard(['a'], ['b']));
        $this->assertEquals(1 / 3, word_diff::jaccard(['a', 'b'], ['b', 'c']));
    }

    /**
     * The comparison score and per-token alignment are correct.
     */
    public function test_compare() {
        $identical = word_diff::compare('the quick brown fox', 'the quick brown fox');
        $this->assertEquals(100.0, $identical['score']);
        $this->assertEquals(4, $identical['matches']);
        foreach ($identical['student_tokens'] as $token) {
            $this->assertEquals('matched', $token['status']);
        }

        $partial = word_diff::compare('the quick brown fox', 'the quick brown');
        $this->assertEquals(3, $partial['matches']);
        $this->assertEquals(1, $partial['missing']);
        $this->assertEquals(85.7, $partial['score']);

        $empty = word_diff::compare('', '');
        $this->assertEquals(100.0, $empty['score']);
    }

    /**
     * Identical sentences are clustered into a single option.
     */
    public function test_clusterer_groups_identical_sentences() {
        $reconstructions = [
            ['author' => 'A', 'authorid' => 1, 'sentences' => ['The cat sat on the mat.']],
            ['author' => 'B', 'authorid' => 2, 'sentences' => ['The cat sat on the mat.']],
        ];
        $result = sentence_clusterer::cluster($reconstructions, 'The cat sat on the mat.', 0.5);

        $this->assertTrue($result['has_anchor']);
        $this->assertCount(1, $result['slots']);
        $this->assertCount(1, $result['slots'][0]['options']);
        $this->assertEquals(2, $result['slots'][0]['options'][0]['count']);
    }

    /**
     * Without an anchor the clusterer falls back to positional alignment.
     */
    public function test_clusterer_positional_fallback() {
        $reconstructions = [
            ['author' => 'A', 'authorid' => 1, 'sentences' => ['One.', 'Two.']],
            ['author' => 'B', 'authorid' => 2, 'sentences' => ['One!', 'Three.']],
        ];
        $result = sentence_clusterer::cluster($reconstructions, '', 0.5);

        $this->assertFalse($result['has_anchor']);
        $this->assertCount(2, $result['slots']);
    }
}
