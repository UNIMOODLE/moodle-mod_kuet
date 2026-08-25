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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_kuet;

use mod_kuet\external\addquestions_external;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_sessions;
use mod_kuet\question\bank_provider;
use mod_kuet\question\version_resolver;

/**
 * Shared banks, source permissions and version snapshots.
 * @package mod_kuet
 * @copyright 2026 KUET contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_kuet\question\bank_provider
 * @covers \mod_kuet\question\version_resolver
 * @covers \mod_kuet\external\addquestions_external
 */
final class question_bank_integration_test extends \advanced_testcase {
    /**
     * Build a real shared bank and a session in the destination course.
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        $kuet = $generator->create_module('kuet', ['course' => $course->id]);
        $bank = $generator->create_module('qbank', ['course' => $other->id]);
        $qgen = $generator->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => \context_module::instance($bank->cmid)->id]);
        $question = $qgen->create_question('shortanswer', null, ['category' => $category->id]);
        $session = new kuet_sessions(0, (object)['name' => 'Session', 'kuetid' => $kuet->id, 'status' => 1,
            'startdate' => 0, 'enddate' => 0, 'sessiontime' => 0, 'questiontime' => 0]);
        $session->create();
        return [$kuet, $bank, $session, $question, $qgen, $course, $other];
    }

    /**
     * Add through the actual public API.
     */
    private function add($kuet, $session, $question, bool $latest = false, string $token = ''): kuet_questions {
        addquestions_external::add_questions([['questionid' => $question->id,
            'kuetid' => $kuet->id, 'sessionid' => $session->get('id'),
            'qtype' => 'forged-type', 'uselatest' => $latest]], $token);
        return kuet_questions::get_record(['sessionid' => $session->get('id')]);
    }

    public function test_shared_banks_from_other_courses_and_server_type(): void {
        [$kuet, $bank, $session, $question, , $course] = $this->fixture();
        $local = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $banks = bank_provider::banks($course->id);
        $ids = array_map('intval', array_column($banks, 'modid'));
        $this->assertContains($bank->cmid, $ids, json_encode($ids));
        $this->assertContains($local->cmid, $ids);
        $slot = $this->add($kuet, $session, $question);
        $this->assertSame('shortanswer', $slot->get('qtype'));
        $reference = version_resolver::reference($slot->get('id'));
        $this->assertEquals(1, $reference->version);
        $this->assertEquals(\context_module::instance($kuet->cmid)->id, $reference->usingcontextid);
    }

    public function test_destination_permission_does_not_grant_source_permission(): void {
        [$kuet, , $session, $question, , $course] = $this->fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        $this->add($kuet, $session, $question);
    }

    public function test_private_quiz_bank_is_not_shared_even_for_admin(): void {
        [$kuet, , $session, , $qgen, $course] = $this->fixture();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id,
            'seb_program_autocomplete_program_quiz' => []]);
        $cat = $qgen->create_question_category(['contextid' => \context_module::instance($quiz->cmid)->id]);
        $question = $qgen->create_question('shortanswer', null, ['category' => $cat->id]);
        $this->expectException(\moodle_exception::class);
        $this->add($kuet, $session, $question);
    }

    public function test_batch_is_atomic_for_unsupported_question(): void {
        global $DB;
        [$kuet, , $session, $question, $qgen] = $this->fixture();
        $essay = $qgen->create_question('essay', null, ['category' => bank_provider::question($question->id)->categoryid]);
        try {
            addquestions_external::add_questions([
                ['questionid' => $question->id, 'kuetid' => $kuet->id, 'sessionid' => $session->get('id')],
                ['questionid' => $essay->id, 'kuetid' => $kuet->id, 'sessionid' => $session->get('id')],
            ]);
            $this->fail('Unsupported questions must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('questionnotusable', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('kuet_questions', ['sessionid' => $session->get('id')]));
    }

    public function test_latest_ready_ignores_newer_draft_and_freezes_on_start(): void {
        [$kuet, , $session, $question, $qgen] = $this->fixture();
        $slot = $this->add($kuet, $session, $question, true);
        $qgen->update_question($question, null, ['status' => 'draft']);
        $this->assertEquals($question->id, version_resolver::resolve($slot->to_record())->id);
        kuet_sessions::mark_session_started($session->get('id'));
        $qgen->update_question($question, null, ['status' => 'ready']);
        $session->read();
        $this->assertEquals(1, $session->get('questionslocked'));
        $slot->read();
        $this->assertEquals($question->id, $slot->get('questionid'));
        kuet_sessions::mark_session_active($session->get('id'));
        $this->expectException(\moodle_exception::class);
        $slot->set('qorder', 2)->update();
    }

    public function test_fixed_version_selection_updates_the_effective_question(): void {
        [$kuet, , $session, $question, $qgen] = $this->fixture();
        $slot = $this->add($kuet, $session, $question);
        $slot->set('config', '{"reviewed":true}');
        $slot->set('isvalid', 1);
        $slot->update();
        $new = $qgen->update_question($question, null, ['name' => 'Version two']);
        $newversion = (int) bank_provider::question($new->id)->version;

        version_resolver::set_policy($slot, $newversion);
        $slot->read();
        $this->assertEquals($new->id, $slot->get('questionid'));
        $this->assertEquals($newversion, version_resolver::reference($slot->get('id'))->version);
        $this->assertSame('', $slot->get('config'));
        $this->assertEquals(0, $slot->get('isvalid'));

        $draft = $qgen->update_question($new, null, ['name' => 'Draft version', 'status' => 'draft']);
        $draftversion = (int) bank_provider::question($draft->id)->version;
        try {
            version_resolver::set_policy($slot, $draftversion);
            $this->fail('Draft versions must not be selectable.');
        } catch (\moodle_exception $e) {
            $this->assertSame('questionnotusable', $e->errorcode);
        }
        $slot->read();
        $this->assertEquals($new->id, $slot->get('questionid'));
        $this->assertEquals($newversion, version_resolver::reference($slot->get('id'))->version);
    }

    public function test_question_form_displays_version_selector_for_multiple_versions(): void {
        $this->resetAfterTest();
        $form = new \mod_kuet\forms\questionform(null, [
            'id' => 1,
            'kid' => 2,
            'sid' => 3,
            'qname' => 'Question',
            'qtype' => 'shortanswer',
            'hasmultipleversions' => true,
            'versionoptions' => ['latest' => 'Latest ready version', '2' => 'Version 2', '1' => 'Version 1'],
            'questionversion' => '2',
            'reviewedquestionid' => 4,
            'timelimit' => 0,
            'sessionlimittimebyquestionsenabled' => true,
            'notimelimit' => false,
            'nograding' => 0,
        ]);
        $form->set_data(['questionversion' => '2']);
        $html = $form->render();
        $this->assertStringContainsString('name="questionversion"', $html);
        $this->assertStringContainsString('value="latest"', $html);
        $this->assertStringContainsString('value="2" selected', $html);
        $this->assertStringContainsString(get_string('questionversionselection', 'mod_kuet'), $html);
    }

    public function test_new_ready_version_requires_review_before_start(): void {
        [$kuet, , $session, $question, $qgen] = $this->fixture();
        $slot = $this->add($kuet, $session, $question, true);
        $new = $qgen->update_question($question, null, ['name' => 'Updated']);
        try {
            kuet_sessions::mark_session_started($session->get('id'));
            $this->fail('Unreviewed new version must not start.');
        } catch (\moodle_exception $e) {
            $this->assertSame('questionversionchanged', $e->errorcode);
        }
        version_resolver::refresh($slot);
        $this->assertEquals($new->id, $slot->get('questionid'));
        kuet_sessions::mark_session_started($session->get('id'));
        $slot->read();
        $this->assertEquals($new->id, $slot->get('questionid'));
    }

    public function test_copy_and_delete_maintain_independent_references(): void {
        [$kuet, , $session, $question] = $this->fixture();
        $slot = $this->add($kuet, $session, $question, true);
        $copyid = kuet_sessions::duplicate_session($session->get('id'));
        kuet_questions::copy_session_questions($session->get('id'), $copyid);
        $copy = kuet_questions::get_record(['sessionid' => $copyid]);
        $this->assertNull(version_resolver::reference($copy->get('id'))->version);
        $oldid = $slot->get('id');
        $slot->delete();
        $this->assertFalse(version_resolver::reference($oldid));
        $this->assertNotFalse(version_resolver::reference($copy->get('id')));
        kuet_questions::delete_session_questions($copyid);
        $this->assertFalse(version_resolver::reference($copy->get('id')));
    }

    public function test_retry_token_does_not_duplicate_positions(): void {
        [$kuet, , $session, $question] = $this->fixture();
        $this->add($kuet, $session, $question, false, 'request-1');
        $this->add($kuet, $session, $question, false, 'request-1');
        $this->assertEquals(1, kuet_questions::count_records(['sessionid' => $session->get('id')]));
    }

    public function test_usemine_only_allows_owned_questions(): void {
        global $DB;
        [$kuet, $bank, $session, $question, , $course] = $this->fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_module::instance($bank->cmid);
        $role = create_role('Bank owner', 'bankowner', '');
        assign_capability('moodle/question:usemine', CAP_ALLOW, $role, $context->id);
        role_assign($role, $teacher->id, $context->id);
        $DB->set_field('question', 'createdby', $teacher->id, ['id' => $question->id]);
        $this->setUser($teacher);
        $this->add($kuet, $session, $question);
        $DB->set_field('question', 'createdby', 2, ['id' => $question->id]);
        $this->expectException(\moodle_exception::class);
        $this->add($kuet, $session, $question);
    }

    public function test_modal_renders_core_filters_and_supported_selection(): void {
        global $CFG, $PAGE;
        [$kuet, $bank, $session, $question] = $this->fixture();
        require_once($CFG->dirroot . '/mod/kuet/lib.php');
        $PAGE->set_url('/mod/kuet/sessions.php');
        $html = mod_kuet_output_fragment_kuet_question_bank([
            'kuetcmid' => $kuet->cmid, 'bankcmid' => $bank->cmid, 'sessionid' => $session->get('id'),
        ]);
        $this->assertStringContainsString('questionsubmit', $html);
        $this->assertStringContainsString('data-filter', $html);
        // Select the question's category explicitly, as the core defaults to its own category.
        $html = mod_kuet_output_fragment_kuet_question_data([
            'cmid' => $bank->cmid, 'cat' => bank_provider::question($question->id)->categoryid . ',' .
                \context_module::instance($bank->cmid)->id, 'filter' => '{}',
            'extraparams' => json_encode(['kuetcmid' => $kuet->cmid, 'sessionid' => $session->get('id')]),
        ]);
        $this->assertStringContainsString('data-questionid="' . $question->id . '"', $html);
        $this->assertStringContainsString('/question/bank/editquestion/question.php', $html);
        $this->assertStringContainsString(get_string('editbankquestion', 'mod_kuet'), $html);
    }

    public function test_session_page_two_links_to_source_question_editor(): void {
        global $OUTPUT;
        [$kuet, $bank, $session, $question] = $this->fixture();
        $slot = $this->add($kuet, $session, $question);
        $data = \mod_kuet\models\questions::export_session_question($slot, $kuet->cmid);
        $returnurl = new \moodle_url('/mod/kuet/sessions.php', [
            'cmid' => $kuet->cmid,
            'sid' => $session->get('id'),
            'page' => 2,
        ]);
        $expectedurl = new \moodle_url('/question/bank/editquestion/question.php', [
            'id' => $question->id,
            'cmid' => $bank->cmid,
            'returnurl' => $returnurl->out_as_local_url(false),
        ]);
        $this->assertTrue($data->caneditbankquestion);
        $this->assertSame($expectedurl->out(false), $data->bankeditquestionurl);

        $html = $OUTPUT->render_from_template('mod_kuet/createsession/sessionquestions', [
            'sid' => $session->get('id'),
            'cmid' => $kuet->cmid,
            'kuetid' => $kuet->id,
            'sessionquestions' => [$data],
        ]);
        $this->assertStringContainsString('/question/bank/editquestion/question.php', $html);
        $this->assertStringContainsString(get_string('editbankquestion', 'mod_kuet'), $html);
        $this->assertStringContainsString(get_string('editkuetquestionsettings', 'mod_kuet'), $html);
    }

    public function test_upgrade_preserves_exact_version_and_is_idempotent(): void {
        global $CFG, $DB;
        [$kuet, , $session, $question, $qgen] = $this->fixture();
        $slot = $this->add($kuet, $session, $question);
        version_resolver::delete_references([$slot->get('id')]);
        $qgen->update_question($question, null, ['name' => 'Newer version']);
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/mod/kuet/db/upgrade.php');
        for ($i = 0; $i < 2; $i++) {
            set_config('version', 2026082500, 'mod_kuet');
            xmldb_kuet_upgrade(2026082500);
            $this->assertEquals(1, version_resolver::reference($slot->get('id'))->version);
            $this->assertEquals($question->id, $DB->get_field('kuet_questions', 'questionid', ['id' => $slot->get('id')]));
        }
        $this->assertEquals(1, $DB->count_records('question_references', ['component' => 'mod_kuet',
            'questionarea' => version_resolver::AREA, 'itemid' => $slot->get('id')]));
    }

    public function test_upgrade_from_legacy_schema_preserves_history(): void {
        global $CFG, $DB;
        $this->preventResetByRollback();
        [$kuet, , $draft, $question] = $this->fixture();
        $slot = $this->add($kuet, $draft, $question);
        version_resolver::delete_references([$slot->get('id')]);
        $historical = [];
        foreach ([2, 0, 1, 1] as $status) {
            $record = $draft->to_record();
            unset($record->id);
            $record->status = $status;
            $historical[] = $DB->insert_record('kuet_sessions', $record);
        }
        $responseid = $DB->insert_record('kuet_questions_responses', (object)[
            'kuet' => $kuet->id, 'session' => $historical[2], 'kid' => $slot->get('id'),
            'questionid' => $question->id, 'userid' => 2, 'response' => '{"answer":"old answer"}',
            'result' => 1, 'manualmark' => 0.5, 'manualcomment' => 'Reviewed before upgrade',
        ]);
        $DB->insert_record('kuet_user_progress', (object)[
            'kuet' => $kuet->id, 'session' => $historical[3], 'userid' => 2, 'randomquestion' => '', 'other' => '{}',
        ]);
        $gradeid = $DB->insert_record('kuet_sessions_grades', (object)[
            'kuet' => $kuet->id, 'session' => $historical[2], 'userid' => 2, 'grade' => 7.25,
        ]);
        $response = $DB->get_record('kuet_questions_responses', ['id' => $responseid]);
        $grade = $DB->get_record('kuet_sessions_grades', ['id' => $gradeid]);
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('kuet_sessions');
        $field = new \xmldb_field('questionslocked', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/mod/kuet/db/upgrade.php');
        try {
            $dbman->drop_field($table, $field);
            set_config('version', 2026082500, 'mod_kuet');
            xmldb_kuet_upgrade(2026082500);
            $this->assertTrue($dbman->field_exists($table, $field));
            $this->assertEquals(2026091600, get_config('mod_kuet', 'version'));
            $this->assertEquals(0, $DB->get_field('kuet_sessions', 'questionslocked', ['id' => $draft->get('id')]));
            foreach ($historical as $sid) {
                $this->assertEquals(1, $DB->get_field('kuet_sessions', 'questionslocked', ['id' => $sid]));
            }
            $this->assertEquals($response, $DB->get_record('kuet_questions_responses', ['id' => $responseid]));
            $this->assertEquals($grade, $DB->get_record('kuet_sessions_grades', ['id' => $gradeid]));
            $this->assertEquals(1, version_resolver::reference($slot->get('id'))->version);
        } finally {
            // Ensure even a failed assertion cannot leave the PHPUnit schema damaged.
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
    }

    public function test_upgrade_batches_preserve_existing_references_and_missing_questions(): void {
        global $CFG, $DB;
        [$kuet, , $session, $question] = $this->fixture();
        $slot = $this->add($kuet, $session, $question, true);
        $existing = version_resolver::reference($slot->get('id'));
        for ($i = 0; $i < 501; $i++) {
            $record = $slot->to_record();
            unset($record->id);
            $record->qorder = $i + 2;
            $DB->insert_record('kuet_questions', $record);
        }
        $record->questionid = 0;
        $missingid = $DB->insert_record('kuet_questions', $record);
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/mod/kuet/db/upgrade.php');
        set_config('version', 2026082500, 'mod_kuet');
        xmldb_kuet_upgrade(2026082500);
        $this->assertEquals(502, $DB->count_records('question_references', ['component' => 'mod_kuet']));
        $this->assertEquals($existing, version_resolver::reference($slot->get('id')));
        $this->assertFalse(version_resolver::reference($missingid));
        $this->assertEquals(0, $DB->get_field('kuet_questions', 'questionid', ['id' => $missingid]));
        $this->assertEquals(501, $DB->count_records('question_references', ['component' => 'mod_kuet', 'version' => 1]));
    }
    public function test_activity_backup_restore_maps_question_references(): void {
        global $CFG;
        [$kuet, , $session, $question, , $course] = $this->fixture();
        $slot = $this->add($kuet, $session, $question, true);
        require_once($CFG->dirroot . '/course/lib.php');
        $cm = get_coursemodule_from_id('kuet', $kuet->cmid);
        $newcm = duplicate_module($course, $cm);
        $this->assertNotNull($newcm);
        $copy = kuet_questions::get_record(['kuetid' => $newcm->instance]);
        $reference = version_resolver::reference($copy->get('id'));
        $this->assertNotFalse($reference);
        $this->assertNull($reference->version);
        $this->assertEquals(\context_module::instance($newcm->id)->id, $reference->usingcontextid);
        $this->assertEquals(
            version_resolver::reference($slot->get('id'))->questionbankentryid,
            $reference->questionbankentryid
        );
        $restored = bank_provider::question($copy->get('questionid'));
        $this->assertEquals($reference->questionbankentryid, $restored->questionbankentryid);
        $this->assertEquals($question->questiontext, $restored->questiontext);
    }

    public function test_rendering_usage_is_persisted_and_reused_for_same_version(): void {
        global $DB;
        [$kuet, , $session, $question] = $this->fixture();
        $this->add($kuet, $session, $question);
        $definition = \question_bank::load_question($question->id);
        for ($i = 0; $i < 2; $i++) {
            \mod_kuet\models\questions::get_text(
                $kuet->cmid,
                $definition->questiontext,
                $definition->questiontextformat,
                $definition->id,
                $definition,
                'questiontext',
                1
            );
        }
        $this->assertEquals(1, $DB->count_records('question_usages', ['component' => 'mod_kuet',
            'contextid' => \context_module::instance($kuet->cmid)->id]));
        $this->assertEquals(1, $DB->count_records('question_attempts', ['questionid' => $question->id]));
    }
}
