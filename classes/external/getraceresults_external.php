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

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class getraceresults_external extends external_api {
    /**
     * @return external_function_parameters
     */
    public static function getraceresults_parameters(): external_function_parameters {
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
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function getraceresults(int $sid, int $cmid): array {
        self::validate_parameters(
            self::getraceresults_parameters(),
            ['sid' => $sid, 'cmid' => $cmid]
        );
        $session = new jqshow_sessions($sid);
        if ($session->is_group_mode()) {
            // TODO race results for groups.
            $groupmode = true;
            $groupresults = sessions::get_group_session_results($sid, $cmid);
            return ['groupmode' => true, 'groupresults' => $groupresults];
        }
        $userresults = sessions::get_session_results($sid, $cmid);
        $questions = sessions::breakdown_responses_for_race($userresults, $sid, $cmid, $session->get('jqshowid'));
        return ['groupmode' => false, 'userresults' => $userresults, 'questions' => $questions];
    }

    /**
     * @return external_single_structure
     */
    public static function getraceresults_returns(): external_single_structure {
        return new external_single_structure([
            'userresults' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'userfullname' => new external_value(PARAM_RAW, 'Name of user'),
                        'correctanswers' => new external_value(PARAM_INT, 'Num of correct answers'),
                        'userimageurl' => new external_value(PARAM_URL, 'User image'),
                        'userprofileurl' => new external_value(PARAM_URL, 'User profile'),
                        'incorrectanswers' => new external_value(PARAM_INT, 'Num of incorrect answers'),
                        'notanswers' => new external_value(PARAM_INT, 'Num of incorrect answers'),
                        'partially' => new external_value(PARAM_INT, 'Num of partially correct answers'),
                        'userpoints' => new external_value(PARAM_RAW, 'Total points of user'),
                        'userposition' => new external_value(PARAM_INT, 'User position depending on the points')
                    ], ''
                ), '', VALUE_OPTIONAL
            ),
            'groupmode' => new external_value(PARAM_BOOL, 'group mode activated'),
            'groupresults' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'groupname' => new external_value(PARAM_RAW, 'Name of group'),
                        'groupimageurl' => new external_value(PARAM_URL, 'Group Image'),
                        'groupprofileurl' => new external_value(PARAM_URL, 'Group Profile'),
                        'correctanswers' => new external_value(PARAM_INT, 'Num of correct answers'),
                        'incorrectanswers' => new external_value(PARAM_INT, 'Num of incorrect answers'),
                        'notanswers' => new external_value(PARAM_INT, 'Num of incorrect answers'),
                        'partially' => new external_value(PARAM_INT, 'Num of partially correct answers'),
                        'grouppoints' => new external_value(PARAM_RAW, 'Total points of group'),
                        'groupposition' => new external_value(PARAM_INT, 'Group position depending on the points')
                    ], ''
                ), '', VALUE_OPTIONAL
            ),
            'questions' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'questionnum' => new external_value(PARAM_RAW, 'Num of questions'),
                        'studentsresponse' => new external_multiple_structure(
                            new external_single_structure(
                            [
                                'userid' => new external_value(PARAM_INT, 'User of response'),
                                'responseclass' => new external_value(PARAM_RAW, 'Css Class for response')
                            ], ''
                        ), ''),
                    ], ''
                ), ''
            )
        ]);
    }
}
