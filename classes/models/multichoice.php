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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 *
 * @package    mod_jqshow
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jqshow\models;

use coding_exception;
use context_module;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\api\grade;
use mod_jqshow\api\groupmode;
use mod_jqshow\external\multichoice_external;
use mod_jqshow\helpers\reports;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use pix_icon;
use question_answer;
use question_bank;
use question_definition;
use stdClass;
use mod_jqshow\interfaces\questionType;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot. '/question/type/multichoice/questiontype.php');

class multichoice extends questions implements questionType {

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @param int $sid
     * @return void
     */
    public function construct(int $jqshowid, int $cmid, int $sid) : void {
        parent::__construct($jqshowid, $cmid, $sid);
    }

    /**
     * @param int $jqid // jqshow_question id
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqshowid
     * @param bool $preview
     * @return object
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function export_question(int $jqid, int $cmid, int $sessionid, int $jqshowid, bool $preview = false) : object {
        $session = jqshow_sessions::get_record(['id' => $sessionid]);
        $jqshowquestion = jqshow_questions::get_record(['id' => $jqid]);
        $question = question_bank::load_question($jqshowquestion->get('questionid'));
        $type = $question->get_type_name();
        $data = self::get_question_common_data($session, $cmid, $sessionid, $jqshowid, $preview, $jqshowquestion, $type);
        $data->$type = true;
        $data->questiontext =
            self::get_text($cmid, $question->questiontext, $question->questiontextformat, $question->id, $question, 'questiontext');
        $answers = [];
        $feedbacks = [];
        foreach ($question->answers as $response) {
            if (assert($response instanceof question_answer)) {
                if ($response->fraction !== '0.0000000' && $response->fraction !== '1.0000000') {
                    $data->multianswers = true;
                }
                $answertext = self::get_text($cmid, $response->answer, $response->answerformat, $response->id, $question, 'answer');
                $answers[] = [
                    'answerid' => $response->id,
                    'questionid' => $jqshowquestion->get('questionid'),
                    'answertext' => $answertext,
                    'fraction' => $response->fraction,
                ];
                $feedbacks[] = [
                    'answerid' => $response->id,
                    'feedback' => $response->feedback,
                    'feedbackformat' => $response->feedbackformat,
                ];
            }
        }
        $data->numanswers = count($question->answers);
        $data->name = $question->name;
        if ($session->get('randomanswers') === 1) {
            shuffle($answers);
        }
        $data->answers = $answers;
        $data->feedbacks = $feedbacks;
        return $data;
    }

    /**
     * @param stdClass $data
     * @param string $response
     * @param int $result
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function export_question_response(stdClass $data, string $response, int $result = 0): stdClass {
        $responsedata = json_decode($response, false);
        $data->answered = true;
        $dataanswer = multichoice_external::multichoice(
            $responsedata->answerids,
            $data->sessionid,
            $data->jqshowid,
            $data->cmid,
            $data->questionid,
            $data->jqid,
            $responsedata->timeleft,
            true
        );
        $data->hasfeedbacks = $dataanswer['hasfeedbacks'];
        $dataanswer['answerids'] = $responsedata->answerids;
        $data->seconds = $responsedata->timeleft;
        $data->correct_answers = $dataanswer['correct_answers'];
        $data->programmedmode = $dataanswer['programmedmode'];
        if ($data->hasfeedbacks) {
            // TODO breaks images in report feedbacks. Services do not pass feedback through escape_characters, and they work. Consider removing.
            $dataanswer['statment_feedback'] = self::escape_characters($dataanswer['statment_feedback']);
            $dataanswer['answer_feedback'] = self::escape_characters($dataanswer['answer_feedback']);
        }
        $data->statment_feedback = $dataanswer['statment_feedback'];
        $data->answer_feedback = $dataanswer['answer_feedback'];
        $data->jsonresponse = base64_encode(json_encode($dataanswer, JSON_THROW_ON_ERROR));
        $data->statistics = $dataanswer['statistics'] ?? '0';
        return $data;
    }

    /**
     * @param jqshow_sessions $session
     * @param question_definition $questiondata
     * @param stdClass $data
     * @param int $jqid
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_question_report(jqshow_sessions $session,
                                               question_definition $questiondata,
                                               stdClass $data,
                                               int $jqid): stdClass {
        $answers = [];
        $correctanswers = [];
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
                $usersicon = new pix_icon('i/partially_users', '', 'mod_jqshow', [
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
        $gmembers = [];
        if ($session->is_group_mode()) {
            $gmembers = groupmode::get_one_member_of_each_grouping_group($session->get('groupings'));
        }
        $responses = jqshow_questions_responses::get_question_responses($session->get('id'), $data->jqshowid, $jqid);
        foreach ($responses as $response) {
            if ($session->is_group_mode() && !in_array($response->get('userid'), $gmembers)) {
                continue;
            }
            $other = json_decode(base64_decode($response->get('response')), false);
            if ($other->answerids !== '' && $other->answerids !== '0') {
                $arrayanswerids = explode(',', $other->answerids);
                foreach ($arrayanswerids as $arrayanswerid) {
                    $answers[$arrayanswerid]['numticked']++;
                }
            }
        }
        $data->correctanswers = array_values($correctanswers);
        $data->answers = array_values($answers);
        return $data;
    }

    /**
     * @param stdClass $participant
     * @param jqshow_questions_responses $response
     * @param array $answers
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception|moodle_exception
     */
    public static function get_ranking_for_question(
        stdClass $participant,
        jqshow_questions_responses $response,
        array $answers,
        jqshow_sessions $session,
        jqshow_questions $question): stdClass {
        $other = json_decode(base64_decode($response->get('response')), false);
        $arrayresponses = explode(',', $other->answerids);
        if (count($arrayresponses) === 1) {
            foreach ($answers as $answer) {
                if ((int)$answer['answerid'] === (int)$arrayresponses[0]) {
                    $participant->response = $answer['result'];
                    $participant->responsestr = get_string($answer['result'], 'mod_jqshow');
                    $participant->answertext = $answer['answertext'];
                } else if ((int)$arrayresponses[0] === 0) {
                    $participant->response = 'noresponse';
                    $participant->responsestr = get_string('qstatus_' . questions::NORESPONSE, 'mod_jqshow');
                    $participant->answertext = '';
                }
                $points = grade::get_simple_mark($response);
                $spoints = grade::get_session_grade($participant->participantid, $session->get('id'),
                    $session->get('jqshowid'));
                if ($session->is_group_mode()) {
                    $participant->grouppoints = grade::get_rounded_mark($spoints);
                } else {
                    $participant->userpoints = grade::get_rounded_mark($spoints);
                }
                $participant->score_moment = grade::get_rounded_mark($points);
                $participant->time = reports::get_user_time_in_question($session, $question, $response);
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
            $status = grade::get_status_response_for_multiple_answers($question->get('questionid'), $other->answerids);
            $participant->response = get_string('qstatus_' . $status, 'mod_jqshow');
            $participant->responsestr = get_string($participant->response, 'mod_jqshow');
            $participant->answertext = trim($answertext, '<br>');
            $points = grade::get_simple_mark($response);
            $spoints = grade::get_session_grade($participant->id, $session->get('id'), $session->get('jqshowid'));
            if ($session->is_group_mode()) {
                $participant->grouppoints = grade::get_rounded_mark($spoints);
            } else {
                $participant->userpoints = grade::get_rounded_mark($spoints);
            }
            $participant->score_moment = grade::get_rounded_mark($points);
            $participant->time = reports::get_user_time_in_question($session, $question, $response);
        }
        return $participant;
    }

    /**
     * @param int $cmid
     * @param int $jqid
     * @param int $questionid
     * @param int $sessionid
     * @param int $jqshowid
     * @param string $statmentfeedback
     * @param int $userid
     * @param int $timeleft
     * @param array $custom
     * @return void
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function question_response(
        int $cmid,
        int $jqid,
        int $questionid,
        int $sessionid,
        int $jqshowid,
        string $statmentfeedback,
        int $userid,
        int $timeleft,
        array $custom
    ): void {
        $answerids = $custom['answerids'];
        $answertexts = $custom['answertexts'];
        $correctanswers = $custom['correctanswers'];
        $answerfeedback = $custom['answerfeedback'];
        $cmcontext = context_module::instance($cmid);
        $isteacher = has_capability('mod/jqshow:managesessions', $cmcontext);
        if ($isteacher !== true) {
            self::manage_response($jqid, $answerids, $answertexts, $correctanswers, $questionid, $sessionid, $jqshowid,
                $statmentfeedback, $answerfeedback, $userid, $timeleft, questions::MULTICHOICE);
        }
    }

    /**
     * @param int $jqid
     * @param string $answerids
     * @param string $answertexts
     * @param string $correctanswers
     * @param int $questionid
     * @param int $sessionid
     * @param int $jqshowid
     * @param string $statmentfeedback
     * @param string $answerfeedback
     * @param int $userid
     * @param int $timeleft
     * @param string $qtype
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function manage_response(
        int $jqid,
        string $answerids,
        string $answertexts,
        string $correctanswers,
        int $questionid,
        int $sessionid,
        int $jqshowid,
        string $statmentfeedback,
        string $answerfeedback,
        int $userid,
        int $timeleft,
        string $qtype
    ) : void {
        $result = self::get_status_response($answerids, $correctanswers, $questionid);
        $response = new stdClass(); // For snapshot.
        $response->hasfeedbacks = (bool)($statmentfeedback !== '' | $answerfeedback !== '');
        $response->correct_answers = $correctanswers;
        $response->answerids = $answerids;
        $response->answertexts = $answertexts;
        $response->timeleft = $timeleft;
        $response->type = $qtype;
        $session = new jqshow_sessions($sessionid);
        if ($session->is_group_mode()) {
            parent::add_group_response($jqshowid, $session, $jqid, $questionid, $userid, $result, $response);
        } else {
            // Individual.
            jqshow_questions_responses::add_response(
                $jqshowid, $sessionid, $jqid, $questionid, $userid, $result, json_encode($response, JSON_THROW_ON_ERROR)
            );
        }
    }

    /**
     * @param string $answerids
     * @param string $correctanswers
     * @param int $questionid
     * @return string
     * @throws dml_exception
     */
    private static function get_status_response(string $answerids, string $correctanswers, int $questionid) : string {
        $result = questions::INVALID; // Invalid response.
        if ($answerids === '0' || $answerids === '') {
            $result = questions::NORESPONSE; // No response.
        } else if ($correctanswers !== '') {
            $correctids = explode(',', $correctanswers);
            if (count($correctids) > 1) { // Multianswers.
                $result = grade::get_status_response_for_multiple_answers($questionid, $answerids);
            } else if (in_array($answerids, $correctids, false)) {
                $result = questions::SUCCESS; // Correct.
            } else {
                $result = questions::FAILURE; // Incorrect.
            }
        }
        return $result;
    }

    /**
     * @param stdClass $useranswer
     * @param jqshow_questions_responses $response
     * @return float
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_simple_mark(stdClass $useranswer,  jqshow_questions_responses $response) : float {
        global $DB;
        $mark = 0;
        $defaultmark = $DB->get_field('question', 'defaultmark', ['id' => $response->get('questionid')]);
        $answerids = $useranswer->{'answerids'} ?? '';
        if (empty($answerids)) {
            return $mark;
        }
        $answerids = explode(',', $answerids);
        foreach ($answerids as $answerid) {
            $fraction = $DB->get_field('question_answers', 'fraction', ['id' => $answerid]);
            $mark += $defaultmark * $fraction;
        }
        return $mark;
    }

    /**
     * @param question_definition $question
     * @param jqshow_questions_responses[] $responses
     * @return array
     * @throws coding_exception
     */
    public static function get_question_statistics( question_definition $question, array $responses) : array {
        $statistics = [];
        foreach ($question->answers as $answer) {
            $statistics[$answer->id] = ['answerid' => $answer->id, 'numberofreplies' => 0];
        }
        foreach ($responses as $response) {
            foreach ($question->answers as $answer) {
                $other = json_decode(base64_decode($response->get('response')), false);
                $arrayresponses = explode(',', $other->answerids);
                foreach ($arrayresponses as $responseid) {
                    if ((int)$responseid === (int)$answer->id) {
                        $statistics[$answer->id]['numberofreplies']++;
                    }
                }
            }
        }
        return $statistics;
    }
    /**
     * @return bool
     */
    public static function show_statistics() : bool {
        return true;
    }
}
