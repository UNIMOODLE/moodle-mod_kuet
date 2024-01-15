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

namespace mod_kuet\forms;

use coding_exception;
use mod_kuet\persistents\kuet_sessions;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");

class questionform extends moodleform {
    /**
     * @return void
     * @throws coding_exception
     */
    public function definition() : void {
        $mform =& $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('html', '<div class="row">');
        $mform->addElement('html', '<h4 style="margin:0 auto;">'.$customdata['qname'].'('. $customdata['qtype'] .')'.'</h4>');
        $mform->addElement('html', '</div>');

        $mform->addElement('header', 'timeheader', get_string('questiontime', 'mod_kuet'));
        if ($customdata['sessionlimittimebyquestionsenabled'] === true || $customdata['notimelimit'] === true) {
            $mform->addElement('duration', 'timelimit', get_string('timelimit', 'mod_kuet'),
                ['units' => [MINSECS, 1], 'optional' => true], 'asd');
            $mform->addHelpButton('timelimit', 'timelimit', 'kuet');
            $mform->setType('timelimit', PARAM_INT);
        } else {
            $sid = required_param('sid', PARAM_INT);
            $session = new kuet_sessions($sid);
            $timelimit = userdate($session->get('sessiontime'), '%Mm %Ss');
            $mform->addElement('html', '<div class=" alert alert-warning">');
            $mform->addElement('html', '<div>' .
                get_string('sessionlimittimebyquestionsenabled', 'mod_kuet', $timelimit) .
                '</div>');
            $mform->addElement('html', '</div>');
        }

        $mform->addElement('header', 'gradesheader', get_string('gradesheader', 'mod_kuet'));
        $mform->addElement('checkbox', 'nograding', get_string('nograding', 'mod_kuet'));
        $mform->setType('nograding', PARAM_INT);

        $mform->addElement('hidden', 'id', $customdata['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'kid', $customdata['kid']);
        $mform->setType('kid', PARAM_INT);

        $mform->addElement('hidden', 'sid', $customdata['sid']);
        $mform->setType('sid', PARAM_INT);

        $this->add_action_buttons(true, get_string('save'));
    }
}
