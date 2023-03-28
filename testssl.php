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

$PAGE->set_title(get_string('testssl', 'mod_jqshow'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('testssl', 'mod_jqshow'));

$server = $CFG->dirroot . '/mod/jqshow/classes/testserver.php';
run_server_background($server);

echo html_writer::div('', '', ['id' => 'testresult']);
$port = get_config('jqshow', 'port') !== false ? get_config('jqshow', 'port') : '8080';
$PAGE->requires->js_amd_inline("require(['mod_jqshow/testssl'], function(TestSockets) {
    TestSockets.initTestSockets('[data-region=\"mainpage\"]', '" . $port . "');
});");

echo $OUTPUT->footer();


