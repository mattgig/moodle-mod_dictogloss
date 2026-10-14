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
 * Main view page for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/dictogloss/lib.php');

use mod_dictogloss\activity;
use mod_dictogloss\form\keywords_form;
use mod_dictogloss\form\reconstruction_form;
use mod_dictogloss\form\followup_form;

$id = optional_param('id', 0, PARAM_INT);
$d = optional_param('d', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

if ($id) {
    list($course, $cm) = get_course_and_cm_from_cmid($id, 'dictogloss');
    $instance = $DB->get_record('dictogloss', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $instance = $DB->get_record('dictogloss', ['id' => $d], '*', MUST_EXIST);
    list($course, $cm) = get_course_and_cm_from_instance($instance->id, 'dictogloss');
}

require_login($course, true, $cm);
$context = \core\context\module::instance($cm->id);
require_capability('mod/dictogloss:view', $context);

$isteacher = has_capability('mod/dictogloss:manage', $context);

$activity = new activity($cm, $course, $instance);

// Only teachers may view another user's contribution, and only within their group scope.
$viewuserid = (int) $USER->id;
if ($isteacher && $userid) {
    if (!$activity->can_access_user((int) $userid)) {
        throw new \moodle_exception('nopermissions', 'error', '',
            get_string('viewcontribution', 'mod_dictogloss'));
    }
    $viewuserid = (int) $userid;
}
$isown = ($viewuserid === (int) $USER->id);

// Page setup.
$PAGE->set_url('/mod/dictogloss/view.php', ['id' => $cm->id]);
$PAGE->set_title($course->shortname . ': ' . format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
// The stage (and audio availability) can change at any time; do not serve the page from
// cache/back-forward cache so the browser always renders the current state.
$PAGE->set_cacheable(false);
// Register the activity record so the activity header renders the name and intro.
$PAGE->set_activity_record($instance);
$PAGE->requires->js_call_amd('mod_dictogloss/confirm', 'init');
$PAGE->requires->js_call_amd('mod_dictogloss/colour', 'init');

// Teacher action: reopen a handed-in contribution (redirects when handled).
\mod_dictogloss\local\view_actions::maybe_reopen($activity, $isteacher, $action, $PAGE->url);

// Trigger the viewed event.
$event = \mod_dictogloss\event\course_module_viewed::create([
    'objectid' => $instance->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('dictogloss', $instance);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$submission = $activity->submissions()->get_submission($viewuserid);
$canedit = $isown && $activity->can_submit($USER->id);
$finalised = $submission && (int) $submission->status >= activity::STATUS_FINALISED;
$audiofiles = $activity->get_audio_files();
$replaystage = !empty($instance->replaystage) && !empty($audiofiles);

// Student action: advance from the listen-again step to the reconstruction.
\mod_dictogloss\local\view_actions::maybe_ready_reconstruction($activity, $isown, $canedit, $action,
    $viewuserid, $PAGE->url);

// Teacher navigation data (ordered list of contributors) so we can move to the next one.
$contributors = [];
$contributorids = [];
$currentcontributorindex = false;
$nextuserid = 0;
if (!$isown) {
    $contributors = $activity->overview()->get_contributors();
    $contributorids = array_map(fn($row) => (int) $row['userid'], $contributors);
    $currentcontributorindex = array_search($viewuserid, $contributorids, true);
    if ($currentcontributorindex !== false && $currentcontributorindex < count($contributorids) - 1) {
        $nextuserid = (int) $contributorids[$currentcontributorindex + 1];
    }
}

if ($canedit) {
    $PAGE->requires->js_call_amd('mod_dictogloss/notebook', 'init', [[
        'cmid' => $cm->id,
        'saving' => get_string('saving', 'mod_dictogloss'),
        'saved' => get_string('saved', 'mod_dictogloss'),
    ]]);
    $PAGE->requires->js_call_amd('mod_dictogloss/wordcloud', 'init', [[
        'cmid' => $cm->id,
        'buttonid' => 'mod-dictogloss-refresh-cloud',
        'containerid' => 'mod-dictogloss-wordcloud',
        'refreshing' => get_string('refreshing', 'mod_dictogloss'),
    ]]);
    $PAGE->requires->js_call_amd('mod_dictogloss/peers', 'init', [[
        'cmid' => $cm->id,
        'buttonid' => 'mod-dictogloss-showpeers',
        'containerid' => 'mod-dictogloss-peers',
        'refreshing' => get_string('refreshing', 'mod_dictogloss'),
        'waitlabel' => get_string('peerscooldown', 'mod_dictogloss', '{$a}'),
    ]]);
    if ($audiofiles) {
        $PAGE->requires->js_call_amd('mod_dictogloss/audiolock', 'init', [['cmid' => $cm->id]]);
    }
}

// Determine the current stage.
$stage = 'keywords';
if ($submission) {
    if ($submission->status >= activity::STATUS_SUBMITTED) {
        $stage = 'comparison';
    } else if ($submission->status >= activity::STATUS_KEYWORDS) {
        if ($replaystage && empty($submission->timereconstructionready)) {
            $stage = 'listenagain';
        } else {
            $stage = 'reconstruction';
        }
    }
}

// Process submitted forms (only for the owner).
$keywordsform = null;
$reconstructionform = null;
$followupform = null;
$gradeform = null;

if (!$isown && !empty($instance->grade) && has_capability('mod/dictogloss:grade', $context)) {
    $gradeform = new \mod_dictogloss\form\grade_form($PAGE->url,
        ['maxgrade' => $instance->grade, 'context' => $context]);
    if ($data = $gradeform->get_data()) {
        $gradevalue = ($data->grade === '' || $data->grade === null) ? null : (float) $data->grade;
        $feedback = is_array($data->feedback) ? $data->feedback['text'] : $data->feedback;
        $activity->submissions()->save_grade($viewuserid, $gradevalue, (string) $feedback, !empty($data->override));
        if (!empty($data->savegradenext) && !empty($nextuserid)) {
            redirect(new moodle_url('/mod/dictogloss/view.php', ['id' => $cm->id, 'userid' => $nextuserid]),
                get_string('saved', 'mod_dictogloss'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        redirect($PAGE->url, get_string('saved', 'mod_dictogloss'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    $gradedefaults = [
        'id' => $cm->id,
        'userid' => $viewuserid,
        'grade' => $submission ? $submission->grade : '',
        'override' => $submission ? (int) $submission->gradeoverridden : 1,
    ];
    if ($submission) {
        $gradedefaults['feedback'] = ['text' => (string) $submission->feedback, 'format' => FORMAT_HTML];
    }
    $gradeform->set_data($gradedefaults);
}

if ($canedit) {
    if ($stage === 'keywords' && !empty($instance->shownotes)) {
        $keywordsform = new keywords_form($PAGE->url);
        if ($data = $keywordsform->get_data()) {
            $activity->submissions()->save_keywords($data->keywords, $viewuserid);
            redirect($PAGE->url, get_string('saved', 'mod_dictogloss'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        $keywordsform->set_data(['id' => $cm->id, 'keywords' => $submission ? $submission->keywords : '']);
    } else if ($stage === 'reconstruction' || ($stage === 'keywords' && empty($instance->shownotes))) {
        $reconstructionform = new reconstruction_form($PAGE->url);
        if ($activity->state()->is_reconstruction_open() && ($data = $reconstructionform->get_data())) {
            if (!empty($data->submitreconstruction) && trim((string) $data->reconstruction) === '') {
                // A blank reconstruction cannot be submitted or handed in.
                redirect($PAGE->url, get_string('required'), null,
                    \core\output\notification::NOTIFY_ERROR);
            }
            $activity->submissions()->save_reconstruction($data->reconstruction, $viewuserid);
            if (!empty($data->submitreconstruction)) {
                $activity->submissions()->submit_reconstruction($viewuserid);
                if (empty($instance->allowfollowup)) {
                    // No rewrite step: submitting the reconstruction hands in the contribution.
                    $activity->submissions()->finalise_submission($viewuserid);
                }
            }
            redirect($PAGE->url, get_string('saved', 'mod_dictogloss'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        $reconstructionform->set_data([
            'id' => $cm->id,
            'reconstruction' => $submission ? $submission->reconstruction : '',
        ]);
    } else if ($stage === 'comparison' && !empty($instance->allowfollowup) && !$finalised) {
        $followupform = new followup_form($PAGE->url);
        if ($data = $followupform->get_data()) {
            $activity->submissions()->save_followup($data->followup, $viewuserid);
            if (!empty($data->finalise)) {
                $activity->submissions()->finalise_submission($viewuserid);
                redirect($PAGE->url, get_string('handedinsuccess', 'mod_dictogloss'), null,
                    \core\output\notification::NOTIFY_SUCCESS);
            }
            redirect($PAGE->url, get_string('saved', 'mod_dictogloss'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        $followupform->set_data(['id' => $cm->id, 'followup' => $submission ? $submission->followup : '']);
    }
}

// Build the stage navigation: Listen > Keyword notes > Reconstruction > Comparison > Rewriting.
$status = $submission ? (int) $submission->status : activity::STATUS_NOTSTARTED;
$played = $submission && !empty($submission->timeplayed);
$hasaudio = !empty($audiofiles);

$listendone = $hasaudio ? $played : ($status >= activity::STATUS_KEYWORDS);
$notesdone = $status >= activity::STATUS_KEYWORDS;
$recondone = $status >= activity::STATUS_SUBMITTED;
$comparisondone = $status >= activity::STATUS_SUBMITTED;
$rewritedone = $submission && (!empty($submission->timefollowup) || !empty($submission->timefinalised));

$stageitems = [
    ['label' => get_string('stage_listening', 'mod_dictogloss'), 'done' => $listendone],
    ['label' => get_string('stage_keywords', 'mod_dictogloss'), 'done' => $notesdone],
    ['label' => get_string('stage_reconstruction', 'mod_dictogloss'), 'done' => $recondone],
    ['label' => get_string('stage_comparison', 'mod_dictogloss'), 'done' => $comparisondone],
];
if (!empty($instance->allowfollowup)) {
    $stageitems[] = ['label' => get_string('stage_rewriting', 'mod_dictogloss'), 'done' => $rewritedone];
}

// The first step that is not complete yet is the one the student is working on.
$activeindex = null;
foreach ($stageitems as $index => $item) {
    if (empty($item['done'])) {
        $activeindex = $index;
        break;
    }
}

$stages = [];
foreach ($stageitems as $index => $item) {
    $stages[] = [
        'label' => $item['label'],
        'active' => ($activeindex === $index),
        'done' => !empty($item['done']),
    ];
}

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('mod_dictogloss');

if (!$isown) {
    $viewuser = $DB->get_record('user', ['id' => $viewuserid], '*', MUST_EXIST);
    echo $OUTPUT->heading(get_string('viewcontribution', 'mod_dictogloss') . ': ' . fullname($viewuser), 4);

    // Navigation between student contributions (previous/next + picker).
    echo $renderer->render_contribution_nav($contributors, $viewuserid, $cm->id);
}

// Per-student teacher controls (the activity-wide flow controls now live on flow.php).
if ($isteacher && !$isown && $submission) {
    echo $renderer->render_teacher_controls($cm->id, $viewuserid, $finalised);
}

echo $renderer->render_stage_nav($stages);

// Render the current stage.
if ($stage === 'keywords' && !empty($instance->shownotes)) {
    if (trim(strip_tags((string) $instance->instructions))) {
        $instructions = file_rewrite_pluginfile_urls((string) $instance->instructions, 'pluginfile.php',
            $context->id, 'mod_dictogloss', 'instructions', 0);
        echo html_writer::div(format_text($instructions, $instance->instructionsformat,
            ['context' => $context]), 'mod-dictogloss-instructions mb-3');
    }

    if ($audiofiles) {
        echo $renderer->render_audio($audiofiles, $context, get_string('playaudio', 'mod_dictogloss'), '', 'mb-3');
    } else {
        echo $OUTPUT->render(new \core\output\notification(
        get_string('noaudio', 'mod_dictogloss'), \core\output\notification::NOTIFY_INFO));
    }

    if ($canedit && $keywordsform) {
        echo html_writer::start_div('mod-dictogloss-formwrapper mt-4');
        $keywordsform->display();
        echo html_writer::end_div();
    } else if ($submission && trim((string) $submission->keywords) !== '') {
        echo $OUTPUT->heading(get_string('keywords', 'mod_dictogloss'), 3);
        echo html_writer::div(s($submission->keywords), 'mod-dictogloss-readonly');
    }
} else if ($stage === 'listenagain') {
    // Student-paced step: review the word cloud and listen again before reconstructing.
    echo $renderer->render_wordcloud_section($activity, $viewuserid, $canedit);

    echo $renderer->render_audio($audiofiles, $context, get_string('listenagain', 'mod_dictogloss'),
        get_string('replaystageintro', 'mod_dictogloss'));

    if ($canedit) {
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $PAGE->url->out_omit_querystring(),
            'class' => 'mod-dictogloss-reconstructionready',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'readyreconstruction']);
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('startreconstruction', 'mod_dictogloss'),
            'class' => 'btn btn-primary',
        ]);
        echo html_writer::end_tag('form');
    }
} else if ($stage === 'reconstruction' || ($stage === 'keywords' && empty($instance->shownotes))) {
    echo $renderer->render_wordcloud_section($activity, $viewuserid, $canedit);

    // Optional: listen to the recording again before reconstructing (screen mode only).
    if (!$replaystage && $activity->state()->is_replay_available() && $audiofiles) {
        echo $renderer->render_audio($audiofiles, $context, get_string('listenagain', 'mod_dictogloss'));
    }

    if ($canedit && !$activity->state()->is_reconstruction_open()) {
        // The teacher is holding the reconstruction; listening/reviewing remains available only
        // when replay is currently allowed, so word the notice accordingly.
        $pendingmsg = $activity->state()->can_listen($viewuserid)
            ? get_string('reconstructionpending', 'mod_dictogloss')
            : get_string('reconstructionpending_nolisten', 'mod_dictogloss');
        echo $OUTPUT->render(new \core\output\notification($pendingmsg, \core\output\notification::NOTIFY_INFO));
    } else if ($canedit && $reconstructionform) {
        echo html_writer::start_div('mod-dictogloss-formwrapper mt-4');
        $reconstructionform->display();
        echo html_writer::end_div();

        // Collaborative: pull and use the group reconstructions.
        if ($activity->peers()->is_peers_enabled()) {
            echo html_writer::start_div('mod-dictogloss-peerssection mt-4');
            echo $OUTPUT->heading(get_string('peersheading', 'mod_dictogloss'), 3);
            echo html_writer::tag('button', get_string('showpeers', 'mod_dictogloss'), [
                'type' => 'button',
                'id' => 'mod-dictogloss-showpeers',
                'class' => 'btn btn-outline-primary',
            ]);
            echo html_writer::div('', 'mod-dictogloss-peers mt-3', ['id' => 'mod-dictogloss-peers']);
            echo html_writer::end_div();
        }
    } else if ($submission && trim((string) $submission->reconstruction) !== '') {
        echo $OUTPUT->heading(get_string('reconstruction', 'mod_dictogloss'), 3);
        echo html_writer::div(format_text($submission->reconstruction, $submission->reconstructionformat,
            ['context' => $context]), 'mod-dictogloss-readonly');
    }
} else {
    if ($finalised) {
        echo $OUTPUT->render(new \core\output\notification(
            get_string('handedinon', 'mod_dictogloss', userdate($submission->timefinalised)),
            \core\output\notification::NOTIFY_SUCCESS));
    }

    // 1) Keyword word cloud (no refresh once handed in).
    echo $renderer->render_wordcloud_section($activity, $viewuserid, $canedit && !$finalised);

    // 2) Text comparison.
    $cansourc = $activity->state()->can_view_source($isteacher, $viewuserid);
    if (!$cansourc) {
        echo $OUTPUT->render(new \core\output\notification(
            get_string('notavailable', 'mod_dictogloss'), \core\output\notification::NOTIFY_INFO));
    } else {
        echo $OUTPUT->heading(get_string('comparisonheading', 'mod_dictogloss'), 3);
        $comparison = $activity->state()->get_comparison($viewuserid);
        if ($comparison) {
            echo $renderer->render_comparison($comparison);
        }
    }

    // 3) Rewrite in own words (card, optionally word-coloured).
    if (!empty($instance->allowfollowup) || ($submission && trim((string) $submission->followup) !== '')) {
        echo html_writer::start_div('card mb-4 mod-dictogloss-rewrite mod-dictogloss-colourable');
        echo html_writer::start_div('card-header d-flex justify-content-between align-items-center gap-2');
        echo $OUTPUT->heading(get_string('followup', 'mod_dictogloss'), 3, 'main mb-0');
        if (($submission && trim((string) $submission->followup) !== '') && !($canedit && $followupform)) {
            echo html_writer::start_div('form-check form-switch mb-0');
            echo html_writer::checkbox('colourrewrite', 1, false, '', [
                'id' => 'mod-dictogloss-colour-rewrite',
                'class' => 'form-check-input',
                'role' => 'switch',
                'data-dictogloss-colour-toggle' => 'mod_dictogloss_colour_rewrite',
            ]);
            echo html_writer::tag('label', get_string('colorbyoriginal', 'mod_dictogloss'),
                ['for' => 'mod-dictogloss-colour-rewrite', 'class' => 'form-check-label']);
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
        echo html_writer::start_div('card-body');
        if ($canedit && $followupform) {
            $followupform->display();
        } else if ($submission && trim((string) $submission->followup) !== '') {
            if ($activity->state()->can_view_source($isteacher, $viewuserid)) {
                echo $renderer->render_coloured_text($submission->followup, $activity->state()->get_plain_source());
            } else {
                echo html_writer::div(format_text($submission->followup, $submission->followupformat,
                    ['context' => $context]), 'mod-dictogloss-text');
            }
        } else {
            echo html_writer::tag('p', get_string('nosubmission', 'mod_dictogloss'),
                ['class' => 'text-muted mb-0']);
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    // 4) Grade and feedback.
    $visiblesubmission = $isown ? $activity->submissions()->get_visible_grade($viewuserid) : $submission;
    if ($gradeform) {
        echo html_writer::start_div('mod-dictogloss-gradesection mb-4');
        echo $OUTPUT->heading(get_string('grade', 'mod_dictogloss'), 3);
        $gradeform->display();
        echo html_writer::end_div();
    } else if ($isown && $visiblesubmission
            && ($visiblesubmission->grade !== null || trim((string) $visiblesubmission->feedback) !== '')) {
        echo $OUTPUT->heading(get_string('grade', 'mod_dictogloss'), 3);
        echo html_writer::start_div('card mb-4');
        echo html_writer::start_div('card-body');
        if ($visiblesubmission->grade !== null) {
            $gradeline = get_string('gradeline', 'mod_dictogloss', [
                'grade' => format_float($visiblesubmission->grade, 2),
                'max' => format_float($instance->grade, 2),
            ]);
            if (!empty($visiblesubmission->gradeoverridden)) {
                $gradeline .= ' <span class="badge text-bg-info">'
                    . get_string('gradeoverridden', 'mod_dictogloss') . '</span>';
            }
            echo html_writer::tag('p', $gradeline);
        }
        if (trim((string) $visiblesubmission->feedback) !== '') {
            echo html_writer::tag('p', get_string('feedback', 'mod_dictogloss'), ['class' => 'h6']);
            echo html_writer::div(format_text($visiblesubmission->feedback, $visiblesubmission->feedbackformat,
                ['context' => $context]), 'mod-dictogloss-feedback');
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();