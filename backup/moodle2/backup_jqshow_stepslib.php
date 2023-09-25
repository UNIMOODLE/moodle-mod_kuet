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

/**
 * Define the complete choice structure for backup, with file and id annotations.
 */
class backup_jqshow_activity_structure_step extends backup_questions_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $jqshow = new backup_nested_element('jqshow', ['id'], [
            'name',
            'intro',
            'introformat',
            'teamgrade',
            'grademethod',
            'completionanswerall',
            'usermodified',
            'timecreated',
            'timemodified',
        ]);

        $sessions = new backup_nested_element('sessions');
        $session = new backup_nested_element('session', ['id'], [
            'name',
            'anonymousanswer',
            'sessionmode',
            'sgrade',
            'countdown',
            'showgraderanking',
            'randomquestions',
            'randomanswers',
            'showfeedback',
            'showfinalgrade',
            'startdate',
            'enddate',
            'automaticstart',
            'timemode',
            'sessiontime',
            'questiontime',
            'groupings',
            'status',
            'usermodified',
            'timecreated',
            'timemodified',
        ]);

        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'questionid',
            'sessionid',
            'qorder',
            'qtype',
            'timelimit',
            'ignorecorrectanswer',
            'isvalid',
            'config',
            'usermodified',
            'timecreated',
            'timemodified'
        ]);
        $this->add_question_usages($question, 'questionid');
        $questionsresponses = new backup_nested_element('questions_responses');
        $questionsresponse = new backup_nested_element('questions_response', ['id'], [
            'session',
            'jqid',
            'questionid',
            'userid',
            'anonymise',
            'result',
            'response',
            'timecreated',
            'timemodified'
        ]);

        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', ['id'], [
            'userid',
            'grade',
            'timemodified'
        ]);

        $sessionsgrades = new backup_nested_element('sessions_grades');
        $sessiongrade = new backup_nested_element('session_grade', ['id'], [
            'session',
            'userid',
            'grade',
            'timecreated',
            'timemodified'
        ]);

        $userprogress = new backup_nested_element('user_progress');
        $userprogres = new backup_nested_element('user_progres', ['id'], [
            'session',
            'userid',
            'randomquestion',
            'other',
            'timecreated',
            'timemodified'
        ]);

        // Build the tree.
        $jqshow->add_child($grades);
        $grades->add_child($grade);

        $jqshow->add_child($sessions);
        $sessions->add_child($session);

        $jqshow->add_child($questions);
        $questions->add_child($question);

        $jqshow->add_child($sessionsgrades);
        $sessionsgrades->add_child($sessiongrade);

        $jqshow->add_child($userprogress);
        $userprogress->add_child($userprogres);

        $jqshow->add_child($questionsresponses);
        $questionsresponses->add_child($questionsresponse);

        // Define sources.
        $jqshow->set_source_table('jqshow', ['id' => backup::VAR_ACTIVITYID]);
        $session->set_source_table('jqshow_sessions', ['jqshowid' => backup::VAR_PARENTID]);
        $question->set_source_table('jqshow_questions', ['jqshowid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $grade->set_source_table('jqshow_grades', ['jqshow' => backup::VAR_PARENTID]);
            $sessiongrade->set_source_table('jqshow_sessions_grades', ['jqshow' => backup::VAR_PARENTID]);
            $userprogres->set_source_table('jqshow_user_progress', ['jqshow' => backup::VAR_PARENTID]);
            $questionsresponse->set_source_table('jqshow_questions_responses', ['jqshow' => backup::VAR_PARENTID]);
        }
        // Define id annotations.
        $session->annotate_ids('groupings', 'groupings');
        $question->annotate_ids('question', 'questionid');
        $sessiongrade->annotate_ids('user', 'userid');
        $userprogres->annotate_ids('user', 'userid');
        $questionsresponse->annotate_ids('user', 'userid');
        $questionsresponse->annotate_ids('question', 'questionid');

        // Define file annotations.
        $jqshow->annotate_files('mod_jqshow', 'intro', null);

        // Return the root element (choice), wrapped into standard activity structure.
        return $this->prepare_activity_structure($jqshow);
    }
}
