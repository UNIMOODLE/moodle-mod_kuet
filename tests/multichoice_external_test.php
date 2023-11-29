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
class multichoice_external_test extends advanced_testcase {
    public function test_multichoice() {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['jqshowid'] = $jqshow->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');

        // Only a user with capability can add questions.
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        $student1 = self::getDataGenerator()->create_and_enrol($course);
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
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, array('category' => $cat->id));

        // Add questions to a session.
        $questions = [
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::MULTICHOICE]
        ];
        $generator->add_questions_to_session($questions);
        \mod_kuet\external\startsession_external::startsession($jqshow->cmid, $createdsid);

        $qbmc = question_bank::load_question($mcq->id);
        $jmcq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::MULTICHOICE]
        );

        $correctanswers = [];
        $correctanswersfeedback = '';
        $incorrectanswers = [];
        $incorrectanswersfeedback = '';
        foreach ($qbmc->answers as $key => $answer) {
            if ($answer->fraction !== '0.0000000' && strpos($answer->fraction, '-') !== 0) {
                $correctanswers[] = $answer->id;
                if (!empty($answer->feedback) ) {
                    $correctanswersfeedback .= questions::get_text(
                            $jqshow->cmid, $answer->feedback, 1, $answer->id, $qbmc, 'answerfeedback'
                        ) . '<br>';
                }
            } else {
                $incorrectanswers[] = $answer->id;
                if (!empty($answer->feedback) ) {
                    $incorrectanswersfeedback .= questions::get_text(
                            $jqshow->cmid, $answer->feedback, 1, $answer->id, $qbmc, 'answerfeedback'
                        ) . '<br>';
                }
            }
        }
        $statmentfeedback = questions::get_text(
            $jqshow->cmid, $qbmc->generalfeedback, 1, $qbmc->id, $qbmc, 'generalfeedback'
        );
        $hasfeedback = !empty($statmentfeedback) || !empty($correctanswersfeedback);

        // User 1 answers a correct answer.
        self::setUser($student1);
        $user1answerids = implode(',', $correctanswers);
        $data1 = \mod_kuet\external\multichoice_external::multichoice($user1answerids, $createdsid, $jqshow->id,
            $jqshow->cmid, $mcq->id, $jmcq->get('id'), 10, false);
        $this->assertIsArray($data1);
        $this->assertArrayHasKey('reply_status', $data1);
        $this->assertArrayHasKey('hasfeedbacks', $data1);
        $this->assertArrayHasKey('statment_feedback', $data1);
        $this->assertArrayHasKey('answer_feedback', $data1);
        $this->assertArrayHasKey('correct_answers', $data1);
        $this->assertArrayHasKey('programmedmode', $data1);
        $this->assertArrayHasKey('preview', $data1);
        $this->assertTrue($data1['reply_status']);
        $this->assertEquals($hasfeedback, $data1['hasfeedbacks']);
        $this->assertEquals($statmentfeedback, $data1['statment_feedback']);
//        $this->assertEquals($correctanswersfeedback, $data1['answer_feedback']); // TODO: it is not correct!
        $this->assertEquals(implode(',', $correctanswers), $data1['correct_answers']);
        $this->assertFalse($data1['programmedmode']);
        $this->assertFalse($data1['preview']);

        // User 2 answers an incorrect answer.
        self::setUser($student2);
        $user2answerids = implode(',', $incorrectanswers);
        $data2 = \mod_kuet\external\multichoice_external::multichoice($user2answerids, $createdsid, $jqshow->id,
            $jqshow->cmid, $mcq->id, $jmcq->get('id'), 10, false);
        $this->assertIsArray($data2);
        $this->assertArrayHasKey('reply_status', $data2);
        $this->assertArrayHasKey('hasfeedbacks', $data2);
        $this->assertArrayHasKey('statment_feedback', $data2);
        $this->assertArrayHasKey('answer_feedback', $data2);
        $this->assertArrayHasKey('correct_answers', $data2);
        $this->assertArrayHasKey('programmedmode', $data2);
        $this->assertArrayHasKey('preview', $data2);
        $this->assertTrue($data2['reply_status']);
        $this->assertEquals($hasfeedback, $data2['hasfeedbacks']);
        $this->assertEquals($statmentfeedback, $data2['statment_feedback']);
//        $this->assertEquals($incorrectanswersfeedback, $data2['answer_feedback']); // TODO: it is not correct!
        $this->assertEquals(implode(',', $correctanswers), $data2['correct_answers']);
        $this->assertFalse($data2['programmedmode']);
        $this->assertFalse($data2['preview']);
    }
}
