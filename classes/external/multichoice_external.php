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

use context_module;
use dml_transaction_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\models\questions;
use question_bank;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot. '/question/engine/bank.php');

class multichoice_external extends external_api {

    public static function multichoice_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'answerid' => new external_value(PARAM_INT, 'answer id'),
                'sessionid' => new external_value(PARAM_INT, 'id of session'),
                'jqshowid' => new external_value(PARAM_INT, 'id of jqshow'),
                'cmid' => new external_value(PARAM_INT, 'id of cm'),
                'questionid' => new external_value(PARAM_INT, 'id of question'),
                'preview' => new external_value(PARAM_BOOL, 'preview or not for grade'),
            ]
        );
    }

    /**
     * @param int $answerid
     * @param int $sessionid
     * @param int $jqshowid
     * @param int $cmid
     * @param int $questionid
     * @param bool $preview
     * @return array
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     */
    public static function multichoice(
        int $answerid, int $sessionid, int $jqshowid, int $cmid, int $questionid, bool $preview
    ): array {
        // TODO Review correctfeedback and incorrectfeedback.
        global $PAGE;
        self::validate_parameters(
            self::multichoice_parameters(),
            [
                'answerid' => $answerid,
                'sessionid' => $sessionid,
                'jqshowid' => $jqshowid,
                'cmid' => $cmid,
                'questionid' => $questionid,
                'preview' => $preview]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);

        $question = question_bank::load_question($questionid);
        $correctanswers = '';
        $answerfeedback = '';
        foreach ($question->answers as $key => $answer) {
            if ($answer->fraction !== '0.0000000') {
                $correctanswers .= $answer->id . ',';
            }
            if ($key === $answerid && $answerfeedback === '') {
                // TODO images do not work.
                $answerfeedback = questions::get_text(
                    $answer->feedback, 1, $answer->id, $question, 'answerfeedback'
                );
            }
        }
        $correctanswers = trim($correctanswers, ',');

        // TODO images do not work.
        $statmentfeedback = questions::get_text(
            $question->generalfeedback, 1, $question->id, $question, 'generalfeedback'
        );

        return [
            'reply_status' => true,
            'hasfeedbacks' => (bool)($statmentfeedback !== '' | $answerfeedback !== ''),
            'statment_feedback' => $statmentfeedback,
            'answer_feedback' => $answerfeedback,
            'correct_answers' => $correctanswers
        ];
    }

    public static function multichoice_returns(): external_single_structure {
        return new external_single_structure(
            [
                'reply_status' => new external_value(PARAM_BOOL, 'Status of reply'),
                'hasfeedbacks' => new external_value(PARAM_BOOL, 'Has feedback'),
                'statment_feedback' => new external_value(PARAM_RAW, 'HTML statment feedback', VALUE_OPTIONAL),
                'answer_feedback' => new external_value(PARAM_RAW, 'HTML answer feedback', VALUE_OPTIONAL),
                'correct_answers' => new external_value(PARAM_RAW, 'correct ansewrs ids separated by commas', VALUE_OPTIONAL)
            ]
        );
    }

}
