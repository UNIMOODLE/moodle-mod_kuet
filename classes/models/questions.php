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
use dml_exception;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;
use qtype_multichoice;
use question_answer;
use question_bank;
use stdClass;
require_once($CFG->dirroot. '/question/type/multichoice/questiontype.php');
require_once($CFG->dirroot. '/question/engine/bank.php');
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
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqshowid
     * @param bool $preview
     * @return object
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function export_multichoice(int $jqid, int $cmid, int $sessionid, int $jqshowid, $preview = false) : object {

        $jqshowquestion = jqshow_questions::get_record(['id' => $jqid]);
        $question2 = question_bank::load_question($jqshowquestion->get('questionid'));
        $numsessionquestions = jqshow_questions::count_records(['jqshowid' => $jqshowid, 'sessionid' => $sessionid]);

        $time = $jqshowquestion->get('hastimelimit') ? $jqshowquestion->get('timelimit') : get_config('mod_jqshow', 'questiontime');
        $order = $jqshowquestion->get('qorder');
        $a = new stdClass();
        $a->num = $order;
        $a->total = $numsessionquestions;
        $type = $question2->get_type_name();
        $answers = [];
        $feedbacks = [];
        /** @var question_answer $response */
        foreach ($question2->answers as $response) {
            $answers[] = [
                'answerid' => $response->id,
                'questionid' => $jqshowquestion->get('questionid'),
                'answertext' => $response->answer,
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
        $data->questionid = $jqshowquestion->get('questionid');
        $data->jqid = $jqshowquestion->get('id');
        $data->question_index_string = get_string('question_index_string', 'mod_jqshow', $a);
        $data->sessionprogress = round($order * 100 / $numsessionquestions);
        $data->questiontext = $question2->questiontext;
        $data->questiontextformat = $question2->questiontextformat;
        $data->hastime = $jqshowquestion->get('hastimelimit');
        $data->seconds = $time;
        $data->preview = $preview;
        $data->numanswers = count($question2->answers);
        $data->name = $question2->name;
        $data->qtype = $type;
        $data->$type = true;
        $data->answers = $answers;
        $data->feedbacks = $feedbacks;
        $data->template = 'mod_jqshow/questions/encasement';
        return $data;
    }
}
