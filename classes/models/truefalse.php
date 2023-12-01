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
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\models;

use coding_exception;
use context_module;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use invalid_parameter_exception;
use JsonException;
use mod_kuet\api\grade;
use mod_kuet\api\groupmode;
use mod_kuet\external\truefalse_external;
use mod_kuet\helpers\reports;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use mod_kuet\persistents\kuet_user_progress;
use moodle_exception;
use pix_icon;
use question_bank;
use question_definition;
use stdClass;
use mod_kuet\interfaces\questionType;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot. '/question/type/multichoice/questiontype.php');

class truefalse extends questions implements questionType {

    /**
     * @param int $kuetid
     * @param int $cmid
     * @param int $sid
     * @return void
     */
    public function construct(int $kuetid, int $cmid, int $sid) : void {
        parent::__construct($kuetid, $cmid, $sid);
    }

    /**
     * @param int $kid
     * @param int $cmid
     * @param int $sessionid
     * @param int $kuetid
     * @param bool $preview
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function export_question(int $kid, int $cmid, int $sessionid, int $kuetid, bool $preview = false): object {
        global $USER;
        $session = kuet_sessions::get_record(['id' => $sessionid]);
        $kuetquestion = kuet_questions::get_record(['id' => $kid]);
        $question = question_bank::load_question($kuetquestion->get('questionid'));
        $numsessionquestions = kuet_questions::count_records(['kuetid' => $kuetid, 'sessionid' => $sessionid]);
        $type = self::TRUE_FALSE;
        $answers = [];
        $feedbacks = [];
        $data = new stdClass();
        // True answer.
        $answers[] = [
            'answerid' => $question->trueanswerid,
            'questionid' => $kuetquestion->get('questionid'),
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
            'questionid' => $kuetquestion->get('questionid'),
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
        $data->kuetid = $kuetid;
        $data->questionid = $kuetquestion->get('questionid');
        $data->kid = $kuetquestion->get('id');
        $data->showquestionfeedback = (int)$session->get('showfeedback') === 1;
        $data->endsession = false;
        switch ($session->get('sessionmode')) {
            case sessions::INACTIVE_PROGRAMMED:
            case sessions::PODIUM_PROGRAMMED:
            case sessions::RACE_PROGRAMMED:
                $data->programmedmode = true;
                $progress = kuet_user_progress::get_session_progress_for_user(
                    $USER->id, $session->get('id'), $session->get('kuetid')
                );
                if ($progress !== false) {
                    $dataprogress = json_decode($progress->get('other'), false);
                    if (!isset($dataprogress->endSession)) {
                        $dataorder = explode(',', $dataprogress->questionsorder);
                        $order = (int)array_search($dataprogress->currentquestion, $dataorder, false);
                        $a = new stdClass();
                        $a->num = $order + 1;
                        $a->total = $numsessionquestions;
                        $data->question_index_string = get_string('question_index_string', 'mod_kuet', $a);
                        $data->sessionprogress = round(($order + 1) * 100 / $numsessionquestions);
                    }
                }
                if (!isset($data->question_index_string)) {
                    $order = $kuetquestion->get('qorder');
                    $a = new stdClass();
                    $a->num = $order;
                    $a->total = $numsessionquestions;
                    $data->question_index_string = get_string('question_index_string', 'mod_kuet', $a);
                    $data->sessionprogress = round($order * 100 / $numsessionquestions);
                }
                break;
            case sessions::INACTIVE_MANUAL:
            case sessions::PODIUM_MANUAL:
            case sessions::RACE_MANUAL:
                $order = $kuetquestion->get('qorder');
                $a = new stdClass();
                $a->num = $order;
                $a->total = $numsessionquestions;
                $data->question_index_string = get_string('question_index_string', 'mod_kuet', $a);
                $data->numquestions = $numsessionquestions;
                $data->sessionprogress = round($order * 100 / $numsessionquestions);
                break;
            default:
                throw new moodle_exception('incorrect_sessionmode', 'mod_kuet', '',
                    [], get_string('incorrect_sessionmode', 'mod_kuet'));
        }
        $data->questiontext =
            self::get_text($cmid, $question->questiontext, $question->questiontextformat, $question->id, $question, 'questiontext');
        $data->questiontextformat = $question->questiontextformat;
        switch ($session->get('timemode')) {
            case sessions::NO_TIME:
            default:
                if ($kuetquestion->get('timelimit') !== 0) {
                    $data->hastime = true;
                    $data->seconds = $kuetquestion->get('timelimit');
                } else {
                    $data->hastime = false;
                    $data->seconds = 0;
                }
                break;
            case sessions::SESSION_TIME:
                $data->hastime = true;
                $numquestion = kuet_questions::count_records(
                    ['sessionid' => $session->get('id'), 'kuetid' => $session->get('kuetid')]
                );
                $data->seconds = round((int)$session->get('sessiontime') / $numquestion);
                break;
            case sessions::QUESTION_TIME:
                $data->hastime = true;
                $data->seconds =
                    $kuetquestion->get('timelimit') !== 0 ? $kuetquestion->get('timelimit') : $session->get('questiontime');
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
        $data->template = 'mod_kuet/questions/encasement';
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
            $data->kuetid,
            $data->cmid,
            $data->questionid,
            $data->kid,
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
     * @param kuet_sessions $session
     * @param question_definition $questiondata
     * @param stdClass $data
     * @param int $kid
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_question_report(kuet_sessions $session,
                                               question_definition $questiondata,
                                               stdClass $data,
                                               int $kid): stdClass {
        $answers = [];
        $correctanswers = [];

        // True.
        $answers[$questiondata->trueanswerid]['answertext'] = get_string('true', 'qtype_truefalse');
        $answers[$questiondata->trueanswerid]['answerid'] = $questiondata->trueanswerid;
        $statustrue = $questiondata->rightanswer ? 'correct' : 'incorrect';
        $answers[$questiondata->trueanswerid]['result'] = $statustrue;
        $answers[$questiondata->trueanswerid]['resultstr'] = get_string($statustrue, 'mod_kuet');
//            $answers[$key]['fraction'] = round(1, 2); // en las trufalse no existe.
        $icon = new pix_icon('i/' . $statustrue,
            get_string($statustrue, 'mod_kuet'), 'mod_kuet', [
                'class' => 'icon',
                'title' => get_string($statustrue, 'mod_kuet')
            ]);
        $usersicon = new pix_icon('i/'. $statustrue .'_users', '', 'mod_kuet', [
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
        $answers[$questiondata->falseanswerid]['resultstr'] = get_string($statusfalse, 'mod_kuet');
//            $answers[$questiondata->falseanswerid]['fraction'] = round(1, 2); // en las trufalse no existe.
        $icon = new pix_icon('i/' . $statusfalse,
            get_string($statusfalse, 'mod_kuet'), 'mod_kuet', [
                'class' => 'icon',
                'title' => get_string($statusfalse, 'mod_kuet')
            ]);
        $usersicon = new pix_icon('i/'. $statusfalse .'_users', '', 'mod_kuet', [
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
        $responses = kuet_questions_responses::get_question_responses($session->get('id'), $data->kuetid, $kid);
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
     * @param kuet_questions_responses $response
     * @param array $answers
     * @param kuet_sessions $session
     * @param kuet_questions $question
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_ranking_for_question(
        stdClass $participant,
        kuet_questions_responses $response,
        array $answers,
        kuet_sessions $session,
        kuet_questions $question) : stdClass {

        $other = json_decode(base64_decode($response->get('response')), false);
        $arrayresponses = explode(',', $other->answerids);

        foreach ($answers as $answer) {
            if ((int)$answer['answerid'] === (int)$arrayresponses[0]) {
                $participant->response = $answer['result'];
                $participant->responsestr = get_string($answer['result'], 'mod_kuet');
                $participant->answertext = $answer['answertext'];
            } else if ((int)$arrayresponses[0] === 0) {
                $participant->response = 'noresponse';
                $participant->responsestr = get_string('qstatus_' . questions::NORESPONSE, 'mod_kuet');
                $participant->answertext = '';
            }
            $points = grade::get_simple_mark($response);
            $spoints = grade::get_session_grade($participant->participantid, $session->get('id'),
                $session->get('kuetid'));
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
     * @param int $kid
     * @param int $questionid
     * @param int $sessionid
     * @param int $kuetid
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
        int $kid,
        int $questionid,
        int $sessionid,
        int $kuetid,
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
        $isteacher = has_capability('mod/kuet:managesessions', $cmcontext);
        if ($isteacher !== true) {
            multichoice::manage_response($kid, $answerids, $answertexts, $correctanswers, $questionid, $sessionid, $kuetid,
                $statmentfeedback, $answerfeedback, $userid, $timeleft, questions::TRUE_FALSE);
        }
    }

    /**
     * @param stdClass $useranswer
     * @param kuet_questions_responses $response
     * @return float
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_simple_mark(stdClass $useranswer,  kuet_questions_responses $response) :float {
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
     * @param kuet_questions_responses[] $responses
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
