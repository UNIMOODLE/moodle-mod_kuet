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
use dml_exception;
use mod_jqshow\persistents\jqshow_sessions;
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
        $data->notsessionimage = $OUTPUT->image_url('f/not_session', 'mod_jqshow')->out(false);
        $nextsession = jqshow_sessions::get_next_session($this->jqshowid);
        if ($nextsession !== 0) {
            $data->hasnextsession = true;
            $data->nextsessiontime = userdate($nextsession, get_string('strftimedatetimeshort', 'core_langconfig'));
        }
        $data->urlreports = (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $this->cmid]))->out(false);
        return $data;
    }
}
