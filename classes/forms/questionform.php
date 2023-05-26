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

namespace mod_jqshow\forms;

use coding_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");

class questionform extends \moodleform {
    /**
     * @return void
     * @throws coding_exception
     */
    public function definition() {
        $mform =& $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('html', '<div class="row">');
        $mform->addElement('html', '<h4 style="margin:0 auto;">'.$customdata['qname'].'('. $customdata['qtype'] .')'.'</h4>');
        $mform->addElement('html', '</div>');

        $mform->addElement('header', 'timeheader', get_string('questiontime', 'mod_jqshow'));

        if ($customdata['sessionlimittimebyquestionsenabled']) {
            $mform->addElement('html', '<div class=" alert alert-warning">');
            $mform->addElement('html', '<div>' . get_string('sessionlimittimebyquestionsenabled', 'mod_jqshow'). '</div>');
            $mform->addElement('html', '</div>');
        }

        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'mod_jqshow'), array('optional' => true), 'asd');
        $mform->setType('timelimit', PARAM_INT);
        $mform->addHelpButton('timelimit', 'qtimelimit', 'jqshow');

        $mform->addElement('header', 'gradesheader', get_string('gradesheader', 'mod_jqshow'));
        $mform->addElement('checkbox', 'nograding', get_string('nograding', 'mod_jqshow'));
        $mform->setType('nograding', PARAM_INT);

        $mform->addElement('hidden', 'id', $customdata['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'jqid', $customdata['jqid']);
        $mform->setType('jqid', PARAM_INT);

        $mform->addElement('hidden', 'sid', $customdata['sid']);
        $mform->setType('sid', PARAM_INT);

        $this->add_action_buttons(true, get_string('save'));
    }
}
