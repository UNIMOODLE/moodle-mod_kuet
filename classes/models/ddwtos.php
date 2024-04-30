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
 * Drag and drop model
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
use dml_transaction_exception;
use html_writer;
use invalid_parameter_exception;
use JsonException;
use mod_kuet\api\grade;
use mod_kuet\external\ddwtos_external;
use mod_kuet\helpers\reports;
use mod_kuet\persistents\kuet;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use qtype_ddwtos_question;
use question_bank;
use question_definition;
use question_display_options;
use question_state;
use stdClass;
use mod_kuet\interfaces\questionType;



/**
 * Drag and drop class
 */
class ddwtos extends questions implements questionType {

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
        if (!assert($question instanceof qtype_ddwtos_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_kuet', '',
                [], get_string('question_nosuitable', 'mod_kuet'));
        }
        $question->shufflechoices = 0;
        $type = $question->get_type_name();
        $data = self::get_question_common_data($session, $cmid, $sessionid, $kuetid, $preview, $kuetquestion, $type);
        $data->$type = true;
        $data->qtype = $type;
        $data->questiontextformat = $question->questiontextformat;
        $data->name = $question->name;
        $data->questiontext = self::get_question_text($cmid, $question);
        $data->randomanswers = $session->get('randomanswers') === 1;
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
        $dataanswer = ddwtos_external::ddwtos(
            $data->sessionid,
            $data->kuetid,
            $data->cmid,
            $data->questionid,
            $data->kid,
            $responsedata->timeleft,
            true,
            $responsedata->response
        );
        $question = question_bank::load_question($data->questionid);
        if (!assert($question instanceof qtype_ddwtos_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_kuet', '',
                [], get_string('question_nosuitable', 'mod_kuet'));
        }
        $question->shufflechoices = 0;
        $data->questiontext = self::get_question_text(
            $data->cmid, $question,
            (array)json_decode($responsedata->response, false)
        );
        $data->hasfeedbacks = $dataanswer['hasfeedbacks'];
        $data->ddwtosresponse = $responsedata->response;
        $data->seconds = $responsedata->timeleft;
        $data->programmedmode = $dataanswer['programmedmode'];
        $data->statment_feedback = self::escape_characters($dataanswer['statment_feedback']);
        $data->answer_feedback = self::escape_characters($dataanswer['answer_feedback']);
        $data->jsonresponse = json_encode($dataanswer, JSON_THROW_ON_ERROR);
        $data->statistics = $dataanswer['statistics'] ?? '0';
        return $data;
    }

    /**
     * Get question text
     *
     * @param int $cmid
     * @param qtype_ddwtos_question $question
     * @param array $response
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     */
    public static function get_question_text(int $cmid, qtype_ddwtos_question $question, array $response = []):string {
        $questiontext = '';
        $embeddedelements = [];
        $placeholders = self::get_fragments_glue_placeholders($question->textfragments);
        $options = new question_display_options();
        foreach ($question->textfragments as $i => $fragment) {
            if ($i > 0) {
                $questiontext .= $placeholders[$i];
                $embeddedelements[$placeholders[$i]] =
                    preg_replace('/\$(\d)/', '\\\$$1', self::embedded_element($question, $i, $options, $response));
            }
            $questiontext .= $fragment;
        }
        $questiontext =
            self::get_text($cmid, $questiontext, $question->questiontextformat, $question->id, $question, 'questiontext');
        foreach ($placeholders as $placeholder) {
            $questiontext = preg_replace('/'. preg_quote($placeholder, '/') . '/',
                $embeddedelements[$placeholder], $questiontext);
        }

        $result = html_writer::tag('div', $questiontext, ['class' => 'qtext']);
        $result .= self::post_qtext_elements($question, $options, $response);

        return $result;
    }

    /**
     * Get fragments and glue placeholders
     *
     * @param array $fragments
     * @return array
     */
    private static function get_fragments_glue_placeholders(array $fragments): array {
        $fragmentscount = count($fragments);
        if ($fragmentscount <= 1) {
            return [];
        }
        $prefix = '[[$';
        $postfix = ']]';
        $text = implode('', $fragments);
        while (preg_match('/' . preg_quote($prefix, '/') . '\\d+' . preg_quote($postfix, '/') . '/', $text)) {
            $prefix .= '$';
        }
        $glues = [];
        for ($i = 1; $i < $fragmentscount; $i++) {
            $glues[$i] = $prefix . $i . $postfix;
        }
        return $glues;
    }

    /**
     * Provide the correct response
     *
     * @param qtype_ddwtos_question $question
     * @param int $cmid
     * @return string
     * @throws dml_exception
     * @throws dml_transaction_exception
     */
    public static function correct_response(qtype_ddwtos_question $question, int $cmid): string {
        $correctanswer = '';
        foreach ($question->textfragments as $i => $fragment) {
            if ($i > 0) {
                $group = $question->places[$i];
                $choice = $question->choices[$group][$question->rightchoices[$i]];
                $correctanswer .= '[' . str_replace('-', '&#x2011;',
                        $choice->text) . ']';
            }
            $correctanswer .= $fragment;
        }
        if (!empty($correctanswer)) {
            $correctanswer =
                self::get_text($cmid, $correctanswer, $question->questiontextformat, $question->id, $question, 'questiontext');
        }
        return $correctanswer;
    }

    /**
     * Embedded element
     *
     * @param qtype_ddwtos_question $question
     * @param int $place
     * @param question_display_options $options
     * @param array $response
     * @return string
     * @throws coding_exception
     */
    private static function embedded_element(qtype_ddwtos_question $question,
                                               int $place,
                                               question_display_options $options, array $response = []): string {
        $group = $question->places[$place];
        if (!empty($response)) {
            $options->questionidentifier = $question->name;
        }
        $label = $options->add_question_identifier_to_label(get_string('blanknumber', 'qtype_ddwtos', $place));
        $boxcontents = '&#160;' . html_writer::tag('span', $label, ['class' => 'accesshide']);
        $attributes = [
            'class' => 'place' . $place . ' drop active group' . $group,
        ];
        if ($options->readonly || !empty($response)) {
            $attributes['class'] .= ' readonly';
        } else {
            $attributes['tabindex'] = '0';
        }
        $feedbackimage = '';
        if ($options->correctness && !empty($response)) {
            $fieldname = $question->field($place);
            if (array_key_exists($fieldname, $response)) {
                $fraction = (int)((int)$response[$fieldname] === (int)self::get_right_choice_for($question, $place));
                $feedbackimage = self::feedback_image($fraction);
            }
        }
        return html_writer::tag('span', $boxcontents, $attributes) . ' ' . $feedbackimage;
    }

    /**
     * Get the right choice for
     *
     * @param qtype_ddwtos_question $question
     * @param int $place
     * @return int|string|null
     */
    private static function get_right_choice_for(qtype_ddwtos_question $question, int $place) {
        $choiceorderarray = [];
        foreach ($question->choices as $group => $choices) {
            $choiceorder = array_keys($choices);
            foreach ($choiceorder as $key => $value) {
                $choiceorderarray[$group][$key + 1] = $value;
            }
        }
        $group = $question->places[$place];
        foreach ($choiceorderarray[$group] as $choicekey => $choiceid) {
            if ($question->rightchoices[$place] === $choiceid) {
                return $choicekey;
            }
        }
        return null;
    }

    /**
     * Provide feedback image
     *
     * @param int $fraction
     * @param bool $selected
     * @return string
     * @throws coding_exception
     */
    private static function feedback_image(int $fraction, bool $selected = true): string {
        global $OUTPUT;
        $feedbackclass = question_state::graded_state_for_fraction($fraction)->get_feedback_class();
        return $OUTPUT->pix_icon('i/grade_' . $feedbackclass, get_string($feedbackclass, 'question'));
    }


    /**
     * Post question text elements
     *
     * @param qtype_ddwtos_question $question
     * @param question_display_options $options
     * @param array $response
     * @return string
     */
    private static function post_qtext_elements(qtype_ddwtos_question $question,
                                                question_display_options $options,
                                                array $response = []): string {
        $result = '';
        $dragboxs = '';
        foreach ($question->choices as $group => $choices) {
            $dragboxs .= self::drag_boxes(
                $question->get_ordered_choices($group), $options);
        }
        $classes = ['answercontainer'];
        if ($options->readonly || !empty($response)) {
            $classes[] = 'readonly';
        }
        $result .= html_writer::tag('div', $dragboxs, ['class' => implode(' ', $classes)]);
        if (!$options->clearwrong) {
            $result .= self::clear_wrong($question, $response);
        }

        return $result;
    }

    /**
     * Clear wrong answer
     *
     * @param qtype_ddwtos_question $question
     * @param array $response
     * @return string
     */
    public static function clear_wrong(qtype_ddwtos_question $question, array $response): string {
        $output = '';
        foreach ($question->places as $place => $group) {
            $fieldname = $question->field($place);
            if (array_key_exists($fieldname, $response)) {
                $value = (string) $response[$fieldname];
            } else {
                $value = '0';
            }
            if (array_key_exists($fieldname, $response)) {
                $cleanvalue = (string) $response[$fieldname];
            } else {
                $cleanvalue = '0';
            }
            if ($cleanvalue === $value) {
                $output .= html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'id' => uniqid('', true),
                    'class' => 'placeinput place' . $place . ' group' . $group,
                    'name' => uniqid('', true),
                    'value' => s($value)]);
            } else {
                $output .= html_writer::empty_tag('input', [
                        'type' => 'hidden',
                        'id' => uniqid('', true),
                        'class' => 'placeinput place' . $place . ' group' . $group,
                        'value' => s($value)]) .
                    html_writer::empty_tag('input', [
                        'type' => 'hidden',
                        'name' => uniqid('', true),
                        'value' => s($cleanvalue)]);
            }
        }
        return $output;
    }

    /**
     * Drag boxex
     *
     * @param array $choices
     * @return string
     */
    private static function drag_boxes(array $choices): string {
        $boxes = '';
        foreach ($choices as $key => $choice) {
            $content = str_replace(['-', ' '], ['&#x2011;', '&#160;'], $choice->text);
            $infinite = '';
            if ($choice->infinite) {
                $infinite = ' infinite';
            }
            $boxes .= html_writer::tag('span', $content, [
                    'class' => 'draghome user-select-none choice' . $key . ' group' .
                        $choice->draggroup . $infinite]) . ' ';
        }
        return html_writer::nonempty_tag('div', $boxes,
            ['class' => 'user-select-none draggrouphomes' . $choice->draggroup]);
    }

    /**
     * Get question report
     *
     * @param kuet_sessions $session
     * @param question_definition $questiondata
     * @param stdClass $data
     * @param int $kid
     * @return void
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_question_report(kuet_sessions     $session,
                                               question_definition $questiondata,
                                               stdClass            $data,
                                               int                 $kid): stdClass {
        if (!assert($questiondata instanceof qtype_ddwtos_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_kuet', '',
                [], get_string('question_nosuitable', 'mod_kuet'));
        }
        $data->questiontext = self::correct_response($questiondata, $data->cmid);
        $data->hasnoanswers = true;
        return $data;
    }

    /**
     * Get ranking for the question
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
        kuet_questions $question): stdClass {

        $participant->response = grade::get_result_mark_type($response);
        $participant->responsestr = get_string($participant->response, 'mod_kuet');
        $points = grade::get_simple_mark($response);
        $spoints = grade::get_session_grade($participant->participantid, $session->get('id'),
            $session->get('kuetid'));
        $participant->userpoints = grade::get_rounded_mark($spoints);
        if ($session->is_group_mode()) {
            $participant->grouppoints = grade::get_rounded_mark($spoints);
        }
        $participant->score_moment = grade::get_rounded_mark($points);
        $participant->time = reports::get_user_time_in_question($session, $question, $response);
        return $participant;
    }

    /**
     * Question response
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

        $responsetext = $custom['responsetext'];
        $result = $custom['result'];
        $answerfeedback = $custom['answerfeedback'];
        $cmcontext = context_module::instance($cmid);
        $isteacher = has_capability('mod/kuet:managesessions', $cmcontext);
        if ($isteacher !== true) {
            $session = new kuet_sessions($sessionid);
            $response = new stdClass();
            $response->hasfeedbacks = (bool)($statmentfeedback !== '' | $answerfeedback !== '');
            $response->timeleft = $timeleft;
            $response->type = questions::DDWTOS;
            $response->response = $responsetext;
            if ($session->is_group_mode()) {
                parent::add_group_response($kuetid, $session, $kid, $questionid, $userid, $result, $response);
            } else {
                kuet_questions_responses::add_response(
                    $kuetid, $sessionid, $kid, $questionid, $userid, $result, json_encode($response, JSON_THROW_ON_ERROR)
                );
            }
        }
    }

    /**
     * Get simple mark for the question
     *
     * @param stdClass $useranswer
     * @param kuet_questions_responses $response
     * @return float
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function get_simple_mark(stdClass $useranswer, kuet_questions_responses $response): float {
        $mark = 0;
        $kuet = new kuet($response->get('kuet'));
        [$course, $cm] = get_course_and_cm_from_instance($response->get('kuet'), 'kuet', $kuet->get('course'));
        $question = question_bank::load_question($response->get('questionid'));
        if (assert($question instanceof qtype_ddwtos_question)) {
            $question->shufflechoices = 0;
            questions::get_text(
                $cm->id, $question->generalfeedback, $question->generalfeedbackformat, $question->id, $question, 'generalfeedback'
            );
            $json = json_decode(base64_decode($response->get('response')), false);
            $responsejson = json_decode($json->response, false);
            $moodleresult = $question->grade_response((array)$responsejson);
            if (isset($moodleresult[0])) {
                $mark = $moodleresult[0];
            }
        }
        return (float)$mark;
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
        list($correct, $incorrect, $invalid, $partially, $noresponse) = grade::count_result_mark_types($responses);
        $statistics[0]['correct'] = $correct !== 0 ? round($correct * 100 / $total, 2) : 0;
        $statistics[0]['failure'] = $incorrect !== 0 ? round($incorrect * 100 / $total, 2) : 0;
        $statistics[0]['partially'] = $partially !== 0 ? round($partially * 100 / $total, 2) : 0;
        $statistics[0]['noresponse'] = $noresponse !== 0 ? round($noresponse * 100 / $total, 2) : 0;
        return $statistics;
    }
}
