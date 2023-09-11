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
use dml_transaction_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\exporter\question_exporter;
use mod_jqshow\helpers\progress;
use mod_jqshow\models\matchquestion;
use mod_jqshow\models\multichoice;
use mod_jqshow\models\numerical;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\models\shortanswer;
use mod_jqshow\models\truefalse;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_user_progress;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class jumptoquestion_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function jumptoquestion_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'sessionid' => new external_value(PARAM_INT, 'session id'),
                'position' => new external_value(PARAM_INT, 'Order of question'),
                'manual' => new external_value(PARAM_BOOL, 'Mode of session', VALUE_OPTIONAL)
            ]
        );
    }

    /**
     * @param int $cmid
     * @param int $sessionid
     * @param int $position
     * @param bool $manual
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function jumptoquestion(int $cmid, int $sessionid, int $position, bool $manual = false): array {
        global $PAGE, $USER;
        self::validate_parameters(
            self::jumptoquestion_parameters(),
            ['cmid' => $cmid, 'sessionid' => $sessionid, 'position' => $position]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);
        $question = jqshow_questions::get_question_by_position($sessionid, $position);
        if ($question !== false) {
            progress::set_progress(
                $question->get('jqshowid'), $sessionid, $USER->id, $cmid, $question->get('id')
            );
            switch ($question->get('qtype')) {
                case questions::MULTICHOICE:
                    $data = multichoice::export_multichoice(
                        $question->get('id'),
                        $cmid,
                        $sessionid,
                        $question->get('jqshowid'));
                    $data->showstatistics = true;
                    break;
                case questions::MATCH:
                    $data = matchquestion::export_match(
                        $question->get('id'),
                        $cmid,
                        $sessionid,
                        $question->get('jqshowid'));
                    $data->showstatistics = false;
                    break;
                case questions::TRUE_FALSE:
                    $data = truefalse::export_truefalse(
                        $question->get('id'),
                        $cmid,
                        $sessionid,
                        $question->get('jqshowid'));
                    $data->showstatistics = true;
                    break;
                case questions::SHORTANSWER:
                    $data = shortanswer::export_shortanswer(
                        $question->get('id'),
                        $cmid,
                        $sessionid,
                        $question->get('jqshowid'));
                    $data->showstatistics = false;
                    break;
                case questions::NUMERICAL:
                    $data = numerical::export_numerical(
                        $question->get('id'),
                        $cmid,
                        $sessionid,
                        $question->get('jqshowid'));
                    $data->showstatistics = false;
                    break;
                default:
                    throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                        [], get_string('question_nosuitable', 'mod_jqshow'));
            }
        } else {
            $session = new jqshow_sessions($sessionid);
            $finishdata = new stdClass();
            $finishdata->endSession = 1;
            jqshow_user_progress::add_progress(
                $session->get('jqshowid'), $sessionid, $USER->id, json_encode($finishdata, JSON_THROW_ON_ERROR)
            );
            $data = sessions::export_endsession(
                $cmid,
                $sessionid);
        }
        $data->programmedmode = $manual === false;
        return (array)(new question_exporter($data, ['context' => $contextmodule]))->export($PAGE->get_renderer('mod_jqshow'));
    }

    /**
     * @return external_single_structure
     */
    public static function jumptoquestion_returns(): external_single_structure {
        return question_exporter::get_read_structure();
    }
}
