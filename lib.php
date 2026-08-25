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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos..

/**
 * Kuet library
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <juanpablo.decastro@uva.es>
 * @author     Juan Pablo de Castro  <juan.pablo.de.castro@gmail.com>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\invalid_persistent_exception;
use core_calendar\action_factory;
use core_calendar\local\event\value_objects\action;
use core_completion\api;
use mod_kuet\api\grade;
use mod_kuet\persistents\kuet;
defined('MOODLE_INTERNAL') || die;
global $CFG;
require_once($CFG->dirroot . '/lib/gradelib.php');

/**
 * Kuet supports
 *
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
function kuet_supports(string $feature): ?bool {
    switch ($feature) {
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
 * Add kuet instance
 *
 * @param stdClass $data An object from the form in mod.html.
 * @return int The id of the newly inserted kuet record.
 * @throws dml_exception
 */
function kuet_add_instance(stdClass $data): int {
    global $DB, $USER;

    $cmid = $data->coursemodule;

    // Create kuet on db.
    $record = new stdClass();
    $record->course = $data->course;
    $record->name = $data->name;
    $record->intro = $data->intro;
    $record->introformat = $data->introformat;
    $record->teamgrade = isset($data->teamgrade) ?? $data->teamgrade;
    $record->grademethod = $data->grademethod;
    $record->sessiongrademax = $data->sessiongrademax ?? get_config('core', 'gradepointmax');
    $record->completionanswerall = $data->completionanswerall ?? 0;
    $record->usermodified = $USER->id;
    $kuet = new kuet(0, $record);
    $kuet->create();
    $data->id  = $kuet->get('id');

    // Update course module record - from now on this instance properly exists and all function may be used.
    $DB->set_field('course_modules', 'instance', $data->id, ['id' => $cmid]);

    if (!empty($data->completionexpected)) {
        api::update_completion_date_event($cmid, 'kuet', $kuet->to_record(), $data->completionexpected);
    }

    mod_kuet_grade_item_update($data, null);
    return $data->id;
}

/**
 * Update kuet instance
 *
 * @param stdClass $data An object from the form in mod.html
 * @return bool Success/Fail
 * @throws invalid_persistent_exception
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function kuet_update_instance(stdClass $data): bool {
    global $USER;

    $teamgrade = isset($data->teamgrade) ?? $data->teamgrade;
    // Update kuet record.
    $kuet = new kuet($data->instance);
    $kuet->set('name', $data->name);
    $kuet->set('intro', $data->intro);
    $kuet->set('introformat', $data->introformat);
    $kuet->set('teamgrade', $teamgrade);
    $kuet->set('grademethod', $data->grademethod);
    $kuet->set('sessiongrademax', $data->sessiongrademax ?? get_config('core', 'gradepointmax'));
    $kuet->set('completionanswerall', $data->completionanswerall ?? 0);
    $kuet->set('usermodified', $USER->id);
    $kuet->update();

    grade::recalculate_mod_mark($data->{'update'}, $data->instance);
    $data->id = $data->instance;
    mod_kuet_grade_item_update($data, null);

    return true;
}

/**
 * Delete kuet instance
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 * @throws dml_exception
 */
function kuet_delete_instance(int $id): bool {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/lib/gradelib.php');
    $kuet = $DB->get_record('kuet', ['id' => $id], '*', MUST_EXIST);
    if (!$kuet) {
        return false;
    }
    $cm = get_coursemodule_from_instance('kuet', $id);
    if ($cm) {
        $usageids = $DB->get_fieldset_select('question_usages', 'id',
            'component = ? AND contextid = ?', ['mod_kuet', context_module::instance($cm->id)->id]);
        foreach ($usageids as $usageid) {
            question_engine::delete_questions_usage_by_activity($usageid);
        }
    }
    // Finally delete the kuet object.
    $DB->delete_records('kuet', ['id' => $id]);
    $DB->delete_records('kuet_grades', ['kuet' => $id]);
    \mod_kuet\question\version_resolver::delete_references(
        $DB->get_fieldset_select('kuet_questions', 'id', 'kuetid = ?', [$id]));
    $DB->delete_records('kuet_questions', ['kuetid' => $id]);
    $DB->delete_records('kuet_questions_responses', ['kuet' => $id]);
    $DB->delete_records('kuet_sessions', ['kuetid' => $id]);
    $DB->delete_records('kuet_sessions_grades', ['kuet' => $id]);
    $DB->delete_records('kuet_user_progress', ['kuet' => $id]);

    grade_update(
        'mod/kuet',
        $kuet->course,
        'mod',
        'kuet',
        $id,
        0,
        null,
        ['deleted' => 1]
    );
    return true;
}

/**
 * Get course module information
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info|bool
 * @throws \dml_exception
 */
function kuet_get_coursemodule_info(stdClass $coursemodule): ?cached_cm_info {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionanswerall';
    if (!$kuet = $DB->get_record('kuet', $dbparams, $fields)) {
        return null;
    }

    $result = new cached_cm_info();
    $result->name = $kuet->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('kuet', $kuet, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ((int)$coursemodule->completion === COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionanswerall'] = $kuet->completionanswerall;
    }

    return $result;
}

/**
 * Get completion active rule descriptions
 *
 * @param cm_info|stdClass $cm
 * @return array $descriptions the array of descriptions for the custom rules.
 * @throws coding_exception
 */
function mod_kuet_get_completion_active_rule_descriptions($cm): array {
    // Values will be present in cm_info, and we assume these are up to date.
    if (
        empty($cm->customdata['customcompletionrules'])
        || (int)$cm->completion !== COMPLETION_TRACKING_AUTOMATIC
    ) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionanswerall':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionansweralldesc', 'kuet');
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}
/**
 * Kills server process.
 * @param $pid
 * @return bool Success/Failure
 */
function mod_kuet_kill_server($pid): bool {
    switch (strtolower(PHP_OS_FAMILY)) {
        case "windows":
            $result = shell_exec("taskkill /F /PID $pid");
            break;
        case "linux":
            $result = shell_exec("kill -9 $pid");
            break;
        default:
            debugging("Unsupported OS" . strtolower(PHP_OS_FAMILY));
            return false;
    }
    // Wait a moment to let the process die.
    sleep(2);
    // Check if process is still running.
    $pidcheck = mod_kuet_get_server_pid();
    return $pidcheck === false || empty($pidcheck);
}

/**
 * Run websocket server on background
 *
 * @param $server
 * @return void
 */
function mod_kuet_run_server_background() {
    global $CFG;
    $server = $CFG->dirroot . '/mod/kuet/unimoodleservercli.php';
    // Check if server is already running.
    // If the server is running, we do not need to start it again.
    $pid = mod_kuet_get_server_pid();
    if (!empty($pid)) {
        // Server is already running, do not start it again.
        return;
    }

    $certificateurl = '';
    $privatekeyurl = '';
    $port = get_config('kuet', 'localport');
    // Get log file path in temporary directory.
    $logfile = $CFG->tempdir . '/kuet/unimoodleserver.log';
    // Get verbose mode.
    $verboselog = get_config('kuet', 'verboselog') ? "-v" : "";

    if (!file_exists($logfile)) {
        // Create log file if it does not exist.
        if (!file_exists($CFG->tempdir . '/kuet')) {
            mkdir($CFG->tempdir . '/kuet', 0770, true);
        }
        touch($logfile);
    }

    // Prepare certificate and key files.
    $syscontext = context_system::instance();
    $fs = get_file_storage();
    $certificatefiles = $fs->get_area_files($syscontext->id, 'kuet', 'certificate_ssl', 0, 'filename', false);
    foreach ($certificatefiles as $file) {
        if ($file->get_filename() !== '.') {
            file_safe_save_content($file->get_content(), $CFG->localcachedir . '/kuet/' . $file->get_filename());
            $certificateurl = $CFG->localcachedir . '/kuet/' . $file->get_filename();
            break;
        }
    }
    $privatekeyfiles = $fs->get_area_files($syscontext->id, 'kuet', 'privatekey_ssl', 0, 'filename', false);
    foreach ($privatekeyfiles as $file) {
        if ($file->get_filename() !== '.') {
            file_safe_save_content($file->get_content(), $CFG->localcachedir . '/kuet/' . $file->get_filename());
            $privatekeyurl = $CFG->localcachedir . '/kuet/' . $file->get_filename();
            break;
        }
    }
    switch (strtolower(PHP_OS_FAMILY)) {
        case "windows":
            pclose(popen("start /B php $server $port -c $certificateurl -p $privatekeyurl $verboselog", "r"));
            break;
        case "linux":
            exec("php $server $port -c $certificateurl -p $privatekeyurl $verboselog > $logfile &");
            break;
        default:
            debugging("Unsupported OS" . strtolower(PHP_OS_FAMILY));
            break;
    }
}

/**
 * Get server PID
 */
function mod_kuet_get_server_pid() {
    global $CFG;
    $server = $CFG->dirroot . '/mod/kuet/unimoodleservercli.php';
    switch (strtolower(PHP_OS_FAMILY)) {
        case "windows":
            $pid = shell_exec("tasklist /FI \"IMAGENAME eq php.exe\" /FO CSV | findstr unimoodleservercli.php");
            break;
        case "linux":
            $pid = shell_exec("ps aux | grep -i unimoodleservercli.php | grep -v grep | awk '{print $2}'");
            break;
        default:
            debugging("Unsupported OS" . strtolower(PHP_OS_FAMILY));
            return null;
    }
    return $pid;
}

/**
 * Kuet provide event action
 *
 * @param calendar_event $event
 * @param action_factory $factory
 * @param int $userid
 * @return action|null
 * @throws coding_exception
 * @throws moodle_exception
 */
function mod_kuet_core_calendar_provide_event_action(
    calendar_event $event,
    action_factory $factory,
    int $userid = 0
): ?action {
    $cm = get_fast_modinfo($event->courseid, $userid)->instances['kuet'][$event->instance];

    if (!$cm->uservisible) {
        // The module is not visible to the user for any reason.
        return null;
    }

    $completion = new completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate !== COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new moodle_url('/mod/kuet/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Extend navigatio settings
 *
 * @param settings_navigation $settings
 * @param navigation_node $navref
 * @return void
 * @throws coding_exception
 * @throws moodle_exception
 */
function kuet_extend_settings_navigation(settings_navigation $settings, navigation_node $navref) {
    $cm = $settings->get_page()->cm;
    if (!$cm) {
        return;
    }
    $course = $settings->get_page()->course;
    if (!$course) {
        return;
    }
    $url = new moodle_url('/mod/kuet/reports.php', ['cmid' => $settings->get_page()->cm->id]);
    $node = navigation_node::create(
        get_string('reports', 'mod_kuet'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'mmod_kuet_reports'
    );
    $navref->add_node($node);
}

/**
 * Kuet encryption algorithm
 *
 * @param $password
 * @param $text
 * @return string|null
 */
function kuet_encrypt($password, $text) {
    $base64 = base64_encode($text);
    $arr = str_split($base64);
    $arrpass = str_split($password);
    $lastpassletter = 0;
    $encrypted = '';
    foreach ($arr as $value) {
        $letter = $value;
        $passwordletter = $arrpass[$lastpassletter];
        $temp = kuet_get_letter_from_alphabet_for_letter($passwordletter, $letter);
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
 * Kuet aux encryption function
 *
 * @param $letter
 * @param $lettertochange
 * @return mixed|null
 */
function kuet_get_letter_from_alphabet_for_letter($letter, $lettertochange) {
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

/**
 * Plugin file definition
 *
 * @param $course
 * @param $cm
 * @param $context
 * @param $filearea
 * @param $args
 * @param $forcedownload
 * @param $options
 * @return false
 * @throws coding_exception
 * @throws dml_exception
 */
function kuet_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = []) {
    global $DB;
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    if ($filearea != 'question') {
        return false;
    }
    require_course_login($course, true, $cm);

    $questionid = (int)array_shift($args);
    $quiz = $DB->get_record('kuet', ['id' => $cm->instance]);
    if (!$quiz) {
        return false;
    }
    $question = $DB->get_record('kuet_questions', [
        'questionid' => $questionid,
        'kuetid' => $cm->instance,
    ]);
    if (!$question) {
        return false;
    }
    $fs = get_file_storage();
    $relative = implode('/', $args);
    $fullpath = "/$context->id/mod_kuet/$filearea/$questionid/$relative";
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file);
    return false;
}

/**
 * Plugin file associated to kuet question
 *
 * @param $course
 * @param $context
 * @param $component
 * @param $filearea
 * @param $qubaid
 * @param $slot
 * @param $args
 * @param $forcedownload
 * @param $options
 * @return void
 * @throws coding_exception
 * @throws moodle_exception
 */
function mod_kuet_question_pluginfile(
    $course,
    $context,
    $component,
    $filearea,
    $qubaid,
    $slot,
    $args,
    $forcedownload,
    $options = []
) {
    global $DB, $USER;
    $quba = question_engine::load_questions_usage_by_activity($qubaid);
    if ($quba->get_owning_component() !== 'mod_kuet') {
        send_file_not_found();
    }
    $usingcontext = $quba->get_owning_context();
    $cm = get_coursemodule_from_id('kuet', $usingcontext->instanceid, 0, false, MUST_EXIST);
    require_login($cm->course, false, $cm);
    require_capability('mod/kuet:view', $usingcontext);
    $qa = $quba->get_question_attempt($slot);
    $question = $qa->get_question();
    if ((int)$question->contextid !== (int)$context->id) {
        send_file_not_found();
    }
    $positions = $DB->get_records('kuet_questions', ['kuetid' => $cm->instance, 'questionid' => $question->id]);
    $manager = has_capability('mod/kuet:managesessions', $usingcontext);
    $allowed = $manager && !empty($positions);
    $feedback = $manager;
    foreach ($positions as $position) {
        $session = $DB->get_record('kuet_sessions', ['id' => $position->sessionid], '*', MUST_EXIST);
        $answered = $DB->record_exists('kuet_questions_responses', ['kid' => $position->id, 'userid' => $USER->id]);
        $progress = $DB->get_record('kuet_user_progress', ['session' => $session->id, 'userid' => $USER->id]);
        $state = $progress ? json_decode($progress->other) : null;
        $current = (int)$session->status === \mod_kuet\models\sessions::SESSION_STARTED &&
            (int)($state->currentquestion ?? 0) === (int)$position->id;
        $allowed = $allowed || $answered || $current;
        $feedback = $feedback || ($answered && !empty($session->showfeedback));
    }
    $displayoptions = new question_display_options();
    $displayoptions->feedback = $displayoptions->generalfeedback = $displayoptions->rightanswer =
        $feedback ? question_display_options::VISIBLE : question_display_options::HIDDEN;
    if (!$allowed || !$qa->check_file_access($displayoptions, $component, $filearea, $args, $forcedownload)) {
        send_file_not_found();
    }
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
 * Get grading options
 *
 * @return array
 * @throws coding_exception
 */
function mod_kuet_get_grading_options(): array {
    return [
        grade::MOD_OPTION_NO_GRADE => get_string('nograde', 'mod_kuet'),
        grade::MOD_OPTION_GRADE_HIGHEST => get_string('gradehighest', 'mod_kuet'),
        grade::MOD_OPTION_GRADE_AVERAGE => get_string('gradeaverage', 'mod_kuet'),
        grade::MOD_OPTION_GRADE_FIRST_SESSION => get_string('firstsession', 'mod_kuet'),
        grade::MOD_OPTION_GRADE_LAST_SESSION => get_string('lastsession', 'mod_kuet'),
    ];
}

/**
 * Update/create grade item for given data
 *
 * @param stdClass $data
 * @param $grades
 * @return int|null
 * @throws dml_exception
 */
function mod_kuet_grade_item_update(stdClass $data, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (!isset($data->id)) {
        return null;
    }
    $params = ['itemname' => $data->name, 'iteminstance' => $data->id];
    if (property_exists($data, 'cmidnumber')) { // May not be always present.
        $params['idnumber'] = $data->cmidnumber;
    }

    if ($data->grademethod == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;
    } else {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = get_config('core', 'gradepointmax');
        $params['grademin'] = 0;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/kuet', $data->course, 'mod', 'kuet', $data->id, 0, $grades, $params);
}

/**
 * Questions in use by Kuet or other modules
 *
 * @param $questionids
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 */
function kuet_questions_in_use($questionids): bool {
    global $DB;
    if (!$questionids) {
        return false;
    }
    [$sql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
    if ($DB->record_exists_select('kuet_questions', "questionid $sql", $params)) {
        return true;
    }
    return $DB->record_exists_sql("SELECT qr.id FROM {question_references} qr
        JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
        WHERE qr.component = 'mod_kuet' AND qr.questionarea = 'session_question'
          AND qv.questionid $sql AND (qr.version IS NULL OR qr.version = qv.version)", $params);
}

/**
 * QR code generator
 *
 * @param string $url
 * @return string
 */
function generate_kuet_qrcode(string $url): string {
    if (class_exists(core_qrcode::class)) {
        $qrcode = new core_qrcode($url);
        return 'data:image/png;base64,' . base64_encode($qrcode->getBarcodePngData(15, 15));
    }
    return '';
}
/**
 * Get grades for an user in a kuet instance.
 * @param int $kuetid
 * @param int $userid
 * @return float
 */
function mod_kuet_get_user_grades(int $kuetid, int $userid): float {
    $kuetgrade = new \mod_kuet\persistents\kuet_grades();
    $pgrade = $kuetgrade::get_record(['kuet' => $kuetid, 'userid' => $userid]);
    if (!$pgrade) {
        return 0;
    }
    return $pgrade->get('grade');
}

/**
 * Build an authorised question selector for a KUET session.
 * @param array $args Fragment arguments; the view class is never supplied by the client.
 * @return \mod_kuet\question\bank\custom_view
 */
function mod_kuet_question_bank_view(array $args): \mod_kuet\question\bank\custom_view {
    global $CFG;
    require_once($CFG->dirroot . '/question/editlib.php');
    [$params, $extra] = \core_question\local\bank\filter_condition_manager::extract_parameters_from_fragment_args($args);
    $kuetcmid = clean_param($args['kuetcmid'] ?? $extra['kuetcmid'] ?? 0, PARAM_INT);
    $sid = clean_param($args['sessionid'] ?? $extra['sessionid'] ?? 0, PARAM_INT);
    $bankcmid = clean_param($args['bankcmid'] ?? $args['cmid'] ?? 0, PARAM_INT);
    $context = \mod_kuet\helpers\modcontext::from_cmid($kuetcmid);
    require_capability('mod/kuet:managesessions', $context);
    \mod_kuet\helpers\modcontext::require_session_in_cm($sid, $kuetcmid);
    \mod_kuet\question\version_resolver::require_editable($sid);
    $bank = \mod_kuet\question\bank_provider::require_bank($bankcmid);
    $params['cmid'] = $bankcmid;
    $params['qperpage'] = min(100, max(1, (int)($args['qperpage'] ?? 20)));
    if (isset($args['cat'])) {
        $params['cat'] = clean_param($args['cat'], PARAM_SEQUENCE);
    }
    if (!empty($params['cat'])) {
        [$categoryid, $contextid] = array_pad(explode(',', $params['cat']), 2, 0);
        if ((int)$contextid !== (int)$bank->context->id) {
            throw new invalid_parameter_exception('Category is outside the selected bank.');
        }
    }
    [$url, $contexts, , $cm, , $pagevars] = question_build_edit_resources(
        'editq', '/mod/kuet/sessions.php', $params, 20);
    $extra = ['kuetcmid' => $kuetcmid, 'sessionid' => $sid, 'cmid' => $bankcmid,
        'view' => \mod_kuet\question\bank\custom_view::class];
    return new \mod_kuet\question\bank\custom_view($contexts, $url, get_course($cm->course), $cm, $pagevars, $extra);
}

/** Render the complete bank, including core filters, inside the selection modal. */
function mod_kuet_output_fragment_kuet_question_bank(array $args): string {
    $view = mod_kuet_question_bank_view($args);
    ob_start();
    try {
        $view->display();
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

/** Render a filtered/paginated table without rebuilding filter controls. */
function mod_kuet_output_fragment_kuet_question_data(array $args): string {
    $view = mod_kuet_question_bank_view($args);
    $view->add_standard_search_conditions();
    ob_start();
    try {
        $view->display_question_list();
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}
