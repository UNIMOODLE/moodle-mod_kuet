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
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class getfinalranking_external extends external_api {
    /**
     * @return external_function_parameters
     */
    public static function getfinalranking_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'sid' => new external_value(PARAM_INT, 'sessionid id'),
                'cmid' => new external_value(PARAM_INT, 'course module id')
            ]
        );
    }

    /**
     * @param int $sid
     * @param int $cmid
     * @return true[]
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function getfinalranking(int $sid, int $cmid): array {
        self::validate_parameters(
            self::getfinalranking_parameters(),
            ['sid' => $sid, 'cmid' => $cmid]
        );
        $session = jqshow_sessions::get_record(['id' => $sid]);
        $questions = new questions($session->get('jqshowid'), $cmid, $sid);
        $contextmodule = context_module::instance($cmid);

        $finalranking = sessions::get_final_ranking($sid, $cmid);
        $firstuserimageurl = $finalranking[0]->userimageurl;
        $firstuserfullname = $finalranking[0]->userfullname;
        $firstuserpoints = $finalranking[0]->userpoints;
        $seconduserimageurl = $finalranking[1]->userimageurl;
        $seconduserfullname = $finalranking[1]->userfullname;
        $seconduserpoints = $finalranking[1]->userpoints;
        $thirduserimageurl = $finalranking[2]->userimageurl;
        $thirduserfullname = $finalranking[2]->userfullname;
        $thirduserpoints = $finalranking[2]->userpoints;
        unset($finalranking[0], $finalranking[1], $finalranking[2]);
        $finalranking = array_values($finalranking);
        return [
            'finalranking' => $finalranking,
            'firstuserimageurl' => $firstuserimageurl,
            'firstuserfullname' => $firstuserfullname,
            'firstuserpoints' => $firstuserpoints,
            'seconduserimageurl' => $seconduserimageurl,
            'seconduserfullname' => $seconduserfullname,
            'seconduserpoints' => $seconduserpoints,
            'thirduserimageurl' => $thirduserimageurl,
            'thirduserfullname' => $thirduserfullname,
            'thirduserpoints' => $thirduserpoints,
            'sessionid' => $sid,
            'cmid' => $cmid,
            'jqshowid' => $session->get('jqshowid'),
            'numquestions' => $questions->get_num_questions(),
            'ranking' => true,
            'endsession' => true,
            'isteacher' => has_capability('mod/jqshow:startsession', $contextmodule)
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function getfinalranking_returns(): external_single_structure {
        return new external_single_structure([
            'finalranking' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'userimageurl' => new external_value(PARAM_URL, 'Url for user image'),
                        'userposition' => new external_value(PARAM_INT, 'User position depending on the points'),
                        'userfullname'   => new external_value(PARAM_RAW, 'Name of user'),
                        'userpoints' => new external_value(PARAM_INT, 'Total points of user')
                    ], ''
                ), ''
            ),
            'sessionid' => new external_value(PARAM_INT, 'jqshow_session id'),
            'cmid' => new external_value(PARAM_INT, 'course module id'),
            'jqshowid' => new external_value(PARAM_INT, 'jqshow id'),
            'numquestions' => new external_value(PARAM_INT, 'Number of questions for teacher panel'),
            'ranking' => new external_value(PARAM_BOOL, 'Is a ranking, for control panel context'),
            'endsession' => new external_value(PARAM_BOOL, 'Mark end session'),
            'firstuserimageurl' => new external_value(PARAM_URL, ''),
            'firstuserfullname' => new external_value(PARAM_RAW, ''),
            'firstuserpoints' => new external_value(PARAM_INT, ''),
            'seconduserimageurl' => new external_value(PARAM_URL, ''),
            'seconduserfullname' => new external_value(PARAM_RAW, ''),
            'seconduserpoints' => new external_value(PARAM_INT, ''),
            'thirduserimageurl' => new external_value(PARAM_URL, ''),
            'thirduserfullname' => new external_value(PARAM_RAW, ''),
            'thirduserpoints' => new external_value(PARAM_INT, ''),
        ]);
    }
}
