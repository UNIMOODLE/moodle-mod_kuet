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
use mod_kuet\models\questions;
/**
 * Get question service test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @category   test
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get question service test class
 */
class getquestion_external_test extends \advanced_testcase {
    /**
     * Get question service test
     *
     * @return void
     * @throws \JsonException
     * @throws \ReflectionException
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_getquestion() {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');

        // Only a user with capability can add questions.
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
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
            'timemode' => 0,
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
        $saq = $questiongenerator->create_question(questions::SHORTANSWER, null, ['category' => $cat->id]);
        $nq = $questiongenerator->create_question(questions::NUMERICAL, null, ['category' => $cat->id]);
        $tfq = $questiongenerator->create_question(questions::TRUE_FALSE, null, ['category' => $cat->id]);
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, ['category' => $cat->id]);
        $cq = $questiongenerator->create_question(questions::CALCULATED, null, ['category' => $cat->id]);
        $ddwtosq = $questiongenerator->create_question(questions::DDWTOS, null, ['category' => $cat->id]);
        $dq = $questiongenerator->create_question(questions::DESCRIPTION, null, ['category' => $cat->id]);

        // Add question.
        \mod_kuet\external\addquestions_external::add_questions([
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL],
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE],
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
            ['questionid' => $cq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::CALCULATED],
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DDWTOS],
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DESCRIPTION],
        ]);

        // Shortanswer.
        $jsaq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::SHORTANSWER]);
        $shortanswer = \mod_kuet\external\getquestion_external::getquestion($kuet->cmid, $createdsid, $jsaq->get('id'));

        // Numerical.
        $jnq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL]);
        $numerical = \mod_kuet\external\getquestion_external::getquestion($kuet->cmid, $createdsid, $jnq->get('id'));

        // Truefalse.
        $jtfq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE]);
        $truefalse = \mod_kuet\external\getquestion_external::getquestion($kuet->cmid, $createdsid, $jtfq->get('id'));

        // Multichoice.
        $jmcq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]);
        $multichoice = \mod_kuet\external\getquestion_external::getquestion($kuet->cmid, $createdsid, $jmcq->get('id'));

        // Drag and drop text.
        $jddwtosq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DDWTOS]);
        $ddwto = \mod_kuet\external\getquestion_external::getquestion($kuet->cmid, $createdsid, $jddwtosq->get('id'));

        // Description.
        $jdq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DESCRIPTION]);
        $description = \mod_kuet\external\getquestion_external::getquestion($kuet->cmid, $createdsid, $jdq->get('id'));

        $this->assertIsArray($shortanswer);
        $this->assertArrayHasKey('cmid', $shortanswer);
        $this->assertEquals($kuet->cmid, $shortanswer['cmid']);
        $this->assertArrayHasKey('questionid', $shortanswer);
        $this->assertEquals($saq->id, $shortanswer['questionid']);
        $this->assertArrayHasKey('kid', $shortanswer);
        $this->assertEquals($jsaq->get('id'), $shortanswer['kid']);
        $this->assertArrayHasKey('qtype', $shortanswer);
        $this->assertEquals(questions::SHORTANSWER, $shortanswer['qtype']);

        $this->assertIsArray($numerical);
        $this->assertArrayHasKey('cmid', $numerical);
        $this->assertEquals($kuet->cmid, $numerical['cmid']);
        $this->assertArrayHasKey('questionid', $numerical);
        $this->assertEquals($nq->id, $numerical['questionid']);
        $this->assertArrayHasKey('kid', $numerical);
        $this->assertEquals($jnq->get('id'), $numerical['kid']);
        $this->assertArrayHasKey('qtype', $numerical);
        $this->assertEquals(questions::NUMERICAL, $numerical['qtype']);

        $this->assertIsArray($truefalse);
        $this->assertArrayHasKey('cmid', $truefalse);
        $this->assertEquals($kuet->cmid, $truefalse['cmid']);
        $this->assertArrayHasKey('questionid', $truefalse);
        $this->assertEquals($tfq->id, $truefalse['questionid']);
        $this->assertArrayHasKey('kid', $truefalse);
        $this->assertEquals($jtfq->get('id'), $truefalse['kid']);
        $this->assertArrayHasKey('qtype', $truefalse);
        $this->assertEquals(questions::TRUE_FALSE, $truefalse['qtype']);

        $this->assertIsArray($multichoice);
        $this->assertArrayHasKey('cmid', $multichoice);
        $this->assertEquals($kuet->cmid, $multichoice['cmid']);
        $this->assertArrayHasKey('questionid', $multichoice);
        $this->assertEquals($mcq->id, $multichoice['questionid']);
        $this->assertArrayHasKey('kid', $multichoice);
        $this->assertEquals($jmcq->get('id'), $multichoice['kid']);
        $this->assertArrayHasKey('qtype', $multichoice);
        $this->assertEquals(questions::MULTICHOICE, $multichoice['qtype']);

        $this->assertIsArray($ddwto);
        $this->assertArrayHasKey('cmid', $ddwto);
        $this->assertEquals($kuet->cmid, $ddwto['cmid']);
        $this->assertArrayHasKey('questionid', $ddwto);
        $this->assertEquals($ddwtosq->id, $ddwto['questionid']);
        $this->assertArrayHasKey('kid', $ddwto);
        $this->assertEquals($jddwtosq->get('id'), $ddwto['kid']);
        $this->assertArrayHasKey('qtype', $ddwto);
        $this->assertEquals(questions::DDWTOS, $ddwto['qtype']);

        $this->assertIsArray($description);
        $this->assertArrayHasKey('cmid', $description);
        $this->assertEquals($kuet->cmid, $description['cmid']);
        $this->assertArrayHasKey('questionid', $description);
        $this->assertEquals($dq->id, $description['questionid']);
        $this->assertArrayHasKey('kid', $description);
        $this->assertEquals($jdq->get('id'), $description['kid']);
        $this->assertArrayHasKey('qtype', $description);
        $this->assertEquals(questions::DESCRIPTION, $description['qtype']);
    }
}
