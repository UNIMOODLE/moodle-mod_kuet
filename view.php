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

require_once('../../config.php');
require_once('lib.php');

global $CFG, $PAGE, $DB, $OUTPUT;
$id = required_param('id', PARAM_INT);    // Course Module ID

if (!$cm = get_coursemodule_from_id('jqshow', $id)) {
    print_error('Course Module ID was incorrect'); // NOTE this is invalid use of print_error, must be a lang string id
}
if (!$course = $DB->get_record('course', array('id'=> $cm->course))) {
    print_error('course is misconfigured');  // NOTE As above
}
if (!$jqshow = $DB->get_record('jqshow', array('id'=> $cm->instance))) {
    print_error('course module is incorrect'); // NOTE As above
}

$PAGE->set_url('/mod/jqshow/view.php', array('id' => $id));
require_login($course, false, $cm);
$context = context_module::instance($cm->id);

require_capability('mod/jqshow:view', $context);


$context = context_course::instance($COURSE->id);
$userroles = get_user_roles($context, $USER->id);
$teacherroles = get_roles_with_capability('mod/assign:grade'); // TODO change by own capability.
$isteacher = false;
foreach ($userroles as $userrole) {
    if (!$isteacher) {
        foreach ($teacherroles as $teacherrole) {
            if ($userrole->shortname === $teacherrole->shortname) {
                $isteacher = true;
                break;
            }
        }
    }
}
$context = [
    'isteacher' => $isteacher,
    'userid' => $USER->id,
    'userfullname' => $USER->firstname . ' ' . $USER->lastname
];

$strjqshow = get_string("modulename", "jqshow");
$PAGE->set_title($strjqshow);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($jqshow->name));

if ($isteacher){
    $server = $CFG->dirroot . '/mod/jqshow/classes/server.php';
    run_server_background($server);
    echo $OUTPUT->render_from_template('mod_jqshow/teacher',
        $context);
} else {
    echo $OUTPUT->render_from_template('mod_jqshow/student',
        $context);
}

echo $OUTPUT->footer();
