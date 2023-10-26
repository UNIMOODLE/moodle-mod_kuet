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
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\models\truefalse;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use question_bank;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot. '/question/engine/bank.php');

class truefalse_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function truefalse_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'answerid' => new external_value(PARAM_INT, 'answer id'),
                'sessionid' => new external_value(PARAM_INT, 'id of session'),
                'jqshowid' => new external_value(PARAM_INT, 'id of jqshow'),
                'cmid' => new external_value(PARAM_INT, 'id of cm'),
                'questionid' => new external_value(PARAM_INT, 'id of question'),
                'jqid' => new external_value(PARAM_INT, 'id of question in jqshow_questions'),
                'timeleft' => new external_value(PARAM_INT, 'Time left of question, if question has time, else 0.'),
                'preview' => new external_value(PARAM_BOOL, 'preview or not for grade'),
            ]
        );
    }

    /**
     * @param string $answerid
     * @param int $sessionid
     * @param int $jqshowid
     * @param int $cmid
     * @param int $questionid
     * @param int $jqid
     * @param int $timeleft
     * @param bool $preview
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function truefalse(
        int $answerid, int $sessionid, int $jqshowid, int $cmid, int $questionid, int $jqid, int $timeleft, bool $preview
    ): array {
        global $PAGE, $USER;
        self::validate_parameters(
            self::truefalse_parameters(),
            [
                'answerid' => $answerid,
                'sessionid' => $sessionid,
                'jqshowid' => $jqshowid,
                'cmid' => $cmid,
                'questionid' => $questionid,
                'jqid' => $jqid,
                'timeleft' => $timeleft,
                'preview' => $preview
            ]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);

        $question = question_bank::load_question($questionid);
        $statmentfeedback = questions::get_text(
            $cmid, $question->generalfeedback, 1, $question->id, $question, 'generalfeedback'
        );
        $answertext = '0';
        $correctanswer = $question->rightanswer ? (int)$question->trueanswerid : (int)$question->falseanswerid;
        if ((int)$answerid === (int)$question->trueanswerid) {
            $answertext = '1';
            $answerfeedback = questions::get_text(
                    $cmid, $question->truefeedback, 1, (int) $question->trueanswerid, $question, 'answerfeedback'
                ) . '<br>';
        } else {
            $answerfeedback = questions::get_text(
                    $cmid, $question->falsefeedback, 1, (int) $question->falseanswerid, $question, 'answerfeedback'
                ) . '<br>';
        }
        if ($preview === false) {
            $custom = [
                'answerids' => $answerid,
                'answertexts' => $answertext,
                'correctanswers' => $correctanswer,
                'answerfeedback' => $answerfeedback,
            ];
            truefalse::question_response(
                $cmid,
                $jqid,
                $questionid,
                $sessionid,
                $jqshowid,
                $statmentfeedback,
                $USER->id,
                $timeleft,
                $custom
            );
        }
        $session = new jqshow_sessions($sessionid);
        return [
            'reply_status' => true,
            'hasfeedbacks' => (bool)($statmentfeedback !== '' | $answerfeedback !== ''),
            'statment_feedback' => $statmentfeedback,
            'answer_feedback' => $answerfeedback,
            'correct_answers' => $correctanswer,
            'programmedmode' => ($session->get('sessionmode') === sessions::PODIUM_PROGRAMMED ||
                $session->get('sessionmode') === sessions::INACTIVE_PROGRAMMED ||
                $session->get('sessionmode') === sessions::RACE_PROGRAMMED),
            'preview' => $preview,
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function truefalse_returns(): external_single_structure {
        return new external_single_structure(
            [
                'reply_status' => new external_value(PARAM_BOOL, 'Status of reply'),
                'hasfeedbacks' => new external_value(PARAM_BOOL, 'Has feedback'),
                'statment_feedback' => new external_value(PARAM_RAW, 'HTML statment feedback', VALUE_OPTIONAL),
                'answer_feedback' => new external_value(PARAM_RAW, 'HTML answer feedback', VALUE_OPTIONAL),
                'correct_answers' => new external_value(PARAM_RAW, 'correct ansewrs ids separated by commas', VALUE_OPTIONAL),
                'programmedmode' => new external_value(PARAM_BOOL, 'Program mode for controls'),
                'preview' => new external_value(PARAM_BOOL, 'Question preview'),
            ]
        );
    }
}
