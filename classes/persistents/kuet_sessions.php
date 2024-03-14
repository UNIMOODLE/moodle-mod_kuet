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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * Kuet sessions persistent
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\persistents;
use coding_exception;
use context_module;
use core\invalid_persistent_exception;
use core\persistent;
use dml_exception;
use mod_kuet\event\session_ended;
use mod_kuet\models\sessions;
use mod_kuet\models\sessions as sessionsmodel;

/**
 * Kuet sessions persistent class
 */
class kuet_sessions extends persistent {
    /**
     * @var string kuet sessions table
     */
    public const TABLE = 'kuet_sessions';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() :array {
        return [
            'name' => [
                'type' => PARAM_RAW,
            ],
            'kuetid' => [
                'type' => PARAM_INT,
            ],
            'anonymousanswer' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'sessionmode' => [
                'type' => PARAM_RAW,
                'default' => sessions::INACTIVE_MANUAL,
                'choices' => [sessions::INACTIVE_MANUAL,
                    sessions::INACTIVE_PROGRAMMED,
                    sessions::PODIUM_MANUAL,
                    sessions::PODIUM_PROGRAMMED,
                    sessions::RACE_MANUAL,
                    sessions::RACE_PROGRAMMED]
            ],
            'sgrade' => [
                'type' => PARAM_INT,
                'default' => sessions::GM_DISABLED,
            ],
            'countdown' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => 0,
            ],
            'showgraderanking' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'randomquestions' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'randomanswers' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'showfeedback' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'showfinalgrade' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'startdate' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ],
            'enddate' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ],
            'automaticstart' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'timemode' => [
                'type' => PARAM_INT,
                'default' => sessions::NO_TIME,
                'choices' => [sessions::NO_TIME,
                    sessions::SESSION_TIME,
                    sessions::QUESTION_TIME]
            ],
            'sessiontime' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ],
            'questiontime' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ],
            'groupings' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'status' => [
                'type' => PARAM_INT,
            ],
            'usermodified' => [
                'type' => PARAM_INT,
            ],
            'timecreated' => [
                'type' => PARAM_INT,
            ],
            'timemodified' => [
                'type' => PARAM_INT,
            ]
        ];
    }

    /**
     * Check the group mode of a session
     *
     * @return bool
     * @throws coding_exception
     */
    public function is_group_mode(): bool {
        $group = $this->get('groupings');
        return $group !== null && $group !== '' && $group !== false && $group !== 0 && $group !== '0';
    }

    /**
     * Duplicate existing session
     *
     * @param int $sessionid
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function duplicate_session(int $sessionid): int {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $sessionid]);
        unset($record->id);
        $record->name .= ' - ' . get_string('copy', 'mod_kuet');
        $record->status = sessionsmodel::SESSION_ACTIVE;
        $record->automaticstart = 0;
        $record->startdate = 0;
        $record->enddate = 0;
        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Edit session setting
     *
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
     * Delete session
     *
     * @param int $sessionid
     * @return bool
     * @throws dml_exception
     */
    public static function delete_session(int $sessionid): bool {
        global $DB;
        return  $DB->delete_records(self::TABLE, ['id' => $sessionid]);
    }

    /**
     * Get the id of an active session
     *
     * @param int $kuetid
     * @return int
     * @throws coding_exception
     */
    public static function get_active_session_id(int $kuetid): int {
        $activesession = self::get_record(['kuetid' => $kuetid, 'status' => sessionsmodel::SESSION_STARTED]);
        return $activesession !== false ? $activesession->get('id') : 0;
    }

    /**
     * Get the name of a session
     *
     * @param int $kuetid
     * @param int $sessionid
     * @return string
     * @throws coding_exception
     */
    public static function get_sessionname(int $kuetid, int $sessionid): string {
        $sessionname = self::get_record(['id' => $sessionid, 'kuetid' => $kuetid]);
        return $sessionname !== false ? $sessionname->get('name') : '';
    }

    /**
     * Get the next session id
     *
     * @param int $kuetid
     * @return int
     * @throws dml_exception
     */
    public static function get_next_session(int $kuetid): int {
        global $DB;
        $allsessions = $DB->get_records(self::TABLE, ['kuetid' => $kuetid, 'status' => sessionsmodel::SESSION_ACTIVE],
            'startdate DESC', 'id, startdate');
        $dates = [];
        foreach ($allsessions as $date) {
            if ($date->startdate !== 0) {
                $dates[] = $date->startdate;
            }
        }
        if (!empty($dates)) {
            return self::find_closest($dates, time());
        }
        return 0;
    }

    /**
     * Find the closest session from a date
     *
     * @param array $array
     * @param int $date
     * @return mixed
     */
    private static function find_closest(array $array, int $date): int {
        foreach ($array as $key => $day) {
            $interval[] = abs($date - $key);
        }
        asort($interval);
        $closest = key($interval);
        return $array[$closest];
    }

    /**
     * Mark the session as started
     *
     * @param int $sid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     */
    public static function mark_session_started(int $sid): void {
        global $DB;
        // All open sessions end, ensuring that no more than one session is logged on.
        $activesession = $DB->get_records(self::TABLE, ['status' => sessionsmodel::SESSION_STARTED]);
        foreach ($activesession as $active) {
            if ($sid !== $active->id) {
                $session = new kuet_sessions($active->id);
                $session->set('status', sessionsmodel::SESSION_FINISHED);
                $session->set('enddate', time());
                $session->update();
            }
        }
        $session = new kuet_sessions($sid);
        $session->set('status', sessionsmodel::SESSION_STARTED);
        $session->set('startdate', time());
        $session->update();
    }

    /**
     * Mark the session as active
     *
     * @param int $sid
     * @return void
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public static function mark_session_active(int $sid): void {
        $session = new kuet_sessions($sid);
        $session->set('status', sessionsmodel::SESSION_ACTIVE);
        $session->update();
    }

    /**
     * Mark the session as finished
     *
     * @param int $sid
     * @return void
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public static function mark_session_finished(int $sid): void {
        $session = new kuet_sessions($sid);
        $session->set('status', sessionsmodel::SESSION_FINISHED);
        $session->set('enddate', time());
        $session->update();

        $cm = get_coursemodule_from_instance('kuet', $session->get('kuetid'));
        $kuet = new kuet($session->get('kuetid'));
        $params = array(
            'objectid' => $sid,
            'courseid' => $kuet->get('course'),
            'context' => context_module::instance($cm->id)
        );
        $event = session_ended::create($params);
        $event->add_record_snapshot('kuet_sessions', $session->to_record());
        $event->trigger();
    }

    /**
     * Used by PHP Unit
     *
     * @param kuet_sessions $other
     * @return bool
     * @throws coding_exception
     */
    public function equals(self $other): bool {
        return $this->get('id') === $other->get('id');
    }

    /**
     * Get sessions by name
     *
     * Error code: textconditionsnotallowed: https://tracker.moodle.org/browse/MDL-27629
     * @param string $name
     * @param int $jsqhowid
     * @return array
     * @throws dml_exception
     */
    public static function get_sessions_by_name(string $name, int $jsqhowid) : array {
        global $DB;
        $comparescaleclause = $DB->sql_compare_text('name')  . ' =  ' . $DB->sql_compare_text(':name');
        $comparescaleclause .= ' AND kuetid = :kuetid';

        return $DB->get_records_sql("SELECT * FROM {kuet_sessions} WHERE $comparescaleclause",
            ['name' => $name, 'kuetid' => $jsqhowid]);
    }

    /**
     * Get the active sessions
     *
     * @param int $kuetid
     * @return array
     * @throws dml_exception
     */
    private function get_active_sessions(int $kuetid): array {
        global $DB;
        $select = "kuetid = :kuetid AND sessionmode = :sessionmode AND automaticstart = :automaticstart AND status != 0";
        $params = [
            'kuetid' => $kuetid
        ];
        return $DB->get_records_select('kuet_sessions', $select, $params);
    }

    /**
     * Get sessions with automatic start feature
     *
     * @param int $kuetid
     * @return array
     * @throws dml_exception
     */
    private static function get_automaticstart_sessions(int $kuetid): array {
        global $DB;
        $select = "kuetid = :kuetid AND sessionmode = :sessionmode AND automaticstart = :automaticstart AND status != 0";
        $params = [
            'kuetid' => $kuetid,
            'sessionmode' => sessions::PODIUM_PROGRAMMED,
            'automaticstart' => 1
        ];
        return $DB->get_records_select('kuet_sessions', $select, $params, 'timecreated ASC');
    }

    /**
     * Check if the mode is programmed or not
     *
     * @return bool
     * @throws coding_exception
     */
    public function is_programmed_mode() : bool {
        return ($this->get('sessionmode') === sessions::PODIUM_PROGRAMMED ||
            $this->get('sessionmode') === sessions::INACTIVE_PROGRAMMED ||
            $this->get('sessionmode') === sessions::RACE_PROGRAMMED);
    }
}
