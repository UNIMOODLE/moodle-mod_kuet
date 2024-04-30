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
 * Course index page
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_kuet\persistents\kuet_sessions;

require_once("../../config.php");
global $DB, $PAGE, $OUTPUT, $USER;

// The `id` parameter is the course id.
$id = required_param('id', PARAM_INT);

$PAGE->set_url('/mod/kuet/index.php', ['id' => $id]);

// Fetch the requested course.
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

// Require that the user is logged into the course.
require_course_login($course);
$PAGE->set_pagelayout('incourse');
$modinfo = get_fast_modinfo($course);
$strkuets = get_string("modulenameplural", "kuet");
// Print the header.
$PAGE->navbar->add($strkuets);
$PAGE->set_title("$course->shortname: $strkuets");
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($strkuets, 2);

if (! $kuets = get_all_instances_in_course("kuet", $course)) {
    notice(get_string('thereareno', 'moodle', $strkuets), "../../course/view.php?id=$course->id");
    die;
}
$strname  = get_string("name");
$strsessionsnum  = get_string("sessionsnum", 'mod_kuet');
$strgrade  = get_string("gradenoun");
$table = new html_table();
$table->head  = [$strname, $strsessionsnum, $strgrade];
$table->align = ["left", "center", "center"];
$gradeoptions = mod_kuet_get_grading_options();
foreach ($kuets as $instanceid => $kuet) {
    $cm = get_coursemodule_from_instance('kuet', $kuet->id);
    $context = context_module::instance($cm->id);
    $class = $kuet->visible ? null : ['class' => 'dimmed']; // Hidden modules are dimmed.
    $link = html_writer::link(new moodle_url('view.php', ['id' => $cm->id]), format_string($kuet->name), $class);
    $gradevalue = $gradeoptions[$kuet->grademethod];
    if (!has_capability('mod/kuet:managesessions', $context)) {
        // It's a student, show their grade.
        $gradevalue = mod_kuet_get_user_grades($kuet->id, $USER->id);
    }
    $sessions = new kuet_sessions();
    $numsessions = $sessions::count_records(['kuetid' => $kuet->id]);
    $table->data[] = [$link, $numsessions, $gradevalue];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
