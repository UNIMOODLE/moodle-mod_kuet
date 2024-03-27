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
 * Session form
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\forms;

use coding_exception;
use DateTime;
use dml_exception;
use mod_kuet\api\groupmode;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_sessions;
use moodleform;

global $CFG;
require_once($CFG->libdir.'/formslib.php');

/**
 * Session form class
 */
class sessionform extends moodleform {

    /**
     * Definition
     *
     * @return void
     * @throws coding_exception
     */
    public function definition() : void {
        global $OUTPUT;
        $mform =& $this->_form;
        $customdata = $this->_customdata;

        // Header.
        $mform->addElement('html', '<div class="row">');
        $mform->addElement('html', '<div class="col-12 formcontainer">');
        $mform->addElement('html', '<h6 class="titlecontainer bg-primary">' .
            $OUTPUT->pix_icon('i/config_session', '', 'mod_kuet') .
            get_string('generalsettings', 'mod_kuet') .
            '</h6>');
        $mform->addElement('html', '<div class="formconcontent col-xl-6 offset-xl-3 col-12">');
        // Name.
        $nameparams = [
            'placeholder' => get_string('session_name_placeholder', 'mod_kuet')];
        $mform->addElement('text', 'name',
            get_string('session_name', 'mod_kuet'), $nameparams);
        $mform->setType('name', PARAM_RAW);
        $mform->addHelpButton('name', 'session_name', 'mod_kuet');
        $mform->addRule('name', get_string('required'), 'required');

        // Anonymousanswer.
        $mform->addElement('select', 'anonymousanswer',
            get_string('anonymousanswer', 'mod_kuet'), $customdata['anonymousanswerchoices']);
        $mform->setType('anonymousanswer', PARAM_RAW);
        $mform->addHelpButton('anonymousanswer', 'anonymousanswer', 'kuet');

        // Sessionmode.
        $mform->addElement('select', 'sessionmode',
            get_string('sessionmode', 'mod_kuet'), $customdata['sessionmodechoices']);
        $mform->setType('sessionmode', PARAM_RAW);
        $mform->addHelpButton('sessionmode', 'sessionmode', 'kuet');

        // Grade method.
        if ($customdata['showsgrade']) {
            $mform->addElement('checkbox', 'sgrade', get_string('sgrade', 'mod_kuet'));
            $mform->setType('sgrade', PARAM_INT);
            $mform->addHelpButton('sgrade', 'sgrade', 'kuet');
        }

        // Countdown.
        $mform->addElement('checkbox', 'countdown', get_string('countdown', 'mod_kuet'));
        $mform->setType('countdown', PARAM_INT);
        $mform->setDefault('countdown', 1);
        $mform->addHelpButton('countdown', 'countdown', 'kuet');

        // Hide grade and ranking between questions.
        $mform->addElement('checkbox', 'showgraderanking', get_string('showgraderanking', 'mod_kuet'));
        $mform->setType('showgraderanking', PARAM_INT);
        $mform->hideIf('showgraderanking', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->hideIf('showgraderanking', 'sessionmode', 'eq', sessions::INACTIVE_PROGRAMMED);
        $mform->setDefault('showgraderanking', 1);
        $mform->addHelpButton('showgraderanking', 'showgraderanking', 'kuet');

        // Randomquestions.
        $mform->addElement('checkbox', 'randomquestions', get_string('randomquestions', 'mod_kuet'));
        $mform->setType('randomquestions', PARAM_INT);
        $mform->addHelpButton('randomquestions', 'randomquestions', 'kuet');
        $mform->hideIf('randomquestions', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->hideIf('randomquestions', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);
        $mform->hideIf('randomquestions', 'sessionmode', 'eq', sessions::RACE_MANUAL);

        // Randomanswers.
        $mform->addElement('checkbox', 'randomanswers', get_string('randomanswers', 'mod_kuet'));
        $mform->setType('randomanswers', PARAM_INT);
        $mform->addHelpButton('randomanswers', 'randomanswers', 'kuet');

        // Showfeedback.
        $mform->addElement('checkbox', 'showfeedback', get_string('showfeedback', 'mod_kuet'));
        $mform->setType('showfeedback', PARAM_INT);
        $mform->setDefault('showfeedback', 1);
        $mform->addHelpButton('showfeedback', 'showfeedback', 'kuet');

        // Showfinalgrade.
        $mform->addElement('checkbox', 'showfinalgrade', get_string('showfinalgrade', 'mod_kuet'));
        $mform->setType('showfinalgrade', PARAM_INT);
        $mform->setDefault('showfinalgrade', 1);
        $mform->addHelpButton('showfinalgrade', 'showfinalgrade', 'kuet');

        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');
        // Header.
        $mform->addElement('html', '<div class="row">');
        $mform->addElement('html', '<div class="col-12 formcontainer">');
        $mform->addElement('html', '<h6  class="titlecontainer bg-primary">' .
            $OUTPUT->pix_icon('i/config_session', '', 'mod_kuet') .
            get_string('timesettings', 'mod_kuet') .
            '</h6>');
        $mform->addElement('html', '<div class="formconcontent col-xl-6 offset-xl-3 col-12">');

        // Automaticstart.
        $mform->addElement('checkbox', 'automaticstart', get_string('automaticstart', 'mod_kuet'));
        $mform->setType('automaticstart', PARAM_INT);
        $mform->hideIf('automaticstart', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->hideIf('automaticstart', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);
        $mform->hideIf('automaticstart', 'sessionmode', 'eq', sessions::RACE_MANUAL);
        $mform->addHelpButton('automaticstart', 'automaticstart', 'kuet');

        // Openquiz - Startdate.
        $date = new DateTime();
        $date->setTime($date->format('H'), ceil($date->format('i') / 10) * 10, 0);
        $mform->addElement('date_time_selector', 'startdate',
            get_string('startdate', 'mod_kuet'), ['optional' => false, 'defaulttime' => $date->getTimestamp()]);
        $mform->hideIf('startdate', 'automaticstart', 'notchecked');
        $mform->hideIf('startdate', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->hideIf('startdate', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);
        $mform->hideIf('startdate', 'sessionmode', 'eq', sessions::RACE_MANUAL);
        $mform->addHelpButton('startdate', 'startdate', 'kuet');

        // Closequiz - enddate.
        $mform->addElement('date_time_selector', 'enddate',
            get_string('enddate', 'mod_kuet'), ['optional' => false, 'defaulttime' => $date->getTimestamp() + 3600]);
        $mform->hideIf('enddate', 'automaticstart', 'notchecked');
        $mform->hideIf('enddate', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->hideIf('enddate', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);
        $mform->hideIf('enddate', 'sessionmode', 'eq', sessions::RACE_MANUAL);
        $mform->hideIf('enddate', 'startdate[enabled]', 'notchecked');
        $mform->addHelpButton('enddate', 'enddate', 'kuet');

        // Time mode.
        $mform->addElement('select', 'timemode',
            get_string('timemode', 'mod_kuet'), $customdata['timemode']);
        $mform->setType('timemode', PARAM_INT);
        $mform->addHelpButton('timemode', 'timemode', 'mod_kuet');

        $mform->addElement('duration', 'sessiontime', get_string('session_time', 'mod_kuet'),
            ['units' => [MINSECS, 1], 'optional' => false]);
        $mform->setType('sessiontime', PARAM_INT);
        $mform->addHelpButton('sessiontime', 'sessiontime', 'mod_kuet');
        $mform->hideIf('sessiontime', 'timemode', 'eq', sessions::NO_TIME);
        $mform->hideIf('sessiontime', 'timemode', 'eq', sessions::QUESTION_TIME);

        $mform->addElement('duration', 'questiontime', get_string('question_time', 'mod_kuet'),
            ['units' => [MINSECS, 1], 'defaultunit' => 1, 'optional' => false]);
        $mform->setType('questiontime', PARAM_INT);
        $mform->addHelpButton('questiontime', 'questiontime', 'mod_kuet');
        $mform->hideIf('questiontime', 'timemode', 'eq', sessions::NO_TIME);
        $mform->hideIf('questiontime', 'timemode', 'eq', sessions::SESSION_TIME);

        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');

        // In case mode group activates.
        if (!empty($customdata['groupingsselect'])) {
            // Header.
            $mform->addElement('html', '<div class="col-12 formcontainer">');
            $mform->addElement('html', '<h6  class="titlecontainer bg-primary">' .
                $OUTPUT->pix_icon('i/config_session', '', 'mod_kuet') .
                get_string('accessrestrictions', 'mod_kuet') .
                '</h6>');
            $mform->addElement('html', '<div class="formconcontent col-xl-6 offset-xl-3 col-12">');
            $select = $mform->addElement('select', 'groupings',
                get_string('groupings', 'mod_kuet'), $customdata['groupingsselect'], ['cols' => 100]);
            $select->setMultiple(false);
            $mform->setType('groupings', PARAM_INT);
            $mform->addElement('html', '</div>');
            $mform->addElement('html', '</div>');
        }

        $cm = $customdata['cm'];
        if ((int) $cm->groupmode !== 0 && empty($customdata['groupingsselect'])) {
            $mform->addElement('html', '<div class="col-12 formcontainer">');
            $mform->addElement('html', '<h6  class="titlecontainer bg-primary">' .
                $OUTPUT->pix_icon('i/config_session', '', 'mod_kuet') .
                get_string('accessrestrictions', 'mod_kuet') .
                '</h6>');
            $mform->addElement('html', '<div class="formconcontent col-xl-6 offset-xl-3 col-12">');

            $mform->addElement('html', '<div class="alert alert-warning">' .
                get_string('nogroupingscreated', 'mod_kuet') . '</div>');
            $mform->addElement('html', '</div>');
            $mform->addElement('html', '</div>');
        }
        // Hidden params.
        $mform->addElement('hidden', 'kuetid', $customdata['kuetid']);
        $mform->setType('kuetid', PARAM_INT);
        $mform->addElement('hidden', 'groupmode', (int)$cm->groupmode);
        $mform->setType('groupmode', PARAM_INT);
        $mform->addElement('hidden', 'status', sessions::SESSION_CREATING);
        $mform->setType('status', PARAM_INT);
        $mform->addElement('hidden', 'sessionid', 0);
        $mform->setType('sessionid', PARAM_INT);
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');

        $mform->addElement('html', "<script>
            let noTime = document.querySelector('#id_timemode option[value=\"0\"]');
            let timeModeSelector = document.getElementById('id_timemode');
            let sessionModeSelector = document.getElementById('id_sessionmode');
            normalizeTimeMode(sessionModeSelector.value);
            sessionModeSelector.addEventListener('change', function() {
              normalizeTimeMode(this.value);
            });
            function normalizeTimeMode(sessionModeValue) {
                if (sessionModeValue !== 'inactive_manual' && sessionModeValue !== 'inactive_programmed') {
                  noTime.setAttribute('disabled', 'disabled');
                  noTime.classList.add('d-none');
                  if (timeModeSelector.value === '0') {
                      timeModeSelector.value = 1;
                      timeModeSelector.dispatchEvent(new Event('change'));
                  }
              } else {
                  noTime.removeAttribute('disabled');
                  noTime.classList.remove('d-none');
              }
            }
        </script>");

        $this->add_action_buttons(true, get_string('next', 'mod_kuet'));
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     * @throws dml_exception
     * @throws coding_exception
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        // Session name must be unique.
        $haserrorname = false;
        $sessions = kuet_sessions::get_sessions_by_name($data['name'], $data['kuetid']);
        if (count($sessions) === 1) {
            $sesion = reset($sessions);
            $haserrorname = (int)$sesion->id !== (int)$data['sessionid'];
        } else if (count($sessions) > 1) {
            $haserrorname = true;
        }
        if ($haserrorname) {
            $errors['name'] = get_string('sessionalreadyexists', 'mod_kuet');
        }
        // Automatic start.
        if (array_key_exists('automaticstart', $data) && (int)$data['automaticstart'] === 1) {
            if ((int)$data['startdate'] <= time()) {
                $errors['startdate'] = get_string('previousstarterror', 'mod_kuet');
            }
            if ((int)$data['startdate'] >= (int)$data['enddate']) {
                $errors['enddate'] = get_string('startminorend', 'mod_kuet');
            }
        } else {
            $data['startdate'] = 0;
            $data['enddate'] = 0;
        }
        // Groups mode.
        if (array_key_exists('groupmode', $data) && (int)$data['groupmode'] != 0 && empty($data['groupings'])) {
            $errors['groupings'] = get_string('session_groupings_error', 'mod_kuet');
        } else if (array_key_exists('groupmode', $data) && (int)$data['groupmode'] != 0 && !empty($data['groupings'])) {
            $members = groupmode::get_grouping_group_members($data['groupings']);
            if (empty($members)) {
                $errors['groupings'] = get_string('session_groupings_no_members', 'mod_kuet');
            } else {
                $allmembers = [];
                $errorusers = [];
                foreach ($members as $member) {
                    if (in_array($member->id, $allmembers)) {
                        $errorusers[] = $member->username . ':' . $member->groupid;
                    } else {
                        $allmembers[] = $member->id;
                    }
                }
                if (!empty($errorusers)) {
                    $users = implode(',', $errorusers);
                    $errors['groupings'] = get_string('session_groupings_same_user_in_groups', 'mod_kuet', $users);
                }
            }
        }

        // Timemode.
        if ($data['sessionmode'] !== sessions::INACTIVE_MANUAL && $data['sessionmode'] !== sessions::INACTIVE_PROGRAMMED) {
            if (!array_key_exists('timemode', $data)) {
                $errors['timemode'] = get_string('timemodemustbeset', 'mod_kuet');
            } else if ((int)$data['timemode'] === sessions::NO_TIME) {
                $errors['timemode'] = get_string('timemodemustbeset', 'mod_kuet');
            }
        }

        if ($data['timemode'] !== "0") {
            if ((int)$data['questiontime'] === 0 && (int)$data['sessiontime'] === 0) {
                $errors['questiontime'] = get_string('timecannotbezero', 'mod_kuet');
                $errors['sessiontime'] = get_string('timecannotbezero', 'mod_kuet');
            }
        }
        return $errors;
    }
}
