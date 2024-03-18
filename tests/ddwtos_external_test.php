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
use mod_kuet\models\ddwtos;
use mod_kuet\models\sessions;
use mod_kuet\models\questions;
/**
 * Drag and drop question type test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * DDrag and drop question type test class
 */
class ddwtos_external_test extends advanced_testcase {

    /**
     * Drag and drop question type test
     *
     * @return void
     * @throws JsonException
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function test_ddwtos() :void {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');

        // Only a user with capability can add questions.
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        $student1 = self::getDataGenerator()->create_and_enrol($course);
        $student2 = self::getDataGenerator()->create_and_enrol($course);
        $student3 = self::getDataGenerator()->create_and_enrol($course);
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
        $ddwtosq = $questiongenerator->create_question(questions::DDWTOS, null, array('category' => $cat->id));

        // Add questions to a session.
        $questions = [
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DDWTOS]
        ];
        $generator->add_questions_to_session($questions);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $createdsid);

        $qbddwtos = question_bank::load_question($ddwtosq->id);
        $jmcq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $qbddwtos->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DDWTOS]
        );
        $correctanswers = [];
        $correctchoices = [];
        $incorrectanswers = [];
        foreach ($qbddwtos->rightchoices as $key => $rightchoice) {
            $correctanswers['p'.$key] = $rightchoice;
            $correctchoices[$key] = $rightchoice;
        }
        foreach ($qbddwtos->choices as $key => $choice) {
            foreach ($choice as $choicekey => $option) {
                if ($choicekey != $correctchoices[$key]) {
                    $incorrectanswers['p'.$key] = $choicekey;
                }
            }
        }
        $partiallycorrectanswers = $correctanswers;
        $partiallycorrectanswers['p1'] = $incorrectanswers['p1'];
        $statmentfeedback = questions::get_text(
            $kuet->cmid, $qbddwtos->generalfeedback, 1, $qbddwtos->id, $qbddwtos, 'generalfeedback'
        );
        $correctanswersfeedback = questions::get_text(
            $kuet->cmid,
            $qbddwtos->correctfeedback,
            $qbddwtos->correctfeedbackformat,
            $qbddwtos->id,
            $qbddwtos,
            'feedback'
        );
        $partialanswersfeedback = questions::get_text(
            $kuet->cmid,
            $qbddwtos->partiallycorrectfeedback,
            $qbddwtos->partiallycorrectfeedbackformat,
            $qbddwtos->id,
            $qbddwtos,
            'feedback'
        );
        $incorrectanswersfeedback = questions::get_text(
            $kuet->cmid,
            $qbddwtos->incorrectfeedback,
            $qbddwtos->incorrectfeedbackformat,
            $qbddwtos->id,
            $qbddwtos,
            'feedback'
        );
        $hasfeedback = !empty($statmentfeedback) || !empty($correctanswersfeedback);
        $hasfeedback2 = !empty($statmentfeedback) || !empty($partialanswersfeedback);
        $hasfeedback3 = !empty($statmentfeedback) || !empty($incorrectanswersfeedback);

        // User 1 answers a correct answer.
        self::setUser($student1);
        $data1 = \mod_kuet\external\ddwtos_external::ddwtos($createdsid, $kuet->id,
            $kuet->cmid, $ddwtosq->id, $jmcq->get('id'), 10, false, json_encode($correctanswers));
        $this->assertIsArray($data1);
        $this->assertArrayHasKey('reply_status', $data1);
        $this->assertArrayHasKey('result', $data1);
        $this->assertArrayHasKey('hasfeedbacks', $data1);
        $this->assertArrayHasKey('statment_feedback', $data1);
        $this->assertArrayHasKey('answer_feedback', $data1);
        $this->assertArrayHasKey('question_text_feedback', $data1);
        $this->assertArrayHasKey('programmedmode', $data1);
        $this->assertArrayHasKey('preview', $data1);
        $this->assertTrue($data1['reply_status']);
        $this->assertEquals(questions::SUCCESS, $data1['result']);
        $this->assertEquals($hasfeedback, $data1['hasfeedbacks']);
        $this->assertEquals($statmentfeedback, $data1['statment_feedback']);
        $this->assertEquals($correctanswersfeedback, $data1['answer_feedback']);
        $this->assertFalse($data1['programmedmode']);
        $this->assertFalse($data1['preview']);

        // User 2 answers a partially correct answer.
        self::setUser($student2);
        $data2 = \mod_kuet\external\ddwtos_external::ddwtos($createdsid, $kuet->id,
            $kuet->cmid, $ddwtosq->id, $jmcq->get('id'), 10, false, json_encode($partiallycorrectanswers));
        $this->assertIsArray($data2);
        $this->assertArrayHasKey('reply_status', $data2);
        $this->assertArrayHasKey('result', $data2);
        $this->assertArrayHasKey('hasfeedbacks', $data2);
        $this->assertArrayHasKey('statment_feedback', $data2);
        $this->assertArrayHasKey('answer_feedback', $data2);
        $this->assertArrayHasKey('question_text_feedback', $data2);
        $this->assertArrayHasKey('programmedmode', $data2);
        $this->assertArrayHasKey('preview', $data2);
        $this->assertTrue($data2['reply_status']);
        $this->assertEquals(questions::PARTIALLY, $data2['result']);
        $this->assertEquals($hasfeedback2, $data2['hasfeedbacks']);
        $this->assertEquals($statmentfeedback, $data2['statment_feedback']);
        $this->assertEquals($partialanswersfeedback, $data2['answer_feedback']);
        $this->assertFalse($data2['programmedmode']);
        $this->assertFalse($data2['preview']);

        // User 3 answers incorrectly.
        self::setUser($student3);
        $data3 = \mod_kuet\external\ddwtos_external::ddwtos($createdsid, $kuet->id,
            $kuet->cmid, $ddwtosq->id, $jmcq->get('id'), 10, false, json_encode($incorrectanswers));
        $this->assertIsArray($data3);
        $this->assertArrayHasKey('reply_status', $data3);
        $this->assertArrayHasKey('result', $data3);
        $this->assertArrayHasKey('hasfeedbacks', $data3);
        $this->assertArrayHasKey('statment_feedback', $data3);
        $this->assertArrayHasKey('answer_feedback', $data3);
        $this->assertArrayHasKey('question_text_feedback', $data3);
        $this->assertArrayHasKey('programmedmode', $data3);
        $this->assertArrayHasKey('preview', $data3);
        $this->assertTrue($data3['reply_status']);
        $this->assertEquals(questions::FAILURE, $data3['result']);
        $this->assertEquals($hasfeedback3, $data3['hasfeedbacks']);
        $this->assertEquals($statmentfeedback, $data3['statment_feedback']);
        $this->assertEquals($incorrectanswersfeedback, $data3['answer_feedback']);
        $this->assertFalse($data3['programmedmode']);
        $this->assertFalse($data3['preview']);
    }
}