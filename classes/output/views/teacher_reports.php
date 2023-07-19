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
use context_module;
use dml_exception;
use JsonException;
use mod_jqshow\helpers\reports;
use mod_jqshow\jqshow;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use user_picture;

class teacher_reports implements renderable, templatable {

    public int $jqshowid;
    public int $cmid;
    public int $sid;
    public int $userid;
    public int $jqid;

    /**
     * @param int $cmid
     * @param int $jqshowid
     * @param int $sid
     * @param int $userid
     * @param int $jqid
     */
    public function __construct(int $cmid, int $jqshowid, int $sid, int $userid, int $jqid) {
        $this->jqshowid = $jqshowid;
        $this->cmid = $cmid;
        $this->sid = $sid;
        $this->userid = $userid;
        $this->jqid = $jqid;
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
        // TODO refactor.
        global $DB, $PAGE, $USER;
        $jqshow = new jqshow($this->cmid);
        $data = new stdClass();
        $data->jqshowid = $this->jqshowid;
        $data->cmid = $this->cmid;
        $cmcontext = context_module::instance($this->cmid);
        if ($this->sid === 0) { // All sessions.
            $data->allreports = true;
            $data->endedsessions = $jqshow->get_completed_sessions();
        } else if ($this->userid === 0 && $this->jqid === 0) { // One session.
            $data = reports::get_session_report($this->jqshowid, $data->cmid, $this->sid, $cmcontext);
        } else if ($this->userid === 0 && $this->jqid !== 0) { // Question report.
            $data = reports::get_question_report($this->cmid, $this->sid, $this->jqid);
        } else { // User report.
            $data = reports::get_user_report($this->cmid, $this->sid, $this->jqid);

        }
        return $data;
    }
}
