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

use mod_jqshow\external\copysession_external;
use mod_jqshow\external\deletesession_external;
use mod_jqshow\external\reorderquestions_external;
use mod_jqshow\external\selectquestionscategory_external;
use mod_jqshow\external\sessionspanel_external;
use mod_jqshow\external\addquestions_external;

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
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'mod_jqshow'
    ]
];
