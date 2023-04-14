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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");

class questionform extends \moodleform {
    public function definition() {
        $mform =& $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('html', '<div class="row">');
        $mform->addElement('html', '<h4 style="margin:0 auto;">'.$customdata['qname'].'('. $customdata['qtype'] .')'.'</h4>');
        $mform->addElement('html', '</div>');


        $mform->addElement('header', 'timeheader', get_string('questiontime', 'mod_jqshow'));


        $mform->addElement('checkbox', 'hastimelimit', get_string('notimelimit', 'mod_jqshow'));
        $mform->setType('hastimelimit', PARAM_INT);

        $objs = array();
        $objs[] =& $mform->createElement('text', 'timelimitvalue', null);
        $options = ['secs' => 'seconds', 'min' => 'minutes'];
        $objs[] =& $mform->createElement('select', 'timelimittype', null, $options);
        $mform->addElement('group', 'timelimit', get_string('notimelimit', 'mod_jqshow'), $objs, ' ', false);
        $mform->setType('timelimitvalue', PARAM_INT);
        $mform->setType('timelimittype', PARAM_RAW);
        $mform->disabledIf('timelimitvalue', 'hastimelimit', 'eq', 1);
        $mform->disabledIf('timelimittype', 'hastimelimit', 'eq', 1);

        $mform->addElement('header', 'gradesheader', get_string('gradesheader', 'mod_jqshow'));
        $mform->addElement('checkbox', 'nograding', get_string('nograding', 'mod_jqshow'));
        $mform->setType('nograding', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $customdata['cmid']);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'qid', $customdata['qid']);
        $mform->setType('qid', PARAM_INT);

        $mform->addElement('hidden', 'sid', $customdata['sid']);
        $mform->setType('sid', PARAM_INT);

        $this->add_action_buttons(true, get_string('save'));
    }
}
