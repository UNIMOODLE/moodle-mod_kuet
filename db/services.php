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

use mod_jqshow\external\activesession_external;
use mod_jqshow\external\copysession_external;
use mod_jqshow\external\deleteresponses_external;
use mod_jqshow\external\deletesession_external;
use mod_jqshow\external\finishsession_external;
use mod_jqshow\external\firstquestion_external;
use mod_jqshow\external\getactivesession_external;
use mod_jqshow\external\getlistresults_external;
use mod_jqshow\external\getquestion_external;
use mod_jqshow\external\getsessionresume_external;
use mod_jqshow\external\getuserquestionresponse_external;
use mod_jqshow\external\jumptoquestion_external;
use mod_jqshow\external\multichoice_external;
use mod_jqshow\external\nextquestion_external;
use mod_jqshow\external\reorderquestions_external;
use mod_jqshow\external\selectquestionscategory_external;
use mod_jqshow\external\session_getallquestions_external;
use mod_jqshow\external\sessionfinished_external;
use mod_jqshow\external\sessionquestions_external;
use mod_jqshow\external\sessionspanel_external;
use mod_jqshow\external\addquestions_external;
use mod_jqshow\external\deletequestion_external;
use mod_jqshow\external\copyquestion_external;
use mod_jqshow\external\editsessionsettings_external;
use mod_jqshow\external\startsession_external;

defined('MOODLE_INTERNAL') || die;

$functions = [
    'mod_jqshow_get_jqshows_by_courses' => [
        'classname' => mod_jqshow_external::class,
        'methodname' => 'get_jqshows_by_courses',
        'description' => 'Returns a list of jqshows in a provided list of courses, if no list is provided all jqshows
                            that the user can view will be returned.',
        'type' => 'read',
        'capabilities' => 'mod/jqshow:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_jqshow_sessionspanel' => [
        'classname' => sessionspanel_external::class,
        'methodname' => 'sessionspanel',
        'description' => 'Get Sessions Panel for cmid',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_copysession' => [
        'classname' => copysession_external::class,
        'methodname' => 'copysession',
        'description' => 'Copy session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_deletesession' => [
        'classname' => deletesession_external::class,
        'methodname' => 'deletesession',
        'description' => 'Delete session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_selectquestionscategory' => [
        'classname' => selectquestionscategory_external::class,
        'methodname' => 'selectquestionscategory',
        'description' => 'Get questions for a determinate category',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_addquestions' => [
        'classname' => addquestions_external::class,
        'methodname' => 'add_questions',
        'description' => 'Add questions to a session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_reorderquestions' => [
        'classname' => reorderquestions_external::class,
        'methodname' => 'reorderquestions',
        'description' => 'Reorder session questions',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_deletequestion' => [
        'classname' => deletequestion_external::class,
        'methodname' => 'deletequestion',
        'description' => 'Removes a question from the session.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_sessionquestions' => [
        'classname' => sessionquestions_external::class,
        'methodname' => 'sessionquestions',
        'description' => 'Get questions for session',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_copyquestion' => [
        'classname' => copyquestion_external::class,
        'methodname' => 'copyquestion',
        'description' => 'Copies a question in a session.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_editsessionsettings' => [
        'classname' => editsessionsettings_external::class,
        'methodname' => 'editsession',
        'description' => 'Updatea session settings.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_multichoice' => [
        'classname' => multichoice_external::class,
        'methodname' => 'multichoice',
        'description' => 'Multichoice reply.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_nextquestion' => [
        'classname' => nextquestion_external::class,
        'methodname' => 'nextquestion',
        'description' => 'Next question of a session',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_firstquestion' => [
        'classname' => firstquestion_external::class,
        'methodname' => 'firstquestion',
        'description' => 'First question of a session',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_getlistresults' => [
        'classname' => getlistresults_external::class,
        'methodname' => 'getlistresults',
        'description' => 'Get list results of one session',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_finishsession' => [
        'classname' => finishsession_external::class,
        'methodname' => 'finishsession',
        'description' => 'Finish session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_startsession' => [
        'classname' => startsession_external::class,
        'methodname' => 'startsession',
        'description' => 'Start session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_sessionfinished' => [
        'classname' => sessionfinished_external::class,
        'methodname' => 'sessionfinished',
        'description' => 'Session closed',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_activesession' => [
        'classname' => activesession_external::class,
        'methodname' => 'activesession',
        'description' => 'Active session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_getactivesession' => [
        'classname' => getactivesession_external::class,
        'methodname' => 'getactivesession',
        'description' => 'Get Active session of Jqshow id',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_session_getallquestions' => [
        'classname' => session_getallquestions_external::class,
        'methodname' => 'session_getallquestions',
        'description' => 'Gets all questions and answers from a session to send to users in manual modes.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_getuserquestionresponse' => [
        'classname' => getuserquestionresponse_external::class,
        'methodname' => 'getuserquestionresponse',
        'description' => 'Get context or response for one user.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_getquestion' => [
        'classname' => getquestion_external::class,
        'methodname' => 'getquestion',
        'description' => 'Get question.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_deleteresponses' => [
        'classname' => deleteresponses_external::class,
        'methodname' => 'deleteresponses',
        'description' => 'Delete all responses for one question',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_jumptoquestion' => [
        'classname' => jumptoquestion_external::class,
        'methodname' => 'jumptoquestion',
        'description' => 'Get question from order in session.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_jqshow_getsessionresume' => [
        'classname' => getsessionresume_external::class,
        'methodname' => 'getsessionresume',
        'description' => 'Get resume for one session.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ]
];
$services = [
    'JQShow' => [
        'functions' => [
            'mod_jqshow_get_jqshows_by_courses',
            'mod_jqshow_sessionspanel',
            'mod_jqshow_copysession',
            'mod_jqshow_deletesession',
            'mod_jqshow_selectquestionscategory',
            'mod_jqshow_addquestions',
            'mod_jqshow_reorderquestions',
            'mod_jqshow_deletequestion',
            'mod_jqshow_sessionquestions',
            'mod_jqshow_copyquestion',
            'mod_jqshow_editsessionsettings',
            'mod_jqshow_multichoice',
            'mod_jqshow_nextquestion',
            'mod_jqshow_firstquestion',
            'mod_jqshow_getlistresults',
            'mod_jqshow_finishsession',
            'mod_jqshow_startsession',
            'mod_jqshow_sessionfinished',
            'mod_jqshow_activesession',
            'mod_jqshow_getactivesession',
            'mod_jqshow_session_getallquestions',
            'mod_jqshow_getuserquestionresponse',
            'mod_jqshow_getquestion',
            'mod_jqshow_deleteresponses',
            'mod_jqshow_jumptoquestion',
            'mod_jqshow_getsessionresume'
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'mod_jqshow'
    ]
];
