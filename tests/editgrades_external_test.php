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

namespace mod_kuet;

use mod_kuet\api\grade;
use mod_kuet\external\editgrades_external;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;

/**
 * Manual grade editing web service tests (KUETEDUCAM-73)
 *
 * @package    mod_kuet
 * @author     3IPUNT <contacte@tresipunt.com>
 * @copyright  3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kuet\external\editgrades_external
 */
final class editgrades_external_test extends \advanced_testcase {
    /**
     * Set up a graded session with one answered multichoice question.
     *
     * @return array [\stdClass kuet, int sid, int kid, \stdClass student, float defaultmark]
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    private function setup_answered_session(): array {
        global $DB;
        $gen = self::getDataGenerator();
        $course = $gen->create_course();
        $kuet = $gen->create_module('kuet', [
            'course' => $course->id,
            'grademethod' => grade::MOD_OPTION_GRADE_HIGHEST,
            'sessiongrademax' => 10.0,
        ]);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        self::setUser($teacher);

        $generator = $gen->get_plugin_generator('mod_kuet');
        $sessionmock = [
            'name' => 'Session', 'kuetid' => $kuet->id, 'anonymousanswer' => 0,
            'sessionmode' => sessions::INACTIVE_MANUAL, 'sgrade' => 1, 'mandatoryattendance' => 1,
            'countdown' => 0, 'showgraderanking' => 0, 'randomquestions' => 0, 'randomanswers' => 0,
            'showfeedback' => 0, 'showfinalgrade' => 0, 'startdate' => 0, 'enddate' => 0, 'automaticstart' => 0,
            'timemode' => sessions::QUESTION_TIME, 'sessiontime' => 0, 'questiontime' => 10,
            'groupings' => 0, 'status' => sessions::SESSION_ACTIVE, 'sessionid' => 0, 'submitbutton' => 0,
        ];
        $sid = $generator->create_session($kuet, (object) $sessionmock);

        $questiongenerator = $gen->get_plugin_generator('core_question');
        $bank = self::getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $cat = $questiongenerator->create_question_category([
            'contextid' => \context_module::instance($bank->cmid)->id,
        ]);
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, ['category' => $cat->id]);
        $generator->add_questions_to_session([
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);

        $jmcq = kuet_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]
        );

        $student = $gen->create_and_enrol($course);
        $qbmc = \question_bank::load_question($mcq->id);
        $correctanswers = [];
        foreach ($qbmc->answers as $answer) {
            if ($answer->fraction > 0) {
                $correctanswers[] = $answer->id;
            }
        }
        self::setUser($student);
        \mod_kuet\external\multichoice_external::multichoice(
            implode(',', $correctanswers),
            $sid,
            $kuet->id,
            $kuet->cmid,
            $mcq->id,
            $jmcq->get('id'),
            10,
            false
        );
        self::setUser($teacher);

        $defaultmark = (float) $DB->get_field('question', 'defaultmark', ['id' => $mcq->id]);
        return [$kuet, $sid, $jmcq->get('id'), $student, $defaultmark];
    }

    /**
     * The service saves the manual mark and reports the effective values back.
     *
     * @return void
     */
    public function test_setmanualgrade_saves(): void {
        $this->resetAfterTest();
        [$kuet, $sid, $kid, $student, $defaultmark] = $this->setup_answered_session();

        $result = editgrades_external::setmanualgrade(
            $kuet->cmid,
            $sid,
            $kid,
            $student->id,
            $defaultmark / 2,
            'Partially valid'
        );

        $this->assertTrue($result['success']);
        $this->assertEqualsWithDelta($defaultmark / 2, $result['mark'], 0.00001);
        $this->assertTrue($result['ismanual']);

        $stored = kuet_questions_responses::get_record(
            ['kuet' => $kuet->id, 'session' => $sid, 'kid' => $kid, 'userid' => $student->id]
        );
        $this->assertEqualsWithDelta($defaultmark / 2, (float) $stored->get('manualmark'), 0.00001);
        $this->assertEquals('Partially valid', $stored->get('manualcomment'));
    }

    /**
     * An empty comment is rejected.
     *
     * @return void
     */
    public function test_setmanualgrade_requires_comment(): void {
        $this->resetAfterTest();
        [$kuet, $sid, $kid, $student, $defaultmark] = $this->setup_answered_session();

        $this->expectException(\moodle_exception::class);
        editgrades_external::setmanualgrade($kuet->cmid, $sid, $kid, $student->id, $defaultmark, '   ');
    }

    /**
     * A mark above the question default mark is rejected.
     *
     * @return void
     */
    public function test_setmanualgrade_rejects_out_of_range(): void {
        $this->resetAfterTest();
        [$kuet, $sid, $kid, $student, $defaultmark] = $this->setup_answered_session();

        $this->expectException(\moodle_exception::class);
        editgrades_external::setmanualgrade($kuet->cmid, $sid, $kid, $student->id, $defaultmark + 1, 'Too high');
    }

    /**
     * A user without the capability cannot edit grades.
     *
     * @return void
     */
    public function test_setmanualgrade_requires_capability(): void {
        $this->resetAfterTest();
        [$kuet, $sid, $kid, $student, $defaultmark] = $this->setup_answered_session();

        self::setUser($student);
        $this->expectException(\required_capability_exception::class);
        editgrades_external::setmanualgrade($kuet->cmid, $sid, $kid, $student->id, $defaultmark, 'Not allowed');
    }
}
