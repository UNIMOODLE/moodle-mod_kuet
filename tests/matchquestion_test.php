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

use core_external\external_api;
use mod_kuet\external\getquestion_external;
use mod_kuet\models\matchquestion;
use mod_kuet\models\questions;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use qtype_match_question;
use question_bank;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/session_fixture_trait.php');

/**
 * Match question test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Match question test class
 *
 * @covers \mod_kuet\models\matchquestion::get_simple_mark
 * @covers \mod_kuet\models\matchquestion::export_question
 * @covers \mod_kuet\models\matchquestion::get_question_report
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class matchquestion_test extends \advanced_testcase {
    use session_fixture_trait;

    /**
     * Load a generated match question, ready to be graded.
     *
     * The 'foursubq' template of core is the interesting one here: it has four
     * subquestions but only three stems and three choices, because 'frog' and 'newt'
     * share the 'amphibian' answer and 'insect' is offered with no stem at all.
     *
     * @param array $overrides
     * @return qtype_match_question
     */
    private function generated_question(array $overrides = []): qtype_match_question {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $questiondata = $generator->create_question('match', 'foursubq', $overrides + ['category' => $category->id]);
        $question = question_bank::load_question($questiondata->id, 0);
        $this->assertInstanceOf(qtype_match_question::class, $question);
        return $question;
    }

    /**
     * The response kuet stores: base64 of a JSON list of stem and choice pairs.
     *
     * A null pick leaves that stem without a pair, as a participant who did not
     * join it to anything.
     *
     * @param qtype_match_question $question
     * @param array $picks stem key to chosen choice key; defaults to the correct one
     * @return kuet_questions_responses
     */
    private function response_for(qtype_match_question $question, array $picks = []): kuet_questions_responses {
        $pairs = [];
        foreach (array_keys($question->stems) as $stem) {
            if (array_key_exists($stem, $picks) && $picks[$stem] === null) {
                continue;
            }
            $choice = array_key_exists($stem, $picks) ? $picks[$stem] : (int)$question->right[$stem];
            $pairs[] = (object)[
                'dragId' => $stem . '-draggable',
                'dropId' => $choice . '-dropzone',
                'color' => '#0fd08c',
                'stemDragId' => (string)$stem,
                'stemDropId' => (string)$choice,
                'stemsLeft' => $stem,
                'stemsRight' => $choice,
            ];
        }
        $json = json_encode(['hasfeedbacks' => true, 'timeleft' => 0, 'type' => 'match', 'response' => $pairs]);
        return new kuet_questions_responses(0, (object)[
            'id' => 0,
            'kuet' => 1,
            'session' => 1,
            'kid' => 1,
            'questionid' => $question->id,
            'userid' => 2,
            'anonymise' => 0,
            'result' => 0,
            'response' => base64_encode($json),
        ]);
    }

    /**
     * A choice shared by two stems, and a choice with no stem, are both handled.
     *
     * @return void
     */
    public function test_perfect_answer_scores_one_with_a_shared_choice_and_a_distractor(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->generated_question();

        // Three stems, three choices, and two of the stems point at the same choice.
        $this->assertCount(3, $question->stems);
        $this->assertCount(3, $question->choices);
        $this->assertCount(2, array_unique(array_map('intval', array_values($question->right))));

        $mark = matchquestion::get_simple_mark(new stdClass(), $this->response_for($question));
        $this->assertEquals(1.0, $mark);
    }

    /**
     * The case that already worked keeps working: one choice per stem, no distractor.
     *
     * @return void
     */
    public function test_perfect_answer_scores_one_without_shared_choices_or_distractors(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->generated_question([
            'name' => 'One answer per stem',
            'subquestions' => [
                0 => ['text' => 'frog', 'format' => FORMAT_HTML],
                1 => ['text' => 'cat', 'format' => FORMAT_HTML],
                2 => ['text' => 'bee', 'format' => FORMAT_HTML],
            ],
            'subanswers' => [0 => 'amphibian', 1 => 'mammal', 2 => 'insect'],
            'noanswers' => 3,
        ]);

        $this->assertCount(3, $question->stems);
        $this->assertCount(3, $question->choices);
        $this->assertCount(3, array_unique(array_map('intval', array_values($question->right))));

        $mark = matchquestion::get_simple_mark(new stdClass(), $this->response_for($question));
        $this->assertEquals(1.0, $mark);
    }

    /**
     * The mark is the proportion of stems, never of choices.
     *
     * @return void
     */
    public function test_mark_is_the_proportion_of_stems(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->generated_question();
        $stems = array_keys($question->stems);

        // Send the first stem to a choice that is not its own.
        $wrong = array_values(array_diff(array_keys($question->choices), [(int)$question->right[$stems[0]]]))[0];
        $mark = matchquestion::get_simple_mark(new stdClass(), $this->response_for($question, [$stems[0] => $wrong]));
        $this->assertEquals(2 / 3, $mark);

        // And two of the three wrong.
        $wrongtwo = array_values(array_diff(array_keys($question->choices), [(int)$question->right[$stems[1]]]))[0];
        $mark = matchquestion::get_simple_mark(
            new stdClass(),
            $this->response_for($question, [$stems[0] => $wrong, $stems[1] => $wrongtwo])
        );
        $this->assertEquals(1 / 3, $mark);
    }

    /**
     * A stem left without a pair counts as wrong, and does not shrink the total.
     *
     * @return void
     */
    public function test_a_stem_with_no_pair_counts_as_wrong(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->generated_question();
        $stems = array_keys($question->stems);

        $mark = matchquestion::get_simple_mark(new stdClass(), $this->response_for($question, [$stems[0] => null]));
        $this->assertEquals(2 / 3, $mark);
    }

    /**
     * Ids arrive from the client, so a pair naming something that is not of this
     * question is ignored rather than trusted.
     *
     * @return void
     */
    public function test_a_choice_that_is_not_of_this_question_is_ignored(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->generated_question();
        $stems = array_keys($question->stems);
        $alien = max(array_keys($question->choices)) + 1000;

        $mark = matchquestion::get_simple_mark(new stdClass(), $this->response_for($question, [$stems[0] => $alien]));
        $this->assertEquals(2 / 3, $mark);
    }

    /**
     * Nothing matched is zero, not a division by zero.
     *
     * @return void
     */
    public function test_no_pairs_scores_zero(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->generated_question();
        $picks = array_fill_keys(array_keys($question->stems), null);

        $mark = matchquestion::get_simple_mark(new stdClass(), $this->response_for($question, $picks));
        $this->assertEquals(0.0, $mark);
    }

    /**
     * A kuet session holding the given question, as an active podium session.
     *
     * @param qtype_match_question $question
     * @return array [kuet_sessions, cmid, sessionid, kuetid, kid]
     */
    private function session_with(qtype_match_question $question): array {
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_kuet');
        $sid = $generator->create_session($kuet, (object)$this->session_fixture(['kuetid' => $kuet->id]));
        $generator->add_questions_to_session([[
            'questionid' => $question->id,
            'sessionid' => $sid,
            'kuetid' => $kuet->id,
            'qtype' => questions::MATCH,
        ]]);
        $kuetquestion = kuet_questions::get_record(['questionid' => $question->id, 'sessionid' => $sid]);
        $this->assertInstanceOf(kuet_questions::class, $kuetquestion);
        return [new kuet_sessions($sid), (int)$kuet->cmid, (int)$sid, (int)$kuet->id, (int)$kuetquestion->get('id')];
    }

    /**
     * Every stem is exported with the option key of the choice it has to be joined to,
     * which the page cannot work out from the stem's own key.
     *
     * @return void
     */
    public function test_export_question_carries_the_correct_choice_of_each_stem(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->generated_question();
        [, $cmid, $sid, $kuetid, $kid] = $this->session_with($question);

        $data = matchquestion::export_question($kid, $cmid, $sid, $kuetid, true);

        // The choices, by the option key that identifies their element in the page.
        $choices = [];
        foreach ($data->rightoptions as $option) {
            $choices[$option['optionkey']] = $option['optiontext'];
        }
        $this->assertCount(3, $choices);

        foreach ($data->leftoptions as $option) {
            $expected = $question->choices[(int)$question->right[$option['key']]];
            $this->assertArrayHasKey($option['correctoptionkey'], $choices);
            $this->assertStringContainsString($expected, $choices[$option['correctoptionkey']]);
        }

        // Two of the three stems share their choice, and one choice has no stem at all.
        $pointedat = array_column($data->leftoptions, 'correctoptionkey');
        $this->assertCount(3, $pointedat);
        $this->assertCount(2, array_unique($pointedat));
        $this->assertCount(1, array_diff(array_keys($choices), $pointedat));
    }

    /**
     * The key reaches the page: the external services return only what the exporter
     * defines, so a field missing from it would be dropped on the way out.
     *
     * @return void
     */
    public function test_the_correct_choice_survives_the_external_service(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->generated_question();
        [, $cmid, $sid, , $kid] = $this->session_with($question);

        $result = external_api::clean_returnvalue(
            getquestion_external::getquestion_returns(),
            getquestion_external::getquestion($cmid, $sid, $kid)
        );

        $this->assertCount(3, $result['leftoptions']);
        $keys = [];
        foreach ($result['leftoptions'] as $option) {
            $this->assertArrayHasKey('correctoptionkey', $option);
            $this->assertNotSame('', $option['correctoptionkey']);
            $keys[] = $option['correctoptionkey'];
        }
        $this->assertCount(2, array_unique($keys));
        $this->assertEmpty(array_diff($keys, array_column($result['rightoptions'], 'optionkey')));
    }

    /**
     * The report pairs each stem with its own choice, and not with the choice that
     * happens to carry the stem's key.
     *
     * @return void
     */
    public function test_question_report_pairs_each_stem_with_its_own_choice(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->generated_question();
        [$session, , , , $kid] = $this->session_with($question);

        $data = matchquestion::get_question_report($session, $question, new stdClass(), $kid);

        $expected = [];
        foreach ($question->stems as $stem => $text) {
            $expected[] = $text . ' -> ' . $question->choices[(int)$question->right[$stem]];
        }
        $this->assertEquals($expected, array_column($data->correctanswers, 'response'));

        // Two of the three stems are answered by the same choice, and no line is left
        // dangling, which is what the stem's own key gave for a shared choice.
        $this->assertCount(3, $data->correctanswers);
        $picked = [];
        foreach ($data->correctanswers as $correctanswer) {
            $this->assertStringEndsNotWith(' -> ', $correctanswer['response']);
            $picked[] = explode(' -> ', $correctanswer['response'])[1];
        }
        $this->assertCount(2, array_unique($picked));
    }
}
