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
    public const TABLE = 'jqshow_sessions';
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
            'enablestartdate' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => '0'
            ),
            'startdate' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ),
            'enableenddate' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => '0'
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
                'default' => 0,
            ),
            'usermodified' => array(
                'type' => PARAM_INT,
            ),
            'timecreated' => array(
                'type' => PARAM_INT,
            ),
            'timemodified' => array(
                'type' => PARAM_INT,
            )
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
        $record->status = 1;
        $record->automaticstart = 0;
        $record->enablestartdate = 0;
        $record->startdate = 0;
        $record->enableenddate = 0;
        $record->enddate = 0;
        return $DB->insert_record(self::TABLE, $record);
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
     * @throws coding_exception
     */
    public static function get_active_session_id(int $jqshowid): int {
        $activesession = self::get_record(['jqshowid' => $jqshowid, 'status' => 2]);
        return $activesession !== false ? $activesession->get('id') : 0;
    }

    /**
     * @param int $jqshowid
     * @return int
     * @throws dml_exception
     */
    public static function get_next_session(int $jqshowid): int {
        // TODO review.
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
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     */
    public static function mark_session_started(int $sid): void {
        global $DB;
        // All open sessions end, ensuring that no more than one session is logged on.
        $activesession = $DB->get_records(self::TABLE, ['status' => 2]);
        foreach ($activesession as $active) {
            if ($sid !== $active->id) {
                (new jqshow_sessions($active->id))->set('status', 0)->update();
            }
        }
        (new jqshow_sessions($sid))->set('status', 2)->update();
    }

    /**
     * @param int $sid
     * @return void
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public static function mark_session_active(int $sid): void {
        (new jqshow_sessions($sid))->set('status', 1)->update();
    }

    /**
     * @param int $sid
     * @return void
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public static function mark_session_finished(int $sid): void {
        (new jqshow_sessions($sid))->set('status', 0)->update();
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

    /**
     * @param int $jqshowid
     * @return array
     * @throws dml_exception
     */
    private function get_active_sessions(int $jqshowid): array {
        global $DB;
        $select = "jqshowid = :jqshowid AND advancemode = :advancemode AND automaticstart = :automaticstart AND status != 0";
        $params = [
            'jqshowid' => $jqshowid
        ];
        return $DB->get_records_select('jqshow_sessions', $select, $params);
    }

    /**
     * @param int $jqshowid
     * @return array
     * @throws dml_exception
     */
    private static function get_automaticstart_sessions(int $jqshowid): array {
        global $DB;
        $select = "jqshowid = :jqshowid AND advancemode = :advancemode AND automaticstart = :automaticstart AND status != 0";
        $params = [
            'jqshowid' => $jqshowid,
            'advancemode' => 'programmed',
            'automaticstart' => 1
        ];
        return $DB->get_records_select('jqshow_sessions', $select, $params, 'timecreated ASC');
    }

    /**
     * @param int $jqshowid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     */
    public static function check_automatic_sessions(int $jqshowid): void {
        $sessions = self::get_automaticstart_sessions($jqshowid);
        $activesession = null;
        $now = time();
        foreach ($sessions as $session) {
            if ($session->startdate !== 0 && $session->startdate < $now) { // If there is a start date and it has been met.
                if ($session->enddate !== 0 && $session->enddate > $now) { // If there is an end date and it has not been met.
                    if ($session->status !== 2) { // If not marked as started.
                        (new jqshow_sessions($session->id))->set('status', 2)->update(); // We mark session as logged in.
                        $session->status = 2;
                    }
                    $activesession = $session;
                }
                if ($session->enddate !== 0 && $session->enddate < $now) { // If there is an end date and it has been met.
                    (new jqshow_sessions($session->id))->set('status', 0)->update(); // We mark the session as ended.
                }
            }
        }
        if ($activesession !== null) {
            // There can only be one started session, and it will be the one chosen in the previous loop.
            foreach ($sessions as $session) {
                if ($session->status === 2 && $session->id !== $activesession->id) {
                    // If the session has a current deadline we leave it as active.
                    if ($session->startdate < $now || $session->enddate > $now) {
                        (new jqshow_sessions($session->id))->set('status', 1)->update();
                    } else {
                        // In any other case, this session is closed.
                        (new jqshow_sessions($session->id))->set('status', 0)->update();
                    }
                }
            }
        }
    }
}
