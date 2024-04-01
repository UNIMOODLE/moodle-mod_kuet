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
 * Uninstall routines
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Uninstall routine
 *
 * @return bool
 */
function xmldb_kuet_uninstall(): bool {
    global $DB;
    try {
        $syscontext = context_system::instance();
        $fs = get_file_storage();
        if ($fs !== null && $syscontext !== null) {
            $certificatefiles = $fs->get_area_files(
                $syscontext->id, 'kuet', 'certificate_ssl', 0, 'filename', false
            );
            foreach ($certificatefiles as $file) {
                $file->delete();
            }
            $privatekeyfiles = $fs->get_area_files(
                $syscontext->id, 'kuet', 'privatekey_ssl', 0, 'filename', false
            );
            foreach ($privatekeyfiles as $file) {
                $file->delete();
            }
        }
        $dbman = $DB->get_manager();
        $kuet = new xmldb_table('kuet');
        $dbman->drop_table($kuet);
        $kuetgrades = new xmldb_table('kuet_grades');
        $dbman->drop_table($kuetgrades);
        $kuetquestions = new xmldb_table('kuet_questions');
        $dbman->drop_table($kuetquestions);
        $questionsresponses = new xmldb_table('questions_responses');
        $dbman->drop_table($questionsresponses);
        $kuetsessions = new xmldb_table('kuet_sessions');
        $dbman->drop_table($kuetsessions);
        $kuetsessionsgrades = new xmldb_table('kuet_sessions_grades');
        $dbman->drop_table($kuetsessionsgrades);
        $kuetuserprogress = new xmldb_table('kuet_user_progress');
        $dbman->drop_table($kuetuserprogress);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
