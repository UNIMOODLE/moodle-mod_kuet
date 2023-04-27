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
use dml_exception;
use mod_jqshow\persistents\jqshow_sessions;
use renderable;
use stdClass;
use templatable;
use renderer_base;
/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_session_view implements renderable, templatable {
    /**
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        // TODO refactor duplicate code for teacher and student.
        global $USER;
        $data = new stdClass();
        $data->cmid = required_param('cmid', PARAM_INT);
        $data->sid = required_param('sid', PARAM_INT);
        $data->isteacher = true;
        $data->userid = $USER->id;
        $data->userfullname = $USER->firstname . ' ' . $USER->lastname;

        $session = new jqshow_sessions($data->sid);
        if ($session->get('advancemode') === 'programmed') {
            $data->programmedmode = true;
        }

        if ($session->get('advancemode') === 'manual') {
            // SOCKETS!
            jqshow_sessions::mark_session_started($data->sid);
            $data->manualmode = true;
            $data->port = get_config('jqshow', 'port') !== false ? get_config('jqshow', 'port') : '8080';
        }
        return $data;
    }
}
