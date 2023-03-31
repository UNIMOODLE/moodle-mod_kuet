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


namespace mod_jqshow\models;

use coding_exception;
use core\invalid_persistent_exception;
use mod_jqshow\forms\sessionform;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use moodleform;
use PhpParser\Node\Expr\Cast\Object_;
use stdClass;

class sessions {

    /** @var stdClass $jqshow */
    protected stdClass $jqshow;

    /** @var int cmid */
    protected int $cmid;

    /** @var jqshow_sessions[] list */
    protected array $list;

    /**
     * sessions constructor.
     * @param stdClass $jqshow
     * @param $cmid
     */
    public function __construct(stdClass $jqshow, $cmid) {
        $this->jqshow = $jqshow;
        $this->cmid = $cmid;
    }

    /**
     *
     */
    private function set_list() {
        $this->list = jqshow_sessions::get_records(['jqshowid' => $this->jqshow->id]);
    }

    /**
     * @return jqshow_sessions[]
     */
    public function get_list() {
        if (empty($this->list)) {
            $this->set_list();
        }
        return $this->list;
    }
    /**
     * @return Object
     * @throws moodle_exception
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public function export_form() : Object {


        $sid = optional_param('sid', 0, PARAM_INT);    // Session id.
        $anonymousanswerchoices = [
            '0' => 'Anonimizar las respuestas del estudiante',
            '1' => 'Anonimizar totalmente las respuestas del estudiante',
            '2' => 'No anonimizar las respuestas del estudiante',
        ];
        $advancemodechoices = [
            'manual' => 'Manual',
            'programmed' => 'Programado'
        ];
        $gamemodechoices = [
            'inactive' => 'Inactivo',
            'race' => 'Carrera',
            'podium' => 'Podio'
        ];
        $countdownchoices = [
            '0' => 'Opcion1',
            '1' => 'Opcion2',
            '3' => 'Opcion3'
        ];
        $groupingsselect = [];
        list($course, $cm) = get_course_and_cm_from_cmid($this->cmid);
        if ($cm->groupmode) {
            $groupings = groups_get_all_groupings($cm->course);
            if (!empty($groupings)) {
                foreach ($groupings as $grouping) {
                    $groupingsselect[$grouping->id] = $grouping->name;
                }
            }
        }
        $customdata = [
            'course' => $course,
            'cm' => $cm,
            'jqshowid' => $this->jqshow->id,
            'countdown' => $countdownchoices,
            'gamemode' => $gamemodechoices,
            'advancemode' => $advancemodechoices,
            'anonymousanswerchoices' => $anonymousanswerchoices,
            'groupingsselect' => $groupingsselect,
        ];

        $action = new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $this->cmid, 'sid' => $sid, 'page' => 1]);
        $mform = new sessionform($action->out(false), $customdata);

        if ($mform->is_cancelled()) {
            $url = new moodle_url('/mod/jqshow/view.php', ['id' => $this->cmid]);
            redirect($url);
        } else if ($fromform = $mform->get_data()) {
            self::save_session($fromform);
            $url = new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $this->cmid, 'page' => 2]);
            redirect($url);
        } else {
            // This branch is executed if the form is submitted but the data doesn't
            // validate and the form should be redisplayed or on the first display of the form.

            // Set anydefault data (if any).
            //    $mform->set_data($toform);
            //
            //    // Display the form.
            //    $this->content->text = $mform->render();
        }
        if ($sid) {
            $formdata = $this->get_form_data($sid);
            $mform->set_data($formdata);
        }
        $data = new stdClass();
        $data->form = $mform->render();
        $data->ispage3 = false;
        $data->ispage2 = false;
        $data->ispage1 = true;

        return $data;
    }

    private function get_form_data(int $sid) {
        /** @var jqshow_sessions $session */
        $session = $this->get_session(['id' => $sid]);
        return [
            'sessionid' => $session->get('id'),
            'name' => $session->get('name'),
            'anonymousanswer' => $session->get('anonymousanswer'),
            'allowguests' => $session->get('allowguests'),
            'advancemode' => $session->get('advancemode'),
            'gamemode' => $session->get('gamemode'),
            'countdown' => $session->get('countdown'),
            'randomquestions' => $session->get('randomquestions'),
            'randomanswers' => $session->get('randomanswers'),
            'showfeedback' => $session->get('showfeedback'),
            'showfinalgrade' => $session->get('showfinalgrade'),
            'startdate' => $session->get('startdate'),
            'enddate' => $session->get('enddate'),
            'automaticstart' => $session->get('automaticstart'),
            'activetimelimit' => $session->get('activetimelimit'),
            'timelimit' => $session->get('timelimit'),
//            'addtimequestionenable' => $session->get('addtimequestionenable')
        ];
    }
    /**
     * @return Object
     */
    public function export_session_questions() : Object {
        $data = new stdClass();
        $data->ispage1 = false;
        $data->ispage2 = true;
        $data->ispage3 = false;
        $data->name = 'export_session_questions';
        return $data;
    }

    /**
     * @return Object
     */
    public function export_session_resume() : Object {
        $data = new stdClass();
        $data->name = 'export_session_resume';
        $data->ispage3 = true;
        $data->ispage2 = false;
        $data->ispage1 = false;
        return $data;
    }

    /**
     * @return Object
     * @throws coding_exception
     */
    public function export() : Object {
        $page = optional_param('page', 1, PARAM_INT);
        switch ($page) {
            case 1:
                return $this->export_form();
            case 2:
                return $this->export_session_questions();
            case 3:
                return $this->export_session_resume();
        }
    }
    /**
     * @param $data
     * @return bool
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    protected static function save_session(object $data) : bool {
        if (!empty($data->groupings)) {
            $values = array_values($data->groupings);
            $groupings = implode(',', $values);
            $data->groupings = $groupings;
        }
        $id = isset($data->sessionid) ? $data->sessionid : 0;
        $update = false;
        if (!empty($id)) {
            $update = true;
            $data->{'id'} = $id;
        }
        $session = new jqshow_sessions($id, $data);
        if ($update) {
            $session->update();
        } else {
            $session->create();
        }
        return true;
    }

    /**
     * @param $params
     * @return jqshow_sessions
     */
    protected function get_session($params) : jqshow_sessions {
        return jqshow_sessions::get_record($params);
    }
}
