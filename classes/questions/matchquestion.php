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

namespace mod_jqshow\questions;
use coding_exception;
use context_course;
use core\invalid_persistent_exception;
use dml_exception;
use JsonException;
use mod_jqshow\api\grade;
use mod_jqshow\api\groupmode;
use mod_jqshow\helpers\reports;
use mod_jqshow\models\questions;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use pix_icon;
use qtype_match_question;
use question_definition;
use stdClass;

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matchquestion implements jqshowquestion {

    /**
     * @param jqshow_sessions $session
     * @param question_definition $questiondata
     * @param stdClass $data
     * @param int $jqid
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_question_report(jqshow_sessions $session,
                                               question_definition $questiondata,
                                               stdClass $data,
                                               int $jqid): stdClass {
        $answers = [];
        $correctanswers = [];
        if (!assert($questiondata instanceof qtype_match_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        if (isset($questiondata->stems)) {
            foreach ($questiondata->stems as $key => $answer) {
                $correctanswers[$key]['response'] = $answer . ' -> ' . $questiondata->choices[$key];
            }
        }
        $data->correctanswers = array_values($correctanswers);
        $data->answers = array_values($answers);
        $data->nostatistics = true;
        return $data;
    }

    /**
     * @param stdClass $participant
     * @param jqshow_questions_responses $response
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_ranking_for_question(
        stdClass $participant,
        jqshow_questions_responses $response,
        jqshow_sessions $session,
        jqshow_questions $question): stdClass {
        $other = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
        switch ($response->get('result')) {
            case questions::FAILURE:
                $participant->response = 'incorrect';
                break;
            case questions::SUCCESS:
                $participant->response = 'correct';
                break;
            case questions::PARTIALLY:
                $participant->response = 'partially';
                break;
            case questions::NORESPONSE:
            default:
                $participant->response = 'noresponse';
                break;
        }
        $participant->responsestr = get_string($participant->response, 'mod_jqshow');
        $points = grade::get_simple_mark($response);
        $spoints = grade::get_session_grade($participant->participantid, $session->get('id'),
            $session->get('jqshowid'));
        $participant->userpoints = grade::get_rounded_mark($spoints);
        $participant->score_moment = grade::get_rounded_mark($points);
        $participant->time = reports::get_user_time_in_question($session, $question, $response);
        return $participant;
    }

    /**
     * @param int $jqid
     * @param string $jsonresponse
     * @param int $result
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
    public static function match_response(
        int $jqid,
        string $jsonresponse,
        int $result,
        int $questionid,
        int $sessionid,
        int $jqshowid,
        string $statmentfeedback,
        string $answerfeedback,
        int $userid,
        int $timeleft
    ):void {
        global $COURSE;
        $coursecontext = context_course::instance($COURSE->id);
        $isteacher = has_capability('mod/jqshow:managesessions', $coursecontext);
        if (!$isteacher) {
            $session = new jqshow_sessions($sessionid);
            if ($session->is_group_mode()) {
                // TODO.
            } else {
                // Individual.
                $response = new stdClass();
                $response->questionid = $questionid;
                $response->hasfeedbacks = (bool)($statmentfeedback !== '' | $answerfeedback !== '');
                $response->timeleft = $timeleft;
                $response->type = questions::MATCH;
                $response->response = json_decode($jsonresponse);
                jqshow_questions_responses::add_response(
                    $jqshowid, $sessionid, $jqid, $userid, $result, json_encode($response, JSON_THROW_ON_ERROR)
                );
            }
        }
    }
}
