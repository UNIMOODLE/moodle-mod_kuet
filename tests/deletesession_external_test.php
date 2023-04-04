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
namespace mod_jqshow;

use advanced_testcase;
use coding_exception;
use core\invalid_persistent_exception;
use dml_exception;
use invalid_parameter_exception;
use mod_jqshow\external\copysession_external;
use mod_jqshow\external\deletesession_external;
use sessions_test;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/jqshow/tests/sessions_test.php');

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @category   test
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deletesession_external_test extends advanced_testcase
{

    public array $sessionmock = [
        'name' => 'Session Test',
        'anonymousanswer' => 0,
        'allowguests' => 0,
        'advancemode' => 'programmed',
        'gamemode' => 'inactive',
        'countdown' => 0,
        'randomquestions' => 0,
        'randomanswers' => 0,
        'showfeedback' => 0,
        'showfinalgrade' => 0,
        'startdate' => 1680534000,
        'enddate' => 1683133200,
        'automaticstart' => 0,
        'activetimelimit' => 0,
        'timelimit' => 0,
        'addtimequestionenable' => 0,
        'groupmode' => 0,
        'status' => 0,
        'sessionid' => 0,
        'submitbutton' => 0
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
        $this->sessionmock['jqshowid'] = $jqshow->id;
        $sessiontest = new sessions_test();
        $sessiontest->test_session($jqshow);
        $list = $sessiontest->sessions->get_list();
        $result = deletesession_external::deletesession($course->id, $list[0]->get('id'));
        $this->assertIsArray($result);
        $this->assertTrue($result['deleted']);
        $sessiontest->sessions->set_list();
        $newlist = $sessiontest->sessions->get_list();
        $this->assertCount(0, $newlist);

        $student = self::getDataGenerator()->create_and_enrol($course);
        self::setUser($student);
        $sessiontest->test_session($jqshow);
        $newlist = $sessiontest->sessions->get_list();
        $result = deletesession_external::deletesession($course->id, $newlist[0]->get('id'));
        $this->assertIsArray($result);
        $this->assertFalse($result['deleted']);
        $this->assertCount(1, $newlist);

        return true;
    }
}
