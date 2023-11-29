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
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet\external;

use coding_exception;
use context_module;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use JsonException;
use mod_kuet\exporter\question_exporter;
use mod_kuet\helpers\progress;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_sessions;
use mod_kuet\persistents\kuet_user_progress;
use moodle_exception;
use ReflectionException;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class jumptoquestion_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function jumptoquestion_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'sessionid' => new external_value(PARAM_INT, 'session id'),
                'position' => new external_value(PARAM_INT, 'Order of question'),
                'manual' => new external_value(PARAM_BOOL, 'Mode of session')
            ]
        );
    }

    /**
     * @param int $cmid
     * @param int $sessionid
     * @param int $position
     * @param bool $manual
     * @return array
     * @throws JsonException
     * @throws ReflectionException
     * @throws dml_exception
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function jumptoquestion(int $cmid, int $sessionid, int $position, bool $manual = false): array {
        global $PAGE, $USER;
        self::validate_parameters(
            self::jumptoquestion_parameters(),
            ['cmid' => $cmid, 'sessionid' => $sessionid, 'position' => $position, 'manual' => $manual]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);
        $question = kuet_questions::get_question_by_position($sessionid, $position);
        if ($question !== false) {
            progress::set_progress(
                $question->get('jqshowid'), $sessionid, $USER->id, $cmid, $question->get('id')
            );
            /** @var questions $type */
            $type = questions::get_question_class_by_string_type($question->get('qtype'));
            $data = $type::export_question(
                $question->get('id'),
                $cmid,
                $sessionid,
                $question->get('jqshowid'));
            $data->showstatistics = $type::show_statistics();
        } else {
            $session = new kuet_sessions($sessionid);
            $finishdata = new stdClass();
            $finishdata->endSession = 1;
            kuet_user_progress::add_progress(
                $session->get('jqshowid'), $sessionid, $USER->id, json_encode($finishdata, JSON_THROW_ON_ERROR)
            );
            $data = sessions::export_endsession(
                $cmid,
                $sessionid);
        }
        $data->programmedmode = $manual === false;
        return (array)(new question_exporter($data, ['context' => $contextmodule]))->export($PAGE->get_renderer('mod_kuet'));
    }

    /**
     * @return external_single_structure
     */
    public static function jumptoquestion_returns(): external_single_structure {
        return question_exporter::get_read_structure();
    }
}
