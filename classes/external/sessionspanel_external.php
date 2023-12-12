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
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_kuet\models\teacher;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class sessionspanel_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function sessionspanel_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'id of cm')
            ]
        );
    }

    /**
     * @param int $cmid
     * @return array
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function sessionspanel(int $cmid): array {
        global $USER;
        self::validate_parameters(
            self::sessionspanel_parameters(),
            ['cmid' => $cmid]
        );
        $teacher = new teacher($USER->id);
        return (array)$teacher->export_sessions($cmid);
    }

    /**
     * @return external_single_structure
     */
    public static function sessionspanel_returns(): external_single_structure {
        return new external_single_structure([
            'activesessions' => self::get_session_structure(),
            'endedsessions' => self::get_session_structure(),
            'courseid' => new external_value(PARAM_RAW, 'Course id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'hasqrcodeimage' => new external_value(PARAM_BOOL, 'Course module id'),
            'urlqrcode' => new external_value(PARAM_RAW, 'QRCode svg', VALUE_OPTIONAL),
            'createsessionurl' => new external_value(PARAM_URL, 'URL for create session'),
            'hasactivesession' => new external_value(PARAM_BOOL, 'URL for create session'),
        ]);
    }

    /**
     * @return external_multiple_structure
     */
    private static function get_session_structure(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'name'   => new external_value(PARAM_RAW, 'Name of session'),
                    'sessionid' => new external_value(PARAM_INT, 'Id of session'),
                    'sessionmode' => new external_value(PARAM_RAW, 'Mode of session'),
                    'timemode' => new external_value(PARAM_RAW, 'Mode of session'),
                    'sessiontime' => new external_value(PARAM_RAW, 'Time of session'),
                    'questions_number' => new external_value(PARAM_INT, 'Number of question for session'),
                    'managesessions' => new external_value(PARAM_BOOL, 'Capability'),
                    'hasconflict' => new external_value(PARAM_BOOL, 'Conflict of dates with other sessions.', VALUE_OPTIONAL),
                    'haswarning' => new external_value(PARAM_BOOL, 'The session should have already started.', VALUE_OPTIONAL),
                    'noquestions' => new external_value(PARAM_BOOL, 'The session no has questions.', VALUE_OPTIONAL),
                    'sessioncreating' => new external_value(PARAM_BOOL, 'The session is being created.', VALUE_OPTIONAL),
                    'initsession' => new external_value(PARAM_BOOL, 'Capability'),
                    'initsessionurl' => new external_value(PARAM_URL, 'Url for init session'),
                    'viewreporturl' => new external_value(PARAM_URL, 'Url for view report of session'),
                    'editsessionurl' => new external_value(PARAM_URL, 'Url for edit session'),
                    'date' => new external_value(PARAM_RAW, 'Init and en date of session, or empty'),
                    'finishingdate' => new external_value(PARAM_RAW, 'End date for completed session', VALUE_OPTIONAL),
                    'status' => new external_value(PARAM_INT, 'Session status: active 1, initi 2 or finished 0'),
                    'issessionstarted' => new external_value(PARAM_BOOL, 'Session status: active 1, initi 2 or finished 0'),
                    'startedssionurl' => new external_value(PARAM_RAW, 'Session url', VALUE_OPTIONAL),
                    'stringsession' => new external_value(PARAM_RAW, 'String for button')
                ], ''
            ), ''
        );
    }
}
