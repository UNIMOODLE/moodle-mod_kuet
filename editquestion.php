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

use mod_jqshow\forms\questionform;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_sessions;

require_once('../../config.php');
global $OUTPUT, $DB, $PAGE;

$cmid = required_param('id', PARAM_INT);
$sid = required_param('sid', PARAM_INT);
$jqid = required_param('jqid', PARAM_INT);

$cm = get_coursemodule_from_id('jqshow', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$jqshow = $DB->get_record('jqshow', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/jqshow/editquestion.php', ['id' => $cmid, 'sid' => $sid, 'jqid' => $jqid]);
require_login($course, false, $cm);

$jqsquestion = new jqshow_questions($jqid);
$question = $DB->get_record('question', ['id' => $jqsquestion->get('questionid')], '*', MUST_EXIST);

$session = jqshow_sessions::get_record(['id' => $sid]);
$customdata = [
    'id' => $cmid,
    'jqid' => $jqid,
    'sid' => $sid,
    'qname' => $question->name,
    'qtype' => $jqsquestion->get('qtype'),
    'timelimit' => $jqsquestion->get('timelimit'),
    'sessionlimittimebyquestionsenabled' => $session->get('timemode') === sessions::QUESTION_TIME,
    'notimelimit' => $session->get('timemode') === sessions::NO_TIME,
    'nograding' => $jqsquestion->get('ignorecorrectanswer'),
    ];

$sesionurl = new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $cmid, 'sid' => $sid, 'page' => 2]);
$actionurl = new moodle_url('/mod/jqshow/editquestion.php', ['id' => $cmid, 'sid' => $sid, 'jqid' => $jqid]);
$mform = new questionform($actionurl->out(false), $customdata);
$mform->set_data($customdata);
if ($mform->is_cancelled()) {
    redirect($sesionurl);
} else if ($fromform = $mform->get_data()) {
    // Save new data.
    if (isset($fromform->{'timelimit'})) {
        $jqsquestion->set('timelimit', $fromform->{'timelimit'});
    }
    $nograding = isset($fromform->{'nograding'}) ? $fromform->{'nograding'} : 0;
    $jqsquestion->set('ignorecorrectanswer', $nograding);
    $jqsquestion->update();
    redirect($sesionurl);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($jqshow->name));
echo $mform->render();
echo $OUTPUT->footer();

