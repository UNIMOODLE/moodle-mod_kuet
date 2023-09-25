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
global $OUTPUT, $PAGE, $CFG;
require_login();
$PAGE->set_title(get_string('testssl', 'mod_jqshow'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('testssl', 'mod_jqshow'));

$server = $CFG->dirroot . '/mod/jqshow/classes/testserver.php';
run_server_background($server);

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


