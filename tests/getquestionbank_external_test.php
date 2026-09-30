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
use mod_kuet\external\getquestionbank_external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/questionbank_scenario_trait.php');

/**
 * Get question bank panel test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get question bank panel test class
 *
 * @covers \mod_kuet\external\getquestionbank_external
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class getquestionbank_external_test extends \advanced_testcase {
    use questionbank_scenario_trait;

    /**
     * A teacher who manages the kuet and can use the bank gets the panel.
     *
     * The assertDebuggingNotCalled() guards the ajax path: a debugging notice raised
     * anywhere under this call is printed inside the response and breaks the JSON.
     *
     * @return void
     */
    public function test_teacher_who_can_use_the_bank_gets_the_panel(): void {
        $this->create_bank_question($this->teacher, 'Own question');
        self::setUser($this->teacher);

        $result = getquestionbank_external::getquestionbank(
            $this->qbank->cmid,
            $this->kuet->id,
            $this->kuet->cmid,
            $this->sid
        );

        $this->assertDebuggingNotCalled();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('questions', $result);
        $panel = $result['questions'];
        $this->assertEquals($this->sid, $panel->sid);
        $this->assertEquals($this->kuet->cmid, $panel->cmid);
        $this->assertEquals($this->qbank->cmid, $panel->questionbankcmid);
        $this->assertIsArray($panel->questions);
    }

    /**
     * The name of each question survives the cleaning of the returns.
     *
     * Regression for KUET-033: getquestionbank_returns() did not declare 'name' in the
     * questions structure, so clean_returnvalue() dropped it on the ajax path and the
     * panel painted an empty {{name}} for every question of the bank. Calling the
     * external directly, as the other tests do, does not see it: the cleaning is what
     * removes the field.
     *
     * @return void
     */
    public function test_question_name_survives_the_cleaning_of_the_returns(): void {
        $this->create_bank_question($this->teacher, 'Own question');
        self::setUser($this->teacher);

        $result = getquestionbank_external::getquestionbank(
            $this->qbank->cmid,
            $this->kuet->id,
            $this->kuet->cmid,
            $this->sid
        );
        $clean = external_api::clean_returnvalue(getquestionbank_external::getquestionbank_returns(), $result);

        $this->assertNotEmpty($clean['questions']['questions']);
        $this->assertContains('Own question', array_column($clean['questions']['questions'], 'name'));
    }

    /**
     * Managing the kuet is not enough: the bank is another module, with its own context.
     *
     * @return void
     */
    public function test_teacher_without_use_capability_on_the_bank_is_rejected(): void {
        $this->create_bank_question($this->teacher, 'Own question');
        $this->set_bank_permissions(CAP_PROHIBIT, CAP_PROHIBIT);
        self::setUser($this->teacher);

        $this->expectException(\required_capability_exception::class);
        getquestionbank_external::getquestionbank(
            $this->qbank->cmid,
            $this->kuet->id,
            $this->kuet->cmid,
            $this->sid
        );
    }

    /**
     * A student never reaches the bank: the panel is guarded by mod/kuet:managesessions.
     *
     * @return void
     */
    public function test_student_is_rejected(): void {
        $student = self::getDataGenerator()->create_and_enrol($this->course, 'student');
        self::setUser($student);

        $this->expectException(\required_capability_exception::class);
        getquestionbank_external::getquestionbank(
            $this->qbank->cmid,
            $this->kuet->id,
            $this->kuet->cmid,
            $this->sid
        );
    }

    /**
     * The bank cmid has to be a qbank instance, not any module the caller happens to name.
     *
     * Without asserting the module type the call fails much later and with an opaque
     * error, because a teacher usually holds moodle/question:useall at course level and
     * that propagates to every module context of the course, so the capability check on
     * the supposed bank passes.
     *
     * This test locks the behaviour but is not a regression test for that assert: this
     * path also throws without it, further down, in export_session_questionbank_panel().
     * The twin test in selectquestionscategory_external_test is the one that fails if the
     * module type stops being checked.
     *
     * @return void
     */
    public function test_bank_cmid_must_be_a_qbank_module(): void {
        self::setUser($this->teacher);

        $this->expectException(\moodle_exception::class);
        getquestionbank_external::getquestionbank(
            $this->kuet->cmid,
            $this->kuet->id,
            $this->kuet->cmid,
            $this->sid
        );
    }

    /**
     * The session has to belong to the module whose context was validated.
     *
     * @return void
     */
    public function test_session_of_another_kuet_is_rejected(): void {
        $generator = self::getDataGenerator();
        $otherkuet = $generator->create_module('kuet', ['course' => $this->course->id]);
        self::setUser($this->teacher);
        $othersid = $generator->get_plugin_generator('mod_kuet')->create_session(
            $otherkuet,
            (object) array_merge((array) $this->session_record(), ['kuetid' => $otherkuet->id])
        );

        $this->expectException(\moodle_exception::class);
        getquestionbank_external::getquestionbank(
            $this->qbank->cmid,
            $this->kuet->id,
            $this->kuet->cmid,
            $othersid
        );
    }
    /**
     * A bank the caller may use but may not reach is rejected.
     *
     * The capability on the bank context is granted on purpose, so the only thing left
     * to stop the read is the require_login() inside validate_context(). Drop that call
     * and this test starts returning questions from a course the user cannot enter.
     *
     * @return void
     */
    public function test_bank_in_an_unreachable_course_is_rejected(): void {
        $unreachable = $this->create_unreachable_bank();
        self::setUser($this->teacher);

        $this->expectException(\require_login_exception::class);
        getquestionbank_external::getquestionbank(
            $unreachable->cmid,
            $this->kuet->id,
            $this->kuet->cmid,
            $this->sid
        );
    }

    /**
     * The panel opens on a category that holds questions, not on the top one.
     *
     * Regression for KUET-043: the current category was the first key of the options,
     * which is always "top" - a category that by convention holds no question of its
     * own, so the bank opened showing whatever its subcategories happened to have.
     *
     * @return void
     */
    public function test_the_panel_opens_on_a_category_with_questions(): void {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        $this->create_bank_question($this->teacher, 'Own question');
        self::setUser($this->teacher);

        $result = getquestionbank_external::getquestionbank(
            $this->qbank->cmid,
            $this->kuet->id,
            $this->kuet->cmid,
            $this->sid
        );

        $top = question_get_top_category($this->bankcontext->id);
        $this->assertSame($this->category_key(), $result['questions']->currentcategory);
        $this->assertNotSame(
            $top->id . ',' . $this->bankcontext->id,
            $result['questions']->currentcategory,
            'The panel still opens on the top category'
        );
    }
}
