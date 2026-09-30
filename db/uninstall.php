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
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Uninstall routine.
 *
 * Only the socket certificate and private key files are removed here. They are
 * stored by admin_setting_configstoredfile under the 'kuet' component, not
 * 'mod_kuet', so the delete_component_files() call in uninstall_plugin() does
 * not reach them.
 *
 * The plugin tables are deliberately NOT dropped here. uninstall_plugin() calls
 * this function before core\plugininfo\mod::uninstall_cleanup(), which runs
 * grade_uninstalled_module() to remove the leftover grade items; that goes
 * through grade_grade::notify_changed() and get_coursemodule_from_instance(),
 * which reads the {kuet} table. Dropping it here made the whole uninstall abort
 * with a dmlreadexception and leave orphaned grade items behind. Core drops
 * every table declared in db/install.xml right afterwards anyway.
 *
 * @return bool
 */
function xmldb_kuet_uninstall(): bool {
    try {
        $syscontext = context_system::instance();
        $fs = get_file_storage();
        if ($fs !== null && $syscontext !== null) {
            $certificatefiles = $fs->get_area_files(
                $syscontext->id,
                'kuet',
                'certificate_ssl',
                0,
                'filename',
                false
            );
            foreach ($certificatefiles as $file) {
                $file->delete();
            }
            $privatekeyfiles = $fs->get_area_files(
                $syscontext->id,
                'kuet',
                'privatekey_ssl',
                0,
                'filename',
                false
            );
            foreach ($privatekeyfiles as $file) {
                $file->delete();
            }
        }
        return true;
    } catch (Exception $e) {
        debugging('mod_kuet uninstall could not delete the socket certificate files: ' . $e->getMessage());
        return false;
    }
}
