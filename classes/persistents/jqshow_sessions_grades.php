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

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jqshow\persistents;

use core\persistent;

class jqshow_sessions_grades extends persistent {
    const TABLE = 'jqshow_sessions_grades';
    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'jqshow' => array(
                'type' => PARAM_INT,
            ),
            'session' => array(
                'type' => PARAM_INT,
            ),
            'userid' => array(
                'type' => PARAM_INT,
            ),
            'grade' => array(
                'type' => PARAM_FLOAT,
            )
        );
    }

    /**
     * @param int $jqshowid
     * @param int $userid
     * @return jqshow_sessions_grades[]
     */
    public static function get_grades_for_user(int $jqshowid, int $userid): array {
        return self::get_records(['jqshow' => $jqshowid, 'userid' => $userid]);
    }

    /**
     * @param int $session
     * @param int $userid
     * @return jqshow_sessions_grades
     */
    public static function get_grade_for_session_user(int $session, int $userid): jqshow_sessions_grades {
        return self::get_record(['session' => $session, 'userid' => $userid], MUST_EXIST);
    }
}
