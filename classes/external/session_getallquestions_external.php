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
use dml_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\models\questions;
use mod_jqshow\persistents\jqshow_questions;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class session_getallquestions_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function session_getallquestions_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'sessionid' => new external_value(PARAM_INT, 'id of session to copy')
            ]
        );
    }

    /**
     * This method is too slow if the volume of questions in the session is too high. Do not use.
     * @param int $cmid
     * @param int $sessionid
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function session_getallquestions(int $cmid, int $sessionid): array {
        global $DB, $PAGE, $COURSE;
        self::validate_parameters(
            self::session_getallquestions_parameters(),
            ['cmid' => $cmid, 'sessionid' => $sessionid]
        );
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'jqshow', $COURSE);
        $jqshow = $DB->get_record('jqshow', ['id' => $cm->instance], 'id', MUST_EXIST);
        $allquestions = (new questions($jqshow->id, $cmid, $sessionid))->get_list();
        $questiondata = [];
        foreach ($allquestions as $question) {
            $jqid = $question->get('id');
            switch ((new jqshow_questions($jqid))->get('qtype')){
                case 'multichoice':
                    $questiondata[] = questions::export_multichoice($jqid, $cmid, $sessionid, $jqshow->id, false);
                    break;
                default:
                    throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                        [], get_string('question_nosuitable', 'mod_jqshow'));
            }
        }
        return $questiondata;
    }

    /**
     * @return external_single_structure
     */
    public static function session_getallquestions_returns() {
        // TODO.
        return null;
    }
}
