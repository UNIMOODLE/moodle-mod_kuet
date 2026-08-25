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
 * Manual grade editing page (KUETEDUCAM-73)
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_kuet\models\sessions as sessionsmodel;
use mod_kuet\output\views\editgrades;
use mod_kuet\persistents\kuet;
use mod_kuet\persistents\kuet_sessions;
use mod_kuet\table\editgrades_students_table;

require_once('../../config.php');

global $CFG, $PAGE, $DB;

$cmid = required_param('cmid', PARAM_INT);
$sid = required_param('sid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('kuet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$kuet = kuet::get_record(['id' => $cm->instance], MUST_EXIST);

require_login($course, false, $cm);
$cmcontext = context_module::instance($cm->id);
require_capability('mod/kuet:editgrades', $cmcontext);

// Only graded sessions can have their grades edited.
$session = new kuet_sessions($sid);
if ((int) $session->get('sgrade') === sessionsmodel::GM_DISABLED) {
    redirect(
        new moodle_url('/mod/kuet/view.php', ['id' => $cmid]),
        get_string('editgrades_notgradable', 'mod_kuet'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

$url = new moodle_url('/mod/kuet/editgrades.php', ['cmid' => $cmid, 'sid' => $sid]);
if ($userid) {
    $url->param('userid', $userid);
}
$PAGE->set_url($url);
$PAGE->set_context($cmcontext);
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('editgrades', 'mod_kuet'));
$PAGE->set_cacheable(false);
$PAGE->navbar->add(get_string('editgrades', 'mod_kuet'));

$output = $PAGE->get_renderer('mod_kuet');
echo $output->header();
echo $output->heading(format_string($kuet->get('name')));

if ($userid) {
    // Page 2: editable question list for the selected user.
    $view = new editgrades($cmid, $kuet->get('id'), $sid, $userid);
    echo $output->render($view);
} else {
    // Page 1: paginated, name-filterable list of students with their session grade.
    echo $output->heading(
        get_string('editgrades_for', 'mod_kuet') . ': ' . format_string($session->get('name')),
        4
    );
    echo html_writer::div(
        $output->single_button(
            new moodle_url('/mod/kuet/view.php', ['id' => $cmid]),
            get_string('back', 'mod_kuet'),
            'get'
        ),
        'd-flex justify-content-end'
    );
    $table = new editgrades_students_table('kuet-editgrades-' . $sid, $cmid, $kuet->get('id'), $sid, $cmcontext);
    $table->define_baseurl($PAGE->url);
    $table->out(25, true);
}

echo $output->footer();
