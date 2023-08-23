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

use mod_jqshow\output\views\sessions_view;
use mod_jqshow\persistents\jqshow_sessions;

global $CFG, $PAGE, $DB, $COURSE, $USER;
$id = required_param('cmid', PARAM_INT);    // Course Module ID.

$cm = get_coursemodule_from_id('jqshow', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$jqshow = $DB->get_record('jqshow', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/jqshow/sessions.php', ['cmid' => $id]);
require_login($course, false, $cm);
$cmcontext = context_module::instance($cm->id);
require_capability('mod/jqshow:view', $cmcontext);
$coursecontext = context_course::instance($COURSE->id);
require_capability('mod/jqshow:managesessions', $coursecontext);

$sid = optional_param('sid', 0, PARAM_INT);
$activesession = jqshow_sessions::get_active_session_id($jqshow->id);
if ($activesession !== 0 && $activesession === $sid) {
    throw new moodle_exception('erroreditsessionactive', 'mod_jqshow', (new moodle_url('/mod/jqshow/view.php', ['id' => $id])),
        [], get_string('erroreditsessionactive', 'mod_jqshow'));
}

$PAGE->set_title(get_string('modulename', 'jqshow'));
$PAGE->set_heading(get_string('sessionconfiguration', 'jqshow'));
$PAGE->set_cacheable(false);
$view = new sessions_view($jqshow, $cm->id);
$output = $PAGE->get_renderer('mod_jqshow');
echo $output->header();
echo $output->heading(get_string('sessionconfiguration', 'jqshow'));
echo $output->render($view);
echo $output->footer();
