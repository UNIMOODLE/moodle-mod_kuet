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


/**
 * @param $oldversion
 * @return true
 */
function xmldb_jqshow_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2023071800) {

        // Define field grademethod to be added to jqshow.
        $table = new xmldb_table('jqshow');
        $field = new xmldb_field('grademethod', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0, 'badgepositions');

        // Conditionally launch add field grademethod.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field badgepositions to be dropped from jqshow.
        $table = new xmldb_table('jqshow');
        $field = new xmldb_field('badgepositions');

        // Conditionally launch drop field badgepositions.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field grademethod to be added to jqshow.
        $table = new xmldb_table('jqshow_sessions');
        $field = new xmldb_field('sgrademethod', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0, 'sessionmode');

        // Conditionally launch add field grademethod.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Jqshow savepoint reached.
        upgrade_mod_savepoint(true, 2023071800, 'jqshow');
    }
    return true;
}
