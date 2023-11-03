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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 *
 * @package    mod_jqshow
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_jqshow;

use mod_jqshow\models\questions;
use advanced_testcase;
use coding_exception;
use core\invalid_persistent_exception;
use invalid_parameter_exception;
use mod_jqshow\external\sessionspanel_external;
use mod_jqshow\models\sessions;
use moodle_exception;
use mod_jqshow\persistents\jqshow_sessions;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/jqshow/tests/sessions_test.php');

class sessionspanel_external_test extends advanced_testcase {

    public array $sessionmock = [
        'name' => 'Session Test External',
        'anonymousanswer' => sessions::ANONYMOUS_ANSWERS,
        'sessionmode' => sessions::PODIUM_MANUAL,
        'countdown' => 0,
        'randomquestions' => 0,
        'randomanswers' => 0,
        'showfeedback' => 0,
        'showfinalgrade' => 0,
        'startdate' => 1680534000,
        'enddate' => 1683133200,
        'automaticstart' => 0,
        'timelimit' => 0,
        'timemode' => sessions::QUESTION_TIME,
        'sessiontime' => 0,
        'questiontime' => 10,
        'groupmode' => 0,
        'status' => sessions::SESSION_FINISHED,
        'sessionid' => 0,
        'submitbutton' => 0,
        'showgraderanking' => 0,
    ];

    /**
     * @return true
     * @throws moodle_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     */
    public function test_sessionspanel(): void {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('jqshow', ['course' => $course->id]);
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);

        $this->sessionmock['jqshowid'] = $jqshow->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_jqshow');
        $createdsid = $generator->create_session($jqshow, (object) $this->sessionmock);

        // Create questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question(questions::SHORTANSWER, null, array('category' => $cat->id));
        $nq = $questiongenerator->create_question(questions::NUMERICAL, null, array('category' => $cat->id));
        $tfq = $questiongenerator->create_question(questions::TRUE_FALSE, null, array('category' => $cat->id));
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, array('category' => $cat->id));
        $ddwtosq = $questiongenerator->create_question(questions::DDWTOS, null, array('category' => $cat->id));
        $dq = $questiongenerator->create_question(questions::DESCRIPTION, null, array('category' => $cat->id));

        // Add questions to a session.
        $questions = [
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::NUMERICAL],
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::TRUE_FALSE],
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::MULTICHOICE],
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DDWTOS],
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DESCRIPTION],
        ];
        $generator->add_questions_to_session($questions);
        $allsessions = jqshow_sessions::get_records(['jqshowid' => $jqshow->id]);
        $expectedids = 0;
        foreach ($allsessions as $session) {
            if ($session->get('name') == $this->sessionmock['name']) {
                $expectedids = $session->get('id');
                break;
            }
        }
        $this->assertSame($expectedids, $createdsid);
        $result = sessionspanel_external::sessionspanel($jqshow->cmid);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('activesessions', $result);
        $this->assertArrayHasKey('endedsessions', $result);
        $this->assertArrayHasKey('courseid', $result);
        $this->assertArrayHasKey('cmid', $result);
        $this->assertArrayHasKey('hasqrcodeimage', $result);
        $this->assertArrayHasKey('urlqrcode', $result);
        $this->assertArrayHasKey('createsessionurl', $result);
        $this->assertArrayHasKey('hasactivesession', $result);
        $this->assertCount(0, $result['activesessions']);
        $this->assertCount(1, $result['endedsessions']);
        $this->assertSame($course->id, $result['courseid']);
        $this->assertSame($jqshow->cmid, $result['cmid']);
        $this->assertTrue($result['hasqrcodeimage']);
        $this->assertIsString($result['urlqrcode']);
        $sessionurl = (new \moodle_url('/mod/jqshow/sessions.php', ['cmid' => $jqshow->cmid, 'page' => 1]))->out(false);
        $this->assertEquals($sessionurl, $result['createsessionurl']);
        $this->assertFalse($result['hasactivesession']);
        $this->assertIsArray($result['endedsessions']);
        $this->assertObjectHasAttribute('name', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('sessionid', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('sessionmode', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('timemode', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('sessiontime', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('questions_number', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('managesessions', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('initsession', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('initsessionurl', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('viewreporturl', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('editsessionurl', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('date', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('status', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('issessionstarted', $result['endedsessions'][0]);
        $this->assertObjectHasAttribute('stringsession', $result['endedsessions'][0]);
        $this->assertEquals($this->sessionmock['name'], $result['endedsessions'][0]->name);
        $this->assertEquals($createdsid, $result['endedsessions'][0]->sessionid);
        $this->assertEquals(get_string($this->sessionmock['sessionmode'], 'mod_jqshow'),
            $result['endedsessions'][0]->sessionmode);
        $this->assertEquals(get_string('question_time', 'mod_jqshow'), $result['endedsessions'][0]->timemode);
        $this->assertEquals(userdate(60, '%Mm %Ss'), $result['endedsessions'][0]->sessiontime);
        $this->assertEquals(6, $result['endedsessions'][0]->questions_number);
        $this->assertTrue($result['endedsessions'][0]->managesessions);
        $this->assertTrue($result['endedsessions'][0]->initsession);
        $initsessionurl = (new \moodle_url('/mod/jqshow/session.php', ['cmid' => $jqshow->cmid, 'sid' => $createdsid]))->out(false);
        $this->assertEquals($initsessionurl, $result['endedsessions'][0]->initsessionurl);
        $viewreporturl =
            (new \moodle_url('/mod/jqshow/reports.php', ['cmid' => $jqshow->cmid, 'sid' => $createdsid]))->out(false);
        $this->assertEquals($viewreporturl, $result['endedsessions'][0]->viewreporturl);
        $editsessionurl =
            (new \moodle_url('/mod/jqshow/sessions.php', ['cmid' => $jqshow->cmid, 'sid' => $createdsid]))->out(false);
        $this->assertEquals($editsessionurl, $result['endedsessions'][0]->editsessionurl);
        $this->assertEquals($this->sessionmock['status'], $result['endedsessions'][0]->status);
        $this->assertFalse($result['endedsessions'][0]->issessionstarted);
        $this->assertEquals(get_string('init_session', 'mod_jqshow'), $result['endedsessions'][0]->stringsession);
    }
}
