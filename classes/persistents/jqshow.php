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

namespace mod_jqshow\persistents;
use core\persistent;
use moodle_exception;

class jqshow extends persistent {
    public const TABLE = 'jqshow';

    /**
     * @return array[]
     */
    protected static function define_properties() : array {
        return [
            'course' => [
                'type' => PARAM_INT,
            ],
            'name' => [
                'type' => PARAM_RAW,
            ],
            'intro' => [
                'type' => PARAM_RAW,
            ],
            'introformat' => [
                'type' => PARAM_INT,
            ],
            'teamgrade' => [
                'type' => PARAM_RAW,
            ],
            'grademethod' => [
                'type' => PARAM_INT,
            ],
            'completionanswerall' => [
                'type' => PARAM_INT,
            ],
            'usermodified' => [
                'type' => PARAM_INT,
            ]
        ];
    }

    /**
     * Get persisten from course module id.
     * @param int $cmid
     * @return false|jqshow
     * @throws moodle_exception
     */
    public static function get_jqshow_from_cmid(int $cmid) {
        list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'jqshow');
        return self::get_record(['id' => (int) $cm->instance, 'course' => $course->id]);
    }
}
