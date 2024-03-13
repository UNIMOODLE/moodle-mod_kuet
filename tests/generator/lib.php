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
 * Kuet test generator library
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Kuet test generator library class
 */
class mod_kuet_generator extends testing_module_generator {

    /**
     * Create instance
     *
     * @param $record
     * @param array|null $options
     * @return stdClass
     * @throws coding_exception
     */
    public function create_instance($record = null, array $options = null) {
        $record = (array)$record;
        $record['showdescription'] = 1;
        $record['grademethod'] = 0;
        $record['completionanswerall'] = 0;
        return parent::create_instance($record, $options);
    }

    /**
     * Create session
     *
     * @param stdClass $kuet
     * @return int
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     */
    public function create_session(stdClass $kuet, stdClass $sessionmock) {
        $sessions = new \mod_kuet\models\sessions($kuet, $kuet->cmid);
        return $sessions::save_session($sessionmock);
    }

    /**
     * Add questions to a session
     *
     * @param array $questions
     * @return bool[]
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function add_questions_to_session(array $questions) {
        return \mod_kuet\external\addquestions_external::add_questions($questions);
    }
}
