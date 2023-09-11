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
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\external\truefalse_external;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_user_progress;
use moodle_exception;
use question_bank;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot. '/question/type/multichoice/questiontype.php');

class truefalse extends questions {

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
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function export_truefalse(int $jqid, int $cmid, int $sessionid, int $jqshowid, bool $preview = false): object {
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
                    $dataprogress = json_decode($progress->get('other'), false, 512, JSON_THROW_ON_ERROR);
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
     * @return stdClass
     * @throws JsonException
     * @throws invalid_persistent_exception
     * @throws invalid_parameter_exception
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function export_truefalse_response(stdClass $data, string $response): stdClass {
        $responsedata = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
        $data->answered = true;
        $dataanswer = truefalse_external::truefalse(
            $responsedata->answerids,
            $data->sessionid,
            $data->jqshowid,
            $data->cmid,
            $responsedata->questionid,
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
        $data->jsonresponse = json_encode($dataanswer, JSON_THROW_ON_ERROR);
        $data->statistics = $dataanswer['statistics'] ?? '0';

        return $data;
    }
}
