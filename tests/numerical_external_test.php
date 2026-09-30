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
use mod_kuet\models\sessions;

/**
 * Numerical question type test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Numerical question type test class
 *
 * @covers \mod_kuet\models\numerical
 */
final class numerical_external_test extends \advanced_testcase {
    /**
     * Only the answer that scores the whole mark is offered as the right one.
     *
     * Regression for KUET-037: every answer of the question was listed, including the
     * ones that only carry a specific feedback and the catch-all '*', so a participant
     * who missed was shown "3.14 / 3.142 / 3.1 / 3 / *" as the right answer.
     *
     * @return void
     */
    public function test_the_answer_help_lists_only_the_answers_that_score(): void {
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_kuet');
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = self::getDataGenerator()->create_and_enrol($course);
        self::setUser($teacher);

        $sid = $generator->create_session($kuet, (object) $this->sessionmock($kuet));

        // Core's 'pi' question scores 3.14 with the whole mark and gives nothing to 3.142,
        // 3.1, 3 and the catch-all '*', which are there for their own feedback.
        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question(questions::NUMERICAL, null, ['category' => $cat->id]);
        $generator->add_questions_to_session([
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);
        $kid = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL]
        )->get('id');

        self::setUser($student);
        $data = \mod_kuet\external\numerical_external::numerical(
            '3',
            '',
            '',
            $sid,
            $kuet->id,
            $kuet->cmid,
            $question->id,
            $kid,
            10,
            false
        );

        $this->assertSame('3.14', $data['possibleanswers']);
    }

    /**
     * The feedback shown is the one of the answer the mark came from.
     *
     * Regression for KUET-039: the answer was matched again with the multiplier the client
     * sends, which is the one stored for the unit, while core works with its inverse. The
     * two picked different answers, so a response graded wrong could be shown the feedback
     * of the right one - "Correcto." under a mark of zero.
     *
     * @return void
     */
    public function test_the_feedback_is_the_one_of_the_answer_that_was_graded(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_kuet');
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = self::getDataGenerator()->create_and_enrol($course);
        self::setUser($teacher);

        $sid = $generator->create_session($kuet, (object) $this->sessionmock($kuet));

        // Core's 'pi' question, given units so that the two conventions diverge: the stored
        // multiplier of 'cm' is 100 - a hundred of them to one 'm' - and core scales the
        // response by its inverse.
        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question(questions::NUMERICAL, null, ['category' => $cat->id]);
        $DB->insert_record(
            'question_numerical_units',
            ['question' => $question->id, 'multiplier' => 1, 'unit' => 'm']
        );
        $DB->insert_record(
            'question_numerical_units',
            ['question' => $question->id, 'multiplier' => 100, 'unit' => 'cm']
        );
        $DB->set_field('question_numerical_options', 'showunits', 2, ['question' => $question->id]);
        $DB->set_field('question_numerical_options', 'unitgradingtype', 1, ['question' => $question->id]);

        $generator->add_questions_to_session([
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);
        $kid = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL]
        )->get('id');

        // 0.0314 cm is 0.000314 m: wrong. Matched with the stored multiplier instead of its
        // inverse it came out as 3.14, which is the answer that scores.
        self::setUser($student);
        $data = \mod_kuet\external\numerical_external::numerical(
            '0.0314',
            'cm',
            '100',
            $sid,
            $kuet->id,
            $kuet->cmid,
            $question->id,
            $kid,
            10,
            false
        );

        $this->assertEquals(questions::FAILURE, $data['result']);
        $this->assertStringNotContainsString(
            'Very good',
            $data['answer_feedback'],
            'A response graded wrong is being shown the feedback of the right answer: '
            . $data['answer_feedback']
        );
        $this->assertStringContainsString('Completely wrong', $data['answer_feedback']);
    }

    /**
     * The session used by every test of this file.
     *
     * @param \stdClass $kuet
     * @return array
     */
    private function sessionmock(\stdClass $kuet): array {
        return [
            'name' => 'Session Test',
            'kuetid' => $kuet->id,
            'anonymousanswer' => 0,
            'sessionmode' => sessions::PODIUM_MANUAL,
            'sgrade' => 0,
            'countdown' => 0,
            'showgraderanking' => 0,
            'randomquestions' => 0,
            'randomanswers' => 0,
            'showfeedback' => 1,
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
        ];
    }
}
