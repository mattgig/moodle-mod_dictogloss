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
 * Teacher report and monitoring page for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/mod/dictogloss/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

use mod_dictogloss\activity;

$id = required_param('id', PARAM_INT);
$groupfilter = optional_param('group', 0, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'dictogloss');
$instance = $DB->get_record('dictogloss', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = \core\context\module::instance($cm->id);
require_capability('mod/dictogloss:manage', $context);

$activity = new activity($cm, $course, $instance);

// Random team scoping. A "team" is a selected student's self + assigned partners.
$teamuserid = optional_param('teamuser', 0, PARAM_INT);
$wordcloudrandom = $activity->is_wordcloud_random();
$peersrandom = $activity->is_peers_random();
$anyrandom = $wordcloudrandom || $peersrandom;
$wordclouduserids = null;
$peersuserids = null;
if ($teamuserid) {
    if ($wordcloudrandom) {
        $wordclouduserids = $activity->random()->get_random_scope_userids($teamuserid, activity::RANDOM_FEATURE_WORDCLOUD);
    }
    if ($peersrandom) {
        $peersuserids = $activity->random()->get_random_scope_userids($teamuserid, activity::RANDOM_FEATURE_PEERS);
    }
}
$monitoruserids = $peersrandom ? $peersuserids : ($wordcloudrandom ? $wordclouduserids : null);

$PAGE->set_url('/mod/dictogloss/report.php',
    ['id' => $cm->id, 'group' => $groupfilter, 'teamuser' => $teamuserid]);
$PAGE->set_title($course->shortname . ': ' . format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($instance);
$PAGE->requires->js_call_amd('mod_dictogloss/confirm', 'init');
$PAGE->requires->js_call_amd('mod_dictogloss/variants', 'init');

// Handle any moderation/deletion POST action (redirects and exits when handled).
\mod_dictogloss\local\report_actions::process($activity, $context, $instance, $groupfilter,
    $wordclouduserids, $PAGE->url);

// Build the filter options. When a random scope is active the filter selects a student
// whose assigned team scopes the page, otherwise it filters by Moodle group.
if ($anyrandom) {
    $filteroptions = [0 => get_string('allstudents', 'mod_dictogloss')];
    $students = get_enrolled_users($context, 'mod/dictogloss:submit', 0,
        'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename',
        'u.lastname, u.firstname', 0, 0, true);
    foreach ($students as $student) {
        $filteroptions[(int) $student->id] = s(fullname($student));
    }
    $filtername = 'teamuser';
    $filtervalue = $teamuserid;
    $filterlabel = get_string('filterbyteam', 'mod_dictogloss');
    $filterid = 'mod-dictogloss-teamfilter';
} else {
    $filteroptions = $activity->scope()->get_group_filter_options((int) $cm->groupingid);
    $filtername = 'group';
    $filtervalue = $groupfilter;
    $filterlabel = get_string('filterbygroup', 'mod_dictogloss');
    $filterid = 'mod-dictogloss-groupfilter';
}

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('mod_dictogloss');

// Filter, applies to all sections on this page.
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $PAGE->url->out_omit_querystring(),
    'class' => 'd-flex align-items-center gap-2 mb-4',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::tag('label', $filterlabel, ['for' => $filterid, 'class' => 'mb-0']);
echo html_writer::select($filteroptions, $filtername, $filtervalue, false, [
    'id' => $filterid,
    'class' => 'form-select w-auto',
    'onchange' => 'this.form.submit()',
]);
echo html_writer::end_tag('form');

// 1) Word cloud moderation.
echo html_writer::start_tag('details', ['class' => 'mod-dictogloss-section mb-4']);
echo html_writer::tag('summary', get_string('wordmoderation', 'mod_dictogloss'),
    ['class' => 'mod-dictogloss-summary h5']);
$aggregate = $activity->wordcloud()->get_keyword_aggregate($groupfilter ?: null, $wordclouduserids);
if ($aggregate) {
    $overrides = $activity->wordcloud()->get_word_overrides();
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out_omit_querystring()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    if ($anyrandom) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'teamuser', 'value' => $teamuserid]);
    }
    $table = new html_table();
    $table->head = [
        html_writer::checkbox('selectallwords', 1, false, '', [
            'data-dictogloss-selectall' => '.mod-dictogloss-wordselect',
            'aria-label' => get_string('selectall', 'mod_dictogloss'),
        ]),
        get_string('keywords', 'mod_dictogloss'),
        get_string('count', 'mod_dictogloss'),
        get_string('hideword', 'mod_dictogloss'),
        get_string('renameword', 'mod_dictogloss'),
    ];
    $index = 0;
    foreach ($aggregate as $row) {
        $override = $overrides[$row->word] ?? null;
        $table->data[] = [
            html_writer::checkbox('selectedwords[]', $row->word, false, '', [
                'class' => 'mod-dictogloss-wordselect',
                'aria-label' => format_string($row->word),
            ]),
            format_string($row->word) . html_writer::empty_tag('input',
                ['type' => 'hidden', 'name' => "word[$index]", 'value' => $row->word]),
            (int) $row->c,
            html_writer::checkbox("hide[$index]", 1, $override && $override->action === 'hide'),
            html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => "rename[$index]",
                'value' => ($override && $override->action === 'rename') ? $override->replacement : '',
            ]),
        ];
        $index++;
    }
    echo html_writer::table($table);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'name' => 'savemoderation',
        'value' => get_string('savemoderation', 'mod_dictogloss'),
        'class' => 'btn btn-primary',
    ]);
    echo ' ';
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'name' => 'deletewords',
        'value' => get_string('deletekeywords', 'mod_dictogloss'),
        'class' => 'btn btn-outline-danger',
        'data-dictogloss-confirm' => get_string('deletekeywords_confirm', 'mod_dictogloss'),
    ]);
    echo html_writer::end_tag('form');
} else {
    echo $OUTPUT->render(new \core\output\notification(
        get_string('wordcloudempty', 'mod_dictogloss'), \core\output\notification::NOTIFY_INFO));
}
echo html_writer::end_tag('details');

// 2) Reconstruction options.
echo html_writer::start_tag('details', ['class' => 'mod-dictogloss-section mb-4']);
echo html_writer::tag('summary', get_string('variantssubheading', 'mod_dictogloss'),
    ['class' => 'mod-dictogloss-summary h5']);

// Display options (persisted in the browser by the AMD module).
echo html_writer::start_div('mod-dictogloss-variantoptions d-flex flex-wrap gap-3 mb-3');
foreach ([
    'colorbyoriginal' => 'mod-dictogloss-colorby',
    'hidenames' => 'mod-dictogloss-hidenames',
    'shufflenames' => 'mod-dictogloss-shuffle',
] as $labelkey => $controlid) {
    echo html_writer::start_div('form-check form-switch mb-0');
    echo html_writer::checkbox($controlid, 1, false, '', [
        'id' => $controlid,
        'class' => 'form-check-input',
        'role' => 'switch',
    ]);
    echo html_writer::tag('label', get_string($labelkey, 'mod_dictogloss'),
        ['for' => $controlid, 'class' => 'form-check-label']);
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo $renderer->render_variants($activity->overview()->get_variants_overview($groupfilter ?: null, $peersuserids));
echo html_writer::end_tag('details');

// 3) Monitor contributions.
echo html_writer::start_tag('details', ['class' => 'mod-dictogloss-section mb-4']);
echo html_writer::tag('summary', get_string('monitor', 'mod_dictogloss'),
    ['class' => 'mod-dictogloss-summary h5']);
echo $renderer->render_monitor($activity->overview()->get_monitor_rows($groupfilter ?: null, $monitoruserids),
    $cm->id, $groupfilter);
echo html_writer::end_tag('details');

echo $OUTPUT->footer();