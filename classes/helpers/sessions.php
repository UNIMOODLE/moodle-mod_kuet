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
 * Sessions helper
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\helpers;

use coding_exception;
use dml_exception;
use mod_kuet\kuet;
use mod_kuet\models\questions;
use mod_kuet\models\sessions as sessionsmodel;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Sessions helper class
 */
class sessions {

    /**
     * Get session data
     *
     * @param kuet_sessions $session
     * @param int $cmid
     * @param bool $managesessions
     * @param bool $initsession
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_data_session(
        kuet_sessions $session,
        int $cmid,
        bool $managesessions,
        bool $initsession
    ): stdClass {
        $ds = new stdClass();
        $ds->name = $session->get('name');
        $ds->sessionid = $session->get('id');
        $ds->sessionmode = get_string($session->get('sessionmode'), 'mod_kuet');
        $kuet = new kuet($cmid);
        $questions = new questions($kuet->get_kuet()->id, $cmid, $session->get('id'));
        $ds->questions_number = $questions->get_num_questions();
        switch ($session->get('timemode')) {
            case sessionsmodel::NO_TIME:
            default:
                $ds->timemode = get_string('no_time', 'mod_kuet');
                $ds->sessiontime = '-';
                $ds->timeperquestion = '-';
                break;
            case sessionsmodel::SESSION_TIME:
                $ds->timemode = get_string('session_time', 'mod_kuet');
                $ds->sessiontime = userdate($session->get('sessiontime'), '%Mm %Ss');
                $ds->timeperquestion =
                    $ds->questions_number !== 0 ? userdate(round($session->get('sessiontime') / $ds->questions_number, 3), '%ss') : 0;
                break;
            case sessionsmodel::QUESTION_TIME:
                $ds->timemode = get_string('question_time', 'mod_kuet');
                $ds->sessiontime = userdate($questions->get_sum_questions_times(), '%Mm %Ss');
                $ds->timeperquestion = userdate($session->get('questiontime'), '%ss');
                break;
        }
        $ds->managesessions = $managesessions;
        $ds->initsession = $initsession;
        $ds->initsessionurl =
            (new moodle_url('/mod/kuet/session.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        $ds->viewreporturl =
            (new moodle_url('/mod/kuet/reports.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        $ds->editsessionurl =
            (new moodle_url('/mod/kuet/sessions.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        $ds->status = $session->get('status');
        $ds->issessionstarted = $ds->status === sessionsmodel::SESSION_STARTED;
        $ds->sessioncreating = $ds->status === sessionsmodel::SESSION_CREATING;
        $ds->haserror = $ds->status === sessionsmodel::SESSION_ERROR;
        if ($ds->issessionstarted) {
            $ds->startedssionurl =
                (new moodle_url('/mod/kuet/session.php', ['cmid' => $cmid, 'sid' => $session->get('id')]))->out(false);
        }
        $ds->stringsession =
            $ds->status === sessionsmodel::SESSION_STARTED ?
                get_string('sessionstarted', 'mod_kuet') : get_string('init_session', 'mod_kuet');
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
        if ($ds->date !== '' || $ds->issessionstarted === true || $ds->sessioncreating === true
            || $ds->noquestions === true || $ds->haserror === true) {
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
