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
