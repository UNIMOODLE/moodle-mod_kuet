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
 * Kuet question responses persistent
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\persistents;

use coding_exception;
use core\invalid_persistent_exception;
use core\persistent;
use dml_exception;
use mod_kuet\models\sessions;
use moodle_exception;
use stdClass;

/**
 * Kuet question responses persistent class
 */
class kuet_questions_responses extends persistent {
    /**
     * @var string kuet question responses table
     */
    public const TABLE = 'kuet_questions_responses';
    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'kuet' => [
                'type' => PARAM_INT,
            ],
            'session' => [
                'type' => PARAM_INT,
            ],
            'kid' => [
                'type' => PARAM_INT,
            ],
            'questionid' => [
                'type' => PARAM_INT,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'anonymise' => [
                'type' => PARAM_INT,
            ],
            'result' => [
                'type' => PARAM_INT,
            ],
            'response' => [
                'type' => PARAM_RAW,
            ],
        ];
    }

    /**
     * Get sessions responses from a user
     *
     * @param int $userid
     * @param int $sessionid
     * @param int $kuetid
     * @return array
     */
    public static function get_session_responses_for_user(int $userid, int $sessionid, int $kuetid): array {
        return self::get_records(['userid' => $userid, 'session' => $sessionid, 'kuet' => $kuetid]);
    }

    /**
     * Get question responses
     *
     * @param int $sessionid
     * @param int $kuetid
     * @param int $kid
     * @return kuet_questions_responses[]
     */
    public static function get_question_responses(int $sessionid, int $kuetid, int $kid): array {
        return self::get_records(['kid' => $kid, 'session' => $sessionid, 'kuet' => $kuetid]);
    }

    /**
     * Get user grade from a session
     *
     * @param int $session
     * @param int $userid
     * @return false|static
     */
    public static function get_grade_for_session_user(int $session, int $userid) {
        return self::get_record(['session' => $session, 'userid' => $userid]);
    }

    /**
     * Get user question responses
     *
     * @param int $userid
     * @param int $session
     * @param int $kid
     * @return false|kuet_questions_responses
     */
    public static function get_question_response_for_user(int $userid, int $session, int $kid) {
        return self::get_record(['session' => $session, 'userid' => $userid, 'kid' => $kid]);
    }

    /**
     * Add response
     *
     * @param int $kuet
     * @param int $session
     * @param int $kid
     * @param int $questionid
     * @param int $userid
     * @param int $result
     * @param string $response
     * @return bool
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function add_response(
        int $kuet,
        int $session,
        int $kid,
        int $questionid,
        int $userid,
        int $result,
        string $response
    ): bool {
        $sessiondata = kuet_sessions::get_record(['id' => $session], MUST_EXIST);
        $record = self::get_record(['kuet' => $kuet, 'session' => $session, 'kid' => $kid, 'userid' => $userid]);
        // Only the first response for user is saved to prevent further responses by relaunching the services.
        if ($record === false && $sessiondata->get('status') === sessions::SESSION_STARTED) {
            try {
                $data = new stdClass();
                $data->kuet = $kuet;
                $data->session = $session;
                $data->kid = $kid;
                $data->questionid = $questionid;
                $data->userid = $userid;
                $data->anonymise = $sessiondata->get('anonymousanswer');
                $data->result = $result;
                $data->response = base64_encode($response);
                $a = new self(0, $data);
                $a->create();
            } catch (moodle_exception $e) {
                throw $e;
            }
        }
        return true;
    }

    /**
     * Delete question responses
     *
     * @param int $kuet
     * @param int $sid
     * @param int $kid
     * @return bool
     * @throws dml_exception
     */
    public static function delete_question_responses(int $kuet, int $sid, int $kid): bool {
        global $DB;
        return  $DB->delete_records(self::TABLE, ['kuet' => $kuet, 'session' => $sid, 'kid' => $kid]);
    }

    /**
     * Delete questions responses
     *
     * @param int $sid
     * @return bool
     * @throws dml_exception
     */
    public static function delete_questions_responses(int $sid): bool {
        global $DB;
        return  $DB->delete_records(self::TABLE, ['session' => $sid]);
    }
}
