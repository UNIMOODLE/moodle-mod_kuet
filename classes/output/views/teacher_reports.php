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

namespace mod_jqshow\output\views;

use coding_exception;
use context_module;
use dml_exception;
use JsonException;
use mod_jqshow\helpers\reports;
use mod_jqshow\jqshow;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use user_picture;

class teacher_reports implements renderable, templatable {

    public int $jqshowid;
    public int $cmid;
    public int $sid;
    public int $userid;
    public int $jqid;

    /**
     * @param int $cmid
     * @param int $jqshowid
     * @param int $sid
     * @param int $userid
     * @param int $jqid
     */
    public function __construct(int $cmid, int $jqshowid, int $sid, int $userid, int $jqid) {
        $this->jqshowid = $jqshowid;
        $this->cmid = $cmid;
        $this->sid = $sid;
        $this->userid = $userid;
        $this->jqid = $jqid;
    }

    /**
     * @param renderer_base $output
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        // TODO refactor.
        global $DB, $PAGE, $USER;
        $jqshow = new jqshow($this->cmid);
        $data = new stdClass();
        $data->jqshowid = $this->jqshowid;
        $data->cmid = $this->cmid;
        $cmcontext = context_module::instance($this->cmid);
        if ($this->sid === 0) { // All sessions.
            $data->allreports = true;
            $data->endedsessions = $jqshow->get_completed_sessions();
        } else if ($this->userid === 0 && $this->jqid === 0) { // One session.
            $data->sessionreport = true;
            $session = new jqshow_sessions($this->sid);
            $mode = $session->get('sessionmode');
            if ($mode !== sessions::INACTIVE_PROGRAMMED || $mode !== sessions::INACTIVE_MANUAL) {
                $data->showfinalranking = true;
            }
            $data->sessionname = $session->get('name');
            $data->config = sessions::get_session_config($this->sid);
            $data->sessionquestions = reports::get_questions_data_for_teacher_report($this->jqshowid, $this->cmid, $this->sid);
            if ($session->get('anonymousanswer') === 1) {
                if (has_capability('mod/jqshow:viewanonymousanswers', $cmcontext, $USER)) {
                    $data->hasranking = true;
                    $data->rankingusers = reports::get_ranking_for_teacher_report($this->cmid, $this->sid);
                }
            } else {
                $data->hasranking = true;
                $data->rankingusers = reports::get_ranking_for_teacher_report($this->cmid, $this->sid);
            }
        } else if ($this->userid === 0 && $this->jqid !== 0) { // Question report.
            $data = reports::get_question_report($this->cmid, $this->sid, $this->jqid);
        } else { // User report.
            $session = new jqshow_sessions($this->sid);
            if ($session->get('anonymousanswer') === 1 && !has_capability('mod/jqshow:viewanonymousanswers', $cmcontext, $USER)) {
                throw new moodle_exception('anonymousanswers', 'mod_jqshow', '',
                    [], get_string('anonymousanswers', 'mod_jqshow'));
            }
            $data->userreport = true;
            $data->sessionname = $session->get('name');
            $userdata = $DB->get_record('user', ['id' => $this->userid]);
            $userpicture = new user_picture($userdata);
            $userpicture->size = 1;
            $data->userimage = $userpicture->get_url($PAGE)->out(false);
            $data->userfullname = $userdata->firstname . ' ' . $userdata->lastname;
            $data->userprofileurl = (new moodle_url('/user/profile.php', ['id' => $this->userid]))->out(false);
            $data->backurl = (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $this->cmid, 'sid' => $this->sid]))->out(false);
            $data->config = sessions::get_session_config($this->sid);
            $data->sessionquestions =
                reports::get_questions_data_for_user_report($this->jqshowid, $this->cmid, $this->sid, $this->userid);
            $data->numquestions = count($data->sessionquestions);
            $data->noresponse = 0;
            $data->success = 0;
            $data->failures = 0;
            $data->noevaluable = 0;
            foreach ($data->sessionquestions as $question) {
                switch ($question->response) {
                    case 'failure':
                        $data->failures++;
                        break;
                    case 'success':
                        $data->success++;
                        break;
                    case 'noresponse':
                        $data->noresponse++;
                        break;
                    case 'noevaluable':
                        $data->noevaluable++;
                        break;
                    default:
                        break;
                }
            }
        }

        return $data;
    }
}
