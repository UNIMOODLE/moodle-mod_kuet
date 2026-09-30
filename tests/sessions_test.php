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
 * Sessions test
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet;

use core\invalid_persistent_exception;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_sessions;

/**
 * Sessions test class
 *
 * @covers \mod_kuet\models\sessions
 */
final class sessions_test extends \advanced_testcase {
    /**
     * @var array session mockup
     */
    public array $sessionmock = [
        'name' => 'Session Test',
        'kuetid' => 0,
        'anonymousanswer' => 0,
        'sessionmode' => sessions::PODIUM_MANUAL,
        'sgrade' => 0,
        'countdown' => 0,
        'showgraderanking' => 0,
        'randomquestions' => 0,
        'randomanswers' => 0,
        'showfeedback' => 0,
        'showfinalgrade' => 0,
        'startdate' => 1680534000,
        'enddate' => 1683133200,
        'automaticstart' => 0,
        'timemode' => 0,
        'sessiontime' => 0,
        'questiontime' => 10,
        'groupings' => 0,
        'status' => 1,
        'sessionid' => 0,
        'submitbutton' => 0,
    ];
    /**
     * @var sessions
     */
    public sessions $sessions;

    /**
     * Save session test
     *
     * @return bool
     * @throws invalid_persistent_exception
     * @throws \coding_exception
     */
    public function test_save_session(): void {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');
        $createdsid = $generator->create_session($kuet, (object) $this->sessionmock);
        $this->assertIsInt($createdsid);
        $this->assertNotFalse($createdsid);
    }

    /**
     * Delete session test
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws invalid_persistent_exception
     */
    public function test_delete_session(): bool {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');
        $generator->create_session($kuet, (object) $this->sessionmock);
        $this->sessions = new sessions($kuet, $kuet->cmid);
        $list = $this->sessions->get_list();
        $first = reset($list);
        $first::delete_session($first->get('id'));
        $this->sessions->set_list();
        $newlist = $this->sessions->get_list();
        $this->assertCount(0, $newlist);
        return true;
    }

    /**
     * Duplicate session test
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws invalid_persistent_exception
     */
    public function test_duplicate_session(): bool {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');
        $generator->create_session($kuet, (object) $this->sessionmock);
        $this->sessions = new sessions($kuet, $kuet->cmid);
        $list = $this->sessions->get_list();
        $first = reset($list);
        $first::duplicate_session($first->get('id'));
        $this->sessions->set_list();
        $newlist = $this->sessions->get_list();
        $this->assertCount(2, $newlist);
        return true;
    }

    /**
     * Test session
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_session(): void {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessions = new sessions($kuet, $kuet->cmid);
        $this->sessionmock['kuetid'] = $kuet->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');
        $createdsid = $generator->create_session($kuet, (object) $this->sessionmock);
        $expecteds = kuet_sessions::get_record(['kuetid' => $kuet->id]);
        $this->assertSame($expecteds->get('id'), $createdsid);
        $list = $this->sessions->get_list();
        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $first = reset($list);
        $this->assertIsObject($first);
        $this->assertSame('Session Test', $first->get('name'));
        $this->assertSame((int)$kuet->id, (int)$first->get('kuetid'));
        $session = new kuet_sessions($first->get('id'));
        $this->assertObjectEquals($session, $first);
    }

    /**
     * Breakdown responses for race test
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws invalid_persistent_exception
     */
    public function test_breakdown_responses_for_race(): void {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);
        $this->sessionmock['kuetid'] = $kuet->id;
        $generator = self::getDataGenerator()->get_plugin_generator('mod_kuet');
        $sid = $generator->create_session($kuet, (object) $this->sessionmock);

        // Three questions, so that the position of each one within the session is visible.
        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $toadd = [];
        for ($i = 0; $i < 3; $i++) {
            $question = $questiongenerator->create_question(questions::TRUE_FALSE, null, ['category' => $category->id]);
            $toadd[] = [
                'questionid' => $question->id,
                'sessionid' => $sid,
                'kuetid' => $kuet->id,
                'qtype' => questions::TRUE_FALSE,
            ];
        }
        $generator->add_questions_to_session($toadd);

        // The breakdown only reads the id of every result row.
        $student = self::getDataGenerator()->create_and_enrol($course);
        $userresults = [(object) ['id' => $student->id]];

        $breakdown = sessions::breakdown_responses_for_race($userresults, $sid, $kuet->cmid, $kuet->id);

        $this->assertCount(3, $breakdown);
        // The questionnum field is the position of the question in the session, 1 to 3, never the id
        // of its record: from Moodle 5.2 persistent::get_records() keys the instances it returns
        // by record id (MDL-79574) instead of numbering them from zero.
        $this->assertSame([1, 2, 3], array_column($breakdown, 'questionnum'));
        foreach ($breakdown as $questiondata) {
            $this->assertCount(1, $questiondata->studentsresponse);
            $this->assertEquals($student->id, $questiondata->studentsresponse[0]->userid);
            $this->assertSame('noresponse', $questiondata->studentsresponse[0]->responseclass);
        }
    }

    /**
     * Breakdown responses for race on group mode test
     *
     * @return void
     */
    public function test_breakdown_responses_for_race_groups(): void {
        // 3IP.
    }

    /**
     * Get provisional rankings test
     *
     * @return void
     */
    public function test_get_provisional_ranking(): void {
        $this->resetAfterTest(true);
        // Create session.
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;
        $student1 = self::getDataGenerator()->create_and_enrol($course);
        $student2 = self::getDataGenerator()->create_and_enrol($course);
        $student3 = self::getDataGenerator()->create_and_enrol($course);
        $student4 = self::getDataGenerator()->create_and_enrol($course);
    }

    /**
     * Get individual provisional ranking test
     *
     * @return void
     */
    public function test_get_provisional_ranking_individual(): void {
        // 3IP.
    }

    /**
     *  Get group provisional ranking test
     *
     * @return void
     */
    public function test_get_provisional_ranking_group(): void {
        // 3IP.
    }

    /**
     * Get final ranking test
     *
     * @return void
     */
    public function test_get_final_ranking(): void {
        // 3IP.
    }

    /**
     * A participant who scored zero is shown a zero, not an empty box.
     *
     * Regression for KUET-036: the points of the podium and of the rest of the final
     * ranking were blanked with a truth test, so anyone on 0 - which is most of a class
     * after a hard question - reached the template as an empty string and got a pill
     * with the star and no number in it.
     *
     * @return void
     */
    public function test_the_final_ranking_shows_a_score_of_zero(): void {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;
        $this->sessionmock['status'] = sessions::SESSION_ACTIVE;
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        $right = self::getDataGenerator()->create_and_enrol($course);
        $wrong = self::getDataGenerator()->create_and_enrol($course);

        self::setUser($teacher);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_kuet');
        $sid = $generator->create_session($kuet, (object) $this->sessionmock);
        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question(questions::TRUE_FALSE, null, ['category' => $category->id]);
        $generator->add_questions_to_session([
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE],
        ]);
        \mod_kuet\external\startsession_external::startsession($kuet->cmid, $sid);

        $definition = \question_bank::load_question($question->id);
        $kid = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE]
        )->get('id');
        foreach ([[$right, $definition->trueanswerid], [$wrong, $definition->falseanswerid]] as [$user, $answerid]) {
            self::setUser($user);
            \mod_kuet\external\truefalse_external::truefalse(
                $answerid,
                $sid,
                $kuet->id,
                $kuet->cmid,
                $question->id,
                $kid,
                10,
                false
            );
        }

        self::setUser($teacher);
        $data = sessions::get_final_ranking_data($sid, $kuet->cmid);

        $this->assertNotSame('', $data['firstuserpoints'], 'The winner has no points.');
        $this->assertSame('0', $data['seconduserpoints'], 'A score of zero must reach the template as a zero.');
        // Nobody else took part, so the two places left on the podium stay empty.
        $this->assertSame('', $data['thirduserpoints']);
    }

    /**
     * End session test
     *
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function test_export_endsession(): void {
        $this->resetAfterTest(true);
        // Create session.
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');
        $createdsid = $generator->create_session($kuet, (object) $this->sessionmock);

        $data = sessions::export_endsession($kuet->cmid, $createdsid);
        $this->assertIsObject($data);
        $this->assertTrue(property_exists($data, 'endsession'));
        $this->assertSame($data->endsession, true);
    }
}
