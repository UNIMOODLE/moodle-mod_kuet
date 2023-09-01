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
use dml_exception;
use JsonException;
use mod_jqshow\api\grade;
use mod_jqshow\api\groupmode;
use mod_jqshow\models\questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use stdClass;

class responses {

    /**
     * @param int $jqid
     * @param string $answerids
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
        int $jqid,
        string $answerids,
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
            self::manage_response($jqid, $answerids, $correctanswers, $questionid, $sessionid, $jqshowid,
                $statmentfeedback, $answerfeedback, $userid, $timeleft, questions::MULTIPLE_CHOICE);
        }
    }

    /**
     * @param int $jqid
     * @param string $answerids
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
    public static function truefalse_response(
        int $jqid,
        string $answerids,
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
            self::manage_response($jqid, $answerids, $correctanswers, $questionid, $sessionid, $jqshowid,
                $statmentfeedback, $answerfeedback, $userid, $timeleft, questions::TRUE_FALSE);
        }
    }
    /**
     * @param int $jqid
     * @param string $answerids
     * @param string $correctanswers
     * @param int $questionid
     * @param int $sessionid
     * @param int $jqshowid
     * @param string $statmentfeedback
     * @param string $answerfeedback
     * @param int $userid
     * @param int $timeleft
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function manage_response(int $jqid,
                                           string $answerids,
                                           string $correctanswers,
                                           int $questionid,
                                           int $sessionid,
                                           int $jqshowid,
                                           string $statmentfeedback,
                                           string $answerfeedback,
                                           int $userid,
                                           int $timeleft,
                                            string $qtype) : void {
        $result = self::get_status_response($answerids, $correctanswers, $questionid);
        $response = new stdClass(); // For snapshot.
        $response->questionid = $questionid;
        $response->hasfeedbacks = (bool)($statmentfeedback !== '' | $answerfeedback !== '');
        $response->correct_answers = $correctanswers;
        $response->answerids = $answerids;
        $response->timeleft = $timeleft;
        $response->type = $qtype;
        $session = new jqshow_sessions($sessionid);
        if ($session->is_group_mode()) {
            // All groupmembers has the same response saved on db.
            $num = jqshow_questions_responses::count_records(
                ['jqshow' => $jqshowid, 'session' => $sessionid, 'jqid' => $jqid, 'userid' => $userid]);
            if ($num > 0) {
                return;
            }
            // Groups.
            $gmemberids = groupmode::get_grouping_group_members_by_userid($session->get('groupings'), $userid);
            foreach ($gmemberids as $gmemberid) {
                jqshow_questions_responses::add_response(
                    $jqshowid, $sessionid, $jqid, $gmemberid, $result, json_encode($response, JSON_THROW_ON_ERROR)
                );
            }
        } else {
            // Individual.
            jqshow_questions_responses::add_response(
                $jqshowid, $sessionid, $jqid, $userid, $result, json_encode($response, JSON_THROW_ON_ERROR)
            );
        }
    }
    /**
     * @param string $answerids
     * @param string $correctanswers
     * @param int $questionid
     * @return string
     * @throws dml_exception
     */
    private static function get_status_response(string $answerids, string $correctanswers, int $questionid) : string {
        $result = questions::INVALID; // Invalid response.
        if ($answerids === '0' || $answerids === '') {
            $result = questions::NORESPONSE; // No response.
        } else if ($correctanswers !== '') {
            $correctids = explode(',', $correctanswers);
            if (count($correctids) > 1) { // Multianswers.
                $result = grade::get_status_response_for_multiple_answers($questionid, $answerids);
            } else if (in_array($answerids, $correctids, false)) {
                $result = questions::SUCCESS; // Correct.
            } else {
                $result = questions::FAILURE; // Incorrect.
            }
        }
        return $result;
    }
}
