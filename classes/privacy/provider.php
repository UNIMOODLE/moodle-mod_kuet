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
 * Privacy provider
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <juanpablo.decastro@uva.es>
 * @author     Juan Pablo de Castro  <juan.pablo.de.castro@gmail.com>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\helper;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use dml_exception;
use stdClass;

/**
 * Privacy provider for mod_kuet.
 *
 * Kuet stores, per user, the answers given during a session, the progress
 * through it, and the resulting grades. When the site is configured to use an
 * external socket server, answers also leave Moodle, so that destination is
 * declared as an external location too.
 */
class provider implements core_userlist_provider, metadata_provider, request_provider {
    /**
     * Tables owned by the plugin that hold user data, keyed by the field
     * pointing at the kuet instance.
     */
    private const USERDATA_TABLES = [
        'kuet_questions_responses',
        'kuet_user_progress',
        'kuet_sessions_grades',
        'kuet_grades',
    ];

    /**
     * Describe the personal data stored by the plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('kuet_questions_responses', [
            'userid' => 'privacy:metadata:responses:userid',
            'session' => 'privacy:metadata:responses:session',
            'questionid' => 'privacy:metadata:responses:questionid',
            'anonymise' => 'privacy:metadata:responses:anonymise',
            'result' => 'privacy:metadata:responses:result',
            'response' => 'privacy:metadata:responses:response',
            'manualmark' => 'privacy:metadata:responses:manualmark',
            'manualcomment' => 'privacy:metadata:responses:manualcomment',
            'timecreated' => 'privacy:metadata:responses:timecreated',
            'timemodified' => 'privacy:metadata:responses:timemodified',
        ], 'privacy:metadata:responses');

        $collection->add_database_table('kuet_user_progress', [
            'userid' => 'privacy:metadata:progress:userid',
            'session' => 'privacy:metadata:progress:session',
            'randomquestion' => 'privacy:metadata:progress:randomquestion',
            'other' => 'privacy:metadata:progress:other',
            'timecreated' => 'privacy:metadata:progress:timecreated',
            'timemodified' => 'privacy:metadata:progress:timemodified',
        ], 'privacy:metadata:progress');

        $collection->add_database_table('kuet_sessions_grades', [
            'userid' => 'privacy:metadata:sessionsgrades:userid',
            'session' => 'privacy:metadata:sessionsgrades:session',
            'grade' => 'privacy:metadata:sessionsgrades:grade',
            'timecreated' => 'privacy:metadata:sessionsgrades:timecreated',
            'timemodified' => 'privacy:metadata:sessionsgrades:timemodified',
        ], 'privacy:metadata:sessionsgrades');

        $collection->add_database_table('kuet_grades', [
            'userid' => 'privacy:metadata:grades:userid',
            'grade' => 'privacy:metadata:grades:grade',
            'timemodified' => 'privacy:metadata:grades:timemodified',
        ], 'privacy:metadata:grades');

        // Live sessions run over a WebSocket server. When the administrator
        // points the plugin at an external one, answers and the identity of
        // the participant travel to a host outside Moodle.
        $collection->add_external_location_link('socketserver', [
            'userid' => 'privacy:metadata:socketserver:userid',
            'fullname' => 'privacy:metadata:socketserver:fullname',
            'picture' => 'privacy:metadata:socketserver:picture',
            'response' => 'privacy:metadata:socketserver:response',
        ], 'privacy:metadata:socketserver');

        return $collection;
    }

    /**
     * Module contexts where the given user has kuet data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $unions = [];
        foreach (self::USERDATA_TABLES as $table) {
            $unions[] = "SELECT kuet FROM {" . $table . "} WHERE userid = :userid_{$table}";
        }
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'kuet'];
        foreach (self::USERDATA_TABLES as $table) {
            $params["userid_{$table}"] = $userid;
        }

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {kuet} k ON k.id = cm.instance
                 WHERE ctx.contextlevel = :contextlevel
                   AND k.id IN (" . implode(' UNION ', $unions) . ")";

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Users holding kuet data in the given context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $params = ['cmid' => $context->instanceid, 'modname' => 'kuet'];
        foreach (self::USERDATA_TABLES as $table) {
            $sql = "SELECT t.userid
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {kuet} k ON k.id = cm.instance
                      JOIN {" . $table . "} t ON t.kuet = k.id
                     WHERE cm.id = :cmid";
            $userlist->add_from_sql('userid', $sql, $params);
        }
    }

    /**
     * Export the user's kuet data for the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     * @throws dml_exception
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $kuetid = self::get_kuet_id($context);
            if ($kuetid === null) {
                continue;
            }

            $data = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);

            $data->responses = array_values(array_map(static function (stdClass $row): array {
                return [
                    'session' => $row->session,
                    'questionid' => $row->questionid,
                    'anonymised' => transform::yesno($row->anonymise),
                    'result' => $row->result,
                    'response' => $row->response,
                    'manualmark' => $row->manualmark,
                    'manualcomment' => $row->manualcomment,
                    'timecreated' => transform::datetime($row->timecreated),
                    'timemodified' => transform::datetime($row->timemodified),
                ];
            }, $DB->get_records('kuet_questions_responses', ['kuet' => $kuetid, 'userid' => $user->id])));

            $data->progress = array_values(array_map(static function (stdClass $row): array {
                return [
                    'session' => $row->session,
                    'randomquestion' => transform::yesno($row->randomquestion),
                    'other' => $row->other,
                    'timecreated' => transform::datetime($row->timecreated),
                    'timemodified' => transform::datetime($row->timemodified),
                ];
            }, $DB->get_records('kuet_user_progress', ['kuet' => $kuetid, 'userid' => $user->id])));

            $data->sessiongrades = array_values(array_map(static function (stdClass $row): array {
                return [
                    'session' => $row->session,
                    'grade' => $row->grade,
                    'timecreated' => transform::datetime($row->timecreated),
                    'timemodified' => transform::datetime($row->timemodified),
                ];
            }, $DB->get_records('kuet_sessions_grades', ['kuet' => $kuetid, 'userid' => $user->id])));

            $data->grades = array_values(array_map(static function (stdClass $row): array {
                return [
                    'grade' => $row->grade,
                    'timemodified' => transform::datetime($row->timemodified),
                ];
            }, $DB->get_records('kuet_grades', ['kuet' => $kuetid, 'userid' => $user->id])));

            writer::with_context($context)->export_data([], $data);
        }
    }

    /**
     * Delete every user's kuet data in the given context.
     *
     * @param context $context
     * @return void
     * @throws dml_exception
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $kuetid = self::get_kuet_id($context);
        if ($kuetid === null) {
            return;
        }

        foreach (self::USERDATA_TABLES as $table) {
            $DB->delete_records($table, ['kuet' => $kuetid]);
        }
    }

    /**
     * Delete the kuet data of one user across the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     * @throws dml_exception
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $kuetid = self::get_kuet_id($context);
            if ($kuetid === null) {
                continue;
            }
            foreach (self::USERDATA_TABLES as $table) {
                $DB->delete_records($table, ['kuet' => $kuetid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Delete the kuet data of several users in one context.
     *
     * @param approved_userlist $userlist
     * @return void
     * @throws dml_exception
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $kuetid = self::get_kuet_id($context);
        if ($kuetid === null) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['kuet'] = $kuetid;
        foreach (self::USERDATA_TABLES as $table) {
            $DB->delete_records_select($table, "kuet = :kuet AND userid {$insql}", $params);
        }
    }

    /**
     * Resolve the kuet instance id behind a module context.
     *
     * @param context_module $context
     * @return int|null Null when the context is not a kuet.
     * @throws dml_exception
     */
    private static function get_kuet_id(context_module $context): ?int {
        global $DB;

        $sql = "SELECT k.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {kuet} k ON k.id = cm.instance
                 WHERE cm.id = :cmid";
        $kuetid = $DB->get_field_sql($sql, ['cmid' => $context->instanceid, 'modname' => 'kuet']);

        return $kuetid === false ? null : (int) $kuetid;
    }
}
