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
use core\persistent;
use core_reportbuilder\local\aggregation\count;
use dml_exception;
use mod_jqshow\models\sessions;
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
        $mform->addElement('checkbox', 'allowguests', get_string('allowguests', 'mod_jqshow'));
        $mform->setType('allowguests', PARAM_INT);

        // Sessionmode.
        $mform->addElement('select', 'sessionmode',
            get_string('sessionmode', 'mod_jqshow'), $customdata['sessionmodechoices']);
        $mform->setType('sessionmode', PARAM_RAW);

        // Countdown.
        $mform->addElement('select', 'countdown',
            get_string('countdown', 'mod_jqshow'), $customdata['countdown']);
        $mform->setType('countdown', PARAM_INT);

        // Hide grade and ranking between questions.
        $mform->addElement('checkbox', 'hidegraderanking', get_string('hidegraderankingbtweenquestions', 'mod_jqshow'));
        $mform->setType('hidegraderanking', PARAM_INT);
        $mform->disabledIf('hidegraderanking', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);

        // Randomquestions.
        $mform->addElement('checkbox', 'randomquestions', get_string('randomquestions', 'mod_jqshow'));
        $mform->setType('randomquestions', PARAM_INT);

        // Randomanswers.
        $mform->addElement('checkbox', 'randomanswers', get_string('randomanswers', 'mod_jqshow'));
        $mform->setType('randomanswers', PARAM_INT);

        // Showfeedback.
        $mform->addElement('checkbox', 'showfeedback', get_string('showfeedback', 'mod_jqshow'));
        $mform->setType('showfeedback', PARAM_INT);

        // Showfinalgrade.
        $mform->addElement('checkbox', 'showfinalgrade', get_string('showfinalgrade', 'mod_jqshow'));
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
//        $mform->addElement('checkbox', 'openquizenable', get_string('openquizenable', 'mod_jqshow'));
//        $mform->setType('openquizenable', PARAM_INT);

        // Openquiz - Startdate.
        $mform->addElement('date_selector', 'startdate',
            get_string('startdate', 'mod_jqshow'), ['optional' => true]);
        $mform->disabledIf('startdate', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->disabledIf('startdate', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);

        // Closequiz.
//        $mform->addElement('html', '<h2>' . get_string('closequiz', 'mod_jqshow') . '</h2>');

        // Closequiz - enable.
//        $mform->addElement('checkbox', 'closequizenable', get_string('closequizenable', 'mod_jqshow'));
//        $mform->setType('closequizenable', PARAM_INT);

        // Closequiz - enddate.
        $mform->addElement('date_selector', 'enddate',
            get_string('enddate', 'mod_jqshow'), ['optional' => true]);
//        $mform->addHelpButton('enddate', 'enddate', 'mod_jqshow');
        $mform->disabledIf('enddate', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->disabledIf('enddate', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);
        $mform->disabledIf('enddate', 'startdate[enabled]', 'notchecked');

        // Automaticstart.
        $mform->addElement('checkbox', 'automaticstart', get_string('automaticstart', 'mod_jqshow'));
        $mform->setType('automaticstart', PARAM_INT);
        $mform->disabledIf('automaticstart', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->disabledIf('automaticstart', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);
        $mform->disabledIf('automaticstart', 'startdate[enabled]', 'notchecked');
        $mform->disabledIf('automaticstart', 'enddate[enabled]', 'notchecked');

//        $mform->addElement('html', '<span  class="bold">' . get_string('timelimit', 'mod_jqshow') . '</span>');
        // Automaticstart.
        $mform->addElement('checkbox', 'activetimelimit', get_string('activetimelimit', 'mod_jqshow'));
        $mform->setType('activetimelimit', PARAM_INT);
        $mform->disabledIf('activetimelimit', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->disabledIf('activetimelimit', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);

        // Timelimit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'mod_jqshow'));
        $mform->setType('timelimit', PARAM_INT);
        $mform->disabledIf('timelimit', 'activetimelimit', 'eq', 0);
        $mform->disabledIf('timelimit', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->disabledIf('timelimit', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);

        // Add time question enable.
        $mform->addElement('checkbox', 'addtimequestion', get_string('addtimequestion', 'mod_jqshow'), 'asdasd');
        $mform->setType('addtimequestion', PARAM_INT);
        $mform->disabledIf('addtimequestion', 'activetimelimit', 'eq', 1);
        $mform->disabledIf('activetimelimit', 'addtimequestion', 'eq', 1);
        $mform->disabledIf('addtimequestion', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->disabledIf('addtimequestion', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);

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
     * @throws dml_exception
     * @throws coding_exception
     */
    public function validation($data, $files) : array {
        $errors = parent::validation($data, $files);
        // Session name must be unique.
        $haserror = false;
        $sessions = jqshow_sessions::get_sessions_by_name($data['name']);
        if (count($sessions) === 1) {
            $sesion = reset($sessions);
            $haserror = $sesion->id != $data['sessionid'];
        } else if (count($sessions) > 1) {
            $haserror = true;
        }
        if ($haserror) {
            $errors['name'] = get_string('sessionalreadyexists', 'mod_jqshow');
        }

        return $errors;
    }
}
