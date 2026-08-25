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
 * Edit question
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <juanpablo.decastro@uva.es>
 * @author     Juan Pablo de Castro  <juan.pablo.de.castro@gmail.com>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_kuet\forms\questionform;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_sessions;

require_once('../../config.php');
global $OUTPUT, $DB, $PAGE;

$cmid = required_param('id', PARAM_INT);
$sid = required_param('sid', PARAM_INT);
$kid = required_param('kid', PARAM_INT);

$cm = get_coursemodule_from_id('kuet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$kuet = $DB->get_record('kuet', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/kuet/editquestion.php', ['id' => $cmid, 'sid' => $sid, 'kid' => $kid]);
require_login($course, false, $cm);

$context = \mod_kuet\helpers\modcontext::from_cmid($cmid);
require_capability('mod/kuet:managesessions', $context);
\mod_kuet\helpers\modcontext::require_session_in_cm($sid, $cmid);
\mod_kuet\helpers\modcontext::require_question_in_session($kid, $sid);
$ksquestion = new kuet_questions($kid);
$guard = new \mod_kuet\question\mutation($kuet->id);
try {
    \mod_kuet\question\version_resolver::refresh($ksquestion);
    $guard->finish();
} catch (\Throwable $e) {
    $guard->abort($e);
}
$reference = \mod_kuet\question\version_resolver::reference($kid);
$question = $DB->get_record('question', ['id' => $ksquestion->get('questionid')], '*', MUST_EXIST);
$versions = $DB->get_records_sql('SELECT qv.version, q.id AS questionid, q.name
      FROM {question_versions} qv
      JOIN {question} q ON q.id = qv.questionid
     WHERE qv.questionbankentryid = :entry AND qv.status = :status
  ORDER BY qv.version DESC', [
    'entry' => $reference->questionbankentryid,
    'status' => 'ready',
]);
$versionoptions = ['latest' => get_string('uselatestready', 'mod_kuet')];
foreach ($versions as $version) {
    $versionoptions[(string) $version->version] = get_string('questionversionoption', 'mod_kuet', (object) [
        'version' => $version->version,
        'name' => format_string($version->name),
    ]);
}

$session = kuet_sessions::get_record(['id' => $sid]);
$customdata = [
    'id' => $cmid,
    'kid' => $kid,
    'sid' => $sid,
    'qname' => $question->name,
    'uselatest' => $reference && $reference->version === null,
    'hasmultipleversions' => count($versions) > 1,
    'questionversion' => $reference && $reference->version === null ? 'latest' : (string) $reference->version,
    'versionoptions' => $versionoptions,
    'reviewedquestionid' => $ksquestion->get('questionid'),
    'qtype' => $ksquestion->get('qtype'),
    'timelimit' => $ksquestion->get('timelimit'),
    'sessionlimittimebyquestionsenabled' => $session->get('timemode') === sessions::QUESTION_TIME,
    'notimelimit' => $session->get('timemode') === sessions::NO_TIME,
    'nograding' => $ksquestion->get('ignorecorrectanswer'),
    ];

$sesionurl = new moodle_url('/mod/kuet/sessions.php', ['cmid' => $cmid, 'sid' => $sid, 'page' => 2]);
$actionurl = new moodle_url('/mod/kuet/editquestion.php', ['id' => $cmid, 'sid' => $sid, 'kid' => $kid]);
$mform = new questionform($actionurl->out(false), $customdata);
$mform->set_data($customdata);
if ($mform->is_cancelled()) {
    redirect($sesionurl);
} else if ($fromform = $mform->get_data()) {
    $guard = new \mod_kuet\question\mutation($kuet->id);
    try {
        $ksquestion = new kuet_questions($kid);
        \mod_kuet\question\version_resolver::require_editable($sid);
        // Reject a stale form if another editor has already changed the effective version.
        if ((int)$fromform->reviewedquestionid !== (int)$ksquestion->get('questionid')) {
            throw new moodle_exception('questionversionchanged', 'mod_kuet');
        }
        // Save new data.
        if (isset($fromform->{'timelimit'})) {
            $ksquestion->set('timelimit', $fromform->{'timelimit'});
        }
        $nograding = isset($fromform->{'nograding'}) ? $fromform->{'nograding'} : 0;
        $ksquestion->set('ignorecorrectanswer', $nograding);
        $ksquestion->update();
        if (isset($fromform->questionversion)) {
            $version = $fromform->questionversion === 'latest' ? null : (int) $fromform->questionversion;
        } else {
            $version = !empty($fromform->uselatest) ? null :
                (int) $DB->get_field('question_versions', 'version', [
                    'questionid' => $ksquestion->get('questionid'),
                ], MUST_EXIST);
        }
        \mod_kuet\question\version_resolver::set_policy($ksquestion, $version);
        $guard->finish();
    } catch (\Throwable $e) {
        $guard->abort($e);
    }
    redirect($sesionurl);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($kuet->name));
echo $mform->render();
echo $OUTPUT->footer();
