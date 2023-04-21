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

namespace mod_jqshow\output;
use mod_jqshow\output\views\sessions_view;
use mod_jqshow\output\views\student_session_view;
use mod_jqshow\output\views\teacher_session_view;
use moodle_exception;
use plugin_renderer_base;
use mod_jqshow\output\views\student_view;
use mod_jqshow\output\views\teacher_view;
/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * @param student_view $view
     * @return string
     * @throws moodle_exception
     */
    public function render_student_view(student_view $view): string {
        $data = $view->export_for_template($this);
        return $this->render_from_template('mod_jqshow/student', $data);
    }

    /**
     * @param student_session_view $view
     * @return string
     * @throws moodle_exception
     */
    public function render_student_session_view(student_session_view $view): string {
        $data = $view->export_for_template($this);
        return $this->render_from_template('mod_jqshow/session/student', $data);
    }

    /**
     * @param teacher_view $view
     * @return string
     * @throws moodle_exception
     */
    public function render_teacher_view(teacher_view $view): string {
        /*$data = $view->export_for_template($this);
        return $this->render_from_template('mod_jqshow/sessions', $data);*/
        $data = new \stdClass();
        $data->questionid = 3;
        $data->question_index_string = '3 de 10';
        $data->sessionprogress = 33;
        $data->multichoice = true;
        $data->sessionid = 1;
        $data->cmid = 327;
        $data->jqshowid = 1;
        $data->questiontext = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus sit amet turpis id odio fringilla imperdiet. Nullam diam est, efficitur sed condimentum egestas, consectetur eu libero. <img src="https://atodomotor.blob.core.windows.net/contents/article/8062/enriquecruz.jpg">';
        $data->hastime = true;
        $data->seconds = 20;
        $data->managesession = true;
        $data->numusers = '12/20';
        $data->answers = [
            0 => [
                'answerid' => 1,
                'answertext' => 'China' // HTML text.
            ],
            1 => [
                'answerid' => 2,
                'answertext' => 'Alemania'
            ],
            2 => [
                'answerid' => 3,
                'answertext' => 'Rusia'
            ],
            3 => [
                'answerid' => 4,
                'answertext' => 'Francia'
            ]
        ];
        return $this->render_from_template('mod_jqshow/questions/encasement', $data);
    }

    public function render_teacher_session_view(teacher_session_view $view): string {
        $data = $view->export_for_template($this);
        return $this->render_from_template('mod_jqshow/session/teacher', $data);
    }

    /**
     * @param sessions_view $view
     * @return string
     * @throws moodle_exception
     */
    public function render_sessions_view(sessions_view $view): string {
        $data = $view->export_for_template($this);
        return $this->render_from_template('mod_jqshow/createsession/createsession', $data);
    }
}
