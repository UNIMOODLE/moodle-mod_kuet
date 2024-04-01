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
 * Get user response to question API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet\external;

use coding_exception;
use context_module;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use JsonException;
use mod_kuet\exporter\question_exporter;
use mod_kuet\models\calculated;
use mod_kuet\models\ddwtos;
use mod_kuet\models\description;
use mod_kuet\models\matchquestion;
use mod_kuet\models\multichoice;
use mod_kuet\models\numerical;
use mod_kuet\models\questions;
use mod_kuet\models\shortanswer;
use mod_kuet\models\truefalse;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use ReflectionException;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Get user response to question class
 */
class getuserquestionresponse_external extends external_api {

    /**
     * Get user response to question parameters validation
     *
     * @return external_function_parameters
     */
    public static function getuserquestionresponse_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'kid' => new external_value(PARAM_INT, 'id of kuet_questions'),
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'sid' => new external_value(PARAM_INT, 'session id'),
                'uid' => new external_value(PARAM_INT, 'user id'),
                'preview' => new external_value(PARAM_BOOL, 'preview'),
            ]
        );
    }

    /**
     * Get user response to question
     *
     * @param int $kid
     * @param int $cmid
     * @param int $sid
     * @param int $uid
     * @param bool $preview
     * @return array
     * @throws JsonException
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function getuserquestionresponse(int $kid, int $cmid, int $sid, int $uid = 0, bool $preview = false): array {
        self::validate_parameters(
            self::getuserquestionresponse_parameters(),
            ['kid' => $kid, 'cmid' => $cmid, 'sid' => $sid, 'uid' => $uid, 'preview' => $preview]
        );
        global $USER, $PAGE;
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);
        $userid = $uid === 0 ? $USER->id : $uid;
        $response = kuet_questions_responses::get_question_response_for_user($userid, $sid, $kid);
        $data = new stdClass();
        $data->sessionid = $sid;
        $data->cmid = $cmid;
        $data->kid = $kid;
        if ($response !== false) {
            $json = base64_decode($response->get('response'));
            $other = json_decode($json, false);
            $data->kuetid = $response->get('kuet');
            $data->questionid = $response->get('questionid');
            $result = $response->get('result');
        } else if ($uid !== 0) { // It is a response review, where there is no response for the user. Mock required.
            $question = new kuet_questions($kid);
            $other = new stdClass();
            $other->questionid = $question->get('questionid');
            $other->hasfeedbacks = false;
            $other->correct_answers = '';
            $other->answerids = 0;
            $other->timeleft = 0;
            $other->type = $question->get('qtype');
            $other->response = [];
            $json = json_encode($other, JSON_THROW_ON_ERROR);
            $data->kuetid = $question->get('kuetid');
            $data->questionid = $question->get('questionid');
            $result = questions::NORESPONSE;
        } else {
            $question = new kuet_questions($kid);
            $session = new kuet_sessions($sid);
            return [
                'cmid' => $cmid,
                'sessionid' => $sid,
                'kuetid' => $question->get('kuetid'),
                'questionid' => $question->get('questionid'),
                'kid' => $kid,
                'programmedmode' => $session->is_programmed_mode(),
            ];
        }

        switch ($other->type) {
            case questions::MULTICHOICE:
                return (array)multichoice::export_question_response($data, $json);
            case questions::MATCH:
                return (array)matchquestion::export_question_response($data, $json, $result);
            case questions::TRUE_FALSE:
                return (array)truefalse::export_question_response($data, $json);
            case questions::SHORTANSWER:
                return (array)shortanswer::export_question_response($data, $json);
            case questions::NUMERICAL:
                $dataexport = numerical::export_question(
                    $data->kid,
                    $cmid,
                    $sid,
                    $data->kuetid,
                    $preview);
                $dataexport->uid = $uid;
                return (array)numerical::export_question_response($dataexport, $json);
            case questions::CALCULATED:
                $dataexport = calculated::export_question(
                    $data->kid,
                    $cmid,
                    $sid,
                    $data->kuetid,
                    $preview);
                return (array)calculated::export_question_response($dataexport, $json);
            case questions::DESCRIPTION:
                return (array)description::export_question_response($data, $json);
            case questions::DDWTOS:
                $dataexport = ddwtos::export_question(
                    $data->kid,
                    $cmid,
                    $sid,
                    $data->kuetid,
                    $preview);
                return (array)ddwtos::export_question_response($dataexport, $json);
            default:
                throw new moodle_exception('question_nosuitable', 'mod_kuet', '',
                    [], get_string('question_nosuitable', 'mod_kuet'));
        }
    }

    /**
     * Get user response to question return
     *
     * @return external_single_structure
     */
    public static function getuserquestionresponse_returns(): external_single_structure {
        return question_exporter::get_read_structure();
    }
}
