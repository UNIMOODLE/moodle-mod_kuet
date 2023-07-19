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
use context_module;
use dml_exception;
use JsonException;
use mod_jqshow\api\grade;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use pix_icon;
use question_bank;
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
     * @throws moodle_exception
     */
    public static function get_questions_data_for_teacher_report(int $jqshowid, int $cmid, int $sid): array {
        global $DB;
        $session = new jqshow_sessions($sid);
        $questions = (new questions($jqshowid, $cmid, $sid))->get_list();
        $questionsdata = [];
        $jqshow = new jqshow($jqshowid);
        $users = enrol_get_course_users($jqshow->get('course'), true);
        $cmcontext = context_module::instance($cmid);
        foreach ($users as $key => $user) {
            if (has_capability('mod/jqshow:startsession', $cmcontext, $user)) {
                unset($users[$key]);
            }
        }
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
            $data->partyally = jqshow_questions_responses::count_records([
                'jqshow' => $jqshowid,
                'session' => $sid,
                'jqid' => $question->get('id'),
                'result' => questions::PARTIALLY
            ]);
            $data->noresponse = count($users) - ($data->success + $data->failures + $data->partyally);
            $data->time = self::get_time_string($session, $question);
            $data->questionreporturl = (new moodle_url('/mod/jqshow/reports.php',
                ['cmid' => $cmid, 'sid' => $sid, 'jqid' => $question->get('id')]
            ))->out(false);
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
                        break;
                    case questions::SUCCESS:
                        $data->response = 'success';
                        $data->responsestr = get_string('success', 'mod_jqshow');
                        break;
                    case questions::PARTIALLY:
                        $data->response = 'partially';
                        $data->responsestr = get_string('partially_correct', 'mod_jqshow');
                        break;
                    case questions::NORESPONSE:
                    default:
                        $data->response = 'noresponse';
                        $data->responsestr = get_string('noresponse', 'mod_jqshow');
                        break;
                    case questions::NOTEVALUABLE:
                        $data->response = 'noevaluable';
                        $data->responsestr = get_string('noevaluable', 'mod_jqshow');
                        break;
                    case questions::INVALID:
                        $data->response = 'invalid';
                        $data->responsestr = get_string('invalid', 'mod_jqshow');
                        break;
                }
                $data->time = self::get_user_time_in_question($session, $question, $response);
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
    ): string {
        $responsedata = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
        $usertimelast = $responsedata->timeleft;
        switch ($session->get('timemode')) {
            case sessions::NO_TIME:
            default:
                $timestring = '-';
                break;
            case sessions::SESSION_TIME:
                $numquestion = jqshow_questions::count_records(
                    ['sessionid' => $session->get('id'), 'jqshowid' => $session->get('jqshowid')]
                );
                $questiontime = round((int)$session->get('sessiontime') / $numquestion);
                $usertime = ($questiontime - $usertimelast) !== 0 ? ($questiontime - $usertimelast) : 1;
                $timestring = $usertime . 's / ' . $questiontime . 's';
                break;
            case sessions::QUESTION_TIME:
                $questiontime = ($question->get('timelimit') > 0) ? $question->get('timelimit') : $session->get('questiontime');
                $usertime = ($questiontime - $usertimelast) !== 0 ? ($questiontime - $usertimelast) : 1;
                $timestring = $usertime . 's / ' . $questiontime . 's';
                break;
        }
        return $timestring;
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

    /**
     * @param int $cmid
     * @param int $sid
     * @param int $jqid
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_question_report(int $cmid, int $sid, int $jqid): stdClass {
        global $DB, $USER;
        $session = new jqshow_sessions($sid);
        $question = new jqshow_questions($jqid);
        $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
        $data = new stdClass();
        $data->questionreport = true;
        $data->sessionid = $sid;
        $data->jqid = $jqid;
        $data->cmid = $cmid;
        $data->jqshowid = $question->get('jqshowid');
        $data->questionnid = $question->get('id');
        $data->position = $question->get('qorder');
        $data->type = $question->get('qtype');
        $questiondata = question_bank::load_question($questiondb->id);
        $data->questiontext = questions::get_text(
            $cmid, $questiondata->questiontext, $questiondata->questiontextformat, $questiondata->id, $questiondata, 'questiontext'
        );
        $data->backurl = (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $cmid, 'sid' => $sid]))->out(false);
        $answers = [];
        $correctanswers = [];
        switch ($data->type) {
            // TODO recfactor.
            case 'multichoice':
                foreach ($questiondata->answers as $key => $answer) {
                    $answers[$key]['answertext'] = $answer->answer; // TODO get text with images questions::get_text.
                    $answers[$key]['answerid'] = $key;
                    if ($answer->fraction === '0.0000000' || strpos($answer->fraction, '-') === 0) {
                        $answers[$key]['result'] = 'incorrect';
                        $answers[$key]['resultstr'] = get_string('incorrect', 'mod_jqshow');
                        $answers[$key]['fraction'] = round($answer->fraction, 2);
                        $icon = new pix_icon('i/incorrect', get_string('incorrect', 'mod_jqshow'), 'mod_jqshow', [
                            'class' => 'icon',
                            'title' => get_string('incorrect', 'mod_jqshow')
                        ]);
                        $usersicon = new pix_icon('i/incorrect_users', '', 'mod_jqshow', [
                            'class' => 'icon',
                            'title' => ''
                        ]);
                    } else if ($answer->fraction === '1.0000000') {
                        $answers[$key]['result'] = 'correct';
                        $answers[$key]['resultstr'] = get_string('correct', 'mod_jqshow');
                        $answers[$key]['fraction'] = '1';
                        $icon = new pix_icon('i/correct', get_string('correct', 'mod_jqshow'), 'mod_jqshow', [
                            'class' => 'icon',
                            'title' => get_string('correct', 'mod_jqshow')
                        ]);
                        $usersicon = new pix_icon('i/correct_users', '', 'mod_jqshow', [
                            'class' => 'icon',
                            'title' => ''
                        ]);
                    } else {
                        $answers[$key]['result'] = 'partially';
                        $answers[$key]['resultstr'] = get_string('partially_correct', 'mod_jqshow');
                        $answers[$key]['fraction'] = round($answer->fraction, 2);
                        $icon = new pix_icon('i/correct', get_string('partially_correct', 'mod_jqshow'), 'mod_jqshow', [
                            'class' => 'icon',
                            'title' => get_string('partially_correct', 'mod_jqshow')
                        ]);
                        $usersicon = new pix_icon('i/correct_users', '', 'mod_jqshow', [
                            'class' => 'icon',
                            'title' => ''
                        ]);
                    }
                    $answers[$key]['resulticon'] = $icon->export_for_pix();
                    $answers[$key]['usersicon'] = $usersicon->export_for_pix();
                    $answers[$key]['numticked'] = 0;
                    if ($answer->fraction !== '0.0000000') { // Answers with punctuation, even if negative.
                        $correctanswers[$key]['response'] = $answer->answer;
                        $correctanswers[$key]['score'] = grade::get_rounded_mark($questiondata->defaultmark * $answer->fraction);
                    }
                }
                $responses = jqshow_questions_responses::get_question_responses($sid, $data->jqshowid, $jqid);
                foreach ($responses as $response) {
                    $other = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
                    if ($other->answerids !== '' && $other->answerids !== '0') { // TODO prepare for multianswer.
                        $arrayanswerids = explode(',', $other->answerids);
                        foreach ($arrayanswerids as $arrayanswerid) {
                            $answers[$arrayanswerid]['numticked']++;
                        }
                    }
                    // TODO obtain the average time to respond to each option ticked. ???
                }
                $data->answers = array_values($answers);
                break;
            default:
                break;
        }
        $data->correctanswers = array_values($correctanswers);
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $cmcontext = context_module::instance($cmid);
        $users = enrol_get_course_users($course->id, true);
        foreach ($users as $key => $user) {
            if (has_capability('mod/jqshow:startsession', $cmcontext, $user)) {
                unset($users[$key]);
            }
        }
        $data->numusers = count($users);
        $data->numcorrect = jqshow_questions_responses::count_records(
            ['jqshow' => $data->jqshowid, 'session' => $sid, 'jqid' => $jqid, 'result' => questions::SUCCESS]
        );
        $data->numincorrect = jqshow_questions_responses::count_records(
            ['jqshow' => $data->jqshowid, 'session' => $sid, 'jqid' => $jqid, 'result' => questions::FAILURE]
        );
        $data->numpartial = jqshow_questions_responses::count_records(
            ['jqshow' => $data->jqshowid, 'session' => $sid, 'jqid' => $jqid, 'result' => questions::PARTIALLY]
        );
        $data->numnoresponse = $data->numusers - ($data->numcorrect + $data->numincorrect + $data->numpartial);
        $data->percent_correct = round(($data->numcorrect / $data->numusers) * 100, 2);
        $data->percent_incorrect = round(($data->numincorrect / $data->numusers) * 100, 2);
        $data->percent_partially = round(($data->numpartial / $data->numusers) * 100, 2);
        $data->percent_noresponse = round(($data->numnoresponse / $data->numusers) * 100, 2);
        if ($session->get('anonymousanswer') === 1) {
            if (has_capability('mod/jqshow:viewanonymousanswers', $cmcontext, $USER)) {
                $data->hasranking = true;
                $data->questionranking =
                    self::get_ranking_for_question($users, $data->answers, $session, $question, $cmid, $sid, $jqid);
            }
        } else {
            $data->hasranking = true;
            $data->questionranking =
                self::get_ranking_for_question($users, $data->answers, $session, $question, $cmid, $sid, $jqid);
        }
        return $data;
    }

    /**
     * @param array $users
     * @param array $answers
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @param int $cmid
     * @param int $sid
     * @param int $jqid
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_ranking_for_question(
        array $users, array $answers, jqshow_sessions $session, jqshow_questions $question, int $cmid, int $sid, int $jqid
    ): array {
        global $DB, $PAGE;
        $context = context_module::instance($cmid);
        $results = [];
        foreach ($users as $user) {
            $userdata = $DB->get_record('user', ['id' => $user->id]);
            if ($userdata !== false && !has_capability('mod/jqshow:startsession', $context, $userdata)) {
                $userpicture = new user_picture($userdata);
                $userpicture->size = 1;
                $user->userimage = $userpicture->get_url($PAGE)->out(false);
                $user->userfullname = $userdata->firstname . ' ' . $userdata->lastname;
                $user->userprofileurl = (new moodle_url('/user/profile.php', ['id' => $userdata->id]))->out(false);
                $user->viewreporturl = (new moodle_url('/mod/jqshow/reports.php',
                    ['cmid' => $cmid, 'sid' => $sid, 'userid' => $userdata->id]))->out(false);
                $response = jqshow_questions_responses::get_record(['userid' => $userdata->id, 'session' => $sid, 'jqid' => $jqid]);
                if ($response !== false) {
                    $other = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
                    switch ($other->type) {
                        case 'multichoice':
                            $arrayresponses = explode(',', $other->answerids);
                            if (count($arrayresponses) === 1) {
                                foreach ($answers as $answer) {
                                    if ((int)$answer['answerid'] === (int)$arrayresponses[0]) {
                                        $user->response = $answer['result'];
                                        $user->responsestr = get_string($answer['result'], 'mod_jqshow');
                                        $user->answertext = $answer['answertext'];
                                        $points = grade::get_response_mark($user->id, $sid, $response);
                                        $spoints = grade::get_session_grade($user->id, $sid, $session->get('jqshowid'));
                                        $user->userpoints = grade::get_rounded_mark($spoints);
                                        $user->score_moment = grade::get_rounded_mark($points);
                                        $user->time = self::get_user_time_in_question($session, $question, $response);
                                    }
                                }
                            } else {
                                $answertext = '';
                                foreach ($answers as $answer) {
                                    foreach ($arrayresponses as $responseid) {
                                        if ((int)$answer['answerid'] === (int)$responseid) {
                                            $answertext .= $answer['answertext'] . '<br>';
                                        }
                                    }
                                }
                                $status = grade::get_status_response_for_multiple_answers($other->questionid, $other->answerids);
                                $user->response = get_string('qstatus_' . $status, 'mod_jqshow');
                                $user->responsestr = get_string($user->response, 'mod_jqshow');
                                $user->answertext = trim($answertext, '<br>');
                                $points = grade::get_response_mark($user->id, $sid, $response);
                                $spoints = grade::get_session_grade($user->id, $sid, $session->get('jqshowid'));
                                $user->userpoints = grade::get_rounded_mark($spoints);
                                $user->score_moment = grade::get_rounded_mark($points);
                                $user->time = self::get_user_time_in_question($session, $question, $response);
                            }
                            break;
                        default:
                            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                                [], get_string('question_nosuitable', 'mod_jqshow'));
                    }
                } else {
                    $questiontimestr = self::get_time_string($session, $question);
                    $user->response = 'noresponse';
                    $user->responsestr = get_string('noresponse', 'mod_jqshow');
                    $user->userpoints = 0;
                    $user->answertext = '-';
                    $user->score_moment = 0;
                    $user->time = $questiontimestr . ' / ' . $questiontimestr; // Or 0?
                }
                $results[] = $user;
            }
        }
        return $results;
    }
}
