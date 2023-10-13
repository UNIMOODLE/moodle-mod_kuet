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

use mod_jqshow\models\questions;
/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @category   test
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_getallquestions_external_test extends advanced_testcase {

    public function test_session_getallquestions() {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('jqshow', ['course' => $course->id]);
        $this->sessionmock['jqshowid'] = $jqshow->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_jqshow');

        // Only a user with capability can add questions.
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);
        // Create session.
        $sessionmock = [
            'name' => 'Session Test',
            'jqshowid' => $jqshow->id,
            'anonymousanswer' => 0,
            'sessionmode' => \mod_jqshow\models\sessions::PODIUM_MANUAL,
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
            'timemode' => 0,
            'sessiontime' => 0,
            'questiontime' => 10,
            'groupings' => 0,
            'status' => \mod_jqshow\models\sessions::SESSION_ACTIVE,
            'sessionid' => 0,
            'submitbutton' => 0,
            'showgraderanking' => 0,
        ];
        $createdsid = $generator->create_session($jqshow, (object) $sessionmock);

        // Create questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question(questions::SHORTANSWER, null, array('category' => $cat->id));
        $nq = $questiongenerator->create_question(questions::NUMERICAL, null, array('category' => $cat->id));
        $tfq = $questiongenerator->create_question(questions::TRUE_FALSE, null, array('category' => $cat->id));
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, array('category' => $cat->id));
        $ddwtosq = $questiongenerator->create_question(questions::DDWTOS, null, array('category' => $cat->id));
        $dq = $questiongenerator->create_question(questions::DESCRIPTION, null, array('category' => $cat->id));

        // Add questions to a session.
        $questions = [
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::NUMERICAL],
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::TRUE_FALSE],
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::MULTICHOICE],
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DDWTOS],
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DESCRIPTION],
        ];
        $generator->add_questions_to_session($questions);
        $data = \mod_jqshow\external\session_getallquestions_external::session_getallquestions($jqshow->cmid, $createdsid);

        $this->assertIsArray($data);
        $this->assertEquals(6, count($data));

        // Shortanswer test.
        $this->assertIsObject($data[0]);
        $this->assertObjectHasAttribute('cmid', $data[0]);
        $this->assertEquals($jqshow->cmid, $data[0]->cmid);
        $this->assertObjectHasAttribute('questionid', $data[0]);
        $this->assertEquals($saq->id, $data[0]->questionid);
        $this->assertObjectHasAttribute('qtype', $data[0]);
        $this->assertEquals(questions::SHORTANSWER, $data[0]->qtype);
        $this->assertObjectHasAttribute('sessionid', $data[0]);
        $this->assertEquals($createdsid, $data[0]->sessionid);
        $this->assertObjectHasAttribute('jqshowid', $data[0]);
        $this->assertEquals($jqshow->id, $data[0]->jqshowid);
        // Numerical Test.
        $this->assertIsObject($data[1]);
        $this->assertObjectHasAttribute('cmid', $data[1]);
        $this->assertEquals($jqshow->cmid, $data[1]->cmid);
        $this->assertObjectHasAttribute('questionid', $data[1]);
        $this->assertEquals($nq->id, $data[1]->questionid);
        $this->assertObjectHasAttribute('qtype', $data[1]);
        $this->assertEquals(questions::NUMERICAL, $data[1]->qtype);
        $this->assertObjectHasAttribute('sessionid', $data[1]);
        $this->assertEquals($createdsid, $data[1]->sessionid);
        $this->assertObjectHasAttribute('jqshowid', $data[1]);
        $this->assertEquals($jqshow->id, $data[1]->jqshowid);
        // Truefalse test.
        $this->assertIsObject($data[2]);
        $this->assertObjectHasAttribute('cmid', $data[2]);
        $this->assertEquals($jqshow->cmid, $data[2]->cmid);
        $this->assertObjectHasAttribute('questionid', $data[2]);
        $this->assertEquals($tfq->id, $data[2]->questionid);
        $this->assertObjectHasAttribute('qtype', $data[2]);
        $this->assertEquals(questions::TRUE_FALSE, $data[2]->qtype);
        $this->assertObjectHasAttribute('sessionid', $data[2]);
        $this->assertEquals($createdsid, $data[2]->sessionid);
        $this->assertObjectHasAttribute('jqshowid', $data[2]);
        $this->assertEquals($jqshow->id, $data[2]->jqshowid);
        // MULTICHOICE test.
        $this->assertIsObject($data[3]);
        $this->assertObjectHasAttribute('cmid', $data[3]);
        $this->assertEquals($jqshow->cmid, $data[3]->cmid);
        $this->assertObjectHasAttribute('questionid', $data[3]);
        $this->assertEquals($mcq->id, $data[3]->questionid);
        $this->assertObjectHasAttribute('qtype', $data[3]);
        $this->assertEquals(questions::MULTICHOICE, $data[3]->qtype);
        $this->assertObjectHasAttribute('sessionid', $data[3]);
        $this->assertEquals($createdsid, $data[3]->sessionid);
        $this->assertObjectHasAttribute('jqshowid', $data[3]);
        $this->assertEquals($jqshow->id, $data[3]->jqshowid);
        // DDWTOS test.
        $this->assertIsObject($data[4]);
        $this->assertObjectHasAttribute('cmid', $data[4]);
        $this->assertEquals($jqshow->cmid, $data[4]->cmid);
        $this->assertObjectHasAttribute('questionid', $data[4]);
        $this->assertEquals($ddwtosq->id, $data[4]->questionid);
        $this->assertObjectHasAttribute('qtype', $data[4]);
        $this->assertEquals(questions::DDWTOS, $data[4]->qtype);
        $this->assertObjectHasAttribute('sessionid', $data[4]);
        $this->assertEquals($createdsid, $data[4]->sessionid);
        $this->assertObjectHasAttribute('jqshowid', $data[4]);
        $this->assertEquals($jqshow->id, $data[4]->jqshowid);
        // DESCRIPTION test.
        $this->assertIsObject($data[5]);
        $this->assertObjectHasAttribute('cmid', $data[5]);
        $this->assertEquals($jqshow->cmid, $data[5]->cmid);
        $this->assertObjectHasAttribute('questionid', $data[5]);
        $this->assertEquals($dq->id, $data[5]->questionid);
        $this->assertObjectHasAttribute('qtype', $data[5]);
        $this->assertEquals(questions::DESCRIPTION, $data[5]->qtype);
        $this->assertObjectHasAttribute('sessionid', $data[5]);
        $this->assertEquals($createdsid, $data[5]->sessionid);
        $this->assertObjectHasAttribute('jqshowid', $data[5]);
        $this->assertEquals($jqshow->id, $data[5]->jqshowid);
    }
}
