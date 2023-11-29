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

use mod_kuet\models\sessions;
use mod_kuet\models\questions;
/**
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class truefalse_external_test extends advanced_testcase {

    public function test_truefalse() {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['jqshowid'] = $jqshow->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');

        // Only a user with capability can add questions.
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = self::getDataGenerator()->create_and_enrol($course);
        $student2 = self::getDataGenerator()->create_and_enrol($course);
        self::setUser($teacher);
        // Create session.
        $sessionmock = [
            'name' => 'Session Test',
            'jqshowid' => $jqshow->id,
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
            'showgraderanking' => 0,
        ];
        $createdsid = $generator->create_session($jqshow, (object) $sessionmock);

        // Create questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $tfq = $questiongenerator->create_question(questions::TRUE_FALSE, null, array('category' => $cat->id));

        // Add questions to a session.
        $questions = [
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::TRUE_FALSE]
        ];
        $generator->add_questions_to_session($questions);
        \mod_kuet\external\startsession_external::startsession($jqshow->cmid, $createdsid);

        $qbtf = question_bank::load_question($tfq->id);
        $jtfq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::TRUE_FALSE]
        );

        // User 1 answers a correct answer.
        self::setUser($student);
        $user1answerid = $qbtf->trueanswerid;
        $data = \mod_kuet\external\truefalse_external::truefalse($user1answerid, $createdsid, $jqshow->id,
            $jqshow->cmid, $tfq->id, $jtfq->get('id'), 10, false);

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
            $jqshow->cmid, $qbtf->generalfeedback, 1, $qbtf->id, $qbtf, 'generalfeedback'
        );
        $answerfeedback1 = questions::get_text(
                    $jqshow->cmid, $qbtf->truefeedback, 1, (int) $qbtf->trueanswerid, $qbtf, 'answerfeedback'
                ) . '<br>';

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
        $data = \mod_kuet\external\truefalse_external::truefalse($user2answerid, $createdsid, $jqshow->id,
            $jqshow->cmid, $tfq->id, $jtfq->get('id'), 10, false);

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
            $jqshow->cmid, $qbtf->generalfeedback, 1, $qbtf->id, $qbtf, 'generalfeedback'
        );

        $answerfeedback2 = $answerfeedback = questions::get_text(
                $jqshow->cmid, $qbtf->falsefeedback, 1, (int) $qbtf->falseanswerid, $qbtf, 'answerfeedback'
            ) . '<br>';
        $hasfeedback = !empty($statmentfeedback) || !empty($answerfeedback);
        $this->assertEquals($hasfeedback, $data['hasfeedbacks']);
        $this->assertEquals($statmentfeedback, $data['statment_feedback']);
        $this->assertEquals($answerfeedback2, $data['answer_feedback']);
        $this->assertEquals($qbtf->trueanswerid, $data['correct_answers']);
        $this->assertFalse($data['programmedmode']);
        $this->assertFalse($data['preview']);
    }
}
