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

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

/**
 * @param $oldversion
 * @throws ddl_exception
 * @throws ddl_table_missing_exception
 * @throws downgrade_exception
 * @throws moodle_exception
 * @throws upgrade_exception
 */
function xmldb_jqshow_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2023032901) {
        $dbman = $DB->get_manager();
        // Define field status to be added to jqshow_sessions.
        $table = new xmldb_table('jqshow_sessions');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'groupmode');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Jqshow savepoint reached.
        upgrade_mod_savepoint(true, 2023032901, 'jqshow');
    }

    if ($oldversion < 2023032902) {
        $dbman = $DB->get_manager();
        // Define field status to be added to jqshow_sessions.
        $table = new xmldb_table('jqshow_sessions');
        $field = new xmldb_field('enablestartdate', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'startdate');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('enableenddate', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'enddate');
        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Jqshow savepoint reached.
        upgrade_mod_savepoint(true, 2023032902, 'jqshow');
    }
    if ($oldversion < 2023032903) {
        $dbman = $DB->get_manager();
        // Define field groupmode to be dropped from jqshow_sessions.
        $table = new xmldb_table('jqshow_sessions');
        $field = new xmldb_field('groupmode');

        // Conditionally launch drop field groupmode.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('groupings', XMLDB_TYPE_TEXT, null, null, null, null, null, 'activetimelimit');
        // Conditionally launch add field groupings.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Jqshow savepoint reached.
        upgrade_mod_savepoint(true, 2023032903, 'jqshow');
    }
}
