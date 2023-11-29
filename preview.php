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
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
global $CFG;
require_once('lib.php');
require_once($CFG->dirroot . '/question/engine/bank.php');

use mod_kuet\models\questions;
use mod_kuet\output\views\question_preview;

global $DB, $PAGE, $USER;

$id = required_param('id', PARAM_INT);    // Course Module ID.
$jqid = required_param('jqid', PARAM_INT);    // Id from mdl_kuet_questions.
$sid = required_param('sid', PARAM_INT);    // Jqshow session ID. mdl_kuet_sessions.
$jqshowid = required_param('jqsid', PARAM_INT);    // Jqshow session ID. mdl_jqshow.
$cid = required_param('cid', PARAM_INT);    // Course ID. mdl_course.

$jqquestion = $DB->get_record('kuet_questions', ['id' => $jqid], '*', MUST_EXIST);
if (!in_array($jqquestion->qtype, questions::TYPES, true)) {
    throw new moodle_exception('incompatible_question', 'mod_kuet', '',
        [], get_string('incompatible_question', 'mod_kuet'));
}

$question = $DB->get_record('question', ['id' => $jqquestion->questionid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cid], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);
require_login($course, false);

$question = question_bank::load_question((int) $question->id);

$view = new question_preview($jqquestion->questionid, $jqid, $id, $sid, $jqshowid);
$PAGE->set_context($coursecontext);
$PAGE->set_url('/mod/kuet/preview.php', ['id' => $id, 'jqid' => $jqid, 'sid' => $sid, 'cid' => $cid, 'jqshowid' => $jqshowid]);
$PAGE->set_heading($question->name);
$PAGE->set_title($question->name);
$output = $PAGE->get_renderer('mod_kuet');

echo $output->header();
echo $output->heading(format_string($question->name));
echo $output->render($view);
echo $output->footer();
