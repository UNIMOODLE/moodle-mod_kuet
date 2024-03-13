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
 * Copy session test
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use core\invalid_persistent_exception;
use mod_kuet\external\copysession_external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/kuet/tests/sessions_test.php');

/**
 * Copy session test class
 */
class copysession_external_test extends advanced_testcase {

    /**
     * Copy session test
     *
     * @return true
     * @throws invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public function test_copysession(): bool {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);
        $sessiontest = new sessions_test();
        $sessiontest->test_session();
        $list = $sessiontest->sessions->get_list();
        $result = copysession_external::copysession($course->id, $kuet->cmid, $list[0]->get('id'));
        $this->assertIsArray($result);
        $this->assertTrue($result['copied']);
        $sessiontest->sessions->set_list();
        $newlist = $sessiontest->sessions->get_list();
        $this->assertCount(2, $newlist);

        $student = self::getDataGenerator()->create_and_enrol($course);
        self::setUser($student);
        $result = copysession_external::copysession($course->id, $kuet->cmid, $newlist[0]->get('id'));
        $this->assertIsArray($result);
        $this->assertFalse($result['copied']);

        return true;
    }
}
