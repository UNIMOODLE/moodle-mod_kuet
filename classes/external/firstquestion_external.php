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
use dml_transaction_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\exporter\question_exporter;
use mod_jqshow\models\calculated;
use mod_jqshow\models\matchquestion;
use mod_jqshow\models\multichoice;
use mod_jqshow\models\numerical;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\models\shortanswer;
use mod_jqshow\models\truefalse;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use ReflectionException;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class firstquestion_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function firstquestion_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'sessionid' => new external_value(PARAM_INT, 'session id'),
            ]
        );
    }


    /**
     * @param int $cmid
     * @param int $sessionid
     * @return array
     * @throws JsonException
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function firstquestion(int $cmid, int $sessionid): array {
        global $PAGE;
        self::validate_parameters(
            self::firstquestion_parameters(),
            ['cmid' => $cmid, 'sessionid' => $sessionid]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);
        $firstquestion = jqshow_questions::get_first_question_of_session($sessionid);
        switch ($firstquestion->get('qtype')) {
            case questions::MULTICHOICE:
                $question = multichoice::export_multichoice(
                    $firstquestion->get('id'),
                    $cmid,
                    $sessionid,
                    $firstquestion->get('jqshowid'));
                $question->showstatistics = true;
                break;
            case questions::MATCH:
                $question = matchquestion::export_match(
                    $firstquestion->get('id'),
                    $cmid,
                    $sessionid,
                    $firstquestion->get('jqshowid'));
                $question->showstatistics = false;
                break;
            case questions::TRUE_FALSE:
                $question = truefalse::export_truefalse(
                    $firstquestion->get('id'),
                    $cmid,
                    $sessionid,
                    $firstquestion->get('jqshowid'));
                $question->showstatistics = true;
                break;
            case questions::SHORTANSWER:
                $question = shortanswer::export_shortanswer(
                    $firstquestion->get('id'),
                    $cmid,
                    $sessionid,
                    $firstquestion->get('jqshowid'));
                $question->showstatistics = false;
                break;
            case questions::NUMERICAL:
                $question = numerical::export_numerical(
                    $firstquestion->get('id'),
                    $cmid,
                    $sessionid,
                    $firstquestion->get('jqshowid'));
                $question->showstatistics = false;
                break;
            case questions::CALCULATED:
                $question = calculated::export_calculated(
                    $firstquestion->get('id'),
                    $cmid,
                    $sessionid,
                    $firstquestion->get('jqshowid'));
                $question->showstatistics = false;
                break;
            default:
                throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                    [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        $session = new jqshow_sessions($sessionid);
        if ($session->get('sessionmode') === sessions::INACTIVE_PROGRAMMED ||
            $session->get('sessionmode') === sessions::PODIUM_PROGRAMMED ||
            $session->get('sessionmode') === sessions::RACE_PROGRAMMED) {
            $question->programmedmode = true;
        }
        return (array)(new question_exporter($question, ['context' => $contextmodule]))->export($PAGE->get_renderer('mod_jqshow'));
    }

    /**
     * @return external_single_structure
     */
    public static function firstquestion_returns(): external_single_structure {
        return question_exporter::get_read_structure();
    }
}
