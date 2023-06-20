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

namespace mod_jqshow\output\views;
use coding_exception;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use JsonException;
use mod_jqshow\helpers\progress;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_user_progress;
use moodle_exception;
use renderable;
use stdClass;
use templatable;
use renderer_base;
use user_picture;

class student_session_view implements renderable, templatable {

    /**
     * @param renderer_base $output
     * @return stdClass
     * @throws JsonException
     * @throws dml_transaction_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        // TODO refactor duplicate code for teacher and student.
        global $USER, $PAGE;
        $cmid = required_param('cmid', PARAM_INT);
        $sid = required_param('sid', PARAM_INT);
        $session = new jqshow_sessions($sid);
        if ($session->get('status') !== 2) {
            // TODO session layaout not active or redirect to cmid view.
            throw new moodle_exception('notactivesession', 'mod_jqshow', '',
                [], get_string('notactivesession', 'mod_jqshow'));
        }
        switch ($session->get('sessionmode')) {
            case sessions::INACTIVE_PROGRAMMED:
            case sessions::PODIUM_PROGRAMMED:
            case sessions::RACE_PROGRAMMED:
                $progress = jqshow_user_progress::get_session_progress_for_user(
                    $USER->id, $session->get('id'), $session->get('jqshowid')
                );
                if ($progress !== false) {
                    $progressdata = json_decode($progress->get('other'), false, 512, JSON_THROW_ON_ERROR);
                    if (isset($progressdata->endSession)) {
                        // END SESSION, no more question.
                        $data = questions::export_endsession(
                            $cmid,
                            $sid);
                        $data->programmedmode = true;
                        break;
                    }
                    $question = jqshow_questions::get_question_by_jqid($progressdata->currentquestion);
                } else {
                    progress::set_progress(
                        $session->get('jqshowid'), $session->get('id'), $USER->id, $cmid, 0
                    );
                    $newprogress = jqshow_user_progress::get_session_progress_for_user(
                        $USER->id, $session->get('id'), $session->get('jqshowid')
                    );
                    $newprogressdata = json_decode($newprogress->get('other'), false, 512, JSON_THROW_ON_ERROR);
                    $question = jqshow_questions::get_question_by_jqid($newprogressdata->currentquestion);
                }
                switch ($question->get('qtype')) {
                    case 'multichoice':
                        $data = questions::export_multichoice(
                            $question->get('id'),
                            $cmid,
                            $sid,
                            $question->get('jqshowid'));
                        $response = jqshow_questions_responses::get_record(
                            ['session' => $question->get('sessionid'), 'jqid' => $question->get('id'), 'userid' => $USER->id]
                        );
                        if ($response !== false) {
                            $data = questions::export_multichoice_response($data, $response->get('response'));
                        }
                        break;
                    default:
                        throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                            [], get_string('question_nosuitable', 'mod_jqshow'));
                }
                $data->programmedmode = true;
                break;
            case sessions::INACTIVE_MANUAL:
            case sessions::PODIUM_MANUAL:
            case sessions::RACE_MANUAL:
                // SOCKETS!
                // Always start with waitingroom, and the socket will place you in the appropriate question if it has started.
                $data = new stdClass();
                $data->cmid = $cmid;
                $data->sid = $sid;
                $data->jqshowid = $session->get('jqshowid');
                $data->userid = $USER->id;
                $data->userfullname = $USER->firstname . ' ' . $USER->lastname;
                $userpicture = new user_picture($USER);
                $userpicture->size = 1;
                $data->userimage = $userpicture->get_url($PAGE)->out(false);
                $data->manualmode = true;
                $data->waitingroom = true;
                // $data->config = sessions::get_session_config($data->sid);
                $data->sessionname = $data->config[0]['configvalue'];
                $data->port = get_config('jqshow', 'port') !== false ? get_config('jqshow', 'port') : '8080';
                break;
            default:
                throw new moodle_exception('incorrect_sessionmode', 'mod_jqshow', '',
                    [], get_string('incorrect_sessionmode', 'mod_jqshow'));
        }
        return $data;
    }
}
