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
            'groupings' => array(
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ),
            'status' => array(
                'type' => PARAM_INT,
            ),
            'hidegraderanking' => array(
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
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function duplicate_session(int $sessionid): int {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $sessionid]);
        unset($record->id);
        $record->name .= ' - ' . get_string('copy', 'mod_jqshow');
        return $DB->insert_record(self::TABLE, $record, true);
    }

    /**
     * @param int $sessionid
     * @param string $name
     * @param string $value
     * @return bool
     * @throws invalid_persistent_exception
     * @throws coding_exception
     */
    public static function edit_session_setting(int $sessionid, string $name, string $value): bool {
        $persistent = new self($sessionid);
        $persistent->set($name, $value);
        return $persistent->update();
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

    /**
     * @param int $jqshowid
     * @return int
     * @throws dml_exception
     */
    public static function get_active_session_id(int $jqshowid): int {
        global $DB;
        $activesession = $DB->get_record(self::TABLE, ['jqshowid' => $jqshowid, 'status' => 2], 'id');
        return $activesession !== false ? $activesession->id : 0;
    }

    /**
     * @param int $jqshowid
     * @return int
     * @throws dml_exception
     */
    public static function get_next_session(int $jqshowid): int {
        global $DB;
        $allsessions = $DB->get_records(self::TABLE, ['jqshowid' => $jqshowid, 'status' => 1], 'startdate DESC', 'startdate');
        $dates = [];
        foreach ($allsessions as $key => $date) {
            if ($key !== 0) {
                $dates[] = $key;
            }
        }
        if (!empty($dates)) {
            return self::find_closest($dates, time());
        }
        return 0;
    }

    /**
     * @param $array
     * @param $date
     * @return mixed
     */
    private static function find_closest($array, $date): int {
        foreach ($array as $key => $day) {
            $interval[] = abs($date - $key);
        }
        asort($interval);
        $closest = key($interval);
        return $array[$closest];
    }

    /**
     * @param int $sid
     * @return bool
     * @throws dml_exception
     */
    public static function mark_session_started(int $sid): bool {
        global $DB;
        $session = $DB->get_record(self::TABLE, ['id' => $sid], 'id, status');
        $session->status = 2;
        return $DB->update_record(self::TABLE, $session);
    }

    /**
     * @param int $sid
     * @return bool
     * @throws dml_exception
     */
    public static function mark_session_active(int $sid): bool {
        global $DB;
        $session = $DB->get_record(self::TABLE, ['id' => $sid], 'id, status');
        $session->status = 1;
        return $DB->update_record(self::TABLE, $session);
    }

    /**
     * @param int $sid
     * @return bool
     * @throws dml_exception
     */
    public static function mark_session_finished(int $sid): bool {
        global $DB;
        $session = $DB->get_record(self::TABLE, ['id' => $sid], 'id, status');
        $session->status = 0;
        return $DB->update_record(self::TABLE, $session);
    }

    /**
     * For PHPUnit
     * @param jqshow_sessions $other
     * @return bool
     * @throws coding_exception
     */
    public function equals(self $other): bool {
        return $this->get('id') === $other->get('id');
    }

    /**
     * Error code: textconditionsnotallowed: https://tracker.moodle.org/browse/MDL-27629
     * @param string $name
     * @return array
     * @throws dml_exception
     */
    public static function get_sessions_by_name(string $name) : array {
        global $DB;
        $comparescaleclause = $DB->sql_compare_text('name')  . ' =  ' . $DB->sql_compare_text(':name');
        return $DB->get_records_sql("SELECT * FROM {jqshow_sessions} WHERE $comparescaleclause", ['name' => $name]);
    }
}
