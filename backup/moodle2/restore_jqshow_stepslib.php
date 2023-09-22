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

    /**
     * @var \stdClass for inform_new_usage_id
     */
    private $currentattempt;

    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $paths = [];
        $paths[] = new restore_path_element('jqshow', '/activity/jqshow');
        $paths[] = new restore_path_element('jqshow_question',
           '/activity/jqshow/questions/question');
        if ($userinfo) {
            $paths[] = new restore_path_element('jqshow_session',
               '/activity/jqshow/sessions/session');
            $paths[] = new restore_path_element('jqshow_session_question',
               '/activity/jqshow/sessions/session/sessionquestions/sessionquestion');
            $paths[] = new restore_path_element('jqshow_merge',
               '/activity/jqshow/sessions/session/merges/merge');
            $paths[] = new restore_path_element('jqshow_vote',
               '/activity/jqshow/sessions/session/votes/vote');
            $paths[] = new restore_path_element('jqshow_attendance',
               '/activity/jqshow/sessions/session/attendances/attendance');
            $attempt = new restore_path_element('jqshow_attempt',
               '/activity/jqshow/sessions/session/attempts/attempt');
            $paths[] = $attempt;
            $this->add_question_usages($attempt, $paths);
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

    protected function process_jqshow_question($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->jqshowid = $this->get_new_parentid('jqshow');
        $data->questionid = $this->get_mappingid('question', $data->questionid);
        if (!$data->questionid) {
            return;
        }
        $newitemid = $DB->insert_record('jqshow_questions', $data);
        $this->set_mapping('jqshow_question', $oldid, $newitemid);
    }

    protected function process_jqshow_session($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->jqshowid = $this->get_new_parentid('jqshow');
        $data->created = $this->apply_date_offset($data->created);
        $newitemid = $DB->insert_record('jqshow_sessions', $data);
        $this->set_mapping('jqshow_session', $oldid, $newitemid);
    }

    protected function process_jqshow_attempt($data) {
        $data = (object)$data;
        $data->sessionid = $this->get_new_parentid('jqshow_session');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timefinish = $this->apply_date_offset($data->timefinish);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $this->currentattempt = clone($data);
    }

    protected function inform_new_usage_id($newusageid) {
        global $DB;
        $data = $this->currentattempt;
        $oldid = $data->id;
        $data->questionengid = $newusageid;
        $newitemid = $DB->insert_record('jqshow_attempts', $data);
        $this->set_mapping('jqshow_attempt', $oldid, $newitemid);
    }

    protected function process_jqshow_session_question($data) {
        global $DB;
        $data = (object)$data;
        $data->sessionid = $this->get_new_parentid('jqshow_session');
        $data->questionid = $this->get_mappingid('question', $data->questionid);
        if (!$data->questionid) {
            return;
        }
        $oldid = $data->id;
        $newitemid = $DB->insert_record('jqshow_session_questions', $data);
        $this->set_mapping('jqshow_session_question', $oldid, $newitemid);
    }

    protected function process_jqshow_merge($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->sessionid = $this->get_new_parentid('jqshow_session');
        $newitemid = $DB->insert_record('jqshow_merges', $data);
        $this->set_mapping('jqshow_merge', $oldid, $newitemid);
    }

    protected function process_jqshow_vote($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->jqshowid = $this->get_mappingid('jqshow', $data->jqshowid);
        $data->sessionid = $this->get_new_parentid('jqshow_session');
        $newitemid = $DB->insert_record('jqshow_votes', $data);
        $this->set_mapping('jqshow_vote', $oldid, $newitemid);
    }

    protected function process_jqshow_attendance($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->sessionid = $this->get_new_parentid('jqshow_session');
        $newitemid = $DB->insert_record('jqshow_attendance', $data);
        $this->set_mapping('jqshow_attendance', $oldid, $newitemid);
    }

    protected function after_execute() {
        $this->add_related_files('mod_jqshow', 'intro', null);
    }

}
