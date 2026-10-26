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

namespace mod_kuet;

use mod_kuet\models\sessions;
use mod_kuet\models\questions;
/**
 * Truefalse question type test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Truefalse question type test class
 *
 * @covers \mod_kuet\external\truefalse_external
 */
final class truefalse_external_test extends \advanced_testcase {
    /**
     * Truefalse question type test
     *
     * @return void
     * @throws \JsonException
     * @throws \core\invalid_persistent_exception
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_truefalse(): void {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');

        // Only a user with capability can add questions.
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = self::getDataGenerator()->create_and_enrol($course);
        $student2 = self::getDataGenerator()->create_and_enrol($course);
        self::setUser($teacher);
        // Create session.
        $sessionmock = [
            'name' => 'Session Test',
            'kuetid' => $kuet->id,
            'anonymousanswer' => 0,
            'sessionmode' => \mod_kuet\models\sessions::PODIUM_MANUAL,
            'sgrade' => 0,
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
            'status' => \mod_kuet\models\sessions::SESSION_ACTIVE,
            'sessionid' => 0,
            'submitbutton' => 0,
        ];
        $createdsid = $generator->create_session($kuet, (object) $sessionmock);

        // Create questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $tfq = $questiongenerator->create_question(questions::TRUE_FALSE, null, ['category' => $cat->id]);

        // Add questions to a session.
        $questions = [
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE],
        ];
        $generator->add_questions_to_session($questions);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $createdsid);

        $qbtf = \question_bank::load_question($tfq->id);
        $jtfq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE]
        );

        // User 1 answers a correct answer.
        self::setUser($student);
        $user1answerid = $qbtf->trueanswerid;
        $data = \mod_kuet\external\truefalse_external::truefalse(
            $user1answerid,
            $createdsid,
            $kuet->id,
            $kuet->cmid,
            $tfq->id,
            $jtfq->get('id'),
            10,
            false
        );

        $this->assertIsArray($data);
        $this->assertArrayHasKey('reply_status', $data);
        $this->assertArrayHasKey('hasfeedbacks', $data);
        $this->assertArrayHasKey('statment_feedback', $data);
        $this->assertArrayHasKey('answer_feedback', $data);
        $this->assertArrayHasKey('correct_answers', $data);
        $this->assertArrayHasKey('programmedmode', $data);
        $this->assertArrayHasKey('preview', $data);
        $this->assertTrue($data['reply_status']);

        $statmentfeedback = questions::get_text(
            $kuet->cmid,
            $qbtf->generalfeedback,
            1,
            $qbtf->id,
            $qbtf,
            'generalfeedback'
        );
        $answerfeedback1 = questions::get_text(
            $kuet->cmid,
            $qbtf->truefeedback,
            1,
            (int) $qbtf->trueanswerid,
            $qbtf,
            'answerfeedback'
        );

        $hasfeedback = !empty($statmentfeedback) || !empty($answerfeedback);
        $this->assertEquals($hasfeedback, $data['hasfeedbacks']);
        $this->assertEquals($statmentfeedback, $data['statment_feedback']);
        $this->assertEquals($answerfeedback1, $data['answer_feedback']);
        $this->assertEquals($qbtf->trueanswerid, $data['correct_answers']);
        $this->assertFalse($data['programmedmode']);
        $this->assertFalse($data['preview']);

        // User 2 answers an incorrect answer.
        self::setUser($student2);
        $user2answerid = $qbtf->falseanswerid;
        $data = \mod_kuet\external\truefalse_external::truefalse(
            $user2answerid,
            $createdsid,
            $kuet->id,
            $kuet->cmid,
            $tfq->id,
            $jtfq->get('id'),
            10,
            false
        );

        $this->assertIsArray($data);
        $this->assertArrayHasKey('reply_status', $data);
        $this->assertArrayHasKey('hasfeedbacks', $data);
        $this->assertArrayHasKey('statment_feedback', $data);
        $this->assertArrayHasKey('answer_feedback', $data);
        $this->assertArrayHasKey('correct_answers', $data);
        $this->assertArrayHasKey('programmedmode', $data);
        $this->assertArrayHasKey('preview', $data);
        $this->assertTrue($data['reply_status']);

        $statmentfeedback = questions::get_text(
            $kuet->cmid,
            $qbtf->generalfeedback,
            1,
            $qbtf->id,
            $qbtf,
            'generalfeedback'
        );

        $answerfeedback2 = $answerfeedback = questions::get_text(
            $kuet->cmid,
            $qbtf->falsefeedback,
            1,
            (int) $qbtf->falseanswerid,
            $qbtf,
            'answerfeedback'
        );
        $hasfeedback = !empty($statmentfeedback) || !empty($answerfeedback);
        $this->assertEquals($hasfeedback, $data['hasfeedbacks']);
        $this->assertEquals($statmentfeedback, $data['statment_feedback']);
        $this->assertEquals($answerfeedback2, $data['answer_feedback']);
        $this->assertEquals($qbtf->trueanswerid, $data['correct_answers']);
        $this->assertFalse($data['programmedmode']);
        $this->assertFalse($data['preview']);
    }

    /**
     * A true or false question with no feedback at all reports that it has none
     *
     * The break was appended to the feedback of the answer whether that answer carried
     * one or not, so a question with none left the string at a lone break. That is not
     * empty, so hasfeedbacks came back true and the participant was shown an empty
     * feedback box (KUET-054). Same shape as KUET-050 on multichoice.
     *
     * @covers \mod_kuet\external\truefalse_external::truefalse
     * @return void
     */
    public function test_a_question_with_no_feedback_reports_none(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = self::getDataGenerator()->create_and_enrol($course);
        self::setUser($teacher);

        $sid = $generator->create_session($kuet, (object) [
            'name' => 'Session Test',
            'kuetid' => $kuet->id,
            'anonymousanswer' => 0,
            'sessionmode' => sessions::PODIUM_MANUAL,
            'sgrade' => 0,
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
        ]);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $tfq = $questiongenerator->create_question(questions::TRUE_FALSE, null, ['category' => $cat->id]);

        // The question core builds carries feedback on both answers; this one has none.
        $DB->set_field('question', 'generalfeedback', '', ['id' => $tfq->id]);
        $DB->set_field('question_answers', 'feedback', '', ['question' => $tfq->id]);

        $generator->add_questions_to_session([
            ['questionid' => $tfq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id,
                'qtype' => questions::TRUE_FALSE],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);
        $kid = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $tfq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id,
                'qtype' => questions::TRUE_FALSE]
        )->get('id');

        $loaded = \question_bank::load_question($tfq->id);
        self::setUser($student);
        $data = \mod_kuet\external\truefalse_external::truefalse(
            (int) $loaded->trueanswerid,
            $sid,
            $kuet->id,
            $kuet->cmid,
            $tfq->id,
            $kid,
            10,
            false
        );

        $this->assertTrue($data['reply_status']);
        $this->assertSame('', $data['statment_feedback']);
        $this->assertSame('', $data['answer_feedback'], 'A break was left behind as if it were feedback');
        $this->assertFalse($data['hasfeedbacks'], 'The question says it has feedback when it has none');
    }
}
