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
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jqshow\models;

use coding_exception;
use context_course;
use dml_exception;
use mod_jqshow\jqshow;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use stdClass;

class teacher extends user {

    /**
     * @param $cmid
     * @return Object
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export($cmid) : Object {
        $data = new stdClass();
        // Depending on parameter  the data returned is different.
        $tab = optional_param('tab', 'sessions', PARAM_RAW);
        switch ($tab) {
            case 'sessions':
                $data = $this->export_sessions($cmid);
                break;
            case 'reports':
                $data = $this->export_reports();
                break;
            default:
                break;
        }
        return $data;
    }

    /**
     * @param $cmid
     * @return Object
     * @throws dml_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export_sessions($cmid) : Object {
        global $COURSE;
        $jqshow = new jqshow($cmid);
        $actives = [];
        $inactives = [];
        $sessions = $jqshow->get_sessions();
        $coursecontext = context_course::instance($COURSE->id);
        $managesessions = has_capability('mod/jqshow:managesessions', $coursecontext);
        $initsession = has_capability('mod/jqshow:startsession', $coursecontext);
        foreach ($sessions as $session) {
            $ds = $this->get_data_session($session, $cmid, $managesessions, $initsession);
            if ((int)$session->get('status') !== 0) {
                $actives[] = $ds;
            } else {
                $inactives[] = $ds;
            }
        }
        $data = new stdClass();
        $data->activesessions = $actives;
        $data->endedsessions = $inactives;
        $data->courseid = $jqshow->course->id;
        $data->cmid = $cmid;
        $data->createsessionurl = (new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $cmid, 'page' => 1]))->out(false);
        $data->hasactivesession = jqshow_sessions::get_active_session_id(($jqshow->get_jqshow())->id) !== 0;
        return $data;
    }

    /**
     * @param jqshow_sessions $session
     * @param int $cmid
     * @param bool $managesessions
     * @param bool $initsession
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function get_data_session(jqshow_sessions $session, int $cmid, bool $managesessions, bool $initsession): stdClass {
        $ds = new stdClass();
        $ds->name = $session->get('name');
        $ds->sessionid = $session->get('id');
        $ds->sessionmode = get_string($session->get('sessionmode'), 'mod_jqshow');
        $jqshow = new jqshow($cmid);
        $questions = new questions($jqshow->get_jqshow()->id, $cmid, $session->get('id'));
        if ($session->get('timemode') === sessions::NO_TIME) {
            $ds->timemode = get_string('no_time', 'mod_jqshow');
            $ds->sessiontime = '';
        } else if ($session->get('timemode') === sessions::SESSION_TIME) {
            $ds->timemode = get_string('session_time', 'mod_jqshow');
            $ds->sessiontime = userdate($session->get('sessiontime'), '%Mm %Ss');
        } else if ($session->get('timemode') === sessions::QUESTION_TIME) {
            $ds->timemode = get_string('question_time', 'mod_jqshow');
            $ds->sessiontime = userdate($questions->get_sum_questions_times(), '%Mm %Ss');
        }
        $ds->questions_number = $questions->get_num_questions();
        $ds->managesessions = $managesessions;
        $ds->initsession = $initsession;
        if ($questions->get_num_questions() === 0) {
            $ds->initsession = false;
        }
        $ds->initsessionurl =
            (new moodle_url('/mod/jqshow/session.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        $ds->viewreporturl =
            (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        $ds->editsessionurl =
            (new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        $ds->date = '';
        $ds->status = $session->get('status');
        $ds->issessionstarted = $ds->status === 2;
        if ($ds->issessionstarted) {
            $ds->startedssionurl =
                (new moodle_url('/mod/jqshow/session.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        }
        $ds->stringsession =
            $ds->status === 2 ? get_string('sessionstarted', 'mod_jqshow') : get_string('init_session', 'mod_jqshow');
        // TODO detect active session, rename and lock them all.
        $startdate = $session->get('startdate');
        if ($startdate !== 0) {
            $startdate = userdate($startdate, get_string('strftimedatetimeshort', 'core_langconfig'));
            $ds->date = $startdate;
        }
        $enddate = $session->get('enddate');
        if ($enddate !== 0) {
            $enddate = userdate($enddate, get_string('strftimedatetimeshort', 'core_langconfig'));
            $ds->date .= ' - ' . $enddate;
        }

        return $ds;
    }

    /**
     * @return Object
     */
    public function export_reports() : Object {
        // TODO.
        $data = new stdClass();
        return $data;
    }
}
