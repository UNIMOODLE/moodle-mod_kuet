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
 * Download report
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use mod_kuet\helpers\reports;
use mod_kuet\output\views\student_reports;
use mod_kuet\output\views\teacher_reports;
use mod_kuet\persistents\kuet;
use mod_kuet\persistents\kuet_sessions;

global $CFG, $DB, $COURSE, $USER, $PAGE, $OUTPUT;
$cmid = required_param('cmid', PARAM_INT);
$reportname = required_param('name', PARAM_RAW);
$sid = required_param('sid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$kid = optional_param('kid', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('kuet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$kuet = kuet::get_record(['id' => $cm->instance], MUST_EXIST);

require_login($course, false, $cm);
$cmcontext = context_module::instance($cm->id);
$isteacher = has_capability('mod/kuet:startsession', $cmcontext);
$isgroupmode = false;
if ($sid) {
    $session = new kuet_sessions($sid);
    $isgroupmode = $session->is_group_mode();
    $participantid = ($isgroupmode && $groupid) ? $groupid : $userid;
} else {
    $participantid = $userid;
}
$params = ['cmid' => $cmid, 'sid' => $sid, 'name' => $reportname];
$url = new moodle_url('/mod/kuet/dwn_report.php', $params);
$PAGE->set_url($url);
if ($isteacher) {
    $view = new teacher_reports($cmid, $kuet->get('id'), $sid, $participantid, $kid);
} else {
    if ((int)$userid !== 0 && (int)$userid !== (int)$USER->id) {
        die();
    }
    $view = new student_reports($cm->id, $kuet->get('id'), $sid);
}
$PAGE->set_title("$course->shortname: " . format_string($kuet->get('name')));
$PAGE->set_heading($kuet->get('name'));

$output = $PAGE->get_renderer('mod_kuet');
$data = $view->export_for_template($output);
$columnames = [];
$reportvalues = [];
$filename = 'report_mod_kuet_courseid_' . $course->id . '_sid_' . $sid;
$tabletitle = $reportname;
switch ($reportname) {
    case reports::GROUP_QUESTION_REPORT:
        $columnames = [ 'groupname', 'responsestr', 'grouppoints', 'score_moment', 'time', 'viewreporturl'];
        $headers = [get_string('groupname', 'group'),
            get_string('response', 'mod_kuet'),
            get_string('score', 'mod_kuet'),
            get_string('score_moment', 'mod_kuet'),
            get_string('time', 'mod_kuet'),
            get_string('reportlink', 'mod_kuet')];
        $filename .= '_questionid_' . $kid;
        $reportvalues = $data->questiongroupranking;
        $tabletitle = get_string('questionreport', 'mod_kuet');
        $params = array_merge($params, ['kid' => $kid]);
        $url->params($params);
        break;
    case reports::QUESTION_REPORT:
        $columnames = [ 'firstname', 'lastname', 'responsestr', 'userpoints', 'score_moment', 'time', 'viewreporturl'];
        $headers = [get_string('firstname'),
            get_string('lastname'),
            get_string('response', 'mod_kuet'),
            get_string('points', 'mod_kuet'),
            get_string('score_moment', 'mod_kuet'),
            get_string('time', 'mod_kuet'),
            get_string('reportlink', 'mod_kuet')];
        $filename .= '_questionid_' . $kid;
        $reportvalues = $data->questionranking;
        $tabletitle = get_string('questionreport', 'mod_kuet');
        $params = array_merge($params, ['kid' => $kid]);
        $url->params($params);
        break;
    case reports::SESSION_QUESTIONS_REPORT:
        $columnames = [ 'questionnid', 'position', 'name', 'type', 'success', 'failures', 'partyally', 'noresponse',
            'time', 'isevaluable', 'questionreporturl'];
        $headers = [
            get_string('questionid', 'mod_kuet'),
            get_string('question_position', 'mod_kuet'),
            get_string('question_name', 'mod_kuet'),
            get_string('question_type', 'mod_kuet'),
            get_string('success', 'mod_kuet'),
            get_string('incorrect', 'mod_kuet'),
            get_string('partially', 'mod_kuet'),
            get_string('noresponse', 'mod_kuet'),
            get_string('time', 'mod_kuet'),
            get_string('isevaluable', 'mod_kuet'),
            get_string('reportlink', 'mod_kuet')];
        $reportvalues = $data->sessionquestions;
        $tabletitle = get_string('sessionquestionsreport', 'mod_kuet');
        break;
    case reports::GROUP_SESSION_RANKING_REPORT:
        $columnames = [ 'groupposition', 'groupname', 'grouppoints', 'correctanswers', 'incorrectanswers', 'partially',
            'notanswers', 'viewreporturl'];
        $headers = [
            get_string('question_position', 'mod_kuet'),
            get_string('groupname', 'group'),
            get_string('score', 'mod_kuet'),
            get_string('success', 'mod_kuet'),
            get_string('incorrect', 'mod_kuet'),
            get_string('partially', 'mod_kuet'),
            get_string('noresponse', 'mod_kuet'),
            get_string('reportlink', 'mod_kuet')];
        $filename .= '_ranking';
        $reportvalues = $data->rankinggroups;
        $tabletitle = get_string('groupsessionrankingreport', 'mod_kuet');
        break;
    case reports::SESSION_RANKING_REPORT:
        $columnames = [ 'userposition', 'userfullname', 'userpoints', 'correctanswers', 'incorrectanswers', 'partially',
            'notanswers', 'viewreporturl'];
        $headers = [
            get_string('question_position', 'mod_kuet'),
            get_string('fullname'),
            get_string('points', 'mod_kuet'),
            get_string('success', 'mod_kuet'),
            get_string('incorrect', 'mod_kuet'),
            get_string('partially', 'mod_kuet'),
            get_string('noresponse', 'mod_kuet'),
            get_string('reportlink', 'mod_kuet')];
        $filename .= '_ranking';
        $reportvalues = $data->rankingusers;
        $tabletitle = get_string('sessionrankingreport', 'mod_kuet');
        break;
    case reports::USER_REPORT:
        $columnames = [ 'position', 'name', 'type', 'responsestr', 'score', 'time'];
        $headers = [
            get_string('question_position', 'mod_kuet'),
            get_string('question_name', 'mod_kuet'),
            get_string('question_type', 'mod_kuet'),
            get_string('responses', 'mod_kuet'),
            get_string('score', 'mod_kuet'),
            get_string('time', 'mod_kuet')];
        $filename .= '_userid_' . $userid . '_' . trim($data->userfullname);
        $reportvalues = $data->sessionquestions;
        $tabletitle = get_string('userreport', 'mod_kuet');
        $params = array_merge($params, ['userid' => $userid]);
        $url->params($params);
        break;
    case reports::GROUP_REPORT:
        $columnames = [ 'position', 'name', 'type', 'responsestr', 'score', 'time'];
        $headers = [
            get_string('question_position', 'mod_kuet'),
            get_string('question_name', 'mod_kuet'),
            get_string('question_type', 'mod_kuet'),
            get_string('responses', 'mod_kuet'),
            get_string('score', 'mod_kuet'),
            get_string('time', 'mod_kuet')];
        $filename .= '_groupid_' . $groupid . '_' . trim($data->groupname);
        $reportvalues = $data->sessionquestions;
        $tabletitle = get_string('userreport', 'mod_kuet');
        $params = array_merge($params, ['groupid' => $groupid]);
        $url->params($params);
        break;
}

$table = new flexible_table('mod-kuet-report-' . $reportname);
if (!empty($download)) {
    ob_clean();
    ob_start();
}
if (!$table->is_downloading($download, $filename, $filename)) {
    echo $OUTPUT->header();
    echo $OUTPUT->box_start('generalbox boxaligncenter');
    echo $OUTPUT->heading($tabletitle);
}

$table->define_baseurl($url->out());
$table->define_columns($columnames);
$table->define_headers($headers);
$table->set_attribute('id', $tabletitle);
$table->show_download_buttons_at([TABLE_P_BOTTOM]);
$table->setup();

foreach ($reportvalues as $d) {
    $table->add_data_keyed((array) $d);
}
$table->finish_output();
if (!$table->is_downloading()) {
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
}
