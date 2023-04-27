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
use context_module;
use dml_exception;
use mod_jqshow\output\views\question_preview;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;
use qbank_previewquestion\question_preview_options;
use qtype_multichoice;
use question_answer;
use question_attempt;
use question_bank;
use question_definition;
use question_engine;
use stdClass;
use context_user;
require_once($CFG->dirroot. '/question/type/multichoice/questiontype.php');
require_once($CFG->dirroot. '/question/engine/lib.php');
require_once($CFG->dirroot. '/question/engine/bank.php');
// require_once($CFG->dirroot. '/question/bank/previewquestion/preview.php');
defined('MOODLE_INTERNAL') || die();

class questions {

    const MULTIPLE_CHOICE = 'multichoice';
    const TYPES = [self::MULTIPLE_CHOICE];
    protected int $jqshowid;
    protected int $cmid;
    protected int $sid;
    /** @var jqshow_questions[] list */
    protected array $list;

    public function __construct(int $jqshowid, int $cmid, int $sid) {
        $this->jqshowid = $jqshowid;
        $this->cmid = $cmid;
        $this->sid = $sid;
    }

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
     * @param int $qid
     * @return object
     * @throws dml_exception
     */
    public static function export_multichoice(int $jqid, int $cmid, int $sessionid, int $jqshowid, $preview = false) : object {

        $jqshowquestion = jqshow_questions::get_record(['id' => $jqid]);
        $question = question_bank::load_question($jqshowquestion->get('questionid'));
        $numsessionquestions = jqshow_questions::count_records(['jqshowid' => $jqshowid, 'sessionid' => $sessionid]);
        $time = $jqshowquestion->get('hastimelimit') ? $jqshowquestion->get('time') : get_config('mod_jqshow', 'questiontime');
        $order = $jqshowquestion->get('qorder');
        $a = new stdClass();
        $a->num = $order;
        $a->total = $numsessionquestions;
        $type = $question->get_type_name();
        $answers = [];
        $feedbacks = [];
        /** @var question_answer $response */
        foreach ($question->answers as $response) {
            $text = self::get_text($response->answer, $response->answerformat, $response->id, $question);
            $answers[] = [
                'answerid' => $response->id,
                'questionid' => $jqid,
                'answertext' => $text,
                'fraction' => $response->fraction,
            ];
            $feedbacks[] = [
                'answerid' => $response->id,
                'feedback' => $response->feedback,
                'feedbackformat' => $response->feedbackformat,
            ];
        }
        $data = new stdClass();
        $data->cmid = $cmid;
        $data->sessionid = $sessionid;
        $data->jqshowid = $jqshowid;
        $data->question_index_string = get_string('question_index_string', 'mod_jqshow', $a);
        $data->sessionprogress = round($order * 100 / $numsessionquestions);
        $data->questiontext = $question->questiontext;
        $data->questiontextformat = $question->questiontextformat;
        $data->hastime = $jqshowquestion->get('hastimelimit');
        $data->seconds = $time;
        $data->preview = $preview;
        $data->numanswers = count($question->answers);
        $data->name = $question->name;
        $data->qtype = $type;
        $data->$type = true;
        $data->answers = $answers;
        $data->feedbacks = $feedbacks;
        $data->template = 'mod_jqshow/questions/encasement';

        return $data;
    }

    /**
     * @param string $answertext
     * @param int $answerformat
     * @param int $answerid
     * @param question_definition $question
     * @return string
     * @throws \dml_transaction_exception
     */
    public static function get_text(string $answertext, int $answerformat, int $answerid, question_definition $question) : string {
        global $DB, $USER;
        $maxvariant = min($question->get_num_variants(), 100);// QUESTION_PREVIEW_MAX_VARIANTS.
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
        question_engine::save_questions_usage_by_activity($quba);
        $transaction->allow_commit();

        $qa = new question_attempt($question, $quba->get_id());
        $qa->set_slot($slot);
        return $qa->get_question()->format_text($answertext, $answerformat, $qa, 'question', 'answer', $answerid);
    }
}
