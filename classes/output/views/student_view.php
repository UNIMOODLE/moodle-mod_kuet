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
use dml_exception;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use moodle_url;
use renderable;
use stdClass;
use templatable;
use renderer_base;

class student_view implements renderable, templatable {

    public int $jqshowid;
    public int $cmid;
    public function __construct(int $jqshowid, int $cmid) {
        $this->jqshowid = $jqshowid;
        $this->cmid = $cmid;
    }

    /**
     * @param renderer_base $output
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $OUTPUT;
        $data = new stdClass();
        $data->cmid = $this->cmid;
        $data->jqshowid = $this->jqshowid;
        $data->notsessionimage = $OUTPUT->image_url('f/not_session', 'mod_kuet')->out(false);
        $nextsession = kuet_sessions::get_next_session($this->jqshowid);
        if ($nextsession !== 0) {
            $data->hasnextsession = true;
            $data->nextsessiontime = userdate($nextsession, get_string('strftimedatetimeshort', 'core_langconfig'));
        }
        $data->urlreports = (new moodle_url('/mod/kuet/reports.php', ['cmid' => $this->cmid]))->out(false);
        return $data;
    }
}
