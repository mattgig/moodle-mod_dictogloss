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

namespace mod_dictogloss\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_dictogloss\activity;

/**
 * External function: return the freshly rendered keyword word cloud.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_wordcloud extends external_api {

    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Return the rendered word cloud section for the current user.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB, $PAGE, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        list($course, $cm) = get_course_and_cm_from_cmid($params['cmid'], 'dictogloss');
        $context = \core\context\module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/dictogloss:submit', $context);

        $instance = $DB->get_record('dictogloss', ['id' => $cm->instance], '*', MUST_EXIST);
        $activity = new activity($cm, $course, $instance);

        $PAGE->set_context($context);
        $renderer = $PAGE->get_renderer('mod_dictogloss');

        return ['html' => $renderer->render_wordcloud_section($activity, $USER->id, true)];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered word cloud section HTML'),
        ]);
    }
}
