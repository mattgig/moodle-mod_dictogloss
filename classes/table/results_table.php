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
 * Results table for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\table;

use mod_dictogloss\activity;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Lists all enrolled students and the state of their contribution.
 */
class results_table extends \table_sql {

    /** @var \stdClass The course module. */
    protected $cm;
    /** @var int The active group filter. */
    protected $groupfilter;
    /** @var array Map of userid => group names. */
    protected $groupmap;

    /**
     * Constructor.
     *
     * @param string $uniqueid The table id.
     * @param \stdClass $cm The course module.
     * @param \stdClass $instance The dictogloss instance.
     * @param int $groupid Optional group filter.
     * @param bool $bulk Whether to show the bulk-selection column (hidden when downloading).
     */
    public function __construct($uniqueid, $cm, $instance, $groupid = 0, $bulk = true) {
        global $DB;

        parent::__construct($uniqueid);

        $this->cm = $cm;
        $this->groupfilter = (int) $groupid;

        $params = ['courseid' => $cm->course, 'dictoglossid' => $instance->id];
        $from = "{user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
            LEFT JOIN {dictogloss_submissions} s ON s.userid = u.id AND s.dictogloss = :dictoglossid";
        $where = "u.deleted = 0 AND ue.status = 0";
        if ($groupid) {
            $where .= " AND u.id IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid = :groupid)";
            $params['groupid'] = $groupid;
        }

        $fields = "DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                   u.middlename, u.alternatename, u.picture, u.imagealt,
                   s.id AS submissionid, s.status, s.autograde, s.grade, s.gradeoverridden, s.feedback,
                   s.timemodified, s.timefinalised";

        $this->set_sql($fields, $from, $where, $params);

        // Group membership map (one query).
        $this->groupmap = [];
        $sql = "SELECT gm.userid, g.name
                  FROM {groups_members} gm
                  JOIN {groups} g ON g.id = gm.groupid
                 WHERE g.courseid = :courseid
              ORDER BY g.name";
        foreach ($DB->get_records_sql($sql, ['courseid' => $cm->course]) as $record) {
            $this->groupmap[(int) $record->userid][] = format_string($record->name);
        }

        $columns = ['fullname', 'groups', 'status', 'timemodified', 'timefinalised',
            'autograde', 'grade', 'feedback', 'actions'];
        $headers = [
            get_string('fullname'),
            get_string('group'),
            get_string('submission', 'mod_dictogloss'),
            get_string('lastmodified', 'mod_dictogloss'),
            get_string('handedintime', 'mod_dictogloss'),
            get_string('autograde', 'mod_dictogloss'),
            get_string('grade', 'mod_dictogloss'),
            get_string('feedback', 'mod_dictogloss'),
            '',
        ];
        if ($bulk) {
            array_unshift($columns, 'select');
            array_unshift($headers, \html_writer::checkbox('selectall', 1, false, '', [
                'id' => 'mod-dictogloss-results-selectall',
                'data-dictogloss-selectall' => '.mod-dictogloss-select',
                'aria-label' => get_string('selectall', 'mod_dictogloss'),
            ]));
        }
        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->define_baseurl(new \moodle_url('/mod/dictogloss/results.php',
            ['id' => $cm->id, 'group' => $groupid]));
        $this->sortable(true, 'lastname');
        $this->collapsible(false);
        $this->set_attribute('class', 'generaltable');
        foreach (['select', 'fullname', 'groups', 'feedback', 'actions'] as $nosort) {
            $this->no_sorting($nosort);
        }
    }

    /**
     * Selection checkbox column.
     *
     * @param \stdClass $row The row.
     * @return string
     */
    public function col_select($row) {
        if (!$row->submissionid) {
            return '';
        }
        return \html_writer::checkbox('selected[]', $row->id, false, '', [
            'class' => 'mod-dictogloss-select',
            'aria-label' => format_string($row->firstname . ' ' . $row->lastname),
        ]);
    }

    /**
     * Full name column.
     *
     * @param \stdClass $row The row.
     * @return string
     */
    public function col_fullname($row) {
        $user = new \stdClass();
        foreach (['id', 'firstname', 'lastname', 'firstnamephonetic', 'lastnamephonetic',
            'middlename', 'alternatename', 'picture', 'imagealt'] as $field) {
            $user->$field = $row->$field;
        }
        $user->email = '';
        return fullname($user);
    }

    /**
     * Group column.
     *
     * @param \stdClass $row The row.
     * @return string
     */
    public function col_groups($row) {
        $names = $this->groupmap[(int) $row->id] ?? [];
        if (!$names) {
            return '';
        }
        $badges = [];
        foreach ($names as $name) {
            $badges[] = \html_writer::span($name, 'badge text-bg-light border');
        }
        return implode(' ', $badges);
    }

    /**
     * Status column.
     *
     * @param \stdClass $row The row.
     * @return string
     */
    public function col_status($row) {
        $status = $row->submissionid ? (int) $row->status : activity::STATUS_NOTSTARTED;
        $classes = [
            activity::STATUS_NOTSTARTED => 'text-bg-secondary',
            activity::STATUS_KEYWORDS => 'text-bg-info',
            activity::STATUS_RECONSTRUCTION => 'text-bg-info',
            activity::STATUS_SUBMITTED => 'text-bg-warning',
            activity::STATUS_FINALISED => 'text-bg-success',
        ];
        $class = $classes[$status] ?? 'text-bg-secondary';
        return \html_writer::span(\mod_dictogloss\local\status::name($status), 'badge ' . $class);
    }

    /**
     * Last modified column.
     *
     * @param \stdClass $row The row.
     * @return string
     */
    public function col_timemodified($row) {
        return ($row->submissionid && $row->timemodified) ? userdate($row->timemodified) : '-';
    }

    /**
     * Handed-in time column.
     *
     * @param \stdClass $row The row.
     * @return string
     */
    public function col_timefinalised($row) {
        return $row->timefinalised ? userdate($row->timefinalised) : '-';
    }

    /**
     * Automatic grade column.
     *
     * @param \stdClass $row The row.
     * @return string
     */
    public function col_autograde($row) {
        return ($row->submissionid && $row->autograde !== null) ? format_float($row->autograde, 1) . '%' : '-';
    }

    /**
     * Grade column.
     *
     * @param \stdClass $row The row.
     * @return string
     */
    public function col_grade($row) {
        if (!$row->submissionid || $row->grade === null) {
            return '-';
        }
        $grade = format_float($row->grade, 2);
        if (!empty($row->gradeoverridden)) {
            $grade .= ' ' . \html_writer::span(get_string('gradeoverridden', 'mod_dictogloss'), 'badge text-bg-info');
        }
        return $grade;
    }

    /**
     * Feedback column.
     *
     * @param \stdClass $row The row.
     * @return string
     */
    public function col_feedback($row) {
        return ($row->submissionid && trim((string) $row->feedback) !== '') ? get_string('yes') : get_string('no');
    }

    /**
     * Actions column.
     *
     * @param \stdClass $row The row.
     * @return string
     */
    public function col_actions($row) {
        if (!$row->submissionid) {
            return '';
        }
        return \html_writer::link(
            new \moodle_url('/mod/dictogloss/view.php', ['id' => $this->cm->id, 'userid' => $row->id]),
            get_string('viewcontribution', 'mod_dictogloss'),
            ['class' => 'btn btn-sm btn-outline-secondary']);
    }

    /**
     * Open the bulk-action form around the table.
     *
     * @return void
     */
    public function wrap_html_start() {
        echo \html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new \moodle_url('/mod/dictogloss/results.php'))->out_omit_querystring(),
            'id' => 'mod-dictogloss-results-form',
        ]);
        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $this->cm->id]);
        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'group', 'value' => $this->groupfilter]);
        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    }

    /**
     * Close the bulk-action form and add the bulk action buttons.
     *
     * @return void
     */
    public function wrap_html_finish() {
        echo \html_writer::start_div('mt-3 d-flex gap-2');
        echo \html_writer::tag('button', get_string('reopenselected', 'mod_dictogloss'), [
            'type' => 'submit',
            'name' => 'action',
            'value' => 'reopenselected',
            'class' => 'btn btn-outline-warning',
            'title' => get_string('reopenselected_help', 'mod_dictogloss'),
        ]);
        echo \html_writer::tag('button', get_string('deleteselected', 'mod_dictogloss'), [
            'type' => 'submit',
            'name' => 'action',
            'value' => 'deleteselected',
            'class' => 'btn btn-outline-danger',
            'title' => get_string('deleteselected_confirm', 'mod_dictogloss'),
            'data-dictogloss-confirm' => get_string('deleteselected_confirm', 'mod_dictogloss'),
        ]);
        echo \html_writer::end_div();
        echo \html_writer::end_tag('form');
    }
}
