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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos..

/**
 * Kuet renderer
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet\output;

use coding_exception;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use invalid_parameter_exception;
use JsonException;
use mod_kuet\output\views\question_preview;
use mod_kuet\output\views\sessions_view;
use mod_kuet\output\views\student_reports;
use mod_kuet\output\views\student_session_view;
use mod_kuet\output\views\teacher_reports;
use mod_kuet\output\views\teacher_session_view;
use mod_kuet\output\views\test_report;
use moodle_exception;
use core\output\plugin_renderer_base;
use mod_kuet\output\views\student_view;
use mod_kuet\output\views\teacher_view;
use ReflectionException;

/**
 * Kuet module renderer class
 *
 */
class renderer extends plugin_renderer_base {
    /**
     * Student view renderer
     *
     * @param student_view $view
     * @return string
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function render_student_view(student_view $view): string {
        // Rendered to the user when no session started.
        $data = $view->export_for_template($this);
        return $this->render_from_template('mod_kuet/student', $data);
    }

    /**
     * Student session view renderer
     *
     * @param student_session_view $view
     * @return string
     * @throws JsonException
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public function render_student_session_view(student_session_view $view): string {
        // Rendered to the user when session started.
        $data = $view->export_for_template($this);
        return $this->render_from_template('mod_kuet/session/student', $data);
    }

    /**
     * Teacher view renderer
     *
     * @param teacher_view $view
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function render_teacher_view(teacher_view $view): string {
        $data = $view->export_for_template($this);
        return $this->render_from_template('mod_kuet/sessions', $data);
    }

    /**
     * Teacher session view renderer
     *
     * @param teacher_session_view $view
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public function render_teacher_session_view(teacher_session_view $view): string {
        $data = $view->export_for_template($this);
        return $this->render_from_template('mod_kuet/session/teacher', $data);
    }

    /**
     * Session view renderer
     *
     * @param sessions_view $view
     * @return string
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public function render_sessions_view(sessions_view $view): string {
        $data = $view->export_for_template($this);
        return $this->render_from_template('mod_kuet/createsession/createsession', $data);
    }

    /**
     * Question view renderer
     *
     * @param question_preview $view
     * @return string
     * @throws JsonException
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public function render_question_preview(question_preview $view): string {
        $data = $view->export_for_template($this);
        return $this->render_from_template($data->template, $data);
    }

    /**
     * Teacher report renderer
     *
     * @param teacher_reports $view
     * @return string
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function render_teacher_reports(teacher_reports $view): string {
        $data = $view->export_for_template($this);
        return $this->render_from_template('mod_kuet/reports/teacher_reports', $data);
    }

    /**
     * Student report renderer
     *
     * @param student_reports $view
     * @return string
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function render_student_reports(student_reports $view): string {
        $data = $view->export_for_template($this);
        $template = 'mod_kuet/reports/student_reports';
        if ($data->groupmode) {
            $template = 'mod_kuet/reports/group_reports';
        }
        return $this->render_from_template($template, $data);
    }

    /**
     * Test WebSocket server report renderer.
     *
     * @param test_report $view
     * @return string
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function render_test_reports(test_report $view): string {
        $data = $view->export_for_template($this);
        return $this->render_from_template('mod_kuet/test_report', $data);
    }
}
