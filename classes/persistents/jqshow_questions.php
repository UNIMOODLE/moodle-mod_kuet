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
namespace mod_jqshow\persistents;
use coding_exception;
use core\invalid_persistent_exception;
use core\persistent;
use dml_exception;
use stdClass;

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jqshow_questions extends persistent {
    const TABLE = 'jqshow_questions';
    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'questionid' => array(
                'type' => PARAM_INT,
            ),
            'sessionid' => array(
                'type' => PARAM_INT,
            ),
            'jqshowid' => array(
                'type' => PARAM_INT,
            ),
            'order' => array(
                'type' => PARAM_INT,
            ),
            'qtype' => array(
                'type' => PARAM_RAW,
            ),
            'hastimelimit' => array(
                'type' => PARAM_INT,
            ),
            'timelimit' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ),
            'ignorecorrectanswer' => array(
                'type' => PARAM_INT,
            ),
            'isvalid' => array(
                'type' => PARAM_INT,
            ),
            'config' => array(
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
            ),
            'usermodified' => array(
                'type' => PARAM_INT,
            ),
            'timecreated' => array(
                'type' => PARAM_INT,
            ),
            'timemodified' => array(
                'type' => PARAM_INT,
            ),
        );
    }

    /**
     * @param $questionid
     * @param $sessionid
     * @param $jqshowid
     * @return bool
     */
    public static function add_not_valid_question($questionid, $sessionid, $jqshowid) : bool {
        $order = parent::count_records(['sessionid' => $sessionid]);
        $isvalid = 0; // Teacher must configured the question for this session.
        $data = new stdClass();
        $data->questionid = $questionid;
        $data->sessionid = $sessionid;
        $data->jqshowid = $jqshowid;
        $data->isvalid = $isvalid;
        $data->order = $order;

        try {
//        $jq = new jqshow_questions(0, $data);
//        $jq->create();
            $a = new self(0, $data);
            $a->create();
        } catch (\moodle_exception $e) {
            return false;
        }

        return true;
    }
}
