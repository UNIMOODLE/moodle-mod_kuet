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
            'grademethod',
            'completionanswerall',
            'usermodified',
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
            'timemodified'
        ]);

        $questionsresponses = new backup_nested_element('questions_responses');
        $questionsresponse = new backup_nested_element('questions_response', ['id'], [
            'jqshow',
            'session',
            'jqid',
            'userid',
            'anonymise',
            'result',
            'response',
            'timemodified'
        ]);

        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', ['id'], [
            'jqshow',
            'userid',
            'grade',
            'timemodified'
        ]);

        $sessionsgrades = new backup_nested_element('sessions_grades');
        $sessionsgrade = new backup_nested_element('sessions_grade', ['id'], [
            'jqshow',
            'session',
            'userid',
            'grade',
            'timemodified'
        ]);

        $userprogress = new backup_nested_element('user_progress');
        $userprogres = new backup_nested_element('user_progres', ['id'], [
            'jqshow',
            'session',
            'userid',
            'randomquestion',
            'other',
            'timemodified'
        ]);

        // Build the tree.
        $jqshow->add_child($sessions);
        $jqshow->add_child($grades);
        $jqshow->add_child($questions);
        $jqshow->add_child($questionsresponses);
        $jqshow->add_child($sessionsgrades);
        $jqshow->add_child($userprogress);

        $sessions->add_child($session);
        $session->add_child($questions);
        $session->add_child($questionsresponses);
        $session->add_child($sessionsgrades);
        $session->add_child($userprogress);

        $questions->add_child($question);
        $question->add_child($questionsresponses);

        $questionsresponses->add_child($questionsresponse);
        $grades->add_child($grade);
        $sessionsgrades->add_child($sessionsgrade);
        $userprogress->add_child($userprogres);

        // Define sources.
        $jqshow->set_source_table('jqshow', ['id' => backup::VAR_ACTIVITYID]);
        $question->set_source_table('jqshow_questions', ['jqshowid' => backup::VAR_PARENTID]);
        if ($userinfo) {
            $session->set_source_table('jqshow_sessions', ['jqshowid' => backup::VAR_PARENTID]);
            $sessionquestion->set_source_table('jqshow_session_questions', ['sessionid' => backup::VAR_PARENTID]);
            $attempt->set_source_table('jqshow_attempts', ['sessionid' => backup::VAR_PARENTID]);
            $attendance->set_source_table('jqshow_attendance', ['sessionid' => backup::VAR_PARENTID]);
            $merge->set_source_table('jqshow_merges', ['sessionid' => backup::VAR_PARENTID]);
            $vote->set_source_table('jqshow_votes', [
                'jqshowid' => backup::VAR_ACTIVITYID,
                'sessionid' => backup::VAR_PARENTID
            ]);
        }

        // Define id annotations.
        $attempt->annotate_ids('user', 'userid');
        $attendance->annotate_ids('user', 'userid');
        $question->annotate_ids('question', 'questionid');
        $sessionquestion->annotate_ids('question', 'questionid');

        // Define file annotations.
        $jqshow->annotate_files('mod_jqshow', 'intro', null);

        // Return the root element (choice), wrapped into standard activity structure.
        return $this->prepare_activity_structure($jqshow);
    }

}
