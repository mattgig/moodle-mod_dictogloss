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
 * Autosave endpoint for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/dictogloss/lib.php');

header('Content-Type: application/json');

try {
    $cmid = required_param('id', PARAM_INT);
    $field = required_param('field', PARAM_ALPHA);
    $value = optional_param('value', '', PARAM_RAW);

    // Reject unreasonably large payloads to protect the database and token tables.
    if (\core_text::strlen($value) > 20000) {
        http_response_code(413);
        echo json_encode(['ok' => false]);
        exit;
    }

    list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'dictogloss');
    require_login($course, false, $cm);

    $context = \core\context\module::instance($cm->id);
    require_capability('mod/dictogloss:submit', $context);
    require_sesskey();

    $instance = $DB->get_record('dictogloss', ['id' => $cm->instance], '*', MUST_EXIST);
    $activity = new \mod_dictogloss\activity($cm, $course, $instance);

    // Apply the autosaved field (the handler rejects finalised contributions).
    echo json_encode(\mod_dictogloss\local\autosave_handler::handle($activity, $field, $value));
} catch (Exception $e) {
    // Do not leak internal exception details to the browser.
    http_response_code(400);
    echo json_encode(['ok' => false]);
}