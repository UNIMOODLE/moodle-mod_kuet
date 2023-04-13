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
 * Strings for component 'jqshow'
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Jam Quiz Show';
$string['pluginadministration'] = 'Jam Quiz Show Administration';
$string['modulename'] = 'Jam Quiz Show';
$string['modulenameplural'] = 'Jam Quiz Shows';
$string['jqshow:addinstance'] = 'Add a new Jam Quiz Show package';
$string['jqshow:view'] = 'View Jam Quiz Show';
$string['jqshow:managesessions'] = 'Manage Sessions';
$string['jqshow:startsession'] = 'Initialise Sessions';
$string['name'] = 'Name';
$string['introduction'] = 'Description';
$string['jqshow_header'] = 'JAM Quiz Show settings';
$string['questiontime'] = 'Question time';
$string['questiontime_desc'] = 'Time for every question in seconds.';
$string['teamsgradeheader'] = 'Teams grade';
$string['teamgrade'] = 'Teams grade';
$string['teamgrade_help'] = 'This is the way each team member is graded.';
$string['chooseoption'] = 'Choose an option';
$string['badgepositions'] = 'Number of the firsts positions who are going to receive a badge';
$string['badgepositions_help'] = 'Number of the firsts positions who are going to receive a badge';
$string['badgepositionsrule'] = 'Only numbers accepted';
$string['completiondetail:answerall'] = 'Answer all the questions.';
$string['completionansweralllabel'] = 'Answer all the questions.';
$string['completionansweralldesc'] = 'Answer all the questions in all the sessions.';
$string['configtitle'] = 'JQ Show';
$string['generalsettings'] = 'General settings';
$string['sslcertificates'] = 'SSL Certificates';
$string['certificate'] = 'Certificate';
$string['certificate_desc'] = '.crt or .pem file of a valid SSL certificate for the server. This file may already be generated on the server, or you can create unique ones for this mod using tools such as <a href="https://zerossl.com" target="_blank">zerossl.com</a>.';
$string['privatekey'] = 'Privaste Key';
$string['privatekey_desc'] = '.pem or .key file of a valid SSL Private Key for the server. This file may already be generated on the server, or you can create unique ones for this mod using tools such as <a href="https://zerossl.com" target="_blank">zerossl.com</a>.';
$string['testssl'] = 'Connection test';
$string['testssl_desc'] = 'Socket connection test with SSL certificates';
$string['validcertificates'] = 'Valid SSL Certificates and Port';
$string['invalidcertificates'] = 'Invalid certificates or Port';
$string['socketclosed'] = 'Socket closed';
$string['port'] = 'Port';
$string['port_desc'] = 'Port to make the connection. This port needs to be open, so you will need to check with your system administrator.';
$string['warningtest'] = 'This test will close open sockets, so do not run this test if there are open user sessions.';
$string['generalsettings'] = 'General settings';
$string['session_name'] = 'Session name';
$string['session_name_placeholder'] = 'Session name';
$string['session_name_help'] = 'Write the session name';
$string['anonymousanswer'] = 'Anonymous answers';
$string['anonymousanswer_help'] = 'Choose one option.';
$string['allowguests'] = 'Allow guests';
$string['advancemode'] = 'Advance mode';
$string['gamemode'] = 'Game mode';
$string['countdown'] = 'Count down';
$string['randomquestions'] = 'Random questions';
$string['randomanswers'] = 'Random answers';
$string['showfeedback'] = 'Show feedback';
$string['showfinalgrade'] = 'Show final grade';
$string['timesettings'] = 'Time settings';
$string['openquiz'] = 'Open quiz';
$string['openquizenable'] = 'Enable';
$string['startdate'] = 'Session start date';
$string['closequiz'] = 'Open quiz';
$string['closequizenable'] = 'Enable';
$string['enddate'] = 'Session end date';
$string['automaticstart'] = 'Automatic start';
$string['timelimit'] = 'Time limit';
$string['activetimelimit'] = 'Active time limit';
$string['addtimequestionenable'] = 'Add time question enable';
$string['accessrestrictions'] = 'Access restrictions';
$string['next'] = 'Next';
$string['sessions'] = 'Sessions';
$string['sessions_info'] = 'All sessions are displayed';
$string['reports'] = 'Reports';
$string['active_sessions'] = 'Active Sessions';
$string['completed_sessions'] = 'Completed sessions';
$string['create_session'] = 'Create session';
$string['session_name'] = 'Session Name';
$string['questions_number'] = 'No. of questions';
$string['session_date'] = 'Date';
$string['session_actions'] = 'Actions';
$string['init_session'] = 'Init Session';
$string['init_session_desc'] = 'Are you sure you want to log in?';
$string['viewreport_session'] = 'View report';
$string['edit_session'] = 'Edit session';
$string['copy_session'] = 'Copy session';
$string['delete_session'] = 'Delete session';
$string['copysession'] = 'Copy Session';
$string['copysession_desc'] = 'Are you sure you want to copy this session?';
$string['copysessionerror'] = 'An error occurred while copying the session. Check that you have the capacity "mod/jqshow:managesessions", or try again later.';
$string['deletesession'] = 'Delete Session';
$string['deletesession_desc'] = 'Are you sure you want to delete this session?';
$string['deletesessionerror'] = 'An error occurred while deleting the session. Check that you have the capacity "mod/jqshow:managesessions", or try again later.';
$string['confirm'] = 'Confirm';
$string['copy'] = 'Copy';
$string['groupings'] = 'Groupings';
$string['anonymiseresponses'] = 'Anonymise student responses';
$string['anonymiseallresponses'] = 'Fully anonymise student responses';
$string['noanonymiseresponses'] = 'Do not anonymise student responses';
$string['sessionconfiguration'] = 'Session configuration';
$string['sessionconfiguration_info'] = 'Set up your own session';
$string['questionsconfiguration'] = 'Question configuration';
$string['questionsconfiguration_info'] = 'Add the questions to the session';
$string['summarysession'] = 'Summary of the session';
$string['summarysession_info'] = 'Review the session';
$string['sessionactive'] = 'Active session';
$string['multiplesessionerror'] = 'A session already exists for this course module, there can be no more than 1 simultaneous session.';
$string['notactivesession'] = 'Oops, it looks like your teacher has not initialised a session yet...';
$string['notactivesessionawait'] = 'Wait for him to initiate it, or look at your latest reports.';
$string['nextsession'] = 'Next session:';
$string['nosession'] = 'No session initiated by the teacher';
$string['questions_list'] = 'Selected questions';
$string['questions_bank'] = 'Question Bank';
$string['question_position'] = 'Position';
$string['question_name'] = 'Name';
$string['question_type'] = 'Type';
$string['question_date'] = 'Dates';
$string['question_actions'] = 'Actions';
$string['select_category'] = 'Select a category';
$string['go_questionbank'] = 'Go to the question bank';
$string['selectall'] = 'Select/unselect all';
$string['selectvisibles'] = 'Select/unselect visibles';
$string['add_questions'] = 'Add questions';
$string['number_select'] = 'Selected questions: ';
$string['changecategory'] = 'Change of category';
$string['changecategory_desc'] = 'You have selected questions that have not been added to the session. If you change category you will lose this selection. Do you wish to continue?';
$string['selectone'] = 'Select questions';
$string['selectone_desc'] = 'Select at least one question to add to the session.';
$string['addquestions'] = 'Añadir preguntas';
$string['addquestions_desc'] = 'Are you sure about adding {$a} questions to the session?';
$string['deletequestion'] = 'Remove a question from the session';
$string['deletequestion_desc'] = 'Are you sure about removing this question from the session?';
$string['copyquestion'] = 'Copy a question from the session';
$string['copyquestion_desc'] = 'Are you sure about copying this question from the session?';

