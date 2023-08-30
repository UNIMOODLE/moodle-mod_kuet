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
use core_availability\info_module;
use dml_exception;
use dml_transaction_exception;
use Exception;
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\external\getfinalranking_external;
use mod_jqshow\external\match_external;
use mod_jqshow\external\multichoice_external;
use mod_jqshow\persistents\jqshow;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_user_progress;
use moodle_exception;
use moodle_url;
use qbank_previewquestion\question_preview_options;
use qtype_match_question;
use qtype_multichoice;
use qtype_multichoice_multi_question;
use qtype_multichoice_single_question;
use question_answer;
use question_attempt;
use question_bank;
use question_definition;
use question_engine;
use stdClass;
use context_user;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot. '/question/type/multichoice/questiontype.php');
require_once($CFG->dirroot. '/question/engine/lib.php');
require_once($CFG->dirroot. '/question/engine/bank.php');

class questions {
    public const MULTICHOICE = 'multichoice';
    public const MATCH = 'match';
    public const TYPES = [self::MULTICHOICE, self::MATCH];

    public const FAILURE = 0; // String: qstatus_0.
    public const SUCCESS = 1; // String: qstatus_1.
    public const PARTIALLY = 2; // String: qstatus_2.
    public const NORESPONSE = 3; // String: qstatus_3.
    public const NOTEVALUABLE = 4; // String: qstatus_4.
    public const INVALID = 5; // String: qstatus_5.

    protected int $jqshowid;
    protected int $cmid;
    protected int $sid;
    /** @var jqshow_questions[] list */
    protected array $list;

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @param int $sid
     */
    public function __construct(int $jqshowid, int $cmid, int $sid) {
        $this->jqshowid = $jqshowid;
        $this->cmid = $cmid;
        $this->sid = $sid;
    }

    /**
     * @return void
     */
    public function set_list() {
        $this->list = jqshow_questions::get_records(['sessionid' => $this->sid, 'jqshowid' => $this->jqshowid], 'qorder', 'ASC');
    }

    /**
     * @return jqshow_questions[]
     */
    public function get_list(): array {
        if (empty($this->list)) {
            $this->set_list();
        }
        return $this->list;
    }

    /**
     * @return int
     */
    public function get_num_questions(): int {
        return jqshow_questions::count_records(['sessionid' => $this->sid, 'jqshowid' => $this->jqshowid]);
    }

    /**
     * @return int
     * @throws coding_exception
     */
    public function get_sum_questions_times(): int {
        $questions = $this->get_list();
        $sessiontimedefault = (new jqshow_sessions($this->sid))->get('questiontime');
        $time = 0;
        foreach ($questions as $question) {
            if ($question->get('timelimit') !== 0) {
                $time = $question->get('timelimit') + $time;
            } else {
                $time = $sessiontimedefault + $time;
            }
        }
        return $time;
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
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function export_multichoice(int $jqid, int $cmid, int $sessionid, int $jqshowid, bool $preview = false) : object {
        global $USER;
        $session = jqshow_sessions::get_record(['id' => $sessionid]);
        $jqshowquestion = jqshow_questions::get_record(['id' => $jqid]);
        $question = question_bank::load_question($jqshowquestion->get('questionid'));
        if (!assert($question instanceof qtype_multichoice_single_question) &&
            !assert($question instanceof qtype_multichoice_multi_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        $type = $question->get_type_name();
        $data = self::get_question_common_data($session, $jqid, $cmid, $sessionid, $jqshowid, $preview, $jqshowquestion);
        $data->qtype = $type;
        $data->$type = true;
        $data->questiontext =
            self::get_text($cmid, $question->questiontext, $question->questiontextformat, $question->id, $question, 'questiontext');
        $data->questiontextformat = $question->questiontextformat;
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
     * @return stdClass
     * @throws JsonException
     * @throws invalid_persistent_exception
     * @throws invalid_parameter_exception
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function export_multichoice_response(stdClass $data, string $response): stdClass {
        $responsedata = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
        $data->answered = true;
        $dataanswer = multichoice_external::multichoice(
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
            // TODO check, as the wide variety of possible HTML may result in errors when encoding and decoding the json.
            $dataanswer['statment_feedback'] = trim(html_entity_decode($dataanswer['statment_feedback']), " \t\n\r\0\x0B\xC2\xA0");
            $dataanswer['statment_feedback'] = str_replace('"', '\"', $dataanswer['statment_feedback']);
            $dataanswer['answer_feedback'] = trim(html_entity_decode($dataanswer['answer_feedback']), " \t\n\r\0\x0B\xC2\xA0");
            $dataanswer['answer_feedback'] = str_replace('"', '\"', $dataanswer['answer_feedback']);
        }
        $data->statment_feedback = $dataanswer['statment_feedback'];
        $data->answer_feedback = $dataanswer['answer_feedback'];
        $data->jsonresponse = json_encode($dataanswer, JSON_THROW_ON_ERROR);
        $data->statistics = $dataanswer['statistics'] ?? '0';
        return $data;
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
        if (!assert($question instanceof qtype_match_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        $type = $question->get_type_name();
        $data = self::get_question_common_data($session, $jqid, $cmid, $sessionid, $jqshowid, $preview, $jqshowquestion);
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
        $responsedata = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
        $data->answered = true;
        $jsonresponse = json_encode($responsedata->response, JSON_THROW_ON_ERROR);
        $dataanswer = match_external::match(
            $jsonresponse,
            $result,
            $data->sessionid,
            $data->jqshowid,
            $data->cmid,
            $responsedata->questionid,
            $data->jqid,
            $responsedata->timeleft,
            true
        );
        $data->hasfeedbacks = $dataanswer['hasfeedbacks'];
        $data->seconds = $responsedata->timeleft;
        $data->correct_answers = $dataanswer['correct_answers'];
        $data->programmedmode = $dataanswer['programmedmode'];
        $data->jsonresponse = $jsonresponse;
        if ($data->hasfeedbacks) {
            // TODO check, as the wide variety of possible HTML may result in errors when encoding and decoding the json.
            $dataanswer['statment_feedback'] = trim(html_entity_decode($dataanswer['statment_feedback']), " \t\n\r\0\x0B\xC2\xA0");
            $dataanswer['statment_feedback'] = str_replace('"', '\"', $dataanswer['statment_feedback']);
            $dataanswer['answer_feedback'] = trim(html_entity_decode($dataanswer['answer_feedback']), " \t\n\r\0\x0B\xC2\xA0");
            $dataanswer['answer_feedback'] = str_replace('"', '\"', $dataanswer['answer_feedback']);
        }
        $data->statment_feedback = $dataanswer['statment_feedback'];
        $data->answer_feedback = $dataanswer['answer_feedback'];
        $data->statistics = $dataanswer['statistics'] ?? '0';
        // TODO feedbacks.
        return $data;
    }

    /**
     * @param jqshow_sessions $session
     * @param int $jqid
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqshowid
     * @param bool $preview
     * @param jqshow_questions $jqshowquestion
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws moodle_exception
     */
    private static function get_question_common_data(
        jqshow_sessions $session,
        int $jqid,
        int $cmid,
        int $sessionid,
        int $jqshowid,
        bool $preview,
        jqshow_questions $jqshowquestion
    ): stdClass {
        global $USER;
        $numsessionquestions = jqshow_questions::count_records(['jqshowid' => $jqshowid, 'sessionid' => $sessionid]);
        $data = new stdClass();
        $data->cmid = $cmid;
        $data->sessionid = $sessionid;
        $data->jqshowid = $jqshowid;
        $data->questionid = $jqshowquestion->get('questionid');
        $data->jqid = $jqshowquestion->get('id');
        $data->showquestionfeedback = (int)$session->get('showfeedback') === 1;
        $data->countdown = $session->get('countdown');
        $data->preview = $preview;
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
        $data->template = 'mod_jqshow/questions/encasement';
        return $data;
    }

    /**
     * @param int $cmid
     * @param int $sessionid
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function export_endsession(int $cmid, int $sessionid): object {
        global $USER;
        $session = new jqshow_sessions($sessionid);
        $jqshow = new jqshow($session->get('jqshowid'));
        $data = new stdClass();
        $data->cmid = $cmid;
        $data->sessionid = $sessionid;
        $data->jqshowid = $session->get('jqshowid');
        $data->courselink = (new moodle_url('/course/view.php', ['id' => $jqshow->get('course')]))->out(false);
        $data->reportlink = (new moodle_url('/mod/jqshow/reports.php',
            ['cmid' => $cmid, 'sid' => $sessionid, 'userid' => $USER->id]))->out(false);
        $contextmodule = context_module::instance($cmid);
        switch ($session->get('sessionmode')) {
            case sessions::INACTIVE_PROGRAMMED:
            case sessions::INACTIVE_MANUAL:
                $data = self::get_normal_endsession($data);
                break;
            case sessions::PODIUM_PROGRAMMED:
            case sessions::PODIUM_MANUAL:
            case sessions::RACE_MANUAL:
            case sessions::RACE_PROGRAMMED:
                if ((int)$session->get('showfinalgrade') === 0) {
                    $data = self::get_normal_endsession($data);
                } else {
                    $data = (object)getfinalranking_external::getfinalranking($sessionid, $cmid);
                    $data = self::get_normal_endsession($data);
                    $data->endsession = true;
                    $data->ranking = true;
                    $data->isteacher = has_capability('mod/jqshow:startsession', $contextmodule);
                    // TODO export ranking.
                }
                break;
            default:
                throw new moodle_exception('incorrect_sessionmode', 'mod_jqshow', '',
                    [], get_string('incorrect_sessionmode', 'mod_jqshow'));
        }
        return $data;
    }

    /**
     * @param stdClass $data
     * @return stdClass
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    private static function get_normal_endsession(stdClass $data): stdClass {
        global $OUTPUT;
        $data->questionid = 0;
        $data->jqid = 0;
        $data->question_index_string = '';
        $data->endsessionimage = $OUTPUT->image_url('f/end_session', 'mod_jqshow')->out(false);
        $data->qtype = 'endsession';
        $data->endsession = true;
        return $data;
    }

    /**
     * @param int $cmid
     * @param string $text
     * @param int $textformat
     * @param int $id
     * @param question_definition $question
     * @param string $filearea
     * @return string
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws Exception
     */
    public static function get_text(
        int $cmid, string $text, int $textformat, int $id, question_definition $question, string $filearea
    ) : string {
        global $DB;
        $contextmodule = context_module::instance($cmid);
        $usage = $DB->get_record('question_usages', ['component' => 'mod_jqshow', 'contextid' => $contextmodule->id]);
        $options = new question_preview_options($question);
        $options->load_user_defaults();
        $options->set_from_request();
        $maxvariant = min($question->get_num_variants(), 100);
        if ($usage !== false) {
            $quba = question_engine::load_questions_usage_by_activity($usage->id);
        } else {
            $quba = question_engine::make_questions_usage_by_activity(
                'mod_jqshow', context_module::instance($cmid));
        }
        $quba->set_preferred_behaviour('immediatefeedback');
        $slot = $quba->add_question($question, $options->maxmark);
        if ($options->variant) {
            $options->variant = min($maxvariant, max(1, $options->variant));
        } else {
            $options->variant = random_int(1, $maxvariant);
        }
        $quba->start_question($slot, $options->variant);
        if ($usage === false) {
            $transaction = $DB->start_delegated_transaction();
            question_engine::save_questions_usage_by_activity($quba);
            $transaction->allow_commit();
        }
        $qa = new question_attempt($question, $quba->get_id());
        $qa->set_slot($slot);
        return $qa->get_question()->format_text($text, $textformat, $qa, 'question', $filearea, $id);
    }
}
