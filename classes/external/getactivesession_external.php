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
use context_module;
use core\invalid_persistent_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\persistents\jqshow_sessions;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class getactivesession_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function getactivesession_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'jqshowid' => new external_value(PARAM_INT, 'Jqshow id'),
            ]
        );
    }

    /**
     * @param int $cmid
     * @param int $jqshowid
     * @return array
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public static function getactivesession(int $cmid, int $jqshowid): array {
        self::validate_parameters(
            self::getactivesession_parameters(),
            ['cmid' => $cmid, 'jqshowid' => $jqshowid]
        );
        $activessesion = jqshow_sessions::get_active_session_id($jqshowid);
        return [
            'active' => $activessesion
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function getactivesession_returns(): external_single_structure {
        return new external_single_structure(
            [
                'active' => new external_value(PARAM_INT, 'Id of active session for jqshowid. 0 if there is no active session.'),
            ]
        );
    }
}
