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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use mod_kuet\output\views\student_reports;
use mod_kuet\output\views\teacher_reports;
use mod_kuet\persistents\kuet;
use mod_kuet\persistents\kuet_sessions;

require_once('../../config.php');

global $CFG, $PAGE, $DB, $COURSE, $USER;

$cmid = required_param('cmid', PARAM_INT);    // Course Module ID.
$sid = optional_param('sid', 0, PARAM_INT);    // Session id.
$userid = optional_param('userid', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$jqid = optional_param('jqid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('kuet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$jqshow = kuet::get_record(['id' => $cm->instance], MUST_EXIST);

if ($sid) {
    $session = new kuet_sessions($sid);
    $participantid = ($session->is_group_mode() && $groupid) ? $groupid : $userid;
} else {
    $participantid = $userid;
}
$PAGE->set_url('/mod/kuet/reports.php', ['cmid' => $cmid]);
require_login($course, false, $cm);
$cmcontext = context_module::instance($cm->id);
require_capability('mod/kuet:view', $cmcontext);
$isteacher = has_capability('mod/kuet:startsession', $cmcontext);
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('reports', 'kuet'));
$PAGE->set_cacheable(false);
$PAGE->navbar->add(get_string('reports', 'kuet'));

navigation_node::override_active_url(new moodle_url('/mod/kuet/reports.php', ['cmid' => $cm->id, 'return' => 1]));

if ($isteacher) {
    $PAGE->add_body_classes(['kuet-reports', 'kuet-reports kuet-teacher-reports']);
    $view = new teacher_reports($cmid, $jqshow->get('id'), $sid, $participantid, $jqid);
} else {
    if ((int)$userid !== 0 && (int)$userid !== (int)$USER->id) {
        redirect(
            (new moodle_url('/mod/kuet/reports.php', ['cmid' => $cmid, 'sid' => $sid, 'userid' => $USER->id]))->out(false),
            null, notification::NOTIFY_ERROR
        );
        throw new moodle_exception('otheruserreport', 'mod_kuet', '',
            [], get_string('otheruserreport', 'mod_kuet'));
    }
    $PAGE->add_body_classes(['kuet-reports', 'kuet-student-reports']);
    $view = new student_reports($cm->id, $jqshow->get('id'), $sid);
}

$output = $PAGE->get_renderer('mod_kuet');
echo $output->header();
echo $output->heading(format_string($jqshow->get('name')));
echo $output->render($view);
echo $output->footer();
