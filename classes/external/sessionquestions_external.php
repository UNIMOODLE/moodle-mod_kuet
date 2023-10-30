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
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use pix_icon;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class sessionquestions_external extends external_api {

    public static function sessionquestions_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'jqshowid' => new external_value(PARAM_INT, 'jqshowid'),
                'cmid' => new external_value(PARAM_INT, 'cmid for course module'),
                'sid' => new external_value(PARAM_INT, 'sid for session jqshow')
            ]
        );
    }

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @param int $sid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function sessionquestions(int $jqshowid, int $cmid, int $sid): array {
        self::validate_parameters(
            self::sessionquestions_parameters(),
            ['jqshowid' => $jqshowid, 'cmid' => $cmid, 'sid' => $sid]
        );
        $allquestions = (new questions($jqshowid, $cmid, $sid))->get_list();
        $questiondata = [];
        foreach ($allquestions as $question) {
            $questiondata[] = self::export_question($question, $cmid);
        }
        return ['jqshowid' => $jqshowid, 'cmid' => $cmid, 'sid' => $sid, 'sessionquestions' => $questiondata];
    }

    /**
     * @param jqshow_questions $question
     * @param int $cmid
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function export_question(jqshow_questions $question, int $cmid): stdClass {
        global $DB;
        $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
        $data = new stdClass();
        $data->questionnid = $question->get('id');
        $data->position = $question->get('qorder');
        $data->name = $questiondb->name;
        $data->type = $question->get('qtype');
        $icon = new pix_icon('icon', '', 'qtype_' . $question->get('qtype'), [
            'class' => 'icon',
            'title' => $question->get('qtype')
        ]);
        $data->icon = $icon->export_for_pix();
        $data->sid = $question->get('sessionid');
        $data->cmid = $cmid;
        $data->jqshowid = $question->get('jqshowid');
        $data->isvalid = $question->get('isvalid');
        $session = new jqshow_sessions($question->get('sessionid'));
        switch ($session->get('timemode')) {
            case sessions::NO_TIME:
            default:
                $data->time = ($question->get('timelimit') > 0) ? $question->get('timelimit') . 's' : '-';
                break;
            case sessions::SESSION_TIME:
                $numquestion = jqshow_questions::count_records(
                    ['sessionid' => $session->get('id'), 'jqshowid' => $session->get('jqshowid')]
                );
                $timeperquestion = round((int)$session->get('sessiontime') / $numquestion);
                $data->time = ($timeperquestion > 0) ? $timeperquestion . 's' : '-';
                break;
            case sessions::QUESTION_TIME:
                $data->time =
                    ($question->get('timelimit') > 0) ? $question->get('timelimit') . 's' : $session->get('questiontime') . 's';
                break;
        }
        $data->issuitable = in_array($question->get('qtype'), questions::TYPES, true);
        $data->version = $DB->get_field('question_versions', 'version', ['questionid' => $question->get('questionid')]);
        $cmcontext = context_module::instance($cmid);
        $data->managesessions = has_capability('mod/jqshow:managesessions', $cmcontext);
        $args = [
            'id' => $cmid,
            'jqid' => $question->get('id'),
            'sid' => $question->get('sessionid'),
            'jqsid' => $question->get('jqshowid'),
            'cid' => ($DB->get_record('jqshow', ['id' => $question->get('jqshowid')], 'course'))->course,
         ];
        $data->question_preview_url = (new moodle_url('/mod/jqshow/preview.php', $args))->out(false);
        $data->editquestionurl = (new moodle_url('/mod/jqshow/editquestion.php', $args))->out(false);
        return $data;
    }

    public static function sessionquestions_returns(): external_single_structure {
        return new external_single_structure([
            'sid' => new external_value(PARAM_INT, 'Session id'),
            'cmid' => new external_value(PARAM_INT, 'Course Module id'),
            'jqshowid' => new external_value(PARAM_INT, 'Jqshow id'),
            'sessionquestions' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'sid' => new external_value(PARAM_INT, 'Session id'),
                        'cmid' => new external_value(PARAM_INT, 'Course Module id'),
                        'jqshowid' => new external_value(PARAM_INT, 'Jqshow id'),
                        'questionnid' => new external_value(PARAM_INT, 'Question id'),
                        'position' => new external_value(PARAM_INT, 'Question order'),
                        'name' => new external_value(PARAM_RAW, 'Name of question'),
                        'icon' => new external_single_structure([
                            'key' => new external_value(PARAM_RAW, 'Key of icon'),
                            'component' => new external_value(PARAM_RAW, 'Component of icon'),
                            'title' => new external_value(PARAM_RAW, 'Title of icon'),
                        ], ''),
                        'type' => new external_value(PARAM_RAW, 'Question type'),
                        'isvalid' => new external_value(PARAM_RAW, 'Is question valid or missing config'),
                        'time' => new external_value(PARAM_RAW, 'Time of question'),
                        'version' => new external_value(PARAM_RAW, 'Question version'),
                        'managesessions' => new external_value(PARAM_BOOL, 'Capability'),
                        'question_preview_url' => new external_value(PARAM_URL, 'Url for preview'),
                        'editquestionurl' => new external_value(PARAM_URL, 'Url for edit question')
                    ], ''
                ), ''
            )
        ]);
    }
}
