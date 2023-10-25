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
use context_module;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\api\grade;
use mod_jqshow\api\groupmode;
use mod_jqshow\external\calculated_external;
use mod_jqshow\helpers\reports;
use mod_jqshow\persistents\jqshow;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use pix_icon;
use qtype_calculated_question;
use qtype_numerical_answer_processor;
use question_bank;
use question_definition;
use ReflectionClass;
use ReflectionException;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;

class calculated extends questions {

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
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     * @throws ReflectionException
     */
    public static function export_calculated(int $jqid, int $cmid, int $sessionid, int $jqshowid, bool $preview = false): object {
        $session = jqshow_sessions::get_record(['id' => $sessionid]);
        $jqshowquestion = jqshow_questions::get_record(['id' => $jqid]);
        $question = question_bank::load_question($jqshowquestion->get('questionid'));
        if (!assert($question instanceof qtype_calculated_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        $type = $question->get_type_name();
        $data = self::get_question_common_data($session, $jqid, $cmid, $sessionid, $jqshowid, $preview, $jqshowquestion, $type);
        $data->$type = true;
        $data->qtype = $type;
        self::get_text($cmid, $question->questiontext, $question->questiontextformat, $question->id, $question, 'questiontext');
        $data->questiontextformat = $question->questiontextformat;
        $data->name = $question->name;
        $data->unitsleft = isset($question->unitdisplay) && $question->unitdisplay === 1;
        $data->questiontext = $question->questiontext;
        $data->variant = $question->variant;
        if (isset($question->ap) && assert($question->ap instanceof qtype_numerical_answer_processor)) {
            $data->units = [];
            $reflection = new ReflectionClass($question->ap);
            $units = $reflection->getProperty('units');
            $units->setAccessible(true);
            $unitid = 0;
            foreach ($units->getValue($question->ap) as $key => $unit) {
                $data->units[$key] = [];
                $data->units[$key]['unitid'] = 'unit_' . $unitid;
                $data->units[$key]['unittext'] = $key;
                $data->units[$key]['multiplier'] = $unit;
            }
            $data->units = array_values($data->units);
            if (isset($question->unitdisplay)) {
                switch ($question->unitdisplay) {
                    case 0:
                    default:
                        $data->unitdisplay = false;
                        break;
                    case 1:
                        $data->unitdisplay = true;
                        $data->showunitsradio = true;
                        break;
                    case 2:
                        $data->unitdisplay = true;
                        $data->showunitsselect = true;
                        break;
                }
            } else {
                $data->unitdisplay = false;
            }
        }
        return $data;
    }

    /**
     * @param stdClass $data
     * @param string $response
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function export_calculated_response(stdClass $data, string $response): stdClass {
        $responsedata = json_decode($response, false);
        if (!isset($responsedata->response) || (is_array($responsedata->response) && count($responsedata->response) === 0)) {
            $responsedata->response = '';
            $responsedata->unit = '';
            $responsedata->multiplier = '';
        }
        if (isset($responsedata->unit) && $responsedata->unit !== '') {
            foreach ($data->units as $key => $unit) {
                if ($unit['unittext'] === $responsedata->unit) {
                    $data->units[$key]['unitselected'] = true;
                    break;
                }
            }
        }
        if (!isset($responsedata->variant)) {
            $responsedata->variant = 0;
        }
        $data->answered = true;
        $dataanswer = calculated_external::calculated(
            $responsedata->response,
            $responsedata->variant,
            $responsedata->unit,
            $responsedata->multiplier,
            $data->sessionid,
            $data->jqshowid,
            $data->cmid,
            $data->questionid,
            $data->jqid,
            $responsedata->timeleft,
            true
        );
        $jqshowquestion = jqshow_questions::get_record(['id' => $data->jqid]);
        $question = question_bank::load_question($jqshowquestion->get('questionid'));
        self::get_text($data->cmid, $question->questiontext, $question->questiontextformat, $question->id, $question, 'questiontext', $responsedata->variant);
        $data->questiontext = $question->questiontext;
        $data->hasfeedbacks = $dataanswer['hasfeedbacks'];
        $data->calculatedresponse = $responsedata->response;
        $data->seconds = $responsedata->timeleft;
        $data->programmedmode = $dataanswer['programmedmode'];
        $data->jsonresponse = base64_encode(json_encode($dataanswer, JSON_THROW_ON_ERROR));
        $data->statistics = $dataanswer['statistics'] ?? '0';
        return $data;
    }

    /**
     * @param jqshow_sessions $session
     * @param question_definition $questiondata
     * @param stdClass $data
     * @param int $jqid
     * @return void
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_question_report(jqshow_sessions     $session,
                                               question_definition $questiondata,
                                               stdClass            $data,
                                               int                 $jqid): stdClass {
        $answers = [];
        $correctanswers = [];
        if (!assert($questiondata instanceof qtype_calculated_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        $answers = [];
        $correctanswers = [];
        foreach ($questiondata->answers as $key => $answer) {
            $answers[$key]['answertext'] = $answer->answer;
            $answers[$key]['answerid'] = $answer->id;
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
            if (isset($other->response) && $other->response !== '') {
                foreach ($answers as $key => $answer) {
                    if ($answer['answertext'] === $other->response) {
                        $answers[$key]['numticked']++;
                    }
                }
            }
        }
        $data->correctanswers = array_values($correctanswers);
        $data->answers = array_values($answers);
        self::get_text(
            $data->cmid, $data->questiontext, (int) $data->questiontextformat, $data->questionnid, $questiondata, 'questiontext'
        );
        $data->questiontext = $questiondata->questiontext;
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
        $other = json_decode(base64_decode($response->get('response')), false);
        switch ($response->get('result')) {
            case questions::FAILURE:
                $participant->response = 'incorrect';
                $participant->responsestr = get_string('incorrect', 'mod_jqshow');
                break;
            case questions::SUCCESS:
                $participant->response = 'correct';
                $participant->responsestr = get_string('correct', 'mod_jqshow');
                break;
            case questions::PARTIALLY:
                $participant->response = 'partially';
                $participant->responsestr = get_string('partially', 'mod_jqshow');
                break;
            case questions::NORESPONSE:
            default:
                $participant->response = 'noresponse';
                $participant->responsestr = get_string('noresponse', 'mod_jqshow');
                break;
        }
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
     * @param int $cmid
     * @param int $jqid
     * @param string $responsetext
     * @param int $variant
     * @param string $unit
     * @param string $multiplier
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
    public static function calculated_response(
        int $cmid,
        int $jqid,
        string $responsetext,
        int $variant,
        string $unit,
        string $multiplier,
        int $result,
        int $questionid,
        int $sessionid,
        int $jqshowid,
        string $statmentfeedback,
        string $answerfeedback,
        int $userid,
        int $timeleft
    ): void {
        $cmcontext = context_module::instance($cmid);
        $isteacher = has_capability('mod/jqshow:managesessions', $cmcontext);
        if ($isteacher !== true) {
            $session = new jqshow_sessions($sessionid);
            $response = new stdClass();
            $response->hasfeedbacks = (bool)($statmentfeedback !== '' | $answerfeedback !== '');
            $response->timeleft = $timeleft;
            $response->type = questions::CALCULATED;
            $response->response = $responsetext; // TODO validate html and special characters.
            $response->unit = $unit;
            $response->multiplier = $multiplier;
            $response->variant = $variant;
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
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function get_simple_mark(stdClass $useranswer, jqshow_questions_responses $response) {
        $mark = 0;
        $question = question_bank::load_question($response->get('questionid'));
        $jqshow = new jqshow($response->get('jqshow'));
        [$course, $cm] = get_course_and_cm_from_instance($response->get('jqshow'), 'jqshow', $jqshow->get('course'));
        if (assert($question instanceof qtype_calculated_question)) {
            $jsonresponse = json_decode(base64_decode($response->get('response')), false);
            questions::get_text(
                $cm->id, $question->generalfeedback, $question->generalfeedbackformat, $question->id, $question, 'generalfeedback', $jsonresponse->variant
            );
            $moodleresult = $question->grade_response(['answer' => $jsonresponse->response, 'unit' => $jsonresponse->unit]);
            if (isset($moodleresult[0])) {
                $mark = $moodleresult[0];
            }
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
        $statistics[0]['correct'] = $correct !== 0 ? round($correct * 100 / $total, 2) : 0;
        $statistics[0]['failure'] = $incorrect !== 0 ? round($incorrect  * 100 / $total, 2) : 0;
        $statistics[0]['partially'] = $partially !== 0 ? round($partially  * 100 / $total, 2) : 0;
        $statistics[0]['noresponse'] = $noresponse !== 0 ? round($noresponse  * 100 / $total, 2) : 0;
        return $statistics;
    }
}
