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
 * Teacher results overview for the Dictogloss activity.
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
$download = optional_param('download', '', PARAM_ALPHA);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'dictogloss');
$instance = $DB->get_record('dictogloss', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = \core\context\module::instance($cm->id);
require_capability('mod/dictogloss:viewreports', $context);

$PAGE->set_url('/mod/dictogloss/results.php', ['id' => $cm->id]);
$PAGE->set_title($course->shortname . ': ' . format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($instance);
$PAGE->requires->js_call_amd('mod_dictogloss/confirm', 'init');

// Handle bulk reopen/delete actions (redirects when an action is submitted).
$activity = new activity($cm, $course, $instance);
$returnurl = new moodle_url('/mod/dictogloss/results.php', ['id' => $cm->id, 'group' => $groupfilter]);
\mod_dictogloss\local\results_actions::process($activity, $returnurl);

// Group filter options.
$groupselect = $activity->scope()->get_group_filter_options((int) $cm->groupingid);

// Honour the separate-groups boundary for restricted teachers.
$visibleuserids = $activity->scope()->get_visible_userids();

$table = new \mod_dictogloss\table\results_table('mod-dictogloss-results', $cm, $instance, $groupfilter,
    empty($download) && has_capability('mod/dictogloss:manage', $context), $visibleuserids);
$table->is_downloading($download, 'dictogloss-results', get_string('results', 'mod_dictogloss'));

if (!$table->is_downloading()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($instance->name));

    $renderer = $PAGE->get_renderer('mod_dictogloss');

    $stats = $activity->overview()->get_results_stats($groupfilter, $visibleuserids);
    $tiles = [
        ['label' => get_string('resultsenrolled', 'mod_dictogloss'), 'value' => $stats['enrolled']],
        ['label' => get_string('statusnotstarted', 'mod_dictogloss'), 'value' => $stats['notstarted']],
        ['label' => get_string('resultsinprogress', 'mod_dictogloss'), 'value' => $stats['inprogress']],
        ['label' => get_string('statussubmitted', 'mod_dictogloss'), 'value' => $stats['submitted']],
        ['label' => get_string('statusfinalised', 'mod_dictogloss'), 'value' => $stats['finalised']],
        ['label' => get_string('resultsaverage', 'mod_dictogloss'),
            'value' => ($stats['average'] === null ? '-' : format_float($stats['average'], 2))],
    ];
    echo $renderer->render_results_summary($tiles, $groupselect, 'group', $groupfilter,
        get_string('filterbygroup', 'mod_dictogloss'), $cm->id, $PAGE->url->out_omit_querystring());
}

$table->out(50, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}