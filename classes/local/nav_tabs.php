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
 * Shared definition of the Dictogloss module navigation tabs.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

use mod_dictogloss\activity;

defined('MOODLE_INTERNAL') || die();

/**
 * Single source of truth for the Results / Monitor / Live controls tabs.
 */
class nav_tabs {

    /**
     * Return the module navigation tabs for the current user.
     *
     * @param activity $activity The activity.
     * @param \core\context\module $context The module context.
     * @return array List of ['label' => string, 'url' => \moodle_url, 'key' => string].
     */
    public static function get(activity $activity, \core\context\module $context): array {
        $cm = $activity->get_cm();
        $instance = $activity->get_instance();
        $tabs = [];

        if (has_capability('mod/dictogloss:viewreports', $context)) {
            $tabs[] = [
                'label' => get_string('results', 'mod_dictogloss'),
                'url' => new \moodle_url('/mod/dictogloss/results.php', ['id' => $cm->id]),
                'key' => 'mod_dictogloss_results',
            ];
        }

        if (has_capability('mod/dictogloss:manage', $context)) {
            $tabs[] = [
                'label' => get_string('monitor', 'mod_dictogloss'),
                'url' => new \moodle_url('/mod/dictogloss/report.php', ['id' => $cm->id]),
                'key' => 'mod_dictogloss_report',
            ];

            if (self::has_live_controls($activity, $instance)) {
                $tabs[] = [
                    'label' => get_string('livecontrols', 'mod_dictogloss'),
                    'url' => new \moodle_url('/mod/dictogloss/flow.php', ['id' => $cm->id]),
                    'key' => 'mod_dictogloss_flow',
                ];
            }
        }

        return $tabs;
    }

    /**
     * Whether the activity currently offers any live control.
     *
     * @param activity $activity The activity.
     * @param \stdClass $instance The dictogloss instance.
     * @return bool
     */
    public static function has_live_controls(activity $activity, $instance): bool {
        $hascontrols = ((int) $instance->revealmode === activity::REVEAL_MANUAL)
            || !empty($instance->holdreconstruction)
            || $activity->peers()->is_peers_enabled();
        if (!$hascontrols && empty($instance->allowaudioreplay) && empty($instance->replaystage)) {
            $hascontrols = $activity->has_audio();
        }
        return $hascontrols;
    }
}
