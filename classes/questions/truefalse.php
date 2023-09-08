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
 * @package     XXXX
 * @author      202X Elena Barrios Galán <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class truefalse implements  jqshowquestion {

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

        // True.
        $answers[$questiondata->trueanswerid]['answertext'] = get_string('true', 'qtype_truefalse');
        $answers[$questiondata->trueanswerid]['answerid'] = $questiondata->trueanswerid;
        $statustrue = $questiondata->rightanswer ? 'correct' : 'incorrect';
        $answers[$questiondata->trueanswerid]['result'] = $statustrue;
        $answers[$questiondata->trueanswerid]['resultstr'] = get_string($statustrue, 'mod_jqshow');
//            $answers[$key]['fraction'] = round(1, 2); // en las trufalse no existe.
        $icon = new pix_icon('i/' . $statustrue,
            get_string($statustrue, 'mod_jqshow'), 'mod_jqshow', [
                'class' => 'icon',
                'title' => get_string($statustrue, 'mod_jqshow')
            ]);
        $usersicon = new pix_icon('i/'. $statustrue .'_users', '', 'mod_jqshow', [
            'class' => 'icon',
            'title' => ''
        ]);
        $answers[$questiondata->trueanswerid]['resulticon'] = $icon->export_for_pix();
        $answers[$questiondata->trueanswerid]['usersicon'] = $usersicon->export_for_pix();
        $answers[$questiondata->trueanswerid]['numticked'] = 0;

        // False.
        $answers[$questiondata->falseanswerid]['answertext'] = get_string('false', 'qtype_truefalse');
        $answers[$questiondata->falseanswerid]['answerid'] = $questiondata->falseanswerid;
        $statusfalse = $questiondata->rightanswer ? 'incorrect' : 'correct';
        $answers[$questiondata->falseanswerid]['result'] = $statusfalse;
        $answers[$questiondata->falseanswerid]['resultstr'] = get_string($statusfalse, 'mod_jqshow');
//            $answers[$questiondata->falseanswerid]['fraction'] = round(1, 2); // en las trufalse no existe.
        $icon = new pix_icon('i/' . $statusfalse,
            get_string($statusfalse, 'mod_jqshow'), 'mod_jqshow', [
                'class' => 'icon',
                'title' => get_string($statusfalse, 'mod_jqshow')
            ]);
        $usersicon = new pix_icon('i/'. $statusfalse .'_users', '', 'mod_jqshow', [
            'class' => 'icon',
            'title' => ''
        ]);
        $answers[$questiondata->falseanswerid]['resulticon'] = $icon->export_for_pix();
        $answers[$questiondata->falseanswerid]['usersicon'] = $usersicon->export_for_pix();
        $answers[$questiondata->falseanswerid]['numticked'] = 0;

        // Correct answer.
        $rightanswerkey = $questiondata->rightanswer ? $questiondata->trueanswerid : $questiondata->falseanswerid;
        $rightanswertext = $questiondata->rightanswer ? get_string('true', 'qtype_truefalse') :
            get_string('false', 'qtype_truefalse');
        $correctanswers[$rightanswerkey]['response'] = $rightanswertext;
        $correctanswers[$rightanswerkey]['score'] = grade::get_rounded_mark($questiondata->defaultmark);

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
     * @param stdClass $user
     * @param jqshow_questions_responses $response
     * @param array $answers
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_ranking_for_question($participant, $response, $answers, $session, $question) {
        $other = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
        $arrayresponses = explode(',', $other->answerids);

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
            if ($session->is_group_mode()) {
                $participant->grouppoints = grade::get_rounded_mark($spoints);
                $participant->score_moment = grade::get_rounded_mark($points);
            } else {
                $participant->userpoints = grade::get_rounded_mark($spoints);
                $participant->score_moment = grade::get_rounded_mark($points);
            }
            $participant->time = reports::get_user_time_in_question($session, $question, $response);
        }
        return $participant;
    }
}
