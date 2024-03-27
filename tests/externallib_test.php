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
 * External library test
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet;

use coding_exception;
use context_module;
use dml_exception;
use external_api;
use externallib_advanced_testcase;
use file_exception;
use invalid_parameter_exception;
use invalid_response_exception;
use mod_kuet_external;
use stdClass;
use stored_file_creation_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * External library test class
 */
class externallib_test extends externallib_advanced_testcase {

    /**
     * Get kuet instances by courses
     *
     * @return void
     * @throws invalid_parameter_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws file_exception
     * @throws invalid_response_exception
     * @throws stored_file_creation_exception
     */
    public function test_mod_kuet_get_kuets_by_courses() {
        global $DB;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        $student = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        self::getDataGenerator()->enrol_user($student->id, $course1->id, $studentrole->id);

        // First kuet.
        $record = new stdClass();
        $record->course = $course1->id;
        $kuet1 = self::getDataGenerator()->create_module('kuet', $record);

        // Second kuet.
        $record = new stdClass();
        $record->course = $course2->id;
        $kuet2 = self::getDataGenerator()->create_module('kuet', $record);

        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol === 'manual') {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $student->id, $studentrole->id);

        self::setUser($student);

        $returndescription = \mod_kuet_external::get_kuets_by_courses_returns();

        // Create what we expect to be returned when querying the two courses.
        $expectedfields = ['id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'introfiles', 'timemodified',
            'section', 'visible', 'groupmode', 'groupingid', 'lang'];

        // Add expected coursemodule and data.
        $kuet1->coursemodule = $kuet1->cmid;
        $kuet1->introformat = 1;
        $kuet1->section = 0;
        $kuet1->visible = true;
        $kuet1->groupmode = 0;
        $kuet1->groupingid = 0;
        $kuet1->introfiles = [];
        $kuet1->lang = '';

        $kuet2->coursemodule = $kuet2->cmid;
        $kuet2->introformat = 1;
        $kuet2->section = 0;
        $kuet2->visible = true;
        $kuet2->groupmode = 0;
        $kuet2->groupingid = 0;
        $kuet2->introfiles = [];
        $kuet2->lang = '';

        foreach ($expectedfields as $field) {
            $expected1[$field] = $kuet1->{$field};
            $expected2[$field] = $kuet2->{$field};
        }

        $expectedkuets = [$expected2, $expected1];

        // Call the external function passing course ids.
        $result = mod_kuet_external::get_kuets_by_courses([$course2->id, $course1->id]);
        $result = external_api::clean_returnvalue($returndescription, $result);

        $this->assertEquals($expectedkuets, $result['kuets']);
        $this->assertCount(0, $result['warnings']);

        // Call the external function without passing course id.
        $result = mod_kuet_external::get_kuets_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedkuets, $result['kuets']);
        $this->assertCount(0, $result['warnings']);

        // Add a file to the intro.
        $filename = "file.txt";
        $filerecordinline = [
            'contextid' => context_module::instance($kuet2->cmid)->id,
            'component' => 'mod_kuet',
            'filearea'  => 'intro',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');

        $result = mod_kuet_external::get_kuets_by_courses(array($course2->id, $course1->id));
        $result = external_api::clean_returnvalue($returndescription, $result);

        $this->assertCount(1, $result['kuets'][0]['introfiles']);
        $this->assertEquals($filename, $result['kuets'][0]['introfiles'][0]['filename']);

        // Unenrol user from second course.
        $enrol->unenrol_user($instance2, $student->id);
        array_shift($expectedkuets);

        // Call the external function without passing course id.
        $result = mod_kuet_external::get_kuets_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedkuets, $result['kuets']);

        // Call for the second course we unenrolled the user from, expected warning.
        $result = mod_kuet_external::get_kuets_by_courses(array($course2->id));
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('1', $result['warnings'][0]['warningcode']);
        $this->assertEquals($course2->id, $result['warnings'][0]['itemid']);
    }
}
