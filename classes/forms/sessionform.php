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
use DateTime;
use dml_exception;
use mod_jqshow\models\sessions;
use mod_jqshow\models\sessions as sessionsmodel;
use mod_jqshow\persistents\jqshow_sessions;
use moodleform;

class sessionform extends moodleform {

    /**
     * @return void
     * @throws coding_exception
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
        $mform->addHelpButton('anonymousanswer', 'anonymousanswer', 'jqshow');

        // Sessionmode.
        $mform->addElement('select', 'sessionmode',
            get_string('sessionmode', 'mod_jqshow'), $customdata['sessionmodechoices']);
        $mform->setType('sessionmode', PARAM_RAW);
        $mform->addHelpButton('sessionmode', 'sessionmode', 'jqshow');

        // Grade method.
        if ($customdata['showsgrade']) {
            $mform->addElement('checkbox', 'sgrade', get_string('sgrade', 'mod_jqshow'));
            $mform->setType('sgrade', PARAM_INT);
            $mform->addHelpButton('sgrade', 'sgrade', 'jqshow');
        }

        // Countdown.
        $mform->addElement('checkbox', 'countdown', get_string('countdown', 'mod_jqshow'));
        $mform->setType('countdown', PARAM_INT);
        $mform->setDefault('countdown', 1);
        $mform->addHelpButton('countdown', 'countdown', 'jqshow');

        // Hide grade and ranking between questions.
        $mform->addElement('checkbox', 'showgraderanking', get_string('showgraderanking', 'mod_jqshow'));
        $mform->setType('showgraderanking', PARAM_INT);
        $mform->hideIf('showgraderanking', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->hideIf('showgraderanking', 'sessionmode', 'eq', sessions::INACTIVE_PROGRAMMED);
        $mform->setDefault('showgraderanking', 1);
        $mform->addHelpButton('showgraderanking', 'showgraderanking', 'jqshow');

        // Randomquestions.
        $mform->addElement('checkbox', 'randomquestions', get_string('randomquestions', 'mod_jqshow'));
        $mform->setType('randomquestions', PARAM_INT);
        $mform->addHelpButton('randomquestions', 'randomquestions', 'jqshow');
        $mform->hideIf('randomquestions', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->hideIf('randomquestions', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);
        $mform->hideIf('randomquestions', 'sessionmode', 'eq', sessions::RACE_MANUAL);

        // Randomanswers.
        $mform->addElement('checkbox', 'randomanswers', get_string('randomanswers', 'mod_jqshow'));
        $mform->setType('randomanswers', PARAM_INT);
        $mform->addHelpButton('randomanswers', 'randomanswers', 'jqshow');

        // Showfeedback.
        $mform->addElement('checkbox', 'showfeedback', get_string('showfeedback', 'mod_jqshow'));
        $mform->setType('showfeedback', PARAM_INT);
        $mform->setDefault('showfeedback', 1);
        $mform->addHelpButton('showfeedback', 'showfeedback', 'jqshow');

        // Showfinalgrade.
        $mform->addElement('checkbox', 'showfinalgrade', get_string('showfinalgrade', 'mod_jqshow'));
        $mform->setType('showfinalgrade', PARAM_INT);
        $mform->setDefault('showfinalgrade', 1);
        $mform->addHelpButton('showfinalgrade', 'showfinalgrade', 'jqshow');

        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');
        // Header.
        $mform->addElement('html', '<div class="row">');
        $mform->addElement('html', '<div class="col-12 formcontainer">');
        $mform->addElement('html', '<h5  class="titlecontainer bg-primary">' .
            $OUTPUT->pix_icon('i/config_session', '', 'mod_jqshow') .
            get_string('timesettings', 'mod_jqshow') .
            '</h5>');
        $mform->addElement('html', '<div class="formconcontent col-xl-6 offset-xl-3 col-12">');

        // Automaticstart.
        $mform->addElement('checkbox', 'automaticstart', get_string('automaticstart', 'mod_jqshow'));
        $mform->setType('automaticstart', PARAM_INT);
        $mform->hideIf('automaticstart', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->hideIf('automaticstart', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);
        $mform->hideIf('automaticstart', 'sessionmode', 'eq', sessions::RACE_MANUAL);
        $mform->addHelpButton('automaticstart', 'automaticstart', 'jqshow');

        // Openquiz - Startdate.
        $date = new DateTime();
        $date->setTime($date->format('H'), ceil($date->format('i') / 10) * 10, 0);
        $mform->addElement('date_time_selector', 'startdate',
            get_string('startdate', 'mod_jqshow'), ['optional' => false, 'defaulttime' => $date->getTimestamp()]);
        $mform->hideIf('startdate', 'automaticstart', 'notchecked');
        $mform->hideIf('startdate', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->hideIf('startdate', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);
        $mform->hideIf('startdate', 'sessionmode', 'eq', sessions::RACE_MANUAL);
        $mform->addHelpButton('startdate', 'startdate', 'jqshow');

        // Closequiz - enddate.
        $mform->addElement('date_time_selector', 'enddate',
            get_string('enddate', 'mod_jqshow'), ['optional' => false, 'defaulttime' => $date->getTimestamp() + 3600]);
        $mform->hideIf('enddate', 'automaticstart', 'notchecked');
        $mform->hideIf('enddate', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
        $mform->hideIf('enddate', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);
        $mform->hideIf('enddate', 'sessionmode', 'eq', sessions::RACE_MANUAL);
        $mform->hideIf('enddate', 'startdate[enabled]', 'notchecked');
        $mform->addHelpButton('enddate', 'enddate', 'jqshow');

        // Time mode.
        $mform->addElement('select', 'timemode',
            get_string('timemode', 'mod_jqshow'), $customdata['timemode']);
        $mform->setType('timemode', PARAM_INT);
        $mform->addHelpButton('timemode', 'timemode', 'mod_jqshow');
//        $mform->disabledIf('timemode', 'sessionmode', 'eq', sessions::INACTIVE_MANUAL);
//        $mform->disabledIf('timemode', 'sessionmode', 'eq', sessions::PODIUM_MANUAL);
//        $mform->disabledIf('timemode', 'sessionmode', 'eq', sessions::RACE_MANUAL);

        $mform->addElement('duration', 'sessiontime', get_string('session_time', 'mod_jqshow'),
            ['units' => [MINSECS, 1], 'optional' => false]);
        $mform->setType('sessiontime', PARAM_INT);
        $mform->addHelpButton('sessiontime', 'sessiontime', 'mod_jqshow');
        $mform->hideIf('sessiontime', 'timemode', 'eq', sessions::NO_TIME);
        $mform->hideIf('sessiontime', 'timemode', 'eq', sessions::QUESTION_TIME);

        $mform->addElement('duration', 'questiontime', get_string('question_time', 'mod_jqshow'),
            ['units' => [MINSECS, 1], 'defaultunit' => 1, 'optional' => false]);
        $mform->setType('questiontime', PARAM_INT);
        $mform->addHelpButton('questiontime', 'questiontime', 'mod_jqshow');
        $mform->hideIf('questiontime', 'timemode', 'eq', sessions::NO_TIME);
        $mform->hideIf('questiontime', 'timemode', 'eq', sessions::SESSION_TIME);

        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');

        // In case mode group activates.
        if (!empty($customdata['groupingsselect'])) {
            // Header.
            $mform->addElement('html', '<div class="col-12 formcontainer">');
            $mform->addElement('html', '<h5  class="titlecontainer bg-primary">' .
                $OUTPUT->pix_icon('i/config_session', '', 'mod_jqshow') .
                get_string('accessrestrictions', 'mod_jqshow') .
                '</h5>');
            $mform->addElement('html', '<div class="formconcontent col-xl-6 offset-xl-3 col-12">');
            $select = $mform->addElement('select', 'groupings',
                get_string('groupings', 'mod_jqshow'), $customdata['groupingsselect'], ['cols' => 100]);
            $select->setMultiple(false);
            $mform->setType('groupings', PARAM_INT);
            $mform->addElement('html', '</div>');
            $mform->addElement('html', '</div>');
        }

        $cm = $customdata['cm'];
        if ($cm->groupmode !== '0' && empty($customdata['groupingsselect'])) {
            $mform->addElement('html', '<div class="col-12 formcontainer">');
            $mform->addElement('html', '<h5  class="titlecontainer bg-primary">' .
                $OUTPUT->pix_icon('i/config_session', '', 'mod_jqshow') .
                get_string('accessrestrictions', 'mod_jqshow') .
                '</h5>');
            $mform->addElement('html', '<div class="formconcontent col-xl-6 offset-xl-3 col-12">');


            $mform->addElement('html', '<div class="alert alert-warning">' . get_string('nogroupingscreated', 'mod_jqshow') . '</div>');
            $mform->addElement('html', '</div>');
            $mform->addElement('html', '</div>');
        }
        // Hidden params.
        $mform->addElement('hidden', 'jqshowid', $customdata['jqshowid']);
        $mform->setType('jqshowid', PARAM_INT);
        $mform->addElement('hidden', 'groupmode', (int)$cm->groupmode);
        $mform->setType('groupmode', PARAM_INT);
        $mform->addElement('hidden', 'status', sessionsmodel::SESSION_ACTIVE);
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
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        // Session name must be unique.
        $haserrorname = false;
        $sessions = jqshow_sessions::get_sessions_by_name($data['name'], $data['jqshowid']);
        if (count($sessions) === 1) {
            $sesion = reset($sessions);
            $haserrorname = (int)$sesion->id !== (int)$data['sessionid'];
        } else if (count($sessions) > 1) {
            $haserrorname = true;
        }
        if ($haserrorname) {
            $errors['name'] = get_string('sessionalreadyexists', 'mod_jqshow');
        }
        // Automatic start.
        if (array_key_exists('automaticstart', $data) && (int)$data['automaticstart'] === 1) {
            if ((int)$data['startdate'] <= time()) {
                $errors['startdate'] = get_string('previousstarterror', 'mod_jqshow');
            }
            if ((int)$data['startdate'] >= (int)$data['enddate']) {
                $errors['enddate'] = get_string('startminorend', 'mod_jqshow');
            }
        }
        // Groups mode.
        if (array_key_exists('groupmode', $data) && (int)$data['groupmode'] != 0 && empty($data['groupings'])) {
            $errors['groupings'] = get_string('session_groupings_error', 'mod_jqshow');
        } else if (array_key_exists('groupmode', $data) && (int)$data['groupmode'] != 0 && !empty($data['groupings'])) {
            $groups = groups_get_grouping_members($data['groupings'], 'gg.groupid');
            if (empty($groups)) {
                $errors['groupings'] = get_string('session_groupings_no_members', 'mod_jqshow');
            } else {
                $allmembers = [];
                foreach ($groups as $group) {
                    $members = groups_get_members($group->groupid, 'u.id, u.username');
                    if (empty($members)) {
                        continue;
                    }
                    $errorusers = [];
                    foreach ($members as $member) {
                        if (in_array($member->id, $allmembers)) {
                            $errorusers[] = $member->username;
                        } else {
                            $allmembers[] = $member->id;
                        }
                    }
                }
                if (!empty($errorusers)) {
                    $users = implode(',', $errorusers);
                    $errors['groupings'] = get_string('session_groupings_same_user_in_groups', 'mod_jqshow', $users);
                }
            }
        }

        // Timemode.
//        $programmedmodes = [sessions::PODIUM_PROGRAMMED, sessions::RACE_PROGRAMMED];
//        if ($data['sessionmode'] != sessions::INACTIVE_MANUAL) {
//            if (!array_key_exists('timemode', $data)) {
//                $errors['timemode'] = get_string('timemodemustbeset', 'mod_jqshow');
//            } else if (array_key_exists('timemode', $data) && (int)$data['timemode'] == sessions::NO_TIME) {
//                $errors['timemode'] = get_string('timemodemustbeset', 'mod_jqshow');
//            }
//        }
//
        if ((int)$data['questiontime'] === 0 && (int)$data['sessiontime'] === 0) {
            $errors['questiontime'] = get_string('timecannotbezero', 'mod_jqshow');
            $errors['sessiontime'] = get_string('timecannotbezero', 'mod_jqshow');
        }

        return $errors;
    }
}
