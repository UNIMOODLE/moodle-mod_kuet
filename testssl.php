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
 * SSL connectivity test
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use mod_kuet\output\views\test_report;
require_once('../../config.php');
require_once('lib.php');
global $OUTPUT, $PAGE, $CFG;

$PAGE->set_url('/mod/kuet/testssl.php');
require_login();
$context = context_system::instance();
// Require administrator to access this page.
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_heading(get_string('testssl', 'mod_kuet'));
$PAGE->set_title(get_string('testssl', 'mod_kuet'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('testssl', 'mod_kuet'));

$action = optional_param('action', '', PARAM_ALPHA);

if (get_config('kuet', 'sockettype') === 'local') {
    $pid = mod_kuet_get_server_pid();
    // Kills the server if action=stop.
    if ($pid && $action === 'stop') {
        if (mod_kuet_kill_server($pid) === true) {
            \core\notification::success(get_string('serverstopped', 'mod_kuet', $pid));
        }
    } else if ($pid == false && $action === 'start') {
        mod_kuet_run_server_background();
        // Wait 2 seconds to let the server start.
        sleep(2);
        // Get new pid.
        \core\notification::success(get_string('serverstarted', 'mod_kuet'));
    }
    $pid = mod_kuet_get_server_pid();
    // Just show server status.
    if ($pid) {
        \core\notification::success(get_string('serverrunning', 'mod_kuet', $pid));
    } else {
        \core\notification::error(get_string('serveroffline', 'mod_kuet'));
    }
}

echo html_writer::div('', '', ['id' => 'testresult']);
$typesocket = get_config('kuet', 'sockettype');
if ($typesocket === 'local') {
    $socketurl = $CFG->wwwroot;
    $port = get_config('kuet', 'localport') !== false ? get_config('kuet', 'localport') : '8080';
}
if ($typesocket === 'external') {
    $socketurl = get_config('kuet', 'externalurl');
    $port = get_config('kuet', 'externalport') !== false ? get_config('kuet', 'externalport') : '8080';
}
if ($typesocket === 'nosocket') {
    throw new moodle_exception(
        'nosocket',
        'mod_kuet',
        '',
        [],
        get_string('nosocket', 'mod_kuet')
    );
}

$view = new test_report($socketurl, $port);
$output = $PAGE->get_renderer('mod_kuet');
$viehtml = $output->render($view);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_heading(get_string('testssl', 'mod_kuet'));
$PAGE->set_title(get_string('testssl', 'mod_kuet'));
$PAGE->set_cacheable(false);


echo $viehtml;
echo $output->footer();
