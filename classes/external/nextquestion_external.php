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
use dml_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\models\questions;
use mod_jqshow\persistents\jqshow_questions;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class nextquestion_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function nextquestion_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'sessionid' => new external_value(PARAM_INT, 'session id'),
                'jqid' => new external_value(PARAM_INT, 'question id of jqshow_questions')
            ]
        );
    }

    /**
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function nextquestion(int $cmid, int $sessionid, int $jqid): array {
        global $PAGE;
        self::validate_parameters(
            self::nextquestion_parameters(),
            ['cmid' => $cmid, 'sessionid' => $sessionid, 'jqid' => $jqid]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);
        $nextquestion = jqshow_questions::get_next_question_of_session($sessionid, $jqid);
        // TODO consider it to be the last question, and in that case send an end-of-session screen.
        switch ($nextquestion->get('qtype')) {
            case 'multichoice':
                $data = questions::export_multichoice(
                    $nextquestion->get('id'),
                    $cmid,
                    $sessionid,
                    $nextquestion->get('jqshowid'));
                break;
            default:
                throw new moodle_exception('question_nosuitable', 'mod_jqshow');
        }
        $data->programmedmode = true;
        return (array)$data;
    }

    /**
     * @return external_single_structure
     */
    public static function nextquestion_returns(): external_single_structure {
        // TODO adapt to any type of question.
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
            'jqshowid' => new external_value(PARAM_INT, 'jq show id'),
            'questionid' => new external_value(PARAM_INT, 'id of jqshow'),
            'jqid' => new external_value(PARAM_INT, 'id of jqshow_questions'),
            'question_index_string' => new external_value(PARAM_RAW, 'String for progress session'),
            'sessionprogress' => new external_value(PARAM_INT, 'Int for progress bar'),
            'questiontext' => new external_value(PARAM_RAW, 'Statement of question'),
            'questiontextformat' => new external_value(PARAM_RAW, 'Format of statement'),
            'hastime' => new external_value(PARAM_BOOL, 'Question has time'),
            'seconds' => new external_value(PARAM_INT, 'Seconds of question', VALUE_OPTIONAL),
            'preview' => new external_value(PARAM_BOOL, 'Is preview or not', VALUE_OPTIONAL),
            'numanswers' => new external_value(PARAM_INT, 'Num of answer for multichoice', VALUE_OPTIONAL),
            'name' => new external_value(PARAM_RAW, 'Name of question'),
            'qtype' => new external_value(PARAM_RAW, 'Type of question'),
            'programmedmode' => new external_value(PARAM_BOOL, 'Mode programmed', VALUE_OPTIONAL),
            'manualmode' => new external_value(PARAM_BOOL, 'Mode manual', VALUE_OPTIONAL),
            'port' => new external_value(PARAM_RAW, 'Port for sockets', VALUE_OPTIONAL),
            'multichoice' => new external_value(PARAM_BOOL, 'Type of question for mustache', VALUE_OPTIONAL),
            'answers' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'answerid'   => new external_value(PARAM_INT, 'Answer id'),
                        'questionid' => new external_value(PARAM_INT, 'Question id of table questions'),
                        'answertext' => new external_value(PARAM_RAW, 'Answer text'),
                        'fraction' => new external_value(PARAM_RAW, 'value of answer')
                    ], ''
                ), '', VALUE_OPTIONAL
            ),
            'feedbacks' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'answerid'   => new external_value(PARAM_INT, 'Answer id of feedback'),
                        'feedback' => new external_value(PARAM_RAW, 'Feedback text'),
                        'feedbackformat' => new external_value(PARAM_INT, 'Format of feedback')
                    ], ''
                ), '', VALUE_OPTIONAL
            )
        ]);
    }
}
