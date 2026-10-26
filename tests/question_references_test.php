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

use mod_kuet\external\deletequestion_external;
use mod_kuet\external\deletesession_external;
use mod_kuet\helpers\question_references;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/session_fixture_trait.php');

/**
 * Question references test
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kuet\helpers\question_references
 */
final class question_references_test extends \advanced_testcase {
    use session_fixture_trait;

    /**
     * A question added to a session is referenced in the bank entry it comes from.
     *
     * The reference is what carries the question into a copy of the activity
     * (KUET-041), and it is pinned to the version the session was built with.
     *
     * @return void
     */
    public function test_adding_a_question_to_a_session_references_its_bank_entry(): void {
        global $DB;

        $fixture = $this->create_session_with_a_question();

        $reference = $this->reference_of($fixture->kid);
        $this->assertNotFalse($reference, 'A question added to a session was left with no reference');
        $this->assertEquals(\context_module::instance($fixture->kuet->cmid)->id, $reference->usingcontextid);
        $this->assertSame('question', $reference->questionarea);
        $version = $DB->get_record(
            'question_versions',
            ['questionid' => $fixture->questionid],
            'questionbankentryid, version'
        );
        $this->assertEquals($version->questionbankentryid, $reference->questionbankentryid);
        $this->assertEquals($version->version, $reference->version, 'The reference is not pinned to its version');
    }

    /**
     * Taking the question out of the session takes its reference with it.
     *
     * @return void
     */
    public function test_taking_a_question_out_of_a_session_drops_its_reference(): void {
        $fixture = $this->create_session_with_a_question();

        deletequestion_external::deletequestion($fixture->sid, $fixture->kid);

        $this->assertFalse($this->reference_of($fixture->kid), 'The reference outlived the question of the session');
    }

    /**
     * Deleting the session drops the references of its questions.
     *
     * Its rows go in one statement, without building the persistent of each
     * question, so this is the path whose hooks never run.
     *
     * @return void
     */
    public function test_deleting_a_session_drops_the_references_of_its_questions(): void {
        $fixture = $this->create_session_with_a_question();

        deletesession_external::deletesession(
            (int) $fixture->course->id,
            (int) $fixture->kuet->cmid,
            (int) $fixture->sid
        );

        $this->assertFalse($this->reference_of($fixture->kid), 'The reference outlived the session');
    }

    /**
     * Deleting the activity drops the references of every question of every session.
     *
     * They are rows of core in the context of the module, and nothing else cleans
     * them: what is left behind keeps a question from being deleted from its bank.
     *
     * @return void
     */
    public function test_deleting_the_activity_drops_the_references_of_its_questions(): void {
        $fixture = $this->create_session_with_a_question();

        course_delete_module($fixture->kuet->cmid);

        $this->assertFalse($this->reference_of($fixture->kid), 'The reference outlived the activity');
    }

    /**
     * A kuet with a session and one question of a bank of its course.
     *
     * The question goes in through the external function the panel calls, which is
     * the path a teacher takes.
     *
     * @return stdClass
     */
    private function create_session_with_a_question(): stdClass {
        global $DB;

        $this->resetAfterTest();
        self::setAdminUser();

        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $kuet = $generator->create_module('kuet', ['course' => $course->id]);
        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(
            ['contextid' => \context_module::instance($qbank->cmid)->id]
        );
        $question = $questiongenerator->create_question('multichoice', 'two_of_four', ['category' => $category->id]);

        $kuetgenerator = $generator->get_plugin_generator('mod_kuet');
        $sid = $kuetgenerator->create_session($kuet, (object) $this->session_fixture(['kuetid' => $kuet->id]));
        $kuetgenerator->add_questions_to_session([
            [
                'questionid' => $question->id,
                'sessionid' => $sid,
                'kuetid' => $kuet->id,
                'qtype' => 'multichoice',
            ],
        ]);

        $kid = (int) $DB->get_field('kuet_questions', 'id', ['sessionid' => $sid, 'questionid' => $question->id]);
        $this->assertNotEmpty($kid, 'The question was not added to the session');

        return (object) [
            'course' => $course,
            'kuet' => $kuet,
            'qbank' => $qbank,
            'questionid' => (int) $question->id,
            'sid' => (int) $sid,
            'kid' => $kid,
        ];
    }

    /**
     * Reference of a session question, if it has one.
     *
     * @param int $kid Id in kuet_questions.
     * @return stdClass|false
     */
    private function reference_of(int $kid) {
        global $DB;

        return $DB->get_record('question_references', [
            'component' => question_references::COMPONENT,
            'questionarea' => question_references::QUESTIONAREA,
            'itemid' => $kid,
        ]);
    }
}
