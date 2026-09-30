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
 * Calculated question type test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Calculated question type test class
 *
 * @covers \mod_kuet\models\calculated
 */
final class calculated_external_test extends \advanced_testcase {
    /**
     * The wildcards of the general feedback are resolved for the variant answered.
     *
     * Regression for KUET-035: qtype_calculated does not substitute a text handed to
     * format_text(), it substitutes the question's own properties when the attempt
     * starts. The feedback was read before that, so the participant was told
     * "Generalfeedback: {={a} + {b}} is the right answer." with the wildcards raw.
     *
     * @return void
     */
    public function test_the_general_feedback_resolves_its_wildcards(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_kuet');
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = self::getDataGenerator()->create_and_enrol($course);
        self::setUser($teacher);

        $sid = $generator->create_session($kuet, (object) $this->sessionmock($kuet));

        // Core's 'sum' calculated question, whose statement is "What is {a} + {b}?". Its
        // general feedback is given here, and not taken from core's fixture, because the
        // fixture carries it as a plain string and save_question() expects the array of an
        // editor field, so it would be stored empty and the test would prove nothing.
        // {={a} + {b}} is a formula, so this covers the wildcards and the evaluation.
        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question(questions::CALCULATED, null, [
            'category' => $cat->id,
            'generalfeedback' => [
                'text' => 'Generalfeedback: {={a} + {b}} is the right answer.',
                'format' => FORMAT_HTML,
            ],
        ]);
        $this->assertStringContainsString(
            '{',
            $DB->get_field('question', 'generalfeedback', ['id' => $question->id]),
            'The fixture must store the feedback with its wildcards, or this test proves nothing.'
        );

        // The generator leaves the question without dataset items, so give it one variant
        // of its own, the way core does it in question/tests/least_used_variant_strategy_test.
        foreach (['a' => 3, 'b' => 7] as $name => $value) {
            $definitionid = $DB->get_field_sql("
                    SELECT qdd.id
                      FROM {question_dataset_definitions} qdd
                      JOIN {question_datasets} qd ON qd.datasetdefinition = qdd.id
                     WHERE qd.question = ? AND qdd.name = ?", [$question->id, $name]);
            $DB->set_field('question_dataset_definitions', 'itemcount', 1, ['id' => $definitionid]);
            $DB->insert_record(
                'question_dataset_items',
                ['definition' => $definitionid, 'itemnumber' => 1, 'value' => $value]
            );
        }

        $generator->add_questions_to_session([
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::CALCULATED],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);
        $kid = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::CALCULATED]
        )->get('id');

        // 3 + 7, the only variant, answered right.
        self::setUser($student);
        $data = \mod_kuet\external\calculated_external::calculated(
            '10',
            1,
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

        $this->assertArrayHasKey('statment_feedback', $data);
        $feedback = $data['statment_feedback'];
        $this->assertStringNotContainsString('{', $feedback, 'The feedback still carries a wildcard: ' . $feedback);
        $this->assertStringContainsString('10', $feedback, 'The feedback does not resolve to 3 + 7: ' . $feedback);
        // Starting the attempt twice, which is what resolves the wildcards, must not
        // disturb the grading that runs right after it.
        $this->assertEquals(questions::SUCCESS, $data['result']);
    }

    /**
     * Only the answer that scores the whole mark is offered as the right one.
     *
     * Regression for KUET-037: every answer of the question was listed, including the
     * ones that are there to carry a specific feedback and the catch-all '*', so the
     * participant was shown a wrong answer as if it had been right.
     *
     * @return void
     */
    public function test_the_answer_help_lists_only_the_answers_that_score(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_kuet');
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = self::getDataGenerator()->create_and_enrol($course);
        self::setUser($teacher);

        $sid = $generator->create_session($kuet, (object) $this->sessionmock($kuet));

        // Core's 'sum' question answers {a} + {b} with the whole mark, {a} - {b} with none
        // -- it is there for the "Add, not subtract!" feedback -- and '*' with none.
        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question(questions::CALCULATED, null, ['category' => $cat->id]);
        foreach (['a' => 3, 'b' => 7] as $name => $value) {
            $definitionid = $DB->get_field_sql("
                    SELECT qdd.id
                      FROM {question_dataset_definitions} qdd
                      JOIN {question_datasets} qd ON qd.datasetdefinition = qdd.id
                     WHERE qd.question = ? AND qdd.name = ?", [$question->id, $name]);
            $DB->set_field('question_dataset_definitions', 'itemcount', 1, ['id' => $definitionid]);
            $DB->insert_record(
                'question_dataset_items',
                ['definition' => $definitionid, 'itemnumber' => 1, 'value' => $value]
            );
        }
        $generator->add_questions_to_session([
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::CALCULATED],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);
        $kid = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::CALCULATED]
        )->get('id');

        self::setUser($student);
        $data = \mod_kuet\external\calculated_external::calculated(
            '1',
            1,
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

        // 3 + 7, with the unit 'x' that core's fixture declares glued to it. What matters is
        // that it is the only one: no separator, no '*', and no sign of {a} - {b}.
        $this->assertStringStartsWith('10', $data['possibleanswers']);
        $this->assertStringNotContainsString(
            '/',
            $data['possibleanswers'],
            'Only one answer scores the whole mark, so there is nothing to separate: '
            . $data['possibleanswers']
        );
        $this->assertStringNotContainsString(
            '*',
            $data['possibleanswers'],
            'The catch-all answer is not an answer: ' . $data['possibleanswers']
        );
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
