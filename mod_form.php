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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    //  It must be included from a Moodle page.
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');
/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_jqshow_mod_form extends moodleform_mod {

    public function definition()
    {
        global $CFG;

        $mform =& $this->_form;

        $mform->addElement('text', 'name', get_string('name', 'jqshow'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
//        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('introduction', 'jqshow'));

        // Grade settings.
        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

        // Teams grade.
        $mform->addElement('header', 'teamsgrade', get_string('teamsgradeheader', 'jqshow'));
        $options = ['' => get_string('chooseoption', 'jqshow'), 'first' => 'first', 'last' => 'last', 'average' => 'average'];
        $mform->addElement('select', 'teamgrade', get_string('teamgrade', 'jqshow'), $options);
        $mform->disabledIf('teamgrade', 'groupmode', 'eq', 0);
        $mform->addHelpButton('teamgrade', 'teamgrade', 'jqshow');
        $mform->setType('teamgrade', PARAM_RAW);

        // Badges.
        $mform->addElement('header', 'badges', get_string('badges', 'badges'));
        $mform->addElement('text', 'badgepositions', get_string('badgepositions', 'jqshow'), array('size'=>'5'));
        $mform->addHelpButton('badgepositions', 'badgepositions', 'jqshow');
        $mform->addRule('badgepositions', get_string('badgepositionsrule', 'jqshow'), 'numeric', null, 'server');
        $mform->setType('badgepositions', PARAM_INT);
        $this->add_action_buttons();
    }
    /**
     * Add custom completion rules.
     *
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('advcheckbox', 'completionanswerall', get_string('completionansweralllabel', 'jqshow'), get_string('completionansweralldesc', 'jqshow'));
        // Enable this completion rule by default.
        $mform->setDefault('completionanswerall', 0);
        $mform->setType('completionanswerall', PARAM_INT);
        return array('completionanswerall');
    }
    /**
     * Determines if completion is enabled for this module.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionanswerall']);
    }
}
