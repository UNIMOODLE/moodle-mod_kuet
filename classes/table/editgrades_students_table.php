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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos..

/**
 * Manual grade editing: students table (KUETEDUCAM-73)
 *
 * @package    mod_kuet
 * @author     3IPUNT <contacte@tresipunt.com>
 * @copyright  3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\table;

use coding_exception;
use context_module;
use core_user\fields;
use html_writer;
use mod_kuet\api\grade;
use moodle_url;
use table_sql;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Paginated, name-filterable list of students with their session grade.
 */
class editgrades_students_table extends table_sql {
    /**
     * @var int course module id
     */
    private int $cmid;

    /**
     * @var int session id
     */
    private int $sid;

    /**
     * Constructor.
     *
     * @param string $uniqueid
     * @param int $cmid
     * @param int $kuetid
     * @param int $sid
     * @param context_module $context
     * @throws coding_exception
     */
    public function __construct(string $uniqueid, int $cmid, int $kuetid, int $sid, context_module $context) {
        global $DB;
        parent::__construct($uniqueid);
        $this->cmid = $cmid;
        $this->sid = $sid;

        $this->define_columns(['fullname', 'grade', 'actions']);
        $this->define_headers([
            get_string('fullname'),
            get_string('editgrades_grade', 'mod_kuet'),
            get_string('session_actions', 'mod_kuet'),
        ]);
        $this->sortable(true, 'lastname', SORT_ASC);
        $this->no_sorting('actions');
        $this->collapsible(false);
        $this->initialbars(true);

        // Active enrolled users, excluding those who can run sessions (teachers).
        [$esql, $eparams] = get_enrolled_sql($context, '', 0, true);
        $ufields = fields::for_name()->get_sql('u', false, '', '', false);
        $selectfullname = $DB->sql_fullname('u.firstname', 'u.lastname');

        // Normalise the name-fields select (it may or may not carry a leading comma).
        $namefields = trim(ltrim(trim($ufields->selects), ','));
        $fields = 'u.id, ' . $namefields . ', ' . $selectfullname . ' AS fullname, sg.grade';
        $from = '{user} u
                  JOIN (' . $esql . ') je ON je.id = u.id
             LEFT JOIN {kuet_sessions_grades} sg ON sg.userid = u.id AND sg.kuet = :kuetid AND sg.session = :ksessionid';
        $where = 'u.deleted = 0';
        $params = $eparams + ['kuetid' => $kuetid, 'ksessionid' => $sid] + $ufields->params;

        $teachers = get_users_by_capability($context, 'mod/kuet:startsession', 'u.id');
        if (!empty($teachers)) {
            [$notinsql, $notinparams] = $DB->get_in_or_equal(array_keys($teachers), SQL_PARAMS_NAMED, 'tch', false);
            $where .= ' AND u.id ' . $notinsql;
            $params += $notinparams;
        }

        $this->set_sql($fields, $from, $where, $params);
    }

    /**
     * Full name column.
     *
     * @param object $row
     * @return string
     */
    public function col_fullname($row): string {
        return fullname($row);
    }

    /**
     * Session grade column.
     *
     * @param object $row
     * @return string
     * @throws \dml_exception
     */
    public function col_grade($row): string {
        return $row->grade !== null ? (string) grade::get_rounded_mark((float) $row->grade) : '-';
    }

    /**
     * Actions column: link to the user's editable question list.
     *
     * @param object $row
     * @return string
     * @throws coding_exception
     */
    public function col_actions($row): string {
        $url = new moodle_url(
            '/mod/kuet/editgrades.php',
            ['cmid' => $this->cmid, 'sid' => $this->sid, 'userid' => $row->id]
        );
        $label = get_string('editgrades', 'mod_kuet');
        return html_writer::link(
            $url,
            html_writer::tag('i', '', ['class' => 'icon fa fa-pencil m-0', 'aria-hidden' => 'true']) . $label,
            ['title' => $label]
        );
    }
}
