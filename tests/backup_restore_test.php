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

use backup;
use backup_controller;
use backup_setting;
use restore_controller;
use restore_dbops;
use mod_kuet\helpers\question_references;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once(__DIR__ . '/session_fixture_trait.php');

/**
 * Backup and restore test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup and restore test class
 *
 * @covers \restore_kuet_activity_structure_step
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_restore_test extends \advanced_testcase {
    use session_fixture_trait;

    /**
     * Restoring a course rewrites the answer ids the responses point at.
     *
     * The questions are duplicated by the restore and get new ids, so a response left
     * with the ids of the original answers reports every correct answer as failed
     * (KUET-028). What the participant picked is what has to survive the restore.
     *
     * @return void
     */
    public function test_restore_remaps_the_answer_ids_of_the_responses(): void {
        $fixture = $this->create_played_kuet();

        $newcourseid = $this->backup_and_restore($fixture->course);

        $this->assert_responses_point_at_their_own_question($newcourseid, $fixture);
    }

    /**
     * The same holds for a course that is itself a restore of a restored course.
     *
     * Copying a copy is ordinary use, and it is where a remapping that reads back the
     * ids it rewrote on the previous pass comes apart.
     *
     * @return void
     */
    public function test_restoring_a_restored_course_still_remaps(): void {
        $fixture = $this->create_played_kuet();

        $firstcourseid = $this->backup_and_restore($fixture->course);
        $secondcourseid = $this->backup_and_restore(get_course($firstcourseid));

        $this->assert_responses_point_at_their_own_question($secondcourseid, $fixture);
    }

    /**
     * Copying the activity on its own carries the questions of a bank outside it.
     *
     * The bank of a session is an activity of its own in Moodle 5, shared between
     * courses, so it is not inside the copy of a kuet. Without a reference to its
     * bank entry the package arrived with an empty questions.xml and the restored
     * session pointed at question ids of the site the copy came from (KUET-041).
     *
     * Restored in the same site, where that bank is still there, the session has to
     * keep sharing the questions it always had: duplicating a kuet cannot clone the
     * bank.
     *
     * @covers \mod_kuet\helpers\question_references
     * @return void
     */
    public function test_copy_of_the_activity_keeps_the_questions_of_a_bank_that_is_still_there(): void {
        global $DB;

        $fixture = $this->create_played_kuet();
        $target = self::getDataGenerator()->create_course();
        $questionsbefore = $DB->count_records('question');

        $newcmid = $this->backup_and_restore_activity($fixture->kuet, $target->id);

        // The package has to carry the questions, which is what was broken: without
        // them the restore has nothing to create and the session keeps the ids it had
        // for the wrong reason.
        $this->assertGreaterThan(
            $questionsbefore,
            $DB->count_records('question'),
            'The package carried no question at all'
        );
        $questions = $this->restored_questions($newcmid);
        $this->assertSame(
            array_map('intval', $fixture->questionids),
            array_map(static function ($question) {
                return (int) $question->questionid;
            }, array_values($questions)),
            'The restored session no longer points at the questions of the bank it shares'
        );
        $this->assert_every_question_is_referenced($newcmid);
        $this->assert_responses_point_at_their_own_question($target->id, $fixture);
    }

    /**
     * And when that bank is not there, the session takes the copies that travelled.
     *
     * Restoring in another site, which is where the ids of the original questions
     * mean nothing. Deleting the bank is what makes them unreachable here, which is
     * the same test core makes before pointing a reference of its own back at an
     * original.
     *
     * @covers \mod_kuet\helpers\question_references
     * @return void
     */
    public function test_copy_of_the_activity_carries_the_questions_when_the_bank_is_gone(): void {
        global $DB;

        $fixture = $this->create_played_kuet();
        $backupid = $this->backup_activity($fixture->kuet);

        course_delete_module($fixture->qbank->cmid);

        $target = self::getDataGenerator()->create_course();
        $newcmid = $this->restore_activity($backupid, $target->id);

        $questions = $this->restored_questions($newcmid);
        $this->assertCount(2, $questions, 'The questions did not travel in the package');
        foreach ($questions as $question) {
            $this->assertNotContains(
                (int) $question->questionid,
                array_map('intval', $fixture->questionids),
                'The session still points at a question of the site the copy came from'
            );
            // The copy has to be in a bank of the target course, and not hanging from
            // a category whose context no longer exists.
            $categoryid = $DB->get_field_sql(
                "SELECT qbe.questioncategoryid
                   FROM {question_versions} qv
                   JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  WHERE qv.questionid = ?",
                [$question->questionid]
            );
            $this->assertNotEmpty($categoryid, 'The restored question is in no category');
            $contextid = $DB->get_field('question_categories', 'contextid', ['id' => $categoryid]);
            $this->assertNotFalse(
                \context::instance_by_id((int) $contextid, IGNORE_MISSING),
                'The restored question is in a category whose context is gone'
            );
        }
        $this->assert_every_question_is_referenced($newcmid);
        $this->assert_responses_point_at_their_own_question($target->id, $fixture);
    }

    /**
     * Questions of the restored kuet, in the order of the session.
     *
     * @param int $cmid Course module of the restored kuet.
     * @return array
     */
    private function restored_questions(int $cmid): array {
        global $DB;

        $cm = get_coursemodule_from_id('kuet', $cmid, 0, false, MUST_EXIST);

        return $DB->get_records('kuet_questions', ['kuetid' => $cm->instance], 'qorder');
    }

    /**
     * Every question of the restored kuet has a reference that says what its id says.
     *
     * @param int $cmid Course module of the restored kuet.
     * @return void
     */
    private function assert_every_question_is_referenced(int $cmid): void {
        global $DB;

        $contextid = \context_module::instance($cmid)->id;
        foreach ($this->restored_questions($cmid) as $question) {
            $reference = $DB->get_record('question_references', [
                'component' => 'mod_kuet',
                'questionarea' => 'question',
                'itemid' => $question->id,
            ]);
            $this->assertNotFalse($reference, 'Question ' . $question->id . ' was restored with no reference');
            $this->assertEquals($contextid, $reference->usingcontextid);
            $version = $DB->get_record(
                'question_versions',
                ['questionid' => $question->questionid],
                'questionbankentryid, version'
            );
            $this->assertEquals(
                $version->questionbankentryid,
                $reference->questionbankentryid,
                'The reference and questionid of the same question do not agree'
            );
            $this->assertEquals($version->version, $reference->version, 'The reference is not pinned to its version');
        }
    }

    /**
     * Backs the activity up on its own, with user data.
     *
     * @param stdClass $kuet
     * @return string Id of the backup.
     */
    private function backup_activity(stdClass $kuet): string {
        global $CFG, $USER;

        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $kuet->cmid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $this->include_user_data($bc->get_plan());
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        return $backupid;
    }

    /**
     * Ask a plan of a single activity for the user data too.
     *
     * The responses only travel if the activity says so, and the setting of the
     * activity does not follow the one of the whole backup on its own.
     *
     * @param \base_plan $plan
     * @return void
     */
    private function include_user_data(\base_plan $plan): void {
        $settings = $plan->get_settings();
        if (isset($settings['users'])) {
            $settings['users']->set_status(backup_setting::NOT_LOCKED);
            $settings['users']->set_value(true);
        }
        foreach ($settings as $name => $setting) {
            if (substr($name, -9) !== '_userinfo') {
                continue;
            }
            $setting->set_status(backup_setting::NOT_LOCKED);
            $setting->set_value(true);
        }
    }

    /**
     * Restores a backed up activity into a course.
     *
     * @param string $backupid
     * @param int $courseid
     * @return int Course module id of the restored activity.
     */
    private function restore_activity(string $backupid, int $courseid): int {
        global $USER;

        $rc = new restore_controller(
            $backupid,
            $courseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_CURRENT_ADDING
        );
        $this->include_user_data($rc->get_plan());
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $cms = get_fast_modinfo($courseid)->get_instances_of('kuet');
        $this->assertCount(1, $cms, 'The activity was not restored into the target course');

        return (int) reset($cms)->id;
    }

    /**
     * Backs the activity up on its own and restores it into another course.
     *
     * @param stdClass $kuet
     * @param int $courseid
     * @return int Course module id of the restored activity.
     */
    private function backup_and_restore_activity(stdClass $kuet, int $courseid): int {
        return $this->restore_activity($this->backup_activity($kuet), $courseid);
    }

    /**
     * A kuet in a course, with a session, two questions and one response to each.
     *
     * The true-false question is one whose correct answer is False, answered True, so
     * a remapping that picks the new id from the right answer instead of from what the
     * participant chose turns a wrong answer into a right one.
     *
     * @return stdClass The course, and the answer texts each response stands for.
     */
    private function create_played_kuet(): stdClass {
        global $DB;

        $this->resetAfterTest();
        self::setAdminUser();

        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $kuet = $generator->create_module('kuet', ['course' => $course->id]);
        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(
            ['contextid' => \context_module::instance($qbank->cmid)->id]
        );
        $multichoice = $questiongenerator->create_question('multichoice', 'two_of_four', ['category' => $category->id]);
        $truefalse = $questiongenerator->create_question(
            'truefalse',
            'true',
            ['category' => $category->id, 'correctanswer' => '0']
        );
        $sid = $generator->get_plugin_generator('mod_kuet')->create_session(
            $kuet,
            (object) $this->session_fixture(['kuetid' => $kuet->id])
        );

        $mcanswers = $DB->get_records('question_answers', ['question' => $multichoice->id], 'id');
        $tfanswers = $DB->get_records('question_answers', ['question' => $truefalse->id], 'id');
        $truetext = get_string('true', 'qtype_truefalse');
        $mcpicked = array_keys(array_filter($mcanswers, static function ($answer) {
            return (float) $answer->fraction > 0;
        }));
        $tfcorrect = array_keys(array_filter($tfanswers, static function ($answer) {
            return (float) $answer->fraction > 0;
        }));
        $tfpicked = array_keys(array_filter($tfanswers, static function ($answer) use ($truetext) {
            return $answer->answer === $truetext;
        }));
        $this->assertNotSame(
            $tfcorrect,
            $tfpicked,
            'The participant has to have picked the wrong answer for this fixture to mean anything'
        );
        $texts = [];
        foreach ($mcanswers as $answer) {
            $texts[$answer->id] = strip_tags($answer->answer);
        }

        $mckid = $this->add_question_to_session($kuet, $sid, $multichoice->id, 'multichoice', 1);
        $tfkid = $this->add_question_to_session($kuet, $sid, $truefalse->id, 'truefalse', 2);
        $this->add_response($kuet, $sid, $mckid, $multichoice->id, $student->id, [
            'hasfeedbacks' => true,
            'correct_answers' => implode(',', $mcpicked),
            'answerids' => implode(',', $mcpicked),
            'answertexts' => json_encode($texts),
            'timeleft' => 0,
            'type' => 'multichoice',
        ]);
        $this->add_response($kuet, $sid, $tfkid, $truefalse->id, $student->id, [
            'hasfeedbacks' => true,
            'correct_answers' => (string) reset($tfcorrect),
            'answerids' => (string) reset($tfpicked),
            'answertexts' => '1',
            'timeleft' => 0,
            'type' => 'truefalse',
        ]);

        return (object) [
            'course' => $course,
            'kuet' => $kuet,
            'qbank' => $qbank,
            'questionids' => [$multichoice->id, $truefalse->id],
            'multichoicetexts' => array_map(static function ($id) use ($texts) {
                return $texts[$id];
            }, $mcpicked),
            'truefalsetext' => $truetext,
        ];
    }

    /**
     * Adds a bank question to a session.
     *
     * @param stdClass $kuet
     * @param int $sid
     * @param int $questionid
     * @param string $qtype
     * @param int $qorder
     * @return int Id of the kuet_questions record.
     */
    private function add_question_to_session(stdClass $kuet, int $sid, int $questionid, string $qtype, int $qorder): int {
        global $DB, $USER;

        $kid = $DB->insert_record('kuet_questions', (object) [
            'questionid' => $questionid,
            'sessionid' => $sid,
            'kuetid' => $kuet->id,
            'qorder' => $qorder,
            'qtype' => $qtype,
            'timelimit' => 10,
            'ignorecorrectanswer' => 0,
            'isvalid' => 1,
            'config' => '',
            'usermodified' => $USER->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        // The row is written straight to the table, so the hook of the persistent
        // that records the reference does not run. That the plugin does record it is
        // what question_references_test covers; here it is part of the fixture.
        question_references::set($kid, (int) $kuet->id, $questionid);

        return $kid;
    }

    /**
     * Stores one response, base64 of JSON, the way the plugin does.
     *
     * @param stdClass $kuet
     * @param int $sid
     * @param int $kid
     * @param int $questionid
     * @param int $userid
     * @param array $response
     * @return void
     */
    private function add_response(stdClass $kuet, int $sid, int $kid, int $questionid, int $userid, array $response): void {
        global $DB;

        $DB->insert_record('kuet_questions_responses', (object) [
            'kuet' => $kuet->id,
            'session' => $sid,
            'kid' => $kid,
            'questionid' => $questionid,
            'userid' => $userid,
            'anonymise' => 0,
            'result' => 1,
            'response' => base64_encode(json_encode($response)),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Every answer id in every response belongs to the question of that response.
     *
     * @param int $courseid The restored course.
     * @param stdClass $fixture What create_played_kuet() returned.
     * @return void
     */
    private function assert_responses_point_at_their_own_question(int $courseid, stdClass $fixture): void {
        global $DB;

        $cms = get_fast_modinfo($courseid)->get_instances_of('kuet');
        $this->assertCount(1, $cms);
        $cm = reset($cms);
        $responses = $DB->get_records('kuet_questions_responses', ['kuet' => $cm->instance], 'id');
        $this->assertCount(2, $responses);

        foreach ($responses as $record) {
            $response = json_decode(base64_decode($record->response), false);
            $this->assertIsObject($response, 'The response is no longer base64 of JSON');
            $answers = $DB->get_records('question_answers', ['question' => $record->questionid], 'id');
            $picked = [];
            foreach (explode(',', (string) $response->answerids) as $answerid) {
                $this->assertArrayHasKey(
                    (int) $answerid,
                    $answers,
                    'Answer ' . $answerid . ' does not belong to question ' . $record->questionid . ': KUET-028'
                );
                $picked[] = strip_tags($answers[(int) $answerid]->answer);
            }
            foreach (explode(',', (string) $response->correct_answers) as $answerid) {
                $this->assertArrayHasKey((int) $answerid, $answers, 'The correct answers were not remapped');
            }
            if ($response->type === 'multichoice') {
                $this->assertEqualsCanonicalizing($fixture->multichoicetexts, $picked);
                $this->assertEqualsCanonicalizing(
                    array_map('intval', array_keys($answers)),
                    array_map('intval', array_keys((array) json_decode($response->answertexts, true))),
                    'The answer texts are still keyed by the answer ids of the original question'
                );
            } else {
                $this->assertSame([$fixture->truefalsetext], $picked);
                $this->assertSame('1', (string) $response->answertexts);
            }
        }
    }

    /**
     * Backs a course up with user data and restores it into a new one.
     *
     * @param stdClass $course
     * @return int Id of the restored course.
     */
    private function backup_and_restore(stdClass $course): int {
        global $CFG, $USER;

        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(true);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '_restored',
            $course->category
        );
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
