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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos

/**
 *
 * @package    mod_jqshow
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use core\invalid_persistent_exception;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_sessions;

class sessions_test extends advanced_testcase {

    public array $sessionmock = [
        'name' => 'Session Test',
        'anonymousanswer' => 0,
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
        'groupmode' => 0,
        'status' => 1,
        'sessionid' => 0,
        'submitbutton' => 0,
        'showgraderanking' => 0,
    ];
    public sessions $sessions;

    /**
     * @return bool
     * @throws invalid_persistent_exception
     * @throws coding_exception
     */
    public function test_save_session(): bool {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('jqshow', ['course' => $course->id]);
        $this->sessionmock['jqshowid'] = $jqshow->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_jqshow');
        $createdsid = $generator->create_session($jqshow, (object) $this->sessionmock);
        return true;
    }

    /**
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     */
    public function test_delete_session(): bool {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('jqshow', ['course' => $course->id]);
        $this->sessionmock['jqshowid'] = $jqshow->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_jqshow');
        $createdsid = $generator->create_session($jqshow, (object) $this->sessionmock);
        $this->sessions = new sessions($jqshow, $jqshow->cmid);
        $list = $this->sessions->get_list();
        $list[0]::delete_session($list[0]->get('id'));
        $this->sessions->set_list();
        $newlist = $this->sessions->get_list();
        $this->assertCount(0, $newlist);
        return true;
    }

    /**
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     */
    public function test_duplicate_session(): bool {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('jqshow', ['course' => $course->id]);
        $this->sessionmock['jqshowid'] = $jqshow->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_jqshow');
        $generator->create_session($jqshow, (object) $this->sessionmock);
        $this->sessions = new sessions($jqshow, $jqshow->cmid);
        $list = $this->sessions->get_list();
        $list[0]::duplicate_session($list[0]->get('id'));
        $this->sessions->set_list();
        $newlist = $this->sessions->get_list();
        $this->assertCount(2, $newlist);
        return true;
    }

    /**
     * @param stdClass $jqshow
     * @return void
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public function test_session() {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('jqshow', ['course' => $course->id]);
        $this->sessions = new sessions($jqshow, $jqshow->cmid);
        $this->sessionmock['jqshowid'] = $jqshow->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_jqshow');
        $createdsid = $generator->create_session($jqshow, (object) $this->sessionmock);
        $expecteds = jqshow_sessions::get_record(['jqshowid' => $jqshow->id]);
        $this->assertSame($expecteds->get('id'), $createdsid);
        $list = $this->sessions->get_list();
        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $this->assertIsObject($list[0]);
        $this->assertSame('Session Test', $list[0]->get('name'));
        $this->assertSame((int)$jqshow->id, (int)$list[0]->get('jqshowid'));
        $session = new jqshow_sessions($list[0]->get('id'));
        $this->assertObjectEquals($session, $list[0]);
    }
}
