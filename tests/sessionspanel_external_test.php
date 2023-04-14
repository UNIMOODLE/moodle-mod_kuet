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
use mod_jqshow\external\sessionspanel_external;
use mod_jqshow\models\sessions;
use moodle_exception;
use sessions_test;
use mod_jqshow\persistents\jqshow_sessions;

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
class sessionspanel_external_test extends advanced_testcase {

    public array $sessionmock = [
        'name' => 'Session Test External',
        'anonymousanswer' => sessions::ANONYMOUS_ANSWERS,
        'allowguests' => 0,
        'advancemode' => sessions::ADVANCE_MODE_PROGRAMMED,
        'gamemode' => sessions::GAME_MODE_INACTIVE,
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
     * @throws moodle_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     */
    public function test_sessionspanel(): bool {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('jqshow', ['course' => $course->id]);
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);
        $sessiontest = new sessions_test();
        $sessiontest->test_session($jqshow);
        $this->sessionmock['jqshowid'] = $jqshow->id;
        $createdsid = $sessiontest->sessions::save_session((object)$this->sessionmock);
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
        $this->assertCount(1, $result['activesessions']);
        $this->assertCount(1, $result['endedsessions']);
        $this->assertSame($course->id, $result['courseid']);
        $this->assertSame($jqshow->cmid, $result['cmid']);

        return true;
    }
}
