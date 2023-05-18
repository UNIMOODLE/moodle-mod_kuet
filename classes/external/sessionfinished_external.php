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
use dml_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use stdClass;


defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class sessionfinished_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function sessionfinished_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'jqshowid' => new external_value(PARAM_INT, 'jqshow id'),
                'cmid' => new external_value(PARAM_INT, 'course module id')
            ]
        );
    }

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @return array|stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function sessionfinished(int $jqshowid, int $cmid): array {
        self::validate_parameters(
            self::sessionfinished_parameters(),
            ['jqshowid' => $jqshowid, 'cmid' => $cmid]
        );
        global $OUTPUT, $PAGE;
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);

        $nextsession = jqshow_sessions::get_next_session($jqshowid);
        return  [
            'sessionclosedimage' => $OUTPUT->image_url('f/error', 'mod_jqshow')->out(false),
            'hasnextsession' => $nextsession !== 0,
            'nextsessiontime' =>
                ($nextsession !== 0) ? userdate($nextsession, get_string('strftimedatetimeshort', 'core_langconfig')) : '',
            'urlreports' => (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $cmid]))->out(false),
        ];
    }

    /**
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
