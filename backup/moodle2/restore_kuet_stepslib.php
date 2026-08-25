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
 * Restore kuet steps
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <juanpablo.decastro@uva.es>
 * @author     Juan Pablo de Castro  <juan.pablo.de.castro@gmail.com>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



/**
 * Kuet activity structure steps class
 */
class restore_kuet_activity_structure_step extends restore_questions_activity_structure_step {
    /**
     * Define structure
     *
     * @return mixed
     * @throws base_step_exception
     * @throws restore_step_exception
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $paths = [];
        $paths[] = new restore_path_element('kuet', '/activity/kuet');
        $paths[] = new restore_path_element('kuet_session', '/activity/kuet/sessions/session');
        $question = new restore_path_element('kuet_question', '/activity/kuet/questions/question');
        $paths[] = $question;
        $this->add_question_references($question, $paths);
        if ($userinfo) {
            $paths[] = new restore_path_element('kuet_grade', '/activity/kuet/grades/grade');
            $paths[] = new restore_path_element('kuet_session_grade', '/activity/kuet/sessions_grades/session_grade');
            $paths[] = new restore_path_element('kuet_user_progres', '/activity/kuet/user_progress/user_progres');
            $paths[] = new restore_path_element('kuet_questions_response', '/activity/kuet/questions_responses/questions_response');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process kuet data
     *
     * @param $data
     * @return void
     * @throws base_step_exception
     * @throws dml_exception
     */
    protected function process_kuet($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newitemid = $DB->insert_record('kuet', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process kuet grade
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_kuet_grade($data) {
        global $DB;
        $data = (object)$data;
        $data->kuet = $this->get_new_parentid('kuet');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('kuet_grades', $data);
    }

    /**
     * Keep exact IDs consistent with the core's final reconnection to shared banks.
     *
     * @param int $oldid Backed-up question version ID.
     * @return int Restored or reused question ID, or zero if missing.
     */
    private function mapped_question_id(int $oldid): int {
        global $DB;
        if ($this->task->is_samesite()) {
            $contextid = $DB->get_field_sql('SELECT qc.contextid
                FROM {question_versions} qv
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                WHERE qv.questionid = ?', [$oldid]);
            if ($contextid && !$this->get_mappingid('context', $contextid)) {
                $context = context::instance_by_id($contextid, IGNORE_MISSING);
                if ($context && $context->contextlevel === CONTEXT_MODULE && has_capability('mod/qbank:view', $context)) {
                    return $oldid;
                }
            }
        }
        return (int)$this->get_mappingid('question', $oldid);
    }
    /**
     * Process kuet questions
     *
     * @param $data
     * @return void
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_kuet_question($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $newquestionid = $this->mapped_question_id((int)$data->questionid);
        // Never reuse an unmapped ID from another site. Keep an explicit missing-question placeholder.
        $data->questionid = $newquestionid ?: 0;
        if (!$newquestionid) {
            $data->isvalid = 0;
        }
        $data->sessionid = $this->get_mappingid('kuet_sessions', $data->sessionid);
        $data->kuetid = $this->get_new_parentid('kuet');
        $newitemid = $DB->insert_record('kuet_questions', $data);
        $this->set_mapping('kuet_questions', $oldid, $newitemid);
        $this->set_mapping('kuet_question', $oldid, $newitemid);
    }

    /**
     * Process kuet question responses
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_kuet_questions_response($data) {
        global $DB;
        $data = (object)$data;
        $data->kuet = $this->get_new_parentid('kuet');
        $data->session = $this->get_mappingid('kuet_sessions', $data->session);
        $data->kid = $this->get_mappingid('kuet_questions', $data->kid);
        $newquestionid = $this->mapped_question_id((int)$data->questionid);
        if ($newquestionid) {
            $data->questionid = $newquestionid;
            $data->response = $this->replace_answerids($data->response, $newquestionid);
        }
        $data->questionid = $newquestionid ?: 0;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('kuet_questions_responses', $data);
    }

    /**
     * Process kuet sessions
     *
     * @param $data
     * @return void
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_kuet_session($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->kuetid = $this->get_new_parentid('kuet');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->groupings = $this->get_mappingid('groupings', $data->groupings);
        $data->questionslocked = $data->questionslocked ?? (int)in_array((int)$data->status, [0, 2], true);
        $newitemid = $DB->insert_record('kuet_sessions', $data);
        $this->set_mapping('kuet_sessions', $oldid, $newitemid);
    }

    /**
     * Process kuet session grades
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_kuet_session_grade($data) {
        global $DB;
        $data = (object)$data;
        $data->session = $this->get_mappingid('kuet_sessions', $data->session);
        $data->kuet = $this->get_new_parentid('kuet');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('kuet_sessions_grades', $data);
    }

    /**
     * Process kuet user progress
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_kuet_user_progres($data) {
        global $DB;
        $data = (object)$data;
        $data->kuet = $this->get_new_parentid('kuet');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->session = $this->get_mappingid('kuet_sessions', $data->session);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('kuet_user_progress', $data);
    }

    /**
     * Inform the new usage id - not used
     *
     * @param $newusageid
     * @return void
     */
    protected function inform_new_usage_id($newusageid) {
        // Not used in this activity module.
    }

    /**
     * After execute restore actions
     *
     * @return void
     */
    protected function after_execute() {
        global $DB;
        parent::after_execute();
        $this->add_related_files('mod_kuet', 'intro', null);
        // Old backups do not contain question_reference elements. Use their remapped exact version.
        $slots = $DB->get_records('kuet_questions', ['kuetid' => $this->get_new_parentid('kuet')]);
        foreach ($slots as $slot) {
            if (!\mod_kuet\question\version_resolver::reference($slot->id) && $slot->questionid) {
                $version = $DB->get_record('question_versions', ['questionid' => $slot->questionid]);
                if ($version) {
                    $DB->insert_record('question_references', (object)[
                        'usingcontextid' => $this->task->get_contextid(),
                        'component' => 'mod_kuet', 'questionarea' => 'session_question',
                        'itemid' => $slot->id, 'questionbankentryid' => $version->questionbankentryid,
                        'version' => $version->version,
                    ]);
                }
            }
        }
    }

    /** Restore references using KUET's position mapping, not quiz_question_instance. */
    public function process_question_reference($data) {
        global $DB;
        $data = (object)$data;
        $entryid = $this->get_mappingid('question_bank_entry', $data->questionbankentryid);
        if (!$entryid) {
            throw new restore_step_exception('missing_question_bank_entry_mapping', $data->questionbankentryid);
        }
        unset($data->id);
        $data->component = 'mod_kuet';
        $data->questionarea = 'session_question';
        $data->usingcontextid = $this->task->get_contextid();
        $data->itemid = $this->get_new_parentid('kuet_question');
        $data->questionbankentryid = $entryid;
        $DB->insert_record('question_references', $data);
    }

    /**
     * Replace answer ids
     *
     * @param string $responsejson
     * @param int $newquestionid
     * @return string
     */
    private function replace_answerids(string $responsejson, int $newquestionid): string {
        if (!$newquestionid) {
            return $responsejson;
        }
        $resp = json_decode($responsejson, false);
        if ($resp->{'type'} == 'truefalse') {
            return $this->replace_answerids_truefalse($resp, $newquestionid);
        } else if ($resp->{'type'} == 'multichoice') {
            return $this->replace_answerids_multichoice($resp, $newquestionid);
        }
        return $responsejson;
    }

    /**
     * Replace answer ids for multichoice questions
     *
     * @param stdClass $response
     * @param $newquestionid
     * @return string
     */
    private function replace_answerids_multichoice(stdClass $response, $newquestionid): string {
        $answertexts = $response->{'answertexts'};
        $answerids = explode(',', $response->{'answerids'});
        $newanswerids = [];
        $answertexts = json_decode($answertexts);
        $question = question_bank::load_question($newquestionid);
        foreach ($question->answers as $key => $answer) {
            foreach ($answerids as $answerid) {
                $text = $answertexts->{$answerid};
                if (strcmp($text, strip_tags($answer->answer)) == 0) {
                    $newanswerids[] = $key;
                }
            }
        }
        $newanswerids = implode(',', $newanswerids);
        $response->{'answerids'} = $newanswerids;

        return json_encode($response);
    }

    /**
     * Replace answer ids for true-false questions
     *
     * @param stdClass $response
     * @param $newquestionid
     * @return string
     */
    private function replace_answerids_truefalse(stdClass $response, $newquestionid): string {
        $question = question_bank::load_question($newquestionid);
        $answertext = $response->{'answertexts'};
        $newanswerids = $question->falseanswerid;
        if ($answertext == '1' && $question->rightanswer) {
            $newanswerids  = $question->trueanswerid;
        }
        $response->{'answerids'} = $newanswerids;
        return json_encode($response);
    }
}
