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

use advanced_testcase;
use coding_exception;
use core\invalid_persistent_exception;
use dml_exception;
use invalid_parameter_exception;
use mod_jqshow\external\copysession_external;
use mod_jqshow\external\deletesession_external;
use mod_jqshow\models\sessions;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/jqshow/tests/sessions_test.php');

class deletesession_external_test extends advanced_testcase {
    public array $sessionmock = [
        'name' => 'Session Test - DELETE',
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
        'timemode' => sessions::QUESTION_TIME,
        'sessiontime' => 0,
        'questiontime' => 10,
        'groupings' => 0,
        'status' => \mod_jqshow\models\sessions::SESSION_ACTIVE,
        'sessionid' => 0,
        'submitbutton' => 0,
        'showgraderanking' => 0,
    ];
    /**
     * @return true
     * @throws invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public function test_deletesession(): bool {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('jqshow', ['course' => $course->id]);
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);
//        $sessiontest = new sessions_test();
        $sessiontest = new sessions($jqshow, $jqshow->cmid);
        $this->sessionmock['jqshowid'] = $jqshow->id;
//        $sessiontest->test_session($jqshow);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_jqshow');
        $sessionid = $generator->create_session($jqshow, (object) $this->sessionmock);
        $list = $sessiontest->get_list();
        $result = deletesession_external::deletesession($course->id, $jqshow->cmid, $list[0]->get('id'));
        $this->assertIsArray($result);
        $this->assertTrue($result['deleted']);
        $sessiontest->set_list();
        $newlist = $sessiontest->get_list();
        $this->assertCount(0, $newlist);

        $student = self::getDataGenerator()->create_and_enrol($course);
        self::setUser($student);
//        $sessiontest->test_session($jqshow);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_jqshow');
        $sessionid = $generator->create_session($jqshow, (object) $this->sessionmock);

        $newlist = $sessiontest->get_list();
        $result = deletesession_external::deletesession($course->id, $jqshow->cmid, $newlist[0]->get('id'));
        $this->assertIsArray($result);
        $this->assertFalse($result['deleted']);
        $this->assertCount(1, $newlist);

        return true;
    }
}
