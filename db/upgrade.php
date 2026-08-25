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
 * Update routines
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Database tables upgrade
 *
 * @param $oldversion
 * @return true
 * @throws ddl_exception
 * @throws ddl_field_missing_exception
 * @throws ddl_table_missing_exception
 * @throws downgrade_exception
 * @throws moodle_exception
 * @throws upgrade_exception
 */
function xmldb_kuet_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2023071800) {
        // Define field grademethod to be added to kuet.
        $table = new xmldb_table('kuet');
        $field = new xmldb_field('grademethod', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0, 'badgepositions');

        // Conditionally launch add field grademethod.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field badgepositions to be dropped from kuet.
        $table = new xmldb_table('kuet');
        $field = new xmldb_field('badgepositions');

        // Conditionally launch drop field badgepositions.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field grademethod to be added to kuet.
        $table = new xmldb_table('kuet_sessions');
        $field = new xmldb_field('sgrade', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0, 'sessionmode');

        // Conditionally launch add field grademethod.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kuet savepoint reached.
        upgrade_mod_savepoint(true, 2023071800, 'kuet');
    }

    if ($oldversion < 2026060200) {
        // KUETEDUCAM-67: maximum grade obtainable per session.
        $table = new xmldb_table('kuet');
        $field = new xmldb_field(
            'sessiongrademax',
            XMLDB_TYPE_NUMBER,
            '10, 5',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'grademethod'
        );

        // Conditionally launch add field sessiongrademax.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Initialise existing instances to the platform gradepointmax so they
        // keep a sensible per-session maximum. Existing session grades are NOT
        // renormalised here: recalculation pushes to the gradebook, which needs
        // get_fast_modinfo() and is forbidden mid-upgrade. Run the CLI script
        // mod/kuet/cli/recalculate_grades.php afterwards to renormalise them.
        $gradepointmax = get_config('core', 'gradepointmax');
        $DB->set_field_select('kuet', 'sessiongrademax', $gradepointmax, 'sessiongrademax = 0');

        // Kuet savepoint reached.
        upgrade_mod_savepoint(true, 2026060200, 'kuet');
    }

    if ($oldversion < 2026062500) {
        // KUETEDUCAM-72: mandatory attendance flag per session.
        $table = new xmldb_table('kuet_sessions');
        $field = new xmldb_field(
            'mandatoryattendance',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'sgrade'
        );

        // Conditionally launch add field mandatoryattendance. Existing sessions
        // default to mandatory (1) so their grading behaviour is unchanged: a
        // non-attendee keeps scoring 0 and the session keeps counting.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kuet savepoint reached.
        upgrade_mod_savepoint(true, 2026062500, 'kuet');
    }

    if ($oldversion < 2026062501) {
        // KUETEDUCAM-73: manual mark override and its justifying comment per response.
        $table = new xmldb_table('kuet_questions_responses');

        // Teacher override for the question mark. Nullable, no fill: NULL means
        // "no override" so existing responses keep their calculated mark.
        $field = new xmldb_field(
            'manualmark',
            XMLDB_TYPE_NUMBER,
            '10, 5',
            null,
            null,
            null,
            null,
            'response'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Mandatory comment justifying the override (only the last value kept).
        $field = new xmldb_field('manualcomment', XMLDB_TYPE_TEXT, null, null, null, null, null, 'manualmark');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kuet savepoint reached.
        upgrade_mod_savepoint(true, 2026062501, 'kuet');
    }
    return true;
}
