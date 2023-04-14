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


use mod_jqshow\persistents\jqshow_sessions;
use moodleform;

class sessionform extends moodleform {

    /**
     * @inheritDoc
     */
    protected function definition() {
        global $OUTPUT;
        $mform =& $this->_form;
        $customdata = $this->_customdata;

        // Header.
        $mform->addElement('html', '<div class="row">');
        $mform->addElement('html', '<div class="col-12 formcontainer">');
        $mform->addElement('html', '<h5 class="titlecontainer bg-primary">' .
            $OUTPUT->pix_icon('i/config_session', '', 'mod_jqshow') .
            get_string('generalsettings', 'mod_jqshow') .
            '</h5>');
        $mform->addElement('html', '<div class="formconcontent col-xl-6 offset-xl-3 col-12">');
        // Name.
        $nameparams = [
            'placeholder' => get_string('session_name_placeholder', 'mod_jqshow')];
        $mform->addElement('text', 'name',
            get_string('session_name', 'mod_jqshow'), $nameparams);
        $mform->setType('name', PARAM_RAW);
        $mform->addHelpButton('name', 'session_name', 'mod_jqshow');
        $mform->addRule('name', get_string('required'), 'required');

        // Anonymousanswer.
        $mform->addElement('select', 'anonymousanswer',
            get_string('anonymousanswer', 'mod_jqshow'), $customdata['anonymousanswerchoices']);
        $mform->setType('anonymousanswer', PARAM_RAW);

        // Allowguests.
        $mform->addElement('selectyesno', 'allowguests', get_string('allowguests', 'mod_jqshow'));
        $mform->setType('allowguests', PARAM_INT);

        // Advancemode.
        $mform->addElement('select', 'advancemode',
            get_string('advancemode', 'mod_jqshow'), $customdata['advancemode']);
        $mform->setType('advancemode', PARAM_RAW);

        // Gamemode.
        $mform->addElement('select', 'gamemode',
            get_string('gamemode', 'mod_jqshow'), $customdata['gamemode']);
        $mform->setType('gamemode', PARAM_RAW);

        // Countdown.
        $mform->addElement('select', 'countdown',
            get_string('countdown', 'mod_jqshow'), $customdata['countdown']);
        $mform->setType('countdown', PARAM_INT);

        // Randomquestions.
        $mform->addElement('selectyesno', 'randomquestions', get_string('randomquestions', 'mod_jqshow'));
        $mform->setType('randomquestions', PARAM_INT);

        // Randomanswers.
        $mform->addElement('selectyesno', 'randomanswers', get_string('randomanswers', 'mod_jqshow'));
        $mform->setType('randomanswers', PARAM_INT);

        // Showfeedback.
        $mform->addElement('selectyesno', 'showfeedback', get_string('showfeedback', 'mod_jqshow'));
        $mform->setType('showfeedback', PARAM_INT);

        // Showfinalgrade.
        $mform->addElement('selectyesno', 'showfinalgrade', get_string('showfinalgrade', 'mod_jqshow'));
        $mform->setType('showfinalgrade', PARAM_INT);

        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');
        // Header.
//        $mform->addElement('header', 'timesettings', get_string('timesettings', 'mod_jqshow'));
        $mform->addElement('html', '<div class="row">');
        $mform->addElement('html', '<div class="col-12 formcontainer">');
        $mform->addElement('html', '<h5  class="titlecontainer bg-primary">' .
            $OUTPUT->pix_icon('i/config_session', '', 'mod_jqshow') .
            get_string('timesettings', 'mod_jqshow') .
            '</h5>');
        $mform->addElement('html', '<div class="formconcontent col-xl-6 offset-xl-3 col-12">');
        // Openquiz.
//        $mform->addElement('html', '<h2>' . get_string('openquiz', 'mod_jqshow') . '</h2>');

        // Openquiz - enable.
//        $mform->addElement('selectyesno', 'openquizenable', get_string('openquizenable', 'mod_jqshow'));
//        $mform->setType('openquizenable', PARAM_INT);

        // Openquiz - Startdate.
        $mform->addElement('date_time_selector', 'startdate',
            get_string('startdate', 'mod_jqshow'), ['optional' => true]);
//        $mform->addHelpButton('startdate', 'startdate', 'mod_jqshow');
        $mform->disabledIf('startdate', 'advancemode', 'eq', 'manual');

        // Closequiz.
//        $mform->addElement('html', '<h2>' . get_string('closequiz', 'mod_jqshow') . '</h2>');

        // Closequiz - enable.
//        $mform->addElement('selectyesno', 'closequizenable', get_string('closequizenable', 'mod_jqshow'));
//        $mform->setType('closequizenable', PARAM_INT);

        // Closequiz - enddate.
        $mform->addElement('date_time_selector', 'enddate',
            get_string('enddate', 'mod_jqshow'), ['optional' => true]);
//        $mform->addHelpButton('startdaenddatete', 'enddate', 'mod_jqshow');
        $mform->disabledIf('enddate', 'advancemode', 'eq', 'manual');

        // Automaticstart.
        $mform->addElement('selectyesno', 'automaticstart', get_string('automaticstart', 'mod_jqshow'));
        $mform->setType('automaticstart', PARAM_INT);

//        $mform->addElement('html', '<span  class="bold">' . get_string('timelimit', 'mod_jqshow') . '</span>');
        // Automaticstart.
        $mform->addElement('selectyesno', 'activetimelimit', get_string('activetimelimit', 'mod_jqshow'));
        $mform->setType('activetimelimit', PARAM_INT);

        // Timelimit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'mod_jqshow'));
        $mform->setType('timelimit', PARAM_INT);
        $mform->disabledIf('timelimit', 'activetimelimit', 'eq', 0);

        // Add time question enable.
        $mform->addElement('selectyesno', 'addtimequestionenable', get_string('addtimequestionenable', 'mod_jqshow'), 'asdasd');
        $mform->setType('addtimequestionenable', PARAM_INT);
        $mform->disabledIf('addtimequestionenable', 'activetimelimit', 'eq', 1);
        $mform->disabledIf('activetimelimit', 'addtimequestionenable', 'eq', 1);

        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');

        // In case mode group activates.
        if (!empty($customdata['groupingsselect'])) {
            // Header.
    //        $mform->addElement('header', 'accessrestrictions', get_string('accessrestrictions', 'mod_jqshow'));
            $mform->addElement('html', '<div class="col-12 formcontainer">');
            $mform->addElement('html', '<h5  class="titlecontainer bg-primary">' .
                $OUTPUT->pix_icon('i/config_session', '', 'mod_jqshow') .
                get_string('accessrestrictions', 'mod_jqshow') .
                '</h5>');
            $mform->addElement('html', '<div class="formconcontent col-xl-6 offset-xl-3 col-12">');
            $select = $mform->addElement('select', 'groupings',
                get_string('groupings', 'mod_jqshow'), $customdata['groupingsselect'], ['cols' => 100]);
            $select->setMultiple(true);
            $mform->setType('groupings', PARAM_INT);
            $mform->addElement('html', '</div>');
            $mform->addElement('html', '</div>');
        }

        // Hidden params.
        $mform->addElement('hidden', 'jqshowid', $customdata['jqshowid']);
        $mform->setType('jqshowid', PARAM_INT);
        $mform->addElement('hidden', 'groupmode', 0);
        $mform->setType('groupmode', PARAM_INT);
        $mform->addElement('hidden', 'status', 1);
        $mform->setType('status', PARAM_INT);
        $mform->addElement('hidden', 'sessionid', 0);
        $mform->setType('sessionid', PARAM_INT);
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');

        $this->add_action_buttons(true, get_string('next', 'mod_jqshow'));
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) : array {
        $errors = parent::validation($data, $files);
        if (0 == $data['sessionid']) {
            // Session name must be unique.
            $sessions = jqshow_sessions::get_records(['name' => $data['name']]);
            if (!empty($sessions)) {
                $errors['name'] = get_string('sessionalreadyexists', 'mod_jqshow');
            }
        }

        return $errors;
    }
}
