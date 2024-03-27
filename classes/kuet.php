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
 * Kuet services and API definition
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet;
use cm_info;
use coding_exception;
use context_course;
use context_module;
use dml_exception;
use mod_kuet\api\groupmode;
use mod_kuet\helpers\sessions as sessions_helper;
use mod_kuet\models\sessions;
use mod_kuet\models\sessions as sessionsmodel;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use stdClass;

/**
 * kuet external services and API class
 */
class kuet {
    /** @var cm_info cm */
    public $cm;
    /** @var mixed course */
    public $course;
    /** @var sessions */
    protected $sessions;
    /** @var stdClass kuet */
    protected $kuet;

    /**
     * Constructor
     *
     * @param int $cmid
     * @throws moodle_exception
     */
    public function __construct(int $cmid) {
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'kuet');
        $this->cm = $cm;
        $this->course = $course;
    }

    /**
     * Set kuet instance
     *
     * @return void
     * @throws dml_exception
     */
    protected function set_kuet() : void {
        global $DB;
        $this->kuet = $DB->get_record('kuet', ['id' => $this->cm->instance], '*', MUST_EXIST);
    }

    /**
     * Get kuet instance
     *
     * @return stdClass
     * @throws dml_exception
     */
    public function get_kuet() : stdClass {
        if (is_null($this->kuet)) {
            $this->set_kuet();
        }
        return $this->kuet;
    }

    /**
     * Set kuet session
     *
     * @return void
     * @throws dml_exception
     */
    protected function set_sessions() : void {
        if (is_null($this->kuet)) {
            $this->set_kuet();
        }
        $this->sessions = new sessions($this->kuet, $this->cm->id);
    }

    /**
     * Get kuet sessions
     *
     * @return kuet_sessions[] array
     * @throws dml_exception
     */
    public function get_sessions(): array {
        if (is_null($this->sessions)) {
            $this->set_sessions();
        }
        return $this->sessions->get_list();
    }

    /**
     * Get kuet completed sessions
     *
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
     * Get students from a kuet instance
     *
     * @param int $cmid
     * @param int $groupingid
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_students(int $cmid, int $groupingid = 0) : array {
        $context = context_module::instance($cmid);
        $participants = self::get_participants($cmid, $context, $groupingid);
        $students = [];
        foreach ($participants as $participant) {
            if (is_null($participant->{'id'})) {
                continue;
            }
            if (has_capability('mod/kuet:startsession', $context, $participant->{'id'})) {
                continue;
            }
            $students[] = $participant;
        }
        return $students;
    }

    /**
     * Get participants from a kuet instance
     *
     * @param int $cmid
     * @param context_module $context
     * @param int $groupingid
     * @return array
     * @throws moodle_exception
     */
    private static function get_participants(int $cmid, context_module $context, int $groupingid = 0) : array {
        $data = get_course_and_cm_from_cmid($cmid, 'kuet');
        /** @var cm_info $cm */
        $cm = $data[1];
        if ($cm->groupmode == '0' && $groupingid === 0) {
            return self::get_participants_individual_mode($context);
        } else {
            return self::get_participants_group_mode($groupingid);
        }
    }

    /**
     * Get participants from a kuet instance on group mode
     *
     * @param int $groupingid
     * @return array
     */
    private static function get_participants_group_mode(int $groupingid) : array {
        return groupmode::get_grouping_users($groupingid);
    }

    /**
     * Get participants from a kuet instance on individual mode
     *
     * @param context_module $context
     * @return array
     */
    private static function get_participants_individual_mode(context_module $context) : array {
        return get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);
    }

    /**
     * Get enrolled users with student tole assigned in course
     *
     * @param int $courseid
     * @param int $cmid
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_enrolled_students_in_course(int $courseid = 0, int $cmid = 0) : array {
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
