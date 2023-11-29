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
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet;

use advanced_testcase;
use coding_exception;
use context_module;
use dml_exception;

class generator_test extends advanced_testcase {
    /**
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_generator() {
        global $DB;
        $this->resetAfterTest(true);
        $this->assertEquals(0, $DB->count_records('kuet'));
        $course = self::getDataGenerator()->create_course();

        $generator = self::getDataGenerator()->get_plugin_generator('mod_kuet');
        $this->assertInstanceOf('mod_kuet_generator', $generator);
        $this->assertEquals('kuet', $generator->get_modulename());

        $generator->create_instance(['course' => $course->id]);
        $generator->create_instance(['course' => $course->id]);
        $jqshow = $generator->create_instance(['course' => $course->id]);
        $this->assertEquals(3, $DB->count_records('kuet'));

        $cm = get_coursemodule_from_instance('kuet', $jqshow->id);
        $this->assertEquals($jqshow->id, $cm->instance);
        $this->assertEquals('kuet', $cm->modname);
        $this->assertEquals($course->id, $cm->course);

        $context = context_module::instance($cm->id);
        $this->assertEquals($jqshow->cmid, $context->instanceid);
    }

    public function test_create_session() {
        // TODO.
    }
}
