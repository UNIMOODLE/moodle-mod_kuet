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
namespace mod_jqshow\interfaces;

use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use question_definition;
use stdClass;

defined('MOODLE_INTERNAL') || die();
interface questionType {

    /**
     * @param stdClass $useranswer
     * @param jqshow_questions_responses $response
     * @return float
     */
    public static function get_simple_mark(stdClass $useranswer,  jqshow_questions_responses $response) : float;


    /**
     * @param stdClass $participant
     * @param jqshow_questions_responses $response
     * @param array $answers
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @return stdClass
     */
    public static function get_ranking_for_question(stdClass $participant,
                                                    jqshow_questions_responses $response,
                                                    array $answers,
                                                    jqshow_sessions $session,
                                                    jqshow_questions $question) : stdClass;

    /**
     * @param question_definition $question
     * @param jqshow_questions_responses[] $responses
     * @return mixed
     */
    public static function get_question_statistics( question_definition $question, array $responses) : array ;

    /**
     * @param jqshow_sessions $session
     * @param question_definition $questiondata
     * @param stdClass $data
     * @param int $jqid
     * @return mixed
     */
    public static function get_question_report(jqshow_sessions $session,
                                               question_definition $questiondata,
                                               stdClass $data,
                                               int $jqid) : stdClass;

    /**
     * @param int $jqid
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqshowid
     * @param bool $preview
     * @return mixed
     */
    public static function export_question(int $jqid, int $cmid, int $sessionid, int $jqshowid, bool $preview) : object ;

    /**
     * @param stdClass $data
     * @param string $response
     * @param int $result
     * @return mixed
     */
    public static function export_question_response(stdClass $data, string $response, int $result) : stdClass;

    /**
     * @param int $cmid
     * @param int $jqid
     * @param int $questionid
     * @param int $sessionid
     * @param int $jqshowid
     * @param string $statmentfeedback
     * @param int $userid
     * @param int $timeleft
     * @param array $custom
     * @return void
     */
    public static function question_response(
        int $cmid,
        int $jqid,
        int $questionid,
        int $sessionid,
        int $jqshowid,
        string $statmentfeedback,
        int $userid,
        int $timeleft,
        array $custom
    ): void;
}

