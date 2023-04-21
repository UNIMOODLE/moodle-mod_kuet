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

use mod_jqshow\forms\questionform;
use mod_jqshow\persistents\jqshow_questions;

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
$customdata = [
    'id' => $cmid,
    'jqid' => $jqid,
    'sid' => $sid,
    'qname' => $question->name,
    'qtype' => $jqsquestion->get('qtype'),
    'hasnotimelimit' => !$jqsquestion->get('hastimelimit'),
    'timelimitvalue' => $jqsquestion->get('timelimit') > 60 ? round($jqsquestion->get('timelimit') / 60) :
        $jqsquestion->get('timelimit'),
    'timelimittype' => $jqsquestion->get('timelimit') > 60 ? 'min' : 'secs',
    ];
$sesionurl = new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $cmid, 'sid' => $sid, 'page' => 2]);
$actionurl = new moodle_url('/mod/jqshow/editquestion.php', ['id' => $cmid, 'sid' => $sid, 'jqid' => $jqid]);
$mform = new questionform($actionurl->out(false), $customdata);
$mform->set_data($customdata);
if ($mform->is_cancelled()) {
    redirect($sesionurl);
} else if ($fromform = $mform->get_data()) {
    // Save new data.
    // Convert time in secs.
    if (isset($fromform->{'timelimitvalue'})) {
        $time = $fromform->{'timelimittype'} == 'min' ? $fromform->{'timelimitvalue'} * 60 : $fromform->{'timelimitvalue'};
        $jqsquestion->set('timelimit', $time);
    }
    $hasnotimelimit = isset($fromform->{'hasnotimelimit'}) ? $fromform->{'hasnotimelimit'} : 0;
    $hastimelimit = $hasnotimelimit ? 0 : 1;
    $jqsquestion->set('hastimelimit', $hastimelimit);
    if (isset($fromform->{'nograding'})) {
        $jqsquestion->set('ignorecorrectanswer', $fromform->{'nograding'});
    }
    $jqsquestion->update();
    redirect($sesionurl);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($jqshow->name));
echo $mform->render();
echo $OUTPUT->footer();

