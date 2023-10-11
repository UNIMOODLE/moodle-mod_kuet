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
use core\invalid_persistent_exception;
use dml_exception;
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\api\grade;
use mod_jqshow\api\groupmode;
use mod_jqshow\external\match_external;
use mod_jqshow\helpers\reports;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use qtype_match_question;
use question_bank;
use question_definition;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;

class matchquestion extends questions {

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @param int $sid
     * @return void
     */
    public function construct(int $jqshowid, int $cmid, int $sid) {
        parent::__construct($jqshowid, $cmid, $sid);
    }

    /**
     * @param int $jqid
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqshowid
     * @param bool $preview
     * @return object
     * @throws JsonException
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function export_match(int $jqid, int $cmid, int $sessionid, int $jqshowid, bool $preview = false): object {
        $session = jqshow_sessions::get_record(['id' => $sessionid]);
        $jqshowquestion = jqshow_questions::get_record(['id' => $jqid]);
        $question = question_bank::load_question($jqshowquestion->get('questionid'));

        if (get_class($question) != 'qtype_match_question') {
            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        $type = $question->get_type_name();
        $data = self::get_question_common_data($session, $jqid, $cmid, $sessionid, $jqshowid, $preview, $jqshowquestion, $type);
        $data->$type = true;
        $data->qtype = $type;
        $data->questiontext =
            self::get_text($cmid, $question->questiontext, $question->questiontextformat, $question->id, $question, 'questiontext');
        $data->questiontextformat = $question->questiontextformat;
        $feedbacks = [];
        $leftoptions = [];
        foreach ($question->stems as $key => $leftside) {
            $leftoptions[$key] = [
                'questionid' => $jqshowquestion->get('questionid'),
                'key' => $key,
                'optionkey' => base_convert($key, 16, 2),
                'optiontext' =>
                    self::get_text($cmid, $leftside, $question->stemformat[$key], $question->id, $question, 'questiontext')
            ];
        }
        $rightoptions = [];
        foreach ($question->choices as $key => $leftside) {
            $rightoptions[$key] = [
                'questionid' => $jqshowquestion->get('questionid'),
                'key' => $key,
                'optionkey' => base_convert($key, 10, 26),
                'optiontext' =>
                    self::get_text($cmid, $leftside, $question->stemformat[$key], $question->id, $question, 'questiontext')
            ];
        }
        $data->name = $question->name;
        shuffle($rightoptions);
        if ($session->get('randomanswers') === 1) {
            shuffle($leftoptions);
        }
        $data->leftoptions = array_values($leftoptions);
        $data->rightoptions = array_values($rightoptions);
        return $data;
    }

    /**
     * @param stdClass $data
     * @param string $response
     * @param int $result
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function export_match_response(stdClass $data, string $response, int $result):stdClass {
        $responsedata = json_decode($response, false);
        $data->answered = true;
        $jsonresponse = json_encode($responsedata->response, JSON_THROW_ON_ERROR);
        $dataanswer = match_external::match(
            $jsonresponse,
            $result,
            $data->sessionid,
            $data->jqshowid,
            $data->cmid,
            $data->questionid,
            $data->jqid,
            $responsedata->timeleft,
            true
        );
        $data->hasfeedbacks = $dataanswer['hasfeedbacks'];
        $data->seconds = $responsedata->timeleft;
        $data->correct_answers = $dataanswer['correct_answers'];
        $data->programmedmode = $dataanswer['programmedmode'];
        $data->jsonresponse = base64_encode($jsonresponse);
        if ($data->hasfeedbacks) {
            $dataanswer['statment_feedback'] = self::escape_characters($dataanswer['statment_feedback']);
            $dataanswer['answer_feedback'] = self::escape_characters($dataanswer['answer_feedback']);
        }
        $data->statment_feedback = $dataanswer['statment_feedback'];
        $data->answer_feedback = $dataanswer['answer_feedback'];
        $data->statistics = $dataanswer['statistics'] ?? '0';
        return $data;
    }

    /**
     * @param jqshow_sessions $session
     * @param question_definition $questiondata
     * @param stdClass $data
     * @param int $jqid
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_question_report(jqshow_sessions $session,
                                               question_definition $questiondata,
                                               stdClass $data,
                                               int $jqid): stdClass {
        $answers = [];
        $correctanswers = [];
        if (!assert($questiondata instanceof qtype_match_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        if (isset($questiondata->stems)) {
            foreach ($questiondata->stems as $key => $answer) {
                $correctanswers[$key]['response'] = $answer . ' -> ' . $questiondata->choices[$key];
            }
        }
        $data->correctanswers = array_values($correctanswers);
        $data->answers = array_values($answers);
        $data->nostatistics = true;
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
     * @throws dml_exception
     */
    public static function get_ranking_for_question(
        stdClass $participant,
        jqshow_questions_responses $response,
        array $answers,
        jqshow_sessions $session,
        jqshow_questions $question): stdClass {
        switch ($response->get('result')) {
            case questions::FAILURE:
                $participant->response = 'incorrect';
                break;
            case questions::SUCCESS:
                $participant->response = 'correct';
                break;
            case questions::PARTIALLY:
                $participant->response = 'partially';
                break;
            case questions::NORESPONSE:
            default:
                $participant->response = 'noresponse';
                break;
        }
        $participant->responsestr = get_string($participant->response, 'mod_jqshow');
        $points = grade::get_simple_mark($response);
        $spoints = grade::get_session_grade($participant->participantid, $session->get('id'),
            $session->get('jqshowid'));
        $participant->userpoints = grade::get_rounded_mark($spoints);
        if ($session->is_group_mode()) {
            $participant->grouppoints = grade::get_rounded_mark($spoints);
        }
        $participant->score_moment = grade::get_rounded_mark($points);
        $participant->time = reports::get_user_time_in_question($session, $question, $response);
        return $participant;
    }

    /**
     * @param int $jqid
     * @param string $jsonresponse
     * @param int $result
     * @param int $questionid
     * @param int $sessionid
     * @param int $jqshowid
     * @param string $statmentfeedback
     * @param string $answerfeedback
     * @param int $userid
     * @param int $timeleft
     * @return void
     * @throws JsonException
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function match_response(
        int $jqid,
        string $jsonresponse,
        int $result,
        int $questionid,
        int $sessionid,
        int $jqshowid,
        string $statmentfeedback,
        string $answerfeedback,
        int $userid,
        int $timeleft
    ):void {
        global $COURSE;
        $coursecontext = context_course::instance($COURSE->id);
        $isteacher = has_capability('mod/jqshow:managesessions', $coursecontext);
        if ($isteacher !== true) {
            $session = new jqshow_sessions($sessionid);
            $response = new stdClass();
            $response->hasfeedbacks = (bool)($statmentfeedback !== '' | $answerfeedback !== '');
            $response->timeleft = $timeleft;
            $response->type = questions::MATCH;
            $response->response = json_decode($jsonresponse);
            if ($session->is_group_mode()) {
                parent::add_group_response($jqshowid, $session, $jqid, $questionid, $userid, $result, $response);
            } else {
                // Individual.
                jqshow_questions_responses::add_response(
                    $jqshowid, $sessionid, $jqid, $questionid, $userid, $result, json_encode($response, JSON_THROW_ON_ERROR)
                );
            }
        }
    }

    /**
     * @param stdClass $useranswer
     * @param jqshow_questions_responses $response
     * @return float|int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_simple_mark(stdClass $useranswer,  jqshow_questions_responses $response) {
        global $DB;
        $mark = 0;
        // TODO prepare the json of the response to pass logic through grade_response.
        if ((int) $response->get('result') === 1) {
            $mark = $DB->get_field('question', 'defaultmark', ['id' => $response->get('questionid')]);
        }
        return $mark;
    }
    /**
     * @param question_definition $question
     * @param jqshow_questions_responses[] $responses
     * @return array
     */
    public static function get_question_statistics( question_definition $question, array $responses) : array {
        $statistics = [];
        $correct = 0;
        $incorrect = 0;
        $partially = 0;
        $noresponse = 0;
        $invalid = 0;
        $total = count($responses);
        foreach ($responses as $response) {
            $result = $response->get('result');
            switch ($result) {
                case questions::SUCCESS: $correct++; break;
                case questions::FAILURE: $incorrect++; break;
                case questions::INVALID: $invalid++; break;
                case questions::PARTIALLY: $partially++; break;
                case questions::NORESPONSE: $noresponse++; break;
            }
        }
        $statistics[0]['correct'] = $correct * 100 / $total;
        $statistics[0]['failure'] = $incorrect  * 100 / $total;
//        $statistics[0]['invalid'] = $invalid  * 100 / $total;
        $statistics[0]['partially'] = $partially  * 100 / $total;
        $statistics[0]['noresponse'] = $noresponse  * 100 / $total;
        return $statistics;
    }
}
