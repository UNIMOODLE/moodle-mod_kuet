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

// This line protects the file from being accessed by a URL directly.
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use question_definition;
use stdClass;

defined('MOODLE_INTERNAL') || die();
interface questionType {
    /**
     * @param \stdClass $useranswer
     * @param jqshow_questions_responses $response
     * @return mixed
     */
    public static function get_simple_mark(stdClass $useranswer,  jqshow_questions_responses $response);

    /**
     * @param \stdClass $participant
     * @param jqshow_questions_responses $response
     * @param array $answers
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @return mixed
     */
    public static function get_ranking_for_question(stdClass $participant,
                                                    jqshow_questions_responses $response,
                                                    array $answers,
                                                    jqshow_sessions $session,
                                                    jqshow_questions $question);

    /**
     * @param question_definition $question
     * @param jqshow_questions_responses[] $responses
     * @return mixed
     */
    public static function get_question_statistics( question_definition $question, array $responses);

    /**
     * @param jqshow_sessions $session
     * @param question_definition $questiondata
     * @param \stdClass $data
     * @param int $jqid
     * @return mixed
     */
    public static function get_question_report(jqshow_sessions $session,
                                               question_definition $questiondata,
                                               stdClass $data,
                                               int $jqid);


}

