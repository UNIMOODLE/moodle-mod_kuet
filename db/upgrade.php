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
    return true;
}
