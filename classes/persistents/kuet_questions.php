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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos..

/**
 * Kuet questions persistent
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
use JsonException;
use mod_kuet\models\sessions;
use moodle_exception;
use stdClass;

/**
 * uet questions persistent class
 */
class kuet_questions extends persistent {
    /**
     * @var string questions table
     */
    public const TABLE = 'kuet_questions';
    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'questionid' => [
                'type' => PARAM_INT,
            ],
            'sessionid' => [
                'type' => PARAM_INT,
            ],
            'kuetid' => [
                'type' => PARAM_INT,
            ],
            'qorder' => [
                'type' => PARAM_INT,
            ],
            'qtype' => [
                'type' => PARAM_RAW,
            ],
            'timelimit' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ],
            'ignorecorrectanswer' => [
                'type' => PARAM_INT,
            ],
            'isvalid' => [
                'type' => PARAM_INT,
            ],
            'config' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
            ],
            'usermodified' => [
                'type' => PARAM_INT,
            ],
            'timecreated' => [
                'type' => PARAM_INT,
            ],
            'timemodified' => [
                'type' => PARAM_INT,
            ],
        ];
    }

    /**
     * Add question
     *
     * @param int $questionid
     * @param int $sessionid
     * @param int $kuetid
     * @param string $qtype
     * @return bool
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function add_question(int $questionid, int $sessionid, int $kuetid, string $qtype): bool {
        global $USER;
        $order = parent::count_records(['sessionid' => $sessionid]) + 1;
        $isvalid = 0; // Teacher must configure the question for this session.
        $data = new stdClass();
        $data->questionid = $questionid;
        $data->sessionid = $sessionid;
        $data->kuetid = $kuetid;
        $data->qorder = $order;
        $data->qtype = $qtype;
        $data->timelimit = 0;
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
     * Get the first question of a session
     *
     * @param int $sessionid
     * @return kuet_questions
     */
    public static function get_first_question_of_session(int $sessionid): kuet_questions {
        return self::get_record(['sessionid' => $sessionid, 'qorder' => 1], MUST_EXIST);
    }

    /**
     * Get the next question of a session
     *
     * @param int $sessionid
     * @param int $questionid
     * @return false|kuet_questions
     * @throws JsonException
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_next_question_of_session(int $sessionid, int $questionid) {
        global $USER;
        $session = kuet_sessions::get_record(['id' => $sessionid], MUST_EXIST);
        $nextquestion = false;
        switch ($session->get('sessionmode')) {
            case sessions::INACTIVE_PROGRAMMED:
            case sessions::PODIUM_PROGRAMMED:
            case sessions::RACE_PROGRAMMED:
                $progress = kuet_user_progress::get_session_progress_for_user(
                    $USER->id, $session->get('id'), $session->get('kuetid')
                );
                if ($progress !== false) {
                    $data = json_decode($progress->get('other'), false);
                    $order = explode(',', $data->questionsorder);
                    $current = array_search($data->currentquestion, $order, false);
                    if ($current !== false && isset($order[$current + 1])) {
                        $nextquestion = self::get_record(['sessionid' => $sessionid, 'id' => $order[$current + 1]]);
                    } else {
                        return false;
                    }
                }
                break;
            case sessions::INACTIVE_MANUAL:
            case sessions::PODIUM_MANUAL:
            case sessions::RACE_MANUAL:
                $current = self::get_record(['id' => $questionid, 'sessionid' => $sessionid], MUST_EXIST);
                $nextquestion = self::get_record(['sessionid' => $sessionid, 'qorder' => $current->get('qorder') + 1]);
                if ($nextquestion === false) {
                    return false;
                }
                break;
            default:
                throw new moodle_exception('incorrect_sessionmode', 'mod_kuet', '',
                    [], get_string('incorrect_sessionmode', 'mod_kuet'));
        }
        return $nextquestion;
    }

    /**
     * Get a question based on the position provided
     *
     * @param int $sid
     * @param int $order
     * @return false|kuet_questions
     */
    public static function get_question_by_position(int $sid, int $order) {
        return self::get_record(['sessionid' => $sid, 'qorder' => $order]);
    }

    /**
     *
     * Get question by kuet question id
     *
     * @param int $kid
     * @return false|kuet_questions
     */
    public static function get_question_by_kid(int $kid): ?kuet_questions {
        return self::get_record(['id' => $kid], MUST_EXIST);
    }

    /**
     * Reorder the questions
     *
     * @param int $questionid
     * @param int $qorder
     * @return bool
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function reorder_question(int $questionid, int $qorder): bool {
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
     * Get session questions to be reordered
     *
     * @param int $sid
     * @param int $qorder
     * @return array array
     * @throws dml_exception
     */
    public static function get_session_questions_to_reorder(int $sid, int $qorder): array {
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
     * Delete session questions
     *
     * @param int $sid
     * @return bool
     * @throws dml_exception
     */
    public static function delete_session_questions(int $sid): bool {
        global $DB;
        return  $DB->delete_records(self::TABLE, ['sessionid' => $sid]);
    }

    /**
     * Copy session questions
     *
     * @param int $oldsid
     * @param int $newsid
     * @return bool
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public static function copy_session_questions(int $oldsid, int $newsid): bool {
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

    /**
     * Get the question time setting
     *
     * @param int $kid
     * @param int $sid
     * @return float
     * @throws coding_exception
     */
    public static function get_question_time(int $kid, int $sid): float {
        $kquestion = new kuet_questions($kid);
        $qtime = $kquestion->get('timelimit');
        if ($qtime === 0) {
            $session = new kuet_sessions($sid);
            if ((int)$session->get('timemode') === sessions::QUESTION_TIME) {
                $qtime = $session->get('questiontime');
            } else if ((int)$session->get('timemode') === sessions::SESSION_TIME) {
                $numquestions = self::count_records(['sessionid' => $sid]);
                if ($numquestions > 0) {
                    $qtime = $session->get('sessiontime') / $numquestions;
                }
            }
        }

        return $qtime;
    }
}
