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
            'qorder' => array(
                'type' => PARAM_INT,
            ),
            'qtype' => array(
                'type' => PARAM_RAW,
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
     * @param int $questionid
     * @param int $sessionid
     * @param int $jqshowid
     * @param string $qtype
     * @return bool
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function add_not_valid_question(int $questionid, int $sessionid, int $jqshowid, string $qtype) : bool {
        // TODO apply default values to make the question valid without the need for the teacher to edit it.
        global $USER;
        $order = parent::count_records(['sessionid' => $sessionid]) + 1;
        $session = jqshow_sessions::get_record(['id' => $sessionid]);
        $isvalid = 0; // Teacher must configured the question for this session.
        $data = new stdClass();
        $data->questionid = $questionid;
        $data->sessionid = $sessionid;
        $data->jqshowid = $jqshowid;
        $data->qorder = $order;
        $data->qtype = $qtype;
        $data->timelimit = $session->get('addtimequestion') > 0 ? get_config('mod_jqshow', 'questiontime') : 0;
        $data->ignorecorrectanswer = 0;
        $data->isvalid = $isvalid;
        $data->config = '';
        $data->usermodified = (int)$USER->id;
        try {
            $a = new self(0, $data);
            $a->create();
        } catch (moodle_exception $e) {
            throw $e;
        }
        return true;
    }

    /**
     * @param int $sessionid
     * @return jqshow_questions
     */
    public static function get_first_question_of_session(int $sessionid): jqshow_questions {
        return self::get_record(['sessionid' => $sessionid, 'qorder' => 1], MUST_EXIST);
    }

    /**
     * @param int $sessionid
     * @param int $questionid
     * @return false|jqshow_questions
     * @throws coding_exception
     */
    public static function get_next_question_of_session(int $sessionid, int $questionid): ?jqshow_questions {
        $current = self::get_record(['id' => $questionid, 'sessionid' => $sessionid], MUST_EXIST);
        $nextquestion = self::get_record(['sessionid' => $sessionid, 'qorder' => $current->get('qorder') + 1]);
        if ($nextquestion === false) {
            return false;
        }
        return $nextquestion;
    }

    /**
     * @param int $questionid
     * @param int $qorder
     * @return bool
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function reorder_question(int $questionid, int $qorder) : bool {
        try {
            $a = new self($questionid);
            $a->set('qorder', $qorder);
            $a->update();
        } catch (moodle_exception $e) {
            throw $e;
        }
        return true;
    }

    /**
     * @param int $sid
     * @param int $qorder
     * @return array array
     * @throws dml_exception
     */
    public static function get_session_questions_to_reorder(int $sid, int $qorder) : array {
        global $DB;
        $sql = 'SELECT sq.*
              FROM {' . static::TABLE . '} sq
             WHERE sq.qorder > :qorder AND sq.sessionid = :sid ORDER BY sq.qorder ASC';
        $persistents = [];
        $recordset = $DB->get_recordset_sql($sql, ['qorder' => $qorder, 'sid' => $sid]);
        foreach ($recordset as $record) {
            $persistents[] = new static(0, $record);
        }
        $recordset->close();

        return $persistents;
    }

    /**
     * @param int $sid
     * @return bool
     * @throws dml_exception
     */
    public static function delete_session_questions(int $sid) : bool {
        global $DB;
        return  $DB->delete_records(self::TABLE, ['sessionid' => $sid]);
    }

    /**
     * @param int $oldsid
     * @param int $newsid
     * @return bool
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public static function copy_session_questions(int $oldsid, int $newsid) : bool {
        $oldquestions = self::get_records(['sessionid' => $oldsid]);
        foreach ($oldquestions as $oldquestion) {
            $data = $oldquestion->to_record();
            unset($data->id, $data->sessionid, $data->usermodified, $data->timecreated, $data->timemodified);
            $data->sessionid = $newsid;
            $newquestion = new self(0, $data);
            $newquestion->create();
        }
        return true;
    }
}
