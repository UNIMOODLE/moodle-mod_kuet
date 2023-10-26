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
use JsonException;
use mod_jqshow\api\groupmode;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_user_progress;
use moodle_exception;
use qbank_previewquestion\question_preview_options;
use question_attempt;
use question_bank;
use question_definition;
use question_engine;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot. '/question/type/multichoice/questiontype.php');
require_once($CFG->dirroot. '/question/type/truefalse/questiontype.php');
require_once($CFG->dirroot. '/question/engine/lib.php');
require_once($CFG->dirroot. '/question/engine/bank.php');

class questions {
    public const MULTICHOICE = 'multichoice';
    public const MATCH = 'match';
    public const TRUE_FALSE = 'truefalse';
    public const SHORTANSWER = 'shortanswer';
    public const NUMERICAL = 'numerical';
    public const CALCULATED = 'calculated';
    public const DESCRIPTION = 'description';
    public const DDWTOS = 'ddwtos';

    public const TYPES = [
        self::MULTICHOICE,
        self::MATCH,
        self::TRUE_FALSE,
        self::SHORTANSWER,
        self::NUMERICAL,
        self::CALCULATED,
        self::DESCRIPTION,
        self::DDWTOS
    ];

    public const FAILURE = 0;
    public const SUCCESS = 1;
    public const PARTIALLY = 2;
    public const NORESPONSE = 3;
    public const NOTEVALUABLE = 4;
    public const INVALID = 5;
    public const CHARACTERS_TO_BE_STRIPPED = " \t\n\r\0\x0B\xC2\xA0";
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
     * @param jqshow_sessions $session
     * @param int $jqid
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqshowid
     * @param bool $preview
     * @param jqshow_questions $jqshowquestion
     * @param string $type
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected static function get_question_common_data(
        jqshow_sessions $session,
        int $jqid,
        int $cmid,
        int $sessionid,
        int $jqshowid,
        bool $preview,
        jqshow_questions $jqshowquestion,
        string $type
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
        $data->qtype = $type;
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
                $data->numquestions = $numsessionquestions;
                break;
            case sessions::INACTIVE_MANUAL:
            case sessions::PODIUM_MANUAL:
            case sessions::RACE_MANUAL:
                $order = $jqshowquestion->get('qorder');
                $a = new stdClass();
                $a->num = $order;
                $a->total = $numsessionquestions;
                $data->programmedmode = false;
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
        return $data;
    }

    /**
     * @param int $cmid
     * @param string $text
     * @param int $textformat
     * @param int $id
     * @param question_definition $question
     * @param string $filearea
     * @param int $variant
     * @param bool $noattempt
     * @return string
     * @throws dml_exception
     * @throws dml_transaction_exception
     */
    public static function get_text(
        int $cmid, string $text, int $textformat, int $id,
        question_definition $question, string $filearea, int $variant = 0, bool $noattempt = false
    ): string {
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
        if ($noattempt === false) {
            $question->variant = $options->variant;
        }
        if ($variant === 0) {
            $quba->start_question($slot, $options->variant);
        } else {
            $quba->start_question($slot, $variant);
        }
        if ($usage === false) {
            $transaction = $DB->start_delegated_transaction();
            question_engine::save_questions_usage_by_activity($quba);
            $transaction->allow_commit();
        }
        $qa = new question_attempt($question, $quba->get_id());
        $qa->set_slot($slot);
        return $qa->get_question()->format_text($text, $textformat, $qa, 'question', $filearea, $id);
    }

    /**
     * @param string $text
     * @return string
     */
    protected static function escape_characters(string $text): string {
        // TODO check, as the wide variety of possible HTML may result in errors when encoding and decoding the json.
        $text = trim(html_entity_decode($text), self::CHARACTERS_TO_BE_STRIPPED);
        $replace = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
        return $replace ?? $text;
    }

    /**
     * @param $jqshowid
     * @param jqshow_sessions $session
     * @param int $jqid
     * @param int $questionid
     * @param int $userid
     * @param int $result
     * @param stdClass $response
     * @return void
     * @throws JsonException
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    protected static function add_group_response(
        $jqshowid, jqshow_sessions $session, int $jqid, int $questionid, int $userid, int $result, stdClass $response
    ) : void {
        // All groupmembers has the same response saved on db.
        $num = jqshow_questions_responses::count_records(
            ['jqshow' => $jqshowid, 'session' => $session->get('id'), 'jqid' => $jqid, 'userid' => $userid]);
        if ($num > 0) {
            return;
        }
        // Groups.
        $gmemberids = groupmode::get_grouping_group_members_by_userid($session->get('groupings'), $userid);
        foreach ($gmemberids as $gmemberid) {
            jqshow_questions_responses::add_response(
                $jqshowid, $session->get('id'), $jqid, $questionid, $gmemberid, $result, json_encode($response, JSON_THROW_ON_ERROR)
            );
        }
    }

    /**
     * @return bool
     */
    public static function is_evaluable() : bool {
        return true;
    }

    /**
     * @param string $type
     * @return string
     * @throws moodle_exception
     */
    public static function get_question_class_by_string_type(string $type) : string {
        if ($type === 'match') {
            $type = 'matchquestion';
        }
        $type = "mod_jqshow\models\\$type";
        if (!class_exists($type)) {
            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        return $type;
    }
    /**
     * @param jqshow_questions $jqquestion
     * @param jqshow_sessions $session
     * @return int
     * @throws coding_exception
     */
    public static function get_question_time(jqshow_questions $jqquestion, jqshow_sessions $session) : int {
        $qtime = $jqquestion->get('timelimit');
        if ((int)$session->get('timemode') === sessions::SESSION_TIME) {
            $sessiontime = $session->get('sessiontime');
            $numq = jqshow_questions::count_records(['sessionid' => $session->get('id'),
                'jqshowid' => $session->get('jqshowid')]);
            $qtime = round($sessiontime / $numq);
        } else if ((int)$session->get('timemode') === sessions::QUESTION_TIME) {
            $qtime = ($qtime > 0) ? $qtime : $session->get('questiontime');
        }
        return $qtime;
    }
    /**
     * @return bool
     */
    public static function show_statistics() : bool {
        return false;
    }
}
