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

use context_module;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/session_fixture_trait.php');

/**
 * Shared scenario for the tests of the question bank externals
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait questionbank_scenario_trait {
    use session_fixture_trait;

    /** @var stdClass Course holding both the kuet and the question bank. */
    private stdClass $course;

    /** @var stdClass The kuet activity. */
    private stdClass $kuet;

    /** @var stdClass The qbank activity acting as question bank. */
    private stdClass $qbank;

    /** @var context_module Context of the question bank. */
    private context_module $bankcontext;

    /** @var stdClass Question category inside the bank context. */
    private stdClass $category;

    /** @var stdClass The enrolled teacher, who manages the kuet and can use the bank. */
    private stdClass $teacher;

    /** @var int Id of the session created for the kuet. */
    private int $sid;

    /**
     * A kuet and a separate qbank module in the same course, with a session and a category.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $generator = self::getDataGenerator();
        $this->course = $generator->create_course();
        $this->kuet = $generator->create_module('kuet', ['course' => $this->course->id]);
        $this->qbank = $generator->create_module('qbank', ['course' => $this->course->id]);
        $this->bankcontext = context_module::instance($this->qbank->cmid);
        $this->category = $generator->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $this->bankcontext->id]);
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');

        self::setUser($this->teacher);
        $this->sid = $generator->get_plugin_generator('mod_kuet')
            ->create_session($this->kuet, $this->session_record());
    }

    /**
     * Minimal session, in a programmed mode so that no socket is involved.
     *
     * @return stdClass
     */
    private function session_record(): stdClass {
        return (object) $this->session_fixture([
            'kuetid' => $this->kuet->id,
            'sessionmode' => \mod_kuet\models\sessions::INACTIVE_PROGRAMMED,
        ]);
    }

    /**
     * The "categoryid,contextid" key the externals and the model expect.
     *
     * @return string
     */
    private function category_key(): string {
        return $this->category->id . ',' . $this->bankcontext->id;
    }

    /**
     * A question in the bank category, authored by the given user.
     *
     * The author matters: moodle/question:usemine only grants access to questions the
     * user created, and question_has_capability_on() resolves it through createdby.
     *
     * @param stdClass $owner
     * @param string $name
     * @return stdClass
     */
    private function create_bank_question(stdClass $owner, string $name): stdClass {
        self::setUser($owner);
        return self::getDataGenerator()->get_plugin_generator('core_question')->create_question(
            'shortanswer',
            null,
            ['category' => $this->category->id, 'name' => $name]
        );
    }

    /**
     * Sets the two question use capabilities on the bank context, for the teacher role.
     *
     * @param int $useall CAP_ALLOW, CAP_PREVENT or CAP_PROHIBIT.
     * @param int $usemine CAP_ALLOW, CAP_PREVENT or CAP_PROHIBIT.
     * @return void
     */
    private function set_bank_permissions(int $useall, int $usemine): void {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('moodle/question:useall', $useall, $roleid, $this->bankcontext->id, true);
        assign_capability('moodle/question:usemine', $usemine, $roleid, $this->bankcontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();
    }
    /**
     * A qbank in another course, which the caller may use but may not reach.
     *
     * The role grants moodle/question:useall on the bank context but no access to its
     * course, so a rejection here can only come from the require_login() that
     * validate_context() performs, never from the capability check.
     *
     * @return stdClass The qbank module.
     */
    private function create_unreachable_bank(): stdClass {
        $generator = self::getDataGenerator();
        $othercourse = $generator->create_course();
        $otherbank = $generator->create_module('qbank', ['course' => $othercourse->id]);
        $othercontext = context_module::instance($otherbank->cmid);
        $roleid = create_role('Bank reader', 'kuetbankreader', 'Can use questions, cannot enter the course');
        assign_capability('moodle/question:useall', CAP_ALLOW, $roleid, $othercontext->id, true);
        role_assign($roleid, $this->teacher->id, $othercontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $otherbank;
    }
}
