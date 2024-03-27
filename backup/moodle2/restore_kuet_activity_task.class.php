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
 * Restore kuet activity
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/kuet/backup/moodle2/restore_kuet_stepslib.php');

/**
 * Kuet restore task that provides all the settings and steps to perform one complete restore of the activity
 */
class restore_kuet_activity_task extends restore_activity_task {

    /**
     * This should define settings. Not used at the moment.
     *
     * @return void
     */
    protected function define_my_settings() {

    }

    /**
     * Define the structure steps.
     *
     * @return void
     * @throws base_task_exception
     */
    protected function define_my_steps() {
        $this->add_step(new restore_kuet_activity_structure_step('kuet_structure', 'kuet.xml'));
    }

    /**
     * Define decode contents routine
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('kuet', ['intro']),
        ];
    }

    /**
     * Define decode content rules
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('KUETVIEWBYID', '/mod/kuet/view.php?id=$1', 'course_module'),
            new restore_decode_rule('KUETINDEX', '/mod/kuet/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Define restore log rules
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Define restore log rules for a course
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }

}
