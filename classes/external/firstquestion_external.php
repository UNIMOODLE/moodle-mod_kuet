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
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

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
use mod_kuet\exporter\question_exporter;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use ReflectionException;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class firstquestion_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function firstquestion_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'sessionid' => new external_value(PARAM_INT, 'session id'),
            ]
        );
    }


    /**
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
    public static function firstquestion(int $cmid, int $sessionid): array {
        global $PAGE;
        self::validate_parameters(
            self::firstquestion_parameters(),
            ['cmid' => $cmid, 'sessionid' => $sessionid]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);
        $firstquestion = kuet_questions::get_first_question_of_session($sessionid);
        /** @var questions $type */
        $type = questions::get_question_class_by_string_type($firstquestion->get('qtype'));
        $question = $type::export_question(
            $firstquestion->get('id'),
            $cmid,
            $sessionid,
            $firstquestion->get('jqshowid'));
        $question->showstatistics = $type::show_statistics();
        $session = new kuet_sessions($sessionid);
        if ($session->get('sessionmode') === sessions::INACTIVE_PROGRAMMED ||
            $session->get('sessionmode') === sessions::PODIUM_PROGRAMMED ||
            $session->get('sessionmode') === sessions::RACE_PROGRAMMED) {
            $question->programmedmode = true;
        } else {
            $question->programmedmode = false;
        }
        return (array)(new question_exporter($question, ['context' => $contextmodule]))->export($PAGE->get_renderer('mod_kuet'));
    }

    /**
     * @return external_single_structure
     */
    public static function firstquestion_returns(): external_single_structure {
        return question_exporter::get_read_structure();
    }
}
