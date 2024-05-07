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
 * Kuet backup steps
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



/**
 * Define the complete choice structure for backup, with file and id annotations.
 */
class backup_kuet_activity_structure_step extends backup_questions_activity_structure_step {

    /**
     * Define structure
     *
     * @return backup_nested_element
     * @throws backup_step_exception
     * @throws base_element_struct_exception
     * @throws base_step_exception
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $kuet = new backup_nested_element('kuet', ['id'], [
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
            'timemodified',
        ]);
        $this->add_question_usages($question, 'questionid');
        $questionsresponses = new backup_nested_element('questions_responses');
        $questionsresponse = new backup_nested_element('questions_response', ['id'], [
            'session',
            'kid',
            'questionid',
            'userid',
            'anonymise',
            'result',
            'response',
            'timecreated',
            'timemodified',
        ]);

        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', ['id'], [
            'userid',
            'grade',
            'timemodified',
        ]);

        $sessionsgrades = new backup_nested_element('sessions_grades');
        $sessiongrade = new backup_nested_element('session_grade', ['id'], [
            'session',
            'userid',
            'grade',
            'timecreated',
            'timemodified',
        ]);

        $userprogress = new backup_nested_element('user_progress');
        $userprogres = new backup_nested_element('user_progres', ['id'], [
            'session',
            'userid',
            'randomquestion',
            'other',
            'timecreated',
            'timemodified',
        ]);

        // Build the tree.
        $kuet->add_child($grades);
        $grades->add_child($grade);

        $kuet->add_child($sessions);
        $sessions->add_child($session);

        $kuet->add_child($questions);
        $questions->add_child($question);

        $kuet->add_child($sessionsgrades);
        $sessionsgrades->add_child($sessiongrade);

        $kuet->add_child($userprogress);
        $userprogress->add_child($userprogres);

        $kuet->add_child($questionsresponses);
        $questionsresponses->add_child($questionsresponse);

        // Define sources.
        $kuet->set_source_table('kuet', ['id' => backup::VAR_ACTIVITYID]);
        $session->set_source_table('kuet_sessions', ['kuetid' => backup::VAR_PARENTID]);
        $question->set_source_table('kuet_questions', ['kuetid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $grade->set_source_table('kuet_grades', ['kuet' => backup::VAR_PARENTID]);
            $sessiongrade->set_source_table('kuet_sessions_grades', ['kuet' => backup::VAR_PARENTID]);
            $userprogres->set_source_table('kuet_user_progress', ['kuet' => backup::VAR_PARENTID]);
            $questionsresponse->set_source_table('kuet_questions_responses', ['kuet' => backup::VAR_PARENTID]);
        }
        // Define id annotations.
        $session->annotate_ids('groupings', 'groupings');
        $question->annotate_ids('question', 'questionid');
        $sessiongrade->annotate_ids('user', 'userid');
        $userprogres->annotate_ids('user', 'userid');
        $questionsresponse->annotate_ids('user', 'userid');
        $questionsresponse->annotate_ids('question', 'questionid');

        // Define file annotations.
        $kuet->annotate_files('mod_kuet', 'intro', null);

        // Return the root element (choice), wrapped into standard activity structure.
        return $this->prepare_activity_structure($kuet);
    }
}
