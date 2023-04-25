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

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jqshow\external;

use coding_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\models\teacher;
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
                    'questions_number' => new external_value(PARAM_INT, 'Number of question for session'),
                    'managesessions' => new external_value(PARAM_BOOL, 'Capability'),
                    'initsession' => new external_value(PARAM_BOOL, 'Capability'),
                    'initsessionurl' => new external_value(PARAM_URL, 'Url for init session'),
                    'viewreporturl' => new external_value(PARAM_URL, 'Url for view report of session'),
                    'editsessionurl' => new external_value(PARAM_URL, 'Url for edit session'),
                    'date' => new external_value(PARAM_RAW, 'Init and en date of session, or empty'),
                    'status' => new external_value(PARAM_INT, 'Session status: active 1, initi 2 or finished 0'),
                    'issessionstarted' => new external_value(PARAM_BOOL, 'Session status: active 1, initi 2 or finished 0'),
                    'startedssionurl' => new external_value(PARAM_RAW, 'Session url', VALUE_OPTIONAL),
                    'stringsession' => new external_value(PARAM_RAW, 'String for button')
                ], ''
            ), ''
        );
    }
}
