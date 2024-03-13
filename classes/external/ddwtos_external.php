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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos

/**
 * Drag and drop question API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

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
use mod_kuet\models\ddwtos;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use qtype_ddwtos_question;
use question_bank;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot. '/question/engine/bank.php');

/**
 * Drag and drop question class
 */
class ddwtos_external extends external_api {

    /**
     * Drag and drop question parameter validation
     *
     * @return external_function_parameters
     */
    public static function ddwtos_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'sessionid' => new external_value(PARAM_INT, 'id of session'),
                'kuetid' => new external_value(PARAM_INT, 'id of kuet'),
                'cmid' => new external_value(PARAM_INT, 'id of cm'),
                'questionid' => new external_value(PARAM_INT, 'id of question'),
                'kid' => new external_value(PARAM_INT, 'id of question in kuet_questions'),
                'timeleft' => new external_value(PARAM_INT, 'Time left of question, if question has time, else 0.'),
                'preview' => new external_value(PARAM_BOOL, 'preview or not for grade'),
                'response' => new external_value(PARAM_RAW, 'Json string with responses'),
            ]
        );
    }

    /**
     * Drag and drop question
     *
     * @param int $sessionid
     * @param int $kuetid
     * @param int $cmid
     * @param int $questionid
     * @param int $kid
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
        int $kuetid,
        int $cmid,
        int $questionid,
        int $kid,
        int $timeleft,
        bool $preview,
        string $response
    ): array {
        global $PAGE, $USER;
        self::validate_parameters(
            self::ddwtos_parameters(),
            [
                'sessionid' => $sessionid,
                'kuetid' => $kuetid,
                'cmid' => $cmid,
                'questionid' => $questionid,
                'kid' => $kid,
                'timeleft' => $timeleft,
                'preview' => $preview,
                'response' => $response
            ]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);

        $session = new kuet_sessions($sessionid);
        $question = question_bank::load_question($questionid);
        $result = questions::NORESPONSE;
        if (assert($question instanceof qtype_ddwtos_question)) {
            $statmentfeedback = questions::get_text(
                $cmid, $question->generalfeedback, $question->generalfeedbackformat, $question->id, $question, 'generalfeedback'
            );
            $responsejson = json_decode($response, false);
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
                $custom = [
                    'responsetext' => $response,
                    'result' => $result,
                    'answerfeedback' => $answerfeedback,
                ];
                ddwtos::question_response(
                    $cmid,
                    $kid,
                    $questionid,
                    $sessionid,
                    $kuetid,
                    $statmentfeedback,
                    $USER->id,
                    $timeleft,
                    $custom
                );
            }
            $question = question_bank::load_question($questionid);
            if (!assert($question instanceof qtype_ddwtos_question)) {
                throw new moodle_exception('question_nosuitable', 'mod_kuet', '',
                    [], get_string('question_nosuitable', 'mod_kuet'));
            }
            $questiontextfeedback = ddwtos::get_question_text(
                $cmid, $question,
                (array)json_decode($response, false)
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
     * Drag and drop question returns
     *
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