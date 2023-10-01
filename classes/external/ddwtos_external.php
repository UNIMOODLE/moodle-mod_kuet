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
use context_module;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\models\ddwtos;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use qtype_ddwtos_question;
use question_bank;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot. '/question/engine/bank.php');

class ddwtos_external extends external_api {

    public static function ddwtos_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'sessionid' => new external_value(PARAM_INT, 'id of session'),
                'jqshowid' => new external_value(PARAM_INT, 'id of jqshow'),
                'cmid' => new external_value(PARAM_INT, 'id of cm'),
                'questionid' => new external_value(PARAM_INT, 'id of question'),
                'jqid' => new external_value(PARAM_INT, 'id of question in jqshow_questions'),
                'timeleft' => new external_value(PARAM_INT, 'Time left of question, if question has time, else 0.'),
                'preview' => new external_value(PARAM_BOOL, 'preview or not for grade'),
                'response' => new external_value(PARAM_RAW, 'Json string with responses'),
            ]
        );
    }

    /**
     * @param int $sessionid
     * @param int $jqshowid
     * @param int $cmid
     * @param int $questionid
     * @param int $jqid
     * @param int $timeleft
     * @param bool $preview
     * @param string $response
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function ddwtos(
        int $sessionid,
        int $jqshowid,
        int $cmid,
        int $questionid,
        int $jqid,
        int $timeleft,
        bool $preview,
        string $response
    ): array {
        global $PAGE, $USER;
        self::validate_parameters(
            self::ddwtos_parameters(),
            [
                'sessionid' => $sessionid,
                'jqshowid' => $jqshowid,
                'cmid' => $cmid,
                'questionid' => $questionid,
                'jqid' => $jqid,
                'timeleft' => $timeleft,
                'preview' => $preview,
                'response' => $response
            ]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);

        $session = new jqshow_sessions($sessionid);
        $question = question_bank::load_question($questionid);
        $result = questions::NORESPONSE;
        if (assert($question instanceof qtype_ddwtos_question)) {
            $statmentfeedback = questions::get_text(
                $cmid, $question->generalfeedback, $question->generalfeedbackformat, $question->id, $question, 'generalfeedback'
            );
            $responsejson = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
            $moodleresult = $question->grade_response((array)$responsejson);
            $answerfeedback = '';
            if (isset($moodleresult[1])) {
                switch (get_class($moodleresult[1])) {
                    case 'question_state_gradedwrong':
                        $result = questions::FAILURE;
                        $answerfeedback = questions::get_text(
                            $cmid,
                            $question->incorrectfeedback,
                            $question->incorrectfeedbackformat,
                            $question->id,
                            $question,
                            'feedback'
                        );
                        break;
                    case 'question_state_gradedpartial':
                        $result = questions::PARTIALLY;
                        $answerfeedback = questions::get_text(
                            $cmid,
                            $question->partiallycorrectfeedback,
                            $question->partiallycorrectfeedbackformat,
                            $question->id,
                            $question,
                            'feedback'
                        );
                        break;
                    case 'question_state_gradedright':
                        $result = questions::SUCCESS;
                        $answerfeedback = questions::get_text(
                            $cmid,
                            $question->correctfeedback,
                            $question->correctfeedbackformat,
                            $question->id,
                            $question,
                            'feedback'
                        );
                        break;
                    default:
                        break;
                }
            }
            if ($preview === false) {
                ddwtos::ddwtos_response(
                    $jqid,
                    $response,
                    $result,
                    $questionid,
                    $sessionid,
                    $jqshowid,
                    $statmentfeedback,
                    $answerfeedback,
                    $USER->id,
                    $timeleft
                );
            }
            $question = question_bank::load_question($questionid);
            if (!assert($question instanceof qtype_ddwtos_question)) {
                throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                    [], get_string('question_nosuitable', 'mod_jqshow'));
            }
            $questiontextfeedback = ddwtos::get_question_text(
                $cmid, $question,
                (array)json_decode($response, false, 512, JSON_THROW_ON_ERROR)
            );
            return [
                'reply_status' => true,
                'result' => $result,
                'hasfeedbacks' => (bool)($statmentfeedback !== ''),
                'statment_feedback' => $statmentfeedback,
                'answer_feedback' => $answerfeedback,
                'question_text_feedback' => base64_encode($questiontextfeedback),
                'programmedmode' => ($session->get('sessionmode') === sessions::PODIUM_PROGRAMMED ||
                    $session->get('sessionmode') === sessions::INACTIVE_PROGRAMMED ||
                    $session->get('sessionmode') === sessions::RACE_PROGRAMMED),
                'preview' => $preview,
            ];
        }

        return [
            'reply_status' => false,
            'hasfeedbacks' => false,
            'programmedmode' => ($session->get('sessionmode') === sessions::PODIUM_PROGRAMMED ||
                $session->get('sessionmode') === sessions::INACTIVE_PROGRAMMED ||
                $session->get('sessionmode') === sessions::RACE_PROGRAMMED),
            'preview' => $preview,
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function ddwtos_returns(): external_single_structure {
        return new external_single_structure(
            [
                'reply_status' => new external_value(PARAM_BOOL, 'Status of reply'),
                'result' => new external_value(PARAM_INT, 'Result of reply'),
                'hasfeedbacks' => new external_value(PARAM_BOOL, 'Has feedback'),
                'statment_feedback' => new external_value(PARAM_RAW, 'HTML statment feedback', VALUE_OPTIONAL),
                'answer_feedback' => new external_value(PARAM_RAW, 'HTML statment feedback', VALUE_OPTIONAL),
                'question_text_feedback' => new external_value(PARAM_RAW, 'HTML text with feedback', VALUE_OPTIONAL),
                'programmedmode' => new external_value(PARAM_BOOL, 'Program mode for controls'),
                'preview' => new external_value(PARAM_BOOL, 'Question preview'),
            ]
        );
    }
}