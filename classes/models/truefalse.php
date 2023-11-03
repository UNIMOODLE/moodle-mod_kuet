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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos

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
use mod_jqshow\external\truefalse_external;
use mod_jqshow\helpers\reports;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_user_progress;
use moodle_exception;
use pix_icon;
use question_bank;
use question_definition;
use stdClass;
use mod_jqshow\interfaces\questionType;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot. '/question/type/multichoice/questiontype.php');

class truefalse extends questions implements questionType {

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
     * @param int $jqid
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqshowid
     * @param bool $preview
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function export_question(int $jqid, int $cmid, int $sessionid, int $jqshowid, bool $preview = false): object {
        global $USER;
        $session = jqshow_sessions::get_record(['id' => $sessionid]);
        $jqshowquestion = jqshow_questions::get_record(['id' => $jqid]);
        $question = question_bank::load_question($jqshowquestion->get('questionid'));
        $numsessionquestions = jqshow_questions::count_records(['jqshowid' => $jqshowid, 'sessionid' => $sessionid]);
        $type = self::TRUE_FALSE;
        $answers = [];
        $feedbacks = [];
        $data = new stdClass();
        // True answer.
        $answers[] = [
            'answerid' => $question->trueanswerid,
            'questionid' => $jqshowquestion->get('questionid'),
            'answertext' => get_string('true', 'qtype_truefalse'),
            'fraction' => '1.0000000',
        ];
        $feedbacks[] = [
            'answerid' => $question->trueanswerid,
            'feedback' => self::escape_characters($question->truefeedback),
            'feedbackformat' => $question->truefeedbackformat,
        ];
        // False answer.
        $answers[] = [
            'answerid' => $question->falseanswerid,
            'questionid' => $jqshowquestion->get('questionid'),
            'answertext' => get_string('false', 'qtype_truefalse'),
            'fraction' => '1.0000000',
        ];
        $feedbacks[] = [
            'answerid' => $question->falseanswerid,
            'feedback' => self::escape_characters($question->falsefeedback),
            'feedbackformat' => $question->falsefeedbackformat,
        ];
        $data->cmid = $cmid;
        $data->sessionid = $sessionid;
        $data->jqshowid = $jqshowid;
        $data->questionid = $jqshowquestion->get('questionid');
        $data->jqid = $jqshowquestion->get('id');
        $data->showquestionfeedback = (int)$session->get('showfeedback') === 1;
        $data->endsession = false;
        switch ($session->get('sessionmode')) {
            case sessions::INACTIVE_PROGRAMMED:
            case sessions::PODIUM_PROGRAMMED:
            case sessions::RACE_PROGRAMMED:
                $data->programmedmode = true;
                $progress = jqshow_user_progress::get_session_progress_for_user(
                    $USER->id, $session->get('id'), $session->get('jqshowid')
                );
                if ($progress !== false) {
                    $dataprogress = json_decode($progress->get('other'), false);
                    if (!isset($dataprogress->endSession)) {
                        $dataorder = explode(',', $dataprogress->questionsorder);
                        $order = (int)array_search($dataprogress->currentquestion, $dataorder, false);
                        $a = new stdClass();
                        $a->num = $order + 1;
                        $a->total = $numsessionquestions;
                        $data->question_index_string = get_string('question_index_string', 'mod_jqshow', $a);
                        $data->sessionprogress = round(($order + 1) * 100 / $numsessionquestions);
                    }
                }
                if (!isset($data->question_index_string)) {
                    $order = $jqshowquestion->get('qorder');
                    $a = new stdClass();
                    $a->num = $order;
                    $a->total = $numsessionquestions;
                    $data->question_index_string = get_string('question_index_string', 'mod_jqshow', $a);
                    $data->sessionprogress = round($order * 100 / $numsessionquestions);
                }
                break;
            case sessions::INACTIVE_MANUAL:
            case sessions::PODIUM_MANUAL:
            case sessions::RACE_MANUAL:
                $order = $jqshowquestion->get('qorder');
                $a = new stdClass();
                $a->num = $order;
                $a->total = $numsessionquestions;
                $data->question_index_string = get_string('question_index_string', 'mod_jqshow', $a);
                $data->numquestions = $numsessionquestions;
                $data->sessionprogress = round($order * 100 / $numsessionquestions);
                break;
            default:
                throw new moodle_exception('incorrect_sessionmode', 'mod_jqshow', '',
                    [], get_string('incorrect_sessionmode', 'mod_jqshow'));
        }
        $data->questiontext =
            self::get_text($cmid, $question->questiontext, $question->questiontextformat, $question->id, $question, 'questiontext');
        $data->questiontextformat = $question->questiontextformat;
        switch ($session->get('timemode')) {
            case sessions::NO_TIME:
            default:
                if ($jqshowquestion->get('timelimit') !== 0) {
                    $data->hastime = true;
                    $data->seconds = $jqshowquestion->get('timelimit');
                } else {
                    $data->hastime = false;
                    $data->seconds = 0;
                }
                break;
            case sessions::SESSION_TIME:
                $data->hastime = true;
                $numquestion = jqshow_questions::count_records(
                    ['sessionid' => $session->get('id'), 'jqshowid' => $session->get('jqshowid')]
                );
                $data->seconds = round((int)$session->get('sessiontime') / $numquestion);
                break;
            case sessions::QUESTION_TIME:
                $data->hastime = true;
                $data->seconds =
                    $jqshowquestion->get('timelimit') !== 0 ? $jqshowquestion->get('timelimit') : $session->get('questiontime');
                break;
        }
        $data->countdown = $session->get('countdown');
        $data->preview = $preview;
        $data->numanswers = 2;
        $data->name = $question->name;
        $data->qtype = $type;
        $data->$type = true;
        if ($session->get('randomanswers') === 1) {
            shuffle($answers);
        }
        $data->answers = $answers;
        $data->feedbacks = $feedbacks;
        $data->template = 'mod_jqshow/questions/encasement';
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
        if (!isset($responsedata->answerids)) {
            $responsedata->answerids = 0;
        }
        $dataanswer = truefalse_external::truefalse(
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

        // True.
        $answers[$questiondata->trueanswerid]['answertext'] = get_string('true', 'qtype_truefalse');
        $answers[$questiondata->trueanswerid]['answerid'] = $questiondata->trueanswerid;
        $statustrue = $questiondata->rightanswer ? 'correct' : 'incorrect';
        $answers[$questiondata->trueanswerid]['result'] = $statustrue;
        $answers[$questiondata->trueanswerid]['resultstr'] = get_string($statustrue, 'mod_jqshow');
//            $answers[$key]['fraction'] = round(1, 2); // en las trufalse no existe.
        $icon = new pix_icon('i/' . $statustrue,
            get_string($statustrue, 'mod_jqshow'), 'mod_jqshow', [
                'class' => 'icon',
                'title' => get_string($statustrue, 'mod_jqshow')
            ]);
        $usersicon = new pix_icon('i/'. $statustrue .'_users', '', 'mod_jqshow', [
            'class' => 'icon',
            'title' => ''
        ]);
        $answers[$questiondata->trueanswerid]['resulticon'] = $icon->export_for_pix();
        $answers[$questiondata->trueanswerid]['usersicon'] = $usersicon->export_for_pix();
        $answers[$questiondata->trueanswerid]['numticked'] = 0;

        // False.
        $answers[$questiondata->falseanswerid]['answertext'] = get_string('false', 'qtype_truefalse');
        $answers[$questiondata->falseanswerid]['answerid'] = $questiondata->falseanswerid;
        $statusfalse = $questiondata->rightanswer ? 'incorrect' : 'correct';
        $answers[$questiondata->falseanswerid]['result'] = $statusfalse;
        $answers[$questiondata->falseanswerid]['resultstr'] = get_string($statusfalse, 'mod_jqshow');
//            $answers[$questiondata->falseanswerid]['fraction'] = round(1, 2); // en las trufalse no existe.
        $icon = new pix_icon('i/' . $statusfalse,
            get_string($statusfalse, 'mod_jqshow'), 'mod_jqshow', [
                'class' => 'icon',
                'title' => get_string($statusfalse, 'mod_jqshow')
            ]);
        $usersicon = new pix_icon('i/'. $statusfalse .'_users', '', 'mod_jqshow', [
            'class' => 'icon',
            'title' => ''
        ]);
        $answers[$questiondata->falseanswerid]['resulticon'] = $icon->export_for_pix();
        $answers[$questiondata->falseanswerid]['usersicon'] = $usersicon->export_for_pix();
        $answers[$questiondata->falseanswerid]['numticked'] = 0;

        // Correct answer.
        $rightanswerkey = $questiondata->rightanswer ? $questiondata->trueanswerid : $questiondata->falseanswerid;
        $rightanswertext = $questiondata->rightanswer ? get_string('true', 'qtype_truefalse') :
            get_string('false', 'qtype_truefalse');
        $correctanswers[$rightanswerkey]['response'] = $rightanswertext;
        $correctanswers[$rightanswerkey]['score'] = grade::get_rounded_mark($questiondata->defaultmark);

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
            // TODO obtain the average time to respond to each option ticked. ???
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
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_ranking_for_question(
        stdClass $participant,
        jqshow_questions_responses $response,
        array $answers,
        jqshow_sessions $session,
        jqshow_questions $question) : stdClass {

        $other = json_decode(base64_decode($response->get('response')), false);
        $arrayresponses = explode(',', $other->answerids);

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
            multichoice::manage_response($jqid, $answerids, $answertexts, $correctanswers, $questionid, $sessionid, $jqshowid,
                $statmentfeedback, $answerfeedback, $userid, $timeleft, questions::TRUE_FALSE);
        }
    }

    /**
     * @param stdClass $useranswer
     * @param jqshow_questions_responses $response
     * @return float
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_simple_mark(stdClass $useranswer,  jqshow_questions_responses $response) :float {
        global $DB;
        $mark = 0;
        $defaultmark = $DB->get_field('question', 'defaultmark', ['id' => $response->get('questionid')]);
        $answerids = $useranswer->{'answerids'} ?? '';
        if (empty($answerids)) {
            return (float)$mark;
        }
        $answerids = explode(',', $answerids);
        foreach ($answerids as $answerid) {
            $fraction = $DB->get_field('question_answers', 'fraction', ['id' => $answerid]);
            $mark += $defaultmark * $fraction;
        }
        return (float)$mark;
    }

    /**
     * @param question_definition $question
     * @param jqshow_questions_responses[] $responses
     * @return array
     * @throws coding_exception
     */
    public static function get_question_statistics( question_definition $question, array $responses) : array {
        $statistics = [];
        $statistics[$question->trueanswerid] = ['answerid' => $question->trueanswerid, 'numberofreplies' => 0];
        $statistics[$question->falseanswerid] = ['answerid' => $question->falseanswerid, 'numberofreplies' => 0];
        foreach ($responses as $response) {
            $other = json_decode(base64_decode($response->get('response')), false);
            if (!empty($other->answerids) && array_key_exists((int)$other->answerids, $statistics)) {
                $statistics[(int)$other->answerids]['numberofreplies']++;
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
