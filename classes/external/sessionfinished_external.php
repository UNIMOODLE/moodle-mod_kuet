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
 * Session finished API
 *
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
use dml_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use moodle_url;
use stdClass;



/**
 * Session finished class
 */
class sessionfinished_external extends external_api {

    /**
     * Session finished parameters validation
     *
     * @return external_function_parameters
     */
    public static function sessionfinished_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'kuetid' => new external_value(PARAM_INT, 'kuet id'),
                'cmid' => new external_value(PARAM_INT, 'course module id'),
            ]
        );
    }

    /**
     * Session finished
     *
     * @param int $kuetid
     * @param int $cmid
     * @return array|stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function sessionfinished(int $kuetid, int $cmid): array {
        self::validate_parameters(
            self::sessionfinished_parameters(),
            ['kuetid' => $kuetid, 'cmid' => $cmid]
        );
        global $OUTPUT, $PAGE;
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);

        $nextsession = kuet_sessions::get_next_session($kuetid);
        return  [
            'sessionclosedimage' => $OUTPUT->image_url('f/error', 'mod_kuet')->out(false),
            'hasnextsession' => $nextsession !== 0,
            'nextsessiontime' =>
                ($nextsession !== 0) ? userdate($nextsession, get_string('strftimedatetimeshort', 'core_langconfig')) : '',
            'urlreports' => (new moodle_url('/mod/kuet/reports.php', ['cmid' => $cmid]))->out(false),
        ];
    }

    /**
     * Session finished returns
     *
     * @return external_single_structure
     */
    public static function sessionfinished_returns(): external_single_structure {
        return new external_single_structure(
            [
                'sessionclosedimage' => new external_value(PARAM_URL, 'Close session image'),
                'hasnextsession' => new external_value(PARAM_BOOL, 'Has next session image'),
                'nextsessiontime' => new external_value(PARAM_RAW, 'Time of next session', VALUE_OPTIONAL),
                'urlreports' => new external_value(PARAM_URL, 'Url reports'),
            ]
        );
    }
}
