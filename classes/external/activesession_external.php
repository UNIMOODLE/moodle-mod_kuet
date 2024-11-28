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
 * Active session API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

use coding_exception;
use context_module;
use core\invalid_persistent_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use mod_kuet\persistents\kuet_sessions;

/**
 * Active session api class
 *
 */
class activesession_external extends external_api {

    /**
     * Active session parameters
     *
     * @return external_function_parameters
     */
    public static function activesession_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'sessionid' => new external_value(PARAM_INT, 'id of session to copy'),
            ]
        );
    }

    /**
     * Active session service
     *
     * @param int $cmid
     * @param int $sessionid
     * @return array
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     */
    public static function activesession(int $cmid, int $sessionid): array {
        global $USER;
        self::validate_parameters(
            self::activesession_parameters(),
            ['cmid' => $cmid, 'sessionid' => $sessionid]
        );
        $cmcontext = context_module::instance($cmid);
        $active = false;
        if ($cmcontext !== null && has_capability('mod/kuet:managesessions', $cmcontext, $USER)) {
            kuet_sessions::mark_session_active($sessionid);
            $active = true;
        }
        return [
            'active' => $active,
        ];
    }

    /**
     * Active sessio return
     *
     * @return external_single_structure
     */
    public static function activesession_returns(): external_single_structure {
        return new external_single_structure(
            [
                'active' => new external_value(PARAM_BOOL, 'finished'),
            ]
        );
    }
}
