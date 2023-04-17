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
use context_course;
use dml_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\persistents\jqshow_sessions;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class editsessionsettings_external extends external_api {

    public static function editsession_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'course id'),
                'sessionid' => new external_value(PARAM_INT, 'id of session to copy'),
                'name' => new external_value(PARAM_RAW, 'element to edit'),
                'value' => new external_value(PARAM_RAW, 'new value')
            ]
        );
    }

    /**
     * @param int $courseid
     * @param int $sessionid
     * @return array
     * @throws dml_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public static function editsession(int $courseid, int $sessionid, string $name, string $value): array {
        global $USER;
        self::validate_parameters(
            self::editsession_parameters(),
            ['courseid' => $courseid, 'sessionid' => $sessionid, 'name' => $name, 'value' => $value]
        );
        $coursecontext = context_course::instance($courseid);
        if ($coursecontext !== null && has_capability('mod/jqshow:managesessions', $coursecontext, $USER)) {
            return [
                'updated' => jqshow_sessions::duplicate_session($sessionid)
            ];
        }
        return [
            'updated' => false
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function editsession_returns(): external_single_structure {
        return new external_single_structure(
            [
                'updated' => new external_value(PARAM_BOOL, 'updated'),
            ]
        );
    }
}
