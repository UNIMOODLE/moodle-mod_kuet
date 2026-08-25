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
 * Kuet grade API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet\api;
use coding_exception;
use core\invalid_persistent_exception;
use dml_exception;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet;
use mod_kuet\persistents\kuet_grades;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use mod_kuet\persistents\kuet_sessions_grades;
use moodle_exception;

/**
 * Grade class
 */
class grade {
    /**
     * @var int no grade
     */
    public const MOD_OPTION_NO_GRADE = 0;

    /**
     * @var int highest grade
     */
    public const MOD_OPTION_GRADE_HIGHEST = 1;
    /**
     * @var int average grade
     */
    public const MOD_OPTION_GRADE_AVERAGE = 2;
    /**
     * @var int first session grade
     */
    public const MOD_OPTION_GRADE_FIRST_SESSION = 3;
    /**
     * @var int last session grade
     */
    public const MOD_OPTION_GRADE_LAST_SESSION = 4;

    /**
     * Get rounded mark
     *
     * @param float $mark
     * @return float
     * @throws dml_exception
     */
    public static function get_rounded_mark(float $mark): float {
        $num = (int) get_config('core', 'grade_export_decimalpoints');
        return round($mark, $num);
    }

    /**
     * Get status response for multiple answers
     *
     * @param int $questionid
     * @param string $answerids
     * @return int
     * @throws dml_exception
     */
    public static function get_status_response_for_multiple_answers(int $questionid, string $answerids): int {
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
     *  Get the answer mark without considering session mode.
     * @param kuet_questions_responses $response
     * @return float
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_simple_mark(kuet_questions_responses $response): float {
        $mark = 0;
        // Check ignore grading setting.
        $kquestion = kuet_questions::get_record(['id' => $response->get('kid')]);
        if ($kquestion !== false && $kquestion->get('ignorecorrectanswer')) {
            return (float)$mark;
        }

        // Teacher manual override (KUETEDUCAM-73): when present it prevails over
        // the automatically calculated mark. This is the root of the grading
        // chain, so the override flows through session, activity and gradebook
        // recalculation. In podium modes the callers still multiply this value by
        // the answer's time percentage (so the manual mark keeps the speed
        // weighting); in race/inactive modes it is summed directly.
        $manualmark = $response->get('manualmark');
        if ($manualmark !== null) {
            return (float) $manualmark;
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
        return (float)$mark;
    }

    /**
     * Apply a teacher manual mark override to a response and recalculate (KUETEDUCAM-73).
     *
     * Persists the manual mark and its justifying comment, refreshes the stored
     * result status and triggers the grade recalculation cascade (session →
     * activity → gradebook). In group sessions the mark is applied per team: it
     * is propagated to every group member's response for the same question and
     * each member is recalculated.
     *
     * The caller is responsible for validating the mark against the question
     * range (0..defaultmark) and for ensuring the question is editable.
     *
     * @param kuet_questions_responses $response response being edited
     * @param float $mark manual mark (0..defaultmark)
     * @param string $comment mandatory justification for the change
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     */
    public static function set_response_manual_mark(
        kuet_questions_responses $response,
        float $mark,
        string $comment
    ): void {
        $session = new kuet_sessions($response->get('session'));
        $kuetid = (int) $response->get('kuet');
        $kid = (int) $response->get('kid');

        if ($session->is_group_mode()) {
            // Same team, same mark: propagate to every member's response.
            $memberids = groupmode::get_grouping_group_members_by_userid(
                (int) $session->get('groupings'),
                (int) $response->get('userid')
            );
        } else {
            $memberids = [(int) $response->get('userid')];
        }

        foreach ($memberids as $memberid) {
            $memberresponse = kuet_questions_responses::get_record(
                ['kuet' => $kuetid, 'session' => $session->get('id'), 'kid' => $kid, 'userid' => (int) $memberid]
            );
            if ($memberresponse === false) {
                continue;
            }
            self::write_manual_mark($memberresponse, $mark, $comment);
            self::recalculate_mod_mark_by_userid((int) $memberid, $kuetid);
        }
    }

    /**
     * Persist a manual mark on a single response and refresh its result status.
     *
     * @param kuet_questions_responses $response
     * @param float $mark
     * @param string $comment
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     */
    private static function write_manual_mark(kuet_questions_responses $response, float $mark, string $comment): void {
        // Only the score is overridden. The response itself and its result status
        // (how the student answered) are left untouched (KUETEDUCAM-73): the
        // manual mark changes the grade, not the recorded answer.
        $response->set('manualmark', $mark);
        $response->set('manualcomment', $comment);
        $response->update();
    }

    /**
     * Get session grade
     *
     * @param int $userid
     * @param int $sessionid
     * @param int $kuetid
     * @return float
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_session_grade(int $userid, int $sessionid, int $kuetid): float {
        $responses = kuet_questions_responses::get_session_responses_for_user($userid, $sessionid, $kuetid);
        if (count($responses) === 0) {
            return 0;
        }

        $session = new kuet_sessions($sessionid);
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
        return (float)$mark;
    }

    /**
     * Get manual podium session type grade
     *
     * @param array $responses
     * @return float
     * @throws coding_exception
     * @throws moodle_exception
     */
    private static function get_session_podium_manual_grade(array $responses): float {
        $mark = 0;
        foreach ($responses as $response) {
            $usermark = self::get_simple_mark($response);
            if ($usermark === 0.0) {
                continue;
            }
            $qtime = kuet_questions::get_question_time($response->get('kid'), $response->get('session'));
            $useranswer = base64_decode($response->get('response'));
            $percent = 1;
            if ($qtime && !empty($useranswer)) {
                $useranswer = json_decode($useranswer);
                $timeleft = $useranswer->timeleft; // Time answering question.
                $percent = 0; // UNIMOOD-150.
                if ($qtime > $timeleft) {
                    $percent = $timeleft * 100 / $qtime;
                }
            }
            $mark += $usermark * $percent;
        }
        return (float)$mark;
    }

    /**
     * Get programmed podium session type grade
     *
     * @param array $responses
     * @param kuet_sessions $session
     * @return float
     * @throws coding_exception
     * @throws moodle_exception
     */
    private static function get_session_podium_programmed_grade(array $responses, kuet_sessions $session): float {
        $mark = 0;
        foreach ($responses as $response) {
            $usermark = self::get_simple_mark($response);
            if ($usermark === 0.0) {
                continue;
            }
            $qtime = kuet_questions::get_question_time($response->get('kid'), $response->get('session'));
            $useranswer = base64_decode($response->get('response'));
            $percent = 1;
            if ($qtime && !empty($useranswer)) {
                $useranswer = json_decode($useranswer);
                $timeleft = $useranswer->timeleft;
                $percent = $timeleft * 100 / $qtime;
            }
            $mark += $usermark * $percent;
        }
        return (float)$mark;
    }

    /**
     * Get session default grade
     *
     * @param array $responses
     * @return float
     * @throws coding_exception
     * @throws moodle_exception
     */
    private static function get_session_default_grade(array $responses): float {
        $mark = 0;
        foreach ($responses as $response) {
            $usermark = self::get_simple_mark($response);
            if ($usermark === 0.0) {
                continue;
            }
            $mark += $usermark;
        }
        return (float)$mark;
    }

    /**
     * Maximum mark obtainable in a session, summed over its gradable questions.
     *
     * Questions flagged with ignorecorrectanswer are excluded from the total,
     * so they do not count towards the number of questions of the session.
     *
     * @param int $sessionid
     * @return float
     * @throws dml_exception
     */
    public static function get_session_max_mark(int $sessionid): float {
        global $DB;
        $sql = "SELECT COALESCE(SUM(q.defaultmark), 0)
                  FROM {kuet_questions} kq
                  JOIN {question} q ON q.id = kq.questionid
                 WHERE kq.sessionid = :sessionid
                   AND kq.ignorecorrectanswer = 0";
        return (float) $DB->get_field_sql($sql, ['sessionid' => $sessionid]);
    }

    /**
     * @var int Scale of the time weighting applied by the podium scoring modes.
     *
     * Podium sessions weight each question mark by a percentage on a 0..100
     * scale (see get_session_podium_manual_grade / get_session_podium_programmed_grade),
     * so the maximum score achievable in a podium session is its summed
     * defaultmarks times this factor.
     */
    private const PODIUM_SCORE_SCALE = 100;

    /**
     * Get the session grade normalised to the activity's per-session maximum.
     *
     * The user's session score (already including the podium time penalty when
     * applicable) is scaled to kuet.sessiongrademax proportionally to the
     * maximum score obtainable in the session. So a fully correct user reaches
     * sessiongrademax regardless of how many questions the session has, while
     * the podium time penalty still reduces the grade proportionally.
     *
     * @param int $userid
     * @param int $sessionid
     * @param int $kuetid
     * @return float
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_normalized_session_grade(int $userid, int $sessionid, int $kuetid): float {
        $kuet = kuet::get_record(['id' => $kuetid]);
        if (!$kuet) {
            return 0.0;
        }
        $sessiongrademax = (float) $kuet->get('sessiongrademax');
        $maxmark = self::get_session_max_mark($sessionid);
        if ($maxmark <= 0.0 || $sessiongrademax <= 0.0) {
            return 0.0;
        }
        // Maximum score achievable under this session's own scoring: podium
        // modes score on a 0..100 time-weighted scale, the rest on the raw
        // mark scale. Dividing the user's (penalised) score by this maximum
        // keeps the penalty while mapping a perfect run onto sessiongrademax.
        $session = new kuet_sessions($sessionid);
        $ispodium = in_array(
            $session->get('sessionmode'),
            [sessions::PODIUM_MANUAL, sessions::PODIUM_PROGRAMMED],
            true
        );
        $maxscore = $maxmark * ($ispodium ? self::PODIUM_SCORE_SCALE : 1);

        $rawscore = self::get_session_grade($userid, $sessionid, $kuetid);
        return ($rawscore / $maxscore) * $sessiongrademax;
    }

    /**
     * Whether a session must be taken into account for a user's grade.
     *
     * A session counts when attendance is mandatory, or when the user actually
     * attended it (answered at least one question). When attendance is not
     * mandatory and the user did not attend, the session is excluded both from
     * the user's session grade and from the activity grade (KUETEDUCAM-72).
     *
     * @param int $userid
     * @param int $sessionid
     * @param int $kuetid
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function session_counts_for_user(int $userid, int $sessionid, int $kuetid): bool {
        $session = new kuet_sessions($sessionid);
        if ((int) $session->get('mandatoryattendance') === 1) {
            return true;
        }
        $responses = kuet_questions_responses::get_session_responses_for_user($userid, $sessionid, $kuetid);
        return count($responses) > 0;
    }

    /**
     * Recalculate module mark by user id
     *
     * @param int $userid
     * @param int $kuetid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function recalculate_mod_mark_by_userid(int $userid, int $kuetid): void {
        $params = ['userid' => $userid, 'kuet' => $kuetid];
        $allgrades = kuet_sessions_grades::get_records($params);

        $kuet = kuet::get_record(['id' => $kuetid]);
        $grademethod = $kuet->get('grademethod');

        // Renormalise every stored session grade to the current per-session max
        // and drop the sessions that no longer count for this user (non-mandatory
        // sessions the user did not attend). So a change to sessiongrademax
        // (KUETEDUCAM-67) or to mandatoryattendance (KUETEDUCAM-72) is reflected,
        // and the activity grade is combined only over the counting sessions.
        $counting = [];
        foreach ($allgrades as $sessiongrade) {
            $sessionid = $sessiongrade->get('session');
            if (!self::session_counts_for_user($userid, $sessionid, $kuetid)) {
                $sessiongrade->delete();
                continue;
            }
            $normalized = self::get_normalized_session_grade($userid, $sessionid, $kuetid);
            if ((float) $sessiongrade->get('grade') !== $normalized) {
                $sessiongrade->set('grade', $normalized);
                $sessiongrade->update();
            }
            $counting[] = $sessiongrade;
        }

        if (count($counting) === 0) {
            // No session counts for this user: leave the gradebook item empty
            // for them instead of forcing a 0 (KUETEDUCAM-72).
            self::clear_mod_grade($userid, $kuet);
            return;
        }

        $finalgrade = self::get_final_mod_grade($counting, $grademethod);

        // Save final grade for kuet. Look the row up by user+activity only: a
        // previous version filtered by grade too, so a changed grade did not
        // match and a duplicate row was created instead of updating. Keep one row
        // and remove any duplicates left behind.
        $jgrades = kuet_grades::get_records(['userid' => $userid, 'kuet' => $kuetid]);
        if (empty($jgrades)) {
            $jg = new kuet_grades(0, (object) ['userid' => $userid, 'kuet' => $kuetid, 'grade' => $finalgrade]);
            $jg->save();
        } else {
            $jgrade = array_shift($jgrades);
            $jgrade->set('grade', $finalgrade);
            $jgrade->update();
            foreach ($jgrades as $duplicate) {
                $duplicate->delete();
            }
        }

        // Save final grade for grade report.
        $params['grade'] = $finalgrade;
        $params['rawgrade'] = $finalgrade;
        $params['rawgrademax'] = get_config('core', 'gradepointmax');
        $params['rawgrademin'] = 0;
        mod_kuet_grade_item_update($kuet->to_record(), $params);
    }

    /**
     * Clear a user's activity grade.
     *
     * Removes the stored kuet_grades row and pushes a null grade to the
     * gradebook, so a user with no counting sessions ends up with no activity
     * grade rather than a 0 (KUETEDUCAM-72).
     *
     * @param int $userid
     * @param kuet $kuet
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    private static function clear_mod_grade(int $userid, kuet $kuet): void {
        $existing = kuet_grades::get_records(['userid' => $userid, 'kuet' => $kuet->get('id')]);
        foreach ($existing as $row) {
            $row->delete();
        }
        $grades = [
            'userid' => $userid,
            'rawgrade' => null,
            'rawgrademax' => get_config('core', 'gradepointmax'),
            'rawgrademin' => 0,
        ];
        mod_kuet_grade_item_update($kuet->to_record(), $grades);
    }

    /**
     * Recalculate module mark
     *
     * @param int $cmid
     * @param int $kuetid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function recalculate_mod_mark(int $cmid, int $kuetid): void {
        $students = \mod_kuet\kuet::get_students($cmid);
        if (empty($students)) {
            return;
        }
        $sessions = kuet_sessions::get_records(['kuetid' => $kuetid]);
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
            self::recalculate_mod_mark_by_userid($student->{'id'}, $kuetid);
        }
    }

    /**
     * Get final module grade
     *
     * @param array $allgrades
     * @param string $grademethod
     * @return float
     * @throws coding_exception
     */
    private static function get_final_mod_grade(array $allgrades, string $grademethod): float {

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
     * Get highest  grade
     *
     * @param array $allgrades
     * @return float
     */
    private static function get_highest_grade(array $allgrades): float {
        $finalmark = 0;
        foreach ($allgrades as $grade) {
            if ($grade->get('grade') > $finalmark) {
                $finalmark = $grade->get('grade');
            }
        }
        return $finalmark;
    }

    /**
     * Get average grade
     *
     * @param array $allgrades
     * @return float
     */
    private static function get_average_grade(array $allgrades): float {
        $finalmark = 0;
        $total = count($allgrades);
        foreach ($allgrades as $grade) {
            $finalmark += $grade->get('grade');
        }
        return $finalmark / $total;
    }

    /**
     * Get first grade
     *
     * @param array $allgrades
     * @return float
     */
    private static function get_first_grade(array $allgrades): float {
        return reset($allgrades)->get('grade');
    }

    /**
     * Get last grade
     *
     * @param array $allgrades
     * @return float
     */
    private static function get_last_grade(array $allgrades): float {
        return end($allgrades)->get('grade');
    }

    /**
     * Get result mark by question type
     *
     * @param kuet_questions_responses $response
     * @return string
     * @throws coding_exception
     */
    public static function get_result_mark_type(kuet_questions_responses $response): string {
        return self::get_result_mark_type_by_status((int)$response->get('result'));
    }

    /**
     * Get result mark by question status constant
     *
     * The returned value is a **key**, never a translated label: callers use it
     * as the language string identifier, as the CSS class and as the pix icon
     * name (`pix/q/<key>.svg`). Passing it through get_string() before storing
     * it breaks the three at once.
     *
     * @param int $status one of the questions::* status constants
     * @return string
     */
    public static function get_result_mark_type_by_status(int $status): string {
        switch ($status) {
            case questions::FAILURE:
                $result = 'incorrect';
                break;
            case questions::SUCCESS:
                $result = 'success';
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
     * Count mark results by question result
     *
     * @param array $responses
     * @return int[]
     */
    public static function count_result_mark_types(array $responses): array {
        $correct = 0;
        $incorrect = 0;
        $partially = 0;
        $noresponse = 0;
        $invalid = 0;
        foreach ($responses as $response) {
            $result = $response->get('result');
            switch ($result) {
                case questions::SUCCESS:
                    $correct++;
                    break;
                case questions::FAILURE:
                    $incorrect++;
                    break;
                case questions::INVALID:
                    $invalid++;
                    break;
                case questions::PARTIALLY:
                    $partially++;
                    break;
                case questions::NORESPONSE:
                    $noresponse++;
                    break;
            }
        }
        return [$correct, $incorrect, $invalid, $partially, $noresponse];
    }
}
