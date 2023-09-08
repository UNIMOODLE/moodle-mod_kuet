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
use core\invalid_persistent_exception;
use dml_transaction_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\models\questions;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class getuserquestionresponse_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function getuserquestionresponse_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'jqid' => new external_value(PARAM_INT, 'id of jqshow_questions'),
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'sid' => new external_value(PARAM_INT, 'session id'),
                'uid' => new external_value(PARAM_INT, 'user id', VALUE_OPTIONAL)
            ]
        );
    }

    /**
     * @param int $jqid
     * @param int $cmid
     * @param int $sid
     * @param int $uid
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function getuserquestionresponse(int $jqid, int $cmid, int $sid, int $uid = 0): array {
        self::validate_parameters(
            self::getuserquestionresponse_parameters(),
            ['jqid' => $jqid, 'cmid' => $cmid, 'sid' => $sid, 'uid' => $uid]
        );
        global $USER;
        $userid = $uid === 0 ? $USER->id : $uid;
        $response = jqshow_questions_responses::get_question_response_for_user($userid, $sid, $jqid);
        $data = new stdClass();
        $data->sessionid = $sid;
        $data->cmid = $cmid;
        $data->jqid = $jqid;
        if ($response !== false) {
            $json = $response->get('response');
            $other = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            $data->jqshowid = $response->get('jqshow');
            $result = $response->get('result');
        } else if ($uid !== 0) { // It is a response review, where there is no response for the user. Mock required.
            $question = new jqshow_questions($jqid);
            $other = new stdClass();
            $other->questionid = $question->get('questionid');
            $other->hasfeedbacks = false;
            $other->correct_answers = '';
            $other->answerids = 0;
            $other->timeleft = 0;
            $other->type = $question->get('qtype');
            $other->response = [];
            $json = json_encode($other, JSON_THROW_ON_ERROR);
            $data->jqshowid = $question->get('jqshowid');
            $result = questions::NORESPONSE;
        } else {
            return [];
        }
        switch ($other->type) {
            case questions::MULTICHOICE:
                return (array)questions::export_multichoice_response($data, $json);
            case questions::MATCH:
                return (array)questions::export_match_response($data, $json, $result);
            case questions::TRUE_FALSE:
                return (array)questions::export_truefalse_response($data, $json);
            case questions::SHORTANSWER:
                return (array)questions::export_shortanswer_response($data, $json);
            case questions::NUMERICAL:
                return (array)questions::export_numerical_response($data, $json);
            default:
                throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                    [], get_string('question_nosuitable', 'mod_jqshow'));
        }
    }

    /**
     * @return external_single_structure
     */
    public static function getuserquestionresponse_returns(): external_single_structure {
        return new external_single_structure([
            'sessionid'   => new external_value(PARAM_INT, 'Session id', VALUE_OPTIONAL),
            'jqshowid' => new external_value(PARAM_INT, 'id of jqshow_questions', VALUE_OPTIONAL),
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_OPTIONAL),
            'answered' => new external_value(PARAM_BOOL, 'State of question', VALUE_OPTIONAL),
            'hasfeedbacks' => new external_value(PARAM_BOOL, 'Question has feedback', VALUE_OPTIONAL),
            'seconds' => new external_value(PARAM_INT, 'Seconds of questions', VALUE_OPTIONAL),
            'statment_feedback' => new external_value(PARAM_RAW, 'Question feedback', VALUE_OPTIONAL),
            'shortanswerresponse' => new external_value(PARAM_RAW, 'User text response', VALUE_OPTIONAL),
            'numericalresponse' => new external_value(PARAM_RAW, 'User text response', VALUE_OPTIONAL),
            'answer_feedback' => new external_value(PARAM_RAW, 'Response feedback', VALUE_OPTIONAL),
            'correct_answers' => new external_value(PARAM_RAW, 'Correct answers ids', VALUE_OPTIONAL),
            'programmedmode' => new external_value(PARAM_BOOL, 'Program mode', VALUE_OPTIONAL),
            'jsonresponse' => new external_value(PARAM_RAW, 'Json response', VALUE_OPTIONAL)
        ]);
    }
}
