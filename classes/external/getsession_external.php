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
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_sessions;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class getsession_external extends external_api {
    /**
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
        $session = new jqshow_sessions($sid);
        return ['session' => [
            'id' => $session->get('id'),
            'name' => $session->get('name'),
            'jqshowid' => $session->get('jqshowid'),
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
            'status' => $session->get('status')
        ]];
    }

    /**
     * @return external_single_structure
     */
    public static function getsession_returns(): external_single_structure {
        return new external_single_structure([
            'session' => new external_single_structure(
                [
                    'id'   => new external_value(PARAM_INT, 'Id of session'),
                    'name'   => new external_value(PARAM_RAW, 'Name of session'),
                    'jqshowid' => new external_value(PARAM_INT, 'jqshowid of session'),
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
            )
        ]);
    }
}
