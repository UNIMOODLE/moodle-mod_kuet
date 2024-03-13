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
 * Delete session API
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
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use mod_kuet\persistents\kuet_sessions_grades;
use mod_kuet\persistents\kuet_user_progress;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Delete session class
 */
class deletesession_external extends external_api {

    /**
     * Delete session parameters validation
     *
     * @return external_function_parameters
     */
    public static function deletesession_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'course id'),
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'sessionid' => new external_value(PARAM_INT, 'id of session to copy')
            ]
        );
    }

    /**
     * Delete session
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $sessionid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function deletesession(int $courseid, int $cmid, int $sessionid): array {
        global $USER;
        self::validate_parameters(
            self::deletesession_parameters(),
            ['courseid' => $courseid, 'cmid' => $cmid, 'sessionid' => $sessionid]
        );
        $cmcontext = context_module::instance($cmid);
        $deleted = false;
        if ($cmcontext !== null && has_capability('mod/kuet:managesessions', $cmcontext, $USER)) {
            $ds = kuet_sessions::delete_session($sessionid);
            $dq = kuet_questions::delete_session_questions($sessionid);
            $dresponses = kuet_questions_responses::delete_questions_responses($sessionid);
            $dsgrades = kuet_sessions_grades::delete_session_grades($sessionid);
            $duprogress = kuet_user_progress::delete_session_user_progress($sessionid);
            $deleted = $dq && $ds && $dresponses && $dsgrades && $duprogress;
        }
        return [
            'deleted' => $deleted
        ];
    }

    /**
     * Delete session returns
     *
     * @return external_single_structure
     */
    public static function deletesession_returns(): external_single_structure {
        return new external_single_structure(
            [
                'deleted' => new external_value(PARAM_BOOL, 'deleted'),
            ]
        );
    }
}
