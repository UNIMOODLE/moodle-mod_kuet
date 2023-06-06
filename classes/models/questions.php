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
use dml_exception;
use dml_transaction_exception;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;
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
     * @param int $jqid // jqshow_question id
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqshowid
     * @param bool $preview
     * @return object
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     */
    public static function export_multichoice(int $jqid, int $cmid, int $sessionid, int $jqshowid, bool $preview = false) : object {
        $session = jqshow_sessions::get_record(['id' => $sessionid]);
        $jqshowquestion = jqshow_questions::get_record(['id' => $jqid]);
        $question = question_bank::load_question($jqshowquestion->get('questionid'));
        $numsessionquestions = jqshow_questions::count_records(['jqshowid' => $jqshowid, 'sessionid' => $sessionid]);
        $time = $jqshowquestion->get('timelimit') > 0 ? $jqshowquestion->get('timelimit') :
            get_config('mod_jqshow', 'questiontime');
        $order = $jqshowquestion->get('qorder');
        $a = new stdClass();
        $a->num = $order;
        $a->total = $numsessionquestions;
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
        $data->question_index_string = get_string('question_index_string', 'mod_jqshow', $a);
        $data->sessionprogress = round($order * 100 / $numsessionquestions);
        $data->questiontext =
            self::get_text($question->questiontext, $question->questiontextformat, $question->id, $question, 'questiontext');
        $data->questiontextformat = $question->questiontextformat;
        $data->hastime = $session->get('countdown') && $jqshowquestion->get('timelimit') > 0;
        $data->seconds = $time;
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
