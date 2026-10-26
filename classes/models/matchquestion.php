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
use mod_kuet\helpers\reports;
use mod_kuet\persistents\kuet;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use qtype_match_question;
use question_bank;
use question_definition;
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
        $question = question_bank::load_question($kuetquestion->get('questionid'), 0);
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
                // The key of the stem in qtype_match's own arrays. Nothing in the page
                // reads it - it used to be painted into a data-key attribute no script
                // looked at, and it never survived the exporter either (KUET-044).
                'key' => $key,
                'optionkey' => base_convert($key, 16, 2),
                // The choice this stem has to be joined to, as the option key of that
                // choice, which is what identifies its element in the page. It cannot be
                // worked out from the stem's own key: qtype_match merges the choices that
                // share a text, so several stems may point at one choice, and a choice may
                // be offered with no stem at all. $question->right is mixed in core - the
                // subquestion id as a string, or the array key of the choice it was merged
                // with, an int - so it is cast before it is converted.
                'correctoptionkey' => isset($question->right[$key])
                    ? base_convert((string)(int)$question->right[$key], 10, 26)
                    : '',
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
        $dataanswer = self::answer(
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
        // The choice a stem has to be joined to is the one $questiondata->right points
        // at, and not the choice that carries the stem's own key: qtype_match merges the
        // choices that share a text, so several stems may point at one choice, and a
        // choice may be offered with no stem at all.
        if (isset($questiondata->stems)) {
            foreach ($questiondata->stems as $key => $stem) {
                $choice = (int)($questiondata->right[$key] ?? 0);
                $correctanswers[$key]['response'] = $stem . ' -> ' . ($questiondata->choices[$choice] ?? '');
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
     * The mark is the proportion of stems matched to the choice that
     * qtype_match_question::$right points at, over the number of stems.
     *
     * Both halves of that matter. A choice with no stem is a distractor and there
     * is nothing to match it to, so it must not count towards the total; and several
     * stems may legitimately share one choice, because qtype_match merges the
     * choices that have the same text, so the correct choice of a stem cannot be
     * assumed to be the choice that carries the stem's own key.
     *
     * @param stdClass $useranswer
     * @param kuet_questions_responses $response
     * @return float|int
     * @throws JsonException
     * @throws coding_exception
     */
    public static function get_simple_mark(stdClass $useranswer, kuet_questions_responses $response): float {
        $question = question_bank::load_question($response->get('questionid'), 0);
        if (!assert($question instanceof qtype_match_question) || empty($question->stems)) {
            return 0;
        }
        $jsonresponse = json_decode(base64_decode($response->get('response')), false, 512, JSON_THROW_ON_ERROR);
        // The choice picked for each stem. Both ids come from the client, so only the
        // pairs that name a stem and a choice of this very question are taken.
        $picked = [];
        foreach ($jsonresponse->response ?? [] as $pair) {
            $stem = (int)($pair->stemDragId ?? 0);
            $choice = (int)($pair->stemDropId ?? 0);
            if (array_key_exists($stem, $question->stems) && array_key_exists($choice, $question->choices)) {
                $picked[$stem] = $choice;
            }
        }
        // The right mapping is mixed in core: its value is the subquestion id straight
        // from the database, so a string, except when the choice was merged with an
        // earlier one, where it is the key of that choice and therefore an int. Both
        // sides are cast so that a shared choice is not reported as a miss.
        $numright = 0;
        foreach (array_keys($question->stems) as $stem) {
            if (isset($picked[$stem], $question->right[$stem]) && $picked[$stem] === (int)$question->right[$stem]) {
                ++$numright;
            }
        }
        return (float)($numright / count($question->stems));
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
    /**
     * Grade and register an answer to a matching question
     *
     * Internal counterpart of the match web service. Report rendering
     * calls this directly, so that it never goes through the external API
     * and its validate_context() in the middle of a page render.
     *
     * @param string $jsonresponse
     * @param int $result
     * @param int $sessionid
     * @param int $kuetid
     * @param int $cmid
     * @param int $questionid
     * @param int $kid
     * @param int $timeleft
     * @param bool $preview
     * @throws JsonException
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @return array
     */
    public static function answer(
        string $jsonresponse,
        int $result,
        int $sessionid,
        int $kuetid,
        int $cmid,
        int $questionid,
        int $kid,
        int $timeleft,
        bool $preview
    ): array {
        global $PAGE, $USER;
        $contextmodule = context_module::instance($cmid);
        $PAGE->set_context($contextmodule);

        $session = new kuet_sessions($sessionid);
        $question = question_bank::load_question($questionid, 0);
        if (assert($question instanceof qtype_match_question)) {
            $statmentfeedback = questions::get_text(
                $cmid,
                $question->generalfeedback,
                $question->generalfeedbackformat,
                $question->id,
                $question,
                'generalfeedback'
            );
            switch ($result) {
                case questions::SUCCESS:
                    $answerfeedback = questions::get_text(
                        $cmid,
                        $question->correctfeedback,
                        $question->correctfeedbackformat,
                        $question->id,
                        $question,
                        'correctfeedback'
                    );
                    break;
                case questions::PARTIALLY:
                    $answerfeedback = questions::get_text(
                        $cmid,
                        $question->partiallycorrectfeedback,
                        $question->partiallycorrectfeedbackformat,
                        $question->id,
                        $question,
                        'partiallycorrectfeedback'
                    );
                    break;
                case questions::FAILURE:
                    $answerfeedback = questions::get_text(
                        $cmid,
                        $question->incorrectfeedback,
                        $question->incorrectfeedbackformat,
                        $question->id,
                        $question,
                        'incorrectfeedback'
                    );
                    break;
                default:
                    $answerfeedback = '';
                    break;
            }

            if ($preview === false) {
                $custom = [
                    'jsonresponse' => $jsonresponse,
                    'result' => $result,
                    'answerfeedback' => $answerfeedback,
                ];
                self::question_response(
                    $cmid,
                    $kid,
                    $questionid,
                    $sessionid,
                    $kuetid,
                    $statmentfeedback,
                    $USER->id,
                    $timeleft,
                    $custom
                );
            }
            return [
                'reply_status' => true,
                'hasfeedbacks' => (bool)($statmentfeedback !== '' | $answerfeedback !== ''),
                'statment_feedback' => $statmentfeedback,
                'answer_feedback' => $answerfeedback,
                'programmedmode' => ($session->get('sessionmode') === sessions::PODIUM_PROGRAMMED ||
                    $session->get('sessionmode') === sessions::INACTIVE_PROGRAMMED ||
                    $session->get('sessionmode') === sessions::RACE_PROGRAMMED),
                'preview' => $preview,
            ];
        }

        return [
            'reply_status' => false,
            'hasfeedbacks' => false,
            'programmedmode' => ($session->get('sessionmode') === sessions::PODIUM_PROGRAMMED ||
                $session->get('sessionmode') === sessions::INACTIVE_PROGRAMMED ||
                $session->get('sessionmode') === sessions::RACE_PROGRAMMED),
            'preview' => $preview,
        ];
    }
}
