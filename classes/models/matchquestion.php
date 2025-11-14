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
 * Match question model
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\models;

use coding_exception;
use context_module;
use core\invalid_persistent_exception;
use dml_exception;
use invalid_parameter_exception;
use JsonException;
use mod_kuet\api\grade;
use mod_kuet\external\match_external;
use mod_kuet\helpers\reports;
use mod_kuet\persistents\kuet;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use qtype_match_question;
use question_bank;
use question_definition;
use question_state;
use stdClass;
use mod_kuet\interfaces\questionType;



/**
 * Match question class
 */
class matchquestion extends questions implements questionType {
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
     * @throws moodle_exception
     */
    public static function export_question(int $kid, int $cmid, int $sessionid, int $kuetid, bool $preview = false): object {
        $session = kuet_sessions::get_record(['id' => $sessionid]);
        $kuetquestion = kuet_questions::get_record(['id' => $kid]);
        $question = question_bank::load_question($kuetquestion->get('questionid'));
        if (!assert($question instanceof qtype_match_question)) {
            throw new moodle_exception(
                'question_nosuitable',
                'mod_kuet',
                '',
                [],
                get_string('question_nosuitable', 'mod_kuet')
            );
        }
        $type = $question->get_type_name();
        $data = self::get_question_common_data($session, $cmid, $sessionid, $kuetid, $preview, $kuetquestion, $type);
        $data->$type = true;
        $data->qtype = $type;
        $data->questiontext =
            self::get_text($cmid, $question->questiontext, $question->questiontextformat, $question->id, $question, 'questiontext');
        $data->questiontextformat = $question->questiontextformat;
        $leftoptions = [];
        foreach ($question->stems as $key => $leftside) {
            $leftoptions[$key] = [
                'questionid' => $kuetquestion->get('questionid'),
                'key' => $key,
                'optionkey' => base_convert($key, 16, 2),
                'optiontext' =>
                    self::get_text($cmid, $leftside, $question->stemformat[$key] ?? 1, $question->id, $question, 'questiontext'),
            ];
        }
        $rightoptions = [];
        foreach ($question->choices as $key => $rightside) {
            $rightoptions[$key] = [
                'questionid' => $kuetquestion->get('questionid'),
                'key' => $key,
                'optionkey' => base_convert($key, 10, 26),
                'optiontext' =>
                    self::get_text($cmid, $rightside, $question->stemformat[$key] ?? 1, $question->id, $question, 'questiontext'),
            ];
        }
        $data->name = $question->name;
        shuffle($rightoptions);
        if ($session->get('randomanswers') === 1) {
            shuffle($leftoptions);
        }
        $data->leftoptions = array_values($leftoptions);
        $data->rightoptions = array_values($rightoptions);
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
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function export_question_response(stdClass $data, string $response, int $result): stdClass {
        $responsedata = json_decode($response, false);
        $data->answered = true;
        $jsonresponse = json_encode($responsedata->response, JSON_THROW_ON_ERROR);
        $dataanswer = match_external::match(
            $jsonresponse,
            $result,
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
        $data->correct_answers = $dataanswer['correct_answers'];
        $data->programmedmode = $dataanswer['programmedmode'];
        $data->jsonresponse = base64_encode($jsonresponse);
        if ($data->hasfeedbacks) {
            $dataanswer['statment_feedback'] = self::escape_characters($dataanswer['statment_feedback']);
            $dataanswer['answer_feedback'] = self::escape_characters($dataanswer['answer_feedback']);
        }
        $data->statment_feedback = $dataanswer['statment_feedback'];
        $data->answer_feedback = $dataanswer['answer_feedback'];
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
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_question_report(
        kuet_sessions $session,
        question_definition $questiondata,
        stdClass $data,
        int $kid
    ): stdClass {
        $answers = [];
        $correctanswers = [];
        if (!assert($questiondata instanceof qtype_match_question)) {
            throw new moodle_exception(
                'question_nosuitable',
                'mod_kuet',
                '',
                [],
                get_string('question_nosuitable', 'mod_kuet')
            );
        }
        if (isset($questiondata->stems)) {
            foreach ($questiondata->stems as $key => $answer) {
                $correctanswers[$key]['response'] = $answer . ' -> ' . $questiondata->choices[$key];
            }
        }
        $data->correctanswers = array_values($correctanswers);
        $data->answers = array_values($answers);
        $data->nostatistics = true;
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
     * @throws dml_exception|moodle_exception
     */
    public static function get_ranking_for_question(
        stdClass $participant,
        kuet_questions_responses $response,
        array $answers,
        kuet_sessions $session,
        kuet_questions $question
    ): stdClass {
        $participant->response = grade::get_result_mark_type($response);
        $participant->responsestr = get_string($participant->response, 'mod_kuet');
        $points = grade::get_simple_mark($response);
        $spoints = grade::get_session_grade(
            $participant->participantid,
            $session->get('id'),
            $session->get('kuetid')
        );
        $participant->userpoints = grade::get_rounded_mark($spoints);
        if ($session->is_group_mode()) {
            $participant->grouppoints = grade::get_rounded_mark($spoints);
        }
        $participant->score_moment = grade::get_rounded_mark($points);
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
        array $custom
    ): void {

        $jsonresponse = $custom['jsonresponse'];
        $result = $custom['result'];
        $answerfeedback = $custom['answerfeedback'];
        $cmcontext = context_module::instance($cmid);
        $isteacher = has_capability('mod/kuet:managesessions', $cmcontext);
        if ($isteacher !== true) {
            $session = new kuet_sessions($sessionid);
            $response = new stdClass();
            $response->hasfeedbacks = (bool)($statmentfeedback !== '' | $answerfeedback !== '');
            $response->timeleft = $timeleft;
            $response->type = questions::MATCH;
            $response->response = json_decode($jsonresponse);
            if ($session->is_group_mode()) {
                parent::add_group_response($kuetid, $session, $kid, $questionid, $userid, $result, $response);
            } else {
                // Individual.
                kuet_questions_responses::add_response(
                    $kuetid,
                    $sessionid,
                    $kid,
                    $questionid,
                    $userid,
                    $result,
                    json_encode($response, JSON_THROW_ON_ERROR)
                );
            }
        }
    }

    /**
     * Get simple mark
     *
     * @param stdClass $useranswer
     * @param kuet_questions_responses $response
     * @return float|int
     * @throws JsonException
     * @throws coding_exception
     */
    public static function get_simple_mark(stdClass $useranswer, kuet_questions_responses $response): float {
        global $DB;
        $mark = 0;
        $question = question_bank::load_question($response->get('questionid'));
        if (assert($question instanceof qtype_match_question)) {
            $jsonresponse = json_decode(base64_decode($response->get('response')), false, 512, JSON_THROW_ON_ERROR);
            usort($jsonresponse->response, static fn($a, $b) => strcmp($a->stemDragId, $b->stemDragId));
            $moodleresponse = [];
            $positionstems = 0;
            $stemorder = [];
            foreach ($question->choices as $keychoice => $rightside) {
                $stemorder[] = $keychoice;
            }
            foreach ($question->stems as $keystem => $leftside) {
                $moodleresponse[$positionstems] = 0;
                foreach ($jsonresponse->response as $useroptionresponse) {
                    if ((int)$useroptionresponse->stemDragId === $keystem) {
                        foreach ($question->choices as $keychoice => $rightside) {
                            if ((int)$useroptionresponse->stemDropId === $keychoice) {
                                $moodleresponse[$positionstems] = $keychoice;
                            }
                        }
                    }
                }
                $positionstems++;
            }
            [$right, $total] = self::get_num_parts_right($moodleresponse, $stemorder);
            $fraction = $right / $total;
            $moodleresult = [$fraction, question_state::graded_state_for_fraction($fraction)];
            if (isset($moodleresult[0])) {
                $mark = $moodleresult[0];
            }
        }
        return (float)$mark;
    }

    /**
     * Get number of right parts to match
     *
     * @param array $moodleresponse
     * @param array $stemorder
     * @return array
     */
    private static function get_num_parts_right(array $moodleresponse, array $stemorder) {
        $numright = 0;
        foreach ($stemorder as $key => $stemid) {
            if (!array_key_exists($key, $moodleresponse)) {
                continue;
            }
            $choice = $moodleresponse[$key];
            if ($stemid === $moodleresponse[$key]) {
                ++$numright;
            }
        }
        return [$numright, count($stemorder)];
    }

    /**
     * Get question statistics
     *
     * @param question_definition $question
     * @param kuet_questions_responses[] $responses
     * @return array
     * @throws coding_exception
     */
    public static function get_question_statistics(question_definition $question, array $responses): array {
        $statistics = [];
        $total = count($responses);
        [$correct, $incorrect, $invalid, $partially, $noresponse] = grade::count_result_mark_types($responses);
        $statistics[0]['correct'] = $correct !== 0 ? round($correct * 100 / $total, 2) : 0;
        $statistics[0]['failure'] = $incorrect !== 0 ? round($incorrect * 100 / $total, 2) : 0;
        $statistics[0]['partially'] = $partially !== 0 ? round($partially * 100 / $total, 2) : 0;
        $statistics[0]['noresponse'] = $noresponse !== 0 ? round($noresponse * 100 / $total, 2) : 0;
        return $statistics;
    }
}
