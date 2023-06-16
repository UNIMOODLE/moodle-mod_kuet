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
use JsonException;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use stdClass;
use user_picture;

class reports {

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @param int $sid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_questions_data_for_teacher_report(int $jqshowid, int $cmid, int $sid): array {
        global $DB;
        $session = new jqshow_sessions($sid);
        $questions = (new questions($jqshowid, $cmid, $sid))->get_list();
        $questionsdata = [];
        $jqshow = new jqshow($jqshowid);
        $users = enrol_get_course_users($jqshow->get('course'), true);
        foreach ($questions as $question) {
            $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
            $data = new stdClass();
            $data->questionnid = $question->get('id');
            $data->position = $question->get('qorder');
            $data->name = $questiondb->name;
            $data->type = $question->get('qtype');
            $data->success = jqshow_questions_responses::count_records([
                'jqshow' => $jqshowid,
                'session' => $sid,
                'jqid' => $question->get('id'),
                'result' => questions::SUCCESS
            ]);
            $data->failures = jqshow_questions_responses::count_records([
                'jqshow' => $jqshowid,
                'session' => $sid,
                'jqid' => $question->get('id'),
                'result' => questions::FAILURE
            ]);
            $data->noresponse = count($users) - ($data->success + $data->failures);
            $data->time = self::get_time_string($session, $question);
            $questionsdata[] = $data;
        }
        return $questionsdata;
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_ranking_for_teacher_report(int $cmid, int $sid): array {
        global $DB, $PAGE;
        $results = sessions::get_session_results($sid, $cmid);
        foreach ($results as $user) {
            $userdata = $DB->get_record('user', ['id' => $user->userid]);
            if ($userdata !== false) {
                $userpicture = new user_picture($userdata);
                $userpicture->size = 1;
                $user->userimage = $userpicture->get_url($PAGE)->out(false);
                $user->userprofileurl = (new moodle_url('/user/profile.php', ['id' => $user->userid]))->out(false);
                $user->viewreporturl = (new moodle_url('/mod/jqshow/reports.php',
                    ['cmid' => $cmid, 'sid' => $sid, 'userid' => $user->userid]))->out(false);
            }
        }
        return $results;
    }

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @param int $sid
     * @param int $userid
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_questions_data_for_user_report(int $jqshowid, int $cmid, int $sid, int $userid): array {
        global $DB;
        $session = new jqshow_sessions($sid);
        $questions = (new questions($jqshowid, $cmid, $sid))->get_list();
        $questionsdata = [];
        foreach ($questions as $question) {
            $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
            $response = jqshow_questions_responses::get_record([
                'jqshow' => $jqshowid,
                'session' => $sid,
                'jqid' => $question->get('id'),
                'userid' => $userid,
            ]);
            $data = new stdClass();
            $data->questionnid = $question->get('id');
            $data->position = $question->get('qorder');
            $data->name = $questiondb->name;
            $data->type = $question->get('qtype');
            $questiontimestr = self::get_time_string($session, $question);
            if ($response === false) {
                $data->response = 'noresponse';
                $data->responsestr = get_string('noresponse', 'mod_jqshow');
                $data->time = $questiontimestr . ' / ' . $questiontimestr; // Or 0?
            } else {
                switch ($response->get('result')) {
                    case questions::FAILURE:
                        $data->response = 'failure';
                        $data->responsestr = get_string('failure', 'mod_jqshow');
                        $data->time = self::get_user_time_in_question($session, $question, $response);
                        break;
                    case questions::SUCCESS:
                        $data->response = 'success';
                        $data->responsestr = get_string('success', 'mod_jqshow');
                        $data->time = self::get_user_time_in_question($session, $question, $response);
                        break;
                    case questions::NORESPONSE:
                    default:
                        $data->response = 'noresponse';
                        $data->responsestr = get_string('noresponse', 'mod_jqshow');
                        $data->time = self::get_user_time_in_question($session, $question, $response);
                        break;
                    case questions::NOTEVALUABLE:
                        $data->response = 'noevaluable';
                        $data->responsestr = get_string('noevaluable', 'mod_jqshow');
                        $data->time = self::get_user_time_in_question($session, $question, $response);
                        break;
                    case questions::INVALID:
                        $data->response = 'invalid';
                        $data->responsestr = get_string('invalid', 'mod_jqshow');
                        $data->time = self::get_user_time_in_question($session, $question, $response);
                        break;
                }
            }
            $data->cmid = $cmid;
            $data->sessionid = $sid;
            $data->userid = $userid;
            $questionsdata[] = $data;
        }
        return $questionsdata;
    }

    /**
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @param jqshow_questions_responses $response
     * @return string
     * @throws JsonException
     * @throws coding_exception
     */
    public static function get_user_time_in_question(
        jqshow_sessions $session, jqshow_questions $question, jqshow_questions_responses $response
    ) {
        $responsedata = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
        $usertimelast = $responsedata->timeleft;
        switch ($session->get('timemode')) {
            case sessions::NO_TIME:
            default:
                $questiontime = ($question->get('timelimit') > 0) ? $question->get('timelimit') : 0;
                break;
            case sessions::SESSION_TIME:
                $numquestion = jqshow_questions::count_records(
                    ['sessionid' => $session->get('id'), 'jqshowid' => $session->get('jqshowid')]
                );
                $questiontime = round((int)$session->get('sessiontime') / $numquestion);
                break;
            case sessions::QUESTION_TIME:
                $questiontime = ($question->get('timelimit') > 0) ? $question->get('timelimit') : $session->get('questiontime');
        }
        $usertime = ($questiontime - $usertimelast) !== 0 ? ($questiontime - $usertimelast) : 1;
        return $usertime . 's / ' . $questiontime . 's';
    }

    /**
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @return string
     * @throws coding_exception
     */
    public static function get_time_string(jqshow_sessions $session, jqshow_questions $question): string {
        switch ($session->get('timemode')) {
            case sessions::NO_TIME:
            default:
                return ($question->get('timelimit') > 0) ? $question->get('timelimit') . 's' : '-';
            case sessions::SESSION_TIME:
                $numquestion = jqshow_questions::count_records(
                    ['sessionid' => $session->get('id'), 'jqshowid' => $session->get('jqshowid')]
                );
                $timeperquestion = round((int)$session->get('sessiontime') / $numquestion);
                return ($timeperquestion > 0) ? $timeperquestion . 's' : '-';
            case sessions::QUESTION_TIME:
                return ($question->get('timelimit') > 0) ? $question->get('timelimit') . 's' : $session->get('questiontime') . 's';
        }
    }

}
