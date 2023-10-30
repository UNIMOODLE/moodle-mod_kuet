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

namespace mod_jqshow\helpers;

use coding_exception;
use dml_exception;
use mod_jqshow\jqshow;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions as sessionsmodel;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use stdClass;

class sessions {

    /**
     * @param jqshow_sessions $session
     * @param int $cmid
     * @param bool $managesessions
     * @param bool $initsession
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_data_session(
        jqshow_sessions $session,
        int $cmid,
        bool $managesessions,
        bool $initsession
    ): stdClass {
        $ds = new stdClass();
        $ds->name = $session->get('name');
        $ds->sessionid = $session->get('id');
        $ds->sessionmode = get_string($session->get('sessionmode'), 'mod_jqshow');
        $jqshow = new jqshow($cmid);
        $questions = new questions($jqshow->get_jqshow()->id, $cmid, $session->get('id'));
        $ds->questions_number = $questions->get_num_questions();
        switch ($session->get('timemode')) {
            case sessionsmodel::NO_TIME:
            default:
                $ds->timemode = get_string('no_time', 'mod_jqshow');
                $ds->sessiontime = '-';
                $ds->timeperquestion = '-';
                break;
            case sessionsmodel::SESSION_TIME:
                $ds->timemode = get_string('session_time', 'mod_jqshow');
                $ds->sessiontime = userdate($session->get('sessiontime'), '%Mm %Ss');
                $ds->timeperquestion =
                    $ds->questions_number !== 0 ? userdate(($session->get('sessiontime') / $ds->questions_number), '%ss') : 0;
                break;
            case sessionsmodel::QUESTION_TIME:
                $ds->timemode = get_string('question_time', 'mod_jqshow');
                $ds->sessiontime = userdate($questions->get_sum_questions_times(), '%Mm %Ss');
                $ds->timeperquestion = userdate($session->get('questiontime'), '%ss');
                break;
        }
        $ds->managesessions = $managesessions;
        $ds->initsession = $initsession;
        $ds->initsessionurl =
            (new moodle_url('/mod/jqshow/session.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        $ds->viewreporturl =
            (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        $ds->editsessionurl =
            (new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        $ds->status = $session->get('status');
        $ds->issessionstarted = $ds->status === sessionsmodel::SESSION_STARTED;
        $ds->sessioncreating = $ds->status === sessionsmodel::SESSION_CREATING;
        if ($ds->issessionstarted) {
            $ds->startedssionurl =
                (new moodle_url('/mod/jqshow/session.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        }
        $ds->stringsession =
            $ds->status === sessionsmodel::SESSION_STARTED ?
                get_string('sessionstarted', 'mod_jqshow') : get_string('init_session', 'mod_jqshow');
        $ds->date = '';
        $ds->enddate = '';
        $ds->automaticstart = false;
        if ($session->get('automaticstart') === 1) {
            $ds->automaticstart = true;
            $startdate = $session->get('startdate');
            if ($startdate !== 0) {
                $ds->startdate = $startdate;
                $startdate = userdate($startdate, get_string('strftimedatetimeshort', 'core_langconfig'));
                $ds->date = $startdate;
            }
            $enddate = $session->get('enddate');
            if ($enddate !== 0) {
                $ds->enddate = $enddate;
                $enddate = userdate($enddate, get_string('strftimedatetimeshort', 'core_langconfig'));
                $ds->date .= $ds->status === sessionsmodel::SESSION_FINISHED ? '' : ' - ' . $enddate;
            }
            if ($ds->issessionstarted !== true && $ds->startdate < time() && $ds->enddate > time()) {
                $ds->haswarning = true;
            }
        }
        $ds->noquestions = $ds->questions_number === 0;
        if ($ds->date !== '' || $ds->issessionstarted === true || $ds->sessioncreating === true || $ds->noquestions === true) {
            $ds->initsession = false;
        }
        if ($ds->status === sessionsmodel::SESSION_FINISHED) {
            $ds->finishingdate = userdate($session->get('enddate'), get_string('strftimedatetimeshort', 'core_langconfig'));
            $ds->enddate = $session->get('enddate');
            $ds->date = userdate($session->get('startdate'), get_string('strftimedatetimeshort', 'core_langconfig'));
        }
        return $ds;
    }
}
