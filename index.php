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
 * Index of Dictogloss activities in a course.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/mod/dictogloss/lib.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);

$PAGE->set_url('/mod/dictogloss/index.php', ['id' => $id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context(\core\context\course::instance($course->id));

$modinfo = get_fast_modinfo($course);
$instances = $modinfo->get_instances_of('dictogloss');

if (!$instances) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('thereareno', 'moodle', get_string('modulenameplural', 'mod_dictogloss')),
        null, \core\output\notification::NOTIFY_INFO);
}

$event = \mod_dictogloss\event\course_module_instance_list_viewed::create([
    'context' => \core\context\course::instance($course->id),
]);
$event->trigger();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_dictogloss'));

// Fetch the current user's submissions for all instances in a single query.
$instanceids = array_map(fn($cm) => (int) $cm->instance, $instances);
list($insql, $inparams) = $DB->get_in_or_equal($instanceids, SQL_PARAMS_QM);
$submissions = $DB->get_records_sql(
    "SELECT dictogloss, status FROM {dictogloss_submissions}
      WHERE dictogloss $insql AND userid = ?",
    array_merge($inparams, [$USER->id]));

$table = new html_table();
$table->head = [get_string('name'), get_string('submission', 'mod_dictogloss')];
$table->attributes['class'] = 'generaltable';

foreach ($instances as $cm) {
    if (!$cm->uservisible) {
        continue;
    }
    $link = html_writer::link($cm->url, format_string($cm->name));
    $submission = $submissions[(int) $cm->instance] ?? null;
    $status = $submission
        ? \mod_dictogloss\local\status::name((int) $submission->status)
        : get_string('statusnotstarted', 'mod_dictogloss');
    $table->data[] = [$link, $status];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
