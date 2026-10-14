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
 * Gradebook drill-down page for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/dictogloss/lib.php');

use mod_dictogloss\activity;

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'dictogloss');
require_login($course, false, $cm);

$instance = $DB->get_record('dictogloss', ['id' => $cm->instance], '*', MUST_EXIST);
$context = \core\context\module::instance($cm->id);

$PAGE->set_url('/mod/dictogloss/grade.php', ['id' => $id, 'userid' => $userid]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($userid) {
    require_capability('mod/dictogloss:grade', $context);

    // Respect the activity group mode: a restricted grader may only see their groups' users.
    $activity = new activity($cm, $course, $instance);
    if (!$activity->can_access_user($userid)) {
        throw new \moodle_exception('nopermissions', 'error', '', get_string('grade'));
    }

    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $gradinginfo = grade_get_grades($course->id, 'mod', 'dictogloss', $instance->id, $userid);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(fullname($user));
    if (!empty($gradinginfo->items[0]->grades[$userid])) {
        $grade = $gradinginfo->items[0]->grades[$userid];
        echo html_writer::tag('p', get_string('grade') . ': ' . $grade->str_long_grade);
    } else {
        echo $OUTPUT->render(new \core\output\notification(
        get_string('nograde', 'mod_dictogloss'), \core\output\notification::NOTIFY_INFO));
    }
    echo $OUTPUT->footer();
    exit;
}

redirect(new moodle_url('/mod/dictogloss/view.php', ['id' => $cm->id]));
