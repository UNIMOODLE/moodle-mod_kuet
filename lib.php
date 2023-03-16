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
//        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_CONTROLS_GRADE_VISIBILITY: return true;
        case FEATURE_USES_QUESTIONS:            return true;

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
/**
 * Add a get_coursemodule_info function in case any jqshow type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function jqshow_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionanswerall';
    if (!$jqshow = $DB->get_record('jqshow', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $jqshow->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('jqshow', $jqshow, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionanswerall'] = $jqshow->completionanswerall;
    }

    return $result;
}
/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_jqshow_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionanswerall':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionanswerall', 'jqshow');
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}