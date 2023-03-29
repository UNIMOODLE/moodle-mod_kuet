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

namespace mod_jqshow;
use cm_info;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_sessions;
use stdClass;

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jqshow {
    /** @var cm_info cm */
    protected $cm;
    /** @var mixed course */
    protected $course;
    /** @var sessions */
    protected $sessions;
    /** @var stdClass jqshow */
    protected $jqshow;

    public function __construct(int $cmid) {
        list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'jqshow');
        $this->cm = $cm;
        $this->course = $course;
    }

    protected function set_jqshow() {
        global $DB;
        $this->jqshow = $DB->get_record('jqshow', array('id' => $this->cm->instance), '*', MUST_EXIST);
    }
    /**
     *
     */
    protected function set_sessions() {
//        $this->activesessions = jqshow_sessions::get_records(['jqshowid' => $this->cm->instance, 'status' => 1]);
//        $this->inactivesessions = jqshow_sessions::get_records(['jqshowid' => $this->cm->instance, 'status' => 0]);
        if (is_null($this->jqshow)) {
            $this->set_jqshow();
        }

        $this->sessions = new sessions($this->jqshow, $this->cm->id);
    }

    /**
     * @return jqshow_sessions[] array
     */
    public function get_sessions() : array {
        if (is_null($this->sessions)) {
            $this->set_sessions();
        }
        return $this->sessions->get_list();
    }
}
