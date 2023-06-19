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

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\jqshow;
use mod_jqshow\persistents\jqshow_questions_responses;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class deleteresponses_external extends external_api {

    public static function deleteresponses_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'id of course module'),
                'sessionid' => new external_value(PARAM_INT, 'id of session'),
                'jqid' => new external_value(PARAM_INT, 'id of jqshow_question'),
            ]
        );
    }

    /**
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqid
     * @return array
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function deleteresponses(int $cmid, int $sessionid, int $jqid): array {
        self::validate_parameters(
            self::deleteresponses_parameters(),
            ['cmid' => $cmid, 'sessionid' => $sessionid, 'jqid' => $jqid]
        );
        $cmcontext = context_module::instance($cmid);
        if (has_capability('mod/jqshow:startsession', $cmcontext)) {
            $jqshow = new jqshow($cmid);
            return [
                'deleted' => jqshow_questions_responses::delete_question_resonses($jqshow->get_jqshow()->id, $sessionid, $jqid)
            ];
        }
        return [
            'deleted' => false
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function deleteresponses_returns(): external_single_structure {
        return new external_single_structure(
            [
                'deleted' => new external_value(PARAM_BOOL, 'deleted responses'),
            ]
        );
    }
}
