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
class description_external_test extends advanced_testcase {

    public function test_description() : void {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');

        // Only a user with capability can add questions.
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        $student1 = self::getDataGenerator()->create_and_enrol($course);
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
            'showgraderanking' => 0,
        ];
        $createdsid = $generator->create_session($kuet, (object) $sessionmock);

        // Create questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $dq = $questiongenerator->create_question(questions::DESCRIPTION, null, array('category' => $cat->id));

        // Add questions to a session.
        $questions = [
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DESCRIPTION]
        ];
        $generator->add_questions_to_session($questions);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $createdsid);

        $qbd = question_bank::load_question($dq->id);

        $jdq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DESCRIPTION]
        );

        // User 1 answers a correct answer.
        self::setUser($student1);
        $hasfeedback = !empty($qbd->generalfeedback);
        $feedback = questions::get_text(
            $kuet->cmid, $qbd->generalfeedback, $qbd->generalfeedbackformat, $qbd->id, $qbd, 'generalfeedback'
        );
        $data1 = \mod_kuet\external\description_external::description($createdsid, $kuet->id,
            $kuet->cmid, $dq->id, $jdq->get('id'), 10, false);
        $this->assertIsArray($data1);
        $this->assertArrayHasKey('reply_status', $data1);
        $this->assertArrayHasKey('result', $data1);
        $this->assertArrayHasKey('hasfeedbacks', $data1);
        $this->assertArrayHasKey('statment_feedback', $data1);
        $this->assertArrayHasKey('programmedmode', $data1);
        $this->assertArrayHasKey('preview', $data1);
        $this->assertTrue($data1['reply_status']);
        $this->assertEquals(questions::NOTEVALUABLE, $data1['result']);
        $this->assertEquals($hasfeedback, $data1['hasfeedbacks']);
        $this->assertEquals($feedback, $data1['statment_feedback']);
        $this->assertFalse($data1['programmedmode']);
        $this->assertFalse($data1['preview']);
    }
}
