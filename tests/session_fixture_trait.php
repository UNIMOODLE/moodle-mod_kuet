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

namespace mod_kuet;

use mod_kuet\models\sessions;

/**
 * Session fixture shared by the tests
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait session_fixture_trait {
    /**
     * The session record the tests feed to mod_kuet_generator::create_session().
     *
     * A method and not a property on a test class: reading the fixture used to mean
     * instantiating another test case, and since PHPUnit 11 the TestCase constructor
     * requires the test name, so that stopped working on Moodle 5.
     *
     * @param array $overrides Values to replace, typically kuetid and sessionmode.
     * @return array
     */
    private function session_fixture(array $overrides = []): array {
        return array_merge([
            'name' => 'Session Test',
            'kuetid' => 0,
            'anonymousanswer' => 0,
            'sessionmode' => sessions::PODIUM_MANUAL,
            'sgrade' => 0,
            'countdown' => 0,
            'showgraderanking' => 0,
            'randomquestions' => 0,
            'randomanswers' => 0,
            'showfeedback' => 0,
            'showfinalgrade' => 0,
            'startdate' => 1680534000,
            'enddate' => 1683133200,
            'automaticstart' => 0,
            'timemode' => 0,
            'sessiontime' => 0,
            'questiontime' => 10,
            'groupings' => 0,
            'status' => 1,
            'sessionid' => 0,
            'submitbutton' => 0,
        ], $overrides);
    }
}
