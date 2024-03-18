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
 * Session finished service test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @category   test
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Session finished service test
 */
class sessionfinished_external_test extends advanced_testcase {
    /**
     * Session finished service test
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function test_sessionfinished() {
        global $OUTPUT;
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');

        // Only a user with capability can add questions.
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);
        // Create session.
        $sessionmock1 = [
            'name' => 'Session Test',
            'kuetid' => $kuet->id,
            'anonymousanswer' => 0,
            'sessionmode' => \mod_kuet\models\sessions::INACTIVE_PROGRAMMED,
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
            'status' => \mod_kuet\models\sessions::SESSION_FINISHED,
            'sessionid' => 0,
            'submitbutton' => 0,
            'showgraderanking' => 0,
        ];
        $sessionmock1['id'] = $generator->create_session($kuet, (object) $sessionmock1);

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
            ['questionid' => $saq->id, 'sessionid' => $sessionmock1['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $sessionmock1['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::NUMERICAL],
            ['questionid' => $tfq->id, 'sessionid' => $sessionmock1['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::TRUE_FALSE],
            ['questionid' => $mcq->id, 'sessionid' => $sessionmock1['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::MULTICHOICE],
            ['questionid' => $ddwtosq->id, 'sessionid' => $sessionmock1['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::DDWTOS],
            ['questionid' => $dq->id, 'sessionid' => $sessionmock1['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::DESCRIPTION],
        ];
        $generator->add_questions_to_session($questions);
        $data = \mod_kuet\external\sessionfinished_external::sessionfinished($kuet->id, $kuet->cmid);
        $sessionmock2 = $sessionmock1;
        $sessionmock2['id'] = 0;
        $sessionmock2['sessionmode'] = \mod_kuet\models\sessions::PODIUM_MANUAL;
        $sessionmock2['status'] = \mod_kuet\models\sessions::SESSION_ACTIVE;
        $sessionmock2['automaticstart'] = 1;
        $sessionmock2['startdate'] = time();
        $sessionmock2['enddate'] = mktime(date("h"), date("i"), date("s"), date("m"),
            date("d") + 1, date("Y"));
        $sessionmock2['id'] = $generator->create_session($kuet, (object) $sessionmock2);

        // Add questions to a session.
        $questions = [
            ['questionid' => $saq->id, 'sessionid' => $sessionmock2['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $sessionmock2['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::NUMERICAL],
            ['questionid' => $tfq->id, 'sessionid' => $sessionmock2['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::TRUE_FALSE],
            ['questionid' => $mcq->id, 'sessionid' => $sessionmock2['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::MULTICHOICE],
            ['questionid' => $ddwtosq->id, 'sessionid' => $sessionmock2['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::DDWTOS],
            ['questionid' => $dq->id, 'sessionid' => $sessionmock2['id'], 'kuetid' => $kuet->id,
                'qtype' => questions::DESCRIPTION],
        ];
        $generator->add_questions_to_session($questions);
        $data2 = \mod_kuet\external\sessionfinished_external::sessionfinished($kuet->id, $kuet->cmid);

        // Test session 1.
        $this->assertIsArray($data);
        $this->assertArrayHasKey('sessionclosedimage', $data);
        $this->assertArrayHasKey('hasnextsession', $data);
        $this->assertArrayHasKey('nextsessiontime', $data);
        $this->assertArrayHasKey('urlreports', $data);
        $this->assertEquals($OUTPUT->image_url('f/error', 'mod_kuet')->out(false), $data['sessionclosedimage']);
        $this->assertEquals(0, $data['hasnextsession']);
        $this->assertEquals('', $data['nextsessiontime']);
        $this->assertEquals((new moodle_url('/mod/kuet/reports.php', ['cmid' => $kuet->cmid]))->out(false),
            $data['urlreports']);
        // Test session 2.
        $this->assertIsArray($data);
        $this->assertArrayHasKey('sessionclosedimage', $data2);
        $this->assertArrayHasKey('hasnextsession', $data2);
        $this->assertArrayHasKey('nextsessiontime', $data2);
        $this->assertArrayHasKey('urlreports', $data2);
        $this->assertEquals($OUTPUT->image_url('f/error', 'mod_kuet')->out(false), $data2['sessionclosedimage']);
        $date = userdate($sessionmock2['startdate'], get_string('strftimedatetimeshort', 'core_langconfig'));
        $this->assertTrue($data2['hasnextsession']);
        $this->assertEquals($date, $data2['nextsessiontime']);
        $this->assertEquals((new moodle_url('/mod/kuet/reports.php', ['cmid' => $kuet->cmid]))->out(false),
            $data2['urlreports']);
    }
}
