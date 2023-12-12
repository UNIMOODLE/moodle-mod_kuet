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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos

/**
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class restore_kuet_activity_structure_step extends restore_questions_activity_structure_step {

    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $paths = [];
        $paths[] = new restore_path_element('kuet', '/activity/kuet');
        $paths[] = new restore_path_element('kuet_session','/activity/kuet/sessions/session');
        $question = new restore_path_element('kuet_question', '/activity/kuet/questions/question');
        $paths[] = $question;
        $this->add_question_usages($question, $paths);
        if ($userinfo) {
            $paths[] = new restore_path_element('kuet_grade','/activity/kuet/grades/grade');
            $paths[] = new restore_path_element('kuet_session_grade','/activity/kuet/sessions_grades/session_grade');
            $paths[] = new restore_path_element('kuet_user_progres', '/activity/kuet/user_progress/user_progres');
            $paths[] = new restore_path_element('kuet_questions_response','/activity/kuet/questions_responses/questions_response');
        }
        return $this->prepare_activity_structure($paths);
    }

    protected function process_kuet($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newitemid = $DB->insert_record('kuet', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_kuet_grade($data) {
        global $DB;
        $data = (object)$data;
        $data->kuet = $this->get_new_parentid('kuet');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('kuet_grades', $data);
    }

    protected function process_kuet_question($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $newquestionid = $this->get_mappingid('question', $data->questionid);
        if ($newquestionid) {
            $data->questionid = $newquestionid;
        }
        $data->sessionid = $this->get_mappingid('kuet_sessions', $data->sessionid);
        $data->kuetid = $this->get_new_parentid('kuet');
        $newitemid = $DB->insert_record('kuet_questions', $data);
        $this->set_mapping('kuet_questions', $oldid, $newitemid);
    }

    protected function process_kuet_questions_response($data) {
        global $DB;
        $data = (object)$data;
        $data->kuet = $this->get_new_parentid('kuet');
        $data->session = $this->get_mappingid('kuet_sessions', $data->session);
        $data->kid = $this->get_mappingid('kuet_questions', $data->kid);
        $newquestionid = $this->get_mappingid('question', $data->questionid);
        if ($newquestionid) {
            $data->questionid = $newquestionid;
            $data->response = $this->replace_answerids($data->response, $newquestionid);
        }
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('kuet_questions_responses', $data);
    }

    protected function process_kuet_session($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->kuetid = $this->get_new_parentid('kuet');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->groupings = $this->get_mappingid('groupings', $data->groupings);
        $newitemid = $DB->insert_record('kuet_sessions', $data);
        $this->set_mapping('kuet_sessions', $oldid, $newitemid);
    }

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
    protected function inform_new_usage_id($newusageid) {
        // Not used in this activity module.
    }



    protected function after_execute() {
        $this->add_related_files('mod_kuet', 'intro', null);
    }
    private function replace_answerids(string $responsejson, int $newquestionid) : string {
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
    private function replace_answerids_multichoice(stdClass $response, $newquestionid) : string {
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
    private function replace_answerids_truefalse(stdClass $response, $newquestionid) : string {
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
