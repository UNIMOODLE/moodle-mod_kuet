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
 * @package    mod_jqshow
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jqshow\external;

use coding_exception;
use context_module;
use dml_exception;
use dml_transaction_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\models\questions;
use moodle_exception;
use ReflectionException;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class session_getallquestions_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function session_getallquestions_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'sessionid' => new external_value(PARAM_INT, 'id of session to copy')
            ]
        );
    }

    /**
     * This method is too slow if the volume of questions in the session is too high. Do not use.
     * @param int $cmid
     * @param int $sessionid
     * @return array
     * @throws JsonException
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function session_getallquestions(int $cmid, int $sessionid): array {
        global $DB, $PAGE, $COURSE;
        self::validate_parameters(
            self::session_getallquestions_parameters(),
            ['cmid' => $cmid, 'sessionid' => $sessionid]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'jqshow', $COURSE);
        $jqshow = $DB->get_record('jqshow', ['id' => $cm->instance], 'id', MUST_EXIST);
        $allquestions = (new questions($jqshow->id, $cmid, $sessionid))->get_list();
        $questiondata = [];
        foreach ($allquestions as $question) {
            /** @var questions $type */
            $type = questions::get_question_class_by_string_type($question->get('qtype'));
            $questiondata = $type::export_question(
                $question->get('id'),
                $cmid,
                $sessionid,
                $question->get('jqshowid'));
        }
        return $questiondata;
    }

    /**
     * @return external_single_structure
     */
    public static function session_getallquestions_returns() {
        // TODO.
        return null;
    }
}
