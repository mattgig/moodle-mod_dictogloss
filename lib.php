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
 * Library of functions and hooks for the Dictogloss activity module.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * List of features supported by the Dictogloss module.
 *
 * @param string $feature FEATURE_xx constant for the requested feature.
 * @return mixed True if the module supports the feature, false if not, null if unknown.
 */
function dictogloss_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_ASSIGNMENT,
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => true,
        FEATURE_MOD_INTRO => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES => true,
        FEATURE_GRADE_HAS_GRADE => true,
        FEATURE_GRADE_OUTCOMES => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_ASSESSMENT,
        default => null,
    };
}

/**
 * Return the editor options used for the Dictogloss text fields.
 *
 * @param context $context The context the files belong to.
 * @return array
 */
function dictogloss_get_editor_options($context) {
    global $CFG;
    return [
        'subdirs' => 0,
        'maxbytes' => $CFG->maxbytes,
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'changeformat' => 1,
        'context' => $context,
        'noclean' => 1,
        'trusttext' => 0,
    ];
}

/**
 * Return the file manager options used for the audio file area.
 *
 * @param context $context The context the files belong to.
 * @return array
 */
function dictogloss_get_filemanager_options($context) {
    global $CFG;
    return [
        'subdirs' => 0,
        'maxbytes' => $CFG->maxbytes,
        'maxfiles' => 10,
        'accepted_types' => ['audio'],
        'return_types' => FILE_INTERNAL,
        'context' => $context,
    ];
}

/**
 * Persist the editor and file manager fields for a Dictogloss instance.
 *
 * The module-level file areas all use itemid 0, so the instance id is not
 * required and this can run before the record is inserted.
 *
 * @param stdClass $data The form data.
 * @param int $coursemodule The course module id, if available.
 * @return stdClass The updated form data.
 */
function dictogloss_process_files(stdClass $data, $coursemodule) {
    if (empty($coursemodule)) {
        return $data;
    }

    $context = \core\context\module::instance($coursemodule);

    // Save the editor fields: the submitted value is an array with text/format/itemid.
    foreach (['instructions', 'source'] as $field) {
        if (isset($data->$field) && is_array($data->$field)) {
            $editor = $data->$field;
            $data->$field = file_save_draft_area_files($editor['itemid'], $context->id, 'mod_dictogloss',
                $field, 0, dictogloss_get_editor_options($context), $editor['text']);
            $data->{$field . 'format'} = $editor['format'];
        }
    }

    // Save the audio file manager draft area.
    if (!empty($data->audio)) {
        file_save_draft_area_files($data->audio, $context->id, 'mod_dictogloss', 'audio', 0,
            dictogloss_get_filemanager_options($context));
    }

    return $data;
}

/**
 * Return the course module information used by the completion subsystem.
 *
 * @param stdClass $coursemodule The course module record.
 * @return cached_cm_info|false The course module info.
 */
function dictogloss_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, completionsubmit';
    $dictogloss = $DB->get_record('dictogloss', ['id' => $coursemodule->instance], $fields);
    if (!$dictogloss) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $dictogloss->name;

    if ($coursemodule->showdescription) {
        // Cached content is not filtered; filters run at display time.
        $result->content = format_module_intro('dictogloss', $dictogloss, $coursemodule->id, false);
    }

    // Expose the custom completion rule so it can be evaluated and described.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionsubmit'] = $dictogloss->completionsubmit;
    }

    return $result;
}

/**
 * Add a new Dictogloss instance.
 *
 * @param stdClass $data The form data.
 * @param mod_dictogloss_mod_form|null $form The form instance, if available.
 * @return int The new instance id.
 */
function dictogloss_add_instance(stdClass $data, ?mod_dictogloss_mod_form $form = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    // The course module (and its context) already exists at this point.
    $data = dictogloss_process_files($data, $data->coursemodule ?? 0);

    // Ensure text/format defaults are never null.
    foreach (['instructions', 'source'] as $field) {
        if (!isset($data->$field) || $data->$field === null) {
            $data->$field = '';
        }
        $formatfield = $field . 'format';
        if (!isset($data->$formatfield) || $data->$formatfield === null) {
            $data->$formatfield = FORMAT_HTML;
        }
    }

    $data->id = $DB->insert_record('dictogloss', $data);

    dictogloss_grade_item_update($data);

    return $data->id;
}

/**
 * Update an existing Dictogloss instance.
 *
 * @param stdClass $data The form data.
 * @param mod_dictogloss_mod_form|null $form The form instance, if available.
 * @return bool
 */
function dictogloss_update_instance(stdClass $data, ?mod_dictogloss_mod_form $form = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    $data = dictogloss_process_files($data, $data->coursemodule ?? 0);

    $result = $DB->update_record('dictogloss', $data);

    dictogloss_grade_item_update($data);

    return $result;
}

/**
 * Delete a Dictogloss instance and all associated data.
 *
 * @param int $id The instance id.
 * @return bool
 */
function dictogloss_delete_instance($id) {
    global $DB;

    if (!$dictogloss = $DB->get_record('dictogloss', ['id' => $id])) {
        return false;
    }

    $submissions = $DB->get_records('dictogloss_submissions', ['dictogloss' => $id], '', 'id');
    if ($submissions) {
        $submissionids = array_keys($submissions);
        list($insql, $inparams) = $DB->get_in_or_equal($submissionids, SQL_PARAMS_QM);
        $DB->delete_records_select('dictogloss_keywords', "submissionid $insql", $inparams);
    }

    $DB->delete_records('dictogloss_submissions', ['dictogloss' => $id]);
    $DB->delete_records('dictogloss_wordoverrides', ['dictogloss' => $id]);
    $DB->delete_records('dictogloss_randompartners', ['dictogloss' => $id]);

    $cm = get_coursemodule_from_instance('dictogloss', $id);
    if ($cm) {
        $context = \core\context\module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_dictogloss');
    }

    dictogloss_grade_item_delete($dictogloss);

    $DB->delete_records('dictogloss', ['id' => $id]);

    return true;
}

/**
 * Create or update the grade item for a Dictogloss instance.
 *
 * @param stdClass $dictogloss The instance.
 * @param mixed $grades Optional grades to push.
 * @return int
 */
function dictogloss_grade_item_update($dictogloss, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname' => clean_param($dictogloss->name, PARAM_NOTAGS),
    ];

    if (!empty($dictogloss->grade)) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = (float) $dictogloss->grade;
        $item['grademin'] = 0;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $grades = null;
        $item['reset'] = true;
    }

    return grade_update('mod/dictogloss', $dictogloss->course, 'mod', 'dictogloss',
        $dictogloss->id, 0, $grades, $item);
}

/**
 * Delete the grade item for a Dictogloss instance.
 *
 * @param stdClass $dictogloss The instance.
 * @return int
 */
function dictogloss_grade_item_delete($dictogloss) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/dictogloss', $dictogloss->course, 'mod', 'dictogloss',
        $dictogloss->id, 0, null, ['deleted' => 1]);
}

/**
 * Update the grades for a Dictogloss instance.
 *
 * @param stdClass $dictogloss The instance.
 * @param int $userid Optional single user.
 * @param bool $nullifnone Whether to push a null grade when there is no grade.
 * @return void
 */
function dictogloss_update_grades($dictogloss, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (empty($dictogloss->grade)) {
        dictogloss_grade_item_update($dictogloss);
        return;
    }

    if ($userid) {
        $grades = dictogloss_get_user_grades($dictogloss, $userid);
    } else {
        $grades = dictogloss_get_user_grades($dictogloss);
    }

    if ($grades) {
        dictogloss_grade_item_update($dictogloss, $grades);
    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        dictogloss_grade_item_update($dictogloss, $grade);
    } else {
        dictogloss_grade_item_update($dictogloss);
    }
}

/**
 * Return the grades for users on a Dictogloss instance.
 *
 * @param stdClass $dictogloss The instance.
 * @param int $userid Optional single user.
 * @return array
 */
function dictogloss_get_user_grades($dictogloss, $userid = 0) {
    global $DB;

    $params = ['dictogloss' => $dictogloss->id];
    $where = 'dictogloss = :dictogloss AND grade IS NOT NULL';
    if ($userid) {
        $where .= ' AND userid = :userid';
        $params['userid'] = $userid;
    }

    $submissions = $DB->get_records_select('dictogloss_submissions', $where, $params);
    $grades = [];
    foreach ($submissions as $submission) {
        $grade = new stdClass();
        $grade->userid = $submission->userid;
        $grade->rawgrade = $submission->grade;
        $grade->dategraded = $submission->timegraded ?: $submission->timemodified;
        $grades[$submission->userid] = $grade;
    }

    return $grades;
}

/**
 * Reset user data for the Dictogloss activity.
 *
 * @param stdClass $data The reset form data.
 * @return array
 */
function dictogloss_reset_userdata($data) {
    global $DB;

    $status = [];

    if (!empty($data->reset_dictogloss_submissions)) {
        $instanceids = $DB->get_fieldset_select('dictogloss', 'id', 'course = ?', [$data->courseid]);
        if ($instanceids) {
            list($insql, $inparams) = $DB->get_in_or_equal($instanceids, SQL_PARAMS_QM);
            $affectedusers = $DB->get_fieldset_sql(
                "SELECT DISTINCT userid FROM {dictogloss_submissions} WHERE dictogloss $insql", $inparams);
            $submissionids = $DB->get_fieldset_select('dictogloss_submissions', 'id',
                "dictogloss $insql", $inparams);
            if ($submissionids) {
                list($subinsql, $subparams) = $DB->get_in_or_equal($submissionids, SQL_PARAMS_QM);
                $DB->delete_records_select('dictogloss_keywords', "submissionid $subinsql", $subparams);
            }
            $DB->delete_records_select('dictogloss_submissions', "dictogloss $insql", $inparams);
            $DB->delete_records_select('dictogloss_wordoverrides', "dictogloss $insql", $inparams);
            $DB->delete_records_select('dictogloss_randompartners', "dictogloss $insql", $inparams);
            $DB->set_field_select('dictogloss', 'timemodified', time(), "id $insql", $inparams);

            // Recompute completion for the affected users now that their contributions are gone.
            if ($affectedusers) {
                $course = get_course($data->courseid);
                $completion = new completion_info($course);
                foreach ($instanceids as $instanceid) {
                    $cm = get_coursemodule_from_instance('dictogloss', $instanceid, $data->courseid);
                    if (!$cm || !$completion->is_enabled($cm)) {
                        continue;
                    }
                    foreach ($affectedusers as $affuserid) {
                        $completion->update_state($cm, COMPLETION_UNKNOWN, (int) $affuserid);
                    }
                }
            }
        }

        $status[] = [
            'component' => get_string('modulenameplural', 'mod_dictogloss'),
            'item' => get_string('deleteallsubmissions'),
            'error' => false,
        ];
    }

    return $status;
}

/**
 * Return a small summary of a user's activity for the grade user report.
 * @param stdClass $course The course.
 * @param stdClass $user The user.
 * @param stdClass $mod The course module.
 * @param stdClass $dictogloss The instance.
 * @return stdClass|null
 */
function dictogloss_user_outline($course, $user, $mod, $dictogloss) {
    global $DB;

    $submission = $DB->get_record('dictogloss_submissions',
        ['dictogloss' => $dictogloss->id, 'userid' => $user->id]);

    if ($submission && $submission->status >= \mod_dictogloss\activity::STATUS_SUBMITTED) {
        $result = new stdClass();
        if ($submission->grade !== null) {
            $result->info = get_string('grade') . ': ' . format_float($submission->grade, 2);
        } else if ($submission->status >= \mod_dictogloss\activity::STATUS_FINALISED) {
            $result->info = get_string('statusfinalised', 'mod_dictogloss');
        } else {
            $result->info = get_string('statussubmitted', 'mod_dictogloss');
        }
        $result->time = $submission->timefinalised ?: $submission->timemodified;
        return $result;
    }

    return null;
}

/**
 * Print a full summary of a user's activity for the grade user report.
 *
 * @param stdClass $course The course.
 * @param stdClass $user The user.
 * @param stdClass $mod The course module.
 * @param stdClass $dictogloss The instance.
 * @return void
 */
function dictogloss_user_complete($course, $user, $mod, $dictogloss) {
    $outline = dictogloss_user_outline($course, $user, $mod, $dictogloss);
    if ($outline) {
        echo get_string('useroutlinedate', 'mod_dictogloss', [
            'info' => $outline->info,
            'date' => userdate($outline->time),
        ]);
    } else {
        echo get_string('nosubmission', 'mod_dictogloss');
    }
}

/**
 * Add links to the activity navigation (secondary tabs / More menu).
 *
 * @param settings_navigation $settings The settings navigation.
 * @param navigation_node $modnode The module node.
 * @return void
 */
function dictogloss_extend_settings_navigation(settings_navigation $settings, navigation_node $modnode) {
    global $DB;

    $page = $settings->get_page();
    // Note: $page->cm and $page->course are magic properties backed by __get() with no __isset(),
    // so empty() would always evaluate to true here. Use truthy checks instead.
    if (!$page->cm || !$page->course) {
        return;
    }

    $cm = $page->cm;
    $course = $page->course;
    $context = \core\context\module::instance($cm->id);

    $instance = $DB->get_record('dictogloss', ['id' => $cm->instance]);
    if (!$instance) {
        return;
    }
    $activity = new \mod_dictogloss\activity($cm, $course, $instance);

    // Insert our tabs directly after "Edit settings" (before the roles/overrides nodes) so they
    // are picked up early and shown in the secondary navigation rather than being pushed into the
    // "More" menu once the 5-node display limit is reached.
    $keys = $modnode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    foreach (\mod_dictogloss\local\nav_tabs::get($activity, $context) as $tab) {
        $node = navigation_node::create($tab['label'], $tab['url'],
            navigation_node::TYPE_SETTING, null, $tab['key']);
        $modnode->add_node($node, $beforekey);
    }
}

/**
 * Serve files for the Dictogloss module.
 *
 * @param stdClass $course The course.
 * @param stdClass $cm The course module.
 * @param context $context The context.
 * @param string $filearea The file area.
 * @param array $args The path arguments.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool
 */
function dictogloss_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    $allowedareas = ['intro', 'instructions', 'source', 'audio'];
    if (!in_array($filearea, $allowedareas)) {
        return false;
    }

    if (!has_capability('mod/dictogloss:view', $context)) {
        return false;
    }

    // Files embedded in the original text must stay hidden until it is revealed.
    if ($filearea === 'source') {
        $instance = $DB->get_record('dictogloss', ['id' => $cm->instance]);
        if ($instance) {
            $isteacher = has_capability('mod/dictogloss:manage', $context);
            $activity = new \mod_dictogloss\activity($cm, $course, $instance);
            if (!$activity->state()->can_view_source($isteacher, $USER->id)) {
                return false;
            }
        }
    }

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_dictogloss', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
