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
 * Get active session API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

use coding_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_kuet\persistents\kuet_sessions;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Get active session class
 */
class getactivesession_external extends external_api {

    /**
     * Get active session parameters validation
     *
     * @return external_function_parameters
     */
    public static function getactivesession_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'kuetid' => new external_value(PARAM_INT, 'Kuet id'),
            ]
        );
    }

    /**
     * Get active session
     *
     * @param int $cmid
     * @param int $kuetid
     * @return array
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public static function getactivesession(int $cmid, int $kuetid): array {
        self::validate_parameters(
            self::getactivesession_parameters(),
            ['cmid' => $cmid, 'kuetid' => $kuetid]
        );
        $activessesion = kuet_sessions::get_active_session_id($kuetid);
        return [
            'active' => $activessesion,
        ];
    }

    /**
     * Get active session returns
     *
     * @return external_single_structure
     */
    public static function getactivesession_returns(): external_single_structure {
        return new external_single_structure(
            [
                'active' => new external_value(PARAM_INT, 'Id of active session for kuetid. 0 if there is no active session.'),
            ]
        );
    }
}
