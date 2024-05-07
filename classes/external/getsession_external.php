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
 * Get session API
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
 * Get session class
 */
class getsession_external extends external_api {
    /**
     * Get session parameters validation
     *
     * @return external_function_parameters
     */
    public static function getsession_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'sid' => new external_value(PARAM_INT, 'sessionid id'),
                'cmid' => new external_value(PARAM_INT, 'course module id'),
            ]
        );
    }

    /**
     * Get session
     *
     * @param int $sid
     * @param int $cmid
     * @return true[]
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public static function getsession(int $sid, int $cmid): array {
        self::validate_parameters(
            self::getsession_parameters(),
            ['sid' => $sid, 'cmid' => $cmid]
        );
        $session = new kuet_sessions($sid);
        return ['session' => [
            'id' => $session->get('id'),
            'name' => $session->get('name'),
            'kuetid' => $session->get('kuetid'),
            'anonymousanswer' => $session->get('anonymousanswer'),
            'sessionmode' => $session->get('sessionmode'),
            'countdown' => $session->get('countdown'),
            'showgraderanking' => $session->get('showgraderanking'),
            'randomquestions' => $session->get('randomquestions'),
            'randomanswers' => $session->get('randomanswers'),
            'showfeedback' => $session->get('showfeedback'),
            'showfinalgrade' => $session->get('showfinalgrade'),
            'startdate' => $session->get('startdate'),
            'enddate' => $session->get('enddate'),
            'automaticstart' => $session->get('automaticstart'),
            'timemode' => $session->get('timemode'),
            'sessiontime' => $session->get('sessiontime'),
            'questiontime' => $session->get('questiontime'),
            'groupings' => $session->get('groupings'),
            'status' => $session->get('status'),
        ]];
    }

    /**
     * Get session return
     *
     * @return external_single_structure
     */
    public static function getsession_returns(): external_single_structure {
        return new external_single_structure([
            'session' => new external_single_structure(
                [
                    'id'   => new external_value(PARAM_INT, 'Id of session'),
                    'name'   => new external_value(PARAM_RAW, 'Name of session'),
                    'kuetid' => new external_value(PARAM_INT, 'kuetid of session'),
                    'anonymousanswer' => new external_value(PARAM_INT, ''),
                    'sessionmode' => new external_value(PARAM_RAW, ''),
                    'countdown' => new external_value(PARAM_INT, ''),
                    'showgraderanking' => new external_value(PARAM_INT, ''),
                    'randomquestions' => new external_value(PARAM_INT, ''),
                    'randomanswers' => new external_value(PARAM_INT, ''),
                    'showfeedback' => new external_value(PARAM_INT, ''),
                    'showfinalgrade' => new external_value(PARAM_INT, ''),
                    'startdate' => new external_value(PARAM_INT, ''),
                    'enddate' => new external_value(PARAM_INT, ''),
                    'automaticstart' => new external_value(PARAM_INT, ''),
                    'timemode' => new external_value(PARAM_INT, ''),
                    'sessiontime' => new external_value(PARAM_INT, ''),
                    'questiontime' => new external_value(PARAM_INT, ''),
                    'groupings' => new external_value(PARAM_RAW, ''),
                    'status' => new external_value(PARAM_INT, ''),
                ], ''
            ),
        ]);
    }
}
