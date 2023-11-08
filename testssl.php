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
 * @package    mod_jqshow
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once('lib.php');
global $OUTPUT, $PAGE, $CFG;

$PAGE->set_url('/mod/jqshow/testssl.php');
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_heading(get_string('testssl', 'mod_jqshow'));
$PAGE->set_title(get_string('testssl', 'mod_jqshow'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('testssl', 'mod_jqshow'));

if (get_config('jqshow', 'sockettype') === 'local') {
    $server = $CFG->dirroot . '/mod/jqshow/classes/server.php';
    run_server_background($server);
}

echo html_writer::div('', '', ['id' => 'testresult']);
$typesocket = get_config('jqshow', 'sockettype');
if ($typesocket === 'local') {
    $socketurl = $CFG->wwwroot;
    $port = get_config('jqshow', 'localport') !== false ? get_config('jqshow', 'localport') : '8080';
}
if ($typesocket === 'external') {
    $socketurl = get_config('jqshow', 'externalurl');
    $port = get_config('jqshow', 'externalport') !== false ? get_config('jqshow', 'externalport') : '8080';
}
if ($typesocket === 'nosocket') {
    throw new moodle_exception('nosocket', 'mod_jqshow', '',
        [], get_string('nosocket', 'mod_jqshow'));
}
$PAGE->requires->js_amd_inline("require(['mod_jqshow/testssl'], function(TestSockets) {
    TestSockets.initTestSockets('[data-region=\"mainpage\"]', '" . $socketurl . "', '" . $port . "');
});");

echo $OUTPUT->footer();


