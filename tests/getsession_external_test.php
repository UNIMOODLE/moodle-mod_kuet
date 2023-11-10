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

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @category   test
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_jqshow;


use mod_jqshow\external\getsession_external;
use mod_jqshow\models\sessions;

class getsession_external_test extends \advanced_testcase {

    public function test_getsession() {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('jqshow', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_jqshow');
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);

        $sessionmock = [
            'name' => 'Session Test',
            'jqshowid' => $jqshow->id,
            'anonymousanswer' => 0,
            'sessionmode' => \mod_jqshow\models\sessions::PODIUM_MANUAL,
            'sgrade' => 0,
            'countdown' => 0,
            'showgraderanking' => 0,
            'randomquestions' => 0,
            'randomanswers' => 0,
            'showfeedback' => 0,
            'showfinalgrade' => 0,
            'startdate' => 0,
            'enddate' => 0,
            'automaticstart' => 0,
            'timemode' => 0,
            'sessiontime' => 0,
            'questiontime' => 10,
            'groupings' => 0,
            'status' => sessions::SESSION_ACTIVE,
            'sessionid' => 0,
            'showgraderanking' => 0,
        ];
        $createdsid = $generator->create_session($jqshow, (object) $sessionmock);
        $data = getsession_external::getsession($createdsid, $jqshow->cmid);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('session', $data);
        $this->assertEquals($createdsid, $data['session']['id']);
        $this->assertEquals($sessionmock['name'], $data['session']['name']);
        $this->assertEquals($sessionmock['anonymousanswer'], $data['session']['anonymousanswer']);
        $this->assertEquals($sessionmock['sessionmode'], $data['session']['sessionmode']);
        $this->assertEquals($sessionmock['showgraderanking'], $data['session']['showgraderanking']);
        $this->assertEquals($sessionmock['randomquestions'], $data['session']['randomquestions']);
        $this->assertEquals($sessionmock['showfeedback'], $data['session']['showfeedback']);
        $this->assertEquals($sessionmock['enddate'], $data['session']['enddate']);
        $this->assertEquals($sessionmock['automaticstart'], $data['session']['automaticstart']);
        $this->assertEquals($sessionmock['timemode'], $data['session']['timemode']);
        $this->assertEquals($sessionmock['sessiontime'], $data['session']['sessiontime']);
        $this->assertEquals($sessionmock['questiontime'], $data['session']['questiontime']);
        $this->assertEquals($sessionmock['groupings'], $data['session']['groupings']);
        $this->assertEquals($sessionmock['showgraderanking'], $data['session']['showgraderanking']);
    }
}
