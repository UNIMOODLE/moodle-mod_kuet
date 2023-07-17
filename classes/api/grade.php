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

namespace mod_jqshow\api;
use coding_exception;
use core_reportbuilder\local\aggregation\count;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow;
use mod_jqshow\persistents\jqshow_grades;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_sessions_grades;

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade {
    public const MOD_OPTION_NO_GRADE = 0;
    public const MOD_OPTION_GRADE_HIGHEST = 1;
    public const MOD_OPTION_GRADE_AVERAGE = 2;
    public const MOD_OPTION_GRADE_FIRST_SESSION = 3;
    public const MOD_OPTION_GRADE_LAST_SESSION = 4;

    /**
     * Get the answer mark without considering session mode.
     * @param jqshow_questions_responses $response
     * @return float|int|null
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function get_simple_mark(jqshow_questions_responses $response) {
        global $DB;
        $mark = null;

        // Check ignore grading setting.
        $jquestion = jqshow_questions::get_record(['id' => $response->get('jqid')]);
        if ($jquestion->get('ignorecorrectanswer')) {
            return $mark;
        }

        // Get answer mark.
        $useranswer = $response->get('response');
        if (!empty($useranswer)) {
            $useranswer = json_decode($useranswer);
            $defaultmark = $DB->get_field('question', 'defaultmark', ['id' => $useranswer->{'questionid'}]);
            $answerids = $useranswer->{'answerids'};
            $answerids = explode(',', $answerids);
            foreach ($answerids as $answerid) {
                $fraction = $DB->get_field('question_answers', 'fraction', ['id' => $answerid]);
                $mark += $defaultmark * $fraction;
            }
        }
        return $mark;
    }

    /**
     * Get answer mark considering the session mode.
     * @param int $userid
     * @param int $sessionid
     * @param int $jqshowid
     * @param jqshow_questions_responses $response
     * @return float|int|null
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function get_response_mark(int $userid, int $sessionid, int $jqshowid, jqshow_questions_responses $response) {

        // Get answer mark without considering session mode.
        $simplemark = self::get_simple_mark($response);
        // Ignore grading mark setting.
        if (is_null($simplemark)) {
            return $simplemark;
        }

        $mark = $simplemark;
        $session = jqshow_sessions::get_record(['id' => $sessionid, 'jqshowid' => $jqshowid]);
        switch ($session->sessionmode) {
            case sessions::INACTIVE_MANUAL:
            case sessions::INACTIVE_PROGRAMMED:
                $mark = $simplemark;
                break;
            case sessions::PODIUM_MANUAL:
            case sessions::PODIUM_PROGRAMMED:
                $mark = self::get_podium_mark($session, $simplemark, $response, $userid);
                break;
            case sessions::RACE_MANUAL:
            case sessions::RACE_PROGRAMMED:
                // TODO: missing logic.
                $mark = $simplemark;
                break;
        }
        return $mark;
    }

    /**
     * @param jqshow_sessions $session
     * @param int $simplemark
     * @param jqshow_questions_responses $response
     * @param int $userid
     * @return float|int
     * @throws coding_exception
     */
    private static function get_podium_mark(jqshow_sessions $session, int $simplemark,
                                            jqshow_questions_responses $response, int $userid) {

        $cm = get_coursemodule_from_instance('jqshow', $session->get('jqshowid'));
        $students = \mod_jqshow\jqshow::get_students($cm->id);
        $studentmarks = [];
        $maxmark = 0;
        foreach ($students as $student) {
            $mark = self::get_simple_mark($response);
            if (is_null($mark)) {
                continue;
            }
            $maxmark = $mark > $maxmark ? $mark : $maxmark;
            $studentmarks[$student->{'userid'}] = $mark;
        }
        // Ordenar por puntos.
        $studentmarks = arsort($studentmarks);
        switch ($session->get('sgrademethod')) {
            case sessions::GM_R_POINTS:
                $mark = self::get_user_mark_relative_to_points_on_ranking($simplemark, $mark);
                break;
            case sessions::GM_R_POSITION:
                $mark = self::get_user_mark_relative_to_position_on_ranking($studentmarks, $userid);
                break;
            case sessions::GM_R_COMBINED;
                $mark1 = self::get_user_mark_relative_to_points_on_ranking($simplemark, $mark);
                $mark2 = self::get_user_mark_relative_to_position_on_ranking($studentmarks, $userid);
                $mark = ($mark1 + $mark2) / 2;
                break;
            default:
                $mark = $simplemark;
                break;
        }
        return $mark;
    }

    /**
     * @param int $simplemark
     * @param int $maxmark
     * @return float|int
     */
    private static function get_user_mark_relative_to_points_on_ranking(int $simplemark, int $maxmark) {
        if ($maxmark == 0) {
            return 0;
        }
        return $simplemark / $maxmark;
    }
    /**
     * @param array $studentmarks
     * @param int $userid
     * @return float|int
     */
    private static function get_user_mark_relative_to_position_on_ranking(array $studentmarks, int $userid) {
        $position = 0;
        $numstudents = count($studentmarks);
        foreach ($studentmarks as $key => $grade) {
            $position++;
            if ($userid == $key) {
                break;
            }
        }
        // Calificacion relativa a la posiicion en ranking: [participantes – posición + 1]/[participantes] * 100%.
        return ($numstudents - $position + 1) / $numstudents;
    }
    /**
     * @param int $userid
     * @param int $sessionid
     * @param int $jqshowid
     * @return int|null
     * @throws coding_exception
     */
    public static function get_session_grade(int $userid, int $sessionid, int $jqshowid) {
        $responses = jqshow_questions_responses::get_session_responses_for_user($userid, $sessionid, $jqshowid);
        // TODO: Duda! si no hay respuestas guardadas ... ¿que se hace? es error?
        if (count($responses) == 0) {
            return null;
        }

        $mark = 0;
        foreach ($responses as $response) {
            $usermark = self::get_response_mark($userid, $sessionid, $jqshowid, $response);
            if (is_null($usermark)) {
                continue;
            }
            $mark += $usermark;
        }
        return $mark;
    }
    /**
     * @param $userid
     * @param $jqshowid
     * @return int
     * @throws coding_exception|\dml_exception
     */
    public static function recalculate_mod_mark_by_userid($userid, $jqshowid) {
        $params = ['userid' => $userid, 'jqshow' => $jqshowid];
        $allgrades = jqshow_sessions_grades::get_records($params);

        $jqshow = jqshow::get_record(['id' => $jqshowid]);
        $grademethod = $jqshow->get('grademethod');
        $finalgrade = self::get_final_mod_grade($allgrades, $grademethod);
        if (is_null($finalgrade)) {
            return;
        }
        $params['grade'] = $finalgrade;

        // Save final grade for jqshow.
        $jgrade = jqshow_grades::get_record($params);
        if (!$jgrade) {
            $jg = new jqshow_grades(0, (object)$params);
            $jg->save();
        } else {
            $jgrade->set('grade', $finalgrade);
            $jgrade->update();
        }

        // Save final grade for grade report.
        $params['rawgrade'] = $finalgrade;
        $params['rawgrademax'] = get_config('core', 'gradepointmax');
        $params['rawgrademin'] = 0;
        mod_jqshow_grade_item_update($jqshow->to_record(), $params);
    }

    /**
     * For all the course students.
     * @param $jqshowid
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function recalculate_mod_mark($cmid, $jqshowid) {
        $students = \mod_jqshow\jqshow::get_students($cmid);
        if (empty($students)) {
            return;
        }
        $sessions = jqshow_sessions::get_records(['jqshowid' => $jqshowid]);
        if (empty($sessions)) {
            return;
        }
        $finished = false;
        foreach ($sessions as $session) {
            if ($session->get('status') == sessions::SESSION_FINISHED) {
                $finished = true;
            }
        }
        if (!$finished) {
            return;
        }
        foreach ($students as $student) {
            self::recalculate_mod_mark_by_userid($student->{'id'}, $jqshowid);
        }
    }
    /**
     * @param array $allgrades
     * @param string $grademethod
     * @return float|int
     * @throws coding_exception
     */
    private static function get_final_mod_grade(array $allgrades, string $grademethod) {
        if (count($allgrades) == 0) {
            return null;
        }
        // Only one session.
        if (count($allgrades) == 1) {
            $grade = reset($allgrades);
            return $grade->get('grade');
        }

        // More sessions.
        switch ($grademethod) {
            case self::MOD_OPTION_GRADE_HIGHEST:
                return self::get_highest_grade($allgrades);
            case self::MOD_OPTION_GRADE_AVERAGE:
                return self::get_average_grade($allgrades);
            case self::MOD_OPTION_GRADE_FIRST_SESSION:
                return self::get_first_grade();
            case self::MOD_OPTION_GRADE_LAST_SESSION:
                return self::get_last_grade();
        }
    }

    /**
     * @param jqshow_sessions_grades[] $allgrades
     * @return int
     * @throws coding_exception
     */
    private static function get_highest_grade(array $allgrades) {
        $finalmark = 0;
        foreach ($allgrades as $grade) {
            if ($grade->get('grade') > $finalmark) {
                $finalmark = $grade->get('grade');
            }
        }
        return $finalmark;
    }

    /**
     * @param jqshow_sessions_grades[] $allgrades
     * @return int
     * @throws coding_exception
     */
    private static function get_average_grade(array $allgrades) {
        $finalmark = 0;
        $total = count($allgrades);
        foreach ($allgrades as $grade) {
            $finalmark += $grade->get('grade');
        }
        return $finalmark / $total;
    }

    /**
     * @param jqshow_sessions_grades[] $allgrades
     * @return int
     * @throws coding_exception
     */
    private static function get_first_grade(array $allgrades) {
        $first = reset($allgrades);
        return $first->get('grade');
    }

    /**
     * @param jqshow_sessions_grades[] $allgrades
     * @return int
     * @throws coding_exception
     */
    private static function get_last_grade(array $allgrades) {
        $last = end($allgrades);
        return $last->get('grade');
    }
}
