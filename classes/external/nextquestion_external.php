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
use mod_jqshow\helpers\progress;
use mod_jqshow\models\questions;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_user_progress;
use moodle_exception;
use stdClass;

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
                'jqid' => new external_value(PARAM_INT, 'question id of jqshow_questions'),
                'manual' => new external_value(PARAM_BOOL, 'Mode of session', VALUE_OPTIONAL)
            ]
        );
    }

    /**
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqid
     * @param bool $manual
     * @param bool $isranking
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function nextquestion(
        int $cmid, int $sessionid, int $jqid, bool $manual = false
    ): array {
        global $PAGE, $USER;
        self::validate_parameters(
            self::nextquestion_parameters(),
            ['cmid' => $cmid, 'sessionid' => $sessionid, 'jqid' => $jqid, 'manual' => $manual]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);
        $nextquestion = jqshow_questions::get_next_question_of_session($sessionid, $jqid);
        $session = new jqshow_sessions($sessionid);
        if ($nextquestion !== false) {
            progress::set_progress(
                $nextquestion->get('jqshowid'), $sessionid, $USER->id, $cmid, $nextquestion->get('id')
            );
            switch ($nextquestion->get('qtype')) {
                case questions::MULTICHOICE:
                    $data = questions::export_multichoice(
                        $nextquestion->get('id'),
                        $cmid,
                        $sessionid,
                        $nextquestion->get('jqshowid'));
                    break;
                default:
                    throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                        [], get_string('question_nosuitable', 'mod_jqshow'));
            }
        } else {
            $finishdata = new stdClass();
            $finishdata->endSession = 1;
            jqshow_user_progress::add_progress(
                $session->get('jqshowid'), $sessionid, $USER->id, json_encode($finishdata, JSON_THROW_ON_ERROR)
            );
            $data = questions::export_endsession(
                $cmid,
                $sessionid);
        }
        $data->programmedmode = $manual === false;
        $data->showquestionfeedback = (int)$session->get('showfeedback') === 1;
        return (array)(new question_exporter($data, ['context' => $contextmodule]))->export($PAGE->get_renderer('mod_jqshow'));
    }

    /**
     * @return external_single_structure
     */
    public static function nextquestion_returns(): external_single_structure {
        return question_exporter::get_read_structure();
    }
}
