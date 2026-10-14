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
 * POST action handling for the Dictogloss live controls page.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles the live-control toggles and random re-shuffle submitted to flow.php.
 */
class live_controls {

    /**
     * Process any submitted live-control or re-shuffle action.
     *
     * Redirects and exits when an action has been handled.
     *
     * @param \mod_dictogloss\activity $activity The activity.
     * @param \stdClass $instance The dictogloss instance.
     * @param array $randomfeatures Enabled random features ('wordcloud'/'peers').
     * @param array $showflags Which toggles are applicable: reveal/replay/reconstruction/peers.
     * @param \moodle_url $returnurl The URL to redirect back to.
     * @return void
     */
    public static function process(\mod_dictogloss\activity $activity, $instance, array $randomfeatures,
            array $showflags, \moodle_url $returnurl): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Save the live-control switches.
        if (optional_param('savelivecontrols', '', PARAM_RAW)) {
            require_sesskey();
            if (!empty($showflags['reveal'])) {
                $activity->state()->set_revealed((bool) optional_param('revealed', 0, PARAM_INT));
            }
            if (!empty($showflags['replay'])) {
                $activity->state()->set_replay_open((bool) optional_param('replayopen', 0, PARAM_INT));
            }
            if (!empty($showflags['reconstruction'])) {
                $activity->state()->set_reconstruction_open((bool) optional_param('reconstructionopen', 0, PARAM_INT));
            }
            if (!empty($showflags['peers'])) {
                $activity->peers()->set_peers_open((bool) optional_param('peeropen', 0, PARAM_INT));
            }
            redirect($returnurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
        }

        // Re-shuffle the random teams.
        if (optional_param('reshufflerandom', '', PARAM_RAW)) {
            require_sesskey();
            if (!empty($instance->randomshared)) {
                foreach ($randomfeatures as $feature) {
                    $activity->random()->reshuffle_random_partners($feature);
                }
            } else {
                $feature = optional_param('feature', '', PARAM_ALPHA);
                if (in_array($feature, $randomfeatures, true)) {
                    $activity->random()->reshuffle_random_partners($feature);
                }
            }
            redirect($returnurl, get_string('randomreshuffled', 'mod_dictogloss'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}
