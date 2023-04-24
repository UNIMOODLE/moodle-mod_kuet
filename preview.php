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
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
use mod_jqshow\models\questions;
use mod_jqshow\output\views\question_preview;

global $DB, $PAGE;

$id = required_param('id', PARAM_INT);    // Course Module ID.
$jqid = required_param('jqid', PARAM_INT);    // Id from mdl_jqshow_questions.
$sid = required_param('sid', PARAM_INT);    // Jqshow session ID. mdl_jqshow_sessions.
$jqshowid = required_param('jqsid', PARAM_INT);    // Jqshow session ID. mdl_jqshow.
$cid = required_param('cid', PARAM_INT);    // Course ID. mdl_course.

$jqquestion = $DB->get_record('jqshow_questions', ['id' => $jqid], '*', MUST_EXIST);
$question = $DB->get_record('question', ['id' => $jqquestion->questionid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cid], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);

if (!in_array($question->qtype, questions::TYPES)) {
    echo 'pregunta no compatible';
}
$view = new question_preview($jqid, $id, $sid, $jqshowid);
$PAGE->set_context($coursecontext);
$PAGE->set_url('/mod/jqshow/preview.php', ['id' => $id, 'jqid' => $jqid, 'sid' => $sid, 'cid' => $cid, 'jqshowid' => $jqshowid]);
$PAGE->set_heading($question->name);
$PAGE->set_title($question->name);
$output = $PAGE->get_renderer('mod_jqshow');

echo $output->header();
echo $output->heading(format_string($question->name));
echo $output->render($view);
echo $output->footer();
