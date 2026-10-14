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
 * Group-reconstruction pull handling for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the group reconstructions panel for a student.
 */
class peers_handler {

    /**
     * Pull and render the group reconstructions for a user.
     *
     * @param \mod_dictogloss\activity $activity The activity.
     * @param \stdClass $instance The dictogloss instance.
     * @param \core\context\module $context The module context.
     * @param \mod_dictogloss\output\renderer $renderer The plugin renderer.
     * @param int $userid The viewing user id.
     * @return array JSON-ready result.
     */
    public static function handle(\mod_dictogloss\activity $activity, $instance, $context, $renderer,
            $userid): array {
        $status = $activity->peers()->get_peers_pull_status($userid);
        if (!$status['ok']) {
            $messages = [
                'off' => get_string('peersclosed', 'mod_dictogloss'),
                'closed' => get_string('peersclosed', 'mod_dictogloss'),
                'draft' => get_string('peersneeddraft', 'mod_dictogloss'),
                'cooldown' => get_string('peerscooldown', 'mod_dictogloss', $status['wait']),
            ];
            return [
                'ok' => false,
                'reason' => $status['reason'],
                'wait' => (int) $status['wait'],
                'message' => $messages[$status['reason']] ?? get_string('peersclosed', 'mod_dictogloss'),
            ];
        }

        $submission = $activity->submissions()->get_submission($userid);
        $owntext = $submission ? (string) $submission->reconstruction : '';
        $peers = $activity->peers()->get_peer_reconstructions($userid);
        $threshold = $activity->threshold();

        $html = $renderer->render_peers($peers, $owntext, $userid, $threshold, !empty($instance->allowcopy),
            !empty($instance->peersshownames));

        // Record the pull and fire the event only once the panel has been built.
        $activity->peers()->mark_peers_pulled($userid);

        $event = \mod_dictogloss\event\peers_viewed::create([
            'objectid' => $instance->id,
            'context' => $context,
            'userid' => $userid,
        ]);
        $event->trigger();

        return [
            'ok' => true,
            'html' => $html,
            'cooldown' => max(0, (int) $instance->peercooldown),
        ];
    }
}
