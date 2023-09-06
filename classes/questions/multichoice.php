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
use dml_exception;
use JsonException;
use mod_jqshow\api\grade;
use mod_jqshow\api\groupmode;
use mod_jqshow\helpers\reports;
use mod_jqshow\models\questions;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use pix_icon;
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
class multichoice implements  jqshowquestion {

    /**
     * @param jqshow_sessions $session
     * @param question_definition $questiondata
     * @param stdClass $data
     * @param int $jqid
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_question_report(jqshow_sessions $session,
                                               question_definition $questiondata,
                                               stdClass $data,
                                               int $jqid): stdClass {
        $answers = [];
        $correctanswers = [];
        foreach ($questiondata->answers as $key => $answer) {
            $answers[$key]['answertext'] = $answer->answer; // TODO get text with images questions::get_text.
            $answers[$key]['answerid'] = $key;
            if ($answer->fraction === '0.0000000' || strpos($answer->fraction, '-') === 0) {
                $answers[$key]['result'] = 'incorrect';
                $answers[$key]['resultstr'] = get_string('incorrect', 'mod_jqshow');
                $answers[$key]['fraction'] = round($answer->fraction, 2);
                $icon = new pix_icon('i/incorrect', get_string('incorrect', 'mod_jqshow'), 'mod_jqshow', [
                    'class' => 'icon',
                    'title' => get_string('incorrect', 'mod_jqshow')
                ]);
                $usersicon = new pix_icon('i/incorrect_users', '', 'mod_jqshow', [
                    'class' => 'icon',
                    'title' => ''
                ]);
            } else if ($answer->fraction === '1.0000000') {
                $answers[$key]['result'] = 'correct';
                $answers[$key]['resultstr'] = get_string('correct', 'mod_jqshow');
                $answers[$key]['fraction'] = '1';
                $icon = new pix_icon('i/correct', get_string('correct', 'mod_jqshow'), 'mod_jqshow', [
                    'class' => 'icon',
                    'title' => get_string('correct', 'mod_jqshow')
                ]);
                $usersicon = new pix_icon('i/correct_users', '', 'mod_jqshow', [
                    'class' => 'icon',
                    'title' => ''
                ]);
            } else {
                $answers[$key]['result'] = 'partially';
                $answers[$key]['resultstr'] = get_string('partially_correct', 'mod_jqshow');
                $answers[$key]['fraction'] = round($answer->fraction, 2);
                $icon = new pix_icon('i/correct', get_string('partially_correct', 'mod_jqshow'), 'mod_jqshow', [
                    'class' => 'icon',
                    'title' => get_string('partially_correct', 'mod_jqshow')
                ]);
                $usersicon = new pix_icon('i/partially_users', '', 'mod_jqshow', [
                    'class' => 'icon',
                    'title' => ''
                ]);
            }
            $answers[$key]['resulticon'] = $icon->export_for_pix();
            $answers[$key]['usersicon'] = $usersicon->export_for_pix();
            $answers[$key]['numticked'] = 0;
            if ($answer->fraction !== '0.0000000') { // Answers with punctuation, even if negative.
                $correctanswers[$key]['response'] = $answer->answer;
                $correctanswers[$key]['score'] = grade::get_rounded_mark($questiondata->defaultmark * $answer->fraction);
            }
        }
        $gmembers = [];
        if ($session->is_group_mode()) {
            $gmembers = groupmode::get_one_member_of_each_grouping_group($session->get('groupings'));
        }
        $responses = jqshow_questions_responses::get_question_responses($session->get('id'), $data->jqshowid, $jqid);
        foreach ($responses as $response) {
            if ($session->is_group_mode() && !in_array($response->get('userid'), $gmembers)) {
                continue;
            }
            $other = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
            if ($other->answerids !== '' && $other->answerids !== '0') { // TODO prepare for multianswer.
                $arrayanswerids = explode(',', $other->answerids);
                foreach ($arrayanswerids as $arrayanswerid) {
                    $answers[$arrayanswerid]['numticked']++;
                }
            }
            // TODO obtain the average time to respond to each option ticked. ???
        }
        $data->correctanswers = array_values($correctanswers);
        $data->answers = array_values($answers);
        return $data;
    }

    /**
     * @param stdClass $participant
     * @param jqshow_questions_responses $response
     * @param array $answers
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
        array $answers,
        jqshow_sessions $session,
        jqshow_questions $question): stdClass {
        $other = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
        $arrayresponses = explode(',', $other->answerids);
        if (count($arrayresponses) === 1) {
            foreach ($answers as $answer) {
                if ((int)$answer['answerid'] === (int)$arrayresponses[0]) {
                    $participant->response = $answer['result'];
                    $participant->responsestr = get_string($answer['result'], 'mod_jqshow');
                    $participant->answertext = $answer['answertext'];
                } else if ((int)$arrayresponses[0] === 0) {
                    $participant->response = 'noresponse';
                    $participant->responsestr = get_string('qstatus_' . questions::NORESPONSE, 'mod_jqshow');
                    $participant->answertext = '';
                }
                $points = grade::get_simple_mark($response);
                $spoints = grade::get_session_grade($participant->participantid, $session->get('id'),
                    $session->get('jqshowid'));
                $participant->userpoints = grade::get_rounded_mark($spoints);
                $participant->score_moment = grade::get_rounded_mark($points);
                $participant->time = reports::get_user_time_in_question($session, $question, $response);
            }
        } else {
            $answertext = '';
            foreach ($answers as $answer) {
                foreach ($arrayresponses as $responseid) {
                    if ((int)$answer['answerid'] === (int)$responseid) {
                        $answertext .= $answer['answertext'] . '<br>';
                    }
                }
            }
            $status = grade::get_status_response_for_multiple_answers($other->questionid, $other->answerids);
            $participant->response = get_string('qstatus_' . $status, 'mod_jqshow');
            $participant->responsestr = get_string($participant->response, 'mod_jqshow');
            $participant->answertext = trim($answertext, '<br>');
            $points = grade::get_simple_mark($response);
            $spoints = grade::get_session_grade($participant->id, $session->get('id'), $session->get('jqshowid'));
            $participant->userpoints = grade::get_rounded_mark($spoints);
            $participant->score_moment = grade::get_rounded_mark($points);
            $participant->time = reports::get_user_time_in_question($session, $question, $response);
        }
        return $participant;
    }
}
