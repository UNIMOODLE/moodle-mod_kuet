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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos..

/**
 * Privacy provider tests
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <juanpablo.decastro@uva.es>
 * @author     Juan Pablo de Castro  <juan.pablo.de.castro@gmail.com>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\privacy;

use context_module;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use stdClass;

/**
 * Privacy provider test class
 *
 * @covers \mod_kuet\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var stdClass Course used across the fixture. */
    private stdClass $course;

    /** @var stdClass The kuet instance holding the data. */
    private stdClass $kuet;

    /** @var context_module Context of $kuet. */
    private context_module $context;

    /** @var stdClass User with data in $kuet. */
    private stdClass $student;

    /** @var stdClass Another user with data in $kuet. */
    private stdClass $otherstudent;

    /**
     * Build a kuet with answers, progress and grades for two students.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');
        $this->kuet = $generator->create_instance(['course' => $this->course->id]);
        $this->context = context_module::instance($this->kuet->cmid);

        $this->student = $this->getDataGenerator()->create_user();
        $this->otherstudent = $this->getDataGenerator()->create_user();

        $this->create_user_data($this->student->id);
        $this->create_user_data($this->otherstudent->id);
    }

    /**
     * Insert one row per user-data table for the given user.
     *
     * @param int $userid
     * @return void
     */
    private function create_user_data(int $userid): void {
        global $DB;

        $now = time();

        $DB->insert_record('kuet_questions_responses', (object) [
            'kuet' => $this->kuet->id,
            'session' => 1,
            'kid' => 1,
            'questionid' => 1,
            'userid' => $userid,
            'anonymise' => 0,
            'result' => 1,
            'response' => '{"answer":"a"}',
            'manualmark' => 0.75,
            'manualcomment' => 'Partially right, accepted.',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('kuet_user_progress', (object) [
            'kuet' => $this->kuet->id,
            'session' => 1,
            'userid' => $userid,
            'randomquestion' => 0,
            'other' => '{"currentquestion":1}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('kuet_sessions_grades', (object) [
            'kuet' => $this->kuet->id,
            'session' => 1,
            'userid' => $userid,
            'grade' => 7.5,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('kuet_grades', (object) [
            'kuet' => $this->kuet->id,
            'userid' => $userid,
            'grade' => 7.5,
            'timemodified' => $now,
        ]);
    }

    /**
     * Count rows of a user across every table owned by the provider.
     *
     * @param int $userid
     * @return int
     */
    private function count_rows_for(int $userid): int {
        global $DB;

        $total = 0;
        foreach (['kuet_questions_responses', 'kuet_user_progress', 'kuet_sessions_grades', 'kuet_grades'] as $table) {
            $total += $DB->count_records($table, ['kuet' => $this->kuet->id, 'userid' => $userid]);
        }

        return $total;
    }

    /**
     * The metadata declares the four tables and the external socket server.
     *
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_kuet'));
        $items = $collection->get_collection();

        $names = array_map(static fn($item) => $item->get_name(), $items);

        $this->assertContains('kuet_questions_responses', $names);
        $this->assertContains('kuet_user_progress', $names);
        $this->assertContains('kuet_sessions_grades', $names);
        $this->assertContains('kuet_grades', $names);
        $this->assertContains('socketserver', $names);
    }

    /**
     * A user with data gets the module context; a user without data gets none.
     *
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $contextlist = provider::get_contexts_for_userid($this->student->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($this->context->id, $contextlist->get_contextids()[0]);

        $stranger = $this->getDataGenerator()->create_user();
        $this->assertCount(0, provider::get_contexts_for_userid($stranger->id));
    }

    /**
     * Both students are reported as holding data in the context.
     *
     * @return void
     */
    public function test_get_users_in_context(): void {
        $userlist = new userlist($this->context, 'mod_kuet');
        provider::get_users_in_context($userlist);

        // The userid list comes back as ints, while the generator hands back string ids.
        $userids = $userlist->get_userids();
        $this->assertCount(2, $userids);
        $this->assertContains((int) $this->student->id, $userids);
        $this->assertContains((int) $this->otherstudent->id, $userids);
    }

    /**
     * The export carries the answers, progress and grades of that user only.
     *
     * @return void
     */
    public function test_export_user_data(): void {
        $contextlist = new approved_contextlist($this->student, 'mod_kuet', [$this->context->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data([]);
        $this->assertCount(1, $data->responses);
        $this->assertSame('{"answer":"a"}', $data->responses[0]['response']);
        $this->assertSame('Partially right, accepted.', $data->responses[0]['manualcomment']);
        $this->assertCount(1, $data->progress);
        $this->assertCount(1, $data->sessiongrades);
        $this->assertCount(1, $data->grades);
    }

    /**
     * Deleting the context wipes both students.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertSame(0, $this->count_rows_for($this->student->id));
        $this->assertSame(0, $this->count_rows_for($this->otherstudent->id));
    }

    /**
     * Deleting one user leaves the other one untouched.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        $contextlist = new approved_contextlist($this->student, 'mod_kuet', [$this->context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertSame(0, $this->count_rows_for($this->student->id));
        $this->assertSame(4, $this->count_rows_for($this->otherstudent->id));
    }

    /**
     * Deleting an approved userlist only removes the users in it.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        $userlist = new approved_userlist($this->context, 'mod_kuet', [$this->student->id]);
        provider::delete_data_for_users($userlist);

        $this->assertSame(0, $this->count_rows_for($this->student->id));
        $this->assertSame(4, $this->count_rows_for($this->otherstudent->id));
    }

    /**
     * A course context is not a kuet: nothing must be deleted.
     *
     * @return void
     */
    public function test_delete_ignores_non_module_contexts(): void {
        provider::delete_data_for_all_users_in_context(\context_course::instance($this->course->id));

        $this->assertSame(4, $this->count_rows_for($this->student->id));
        $this->assertSame(4, $this->count_rows_for($this->otherstudent->id));
    }
}
