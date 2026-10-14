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
 * Data generator for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Data generator class for mod_dictogloss.
 */
class mod_dictogloss_generator extends testing_module_generator {

    /**
     * Create a dictogloss instance with sensible defaults.
     *
     * @param array|stdClass|null $record The instance data.
     * @param array|null $options The options.
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $defaults = [
            'name' => 'Test dictogloss',
            'intro' => '',
            'introformat' => FORMAT_PLAIN,
            'instructions' => '',
            'instructionsformat' => FORMAT_PLAIN,
            'source' => 'The quick brown fox jumps over the lazy dog.',
            'sourceformat' => FORMAT_PLAIN,
            'revealmode' => 1,
            'revealed' => 0,
            'wordcloudmode' => 'group',
            'similaritythreshold' => 70,
            'shownotes' => 1,
            'allowfollowup' => 0,
            'allowaudioreplay' => 0,
            'replayopen' => 0,
            'holdreconstruction' => 0,
            'reconstructionopen' => 0,
            'replaystage' => 0,
            'peerscope' => 'off',
            'peersource' => 'both',
            'peercooldown' => 60,
            'peermindraft' => 20,
            'peersshownames' => 0,
            'allowcopy' => 1,
            'peeropen' => 0,
            'randomshared' => 0,
            'randomsharedpool' => 'class',
            'randomsharedgroupingid' => 0,
            'randomsharedgroupid' => 0,
            'wordcloudrandomsize' => 3,
            'wordcloudrandompool' => 'class',
            'wordcloudrandomgroupingid' => 0,
            'wordcloudrandomgroupid' => 0,
            'peersrandomsize' => 2,
            'peersrandompool' => 'class',
            'peersrandomgroupingid' => 0,
            'peersrandomgroupid' => 0,
            'grade' => 0,
            'autograding' => 1,
            'gradeshow' => 0,
            'completionsubmit' => 0,
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($record->$key)) {
                $record->$key = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }
}
