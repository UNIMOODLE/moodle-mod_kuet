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
 * Kuet session grades persistent
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\persistents;

use core\persistent;
use dml_exception;

/**
 * Kuet session grades persistent class
 */
class kuet_sessions_grades extends persistent {
    /**
     * @var string kuet sessions grades table
     */
    const TABLE = 'kuet_sessions_grades';
    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() :array {
        return [
            'kuet' => [
                'type' => PARAM_INT,
            ],
            'session' => [
                'type' => PARAM_INT,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'grade' => [
                'type' => PARAM_FLOAT,
            ]
        ];
    }

    /**
     * Get user grades
     *
     * @param int $kuetid
     * @param int $userid
     * @return kuet_sessions_grades[]
     */
    public static function get_grades_for_user(int $kuetid, int $userid): array {
        return self::get_records(['kuet' => $kuetid, 'userid' => $userid]);
    }

    /**
     * Get user grades from a session
     *
     * @param int $session
     * @param int $userid
     * @return kuet_sessions_grades
     */
    public static function get_grade_for_session_user(int $session, int $userid): kuet_sessions_grades {
        return self::get_record(['session' => $session, 'userid' => $userid], MUST_EXIST);
    }

    /**
     * Delete session grades
     *
     * @param int $sid
     * @return bool
     * @throws dml_exception
     */
    public static function delete_session_grades(int $sid): bool {
        global $DB;
        return  $DB->delete_records(self::TABLE, ['session' => $sid]);
    }
}
