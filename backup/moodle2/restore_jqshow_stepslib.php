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

defined('MOODLE_INTERNAL') || die();

class restore_jqshow_activity_structure_step extends restore_questions_activity_structure_step {

    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $paths = [];
        $paths[] = new restore_path_element('jqshow', '/activity/jqshow');
        $paths[] = new restore_path_element('jqshow_session','/activity/jqshow/sessions/session');
        $question = new restore_path_element('jqshow_question', '/activity/jqshow/questions/question');
        $paths[] = $question;
        $this->add_question_usages($question, $paths);
        if ($userinfo) {
            $paths[] = new restore_path_element('jqshow_grade','/activity/jqshow/grades/grade');
            $paths[] = new restore_path_element('jqshow_session_grade','/activity/jqshow/sessions_grades/session_grade');
            $paths[] = new restore_path_element('jqshow_user_progres', '/activity/jqshow/user_progress/user_progres');
            $paths[] = new restore_path_element('jqshow_questions_response','/activity/jqshow/questions_responses/questions_response');
        }
        return $this->prepare_activity_structure($paths);
    }

    protected function process_jqshow($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newitemid = $DB->insert_record('jqshow', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_jqshow_grade($data) {
        global $DB;
        $data = (object)$data;
        $data->jqshow = $this->get_new_parentid('jqshow');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('jqshow_grades', $data);
    }

    protected function process_jqshow_question($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $newquestionid = $this->get_mappingid('question', $data->questionid);
        if ($newquestionid) {
            $data->questionid = $newquestionid;
        }
        $data->sessionid = $this->get_mappingid('jqshow_sessions', $data->sessionid);
        $data->jqshowid = $this->get_new_parentid('jqshow');
        $newitemid = $DB->insert_record('jqshow_questions', $data);
        $this->set_mapping('jqshow_questions', $oldid, $newitemid);
    }

    protected function process_jqshow_questions_response($data) {
        //TODO: missing answerid! multichoice at least.
        global $DB;
        $data = (object)$data;
        $data->jqshow = $this->get_new_parentid('jqshow');
        $data->session = $this->get_mappingid('jqshow_sessions', $data->session);
        $data->jqid = $this->get_mappingid('jqshow_questions', $data->jqid);
        $newquestionid = $this->get_mappingid('question', $data->questionid);
        if ($newquestionid) {
            $data->questionid = $newquestionid;
            $data->response = $this->replace_answerids($data->response, $newquestionid);
        }
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('jqshow_questions_responses', $data);
    }

    protected function process_jqshow_session($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->jqshowid = $this->get_new_parentid('jqshow');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->groupings = $this->get_mappingid('groupings', $data->groupings);
        $newitemid = $DB->insert_record('jqshow_sessions', $data);
        $this->set_mapping('jqshow_sessions', $oldid, $newitemid);
    }

    protected function process_jqshow_session_grade($data) {
        global $DB;
        $data = (object)$data;
        $data->session = $this->get_mappingid('jqshow_sessions', $data->session);
        $data->jqshow = $this->get_new_parentid('jqshow');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('jqshow_sessions_grades', $data);
    }

    protected function process_jqshow_user_progres($data) {
        global $DB;
        $data = (object)$data;
        $data->jqshow = $this->get_new_parentid('jqshow');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->session = $this->get_mappingid('jqshow_sessions', $data->session);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('jqshow_user_progress', $data);
    }
    protected function inform_new_usage_id($newusageid) {
        // Not used in this activity module.
    }



    protected function after_execute() {
        $this->add_related_files('mod_jqshow', 'intro', null);
    }
    private function replace_answerids(string $responsejson, int $newquestionid) : string {
        error_log(__FUNCTION__ . ' newquestionid: '.var_export($newquestionid, true));
        error_log(__FUNCTION__ . ' 1 responsejson: '.var_export($responsejson, true));
        if (!$newquestionid) {
            return $responsejson;
        }
        $resp = json_decode($responsejson, false, 512, JSON_THROW_ON_ERROR);
        error_log(__FUNCTION__ . ' resp->type: '.var_export($resp->{'type'}, true));
        if ($resp->{'type'} == 'truefalse') {
            return $this->replace_answerids_truefalse($resp, $newquestionid);
        } else if ($resp->{'type'} == 'multichoice') {
            return $this->replace_answerids_multichoice($resp, $newquestionid);
        }
        error_log(__FUNCTION__ . ' 2 responsejson: '.var_export($responsejson, true));
        return $responsejson;

    }
    private function replace_answerids_multichoice(stdClass $response, $newquestionid) : string {
        $answertexts = $response->{'answertexts'};
        $answerids = explode(',', $response->{'answerids'});
        $newanswerids = [];
        $answertexts = json_decode($answertexts);
        $question = question_bank::load_question($newquestionid);
        error_log(__FUNCTION__ . ' question: '.var_export($question, true));
        error_log(__FUNCTION__ . ' response: '.var_export($response, true));
        foreach ($question->answers as $key => $answer) {
            foreach ($answerids as $answerid) {
                $text = $answertexts->{$answerid};
                if (strcmp($text, $answer->answer) == 0) {
                    $newanswerids[] = $key;
                }
            }
        }
        $newanswerids = implode(',', $newanswerids);
        $response->{'answerids'} = $newanswerids;

        error_log(__FUNCTION__ . ' 2 response: '.var_export($response, true));
        return json_encode($response);
    }
    private function replace_answerids_truefalse(stdClass $response, $newquestionid) : string {
        $question = question_bank::load_question($newquestionid);
        error_log(__FUNCTION__ . ' question: '.var_export($question, true));
        error_log(__FUNCTION__ . ' response: '.var_export($response, true));
        $answertext = $response->{'answertexts'};
        $newanswerids = $question->falseanswerid;
        if ($answertext == '1' && $question->rightanswer) {
            $newanswerids  = $question->trueanswerid;
        }
        $response->{'answerids'} = $newanswerids;
        error_log(__FUNCTION__ . ' 2 response: '.var_export($response, true));
        return json_encode($response);
    }
}
