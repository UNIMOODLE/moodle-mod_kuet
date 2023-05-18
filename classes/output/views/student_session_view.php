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

namespace mod_jqshow\output\views;
use coding_exception;
use core\invalid_persistent_exception;
use dml_exception;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use renderable;
use stdClass;
use templatable;
use renderer_base;
use user_picture;

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_session_view implements renderable, templatable {

    /**
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        // TODO refactor duplicate code for teacher and student.
        global $USER, $PAGE;
        $data = new stdClass();
        $data->cmid = required_param('cmid', PARAM_INT);
        $data->sid = required_param('sid', PARAM_INT);
        $data->isteacher = true;
        $data->userid = $USER->id;
        $data->userfullname = $USER->firstname . ' ' . $USER->lastname;
        /*$picturefields = explode(',', implode(',', \core_user\fields::get_picture_fields()));
        $user = new stdclass();
        $user->id = $USER->id;
        $user = username_load_fields_from_object($user, $USER, null, $picturefields);*/
        $userpicture = new user_picture($USER);
        $userpicture->size = 1;
        $data->userimage = $userpicture->get_url($PAGE)->out(false);
        $session = new jqshow_sessions($data->sid);
        $data->jqshowid = $session->get('jqshowid');
        // TODO detect if the session is still active, and if not, paint a session ended message.
        // TODO get progress from the student's session and paint the question they are asked.
        if ($session->get('sessionmode') === sessions::PODIUM_PROGRAMMED) {
            $firstquestion = jqshow_questions::get_first_question_of_session($data->sid);
            switch ($firstquestion->get('qtype')) {
                case 'multichoice':
                    $data = questions::export_multichoice(
                        $firstquestion->get('id'),
                        $data->cmid,
                        $data->sid,
                        $firstquestion->get('jqshowid'));
                    break;
                default:
                    throw new moodle_exception('question_nosuitable', 'mod_jqshow');
            }
            $data->programmedmode = true;
        } else {
            // SOCKETS!
            // Always start with waitingroom.
            $data->manualmode = true;
            $data->waitingroom = true;
            $data->config = sessions::get_session_config($data->sid);
            $data->sessionname = $data->config[0]['configvalue'];
            $data->port = get_config('jqshow', 'port') !== false ? get_config('jqshow', 'port') : '8080';
        }

        return $data;
    }
}
