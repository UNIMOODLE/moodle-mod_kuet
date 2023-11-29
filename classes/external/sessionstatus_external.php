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
use core\invalid_persistent_exception;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_assign\external\external_api;
use mod_kuet\persistents\kuet_sessions;

class sessionstatus_external extends external_api {
    public static function sessionstatus_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'sid' => new external_value(PARAM_INT, 'Session id'),
                'status' => new external_value(PARAM_INT, 'Status code'),
            ]
        );
    }

    /**
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws invalid_parameter_exception
     */
    public static function sessionstatus(int $sid, int $status) : array {
        self::validate_parameters(
            self::sessionstatus_parameters(),
            ['sid' => $sid, 'status' => $status]
        );

        $result = [];
        $session = new kuet_sessions($sid);
        $session->set('status', $status);
        $result['statuschanged'] = $session->update();

        return $result;
    }

    /**
     * @return external_single_structure
     */
    public static function sessionstatus_returns(): external_single_structure {
        return new external_single_structure(
            [
                'statuschanged' => new external_value(PARAM_BOOL, 'Session status has changed')
            ]
        );
    }
}