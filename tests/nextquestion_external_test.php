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
use mod_kuet\models\questions;

/**
 * Next question service test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Next question service test class
 */
class nextquestion_external_test extends advanced_testcase {
    /**
     * Next question service test
     *
     * @return void
     * @throws JsonException
     * @throws ReflectionException
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function test_nextquestion() {
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
            'showgraderanking' => 0,
        ];
        $createdsid = $generator->create_session($kuet, (object) $sessionmock);

        // Create questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question(questions::SHORTANSWER, null, array('category' => $cat->id));
        $nq = $questiongenerator->create_question(questions::NUMERICAL, null, array('category' => $cat->id));
        $tfq = $questiongenerator->create_question(questions::TRUE_FALSE, null, array('category' => $cat->id));
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, array('category' => $cat->id));
        $cq = $questiongenerator->create_question(questions::CALCULATED, null, array('category' => $cat->id));
        $ddwtosq = $questiongenerator->create_question(questions::DDWTOS, null, array('category' => $cat->id));
        $dq = $questiongenerator->create_question(questions::DESCRIPTION, null, array('category' => $cat->id));

        // Add question.
        \mod_kuet\external\addquestions_external::add_questions([
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL],
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE],
            ['questionid' => $cq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::CALCULATED],
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DDWTOS],
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DESCRIPTION],
        ]);

        $jmcq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $mcq->id , 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]);
        $jtfq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $tfq->id , 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE]);
        $shouldbetruefalse = \mod_kuet\external\nextquestion_external::nextquestion($kuet->cmid, $createdsid, $jmcq->get('id'));

        $this->assertIsArray(  $shouldbetruefalse);
        $this->assertArrayHasKey('cmid',   $shouldbetruefalse);
        $this->assertEquals($kuet->cmid,   $shouldbetruefalse['cmid']);
        $this->assertArrayHasKey('questionid',   $shouldbetruefalse);
        $this->assertEquals($tfq->id,   $shouldbetruefalse['questionid']);
        $this->assertArrayHasKey('kid',   $shouldbetruefalse);
        $this->assertEquals($jtfq->get('id'),   $shouldbetruefalse['kid']);
        $this->assertArrayHasKey('qtype',   $shouldbetruefalse);
        $this->assertEquals(questions::TRUE_FALSE, $shouldbetruefalse['qtype']);
    }
}
