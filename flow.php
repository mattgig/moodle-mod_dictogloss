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
 * Teacher live controls for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/dictogloss/lib.php');

use mod_dictogloss\activity;

$id = required_param('id', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'dictogloss');
$instance = $DB->get_record('dictogloss', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = \core\context\module::instance($cm->id);
require_capability('mod/dictogloss:manage', $context);

$PAGE->set_url('/mod/dictogloss/flow.php', ['id' => $cm->id]);
$PAGE->set_title($course->shortname . ': ' . format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($instance);
$PAGE->requires->js_call_amd('mod_dictogloss/confirm', 'init');

$activity = new activity($cm, $course, $instance);

// Which controls are applicable.
$showreveal = ((int) $instance->revealmode === activity::REVEAL_MANUAL);
$showreplay = empty($instance->allowaudioreplay) && empty($instance->replaystage) && $activity->has_audio();
$showreconstruction = !empty($instance->holdreconstruction);
$showpeers = $activity->peers()->is_peers_enabled();

$randomfeatures = [];
if ($activity->is_wordcloud_random()) {
    $randomfeatures[] = activity::RANDOM_FEATURE_WORDCLOUD;
}
if ($activity->is_peers_random()) {
    $randomfeatures[] = activity::RANDOM_FEATURE_PEERS;
}
$showrandom = !empty($randomfeatures);

// Handle live-control saves and random re-shuffles (redirects when handled).
\mod_dictogloss\local\live_controls::process($activity, $instance, $randomfeatures,
    [
        'reveal' => $showreveal,
        'replay' => $showreplay,
        'reconstruction' => $showreconstruction,
        'peers' => $showpeers,
    ], $PAGE->url);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));

if (!$showreveal && !$showreplay && !$showreconstruction && !$showpeers && !$showrandom) {
    echo $OUTPUT->render(new \core\output\notification(
        get_string('nocontrols', 'mod_dictogloss'), \core\output\notification::NOTIFY_INFO));
    echo $OUTPUT->footer();
    exit;
}

$renderer = $PAGE->get_renderer('mod_dictogloss');

$switches = [];
if ($showreplay) {
    $switches[] = ['name' => 'replayopen', 'label' => get_string('liveshowreplay', 'mod_dictogloss'),
        'description' => get_string('liveshowreplay_desc', 'mod_dictogloss'),
        'checked' => !empty($instance->replayopen)];
}
if ($showreconstruction) {
    $switches[] = ['name' => 'reconstructionopen', 'label' => get_string('liveshowreconstruction', 'mod_dictogloss'),
        'description' => get_string('liveshowreconstruction_desc', 'mod_dictogloss'),
        'checked' => !empty($instance->reconstructionopen)];
}
if ($showpeers) {
    $switches[] = ['name' => 'peeropen', 'label' => get_string('liveshowpeers', 'mod_dictogloss'),
        'description' => get_string('liveshowpeers_desc', 'mod_dictogloss'),
        'checked' => !empty($instance->peeropen)];
}
if ($showreveal) {
    $switches[] = ['name' => 'revealed', 'label' => get_string('liveshowreveal', 'mod_dictogloss'),
        'description' => get_string('liveshowreveal_desc', 'mod_dictogloss'),
        'checked' => !empty($instance->revealed)];
}

$shared = !empty($instance->randomshared);
$features = [];
foreach ($randomfeatures as $feature) {
    $teams = $activity->random()->get_random_teams($feature);
    $teamrows = [];
    foreach ($teams as $team) {
        $teamrows[] = [
            'fullname' => s($team['fullname']),
            'partners' => $team['partners']
                ? implode(', ', array_map('s', $team['partners']))
                : get_string('randomnopartners', 'mod_dictogloss'),
        ];
    }
    $features[] = [
        'label' => ($feature === activity::RANDOM_FEATURE_WORDCLOUD)
            ? get_string('randomteams_wordcloud', 'mod_dictogloss')
            : get_string('randomteams_peers', 'mod_dictogloss'),
        'feature' => $feature,
        'nopool' => empty($teams),
        'nopoolmessage' => get_string('randomnopool', 'mod_dictogloss'),
        'showreshuffle' => !$shared,
        'teams' => $teamrows,
    ];
}

echo $renderer->render_live_controls([
    'intro' => get_string('livecontrolsintro', 'mod_dictogloss'),
    'formaction' => $PAGE->url->out_omit_querystring(),
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
    'savelabel' => get_string('savelivecontrols', 'mod_dictogloss'),
    'switches' => $switches,
    'showrandom' => $showrandom,
    'randomteamslabel' => get_string('randomteams', 'mod_dictogloss'),
    'randomteamsintro' => get_string('randomteamsintro', 'mod_dictogloss'),
    'features' => $features,
    'shared' => $shared,
    'reshufflelabel' => get_string('randomreshuffle', 'mod_dictogloss'),
    'reshuffleconfirm' => get_string('randomreshuffle_confirm', 'mod_dictogloss'),
    'participantlabel' => get_string('participant', 'mod_dictogloss'),
    'partnerslabel' => get_string('randompartners', 'mod_dictogloss'),
]);

echo $OUTPUT->footer();
