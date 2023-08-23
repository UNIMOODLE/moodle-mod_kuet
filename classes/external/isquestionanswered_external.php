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
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\api\groupmode;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class isquestionanswered_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function isquestionanswered_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'groupid' => new external_value(PARAM_INT, 'group id'),
                'userid' => new external_value(PARAM_INT, 'user id'),
                'sid' => new external_value(PARAM_INT, 'jqshow session id'),
                'jqid' => new external_value(PARAM_INT, 'jqshow question id')
            ]
        );
    }

    /**
     * @param int $groupid
     * @param int $userid
     * @param int $sid
     * @param int $jqid
     * @return bool[]|false[]
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public static function isquestionanswered(int $groupid, int $userid, int $sid, int $jqid): array {

        self::validate_parameters(
            self::isquestionanswered_parameters(),
            ['groupid' => $groupid, 'userid' => $userid, 'sid' => $sid, 'jqid' => $jqid]
        );

        $session = new jqshow_sessions($sid);
        if (!$session->is_group_mode()) {
            return ['answered' => false];
        }

        // Only for group mode.
        $answered = false;
        $groupmembers = groupmode::get_group_members($groupid);
        foreach ($groupmembers as $groupmember) {
            if ($userid == $groupmember) {
                continue;
            }
            $num = jqshow_questions_responses::count_records(['jqshow' => $session->get('jqshowid'),
                'session' => $session->get('id'), 'jqid' => $jqid, 'userid' => $groupmember->userid]);
            if ($num > 0) {
                $answered = true;
                continue;
            }
        }

        return [
            'answered' => $answered
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function isquestionanswered_returns(): external_single_structure {
        return new external_single_structure(
            [
                'answered' => new external_value(PARAM_BOOL, 'a group member has already answered or not'),
            ]
        );
    }
}
