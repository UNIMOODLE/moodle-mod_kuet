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

use mod_kuet\external\activesession_external;
use mod_kuet\external\calculated_external;
use mod_kuet\external\copysession_external;
use mod_kuet\external\ddwtos_external;
use mod_kuet\external\deleteresponses_external;
use mod_kuet\external\deletesession_external;
use mod_kuet\external\description_external;
use mod_kuet\external\finishsession_external;
use mod_kuet\external\firstquestion_external;
use mod_kuet\external\getactivesession_external;
use mod_kuet\external\getfinalranking_external;
use mod_kuet\external\getlistresults_external;
use mod_kuet\external\getgrouplistresults_external;
use mod_kuet\external\getprovisionalranking_external;
use mod_kuet\external\getquestion_external;
use mod_kuet\external\getquestionstatistics_external;
use mod_kuet\external\getraceresults_external;
use mod_kuet\external\getsession_external;
use mod_kuet\external\getsessionresume_external;
use mod_kuet\external\getuserquestionresponse_external;
use mod_kuet\external\jumptoquestion_external;
use mod_kuet\external\match_external;
use mod_kuet\external\multichoice_external;
use mod_kuet\external\nextquestion_external;
use mod_kuet\external\numerical_external;
use mod_kuet\external\reorderquestions_external;
use mod_kuet\external\selectquestionscategory_external;
use mod_kuet\external\sessionfinished_external;
use mod_kuet\external\sessionquestions_external;
use mod_kuet\external\sessionspanel_external;
use mod_kuet\external\addquestions_external;
use mod_kuet\external\deletequestion_external;
use mod_kuet\external\copyquestion_external;
use mod_kuet\external\editsessionsettings_external;
use mod_kuet\external\sessionstatus_external;
use mod_kuet\external\shortanswer_external;
use mod_kuet\external\startsession_external;
use mod_kuet\external\truefalse_external;

defined('MOODLE_INTERNAL') || die;

$functions = [
    'mod_kuet_get_kuets_by_courses' => [
        'classname' => mod_kuet_external::class,
        'methodname' => 'get_kuets_by_courses',
        'description' => 'Returns a list of kuets in a provided list of courses, if no list is provided all kuets
                            that the user can view will be returned.',
        'type' => 'read',
        'capabilities' => 'mod/kuet:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_kuet_sessionspanel' => [
        'classname' => sessionspanel_external::class,
        'methodname' => 'sessionspanel',
        'description' => 'Get Sessions Panel for cmid',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_copysession' => [
        'classname' => copysession_external::class,
        'methodname' => 'copysession',
        'description' => 'Copy session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_deletesession' => [
        'classname' => deletesession_external::class,
        'methodname' => 'deletesession',
        'description' => 'Delete session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_selectquestionscategory' => [
        'classname' => selectquestionscategory_external::class,
        'methodname' => 'selectquestionscategory',
        'description' => 'Get questions for a determinate category',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_addquestions' => [
        'classname' => addquestions_external::class,
        'methodname' => 'add_questions',
        'description' => 'Add questions to a session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_reorderquestions' => [
        'classname' => reorderquestions_external::class,
        'methodname' => 'reorderquestions',
        'description' => 'Reorder session questions',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_deletequestion' => [
        'classname' => deletequestion_external::class,
        'methodname' => 'deletequestion',
        'description' => 'Removes a question from the session.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_sessionquestions' => [
        'classname' => sessionquestions_external::class,
        'methodname' => 'sessionquestions',
        'description' => 'Get questions for session',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_copyquestion' => [
        'classname' => copyquestion_external::class,
        'methodname' => 'copyquestion',
        'description' => 'Copies a question in a session.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_editsessionsettings' => [
        'classname' => editsessionsettings_external::class,
        'methodname' => 'editsession',
        'description' => 'Updatea session settings.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_multichoice' => [
        'classname' => multichoice_external::class,
        'methodname' => 'multichoice',
        'description' => 'Multichoice reply.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_match' => [
        'classname' => match_external::class,
        'methodname' => 'match',
        'description' => 'Match reply.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_truefalse' => [
        'classname' => truefalse_external::class,
        'methodname' => 'truefalse',
        'description' => 'Truefalse reply.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_shortanswer' => [
        'classname' => shortanswer_external::class,
        'methodname' => 'shortanswer',
        'description' => 'Shortanswer reply.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_numerical' => [
        'classname' => numerical_external::class,
        'methodname' => 'numerical',
        'description' => 'Numerical reply.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_calculated' => [
        'classname' => calculated_external::class,
        'methodname' => 'calculated',
        'description' => 'Calculated reply.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_description' => [
        'classname' => description_external::class,
        'methodname' => 'description',
        'description' => 'Description reply.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_ddwtos' => [
        'classname' => ddwtos_external::class,
        'methodname' => 'ddwtos',
        'description' => 'Ddwtos reply.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_nextquestion' => [
        'classname' => nextquestion_external::class,
        'methodname' => 'nextquestion',
        'description' => 'Next question of a session',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_firstquestion' => [
        'classname' => firstquestion_external::class,
        'methodname' => 'firstquestion',
        'description' => 'First question of a session',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_getlistresults' => [
        'classname' => getlistresults_external::class,
        'methodname' => 'getlistresults',
        'description' => 'Get list results of one session',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_getgrouplistresults' => [
        'classname' => getgrouplistresults_external::class,
        'methodname' => 'getgrouplistresults',
        'description' => 'Get group list results of one session',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_finishsession' => [
        'classname' => finishsession_external::class,
        'methodname' => 'finishsession',
        'description' => 'Finish session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_startsession' => [
        'classname' => startsession_external::class,
        'methodname' => 'startsession',
        'description' => 'Start session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_sessionfinished' => [
        'classname' => sessionfinished_external::class,
        'methodname' => 'sessionfinished',
        'description' => 'Session closed',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_activesession' => [
        'classname' => activesession_external::class,
        'methodname' => 'activesession',
        'description' => 'Active session',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_getactivesession' => [
        'classname' => getactivesession_external::class,
        'methodname' => 'getactivesession',
        'description' => 'Get Active session of Kuet id',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_getuserquestionresponse' => [
        'classname' => getuserquestionresponse_external::class,
        'methodname' => 'getuserquestionresponse',
        'description' => 'Get context or response for one user.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_getquestion' => [
        'classname' => getquestion_external::class,
        'methodname' => 'getquestion',
        'description' => 'Get question.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_deleteresponses' => [
        'classname' => deleteresponses_external::class,
        'methodname' => 'deleteresponses',
        'description' => 'Delete all responses for one question',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_jumptoquestion' => [
        'classname' => jumptoquestion_external::class,
        'methodname' => 'jumptoquestion',
        'description' => 'Get question from order in session.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_getsessionresume' => [
        'classname' => getsessionresume_external::class,
        'methodname' => 'getsessionresume',
        'description' => 'Get resume for one session.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_getquestionstatistics' => [
        'classname' => getquestionstatistics_external::class,
        'methodname' => 'getquestionstatistics',
        'description' => 'Get response statistics for a question',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_getsession' => [
        'classname' => getsession_external::class,
        'methodname' => 'getsession',
        'description' => 'Get all data of session',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_getprovisionalranking' => [
        'classname' => getprovisionalranking_external::class,
        'methodname' => 'getprovisionalranking',
        'description' => 'Get ranking for a session and question',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_getfinalranking' => [
        'classname' => getfinalranking_external::class,
        'methodname' => 'getfinalranking',
        'description' => 'Get final ranking for a session',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_getraceresults' => [
        'classname' => getraceresults_external::class,
        'methodname' => 'getraceresults',
        'description' => 'Get race results',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ],
    'mod_kuet_sessionstatus' => [
        'classname' => sessionstatus_external::class,
        'methodname' => 'sessionstatus',
        'description' => 'Change session status',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ]
];
$services = [
    'Kuet' => [
        'functions' => [
            'mod_kuet_get_kuets_by_courses',
            'mod_kuet_sessionspanel',
            'mod_kuet_copysession',
            'mod_kuet_deletesession',
            'mod_kuet_selectquestionscategory',
            'mod_kuet_addquestions',
            'mod_kuet_reorderquestions',
            'mod_kuet_deletequestion',
            'mod_kuet_sessionquestions',
            'mod_kuet_copyquestion',
            'mod_kuet_editsessionsettings',
            'mod_kuet_multichoice',
            'mod_kuet_match',
            'mod_kuet_truefalse',
            'mod_kuet_shortanswer',
            'mod_kuet_numerical',
            'mod_kuet_calculated',
            'mod_kuet_description',
            'mod_kuet_ddwtos',
            'mod_kuet_nextquestion',
            'mod_kuet_firstquestion',
            'mod_kuet_getlistresults',
            'mod_kuet_getgrouplistresults',
            'mod_kuet_finishsession',
            'mod_kuet_startsession',
            'mod_kuet_sessionfinished',
            'mod_kuet_activesession',
            'mod_kuet_getactivesession',
            'mod_kuet_getuserquestionresponse',
            'mod_kuet_getquestion',
            'mod_kuet_deleteresponses',
            'mod_kuet_jumptoquestion',
            'mod_kuet_getsessionresume',
            'mod_kuet_getquestionstatistics',
            'mod_kuet_getsession',
            'mod_kuet_getprovisionalranking',
            'mod_kuet_getfinalranking',
            'mod_kuet_getraceresults',
            'mod_kuet_sessionstatus'
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'mod_kuet'
    ]
];
