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
 * Capability definitions for the quiz module.
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
$string['privatekey'] = 'Private Key';
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
$string['advancemode'] = 'Advance mode';
$string['gamemode'] = 'Game mode';
$string['countdown'] = 'Show questions countdown';
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
$string['addtimequestion'] = 'Add time question enable';
$string['accessrestrictions'] = 'Access restrictions';
$string['next'] = 'Next';
$string['sessions'] = 'Sessions';
$string['sessions_info'] = 'All sessions are displayed';
$string['reports'] = 'Reports';
$string['report'] = 'Report';
$string['active_sessions'] = 'Active Sessions';
$string['completed_sessions'] = 'Completed sessions';
$string['create_session'] = 'Create session';
$string['session_name'] = 'Session Name';
$string['questions_number'] = 'No. of questions';
$string['session_date'] = 'Date';
$string['session_actions'] = 'Actions';
$string['init_session'] = 'Init Session';
$string['init_session_desc'] = 'Are you sure you want to init session?';
$string['end_session'] = 'End Session';
$string['end_session_error'] = 'The session could not be ended due to an error in communication with the server, please try again.';
$string['end_session_desc'] = 'Are you sure you want to end session?';
$string['end_session_manual_desc'] = 'If you end the session, you will close the connection of all students and they will no longer be able to answer this questionnaire.<br><b>Are you sure you want to end session?</b>';
$string['viewreport_session'] = 'View report';
$string['edit_session'] = 'Edit session';
$string['copy_session'] = 'Copy session';
$string['delete_session'] = 'Delete session';
$string['copysession'] = 'Copy Session';
$string['copysession_desc'] = 'Are you sure you want to copy this session? If the session has automatic start or start and end dates, these will need to be reset.';
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
$string['sessionstarted'] = 'Session started';
$string['multiplesessionerror'] = 'This session is not active or does not exist.';
$string['notactivesession'] = 'Oops, it looks like your teacher has not initialised a session yet...';
$string['notactivesessionawait'] = 'Wait for him to initiate it, or look at your latest reports.';
$string['nextsession'] = 'Next session:';
$string['nosession'] = 'No session initiated by the teacher';
$string['questions_list'] = 'Selected questions';
$string['questions_bank'] = 'Question Bank';
$string['question_position'] = 'Position';
$string['question_name'] = 'Name';
$string['question_type'] = 'Type';
$string['question_time'] = 'Time';
$string['question_version'] = 'Version';
$string['question_isvalid'] = 'Is valid';
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
$string['questionnameheader'] = 'Question name: "{$a}"';
$string['questiontime'] = 'Question time';
$string['notimelimit'] = 'No time limit';
$string['gradesheader'] = 'Question grading';
$string['nograding'] = 'Ignore correct answer and grading';
$string['sessionalreadyexists'] = 'Session name already exists';
$string['manualmode'] = 'Manual';
$string['programmedmode'] = 'Programado';
$string['inactivemode'] = 'Inactive';
$string['racemode'] = 'Race';
$string['podiummode'] = 'Podium';
$string['hidegraderanking'] = 'Hide grade and ranking between questions';
$string['question_nosuitable'] = 'No suitable with jqshow plugin.';
$string['configuration'] = 'Configuration';
$string['end'] = 'End';
$string['questionidnotsent'] = 'questionidnotsent';
$string['question_index_string'] = '{$a->num} of {$a->total}';
$string['question'] = 'Question';
$string['feedback'] = 'Feedback';
$string['session_info'] = 'Session information';
$string['results'] = 'Results';
$string['students'] = 'Students';
$string['corrects'] = 'Corrects';
$string['incorrects'] = 'Incorrects';
$string['points'] = 'Points';
$string['onlyinactivemodevalid'] = 'Only inactive game mode is valid with manual advance mode.';
$string['inactive_manual'] = 'Manual inactive';
$string['inactive_programmed'] = 'Programmed inactive';
$string['podium_manual'] = 'Manual podium';
$string['podium_programmed'] = 'Programmed Podium';
$string['race_manual'] = 'Manual Race';
$string['race_programmed'] = 'Programmed Race';
$string['sessionmode'] = 'Session mode';
$string['anonymousanswer_help'] = 'Teacher will not know who is answering in live quizzes';
$string['sessionmode_help'] = 'Session modes show different ways to use jqshow sessions.';
$string['countdown_help'] = 'Enable this option so that students can see the countdown in each question.';
$string['hidegraderanking_help'] = 'Teacher will not see the ranking during a live session. Only available on podiums session modes.';
$string['hidegraderankinghelp'] = 'SIN _Teacher will not see the ranking during a live session. Only available on podiums session modes.';
$string['randomquestions_help'] = 'Questions will appear in a random order for each student. Only valid for programmed session mode.';
$string['randomanswers_help'] = 'Answers will appear in a random order for each student.';
$string['showfeedback_help'] = 'After answering each question a feedback will be appeared.';
$string['showfinalgrade_help'] = 'Final grade will appear after finishing the session.';
$string['startdate_help'] = 'Session will start automatically at this date. Start date only will be available with programmed sessions.';
$string['enddate_help'] = 'Session will end automatically at this date. End date only will be available with programmed sessions.';
$string['automaticstart_help'] = 'Session will start automatically at this date. Only available after choosing an start/end date.';
$string['timelimit_help'] = 'Total time for the session';
$string['addtimequestion_help'] = 'The total session time will be the sum of the questions time.';
$string['waitingroom'] = 'Waiting room';
$string['waitingroom_info'] = 'Check that everything is correct before starting the session.';
$string['sessionstarted'] = 'Session started';
$string['sessionstarted_info'] = 'You have started the session, you need to keep track of the questions.';
$string['participants'] = 'Participants';
$string['waitingroom_message'] = 'Hold on, we\'re leaving in no time...';
$string['ready_users'] = 'Ready participants';
$string['session_closed'] = 'Session ended by the teacher';
$string['session_closed_info'] = 'The teacher has ended the session. If there are more sessions for this activity, you can view them by logging back into the activity.';
$string['system_error'] = 'An error has occurred and the connection has been closed.<br>It is not possible to continue with the session.';
$string['connection_closed'] = 'Connection Closed {$a->reason} - {$a->code}';
$string['backtopanelfromsession'] = 'Back to the sessions panel?';
$string['backtopanelfromsession_desc'] = 'If you come back, the session will not be initialised, and you can start it again at any time. Do you want to return to the session panel?';
$string['lowspeed'] = 'Your internet connection seems slow or unstable ({$a->downlink} Mbps, {$a->effectiveType}). This may cause unexpected behaviour, or sudden closure of the session.<br>We recommend that you do not init session until you have a good internet connection.';
$string['alreadyteacher'] = 'There is already a teacher imparting this session, so you cannot connect. Please wait for the current session to end before you can enter.';
$string['userdisconnected'] = 'User {$a} has been disconnected.';
$string['qtimelimit_help'] = 'Time to answer the question. Useful when session time is the sum of the questions time.';
$string['sessionlimittimebyquestionsenabled'] = 'This session has time limit. The total time limit will be the sum of the questions time.';
$string['incompatible_question'] = 'Question not compatible';
$string['controlpanel'] = 'Control panel';
$string['control'] = 'Control';
$string['next'] = 'Next';
$string['pause'] = 'Pause';
$string['resend'] = 'Reenviar';
$string['jump'] = 'Jump';
$string['finishquestion'] = 'Finish the question';
$string['showhide'] = 'Show / hide';
$string['responses'] = 'Respuestas';
$string['statistics'] = 'Statistics';
$string['feedback'] = 'Feedback';
$string['questions'] = 'Questions';
$string['improvise'] = 'Improvise';
$string['vote'] = 'Vote';
$string['end'] = 'End';
$string['incorrect_sessionmode'] = 'Incorrect session mode';
$string['endsession'] = 'Session ended';
$string['endsession_info'] = 'You have reached the end of the session, and can now view the report with your results, or continue with the course.';
