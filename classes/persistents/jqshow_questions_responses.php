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

namespace mod_jqshow\persistents;

use coding_exception;
use core\invalid_persistent_exception;
use core\persistent;
use dml_exception;
use moodle_exception;
use stdClass;

class jqshow_questions_responses extends persistent {
    public const TABLE = 'jqshow_questions_responses';
    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() : array {
        return [
            'jqshow' => [
                'type' => PARAM_INT,
            ],
            'session' => [
                'type' => PARAM_INT,
            ],
            'jqid' => [
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
            ]
        ];
    }

    /**
     * @param int $userid
     * @param int $jqshowid
     * @param int $sessionid
     * @return jqshow_questions_responses[]
     */
    public static function get_session_responses_for_user(int $userid, int $sessionid, int $jqshowid): array {
        return self::get_records(['userid' => $userid, 'session' => $sessionid, 'jqshow' => $jqshowid]);
    }

    /**
     * @param int $sessionid
     * @param int $jqshowid
     * @param int $jqid
     * @return jqshow_questions_responses[]
     */
    public static function get_question_responses(int $sessionid, int $jqshowid, int $jqid): array {
        return self::get_records(['jqid' => $jqid, 'session' => $sessionid, 'jqshow' => $jqshowid]);
    }

    /**
     * @param int $session
     * @param int $userid
     * @return false|static
     */
    public static function get_grade_for_session_user(int $session, int $userid) {
        return self::get_record(['session' => $session, 'userid' => $userid]);
    }

    /**
     * @param int $userid
     * @param int $session
     * @param int $jqid
     * @return false|jqshow_questions_responses
     */
    public static function get_question_response_for_user(int $userid, int $session, int $jqid) {
        return self::get_record(['session' => $session, 'userid' => $userid, 'jqid' => $jqid]);
    }

    /**
     * @param int $jqshow
     * @param int $session
     * @param int $jqid
     * @param int $questionid
     * @param int $userid
     * @param int $result
     * @param string $response
     * @return bool
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function add_response(int $jqshow, int $session, int $jqid, int $questionid, int $userid, int $result, string $response): bool {
        $sessiondata = jqshow_sessions::get_record(['id' => $session], MUST_EXIST);
        $record = self::get_record(['jqshow' => $jqshow, 'session' => $session, 'jqid' => $jqid, 'userid' => $userid]);
        try {
            if ($record === false) {
                $data = new stdClass();
                $data->jqshow = $jqshow;
                $data->session = $session;
                $data->jqid = $jqid;
                $data->questionid = $questionid;
                $data->userid = $userid;
                $data->anonymise = $sessiondata->get('anonymousanswer');
                $data->result = $result;
                $data->response = base64_encode($response);
                $a = new self(0, $data);
                $a->create();
            } else {
                $record->set('result', $result);
                $record->set('response', base64_encode($response));
                $record->update();
            }
        } catch (moodle_exception $e) {
            throw $e;
        }
        return true;
    }

    /**
     * @param int $jqshow
     * @param int $sid
     * @param int $jqid
     * @return bool
     * @throws dml_exception
     */
    public static function delete_question_responses(int $jqshow, int $sid, int $jqid): bool {
        global $DB;
        return  $DB->delete_records(self::TABLE, ['jqshow' => $jqshow, 'session' => $sid, 'jqid' => $jqid]);
    }

    /**
     * @param int $sid
     * @return bool
     * @throws dml_exception
     */
    public static function delete_questions_responses(int $sid): bool {
        global $DB;
        return  $DB->delete_records(self::TABLE, ['session' => $sid]);
    }
}
