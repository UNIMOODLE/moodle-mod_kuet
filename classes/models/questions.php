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
 * Question model
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
use Exception;
use JsonException;
use mod_kuet\api\groupmode;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use mod_kuet\persistents\kuet_user_progress;
use moodle_exception;
use qbank_previewquestion\question_preview_options;
use question_attempt;
use question_definition;
use question_engine;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot. '/question/type/multichoice/questiontype.php');
require_once($CFG->dirroot. '/question/type/truefalse/questiontype.php');
require_once($CFG->dirroot. '/question/engine/lib.php');
require_once($CFG->dirroot. '/question/engine/bank.php');

/**
 * Question model class
 */
class questions {
    /**
     * @const string multichoice
     */
    public const MULTICHOICE = 'multichoice';
    /**
     * @const string match
     */
    public const MATCH = 'match';
    /**
     * @const string truefalse
     */
    public const TRUE_FALSE = 'truefalse';
    /**
     * @const string shortanswer
     */
    public const SHORTANSWER = 'shortanswer';
    /**
     * @const string numerical
     */
    public const NUMERICAL = 'numerical';
    /**
     * @const string calculated
     */
    public const CALCULATED = 'calculated';
    /**
     * @const string description
     */
    public const DESCRIPTION = 'description';
    /**
     * @const string ddwtos
     */
    public const DDWTOS = 'ddwtos';

    /**
     * @const array types
     */
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

    /**
     * @const int failure
     */
    public const FAILURE = 0;
    /**
     * @const int sucess
     */
    public const SUCCESS = 1;

    /**
     * @const int partially
     */
    public const PARTIALLY = 2;
    /**
     * @const int noresponse
     */
    public const NORESPONSE = 3;
    /**
     * @const int not evaluable
     */
    public const NOTEVALUABLE = 4;
    /**
     * @const int invalid
     */
    public const INVALID = 5;
    /**
     * @const string characters to be stripped
     */
    public const CHARACTERS_TO_BE_STRIPPED = " \t\n\r\0\x0B\xC2\xA0";
    /*
     * @var int kuet instance id
     */
    protected int $kuetid;
    /**
     * @var int course module id
     */
    protected int $cmid;
    /**
     * @var int session id
     */
    protected int $sid;
    /** @var kuet_questions[] list */
    protected array $list;

    /**
     * Constructor
     *
     * @param int $kuetid
     * @param int $cmid
     * @param int $sid
     */
    public function __construct(int $kuetid, int $cmid, int $sid) {
        $this->kuetid = $kuetid;
        $this->cmid = $cmid;
        $this->sid = $sid;
    }

    /**
     * Set list of questions
     *
     * @return void
     */
    public function set_list() : void {
        $this->list = kuet_questions::get_records(['sessionid' => $this->sid, 'kuetid' => $this->kuetid], 'qorder', 'ASC');
    }

    /**
     * Get list of questions
     *
     * @return kuet_questions[]
     */
    public function get_list(): array {
        if (empty($this->list)) {
            $this->set_list();
        }
        return $this->list;
    }

    /**
     * Get number of questions
     *
     * @return int
     */
    public function get_num_questions(): int {
        return kuet_questions::count_records(['sessionid' => $this->sid, 'kuetid' => $this->kuetid]);
    }

    /**
     * Get the total time for a set of questions
     *
     * @return int
     * @throws coding_exception
     */
    public function get_sum_questions_times(): int {
        $questions = $this->get_list();
        $sessiontimedefault = (new kuet_sessions($this->sid))->get('questiontime');
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
     * Get common data for questions
     *
     * @param kuet_sessions $session
     * @param int $cmid
     * @param int $sessionid
     * @param int $kuetid
     * @param bool $preview
     * @param kuet_questions $kuetquestion
     * @param string $type
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected static function get_question_common_data(
        kuet_sessions $session,
        int $cmid,
        int $sessionid,
        int $kuetid,
        bool $preview,
        kuet_questions $kuetquestion,
        string $type
    ): stdClass {
        global $USER;
        $numsessionquestions = kuet_questions::count_records(['kuetid' => $kuetid, 'sessionid' => $sessionid]);
        $data = new stdClass();
        $data->cmid = $cmid;
        $data->sessionid = $sessionid;
        $data->kuetid = $kuetid;
        $data->questionid = $kuetquestion->get('questionid');
        $data->kid = $kuetquestion->get('id');
        $data->showquestionfeedback = (int)$session->get('showfeedback') === 1;
        $data->countdown = $session->get('countdown');
        $data->preview = $preview;
        $data->qtype = $type;
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
                $data->numquestions = $numsessionquestions;
                break;
            case sessions::INACTIVE_MANUAL:
            case sessions::PODIUM_MANUAL:
            case sessions::RACE_MANUAL:
                $order = $kuetquestion->get('qorder');
                $a = new stdClass();
                $a->num = $order;
                $a->total = $numsessionquestions;
                $data->programmedmode = false;
                $data->question_index_string = get_string('question_index_string', 'mod_kuet', $a);
                $data->numquestions = $numsessionquestions;
                $data->sessionprogress = round($order * 100 / $numsessionquestions);
                break;
            default:
                throw new moodle_exception('incorrect_sessionmode', 'mod_kuet', '',
                    [], get_string('incorrect_sessionmode', 'mod_kuet'));
        }
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
        return $data;
    }

    /**
     * Get question text
     *
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
     * @throws Exception
     */
    public static function get_text(
        int $cmid, string $text, int $textformat, int $id,
        question_definition $question, string $filearea, int $variant = 0, bool $noattempt = false
    ): string {
        global $DB;
        $contextmodule = context_module::instance($cmid);
        $usage = $DB->get_record('question_usages', ['component' => 'mod_kuet', 'contextid' => $contextmodule->id]);
        $options = new question_preview_options($question);
        $options->load_user_defaults();
        $options->set_from_request();
        $maxvariant = min($question->get_num_variants(), 100);
        if ($usage !== false) {
            $quba = question_engine::load_questions_usage_by_activity($usage->id);
        } else {
            $quba = question_engine::make_questions_usage_by_activity(
                'mod_kuet', context_module::instance($cmid));
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
     * Escape characters
     *
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
     * Add a group response to a question
     *
     * @param int $kuetid
     * @param kuet_sessions $session
     * @param int $kid
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
        int $kuetid, kuet_sessions $session, int $kid, int $questionid, int $userid, int $result, stdClass $response
    ) : void {
        // All groupmembers has the same response saved on db.
        $num = kuet_questions_responses::count_records(
            ['kuet' => $kuetid, 'session' => $session->get('id'), 'kid' => $kid, 'userid' => $userid]);
        if ($num > 0) {
            return;
        }
        // Groups.
        $gmemberids = groupmode::get_grouping_group_members_by_userid($session->get('groupings'), $userid);
        foreach ($gmemberids as $gmemberid) {
            kuet_questions_responses::add_response(
                $kuetid, $session->get('id'), $kid, $questionid, $gmemberid, $result, json_encode($response, JSON_THROW_ON_ERROR)
            );
        }
    }

    /**
     * Questions are evaluable by default
     *
     * @return bool
     */
    public static function is_evaluable() : bool {
        return true;
    }

    /**
     * Get the question class type in string format
     *
     * @param string $type
     * @return string
     * @throws moodle_exception
     */
    public static function get_question_class_by_string_type(string $type) : string {
        if ($type === 'match') {
            $type = 'matchquestion';
        }
        $type = "mod_kuet\models\\$type";
        if (!class_exists($type)) {
            throw new moodle_exception('question_nosuitable', 'mod_kuet', '',
                [], get_string('question_nosuitable', 'mod_kuet'));
        }
        return $type;
    }

    /**
     * Questions do not show statistics by default
     *
     * @return bool
     */
    public static function show_statistics() : bool {
        return false;
    }
}
