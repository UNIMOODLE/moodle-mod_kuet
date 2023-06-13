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

use mod_jqshow\jqshow;
use moodle_exception;
use renderable;
use renderer_base;
use stdClass;
use templatable;

class teacher_reports implements renderable, templatable {

    public int $jqshowid;
    public int $cmid;
    public function __construct(int $cmid, int $jqshowid) {
        $this->jqshowid = $jqshowid;
        $this->cmid = $cmid;
    }

    /**
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $jqshow = new jqshow($this->cmid);
        $data = new stdClass();
        $data->jqshowid = $this->jqshowid;
        $data->cmid = $this->cmid;
        $data->endedsessions = $jqshow->get_completed_sessions();

        return $data;
    }
}
