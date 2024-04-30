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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos..

/**
 * Student session view renderer
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\output\views;
use coding_exception;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use invalid_parameter_exception;
use JsonException;
use mod_kuet\api\groupmode;
use mod_kuet\helpers\progress;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\models\sessions as sessionsmodel;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use mod_kuet\persistents\kuet_user_progress;
use moodle_exception;
use ReflectionException;
use renderable;
use stdClass;
use templatable;
use renderer_base;
use user_picture;

/**
 * Student session view renderable class
 */
class student_session_view implements renderable, templatable {

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER, $PAGE;
        $cmid = required_param('cmid', PARAM_INT);
        $sid = required_param('sid', PARAM_INT);
        $session = new kuet_sessions($sid);
        $PAGE->set_title(get_string('session', 'kuet') . ' ' . $session->get('name'));
        if ($session->get('status') !== sessionsmodel::SESSION_STARTED) {
            // 3IP session layaout not active or redirect to cmid view.
            throw new moodle_exception('notactivesession', 'mod_kuet', '',
                [], get_string('notactivesession', 'mod_kuet'));
        }
        switch ($session->get('sessionmode')) {
            case sessions::INACTIVE_PROGRAMMED:
            case sessions::PODIUM_PROGRAMMED:
            case sessions::RACE_PROGRAMMED:
                $progress = kuet_user_progress::get_session_progress_for_user(
                    $USER->id, $session->get('id'), $session->get('kuetid')
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
                    $question = kuet_questions::get_question_by_kid($progressdata->currentquestion);
                } else {
                    progress::set_progress(
                        $session->get('kuetid'), $session->get('id'), $USER->id, $cmid, 0
                    );
                    $newprogress = kuet_user_progress::get_session_progress_for_user(
                        $USER->id, $session->get('id'), $session->get('kuetid')
                    );
                    $newprogressdata = json_decode($newprogress->get('other'), false);
                    $question = kuet_questions::get_question_by_kid($newprogressdata->currentquestion);
                }
                /** @var questions $type */
                $type = questions::get_question_class_by_string_type($question->get('qtype'));
                $data = $type::export_question($question->get('id'),
                    $cmid,
                    $sid,
                    $question->get('kuetid'));
                $response = kuet_questions_responses::get_record(
                    ['session' => $question->get('sessionid'), 'kid' => $question->get('id'), 'userid' => $USER->id]
                );
                if ($response !== false) {
                    $data->kid = $question->get('id');
                    $data =
                        $type::export_question_response($data, base64_decode($response->get('response')), $response->get('result'));
                }
                $data->programmedmode = true;
                if ($session->is_group_mode()) {
                    $data->isgroupmode = true;
                    $group = groupmode::get_user_group($USER->id, $session);
                    $data->groupimage = groupmode::get_group_image($group, $sid, 1);
                    $data->groupname = $group->name;
                    $data->groupid = $group->id;
                    if ($session->get('anonymousanswer')) {
                        unset($data->groupimage);
                        unset($data->groupname);
                    }
                }
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
                $data->kuetid = $session->get('kuetid');
                $data->userid = $USER->id;
                $data->userfullname = $USER->firstname . ' ' . $USER->lastname;
                if ($session->get('anonymousanswer') === 1) {
                    unset($data->userimageurl);
                    $data->userfullname = '**********';
                }
                $data->manualmode = true;
                $data->waitingroom = true;
                $data->config = sessions::get_session_config($data->sid, $data->cmid);
                $data->sessionname = $data->config[0]['configvalue'];
                $typesocket = get_config('kuet', 'sockettype');
                if ($typesocket === 'local') {
                    $data->socketurl = $CFG->wwwroot;
                    $data->port = get_config('kuet', 'localport') !== false ? get_config('kuet', 'localport') : '8080';
                }
                if ($typesocket === 'external') {
                    $data->socketurl = get_config('kuet', 'externalurl');
                    $data->port = get_config('kuet', 'externalport') !== false ? get_config('kuet', 'externalport') : '8080';
                }
                if ($session->is_group_mode()) {
                    $data->isgroupmode = true;
                    $group = groupmode::get_user_group($data->userid, $session);
                    $data->groupimage = groupmode::get_group_image($group, $sid, 1);
                    $data->groupname = $group->name;
                    $data->groupid = $group->id;
                    if ($session->get('anonymousanswer') === 1) {
                        unset($data->groupimage);
                        $data->groupname = '**********';
                    }
                } else {
                    $userpicture = new user_picture($USER);
                    $userpicture->size = 1;
                    $data->userimage = $userpicture->get_url($PAGE)->out(false);
                    if ($session->get('anonymousanswer') === 1) {
                        unset($data->userimage);
                        $data->username = '**********';
                    }
                }
                break;
            default:
                throw new moodle_exception('incorrect_sessionmode', 'mod_kuet', '',
                    [], get_string('incorrect_sessionmode', 'mod_kuet'));
        }
        return $data;
    }
}
