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

use mod_kuet\helpers\question_references;



/**
 * Kuet activity structure steps class
 */
class restore_kuet_activity_structure_step extends restore_questions_activity_structure_step {
    /**
     * @var array Bank entry and version each session question named in the backup, by new kuet_questions id
     */
    private array $backupreferences = [];

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
        // The reference each session question carries to its bank entry. Its own path
        // because the one core provides in restore_question_reference_data_trait
        // reads the parent id of a quiz slot.
        $paths[] = new restore_path_element(
            'kuet_question_reference',
            '/activity/kuet/questions/question/question_reference'
        );
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
     * Process the reference of a session question to its question bank entry
     *
     * Nothing is written here: what the package says is kept to be read in
     * {@see after_restore()}, once core has had its say about which questions the
     * restored activity is left pointing at. The reference rows themselves are
     * written in after_execute(), from the question each row ended up with.
     *
     * @param array|stdClass $data
     * @return void
     */
    protected function process_kuet_question_reference($data) {
        $data = (object)$data;
        $this->backupreferences[(int)$this->get_new_parentid('kuet_question')] = (object) [
            'questionbankentryid' => (int)$data->questionbankentryid,
            'version' => is_null($data->version) ? null : (int)$data->version,
        ];
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
        $this->reference_restored_questions();
    }

    /**
     * Give every restored session question the reference to its bank entry
     *
     * Derived from the question each row ended up pointing at, so the reference and
     * questionid say the same thing. It runs for every restored kuet, which is also
     * what gives a reference to the questions of a copy made before references
     * existed: those packages carry none, and without one the next copy of the
     * activity would again arrive with no questions.
     *
     * @return void
     * @throws dml_exception
     */
    private function reference_restored_questions(): void {
        global $DB;

        $kuetid = (int)$this->task->get_activityid();
        $questions = $DB->get_records('kuet_questions', ['kuetid' => $kuetid], '', 'id, questionid');
        foreach ($questions as $question) {
            question_references::set((int)$question->id, $kuetid, (int)$question->questionid);
        }
    }

    /**
     * Follow the question each reference ended up pointing at
     *
     * This runs after restore_move_module_questions_categories, the step of core
     * that decides what a restored activity is left pointing at when the bank of
     * its questions was not part of the backup:
     *
     * - if that bank is still in this site and the user can see it, core points the
     *   references back at the original questions and leaves the copies the restore
     *   made hanging from a category it never moves. This is what duplicating an
     *   activity does, and the session has to go on sharing the same questions
     *   rather than take copies no bank shows;
     * - otherwise - another site, or the bank is gone - core puts those categories
     *   in a bank of the target course and the copies are what the session keeps.
     *
     * Taking the decision from core, instead of making the same one here, is what
     * keeps kuet and the references agreeing with each other whatever core does with
     * them. The responses follow their question: the answer ids of a response only
     * mean something in the question that response is about.
     *
     * @return void
     * @throws dml_exception
     */
    protected function after_restore() {
        global $DB;

        $kuetid = (int)$this->task->get_activityid();
        $questions = $DB->get_records('kuet_questions', ['kuetid' => $kuetid], '', 'id, questionid');
        foreach ($questions as $question) {
            $this->repin_reference((int)$question->id);
            $referenced = $this->referenced_questionid((int)$question->id);
            if (!$referenced || $referenced === (int)$question->questionid) {
                continue;
            }
            $DB->set_field('kuet_questions', 'questionid', $referenced, ['id' => $question->id]);
            $this->rewind_responses((int)$question->id, $referenced);
        }
    }

    /**
     * Put back the version the package named, when core has gone back to its entry
     *
     * The reference was written from the copy of the question the restore made, so
     * it names the version of that copy, which starts again at one. If core has
     * pointed it back at the entry the package named, it is the version of the
     * package that goes with it - the one the session was built with.
     *
     * @param int $kid Id in kuet_questions.
     * @return void
     * @throws dml_exception
     */
    private function repin_reference(int $kid): void {
        global $DB;

        $backup = $this->backupreferences[$kid] ?? null;
        if (!$backup) {
            // A package made before kuet referenced its questions.
            return;
        }
        $reference = $DB->get_record('question_references', [
            'component' => question_references::COMPONENT,
            'questionarea' => question_references::QUESTIONAREA,
            'itemid' => $kid,
        ]);
        if (!$reference || (int)$reference->questionbankentryid !== $backup->questionbankentryid) {
            // The session is on the copy that travelled, whose version is its own.
            return;
        }
        if ($reference->version == $backup->version) {
            return;
        }
        $DB->set_field('question_references', 'version', $backup->version, ['id' => $reference->id]);
    }

    /**
     * Question the reference of a session question resolves to
     *
     * The version the reference names, which is the one the session was built with,
     * and the latest one if that version is no longer there.
     *
     * @param int $kid Id in kuet_questions.
     * @return int|null
     * @throws dml_exception
     */
    private function referenced_questionid(int $kid): ?int {
        global $DB;

        $reference = $DB->get_record('question_references', [
            'component' => question_references::COMPONENT,
            'questionarea' => question_references::QUESTIONAREA,
            'itemid' => $kid,
        ]);
        if (!$reference) {
            return null;
        }
        $conditions = ['questionbankentryid' => $reference->questionbankentryid];
        if (!is_null($reference->version)) {
            $conditions['version'] = $reference->version;
        }
        $questionid = $DB->get_field('question_versions', 'questionid', $conditions, IGNORE_MULTIPLE);
        if (!$questionid) {
            $questionid = $DB->get_field_sql(
                "SELECT questionid
                   FROM {question_versions}
                  WHERE questionbankentryid = ?
               ORDER BY version DESC",
                [$reference->questionbankentryid],
                IGNORE_MULTIPLE
            );
        }

        return $questionid ? (int)$questionid : null;
    }

    /**
     * Take the responses of a session question back to the question it points at now
     *
     * Their answer ids were rewritten to the ones of the copy the restore made, and
     * the session is not pointing at that copy any more, so they are read back
     * through the same mapping the other way round.
     *
     * @param int $kid Id in kuet_questions.
     * @param int $questionid Question the session points at now.
     * @return void
     * @throws dml_exception
     */
    private function rewind_responses(int $kid, int $questionid): void {
        global $DB;

        $responses = $DB->get_records('kuet_questions_responses', ['kid' => $kid], '', 'id, response');
        foreach ($responses as $response) {
            $DB->update_record('kuet_questions_responses', (object) [
                'id' => $response->id,
                'questionid' => $questionid,
                'response' => $this->replace_answerids($response->response, $questionid, true),
            ]);
        }
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
     * @param bool $rewind Read the mapping the other way round, from the ids this
     *      restore created back to the ones of the backup. See {@see after_restore()}.
     * @return string
     */
    private function replace_answerids(string $responsejson, int $newquestionid, bool $rewind = false): string {
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
            $response->answerids = $this->map_answerids((string)$response->answerids, $rewind);
        }
        if (isset($response->correct_answers)) {
            $response->correct_answers = $this->map_answerids((string)$response->correct_answers, $rewind);
        }
        if ($response->type === 'multichoice' && isset($response->answertexts)) {
            // Only multichoice keys its answer texts by answer id. For true-false the
            // field holds the '1' or '0' the participant picked, and means nothing here.
            $response->answertexts = $this->map_answertexts((string)$response->answertexts, $rewind);
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
     * @param bool $rewind
     * @return string
     */
    private function map_answerids(string $answerids, bool $rewind = false): string {
        $mapped = [];
        foreach (explode(',', $answerids) as $answerid) {
            $newanswerid = (int)$answerid > 0 ? $this->mapped_answerid((int)$answerid, $rewind) : false;
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
     * @param bool $rewind
     * @return string
     */
    private function map_answertexts(string $answertexts, bool $rewind = false): string {
        $texts = json_decode($answertexts, true);
        if (!is_array($texts)) {
            return $answertexts;
        }
        $mapped = [];
        foreach ($texts as $answerid => $text) {
            $newanswerid = (int)$answerid > 0 ? $this->mapped_answerid((int)$answerid, $rewind) : false;
            $mapped[$newanswerid ?: $answerid] = $text;
        }
        $encoded = json_encode((object)$mapped);

        return $encoded === false ? $answertexts : $encoded;
    }

    /**
     * Counterpart of an answer id on the other side of this restore
     *
     * Forwards, the mapping core records for every question it restores. Backwards,
     * the same rows read by the id this restore created, which is how a response
     * gets back to the answers of the question it came from when the session ends up
     * pointing at that one again.
     *
     * @param int $answerid
     * @param bool $rewind
     * @return int|false
     */
    private function mapped_answerid(int $answerid, bool $rewind) {
        global $DB;

        if (!$rewind) {
            return $this->get_mappingid('question_answer', $answerid);
        }
        $record = $DB->get_record(
            'backup_ids_temp',
            [
                'backupid' => $this->get_restoreid(),
                'itemname' => 'question_answer',
                'newitemid' => $answerid,
            ],
            'itemid',
            IGNORE_MULTIPLE
        );

        return $record ? (int)$record->itemid : false;
    }
}
