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
$qid = optional_param('qid', 0, PARAM_INT);    // Question ID. mdl_question.
$jqid = optional_param('jqid', 0, PARAM_INT);    // Id from mdl_jqshow_questions.
$cid = required_param('cid', PARAM_INT);    // Question ID. mdl_question.
$sid = required_param('sid', PARAM_INT);    // Jqshow session ID. mdl_jqshow_sessions.
$jqshowid = required_param('jqsid', PARAM_INT);    // Jqshow session ID. mdl_jqshow_sessions.

if ($jqid != 0) {
    $jqquestion = $DB->get_record('jqshow_questions', ['id' => $qid], '*', MUST_EXIST);
    $question = $DB->get_record('question', ['id' => $jqquestion->quesionid], '*', MUST_EXIST);
} else if ($qid != 0) {
    $question = $DB->get_record('question', ['id' => $qid], '*', MUST_EXIST);
} else {
    throw new moodle_exception('questionidnotsent', 'mod_jqshow');
}

$course = $DB->get_record('course', ['id' => $cid], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);

if (!in_array($question->qtype, questions::TYPES)) {
    echo 'pregunta no compatible';
}
$view = new question_preview($qid, $id, $sid, $jqshowid);
$PAGE->set_context($coursecontext);
$PAGE->set_url('/mod/jqshow/preview.php', ['id' => $id, 'qid' => $qid, 'cid' => $cid]);
$PAGE->set_heading($question->name);
$PAGE->set_title($question->name);
$output = $PAGE->get_renderer('mod_jqshow');

echo $output->header();
echo $output->heading(format_string($question->name));
echo $output->render($view);
echo $output->footer();
