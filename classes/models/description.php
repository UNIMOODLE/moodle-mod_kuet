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
 * Description question model
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\models;

use coding_exception;
use context_module;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use invalid_parameter_exception;
use JsonException;
use mod_kuet\external\description_external;
use mod_kuet\helpers\reports;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use qtype_description_question;
use question_bank;
use question_definition;
use stdClass;
use mod_kuet\interfaces\questionType;



/**
 * Description question model class
 */
class description extends questions implements questionType {

    /**
     * Constructor
     *
     * @param int $kuetid
     * @param int $cmid
     * @param int $sid
     * @return void
     */
    public function construct(int $kuetid, int $cmid, int $sid): void {
        parent::__construct($kuetid, $cmid, $sid);
    }

    /**
     * Export question
     *
     * @param int $kid
     * @param int $cmid
     * @param int $sessionid
     * @param int $kuetid
     * @param bool $preview
     * @return object
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function export_question(int $kid, int $cmid, int $sessionid, int $kuetid, bool $preview = false): object {
        $session = kuet_sessions::get_record(['id' => $sessionid]);
        $kuetquestion = kuet_questions::get_record(['id' => $kid]);
        $question = question_bank::load_question($kuetquestion->get('questionid'));
        if (!assert($question instanceof qtype_description_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_kuet', '',
                [], get_string('question_nosuitable', 'mod_kuet'));
        }
        $type = $question->get_type_name();
        $data = self::get_question_common_data($session, $cmid, $sessionid, $kuetid, $preview, $kuetquestion, $type);
        $data->$type = true;
        $data->qtype = $type;
        $data->questiontext =
            self::get_text($cmid, $question->questiontext, $question->questiontextformat, $question->id, $question, 'questiontext');
        $data->questiontextformat = $question->questiontextformat;
        $data->name = $question->name;
        return $data;
    }


    /**
     * Export question response
     *
     * @param stdClass $data
     * @param string $response
     * @param int $result
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function export_question_response(stdClass $data, string $response, int $result = 0): stdClass {
        $responsedata = json_decode($response, false);
        if (!isset($responsedata->response) || (is_array($responsedata->response) && count($responsedata->response) === 0)) {
            $responsedata->response = '';
        }
        $data->answered = true;
        $dataanswer = description_external::description(
            $data->sessionid,
            $data->kuetid,
            $data->cmid,
            $data->questionid,
            $data->kid,
            $responsedata->timeleft,
            true
        );
        $data->hasfeedbacks = $dataanswer['hasfeedbacks'];
        $data->seconds = $responsedata->timeleft;
        $data->programmedmode = $dataanswer['programmedmode'];
        $data->jsonresponse = base64_encode(json_encode($dataanswer, JSON_THROW_ON_ERROR));
        $data->statistics = $dataanswer['statistics'] ?? '0';
        return $data;
    }

    /**
     * Get question report
     *
     * @param kuet_sessions $session
     * @param question_definition $questiondata
     * @param stdClass $data
     * @param int $kid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_question_report(kuet_sessions     $session,
                                               question_definition $questiondata,
                                               stdClass            $data,
                                               int                 $kid): stdClass {
        if (!assert($questiondata instanceof qtype_description_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_kuet', '',
                [], get_string('question_nosuitable', 'mod_kuet'));
        }
        return $data;
    }

    /**
     * Get ranking for question
     *
     * @param stdClass $participant
     * @param kuet_questions_responses $response
     * @param array $answers
     * @param kuet_sessions $session
     * @param kuet_questions $question
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     */
    public static function get_ranking_for_question(
        stdClass $participant,
        kuet_questions_responses $response,
        array $answers,
        kuet_sessions $session,
        kuet_questions $question): stdClass {
        $participant->response = 'noevaluable';
        $participant->responsestr = get_string('noevaluable', 'mod_kuet');
        if ($session->is_group_mode()) {
            $participant->grouppoints = 0;
        } else {
            $participant->userpoints = 0;
        }
        $participant->score_moment = 0;
        $participant->time = reports::get_user_time_in_question($session, $question, $response);
        return $participant;
    }

    /**
     * Get question response
     *
     * @param int $cmid
     * @param int $kid
     * @param int $questionid
     * @param int $sessionid
     * @param int $kuetid
     * @param string $statmentfeedback
     * @param int $userid
     * @param int $timeleft
     * @param array $custom
     * @return void
     * @throws JsonException
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function question_response(
        int $cmid,
        int $kid,
        int $questionid,
        int $sessionid,
        int $kuetid,
        string $statmentfeedback,
        int $userid,
        int $timeleft,
        array $custom = []
    ): void {
        $cmcontext = context_module::instance($cmid);
        $isteacher = has_capability('mod/kuet:managesessions', $cmcontext);
        $result = questions::NOTEVALUABLE;
        if ($isteacher !== true) {
            $session = new kuet_sessions($sessionid);
            $response = new stdClass();
            $response->hasfeedbacks = $statmentfeedback !== '';
            $response->timeleft = $timeleft;
            $response->type = questions::DESCRIPTION;
            $response->response = '';
            if ($session->is_group_mode()) {
                parent::add_group_response($kuetid, $session, $kid, $questionid, $userid, $result, $response);
            } else {
                // Individual.
                kuet_questions_responses::add_response(
                    $kuetid, $sessionid, $kid, $questionid, $userid, $result, json_encode($response, JSON_THROW_ON_ERROR)
                );
            }
        }
    }

    /**
     * Get simple mark
     *
     * @param stdClass $useranswer
     * @param kuet_questions_responses $response
     * @return float
     */
    public static function get_simple_mark(stdClass $useranswer,  kuet_questions_responses $response): float {
        return 0;
    }

    /**
     * Description is not an evaluable question type for kuet
     *
     * @return bool
     */
    public static function is_evaluable(): bool {
        return false;
    }
    /**
     * Get question statistics
     *
     * @param question_definition $question
     * @param kuet_questions_responses[] $responses
     * @return array
     */
    public static function get_question_statistics(question_definition $question, array $responses): array {
        return [];
    }
}
