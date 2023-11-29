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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos

/**
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\persistents;

use coding_exception;
use core\invalid_persistent_exception;
use core\persistent;
use dml_exception;
use moodle_exception;
use stdClass;

class kuet_user_progress extends persistent {
    public const TABLE = 'kuet_user_progress';

    /**
     * @return array[]
     */
    protected static function define_properties(): array {
        return [
            'kuet' => [
                'type' => PARAM_INT,
            ],
            'session' => [
                'type' => PARAM_INT,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'randomquestion' => [
                'type' => PARAM_INT,
            ],
            'other' => [
                'type' => PARAM_RAW,
            ]
        ];
    }

    /**
     * @param int $userid
     * @param int $sessionid
     * @param int $jqshowid
     * @return false|static
     */
    public static function get_session_progress_for_user(int $userid, int $sessionid, int $jqshowid) {
        return self::get_record(['userid' => $userid, 'session' => $sessionid, 'kuet' => $jqshowid]);
    }

    /**
     * @param int $jqshowid
     * @param int $session
     * @param int $userid
     * @param string $other
     * @return bool
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function add_progress(int $jqshowid, int $session, int $userid, string $other): bool {
        $sessiondata = kuet_sessions::get_record(['id' => $session], MUST_EXIST);
        $record = self::get_record(['kuet' => $jqshowid, 'session' => $session, 'userid' => $userid]);
        try {
            if ($record === false) {
                $data = new stdClass();
                $data->kuet = $jqshowid;
                $data->session = $session;
                $data->userid = $userid;
                $data->randomquestion = $sessiondata->get('randomquestions');
                $data->other = $other;
                $a = new self(0, $data);
                $a->create();
            } else {
                $record->set('other', $other);
                $record->update();
            }
        } catch (moodle_exception $e) {
            throw $e;
        }
        return true;
    }

    /**
     * @param int $sid
     * @return bool
     * @throws dml_exception
     */
    public static function delete_session_user_progress(int $sid): bool {
        global $DB;
        return  $DB->delete_records(self::TABLE, ['session' => $sid]);
    }
}
