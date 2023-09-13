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
use question_bank;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/question/engine/bank.php');

class getquestionstatistics_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function getquestionstatistics_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'sid' => new external_value(PARAM_INT, 'session id'),
                'jqid' => new external_value(PARAM_INT, 'Id for jqshow_questions')
            ]
        );
    }

    /**
     * @param int $jqshowid
     * @param int $sid
     * @param int $jqid
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function getquestionstatistics(int $sid, int $jqid): array {
        self::validate_parameters(
            self::getquestionstatistics_parameters(),
            ['sid' => $sid, 'jqid' => $jqid]
        );
        $jqshowquestion = jqshow_questions::get_question_by_jqid($jqid);
        $question = question_bank::load_question($jqshowquestion->get('questionid'));
        $statistics = [];
        switch ($jqshowquestion->get('qtype')) {
            case questions::MULTICHOICE:
                foreach ($question->answers as $answer) {
                    $statistics[$answer->id] = ['answerid' => $answer->id, 'numberofreplies' => 0];
                }
                $responses = jqshow_questions_responses::get_question_responses($sid, $jqshowquestion->get('jqshowid'), $jqid);
                foreach ($responses as $response) {
                    foreach ($question->answers as $answer) {
                        $other = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
                        $arrayresponses = explode(',', $other->answerids);
                        foreach ($arrayresponses as $responseid) {
                            if ((int)$responseid === (int)$answer->id) {
                                $statistics[$answer->id]['numberofreplies']++;
                            }
                        }
                    }
                }
                break;
            case questions::MATCH:
            case questions::SHORTANSWER:
            case questions::NUMERICAL:
            case questions::CALCULATED:
            case questions::DESCRIPTION:
                // TODO review statistics for all, as most of them have to be applied.
                // There are no statistics defined for these modes.
                break;
            case questions::TRUE_FALSE:
                $statistics[$question->trueanswerid] = ['answerid' => $question->trueanswerid, 'numberofreplies' => 0];
                $statistics[$question->falseanswerid] = ['answerid' => $question->falseanswerid, 'numberofreplies' => 0];
                $responses = jqshow_questions_responses::get_question_responses($sid, $jqshowquestion->get('jqshowid'), $jqid);
                foreach ($responses as $response) {
                    foreach ($question->answers as $answer) {
                        $other = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
                        if ((int)$other->answerids === (int)$answer->id) {
                            $statistics[$answer->id]['numberofreplies']++;
                        }
                    }
                }
                break;
            default:
                throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                    [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        return ['statistics' => $statistics];
    }

    /**
     * @return external_single_structure
     */
    public static function getquestionstatistics_returns(): external_single_structure {
        return new external_single_structure(
            [
                'statistics' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'answerid' => new external_value(PARAM_INT, 'Answer id'),
                        'numberofreplies' => new external_value(PARAM_INT, 'Number of replies')
                    ], 'Number of replies for one answer.'
                ), 'List of answers with number of replies.', VALUE_OPTIONAL)
            ]
        );
    }
}
