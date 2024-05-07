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
 * Backup kuet activity task
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/kuet/backup/moodle2/backup_kuet_stepslib.php');

/**
 * Backup task that provides all the settings and steps to perform one complete backup.
 */
class backup_kuet_activity_task extends backup_activity_task {

    /**
     * This should define settings. Not used at the moment.
     *
     * @return void
     */
    protected function define_my_settings() {

    }

    /**
     * Define (add) particular steps this activity can have.
     *
     * @return void
     * @throws base_task_exception
     */
    protected function define_my_steps() {
        $this->add_step(new backup_kuet_activity_structure_step('kuet_structure', 'kuet.xml'));
        $this->add_step(new backup_calculate_question_categories('activity_question_categories'));
        $this->add_step(new backup_delete_temp_questions('clean_temp_questions'));
    }

    /**
     * Code the transformations to perform in the activity in order to get transportable (encoded) links.
     *
     * @param $content
     * @return array|string|string[]|null
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        // Link to the list of JazzQuizes.
        $search = "/(" . $base . "\/mod\/kuet\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@KUETINDEX*$2@$', $content);
        // Link to JazzQuiz view by moduleid.
        $search = "/(" . $base . "\/mod\/kuet\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@KUETVIEWBYID*$2@$', $content);
        return $content;
    }

}
