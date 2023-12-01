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

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_kuet\kuet;
use mod_kuet\persistents\kuet_questions_responses;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class deleteresponses_external extends external_api {

    public static function deleteresponses_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'id of course module'),
                'sessionid' => new external_value(PARAM_INT, 'id of session'),
                'kid' => new external_value(PARAM_INT, 'id of kuet_question'),
            ]
        );
    }

    /**
     * @param int $cmid
     * @param int $sessionid
     * @param int $kid
     * @return array
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function deleteresponses(int $cmid, int $sessionid, int $kid): array {
        self::validate_parameters(
            self::deleteresponses_parameters(),
            ['cmid' => $cmid, 'sessionid' => $sessionid, 'kid' => $kid]
        );
        $cmcontext = context_module::instance($cmid);
        if (has_capability('mod/kuet:startsession', $cmcontext)) {
            $kuet = new kuet($cmid);
            return [
                'deleted' => kuet_questions_responses::delete_question_responses($kuet->get_kuet()->id, $sessionid, $kid)
            ];
        }
        return [
            'deleted' => false
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function deleteresponses_returns(): external_single_structure {
        return new external_single_structure(
            [
                'deleted' => new external_value(PARAM_BOOL, 'deleted responses'),
            ]
        );
    }
}
