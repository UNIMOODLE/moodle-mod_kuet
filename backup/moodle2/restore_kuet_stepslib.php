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
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
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
        $this->add_question_usages($question, $paths);
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
        $newquestionid = $this->get_mappingid('question', $data->questionid);
        if ($newquestionid) {
            $data->questionid = $newquestionid;
        }
        $data->sessionid = $this->get_mappingid('kuet_sessions', $data->sessionid);
        $data->kuetid = $this->get_new_parentid('kuet');
        $newitemid = $DB->insert_record('kuet_questions', $data);
        $this->set_mapping('kuet_questions', $oldid, $newitemid);
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
        $newquestionid = $this->get_mappingid('question', $data->questionid);
        if ($newquestionid) {
            $data->questionid = $newquestionid;
            $data->response = $this->replace_answerids($data->response, $newquestionid);
        }
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
        $this->add_related_files('mod_kuet', 'intro', null);
    }

    /**
     * Replace the answer ids of a stored response with the ones this restore created
     *
     * The response column keeps base64 of JSON, the same as everywhere else in the
     * plugin, so it has to be decoded on the way in and encoded again on the way out.
     *
     * Ids are resolved through the 'question_answer' mapping that restore_qtype_plugin
     * records for every question it restores. That is the mapping core itself relies
     * on, and it is set both for a question the restore created and for one matched
     * against a question already in the bank.
     *
     * @param string $responsejson
     * @param int $newquestionid
     * @return string
     */
    private function replace_answerids(string $responsejson, int $newquestionid): string {
        if (!$newquestionid) {
            return $responsejson;
        }
        $response = json_decode(base64_decode($responsejson), false);
        if (!is_object($response) || !isset($response->type)) {
            // A response this code cannot read is left exactly as it was.
            return $responsejson;
        }
        if ($response->type !== 'truefalse' && $response->type !== 'multichoice') {
            return $responsejson;
        }
        if (isset($response->answerids)) {
            $response->answerids = $this->map_answerids((string)$response->answerids);
        }
        if (isset($response->correct_answers)) {
            $response->correct_answers = $this->map_answerids((string)$response->correct_answers);
        }
        if ($response->type === 'multichoice' && isset($response->answertexts)) {
            // Only multichoice keys its answer texts by answer id. For true-false the
            // field holds the '1' or '0' the participant picked, and means nothing here.
            $response->answertexts = $this->map_answertexts((string)$response->answertexts);
        }
        $encoded = json_encode($response);

        return $encoded === false ? $responsejson : base64_encode($encoded);
    }

    /**
     * Map a comma separated list of question_answers ids through the restore mappings
     *
     * A token with nothing to map is kept verbatim: '' and '0' are what the plugin
     * stores for a question that was never answered.
     *
     * @param string $answerids
     * @return string
     */
    private function map_answerids(string $answerids): string {
        $mapped = [];
        foreach (explode(',', $answerids) as $answerid) {
            $newanswerid = (int)$answerid > 0 ? $this->get_mappingid('question_answer', (int)$answerid) : false;
            $mapped[] = $newanswerid ?: $answerid;
        }

        return implode(',', $mapped);
    }

    /**
     * Map the keys of the answer texts of a multichoice response
     *
     * The field is JSON nested inside the response: answer id to answer text.
     *
     * @param string $answertexts
     * @return string
     */
    private function map_answertexts(string $answertexts): string {
        $texts = json_decode($answertexts, true);
        if (!is_array($texts)) {
            return $answertexts;
        }
        $mapped = [];
        foreach ($texts as $answerid => $text) {
            $newanswerid = (int)$answerid > 0 ? $this->get_mappingid('question_answer', (int)$answerid) : false;
            $mapped[$newanswerid ?: $answerid] = $text;
        }
        $encoded = json_encode((object)$mapped);

        return $encoded === false ? $answertexts : $encoded;
    }
}
