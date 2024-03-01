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
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_kuet\api\grade;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_kuet_mod_form extends moodleform_mod {

    /**
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function definition() : void {
        $mform =& $this->_form;
        $mform->addElement('text', 'name', get_string('name', 'kuet'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);

        $this->standard_intro_elements(get_string('introduction', 'kuet'));

        // Grade settings.
        $this->standard_grading_coursemodule_elements();
        $mform->removeElement('grade');
        if (property_exists($this->current, 'grade')) {
            $currentgrade = $this->current->grade;
        } else {
            $currentgrade = get_config('core', 'gradepointmax');
        }
        $mform->addElement('hidden', 'grade', $currentgrade);
        $mform->setType('grade', PARAM_FLOAT);

        $grademethodelement = $mform->createElement('select', 'grademethod', get_string('grademethod', 'kuet'),
            mod_kuet_get_grading_options());
        $mform->disabledIf('gradecat', 'grademethod', 'eq', grade::MOD_OPTION_NO_GRADE);
        $mform->disabledIf('gradepass', 'grademethod', 'eq', grade::MOD_OPTION_NO_GRADE);
        $mform->insertElementBefore($grademethodelement, 'gradecat');
        $mform->addHelpButton('grademethod', 'grademethod', 'kuet');

        // Course module elements.
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Add custom completion rules.
     *
     * @return array Array of string IDs of added items, empty array if none
     * @throws coding_exception
     */
    public function add_completion_rules() : array {
        $mform =& $this->_form;

        $mform->addElement(
            'advcheckbox',
            'completionanswerall',
            get_string('completionansweralllabel', 'kuet'),
            get_string('completionansweralldesc', 'kuet')
        );
        // Enable this completion rule by default.
        $mform->setDefault('completionanswerall', 0);
        $mform->setType('completionanswerall', PARAM_INT);
        return ['completionanswerall'];
    }

    /**
     * Determines if completion is enabled for this module.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) : bool {
        return !empty($data['completionanswerall']);
    }
}
