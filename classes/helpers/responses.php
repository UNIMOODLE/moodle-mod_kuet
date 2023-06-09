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

namespace mod_jqshow\helpers;

use coding_exception;
use context_course;
use core\invalid_persistent_exception;
use JsonException;
use mod_jqshow\models\questions;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use moodle_exception;
use stdClass;

class responses {

    /**
     * @param int $answerid
     * @param string $correctanswers
     * @param int $questionid
     * @param int $sessionid
     * @param int $jqshowid
     * @param string $statmentfeedback
     * @param string $answerfeedback
     * @param int $userid
     * @param int $timeleft
     * @return void
     * @throws JsonException
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function multichoice_response(
        int $answerid,
        string $correctanswers,
        int $questionid,
        int $sessionid,
        int $jqshowid,
        string $statmentfeedback,
        string $answerfeedback,
        int $userid,
        int $timeleft
    ): void {
        global $COURSE;
        $coursecontext = context_course::instance($COURSE->id);
        $isteacher = has_capability('mod/jqshow:managesessions', $coursecontext);
        if (!$isteacher) {
            if ($answerid === 0) {
                $result = 2; // Not answered.
            } else if ($correctanswers !== '') {
                $correctids = explode(',', $correctanswers);
                if (in_array($answerid, $correctids, false)) {
                    $result = 1; // Correct.
                } else {
                    $result = 0; // Incorrect.
                }
            } else {
                $result = 3; // Invalid response.
            }
            $jqid = jqshow_questions::get_record(
                ['questionid' => $questionid, 'sessionid' => $sessionid, 'jqshowid' => $jqshowid],
                MUST_EXIST);
            $response = new stdClass(); // For snapshot.
            $response->questionid = $questionid;
            $response->hasfeedbacks = (bool)($statmentfeedback !== '' | $answerfeedback !== '');
            $response->correct_answers = $correctanswers;
            $response->answerid = $answerid;
            $response->timeleft = $timeleft;
            $response->type = questions::MULTIPLE_CHOICE;
            jqshow_questions_responses::add_response(
                $jqshowid, $sessionid, $jqid->get('id'), $userid, $result, json_encode($response, JSON_THROW_ON_ERROR)
            );
        }
    }
}
