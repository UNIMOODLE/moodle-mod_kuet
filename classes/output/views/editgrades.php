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
 * Manual grade editing view (KUETEDUCAM-73)
 *
 * @package    mod_kuet
 * @author     3IPUNT <contacte@tresipunt.com>
 * @copyright  3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\output\views;

use coding_exception;
use dml_exception;
use mod_kuet\helpers\editgrades as editgradeshelper;
use mod_kuet\persistents\kuet_sessions;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Manual grade editing renderable class.
 */
class editgrades implements renderable, templatable {
    /**
     * @var int course module id
     */
    public int $cmid;
    /**
     * @var int kuet module id
     */
    public int $kuetid;
    /**
     * @var int session id
     */
    public int $sid;
    /**
     * @var int user id being edited (0 = student list page)
     */
    public int $userid;

    /**
     * Constructor.
     *
     * @param int $cmid
     * @param int $kuetid
     * @param int $sid
     * @param int $userid
     */
    public function __construct(int $cmid, int $kuetid, int $sid, int $userid = 0) {
        $this->cmid = $cmid;
        $this->kuetid = $kuetid;
        $this->sid = $sid;
        $this->userid = $userid;
    }

    /**
     * Template to render this view with.
     *
     * @return string
     */
    public function get_template(): string {
        return 'mod_kuet/editgrades/questions';
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB;
        $session = new kuet_sessions($this->sid);
        $data = new stdClass();
        $data->cmid = $this->cmid;
        $data->kuetid = $this->kuetid;
        $data->sid = $this->sid;
        $data->sessionname = format_string($session->get('name'));
        $user = $DB->get_record('user', ['id' => $this->userid], '*', MUST_EXIST);
        $data->userid = $this->userid;
        $data->userfullname = fullname($user);
        $data->ispodium = editgradeshelper::session_applies_time_percentage($session);
        $groupinfo = editgradeshelper::get_group_info($session, $this->userid);
        if ($groupinfo !== null) {
            $data->isgroup = true;
            $data->groupinfomessage = get_string('editgrades_groupinfo', 'mod_kuet', $groupinfo->name);
            $data->groupmembers = $groupinfo->members;
        }
        $data->questions = editgradeshelper::get_questions_data(
            $this->kuetid,
            $this->cmid,
            $this->sid,
            $this->userid
        );
        $data->hasquestions = !empty($data->questions);
        $data->studentsurl = (new moodle_url(
            '/mod/kuet/editgrades.php',
            ['cmid' => $this->cmid, 'sid' => $this->sid]
        ))->out(false);
        return $data;
    }
}
