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
use context_module;
use dml_exception;

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @category   test
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator_test extends advanced_testcase {
    /**
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_generator() {
        global $DB;
        $this->resetAfterTest(true);
        $this->assertEquals(0, $DB->count_records('jqshow'));
        $course = self::getDataGenerator()->create_course();

        $generator = self::getDataGenerator()->get_plugin_generator('mod_jqshow');
        $this->assertInstanceOf('mod_jqshow_generator', $generator);
        $this->assertEquals('jqshow', $generator->get_modulename());

        $generator->create_instance(['course' => $course->id]);
        $generator->create_instance(['course' => $course->id]);
        $jqshow = $generator->create_instance(['course' => $course->id]);
        $this->assertEquals(3, $DB->count_records('jqshow'));

        $cm = get_coursemodule_from_instance('jqshow', $jqshow->id);
        $this->assertEquals($jqshow->id, $cm->instance);
        $this->assertEquals('jqshow', $cm->modname);
        $this->assertEquals($course->id, $cm->course);

        $context = context_module::instance($cm->id);
        $this->assertEquals($jqshow->cmid, $context->instanceid);
    }

    public function test_create_session() {
        // TODO.
    }
}
