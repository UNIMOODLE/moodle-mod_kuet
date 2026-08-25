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

namespace mod_kuet;

use mod_kuet\api\grade;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;

/**
 * Grade API tests
 *
 * @package    mod_kuet
 * @author     3IPUNT <contacte@tresipunt.com>
 * @copyright  3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kuet\api\grade::get_normalized_session_grade
 */
final class grade_test extends \advanced_testcase {
    /**
     * A fully correct answer in a non-podium session yields exactly the
     * configured per-session maximum; an unanswered user yields zero.
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \core\invalid_persistent_exception
     */
    public function test_normalized_grade_full_and_empty(): void {
        $this->resetAfterTest();

        $sessiongrademax = 10.0;
        [$course, $kuet, $sid, $generator] = $this->create_kuet_session($sessiongrademax, sessions::INACTIVE_MANUAL);

        // Single gradable multichoice question.
        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, ['category' => $cat->id]);
        $generator->add_questions_to_session([
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);

        $student = self::getDataGenerator()->create_and_enrol($course);
        $unanswered = self::getDataGenerator()->create_and_enrol($course);

        // The student answers all correct options.
        $qbmc = \question_bank::load_question($mcq->id);
        $correctanswers = [];
        foreach ($qbmc->answers as $answer) {
            if ($answer->fraction > 0) {
                $correctanswers[] = $answer->id;
            }
        }
        $jmcq = kuet_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]
        );
        self::setUser($student);
        \mod_kuet\external\multichoice_external::multichoice(
            implode(',', $correctanswers),
            $sid,
            $kuet->id,
            $kuet->cmid,
            $mcq->id,
            $jmcq->get('id'),
            10,
            false
        );

        // Fully correct over a single question normalises to the session maximum.
        $this->assertEqualsWithDelta(
            $sessiongrademax,
            grade::get_normalized_session_grade($student->id, $sid, $kuet->id),
            0.00001
        );

        // No responses at all means no grade.
        $this->assertEqualsWithDelta(
            0.0,
            grade::get_normalized_session_grade($unanswered->id, $sid, $kuet->id),
            0.00001
        );
    }

    /**
     * Questions flagged with ignorecorrectanswer are excluded from the session
     * maximum, so they do not count towards the number of questions.
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \core\invalid_persistent_exception
     */
    public function test_session_max_mark_excludes_ignored(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $kuet, $sid, $generator] = $this->create_kuet_session(10.0, sessions::INACTIVE_MANUAL);

        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $gradable = $questiongenerator->create_question(questions::MULTICHOICE, null, ['category' => $cat->id]);
        $ignored = $questiongenerator->create_question(questions::MULTICHOICE, null, ['category' => $cat->id]);
        $generator->add_questions_to_session([
            ['questionid' => $gradable->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
            ['questionid' => $ignored->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
        ]);

        // Flag the second question so it is not graded.
        $kignored = kuet_questions::get_record(
            ['questionid' => $ignored->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]
        );
        $kignored->set('ignorecorrectanswer', 1);
        $kignored->update();

        // Only the gradable question's defaultmark counts towards the maximum.
        $expectedmax = (float) $DB->get_field('question', 'defaultmark', ['id' => $gradable->id]);
        $this->assertEqualsWithDelta($expectedmax, grade::get_session_max_mark($sid), 0.00001);
    }

    /**
     * A session counts for a user when attendance is mandatory (even if absent),
     * and when attendance is not mandatory only if the user actually answered
     * at least one question (KUETEDUCAM-72).
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \core\invalid_persistent_exception
     */
    public function test_session_counts_for_user(): void {
        $this->resetAfterTest();

        // Non-mandatory session: an absentee is excluded, an attendee counts.
        [$course, $kuet, $sid, $generator] = $this->create_kuet_session(10.0, sessions::INACTIVE_MANUAL, 0);

        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, ['category' => $cat->id]);
        $generator->add_questions_to_session([
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);

        $attendee = self::getDataGenerator()->create_and_enrol($course);
        $absentee = self::getDataGenerator()->create_and_enrol($course);

        $qbmc = \question_bank::load_question($mcq->id);
        $correctanswers = [];
        foreach ($qbmc->answers as $answer) {
            if ($answer->fraction > 0) {
                $correctanswers[] = $answer->id;
            }
        }
        $jmcq = kuet_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]
        );
        self::setUser($attendee);
        \mod_kuet\external\multichoice_external::multichoice(
            implode(',', $correctanswers),
            $sid,
            $kuet->id,
            $kuet->cmid,
            $mcq->id,
            $jmcq->get('id'),
            10,
            false
        );

        $this->assertTrue(grade::session_counts_for_user($attendee->id, $sid, $kuet->id));
        $this->assertFalse(grade::session_counts_for_user($absentee->id, $sid, $kuet->id));

        // Mandatory session: an absentee still counts (and would score 0).
        [, $mkuet, $msid] = $this->create_kuet_session(10.0, sessions::INACTIVE_MANUAL, 1);
        $mabsentee = self::getDataGenerator()->create_and_enrol($course);
        $this->assertTrue(grade::session_counts_for_user($mabsentee->id, $msid, $mkuet->id));
    }

    /**
     * A manual mark override (KUETEDUCAM-73) prevails over the calculated mark in
     * a non-podium session: the normalized session grade follows the manual mark,
     * and the stored response result is refreshed accordingly.
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \core\invalid_persistent_exception
     */
    public function test_manual_mark_override_individual(): void {
        global $DB;
        $this->resetAfterTest();

        $sessiongrademax = 10.0;
        [$course, $kuet, $sid, $generator] = $this->create_kuet_session($sessiongrademax, sessions::INACTIVE_MANUAL);

        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, ['category' => $cat->id]);
        $generator->add_questions_to_session([
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);

        $student = self::getDataGenerator()->create_and_enrol($course);
        $jmcq = kuet_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]
        );
        $this->answer_multichoice_correct($mcq, $jmcq->get('id'), $kuet, $sid, $student, 10);

        // Baseline: a fully correct answer normalises to the session maximum.
        $this->assertEqualsWithDelta(
            $sessiongrademax,
            grade::get_normalized_session_grade($student->id, $sid, $kuet->id),
            0.00001
        );

        $defaultmark = (float) $DB->get_field('question', 'defaultmark', ['id' => $mcq->id]);
        $response = kuet_questions_responses::get_record(
            ['kuet' => $kuet->id, 'session' => $sid, 'kid' => $jmcq->get('id'), 'userid' => $student->id]
        );

        // The original result status of the answer (a correct answer => success).
        $originalresult = (int) $response->get('result');
        $this->assertEquals(questions::SUCCESS, $originalresult);

        // Half mark -> half of the session maximum. The response and its result
        // status are NOT altered, only the mark is overridden.
        grade::set_response_manual_mark($response, $defaultmark / 2, 'Partially valid answer');
        $this->assertEqualsWithDelta(
            $sessiongrademax / 2,
            grade::get_normalized_session_grade($student->id, $sid, $kuet->id),
            0.00001
        );
        $stored = kuet_questions_responses::get_record(
            ['kuet' => $kuet->id, 'session' => $sid, 'kid' => $jmcq->get('id'), 'userid' => $student->id]
        );
        $this->assertEqualsWithDelta($defaultmark / 2, (float) $stored->get('manualmark'), 0.00001);
        $this->assertEquals('Partially valid answer', $stored->get('manualcomment'));
        $this->assertEquals($originalresult, (int) $stored->get('result'));

        // Zero -> no grade; result status still unchanged.
        grade::set_response_manual_mark($stored, 0.0, 'Invalid answer');
        $this->assertEqualsWithDelta(
            0.0,
            grade::get_normalized_session_grade($student->id, $sid, $kuet->id),
            0.00001
        );
        $stored = kuet_questions_responses::get_record(
            ['kuet' => $kuet->id, 'session' => $sid, 'kid' => $jmcq->get('id'), 'userid' => $student->id]
        );
        $this->assertEquals($originalresult, (int) $stored->get('result'));

        // Full mark -> session maximum again.
        grade::set_response_manual_mark($stored, $defaultmark, 'Fully valid answer');
        $this->assertEqualsWithDelta(
            $sessiongrademax,
            grade::get_normalized_session_grade($student->id, $sid, $kuet->id),
            0.00001
        );
    }

    /**
     * In podium sessions the manual mark is NOT definitive: the answer's time
     * percentage is still applied (KUETEDUCAM-73, decision D4). With a 50% speed
     * percentage, a half manual mark yields a quarter of the session maximum.
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \core\invalid_persistent_exception
     */
    public function test_manual_mark_override_podium_applies_percent(): void {
        global $DB;
        $this->resetAfterTest();

        $sessiongrademax = 10.0;
        [$course, $kuet, $sid, $generator] = $this->create_kuet_session($sessiongrademax, sessions::PODIUM_MANUAL);

        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, ['category' => $cat->id]);
        $generator->add_questions_to_session([
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);

        $student = self::getDataGenerator()->create_and_enrol($course);
        $jmcq = kuet_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]
        );
        // Question time is 10s (session questiontime); answer with 5s left -> 50% speed.
        $this->answer_multichoice_correct($mcq, $jmcq->get('id'), $kuet, $sid, $student, 5);

        // Correct answer at 50% speed: half of the session maximum.
        $this->assertEqualsWithDelta(
            $sessiongrademax / 2,
            grade::get_normalized_session_grade($student->id, $sid, $kuet->id),
            0.00001
        );

        $defaultmark = (float) $DB->get_field('question', 'defaultmark', ['id' => $mcq->id]);
        $response = kuet_questions_responses::get_record(
            ['kuet' => $kuet->id, 'session' => $sid, 'kid' => $jmcq->get('id'), 'userid' => $student->id]
        );

        // Half manual mark, still weighted by the 50% time percentage:
        // (defaultmark/2 * 50) / (defaultmark * 100) * sessiongrademax = sessiongrademax / 4.
        grade::set_response_manual_mark($response, $defaultmark / 2, 'Partially valid answer');
        $this->assertEqualsWithDelta(
            $sessiongrademax / 4,
            grade::get_normalized_session_grade($student->id, $sid, $kuet->id),
            0.00001
        );
    }

    /**
     * In group sessions a manual mark is applied per team: it propagates to every
     * group member's response and recalculates each member (KUETEDUCAM-73).
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \core\invalid_persistent_exception
     */
    public function test_manual_mark_propagates_to_group(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = self::getDataGenerator();
        $sessiongrademax = 10.0;
        $course = $gen->create_course();
        $kuet = $gen->create_module('kuet', [
            'course' => $course->id,
            'grademethod' => grade::MOD_OPTION_GRADE_HIGHEST,
            'sessiongrademax' => $sessiongrademax,
        ]);

        // A group with two students inside a grouping.
        $group = $gen->create_group(['courseid' => $course->id]);
        $grouping = $gen->create_grouping(['courseid' => $course->id]);
        $gen->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $group->id]);
        $member1 = $gen->create_and_enrol($course);
        $member2 = $gen->create_and_enrol($course);
        $gen->create_group_member(['groupid' => $group->id, 'userid' => $member1->id]);
        $gen->create_group_member(['groupid' => $group->id, 'userid' => $member2->id]);

        $teacher = $gen->create_and_enrol($course, 'teacher');
        self::setUser($teacher);

        $generator = $gen->get_plugin_generator('mod_kuet');
        $sessionmock = [
            'name' => 'Group session', 'kuetid' => $kuet->id, 'anonymousanswer' => 0,
            'sessionmode' => sessions::INACTIVE_MANUAL, 'sgrade' => 1, 'mandatoryattendance' => 1,
            'countdown' => 0, 'showgraderanking' => 0, 'randomquestions' => 0, 'randomanswers' => 0,
            'showfeedback' => 0, 'showfinalgrade' => 0, 'startdate' => 0, 'enddate' => 0, 'automaticstart' => 0,
            'timemode' => sessions::QUESTION_TIME, 'sessiontime' => 0, 'questiontime' => 10,
            'groupings' => $grouping->id, 'status' => sessions::SESSION_ACTIVE, 'sessionid' => 0, 'submitbutton' => 0,
        ];
        $sid = $generator->create_session($kuet, (object) $sessionmock);

        $questiongenerator = $gen->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, ['category' => $cat->id]);
        $generator->add_questions_to_session([
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);

        $jmcq = kuet_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]
        );
        // One member answers: in group mode a response row is stored for every member.
        $this->answer_multichoice_correct($mcq, $jmcq->get('id'), $kuet, $sid, $member1, 10);

        $defaultmark = (float) $DB->get_field('question', 'defaultmark', ['id' => $mcq->id]);
        $response1 = kuet_questions_responses::get_record(
            ['kuet' => $kuet->id, 'session' => $sid, 'kid' => $jmcq->get('id'), 'userid' => $member1->id]
        );
        $this->assertNotFalse($response1);

        // Editing one member propagates the manual mark to the whole team.
        grade::set_response_manual_mark($response1, $defaultmark / 2, 'Team adjustment');

        foreach ([$member1, $member2] as $member) {
            $stored = kuet_questions_responses::get_record(
                ['kuet' => $kuet->id, 'session' => $sid, 'kid' => $jmcq->get('id'), 'userid' => $member->id]
            );
            $this->assertNotFalse($stored);
            $this->assertEqualsWithDelta($defaultmark / 2, (float) $stored->get('manualmark'), 0.00001);
            $this->assertEquals('Team adjustment', $stored->get('manualcomment'));
            $this->assertEqualsWithDelta(
                $sessiongrademax / 2,
                grade::get_normalized_session_grade($member->id, $sid, $kuet->id),
                0.00001
            );
        }
    }

    /**
     * Answer a multichoice question with all its correct options for a user.
     *
     * @param \stdClass $mcq question record from the question generator
     * @param int $kid kuet_questions id of the question in the session
     * @param \stdClass $kuet kuet instance
     * @param int $sid session id
     * @param \stdClass $user user answering
     * @param int $timeleft seconds left when answering (drives the podium percentage)
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function answer_multichoice_correct(
        \stdClass $mcq,
        int $kid,
        \stdClass $kuet,
        int $sid,
        \stdClass $user,
        int $timeleft
    ): void {
        $qbmc = \question_bank::load_question($mcq->id);
        $correctanswers = [];
        foreach ($qbmc->answers as $answer) {
            if ($answer->fraction > 0) {
                $correctanswers[] = $answer->id;
            }
        }
        self::setUser($user);
        \mod_kuet\external\multichoice_external::multichoice(
            implode(',', $correctanswers),
            $sid,
            $kuet->id,
            $kuet->cmid,
            $mcq->id,
            $kid,
            $timeleft,
            false
        );
    }

    /**
     * Create a course, a graded kuet instance and a session of the given mode.
     *
     * @param float $sessiongrademax maximum grade obtainable per session
     * @param string $sessionmode one of the sessions::* mode constants
     * @param int $mandatory whether attendance is mandatory (1) or not (0)
     * @return array [\stdClass course, \stdClass kuet, int sessionid, mod_kuet_generator generator]
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    private function create_kuet_session(float $sessiongrademax, string $sessionmode, int $mandatory = 1): array {
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', [
            'course' => $course->id,
            'grademethod' => grade::MOD_OPTION_GRADE_HIGHEST,
            'sessiongrademax' => $sessiongrademax,
        ]);

        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);

        $generator = self::getDataGenerator()->get_plugin_generator('mod_kuet');
        $sessionmock = [
            'name' => 'Session Test',
            'kuetid' => $kuet->id,
            'anonymousanswer' => 0,
            'sessionmode' => $sessionmode,
            'sgrade' => 0,
            'mandatoryattendance' => $mandatory,
            'countdown' => 0,
            'showgraderanking' => 0,
            'randomquestions' => 0,
            'randomanswers' => 0,
            'showfeedback' => 0,
            'showfinalgrade' => 0,
            'startdate' => 0,
            'enddate' => 0,
            'automaticstart' => 0,
            'timemode' => sessions::QUESTION_TIME,
            'sessiontime' => 0,
            'questiontime' => 10,
            'groupings' => 0,
            'status' => sessions::SESSION_ACTIVE,
            'sessionid' => 0,
            'submitbutton' => 0,
        ];
        $sid = $generator->create_session($kuet, (object) $sessionmock);

        return [$course, $kuet, $sid, $generator];
    }
}
