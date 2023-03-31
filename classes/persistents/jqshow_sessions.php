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
use core\persistent;
use dml_exception;

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jqshow_sessions extends persistent {
    const TABLE = 'jqshow_sessions';
    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'name' => array(
                'type' => PARAM_RAW,
            ),
            'jqshowid' => array(
                'type' => PARAM_INT,
            ),
            'anonymousanswer' => array(
                'type' => PARAM_INT,
            ),
            'allowguests' => array(
                'type' => PARAM_INT,
            ),
            'advancemode' => array(
                'type' => PARAM_RAW,
            ),
            'gamemode' => array(
                'type' => PARAM_RAW,
            ),
            'countdown' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ),
            'randomquestions' => array(
                'type' => PARAM_INT,
            ),
            'randomanswers' => array(
                'type' => PARAM_INT,
            ),
            'showfeedback' => array(
                'type' => PARAM_INT,
            ),
            'showfinalgrade' => array(
                'type' => PARAM_INT,
            ),
            'startdate' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ),
            'enddate' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ),
            'automaticstart' => array(
                'type' => PARAM_INT,
            ),
            'timelimit' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ),
            'activetimelimit' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ),
            'groupmode' => array(
                'type' => PARAM_INT,
            ),
            'status' => array(
                'type' => PARAM_INT,
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
     * @param int $sessionid
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function duplicate_session(int $sessionid): bool {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $sessionid]);
        unset($record->id);
        $record->name .= ' - ' . get_string('copy', 'mod_jqshow');
        return $DB->insert_record(self::TABLE, $record, false);
    }

    /**
     * @param int $sessionid
     * @return bool
     * @throws dml_exception
     */
    public static function delete_session(int $sessionid): bool {
        global $DB;
        return  $DB->delete_records(self::TABLE, ['id' => $sessionid]);
    }
}
