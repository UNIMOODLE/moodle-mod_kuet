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
use context_course;
use context_module;
use dml_exception;
use mod_jqshow\api\groupmode;
use mod_jqshow\helpers\sessions as sessions_helper;
use mod_jqshow\models\sessions;
use mod_jqshow\models\sessions as sessionsmodel;
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
            if ($session->get('status') === sessionsmodel::SESSION_FINISHED) {
                $completed[] = sessions_helper::get_data_session($session, $this->cm->id, false, false);
            }
        }
        usort($completed, static fn($a, $b) => $b->enddate <=> $a->enddate);
        return $completed;
    }

    /**
     * @param $cmid
     * @param int $groupingid
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_students($cmid, int $groupingid = 0) : array {
        $context = context_module::instance($cmid);
        $participants = self::get_participants($cmid, $context, $groupingid);
        $students = [];
        foreach ($participants as $participant) {
            if (is_null($participant->{'id'})) {
                continue;
            }
            if (has_capability('mod/jqshow:startsession', $context, $participant->{'id'})) {
                continue;
            }
            $students[] = $participant;
        }
        return $students;
    }

    /**
     * @param $cmid
     * @param $context
     * @return array
     * @throws moodle_exception
     */
    private static function get_participants($cmid, $context, int $groupingid = 0) {
        $data = get_course_and_cm_from_cmid($cmid, 'jqshow');
        /** @var cm_info $cm */
        $cm = $data[1];
        if ($cm->groupmode == '0' && $groupingid === 0) {
            return self::get_participants_individual_mode($context);
        } else {
            return self::get_participants_group_mode($groupingid);
        }
    }

    /**
     * @param int groupingid
     * @return array
     */
    private static function get_participants_group_mode(int $groupingid) : array {
        $members = groupmode::get_grouping_users($groupingid);
        return $members;
    }

    /**
     * @param context_module $context
     * @return array
     */
    private static function get_participants_individual_mode(context_module $context) {
        return get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);
    }

    /**
     * @param int $courseid
     * @param int $cmid
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_enrolled_students_in_course(int $courseid = 0, int $cmid = 0) {
        if ($cmid) {
            $module = get_module_from_cmid($cmid);
            $courseid = $module[0]->course;
        }
        $context = context_course::instance($courseid);
        $courseparticipants = enrol_get_course_users($courseid, true);
        $students = [];
        foreach ($courseparticipants as $courseparticipant) {
            if (!has_capability('moodle/course:managegroups', $context, $courseparticipant->id)) {
                $students[$courseparticipant->id] = $courseparticipant;
            }
        }
        return $students;
    }
}
