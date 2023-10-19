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

use coding_exception;
use core\invalid_persistent_exception;
use dml_exception;
use mod_jqshow\api\grade;
use mod_jqshow\event\session_ended;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_sessions_grades;

class observer {
    /**
     * Jqshow session ended
     * Before calculate and save session grade, check:
     * - mod_jqshow->grademethod != 0
     * - session is gradeable too.
     *
     * @param session_ended $event The event.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     */
    public static function session_ended(session_ended $event) {

        global $PAGE;

        $data = $event->get_data();
        $context = \context_module::instance((int) $data['contextinstanceid']);
        $PAGE->set_context($context);
        $jqshow = \mod_jqshow\persistents\jqshow::get_jqshow_from_cmid((int) $data['contextinstanceid']);
        if (!$jqshow || (int) $jqshow->get('grademethod') == grade::MOD_OPTION_NO_GRADE) {
            return;
        };
        $session = jqshow_sessions::get_record(['id' => $data['objectid']]);
        if (!$session || $session->get('sgrade') == sessions::GM_DISABLED) {
            return;
        }
        $grouping = is_null($session->get('groupings')) ? 0 : (int) $session->get('groupings');
        $participants = self::get_course_students($data, $grouping);
        foreach ($participants as $participant) {
            if (is_null($participant->{'id'})) {
                continue;
            }
            // Get session grade.
            $sessiongrade = grade::get_session_grade($participant->{'id'}, $data['objectid'], $jqshow->get('id'));
            $params = [
                'jqshow' => $jqshow->get('id'),
                'session' => $data['objectid'],
                'userid' => $participant->{'id'}
            ];
             // Save grade on db.
            $jgrade = jqshow_sessions_grades::get_record($params);
            if (!$jgrade) {
                $params['grade'] = $sessiongrade;
                $jsg = new jqshow_sessions_grades(0, (object) $params);
                $jsg->create();
            } else {
                $jgrade->set('grade', $sessiongrade);
                $jgrade->update();
            }
            grade::recalculate_mod_mark_by_userid($participant->{'id'}, $jqshow->get('id'));
        }
    }
    /**
     * @param array $data
     * @param int $data
     * @return array
     */
    private static function get_course_students(array $data, int $grouping = 0) {
        // Check if userid is teacher or student.
        $students = [(object) ['id' => $data['userid']]];
        $context = \context_module::instance($data['contextinstanceid']);
        $isteacher = has_capability('mod/jqshow:startsession', $context, $data['userid']);
        if ($isteacher) {
            $students = jqshow::get_students($data['contextinstanceid'], $grouping);
        }

        return $students;
    }
}
