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
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\api\groupmode;
use mod_jqshow\helpers\progress;
use mod_jqshow\models\calculated;
use mod_jqshow\models\ddwtos;
use mod_jqshow\models\description;
use mod_jqshow\models\matchquestion;
use mod_jqshow\models\multichoice;
use mod_jqshow\models\numerical;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\models\sessions as sessionsmodel;
use mod_jqshow\models\shortanswer;
use mod_jqshow\models\truefalse;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_user_progress;
use moodle_exception;
use ReflectionException;
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
     * @throws ReflectionException
     * @throws invalid_parameter_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER, $PAGE;
        $cmid = required_param('cmid', PARAM_INT);
        $sid = required_param('sid', PARAM_INT);
        $session = new jqshow_sessions($sid);
        $PAGE->set_title(get_string('session', 'jqshow') . ' ' . $session->get('name'));
        if ($session->get('status') !== sessionsmodel::SESSION_STARTED) {
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
                    $progressdata = json_decode($progress->get('other'), false);
                    if (isset($progressdata->endSession)) {
                        // END SESSION, no more question.
                        $data = sessions::export_endsession(
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
                    $newprogressdata = json_decode($newprogress->get('other'), false);
                    $question = jqshow_questions::get_question_by_jqid($newprogressdata->currentquestion);
                }
                /** @var questions $type */
                $type = questions::get_question_class_by_string_type($question->get('qtype'));
                $data = $type::export_question($question->get('id'),
                    $cmid,
                    $sid,
                    $question->get('jqshowid'));
                $response = jqshow_questions_responses::get_record(
                    ['session' => $question->get('sessionid'), 'jqid' => $question->get('id'), 'userid' => $USER->id]
                );
                if ($response !== false) {
                    $data->jqid = $question->get('id');
                    $data =
                        $type::export_question_response($data, base64_decode($response->get('response')), $response->get('result'));
                }
                $data->programmedmode = true;
                break;
            case sessions::INACTIVE_MANUAL:
            case sessions::PODIUM_MANUAL:
            case sessions::RACE_MANUAL:
                global $CFG;
                // SOCKETS!
                // Always start with waitingroom, and the socket will place you in the appropriate question if it has started.
                $data = new stdClass();
                $data->cmid = $cmid;
                $data->sid = $sid;
                $data->jqshowid = $session->get('jqshowid');
                $data->userid = $USER->id;
                $data->userfullname = $USER->firstname . ' ' . $USER->lastname;

                $data->manualmode = true;
                $data->waitingroom = true;
                $data->config = sessions::get_session_config($data->sid, $data->cmid);
                $data->sessionname = $data->config[0]['configvalue'];
                $typesocket = get_config('jqshow', 'sockettype');
                if ($typesocket === 'local') {
                    $data->socketurl = $CFG->wwwroot;
                    $data->port = get_config('jqshow', 'localport') !== false ? get_config('jqshow', 'localport') : '8080';
                }
                if ($typesocket === 'external') {
                    $data->socketurl = get_config('jqshow', 'externalurl');
                    $data->port = get_config('jqshow', 'externalport') !== false ? get_config('jqshow', 'externalport') : '8080';
                }
                if ($session->is_group_mode()) {
                    $data->isgroupmode = true;
                    $group = groupmode::get_user_group($data->userid, $session->get('groupings'));
                    $data->groupimage = groupmode::get_group_image($group, $sid, 1);
                    $data->groupname = $group->name;
                    $data->groupid = $group->id;
                } else {
                    $userpicture = new user_picture($USER);
                    $userpicture->size = 1;
                    $data->userimage = $userpicture->get_url($PAGE)->out(false);
                }
                break;
            default:
                throw new moodle_exception('incorrect_sessionmode', 'mod_jqshow', '',
                    [], get_string('incorrect_sessionmode', 'mod_jqshow'));
        }
        return $data;
    }
}
