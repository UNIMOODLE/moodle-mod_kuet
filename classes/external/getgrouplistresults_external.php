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
 * Get group list results API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

use context_module;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_kuet\models\sessions;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Get group list results class
 */
class getgrouplistresults_external extends external_api {
    /**
     * Get group list results parameters validation
     *
     * @return external_function_parameters
     */
    public static function getgrouplistresults_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'sid' => new external_value(PARAM_INT, 'sessionid id'),
                'cmid' => new external_value(PARAM_INT, 'course module id'),
            ]
        );
    }

    /**
     * Get group list results
     *
     * @param int $sid
     * @param int $cmid
     * @return true[]
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function getgrouplistresults(int $sid, int $cmid): array {
        global $PAGE;
        $context = context_module::instance($cmid);
        $PAGE->set_context($context);
        self::validate_parameters(
            self::getgrouplistresults_parameters(),
            ['sid' => $sid, 'cmid' => $cmid]
        );
        $groupresults = sessions::get_group_session_results($sid, $cmid);

        return ['groupresults' => $groupresults];
    }

    /**
     * Get group list results returns
     *
     * @return external_single_structure
     */
    public static function getgrouplistresults_returns(): external_single_structure {
        return new external_single_structure([
            'groupresults' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'groupname' => new external_value(PARAM_RAW, 'Name of group'),
                        'correctanswers' => new external_value(PARAM_INT, 'Num of correct answers'),
                        'incorrectanswers' => new external_value(PARAM_INT, 'Num of incorrect answers'),
                        'notanswers' => new external_value(PARAM_INT, 'Num of incorrect answers'),
                        'partially' => new external_value(PARAM_INT, 'Num of partially correct answers'),
                        'grouppoints' => new external_value(PARAM_RAW, 'Total points of group'),
                        'groupposition' => new external_value(PARAM_INT, 'Group position depending on the points'),
                    ], ''
                ), ''
            ),
        ]);
    }
}
