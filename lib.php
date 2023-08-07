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

use core_calendar\action_factory;
use core_calendar\local\event\value_objects\action;
use core_completion\api;
use mod_jqshow\api\grade;

require_once($CFG->dirroot . '/lib/gradelib.php');

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return true|null True if module supports feature, false if not, null if doesn't know
 */
function jqshow_supports(string $feature): ?bool {
    switch($feature) {
        case FEATURE_GROUPINGS:
        case FEATURE_MOD_INTRO:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_CONTROLS_GRADE_VISIBILITY:
        case FEATURE_USES_QUESTIONS:
        case FEATURE_GROUPS:
            return true;
        default:
            return null;
    }
}
/**
 *
 * @param stdClass $data An object from the form in mod.html.
 * @return int The id of the newly inserted jqshow record.
 * @throws dml_exception
 */
function jqshow_add_instance(stdClass $data): int {
    global $DB;

    $cmid = $data->coursemodule;
    $id = $DB->insert_record('jqshow', $data);

    // Update course module record - from now on this instance properly exists and all function may be used.
    $DB->set_field('course_modules', 'instance', $id, array('id' => $cmid));

    // Reload scorm instance.
    $record = $DB->get_record('jqshow', array('id' => $id));

    if (!empty($data->completionexpected)) {
        api::update_completion_date_event($cmid, 'jqshow', $record, $data->completionexpected);
    }

    mod_jqshow_grade_item_update($data, null);
    return $record->id;
}

/**
 *
 * @param stdClass $data An object from the form in mod.html
 * @return boolean Success/Fail
 * @throws coding_exception
 * @throws dml_exception
 */
function jqshow_update_instance(stdClass $data): bool {
    global $DB;

    // Get the current value, so we can see save changes.
    $oldjqshow = $DB->get_record('jqshow', array('id' => $data->instance));

    // Update the database.
    $oldjqshow->name = $data->name;
    $oldjqshow->teamgrade = isset($data->teamgrade) ? $data->teamgrade : null;
    $oldjqshow->grademethod = $data->grademethod;
    if (!isset($data->completionanswerall) || $data->completionanswerall === null) {
        $oldjqshow->completionanswerall = 0;
    } else {
        $oldjqshow->completionanswerall = $data->completionanswerall;
    }
    $DB->update_record('jqshow', $oldjqshow);

    grade::recalculate_mod_mark($data->{'update'}, $data->instance);
    mod_jqshow_grade_item_update($data, null);
    return true;
}
/**
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function jqshow_delete_instance(int $id): bool {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/lib/gradelib.php');
    try {
        $jqshow = $DB->get_record('jqshow', ['id' => $id], '*', MUST_EXIST);
        if (!$jqshow) {
            return false;
        }
        // Finally delete the jqshow object.
        $DB->delete_records('jqshow', ['id' => $id]);
        $DB->delete_records('jqshow_grades', ['jqshow' => $id]);
        $DB->delete_records('jqshow_questions', ['jqshowid' => $id]);
        $DB->delete_records('questions_responses', ['jqid' => $id]);
        $DB->delete_records('jqshow_sessions', ['jqshowid' => $id]);
        $DB->delete_records('jqshow_sessions_grades', ['jqshow' => $id]);
        $DB->delete_records('jqshow_user_progress', ['jqshow' => $id]);

        grade_update('mod/assign',
            $jqshow->course,
            'mod',
            'jqshow',
            $jqshow->id,
            0,
            null,
            ['deleted' => 1]);
        return true;
    } catch (Exception $e) {
        throw new moodle_exception('error_delete_instance', 'mod_jqshow', '', $e->getMessage());
        return false;
    }
}

/**
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info|bool
 * @throws dml_exception
 */
function jqshow_get_coursemodule_info(stdClass $coursemodule): ?cached_cm_info {
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
    if ((int)$coursemodule->completion === COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionanswerall'] = $jqshow->completionanswerall;
    }

    return $result;
}

/**
 * @param cm_info|stdClass $cm
 * @return array $descriptions the array of descriptions for the custom rules.
 * @throws coding_exception
 */
function mod_jqshow_get_completion_active_rule_descriptions($cm): array {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || (int)$cm->completion !== COMPLETION_TRACKING_AUTOMATIC) {
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

/**
 * @param $server
 * @return void
 */
function run_server_background($server) {
    switch (strtolower(PHP_OS_FAMILY)) {
        case "windows":
            pclose(popen("start /B php $server", "r"));
            break;
        case "linux":
            exec("php $server > /dev/null &");
            break;
        default:
            debugging("Unsupported OS" . strtolower(PHP_OS_FAMILY));
            break;
    }
}

/**
 * @throws moodle_exception
 * @throws coding_exception
 */
function mod_jqshow_core_calendar_provide_event_action(
    calendar_event $event,
    action_factory $factory,
    int $userid = 0
): ?action {
    $cm = get_fast_modinfo($event->courseid, $userid)->instances['jqshow'][$event->instance];

    if (!$cm->uservisible) {
        // The module is not visible to the user for any reason.
        return null;
    }

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate !== COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new moodle_url('/mod/jqshow/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * @param settings_navigation $settings
 * @param navigation_node $navref
 * @return void
 * @throws coding_exception
 * @throws moodle_exception
 */
function jqshow_extend_settings_navigation(settings_navigation $settings, navigation_node $navref) {
    $cm = $settings->get_page()->cm;
    if (!$cm) {
        return;
    }
    $course = $settings->get_page()->course;
    if (!$course) {
        return;
    }
    $url = new moodle_url('/mod/jqshow/reports.php', ['cmid' => $settings->get_page()->cm->id]);
    $node = navigation_node::create(get_string('reports', 'mod_jqshow'),
        $url,
        navigation_node::TYPE_SETTING, null, 'mmod_jqshow_reports');
    $navref->add_node($node, 'modedit');
}

/**
 * @param $password
 * @param $text
 * @return string|null
 */
function encrypt($password, $text) {
    $base64 = base64_encode($text);
    $arr = str_split($base64);
    $arrpass = str_split($password);
    $lastpassletter = 0;
    $encrypted = '';
    foreach ($arr as $value) {
        $letter = $value;
        $passwordletter = $arrpass[$lastpassletter];
        $temp = get_letter_from_alphabet_for_letter($passwordletter, $letter);
        if ($temp !== null) {
            $encrypted .= $temp;
        } else {
            return null;
        }
        if ($lastpassletter === (count($arrpass) - 1)) {
            $lastpassletter = 0;
        } else {
            $lastpassletter++;
        }
    }
    return $encrypted;
}


/**
 * @param $letter
 * @param $lettertochange
 * @return mixed|null
 */
function get_letter_from_alphabet_for_letter($letter, $lettertochange) {
    // TODO it is possible that new characters will pop up as errors. In this case, add here and in js.
    $abc = 'abcdefghijklmnopqrstuvwxyz0123456789=ABCDEFGHIJKLMNOPQRSTUVWXYZ/+-*';
    $posletter = strpos($abc, $letter);
    if ($posletter === false) {
        return null;
    }
    $poslettertochange = strpos($abc, $lettertochange);
    if ($poslettertochange === false) {
        return null;
    }
    $part1 = substr($abc, $posletter, strlen($abc));
    $part2 = substr($abc, 0, $posletter);
    $newabc = $part1 . $part2;
    $temp = str_split($newabc);
    return $temp[$poslettertochange];
}

function jqshow_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = []) {
    global $DB;
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    if ($filearea != 'question') {
        return false;
    }
    require_course_login($course, true, $cm);

    $questionid = (int)array_shift($args);
    $quiz = $DB->get_record('jqshow', ['id' => $cm->instance]);
    if (!$quiz) {
        return false;
    }
    $question = $DB->get_record('jqshow_questions', [
        'questionid' => $questionid,
        'jqshowid' => $cm->instance
    ]);
    if (!$question) {
        return false;
    }
    $fs = get_file_storage();
    $relative = implode('/', $args);
    $fullpath = "/$context->id/mod_jqshow/$filearea/$questionid/$relative";
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file);
    return false;
}

function mod_jqshow_question_pluginfile($course, $context, $component, $filearea, $qubaid, $slot,
                                          $args, $forcedownload, $options = []) {
    $fs = get_file_storage();
    $relative = implode('/', $args);
    $full = "/$context->id/$component/$filearea/$relative";
    $file = $fs->get_file_by_hash(sha1($full));
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * @return array
 * @throws coding_exception
 */
function mod_jqshow_get_grading_options() {
    return [
        \mod_jqshow\api\grade::MOD_OPTION_NO_GRADE => get_string('nograde', 'mod_jqshow'),
        \mod_jqshow\api\grade::MOD_OPTION_GRADE_HIGHEST => get_string('gradehighest', 'mod_jqshow'),
        \mod_jqshow\api\grade::MOD_OPTION_GRADE_AVERAGE => get_string('gradeaverage', 'mod_jqshow'),
        \mod_jqshow\api\grade::MOD_OPTION_GRADE_FIRST_SESSION => get_string('firstsession', 'mod_jqshow'),
        \mod_jqshow\api\grade::MOD_OPTION_GRADE_LAST_SESSION => get_string('lastsession', 'mod_jqshow')
    ];
}

/**
 * Update/create grade item for given data
 *
 * @category grade
 * @param stdClass $data A jqshow instance
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return object grade_item
 */
function mod_jqshow_grade_item_update(stdClass $data, $grades = null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $params = ['itemname' => $data->name];
    if (property_exists($data, 'cmidnumber')) { // May not be always present.
        $params['idnumber'] = $data->cmidnumber;
    }

    if ($data->grademethod == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;
    } else {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = get_config('core', 'gradepointmax');;
        $params['grademin'] = 0;
    }
    if (is_null($grades)) {
        $params['reset'] = true;
    }
    return grade_update('mod/jqshow', $data->course, 'mod', 'jqshow', $data->id, 0, $grades, $params);

}

/**
 * @param $questionids
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 */
function jqshow_questions_in_use($questionids) {
    global $DB;
    [$sqlfragment, $params] = $DB->get_in_or_equal($questionids);
    $params['component'] = 'mod_jqshow';
    $params['questionarea'] = 'slot';
    $sql = "SELECT jq.id
              FROM {jqshow_questions} jq
             WHERE jq.questionid $sqlfragment";
    return $DB->record_exists_sql($sql, $params);
}
