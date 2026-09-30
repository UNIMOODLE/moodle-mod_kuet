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

use mod_kuet\external\selectquestionscategory_external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/questionbank_scenario_trait.php');

/**
 * Select questions category test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Select questions category test class
 *
 * @covers \mod_kuet\external\selectquestionscategory_external
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class selectquestionscategory_external_test extends \advanced_testcase {
    use questionbank_scenario_trait;

    /**
     * Names of the questions returned for a category.
     *
     * @param array $result
     * @return array
     */
    private function question_names(array $result): array {
        $names = [];
        foreach ($result['questions'] as $question) {
            $names[] = $question['name'];
        }
        sort($names);
        return $names;
    }

    /**
     * With useall on the bank, every question of the category is listed.
     *
     * The assertDebuggingNotCalled() guards the ajax path: a debugging notice raised
     * anywhere under this call is printed inside the response and breaks the JSON.
     *
     * @return void
     */
    public function test_useall_lists_every_question_of_the_category(): void {
        $other = self::getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->create_bank_question($this->teacher, 'Mine');
        $this->create_bank_question($other, 'Someone elses');
        self::setUser($this->teacher);

        $result = selectquestionscategory_external::selectquestionscategory(
            $this->category_key(),
            $this->kuet->cmid,
            $this->qbank->cmid
        );

        $this->assertDebuggingNotCalled();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('questions', $result);
        $this->assertEquals(['Mine', 'Someone elses'], $this->question_names($result));
    }

    /**
     * With usemine only, the caller sees the questions they authored and no others.
     *
     * This is the case that exercises the per-question filter: the capability check on
     * the bank context passes, so anything that leaks here leaks question by question.
     *
     * @return void
     */
    public function test_usemine_lists_only_the_callers_own_questions(): void {
        $other = self::getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->create_bank_question($this->teacher, 'Mine');
        $this->create_bank_question($other, 'Someone elses');
        $this->set_bank_permissions(CAP_PREVENT, CAP_ALLOW);
        self::setUser($this->teacher);

        $result = selectquestionscategory_external::selectquestionscategory(
            $this->category_key(),
            $this->kuet->cmid,
            $this->qbank->cmid
        );

        $this->assertDebuggingNotCalled();
        $this->assertEquals(['Mine'], $this->question_names($result));
    }

    /**
     * Managing the kuet is not enough: the bank is another module, with its own context.
     *
     * @return void
     */
    public function test_teacher_without_use_capability_on_the_bank_is_rejected(): void {
        $this->create_bank_question($this->teacher, 'Mine');
        $this->set_bank_permissions(CAP_PROHIBIT, CAP_PROHIBIT);
        self::setUser($this->teacher);

        $this->expectException(\required_capability_exception::class);
        selectquestionscategory_external::selectquestionscategory(
            $this->category_key(),
            $this->kuet->cmid,
            $this->qbank->cmid
        );
    }

    /**
     * A student cannot list a category: the call needs mod/kuet:managesessions.
     *
     * @return void
     */
    public function test_student_is_rejected(): void {
        $this->create_bank_question($this->teacher, 'Mine');
        $student = self::getDataGenerator()->create_and_enrol($this->course, 'student');
        self::setUser($student);

        $this->expectException(\required_capability_exception::class);
        selectquestionscategory_external::selectquestionscategory(
            $this->category_key(),
            $this->kuet->cmid,
            $this->qbank->cmid
        );
    }

    /**
     * The bank cmid has to be a qbank instance, not any module the caller happens to name.
     *
     * @return void
     */
    public function test_bank_cmid_must_be_a_qbank_module(): void {
        self::setUser($this->teacher);

        $this->expectException(\moodle_exception::class);
        selectquestionscategory_external::selectquestionscategory(
            $this->category_key(),
            $this->kuet->cmid,
            $this->kuet->cmid
        );
    }

    /**
     * A category key without a comma is not a category: the model returns nothing.
     *
     * @return void
     */
    public function test_malformed_category_key_returns_no_questions(): void {
        $this->create_bank_question($this->teacher, 'Mine');
        self::setUser($this->teacher);

        $result = selectquestionscategory_external::selectquestionscategory(
            (string) $this->category->id,
            $this->kuet->cmid,
            $this->qbank->cmid
        );

        $this->assertDebuggingNotCalled();
        $this->assertSame([], $result['questions']);
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
        selectquestionscategory_external::selectquestionscategory(
            $this->category_key(),
            $this->kuet->cmid,
            $unreachable->cmid
        );
    }
}
