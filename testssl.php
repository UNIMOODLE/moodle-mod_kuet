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

/**
 * A server status string with any link its translation carries taken out
 *
 * The anchor and its label go together: stripping only the tags would leave the old
 * label sitting beside the real link, saying the same thing twice.
 *
 * @param string $text
 * @return string
 */
function mod_kuet_testssl_without_links(string $text): string {
    return trim(preg_replace('/<a(?=[\s>])[^>]*>.*?<\/a>/is', '', $text));
}

$action = optional_param('action', '', PARAM_ALPHA);

// Starting and stopping the server changes state, so it cannot be reachable
// through a plain link: an administrator following a crafted URL would kill
// the WebSocket server of a live session.
if ($action !== '') {
    require_sesskey();
}

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
    //
    // These two strings are plain text and the link beside them is built here, with a
    // sesskey (KUET-010). They used to carry the anchor inside them, pointing at a bare
    // ./testssl.php?action=... with no token, and that older wording still lives in the
    // language packs published on AMOS. A site with one installed loads it after the
    // plugin's own lang directory and overrides it, so the page drew the stale anchor as
    // well as the real link: two links for the same action, one of them dead, since the
    // require_sesskey() above turns it into an invalid session key (KUET-053).
    //
    // Dropped here rather than waited out, so it holds on every site whatever
    // translation it has installed.
    if ($pid) {
        $stopurl = new moodle_url('/mod/kuet/testssl.php', ['action' => 'stop', 'sesskey' => sesskey()]);
        \core\notification::success(
            mod_kuet_testssl_without_links(get_string('serverrunning', 'mod_kuet', $pid)) . ' ' .
            html_writer::link($stopurl, get_string('killlocalserver', 'mod_kuet'))
        );
    } else {
        $starturl = new moodle_url('/mod/kuet/testssl.php', ['action' => 'start', 'sesskey' => sesskey()]);
        \core\notification::error(
            mod_kuet_testssl_without_links(get_string('serveroffline', 'mod_kuet')) . ' ' .
            html_writer::link($starturl, get_string('startlocalserver', 'mod_kuet'))
        );
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
