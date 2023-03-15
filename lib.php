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
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function jqshow_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}
/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param \stdClass $jqshow An object from the form in mod.html
 * @return int The id of the newly inserted jqshow record
 * @throws dml_exception
 */
function jqshow_add_instance($data) {
    global $CFG, $DB;


    $cmid       = $data->coursemodule;
    $cmidnumber = $data->cmidnumber;
    $courseid   = $data->course;

    $context = context_module::instance($cmid);

    $id = $DB->insert_record('jqshow', $data);

    // Update course module record - from now on this instance properly exists and all function may be used.
    $DB->set_field('course_modules', 'instance', $id, array('id' => $cmid));

    // Reload scorm instance.
    $record = $DB->get_record('jqshow', array('id' => $id));


    if (!empty($data->completionexpected)) {
        \core_completion\api::update_completion_date_event($cmid, 'jqshow', $record, $data->completionexpected);
    }

    return $record->id;
}
/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 *
 * @param \stdClass $jqshow An object from the form in mod.html
 * @return boolean Success/Fail
 * @throws dml_exception
 */
function jqshow_update_instance($data) {
    global $DB;

    // Get the current value, so we can see what changed.
    $oldjqshow = $DB->get_record('jqshow', array('id' => $data->instance));

    // Update the database.
//    $oldjqshow->id = $data->instance;
    $oldjqshow->name = $data->name;
    $DB->update_record('jqshow', $oldjqshow);

    return true;
}
/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function jqshow_delete_instance($id) {
    global $DB, $CFG;

    try {
        $jqshow = $DB->get_record('jqshow', ['id' => $id], '*', MUST_EXIST);
        //TODO: remove other data.
        // Finally delete the jqshow object.
        $DB->delete_records('jqshow', ['id' => $id]);
    } catch (Exception $e) {
        return false;
    }
    return true;
}