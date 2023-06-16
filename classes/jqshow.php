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

namespace mod_jqshow;
use cm_info;
use coding_exception;
use dml_exception;
use mod_jqshow\helpers\sessions as sessions_helper;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use stdClass;

class jqshow {
    /** @var cm_info cm */
    public $cm;
    /** @var mixed course */
    public $course;
    /** @var sessions */
    protected $sessions;
    /** @var stdClass jqshow */
    protected $jqshow;

    /**
     * @param int $cmid
     * @throws moodle_exception
     */
    public function __construct(int $cmid) {
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'jqshow');
        $this->cm = $cm;
        $this->course = $course;
    }

    /**
     * @return void
     * @throws dml_exception
     */
    protected function set_jqshow() {
        global $DB;
        $this->jqshow = $DB->get_record('jqshow', ['id' => $this->cm->instance], '*', MUST_EXIST);
    }

    /**
     * @return stdClass
     * @throws dml_exception
     */
    public function get_jqshow() {
        if (is_null($this->jqshow)) {
            $this->set_jqshow();
        }
        return $this->jqshow;
    }

    /**
     * @return void
     * @throws dml_exception
     */
    protected function set_sessions() {
        if (is_null($this->jqshow)) {
            $this->set_jqshow();
        }
        $this->sessions = new sessions($this->jqshow, $this->cm->id);
    }

    /**
     * @return jqshow_sessions[] array
     * @throws dml_exception
     */
    public function get_sessions(): array {
        if (is_null($this->sessions)) {
            $this->set_sessions();
        }
        return $this->sessions->get_list();
    }

    /**
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_completed_sessions(): array {
        if (is_null($this->sessions)) {
            $this->set_sessions();
        }
        $completed = [];
        foreach ($this->sessions->get_list() as $session) {
            if ($session->get('status') === 0) {
                $completed[] = sessions_helper::get_data_session($session, $this->cm->id, false, false);
            }
        }
        return $completed;
    }
}
