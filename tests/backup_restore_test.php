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
 * @package     mod_kuet
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
        $student = $generator->create_and_enrol($course, 'student');
        $questiongenerator = $generator->get_plugin_generator('core_question');
        // The category lives in the context of the course, which is where a question bank
        // lives before Moodle 5, and which is what makes the questions travel in the backup.
        $category = $questiongenerator->create_question_category(
            ['contextid' => \context_course::instance($course->id)->id]
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

        return $DB->insert_record('kuet_questions', (object) [
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
