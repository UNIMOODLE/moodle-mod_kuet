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
use core\invalid_persistent_exception;
use dml_exception;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow;
use mod_jqshow\persistents\jqshow_grades;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_sessions_grades;
use moodle_exception;

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
     * @param float $mark
     * @return float
     * @throws dml_exception
     */
    public static function get_rounded_mark(float $mark) : float {
        $num = (int) get_config('core', 'grade_export_decimalpoints');
        return round($mark, $num);
    }

    /**
     * @param int $questionid
     * @param string $answerids
     * @return int
     * @throws dml_exception
     */
    public static function get_status_response_for_multiple_answers(int $questionid, string $answerids) : int {
        global $DB;

        if (empty($answerids)) {
            return questions::NORESPONSE;
        }
        $defaultmark = $DB->get_field('question', 'defaultmark', ['id' => $questionid]);
        $arrayanswerids = explode(',', $answerids);
        $mark = 0;
        foreach ($arrayanswerids as $arrayanswerid) {
            $fraction = $DB->get_field('question_answers', 'fraction', ['id' => $arrayanswerid]);
            $mark += $fraction * $defaultmark;
        }
        $defaultmarkrounded = round($defaultmark, 2);
        $markrounded = round($mark, 2);
        if ((int)$mark === 0) {
            $status = questions::FAILURE;
        } else if ($markrounded === $defaultmarkrounded) {
            $status = questions::SUCCESS;
        } else {
            $status = questions::PARTIALLY;
        }

        return $status;
    }

    /**
     * Get the answer mark without considering session mode.
     * @param jqshow_questions_responses $response
     * @return float
     * @throws moodle_exception
     * @throws coding_exception
     */
    public static function get_simple_mark(jqshow_questions_responses $response) : float {
        $mark = 0;
        // Check ignore grading setting.
        $jquestion = jqshow_questions::get_record(['id' => $response->get('jqid')]);
        if ($jquestion !== false && $jquestion->get('ignorecorrectanswer')) {
            return $mark;
        }

        // Get answer mark.
        $useranswer = $response->get('response');
        if (!empty($useranswer)) {
            $useranswer = json_decode(base64_decode($useranswer), false);
            if (isset($useranswer->type)) {
                /** @var questions $type */
                $type = questions::get_question_class_by_string_type($useranswer->type);
                $mark = $type::get_simple_mark($useranswer, $response);
            }
        }
        return $mark;
    }

    /**
     * @param int $userid
     * @param int $sessionid
     * @param int $jqshowid
     * @return float
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_session_grade(int $userid, int $sessionid, int $jqshowid) : float {
        $responses = jqshow_questions_responses::get_session_responses_for_user($userid, $sessionid, $jqshowid);
        if (count($responses) === 0) {
            return 0;
        }

        $session = new jqshow_sessions($sessionid);
        switch ($session->get('sessionmode')) {
            case sessions::PODIUM_MANUAL:
                $mark = self::get_session_podium_manual_grade($responses);
                break;
            case sessions::PODIUM_PROGRAMMED:
                $mark = self::get_session_podium_programmed_grade($responses, $session);
                break;
            case sessions::INACTIVE_PROGRAMMED:
            case sessions::INACTIVE_MANUAL:
            case sessions::RACE_PROGRAMMED:
            case sessions::RACE_MANUAL:
            default:
                $mark = self::get_session_default_grade($responses);
        }
        return $mark;
    }

    /**
     * @param jqshow_questions_responses[] $responses
     * @return float
     * @throws coding_exception
     * @throws moodle_exception
     */
    private static function get_session_podium_manual_grade(array $responses) : float {
        $mark = 0;
        foreach ($responses as $response) {
            $usermark = self::get_simple_mark($response);
            if ((int) $usermark === 0) {
                continue;
            }
            $jqquestion = new jqshow_questions($response->get('jqid'));
            $qtime = $jqquestion->get('timelimit'); // All the time that participants have had to answer the question.
            $useranswer = base64_decode($response->get('response'));
            $percent = 1;
            if ($qtime && !empty($useranswer)) {
                $useranswer = json_decode($useranswer);
                $timeleft = $useranswer->timeleft; // Time answering question.
                $percent = 0; // UNIMOOD-150.
                if ($qtime > $timeleft) {
                    $percent = ($qtime - $timeleft) * 100 / $qtime;
                }
            }
            $mark += $usermark * $percent;
        }
        return $mark;
    }

    /**
     * @param jqshow_questions_responses[] $responses
     * @param jqshow_sessions $session
     * @return float
     * @throws coding_exception
     * @throws moodle_exception
     */
    private static function get_session_podium_programmed_grade(array $responses, jqshow_sessions $session) : float {
        $qtime = 0;
        if ((int)$session->get('timemode') === sessions::SESSION_TIME) {
            $total = $session->get('sessiontime');
            $numq = jqshow_questions::count_records(['sessionid' => $session->get('id'),
                'jqshowid' => $session->get('jqshowid')]);
            $qtime = $total / $numq;
        } else if ((int)$session->get('timemode') === sessions::QUESTION_TIME) {
            $qtime = $session->get('questiontime');
        }
        $mark = 0;
        foreach ($responses as $response) {
            $usermark = self::get_simple_mark($response);
            if ((int) $usermark === 0) {
                continue;
            }
            $jqquestion = new jqshow_questions($response->get('jqid'));
            $qtime = !$qtime ? $jqquestion->get('timelimit') : 40;
            $useranswer = base64_decode($response->get('response'));
            $percent = 1;
            if ($qtime && !empty($useranswer)) {
                $useranswer = json_decode($useranswer);
                $timeleft = $useranswer->timeleft; // Time answering question.
                $percent = ($qtime - $timeleft) * 100 / $qtime;
            }
            $mark += $usermark * $percent;
        }
        return $mark;
    }

    /**
     * @param jqshow_questions_responses[] $responses
     * @return float
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private static function get_session_default_grade(array $responses) : float {
        $mark = 0;
        foreach ($responses as $response) {
            $usermark = self::get_simple_mark($response);
            if ((int) $usermark === 0) {
                continue;
            }
            $mark += $usermark;
        }
        return $mark;
    }

    /**
     * @param int $userid
     * @param int $jqshowid
     * @return void
     * @throws invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function recalculate_mod_mark_by_userid(int $userid, int $jqshowid) :void {
        $params = ['userid' => $userid, 'jqshow' => $jqshowid];
        $allgrades = jqshow_sessions_grades::get_records($params);

        $jqshow = jqshow::get_record(['id' => $jqshowid]);
        $grademethod = $jqshow->get('grademethod');

        if (count($allgrades) === 0) {
            return;
        }
        $finalgrade = self::get_final_mod_grade($allgrades, $grademethod);
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
     * @param int $cmid
     * @param int $jqshowid
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function recalculate_mod_mark(int $cmid, int $jqshowid) : void {
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
     * @return float
     * @throws coding_exception
     */
    private static function get_final_mod_grade(array $allgrades, string $grademethod) : float {

        // Only one session.
        if (count($allgrades) === 1) {
            return reset($allgrades)->get('grade');
        }

        // More sessions.
        switch ($grademethod) {
            case self::MOD_OPTION_GRADE_HIGHEST:
            default:
                return self::get_highest_grade($allgrades);
            case self::MOD_OPTION_GRADE_AVERAGE:
                return self::get_average_grade($allgrades);
            case self::MOD_OPTION_GRADE_FIRST_SESSION:
                return self::get_first_grade($allgrades);
            case self::MOD_OPTION_GRADE_LAST_SESSION:
                return self::get_last_grade($allgrades);
        }
    }

    /**
     * @param jqshow_sessions_grades[] $allgrades
     * @return float
     * @throws coding_exception
     */
    private static function get_highest_grade(array $allgrades) : float {
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
     * @return float
     * @throws coding_exception
     */
    private static function get_average_grade(array $allgrades) : float {
        $finalmark = 0;
        $total = count($allgrades);
        foreach ($allgrades as $grade) {
            $finalmark += $grade->get('grade');
        }
        return $finalmark / $total;
    }

    /**
     * @param jqshow_sessions_grades[] $allgrades
     * @return float
     * @throws coding_exception
     */
    private static function get_first_grade(array $allgrades) : float {
        return reset($allgrades)->get('grade');
    }

    /**
     * @param jqshow_sessions_grades[] $allgrades
     * @return float
     * @throws coding_exception
     */
    private static function get_last_grade(array $allgrades) : float {
        return end($allgrades)->get('grade');
    }

    /**
     * @param jqshow_questions_responses $response
     * @return string
     * @throws coding_exception
     */
    public static function get_result_mark_type(jqshow_questions_responses $response) : string {
        switch ($response->get('result')) {
            case questions::FAILURE:
                $result = 'incorrect';
                break;
            case questions::SUCCESS:
                $result = 'correct';
                break;
            case questions::PARTIALLY:
                $result = 'partially';
                break;
            case questions::INVALID:
                $result = 'invalid';
                break;
            case questions::NOTEVALUABLE:
                $result = 'noevaluable';
                break;
            case questions::NORESPONSE:
            default:
                $result = 'noresponse';
                break;
        }
        return $result;
    }

    /**
     * @param jqshow_questions_responses[] $responses
     * @return array
     * @throws coding_exception
     */
    public static function count_result_mark_types(array $responses) : array {
        $correct = 0;
        $incorrect = 0;
        $partially = 0;
        $noresponse = 0;
        $invalid = 0;
        foreach ($responses as $response) {
            $result = $response->get('result');
            switch ($result) {
                case questions::SUCCESS: $correct++; break;
                case questions::FAILURE: $incorrect++; break;
                case questions::INVALID: $invalid++; break;
                case questions::PARTIALLY: $partially++; break;
                case questions::NORESPONSE: $noresponse++; break;
            }
        }
        return [$correct, $incorrect, $invalid, $partially, $noresponse];
    }
}
