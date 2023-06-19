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
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\external\multichoice_external;
use mod_jqshow\persistents\jqshow;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_user_progress;
use moodle_exception;
use moodle_url;
use qbank_previewquestion\question_preview_options;
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
    public const MULTIPLE_CHOICE = 'multichoice';
    public const TYPES = [self::MULTIPLE_CHOICE];

    public const FAILURE = 0;
    public const SUCCESS = 1;
    public const NORESPONSE = 2;
    public const NOTEVALUABLE = 3;
    public const INVALID = 4;

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
        $numsessionquestions = jqshow_questions::count_records(['jqshowid' => $jqshowid, 'sessionid' => $sessionid]);
        $type = $question->get_type_name();
        $answers = [];
        $feedbacks = [];
        foreach ($question->answers as $response) {
            if (assert($response instanceof question_answer)) {
                $answertext = self::get_text($response->answer, $response->answerformat, $response->id, $question, 'answer');
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
        $data = new stdClass();
        $data->cmid = $cmid;
        $data->sessionid = $sessionid;
        $data->jqshowid = $jqshowid;
        $data->questionid = $jqshowquestion->get('questionid');
        $data->jqid = $jqshowquestion->get('id');
        switch ($session->get('sessionmode')) {
            case sessions::INACTIVE_PROGRAMMED:
            case sessions::PODIUM_PROGRAMMED:
            case sessions::RACE_PROGRAMMED:
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
                $data->sessionprogress = round($order * 100 / $numsessionquestions);
                break;
            default:
                throw new moodle_exception('incorrect_sessionmode', 'mod_jqshow', '',
                    [], get_string('incorrect_sessionmode', 'mod_jqshow'));
        }
        $data->questiontext =
            self::get_text($question->questiontext, $question->questiontextformat, $question->id, $question, 'questiontext');
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
        $data->numanswers = count($question->answers);
        $data->name = $question->name;
        $data->qtype = $type;
        $data->$type = true;
        if ($session->get('randomanswers') === 1) {
            shuffle($answers);
        }
        $data->answers = $answers;
        $data->feedbacks = $feedbacks;
        $data->template = 'mod_jqshow/questions/encasement';
        $data->programmedmode = ($session->get('sessionmode') === sessions::PODIUM_PROGRAMMED);
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
            $responsedata->answerid,
            $data->sessionid,
            $data->jqshowid,
            $data->cmid,
            $responsedata->questionid,
            $responsedata->timeleft, true
        );
        $data->hasfeedbacks = $dataanswer['hasfeedbacks'];
        $dataanswer['answerid'] = $responsedata->answerid;
        $data->seconds = $responsedata->timeleft;
        $data->statment_feedback = $dataanswer['statment_feedback'];
        $data->answer_feedback = $dataanswer['answer_feedback'];
        $data->correct_answers = $dataanswer['correct_answers'];
        $data->programmedmode = $dataanswer['programmedmode'];
        $data->jsonresponse = json_encode($dataanswer, JSON_THROW_ON_ERROR);
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
        global $OUTPUT, $USER;
        $session = new jqshow_sessions($sessionid);
        $jqshow = new jqshow($session->get('jqshowid'));
        $data = new stdClass();
        // TODO refactor.
        switch ($session->get('sessionmode')) {
            case sessions::INACTIVE_PROGRAMMED:
            case sessions::INACTIVE_MANUAL:
                $data->cmid = $cmid;
                $data->sessionid = $sessionid;
                $data->jqshowid = $session->get('jqshowid');
                $data->endsessionimage = $OUTPUT->image_url('f/end_session', 'mod_jqshow')->out(false);
                $data->qtype = 'endsession';
                $data->endsession = true;
                $data->courselink = (new moodle_url('/course/view.php', ['id' => $jqshow->get('course')]))->out(false);
                $data->reportlink = (new moodle_url('/mod/jqshow/reports.php',
                    ['cmid' => $cmid, 'sid' => $sessionid, 'userid' => $USER->id]))->out(false);
                $cmcontext = context_module::instance($cmid);
                if ($session->get('sessionmode') === sessions::INACTIVE_MANUAL &&
                    has_capability('mod/jqshow:startsession', $cmcontext)) {
                    jqshow_sessions::mark_session_finished($sessionid);
                }
                break;
            case sessions::PODIUM_PROGRAMMED:
            case sessions::PODIUM_MANUAL:
                // TODO export Podium layout for all users.
                break;
            case sessions::RACE_PROGRAMMED:
            case sessions::RACE_MANUAL:
                // TODO not yet designed.
                break;
            default:
                throw new moodle_exception('incorrect_sessionmode', 'mod_jqshow', '',
                    [], get_string('incorrect_sessionmode', 'mod_jqshow'));
        }
        return $data;
    }

    /**
     * @param string $text
     * @param int $textformat
     * @param int $id
     * @param question_definition $question
     * @param string $filearea
     * @return string
     * @throws dml_transaction_exception
     */
    public static function get_text(
        string $text, int $textformat, int $id, question_definition $question, string $filearea
    ) : string {
        global $DB, $USER;
        $maxvariant = min($question->get_num_variants(), 100); // QUESTION_PREVIEW_MAX_VARIANTS.
        $options = new question_preview_options($question);
        $options->load_user_defaults();
        $options->set_from_request();
        $quba = question_engine::make_questions_usage_by_activity(
            'core_question_preview', context_user::instance($USER->id));
        $quba->set_preferred_behaviour($options->behaviour);
        $slot = $quba->add_question($question, $options->maxmark);
        if ($options->variant) {
            $options->variant = min($maxvariant, max(1, $options->variant));
        } else {
            $options->variant = rand(1, $maxvariant);
        }
        $quba->start_question($slot, $options->variant);
        $transaction = $DB->start_delegated_transaction();
        /* TODO check, as one usage is saved for each of the images in the question,
        and no more than 1 should be saved per question, as in the Moodle preview. */
        question_engine::save_questions_usage_by_activity($quba);
        $transaction->allow_commit();

        $qa = new question_attempt($question, $quba->get_id());
        $qa->set_slot($slot);
        return $qa->get_question()->format_text($text, $textformat, $qa, 'question', $filearea, $id);
    }
}
