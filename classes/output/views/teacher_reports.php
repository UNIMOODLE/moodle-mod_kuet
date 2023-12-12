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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\output\views;

use coding_exception;
use context_module;
use dml_exception;
use JsonException;
use mod_kuet\helpers\reports;
use mod_kuet\kuet;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use renderable;
use renderer_base;
use stdClass;
use templatable;

class teacher_reports implements renderable, templatable {

    public int $kuetid;
    public int $cmid;
    public int $sid;
    public int $userid = 0;
    public int $groupid = 0;
    public int $kid;

    /**
     * @param int $cmid
     * @param int $kuetid
     * @param int $sid
     * @param int $partipantid could be userid or groupid
     * @param int $kid
     * @throws coding_exception
     */
    public function __construct(int $cmid, int $kuetid, int $sid, int $partipantid, int $kid) {
        $this->kuetid = $kuetid;
        $this->cmid = $cmid;
        $this->sid = $sid;
        $session = new kuet_sessions($sid);
        if ($session->is_group_mode()) {
            $this->groupid = $partipantid;
        } else {
            $this->userid = $partipantid;
        }
        $this->kid = $kid;
    }

    /**
     * @param renderer_base $output
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $kuet = new kuet($this->cmid);
        $data = new stdClass();
        $data->kuetid = $this->kuetid;
        $data->cmid = $this->cmid;
        $cmcontext = context_module::instance($this->cmid);
        if ($this->sid === 0) {
            $data->allreports = true;
            $data->endedsessions = $kuet->get_completed_sessions();
        } else if ($this->userid === 0 & $this->groupid === 0 && $this->kid === 0) {
            $data = reports::get_session_report($this->kuetid, $data->cmid, $this->sid);
        } else if ($this->userid === 0 && $this->kid !== 0) {
            $data = reports::get_question_report($this->cmid, $this->sid, $this->kid);
        } else if ($this->groupid === 0) {
            $data = reports::get_user_report($this->cmid, $this->sid, $this->userid, $cmcontext);
        } else {
            $data = reports::get_group_report($this->cmid, $this->sid, $this->groupid, $cmcontext);
        }
        return $data;
    }
}
