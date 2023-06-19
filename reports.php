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

use core\output\notification;
use mod_jqshow\output\views\student_reports;
use mod_jqshow\output\views\teacher_reports;
use mod_jqshow\persistents\jqshow;

require_once('../../config.php');

global $CFG, $PAGE, $DB, $COURSE, $USER;

$cmid = required_param('cmid', PARAM_INT);    // Course Module ID.
$sid = optional_param('sid', 0, PARAM_INT);    // Session id.
$userid = optional_param('userid', 0, PARAM_INT);
$jqid = optional_param('jqid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('jqshow', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$jqshow = jqshow::get_record(['id' => $cm->instance], MUST_EXIST);

$PAGE->set_url('/mod/jqshow/reports.php', ['cmid' => $cmid]);
require_login($course, false, $cm);
$cmcontext = context_module::instance($cm->id);
require_capability('mod/jqshow:view', $cmcontext);
$coursecontext = context_course::instance($COURSE->id);
$isteacher = has_capability('mod/jqshow:startsession', $coursecontext);
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('reports', 'jqshow'));
$PAGE->set_cacheable(false);
$PAGE->navbar->add(get_string('reports', 'jqshow'));

navigation_node::override_active_url(new moodle_url('/mod/jqshow/reports.php', ['cmid' => $cm->id, 'return' => 1]));

if ($isteacher) {
    $PAGE->add_body_classes(['jqshow-reports', 'jqshow-reports jqshow-teacher-reports']);
    $view = new teacher_reports($cmid, $jqshow->get('id'), $sid, $userid, $jqid);
} else {
    if ((int)$userid !== 0 && (int)$userid !== (int)$USER->id) {
        redirect(
            (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $cmid, 'sid' => $sid, 'userid' => $USER->id]))->out(false),
            null, notification::NOTIFY_ERROR
        );
        throw new moodle_exception('otheruserreport', 'mod_jqshow', '',
            [], get_string('otheruserreport', 'mod_jqshow'));
    }
    $PAGE->add_body_classes(['jqshow-reports', 'jqshow-student-reports']);
    $view = new student_reports($cm->id, $jqshow->get('id'), $sid);
}

$output = $PAGE->get_renderer('mod_jqshow');
echo $output->header();
echo $output->heading(format_string($jqshow->name));
echo $output->render($view);
echo $output->footer();
