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
 * Short answer question type test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Short answer question type test class
 *
 * @covers \mod_kuet\external\shortanswer_external
 */
final class shortanswer_external_test extends \advanced_testcase {
    /**
     * Build a session holding one short answer question
     *
     * @return array [$kuet, $sessionid, $question, $kid]
     */
    private function make_session(): array {
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
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
        $question = $questiongenerator->create_question(questions::SHORTANSWER, null, ['category' => $cat->id]);

        $generator->add_questions_to_session([
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id,
                'qtype' => questions::SHORTANSWER],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);
        $kid = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id,
                'qtype' => questions::SHORTANSWER]
        )->get('id');

        return [$kuet, $sid, $question, $kid];
    }

    /**
     * The lower half of the feedback box carries the feedback of the answer the response fell on
     *
     * It used to carry the general feedback, which the upper half already shows, so the
     * participant was told the same thing twice and never saw the feedback the teacher
     * wrote on the answer they actually gave (KUET-052).
     *
     * @covers \mod_kuet\external\shortanswer_external::shortanswer
     * @return void
     */
    public function test_the_feedback_belongs_to_the_answer_that_was_given(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$kuet, $sid, $question, $kid] = $this->make_session();

        $DB->set_field('question', 'generalfeedback', '<p>General</p>', ['id' => $question->id]);
        $answers = $DB->get_records('question_answers', ['question' => $question->id], 'fraction DESC, id ASC');
        $right = reset($answers);
        $wrong = end($answers);
        $DB->set_field('question_answers', 'feedback', '<p>Right one</p>', ['id' => $right->id]);
        $DB->set_field('question_answers', 'feedback', '<p>Wrong one</p>', ['id' => $wrong->id]);

        $student = self::getDataGenerator()->create_and_enrol(
            get_course($kuet->course),
            'student'
        );
        self::setUser($student);

        $data = \mod_kuet\external\shortanswer_external::shortanswer(
            $right->answer,
            $sid,
            $kuet->id,
            $kuet->cmid,
            $question->id,
            $kid,
            10,
            false
        );

        $this->assertTrue($data['reply_status']);
        $this->assertStringContainsString('General', $data['statment_feedback']);
        $this->assertStringContainsString(
            'Right one',
            $data['answer_feedback'],
            'The lower half does not carry the feedback of the answer that was given'
        );
        $this->assertStringNotContainsString(
            'General',
            $data['answer_feedback'],
            'The general feedback is repeated in both halves of the box'
        );
    }

    /**
     * An answer with no feedback of its own leaves the lower half empty
     *
     * @covers \mod_kuet\external\shortanswer_external::shortanswer
     * @return void
     */
    public function test_an_answer_with_no_feedback_leaves_the_lower_half_empty(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$kuet, $sid, $question, $kid] = $this->make_session();

        $DB->set_field('question', 'generalfeedback', '', ['id' => $question->id]);
        $DB->set_field('question_answers', 'feedback', '', ['question' => $question->id]);

        $answers = $DB->get_records('question_answers', ['question' => $question->id], 'fraction DESC, id ASC');
        $right = reset($answers);

        $student = self::getDataGenerator()->create_and_enrol(
            get_course($kuet->course),
            'student'
        );
        self::setUser($student);

        $data = \mod_kuet\external\shortanswer_external::shortanswer(
            $right->answer,
            $sid,
            $kuet->id,
            $kuet->cmid,
            $question->id,
            $kid,
            10,
            false
        );

        $this->assertTrue($data['reply_status']);
        $this->assertSame('', $data['statment_feedback']);
        $this->assertSame('', $data['answer_feedback']);
        $this->assertFalse($data['hasfeedbacks']);
    }
}
