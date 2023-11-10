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
namespace mod_jqshow;
use \mod_jqshow\models\questions;
/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @category   test
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class getquestion_external_test extends \advanced_testcase {

    public function test_getquestion() {
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
//        $mq = $questiongenerator->create_question(questions::MATCH, null, array('category' => $cat->id));
        $cq = $questiongenerator->create_question(questions::CALCULATED, null, array('category' => $cat->id));
        $ddwtosq = $questiongenerator->create_question(questions::DDWTOS, null, array('category' => $cat->id));
        $dq = $questiongenerator->create_question(questions::DESCRIPTION, null, array('category' => $cat->id));

        // Add question.
        \mod_jqshow\external\addquestions_external::add_questions([
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::NUMERICAL],
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::TRUE_FALSE],
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::MULTICHOICE],
//            ['questionid' => $mq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::MATCH],
            ['questionid' => $cq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::CALCULATED],
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DDWTOS],
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DESCRIPTION],
        ]);

        // Shortanswer.
        $jsaq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::SHORTANSWER]);
        $shortanswer = \mod_jqshow\external\getquestion_external::getquestion($jqshow->cmid, $createdsid, $jsaq->get('id'));

        // Numerical.
        $jnq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::NUMERICAL]);
        $numerical = \mod_jqshow\external\getquestion_external::getquestion($jqshow->cmid, $createdsid, $jnq->get('id'));

        // Truefalse.
        $jtfq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::TRUE_FALSE]);
        $truefalse = \mod_jqshow\external\getquestion_external::getquestion($jqshow->cmid, $createdsid, $jtfq->get('id'));

        // Multichoice.
        $jmcq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::MULTICHOICE]);
        $multichoice = \mod_jqshow\external\getquestion_external::getquestion($jqshow->cmid, $createdsid, $jmcq->get('id'));

//        // Match.
//        $jmq = \mod_jqshow\persistents\jqshow_questions::get_record(
//            ['questionid' => $mq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::MATCH]);
//        $match = \mod_jqshow\external\getquestion_external::getquestion($jqshow->cmid, $createdsid, $jmq->get('id'));

        // Drag and drop text.
        $jddwtosq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DDWTOS]);
        $ddwto = \mod_jqshow\external\getquestion_external::getquestion($jqshow->cmid, $createdsid, $jddwtosq->get('id'));

        // Description.
        $jdq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DESCRIPTION]);
        $description = \mod_jqshow\external\getquestion_external::getquestion($jqshow->cmid, $createdsid, $jdq->get('id'));

        $this->assertIsArray($shortanswer);
        $this->assertArrayHasKey('cmid', $shortanswer);
        $this->assertEquals($jqshow->cmid, $shortanswer['cmid']);
        $this->assertArrayHasKey('questionid', $shortanswer);
        $this->assertEquals($saq->id, $shortanswer['questionid']);
        $this->assertArrayHasKey('jqid', $shortanswer);
        $this->assertEquals($jsaq->get('id'), $shortanswer['jqid']);
        $this->assertArrayHasKey('qtype', $shortanswer);
        $this->assertEquals(questions::SHORTANSWER, $shortanswer['qtype']);

        $this->assertIsArray($numerical);
        $this->assertArrayHasKey('cmid', $numerical);
        $this->assertEquals($jqshow->cmid, $numerical['cmid']);
        $this->assertArrayHasKey('questionid', $numerical);
        $this->assertEquals($nq->id, $numerical['questionid']);
        $this->assertArrayHasKey('jqid', $numerical);
        $this->assertEquals($jnq->get('id'), $numerical['jqid']);
        $this->assertArrayHasKey('qtype', $numerical);
        $this->assertEquals(questions::NUMERICAL, $numerical['qtype']);

        $this->assertIsArray($truefalse);
        $this->assertArrayHasKey('cmid', $truefalse);
        $this->assertEquals($jqshow->cmid, $truefalse['cmid']);
        $this->assertArrayHasKey('questionid', $truefalse);
        $this->assertEquals($tfq->id, $truefalse['questionid']);
        $this->assertArrayHasKey('jqid', $truefalse);
        $this->assertEquals($jtfq->get('id'), $truefalse['jqid']);
        $this->assertArrayHasKey('qtype', $truefalse);
        $this->assertEquals(questions::TRUE_FALSE, $truefalse['qtype']);

        $this->assertIsArray($multichoice);
        $this->assertArrayHasKey('cmid', $multichoice);
        $this->assertEquals($jqshow->cmid, $multichoice['cmid']);
        $this->assertArrayHasKey('questionid', $multichoice);
        $this->assertEquals($mcq->id, $multichoice['questionid']);
        $this->assertArrayHasKey('jqid', $multichoice);
        $this->assertEquals($jmcq->get('id'), $multichoice['jqid']);
        $this->assertArrayHasKey('qtype', $multichoice);
        $this->assertEquals(questions::MULTICHOICE, $multichoice['qtype']);

//        $this->assertIsArray($match);
//        $this->assertArrayHasKey('cmid', $match);
//        $this->assertEquals($jqshow->cmid, $match['cmid']);
//        $this->assertArrayHasKey('questionid', $match);
//        $this->assertEquals($mq->id, $match['questionid']);
//        $this->assertArrayHasKey('jqid', $match);
//        $this->assertEquals($jmq->get('id'), $match['jqid']);
//        $this->assertArrayHasKey('qtype', $match);
//        $this->assertEquals(questions::MATCH, $multichoice['qtype']);

        $this->assertIsArray($ddwto);
        $this->assertArrayHasKey('cmid', $ddwto);
        $this->assertEquals($jqshow->cmid, $ddwto['cmid']);
        $this->assertArrayHasKey('questionid', $ddwto);
        $this->assertEquals($ddwtosq->id, $ddwto['questionid']);
        $this->assertArrayHasKey('jqid', $ddwto);
        $this->assertEquals($jddwtosq->get('id'), $ddwto['jqid']);
        $this->assertArrayHasKey('qtype', $ddwto);
        $this->assertEquals(questions::DDWTOS, $ddwto['qtype']);

        $this->assertIsArray($description);
        $this->assertArrayHasKey('cmid', $description);
        $this->assertEquals($jqshow->cmid, $description['cmid']);
        $this->assertArrayHasKey('questionid', $description);
        $this->assertEquals($dq->id, $description['questionid']);
        $this->assertArrayHasKey('jqid', $description);
        $this->assertEquals($jdq->get('id'), $description['jqid']);
        $this->assertArrayHasKey('qtype', $description);
        $this->assertEquals(questions::DESCRIPTION, $description['qtype']);
    }
}
