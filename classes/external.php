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

use core_course\external\helper_for_get_mods_by_courses;

defined('MOODLE_INTERNAL') || die;
global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_jqshow_external extends external_api {

    /**
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_jqshows_by_courses_parameters() {
        return new external_function_parameters (
            [
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course id'), 'Array of course ids', VALUE_DEFAULT, []
                ),
            ]
        );
    }

    /**
     *
     * @param array $courseids course ids
     * @return array of warnings and jqshows
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @since Moodle 3.3
     */
    public static function get_jqshows_by_courses($courseids = []) {

        $warnings = [];
        $returnedjqshows = [];
        $params = [
            'courseids' => $courseids,
        ];
        $params = self::validate_parameters(self::get_jqshows_by_courses_parameters(), $params);

        $mycourses = [];
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {
            [$courses, $warnings] = external_util::validate_courses($params['courseids'], $mycourses);

            // Get the jqshows in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $jqshows = get_all_instances_in_courses("jqshow", $courses);
            foreach ($jqshows as $jqshow) {
                helper_for_get_mods_by_courses::format_name_and_intro($jqshow, 'mod_jqshow');
                $returnedjqshows[] = $jqshow;
            }
        }

        return [
            'jqshows' => $returnedjqshows,
            'warnings' => $warnings
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function get_jqshows_by_courses_returns() {
        return new external_single_structure(
            array(
                'jqshows' => new external_multiple_structure(
                    new external_single_structure(array_merge(
                        helper_for_get_mods_by_courses::standard_coursemodule_elements_returns(),
                        [
                            'timemodified' => new external_value(PARAM_INT, 'Last time the jqshow was modified'),
                        ]
                    ))
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
