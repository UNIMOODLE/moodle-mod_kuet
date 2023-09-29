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

namespace mod_jqshow\models;

use coding_exception;
use context_course;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use enrol_self\self_test;
use html_writer;
use invalid_parameter_exception;
use JsonException;
use mod_jqshow\external\ddwtos_external;
use mod_jqshow\helpers\reports;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use qtype_ddwtos_question;
use question_bank;
use question_definition;
use question_display_options;
use question_state;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;

class ddwtos extends questions {

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @param int $sid
     * @return void
     */
    public function construct(int $jqshowid, int $cmid, int $sid) {
        parent::__construct($jqshowid, $cmid, $sid);
    }

    /**
     * @param int $jqid
     * @param int $cmid
     * @param int $sessionid
     * @param int $jqshowid
     * @param bool $preview
     * @return object
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function export_ddwtos(int $jqid, int $cmid, int $sessionid, int $jqshowid, bool $preview = false): object {
        $session = jqshow_sessions::get_record(['id' => $sessionid]);
        $jqshowquestion = jqshow_questions::get_record(['id' => $jqid]);
        $question = question_bank::load_question($jqshowquestion->get('questionid'));
        if (!assert($question instanceof qtype_ddwtos_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        $question->shufflechoices = 0;
        $type = $question->get_type_name();
        $data = self::get_question_common_data($session, $jqid, $cmid, $sessionid, $jqshowid, $preview, $jqshowquestion, $type);
        $data->$type = true;
        $data->qtype = $type;
        $data->questiontextformat = $question->questiontextformat;
        $data->name = $question->name;
        $data->questiontext = self::get_question_text($cmid, $question);
        $data->randomanswers = $session->get('randomanswers') === 1;
        return $data;
    }

    /**
     * @param stdClass $data
     * @param string $response
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function export_ddwtos_response(stdClass $data, string $response): stdClass {
        $responsedata = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
        if (!isset($responsedata->response) || (is_array($responsedata->response) && count($responsedata->response) === 0)) {
            $responsedata->response = '';
        }
        $data->answered = true;
        $dataanswer = ddwtos_external::ddwtos(
            $data->sessionid,
            $data->jqshowid,
            $data->cmid,
            $data->questionid,
            $data->jqid,
            $responsedata->timeleft,
            true,
            $responsedata->response
        );
        $question = question_bank::load_question($data->questionid);
        if (!assert($question instanceof qtype_ddwtos_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        $question->shufflechoices = 0;
        $data->questiontext = self::get_question_text(
            $data->cmid, $question,
            (array)json_decode($responsedata->response, false, 512, JSON_THROW_ON_ERROR)
        );
        $data->hasfeedbacks = $dataanswer['hasfeedbacks'];
        $data->ddwtosresponse = $responsedata->response;
        $data->seconds = $responsedata->timeleft;
        $data->programmedmode = $dataanswer['programmedmode'];
        $data->statment_feedback = $dataanswer['statment_feedback'];
        $data->answer_feedback = $dataanswer['answer_feedback'];
        $data->jsonresponse = json_encode($dataanswer, JSON_THROW_ON_ERROR);
        $data->statistics = $dataanswer['statistics'] ?? '0';
        return $data;
    }

    /**
     * @param int $cmid
     * @param qtype_ddwtos_question $question
     * @param array $response
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     */
    public static function get_question_text(int $cmid, qtype_ddwtos_question $question, array $response = []) {
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
        foreach ($placeholders as $i => $placeholder) {
            $questiontext = preg_replace('/'. preg_quote($placeholder, '/') . '/',
                $embeddedelements[$placeholder], $questiontext);
        }

        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));
        $result .= self::post_qtext_elements($question, $options, $response);

        return $result;
    }

    /**
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
            'class' => 'place' . $place . ' drop active group' . $group
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
     * @param int $fraction
     * @param bool $selected
     * @return mixed
     * @throws coding_exception
     */
    private static function feedback_image(int $fraction, bool $selected = true) {
        global $OUTPUT;
        $feedbackclass = question_state::graded_state_for_fraction($fraction)->get_feedback_class();
        return $OUTPUT->pix_icon('i/grade_' . $feedbackclass, get_string($feedbackclass, 'question'));
    }


    /**
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
        $result .= html_writer::tag('div', $dragboxs, array('class' => implode(' ', $classes)));
        if (!$options->clearwrong) {
            $result .= self::clear_wrong($question, $response);
        }

        return $result;
    }

    /**
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
                $output .= html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'id' => uniqid('', true),
                    'class' => 'placeinput place' . $place . ' group' . $group,
                    'name' => uniqid('', true),
                    'value' => s($value)));
            } else {
                $output .= html_writer::empty_tag('input', array(
                        'type' => 'hidden',
                        'id' => uniqid('', true),
                        'class' => 'placeinput place' . $place . ' group' . $group,
                        'value' => s($value))) .
                    html_writer::empty_tag('input', array(
                        'type' => 'hidden',
                        'name' => uniqid('', true),
                        'value' => s($cleanvalue)));
            }
        }
        return $output;
    }

    /**
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
     * @param jqshow_sessions $session
     * @param question_definition $questiondata
     * @param stdClass $data
     * @param int $jqid
     * @return void
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_question_report(jqshow_sessions     $session,
                                               question_definition $questiondata,
                                               stdClass            $data,
                                               int                 $jqid): stdClass {
        if (!assert($questiondata instanceof qtype_ddwtos_question)) {
            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        return $data;
    }

    /**
     * @param stdClass $participant
     * @param jqshow_questions_responses $response
     * @param array $answers
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     */
    public static function get_ranking_for_question(
        stdClass $participant,
        jqshow_questions_responses $response,
        array $answers,
        jqshow_sessions $session,
        jqshow_questions $question): stdClass {
        $participant->response = 'noevaluable';
        $participant->responsestr = get_string('noevaluable', 'mod_jqshow');
        $participant->userpoints = 0;
        $participant->score_moment = 0;
        $participant->time = reports::get_user_time_in_question($session, $question, $response);
        return $participant;
    }

    /**
     * @param int $jqid
     * @param string $responsetext
     * @param int $result
     * @param int $questionid
     * @param int $sessionid
     * @param int $jqshowid
     * @param string $statmentfeedback
     * @param string $answerfeedback
     * @param int $userid
     * @param int $timeleft
     * @return void
     * @throws JsonException
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function ddwtos_response(
        int $jqid,
        string $responsetext,
        int $result,
        int $questionid,
        int $sessionid,
        int $jqshowid,
        string $statmentfeedback,
        string $answerfeedback,
        int $userid,
        int $timeleft
    ): void {
        global $COURSE;
        $coursecontext = context_course::instance($COURSE->id);
        $isteacher = has_capability('mod/jqshow:managesessions', $coursecontext);
        if (!$isteacher) {
            $session = new jqshow_sessions($sessionid);
            if ($session->is_group_mode()) {
                // TODO.
            } else {
                // Individual.
                $response = new stdClass();
                $response->hasfeedbacks = (bool)($statmentfeedback !== '' | $answerfeedback !== '');
                $response->timeleft = $timeleft;
                $response->type = questions::DDWTOS;
                $response->response = $responsetext;
                jqshow_questions_responses::add_response(
                    $jqshowid, $sessionid, $jqid, $questionid, $userid, $result, json_encode($response, JSON_THROW_ON_ERROR)
                );
            }
        }
    }

    /**
     * @param stdClass $useranswer
     * @param jqshow_questions_responses $response
     * @return float|int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_simple_mark(stdClass $useranswer, jqshow_questions_responses $response) {
        global $DB;
        $mark = 0;
        if ((int) $response->get('result') === 1) {
            $mark = $DB->get_field('question', 'defaultmark', ['id' => $response->get('questionid')]);
        }

        return $mark;
    }
}
