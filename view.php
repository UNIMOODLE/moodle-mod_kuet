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
 * Course module main page
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');

use mod_kuet\output\views\student_view;
use mod_kuet\output\views\teacher_view;
use mod_kuet\persistents\kuet_sessions;

global $CFG, $PAGE, $DB, $COURSE, $USER;

$id = required_param('id', PARAM_INT);    // Course Module ID.

$cm = get_coursemodule_from_id('kuet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$kuet = $DB->get_record('kuet', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/kuet/view.php', ['id' => $id]);
require_login($course, false, $cm);
$cmcontext = context_module::instance($cm->id);
require_capability('mod/kuet:view', $cmcontext);
$isteacher = has_capability('mod/kuet:startsession', $cmcontext);
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('modulename', 'kuet'));

if ($isteacher) {
    $view = new teacher_view();
} else {
    $activessesion = kuet_sessions::get_active_session_id($kuet->id);
    if ($activessesion !== 0) {
        redirect((new moodle_url('/mod/kuet/session.php', ['cmid' => $cm->id, 'sid' => $activessesion]))->out(false));
    }
    $view = new student_view($kuet->id, $cm->id);
}

$output = $PAGE->get_renderer('mod_kuet');
echo $output->header();
echo $output->heading(format_string($kuet->name));
echo $output->render($view);
echo $output->footer();
