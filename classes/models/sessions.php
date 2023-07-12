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
use context_module;
use core\invalid_persistent_exception;
use core_availability\info_module;
use core_php_time_limit;
use dml_exception;
use Exception;
use mod_jqshow\external\sessionquestions_external;
use mod_jqshow\forms\sessionform;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use pix_icon;
use qbank_managecategories\helper;
use stdClass;
use user_picture;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/question/editlib.php');

class sessions {

    /** @var stdClass $jqshow */
    protected stdClass $jqshow;

    /** @var int cmid */
    protected int $cmid;

    /** @var jqshow_sessions[] list */
    protected array $list;

    // Session modes.
    public const INACTIVE_MANUAL = 'inactive_manual';
    public const INACTIVE_PROGRAMMED = 'inactive_programmed';
    public const PODIUM_MANUAL = 'podium_manual';
    public const PODIUM_PROGRAMMED = 'podium_programmed';
    public const RACE_MANUAL = 'race_manual';
    public const RACE_PROGRAMMED = 'race_programmed';

    // Anonymous response.
    public const ANONYMOUS_ANSWERS_NO = 0;
    public const ANONYMOUS_ANSWERS = 1;

    // Time mode.
    public const NO_TIME = 0;
    public const SESSION_TIME = 1;
    public const QUESTION_TIME = 2;

    /**
     * sessions constructor.
     * @param stdClass $jqshow
     * @param int $cmid
     */
    public function __construct(stdClass $jqshow, int $cmid) {
        $this->jqshow = $jqshow;
        $this->cmid = $cmid;
    }

    /**
     * @return void
     */
    public function set_list() {
        $this->list = jqshow_sessions::get_records(['jqshowid' => $this->jqshow->id]);
    }

    /**
     * @return jqshow_sessions[]
     */
    public function get_list(): array {
        if (empty($this->list)) {
            $this->set_list();
        }
        return $this->list;
    }
    /**
     * @return Object
     * @throws moodle_exception
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public function export_form(): Object {
        $sid = optional_param('sid', 0, PARAM_INT);    // Session id.
        $anonymousanswerchoices = [
            self::ANONYMOUS_ANSWERS_NO => get_string('noanonymiseresponses', 'mod_jqshow'),
            self::ANONYMOUS_ANSWERS => get_string('anonymiseresponses', 'mod_jqshow')
        ];
        $sessionmodechoices = [
            self::INACTIVE_MANUAL => get_string('inactive_manual', 'mod_jqshow'),
            self::INACTIVE_PROGRAMMED => get_string('inactive_programmed', 'mod_jqshow'),
            self::PODIUM_MANUAL => get_string('podium_manual', 'mod_jqshow'),
            self::PODIUM_PROGRAMMED => get_string('podium_programmed', 'mod_jqshow'),
            self::RACE_MANUAL => get_string('race_manual', 'mod_jqshow'),
            self::RACE_PROGRAMMED => get_string('race_programmed', 'mod_jqshow'),
        ];
        $timemode = [
            self::NO_TIME => get_string('no_time', 'mod_jqshow'),
            self::SESSION_TIME => get_string('session_time', 'mod_jqshow'),
            self::QUESTION_TIME => get_string('question_time', 'mod_jqshow'),
        ];
        $countdownchoices = [
            0 => 'Opcion1',
            1 => 'Opcion2',
            3 => 'Opcion3'
        ];
        $groupingsselect = [];
        [$course, $cm] = get_course_and_cm_from_cmid($this->cmid);
        if ($cm->groupmode) {
            $groupings = groups_get_all_groupings($cm->course);
            if (!empty($groupings)) {
                foreach ($groupings as $grouping) {
                    $groupingsselect[$grouping->id] = $grouping->name;
                }
            }
        }
        $customdata = [
            'course' => $course,
            'cm' => $cm,
            'jqshowid' => $this->jqshow->id,
            'countdown' => $countdownchoices,
            'sessionmodechoices' => $sessionmodechoices,
            'timemode' => $timemode,
            'anonymousanswerchoices' => $anonymousanswerchoices,
            'groupingsselect' => $groupingsselect,
        ];

        $action = new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $this->cmid, 'sid' => $sid, 'page' => 1]);
        $mform = new sessionform($action->out(false), $customdata);

        if ($mform->is_cancelled()) {
            $url = new moodle_url('/mod/jqshow/view.php', ['id' => $this->cmid]);
            redirect($url);
        } else if ($fromform = $mform->get_data()) {
            $sid = self::save_session($fromform);
            $url = new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $this->cmid, 'sid' => $sid,  'page' => 2]);
            redirect($url);
        }
        if ($sid) {
            $formdata = $this->get_form_data($sid);
            $mform->set_data($formdata);
        }
        $data = new stdClass();
        $data->form = $mform->render();
        $data->ispage1 = true;

        return $data;
    }

    /**
     * @param int $sid
     * @return array
     * @throws coding_exception
     */
    private function get_form_data(int $sid): array {
        $session = $this->get_session(['id' => $sid]);
        return [
            'sessionid' => $session->get('id'),
            'name' => $session->get('name'),
            'anonymousanswer' => $session->get('anonymousanswer'),
            'sessionmode' => $session->get('sessionmode'),
            'countdown' => $session->get('countdown'),
            'showgraderanking' => $session->get('showgraderanking'),
            'randomquestions' => $session->get('randomquestions'),
            'randomanswers' => $session->get('randomanswers'),
            'showfeedback' => $session->get('showfeedback'),
            'showfinalgrade' => $session->get('showfinalgrade'),
            'startdate' => $session->get('startdate'),
            'enddate' => $session->get('enddate'),
            'automaticstart' => $session->get('automaticstart'),
            'timemode' => $session->get('timemode'),
            'sessiontime' => $session->get('sessiontime'),
            'questiontime' => $session->get('questiontime')
        ];
    }

    /**
     * @return Object
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function export_session_questions(): Object {
        global $DB;
        $data = new stdClass();
        $data->ispage2 = true;
        $data->sid = required_param('sid', PARAM_INT);
        $data->cmid = required_param('cmid', PARAM_INT);
        $data->jqshowid = $this->jqshow->id;
        [$data->currentcategory, $data->questionbank_categories] = $this->get_questionbank_select();
        $course = $DB->get_record_sql("
                    SELECT c.*
                      FROM {course_modules} cm
                      JOIN {course} c ON c.id = cm.course
                     WHERE cm.id = ?", [$this->cmid], MUST_EXIST);
        $data->questionbank_url = (new moodle_url('/question/edit.php', ['courseid' => $course->id]))->out(false);
        $data->questions = $this->get_questions_for_category($data->currentcategory);
        $allquestions = (new questions($data->jqshowid, $data->cmid, $data->sid))->get_list();
        $questiondata = [];
        foreach ($allquestions as $question) {
            $questiondata[] = sessionquestions_external::export_question($question, $this->cmid);
        }
        $data->sessionquestions = $questiondata;
        $data->resumeurl =
            (new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $data->cmid, 'sid' => $data->sid, 'page' => 3]))->out(false);
        $data->formurl =
            (new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $data->cmid, 'sid' => $data->sid, 'page' => 1]))->out(false);
        return $data;
    }

    /**
     * @param string $category
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_questions_for_category(string $category): array {
        global $DB, $OUTPUT;
        core_php_time_limit::raise(300);
        // Get all the categories below the marked one, as they would be subcategories.
        $categories = [];
        $context = context_module::instance($this->cmid);
        $contexts = $context->get_parent_contexts();
        $contexts[$context->id] = $context;
        $categoriesarray = helper::question_category_options($contexts, true, 0,
            false, -1, false);
        $asof = false;
        foreach ($categoriesarray as $sistemcategory) {
            foreach ($sistemcategory as $key => $subcategory) {
                if ($key === $category) {
                    $asof = true;
                }
                if ($asof) {
                    $categories[] = $key;
                }
            }
            if ($asof) {
                break;
            }
        }
        $catstr = '';
        $params = [];
        $questions = [];

        foreach ($categories as $key => $str) {
            [$categoryid, $contextid] = explode(',', $str);
            $catstr .= ':cat_' . $key . ',';
            $params['cat_' . $key] = $categoryid;
        }
        if (!empty($params) && $catstr !== '') {
            // TODO review, categories of the same level bring all questions.
            $catstr = trim($catstr, ',');
            $sql = "SELECT
                        qv.status,
                        qc.id as categoryid,
                        qv.version,
                        qv.id as versionid,
                        qbe.id as questionbankentryid,
                        q.id,
                        q.qtype,
                        q.name,
                        qbe.idnumber,
                        qc.contextid
                    FROM {question} q
                        JOIN {question_versions} qv ON qv.questionid = q.id
                        JOIN {question_bank_entries} qbe on qbe.id = qv.questionbankentryid
                        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                            WHERE q.parent = 0
                            AND qv.version = (SELECT MAX(v.version)
                                                FROM {question_versions} v
                                                JOIN {question_bank_entries} be
                                                ON be.id = v.questionbankentryid
                                                WHERE be.id = qbe.id)
                                                    AND ((qbe.questioncategoryid IN ($catstr)))
                            ORDER BY q.qtype ASC, q.name ASC";
            $questionsrs = $DB->get_recordset_sql($sql, $params);
            foreach ($questionsrs as $question) {
                if (!empty($question->id)) {
                    $questions[$question->id] = $question;
                }
            }
            $questionsrs->close();
        }
        foreach ($questions as $key => $question) {
            $icon = new pix_icon('icon', '', 'qtype_' . $question->qtype, [
                'class' => 'icon',
                'title' => ''
            ]);
            $question->icon = $icon->export_for_pix();
            $question->issuitable = in_array($question->qtype, questions::TYPES);
            $question->questionpreview =
                (new moodle_url('/question/bank/previewquestion/preview.php', ['id' => $key]))->out(false);
            $question->questionedit =
                (new moodle_url('/question/bank/editquestion/question.php', ['id' => $key, 'cmid' => $this->cmid]))->out(false);
            $questions[$key] = (array)$question;
        }
        return array_values($questions);
    }

    /**
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    private function get_questionbank_select(): array {
        $context = context_module::instance($this->cmid);
        $contexts = $context->get_parent_contexts();
        $contexts[$context->id] = $context;
        $categoriesarray = helper::question_category_options($contexts, true, 0,
            false, -1, false);
        $currentcategory = [];
        foreach ($categoriesarray as $sistemcategory) {
            foreach ($sistemcategory as $key => $category) {
                $currentcategory = $key;
                break;
            }
            break;
        }
        return [$currentcategory, helper::question_category_select_menu($contexts, true, 0,
            true, -1, true)];
    }

    /**
     * @return Object
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function export_session_resume(): Object {
        $data = new stdClass();
        $data->ispage3 = true;
        $data->sid = required_param('sid', PARAM_INT);
        $data->cmid = required_param('cmid', PARAM_INT);
        $data->jqshowid = $this->jqshow->id;
        $data->config = self::get_session_config($data->sid, $data->cmid);
        $allquestions = (new questions($data->jqshowid, $data->cmid, $data->sid))->get_list();
        $questiondata = [];
        foreach ($allquestions as $question) {
            $questiondata[] = sessionquestions_external::export_question($question, $this->cmid);
        }
        $data->sessionquestions = $questiondata;
        $data->addquestions =
            (new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $data->cmid, 'sid' => $data->sid, 'page' => 2]))->out(false);
        $data->sessionsurl =
            (new moodle_url('/mod/jqshow/view.php', ['id' => $data->cmid]))->out(false);
        return $data;
    }

    /**
     * @param int $sid
     * @param int $cmid
     * @return array
     * @throws coding_exception
     */
    public static function get_session_config(int $sid, int $cmid): array {
        // TODO finish setting with all icons and session settings to be shown.
        $sessiondata = new jqshow_sessions($sid);
        $data = [];
        $data[] = [
            'iconconfig' => 'name',
            'configname' => get_string('session_name', 'mod_jqshow'),
            'configvalue' => $sessiondata->get('name')
        ];

        $data[] = [
            'iconconfig' => 'anonymise',
            'configname' => get_string('anonymousanswer', 'mod_jqshow'),
            'configvalue' => $sessiondata->get('anonymousanswer') === 1 ? get_string('yes') : get_string('no')
        ];

        $data[] = [
            'iconconfig' => 'sessionmode',
            'configname' => get_string('sessionmode', 'mod_jqshow'),
            'configvalue' => get_string($sessiondata->get('sessionmode'), 'mod_jqshow')
        ];

        $data[] = [
            'iconconfig' => 'countdown',
            'configname' => get_string('countdown', 'mod_jqshow'),
            'configvalue' => $sessiondata->get('countdown') === 1 ? get_string('yes') : get_string('no')
        ];

        if (in_array($sessiondata->get('sessionmode'), [self::PODIUM_MANUAL, self::PODIUM_PROGRAMMED], true)) {
            $data[] = [
                'iconconfig' => 'showgraderanking',
                'configname' => get_string('showgraderanking', 'mod_jqshow'),
                'configvalue' => $sessiondata->get('showgraderanking')
            ];
        }

        $data[] = [
            'iconconfig' => 'randomquestions',
            'configname' => get_string('randomquestions', 'mod_jqshow'),
            'configvalue' => $sessiondata->get('randomquestions') === 1 ? get_string('yes') : get_string('no')
        ];

        $data[] = [
            'iconconfig' => 'randomanswers',
            'configname' => get_string('randomanswers', 'mod_jqshow'),
            'configvalue' => $sessiondata->get('randomanswers') === 1 ? get_string('yes') : get_string('no')
        ];

        $data[] = [
            'iconconfig' => 'showfeedback',
            'configname' => get_string('showfeedback', 'mod_jqshow'),
            'configvalue' => $sessiondata->get('showfeedback') === 1 ? get_string('yes') : get_string('no')
        ];

        $data[] = [
            'iconconfig' => 'showfinalgrade',
            'configname' => get_string('showfinalgrade', 'mod_jqshow'),
            'configvalue' => $sessiondata->get('showfinalgrade') === 1 ? get_string('yes') : get_string('no')
        ];

        if ($sessiondata->get('startdate') !== 0) {
            $data[] = [
                'iconconfig' => 'startdate',
                'configname' => get_string('startdate', 'mod_jqshow'),
                'configvalue' => userdate($sessiondata->get('startdate'), get_string('strftimedatetimeshort', 'core_langconfig'))
            ];
        }

        if ($sessiondata->get('enddate') !== 0) {
            $data[] = [
                'iconconfig' => 'enddate',
                'configname' => get_string('enddate', 'mod_jqshow'),
                'configvalue' => userdate($sessiondata->get('enddate'), get_string('strftimedatetimeshort', 'core_langconfig'))
            ];
        }

        $data[] = [
            'iconconfig' => 'automaticstart',
            'configname' => get_string('automaticstart', 'mod_jqshow'),
            'configvalue' => $sessiondata->get('automaticstart') === 1 ? get_string('yes') : get_string('no')
        ];

        switch ($sessiondata->get('timemode')) {
            case self::NO_TIME:
            default:
                $timemodestring = get_string('no_time', 'mod_jqshow');
                break;
            case self::SESSION_TIME:
                $numquestion = jqshow_questions::count_records(
                    ['sessionid' => $sessiondata->get('id'), 'jqshowid' => $sessiondata->get('jqshowid')]
                );
                $timeperquestion = round((int)$sessiondata->get('sessiontime') / $numquestion);
                $timemodestring = get_string(
                    'session_time_resume', 'mod_jqshow', userdate($sessiondata->get('sessiontime'), '%Mm %Ss')
                    ) . '<br>' .
                    get_string('question_time', 'mod_jqshow') . ': ' .
                    $timeperquestion . 's';
                break;
            case self::QUESTION_TIME:
                $totaltime =
                    (new questions($sessiondata->get('jqshowid'), $cmid, $sessiondata->get('id')))->get_sum_questions_times();
                $timemodestring = get_string('question_time', 'mod_jqshow') . '<br>' .
                get_string('session_time_resume', 'mod_jqshow', userdate($totaltime, '%Mm %Ss'));
                break;
        }
        $data[] = [
            'iconconfig' => 'timelimit',
            'configname' => get_string('timemode', 'mod_jqshow'),
            'configvalue' => $timemodestring
        ];

        return $data;
    }

    /**
     * @param int $sid
     * @param int $cmid
     * @return array
     * @throws moodle_exception
     * @throws Exception
     */
    public static function get_session_results(int $sid, int $cmid): array {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $users = enrol_get_course_users($course->id, true);
        $session = jqshow_sessions::get_record(['id' => $sid]);
        $questions = (new questions($session->get('jqshowid'), $cmid, $sid))->get_list();
        $students = [];
        $context = context_module::instance($cmid);
        foreach ($users as $user) {
            if (!has_capability('mod/jqshow:startsession', $context, $user) &&
                info_module::is_user_visible($cm, $user->id, false)) {
                $answers = jqshow_questions_responses::get_session_responses_for_user(
                    $user->id, $session->get('id'), $session->get('jqshowid')
                );
                $correctanswers = 0;
                $incorrectanswers = 0;
                $partially = 0;
                $userpoints = 0;
                foreach ($answers as $answer) {
                    switch ($answer->get('result')) {
                        case questions::SUCCESS:
                            $correctanswers++;
                            ++$userpoints;
                            break;
                        case questions::FAILURE:
                            $incorrectanswers++;
                            break;
                        case questions::PARTIALLY:
                            $partially++;
                            $response = json_decode($answer->get('response'), false, 512, JSON_THROW_ON_ERROR);
                            switch ($response->type) {
                                case 'multichoice':
                                    $answerids = explode(',', $response->answerids);
                                    if (count($answerids) > 0) {
                                        foreach ($answerids as $answerid) {
                                            $answervalue = $DB->get_record(
                                                'question_answers', ['id' => (int)$answerid], 'fraction', MUST_EXIST
                                            );
                                            $userpoints += $answervalue->fraction;
                                        }
                                    }
                                    break;
                                default:
                                    throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                                        [], get_string('question_nosuitable', 'mod_jqshow'));
                            }
                            break;
                        default:
                            break;
                    }
                }
                $student = new stdClass();
                $student->id = $user->id;
                $student->userid = $user->id;
                $student->userfullname = $user->firstname . ' ' . $user->lastname;
                $student->correctanswers = $correctanswers;
                $student->incorrectanswers = $incorrectanswers;
                $student->partially = $partially;
                $student->notanswers = count($questions) - ($correctanswers + $incorrectanswers + $partially);
                // TODO develop the entire scoring system for different modes.
                $student->userpoints = round($userpoints * (1000 / count($questions)), 2);
                $students[] = $student;
            }
        }
        usort($students, static fn($a, $b) => $b->userpoints <=> $a->userpoints);
        $position = 0;
        foreach ($students as $student) {
            $student->userposition = ++$position;
        }
        return $students;
    }

    /**
     * @return Object
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public function export() : Object {
        $page = optional_param('page', 1, PARAM_INT);
        switch ($page) {
            case 1:
            default:
                return $this->export_form();
            case 2:
                return $this->export_session_questions();
            case 3:
                return $this->export_session_resume();
        }
    }

    /**
     * @param object $data
     * @return int
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public static function save_session(object $data): int {
        if (!empty($data->groupings)) {
            $values = array_values($data->groupings);
            $groupings = implode(',', $values);
            $data->groupings = $groupings;
        } else {
            $data->groupings = '';
        }
        $id = $data->sessionid ?? 0;
        $update = false;
        if (!empty($id)) {
            $update = true;
            $data->{'id'} = $id;
        }
        if (!isset($data->showfeedback)) {
            $data->showfeedback = 0;
        }
        if (!isset($data->automaticstart) || $data->automaticstart === 0) {
            $data->startdate = 0;
            $data->enddate = 0;
        }
        $session = new jqshow_sessions($id, $data);
        if ($update) {
            $session->update();
        } else {
            $persistent = $session->create();
            $id = $persistent->get('id');
        }
        return $id;
    }

    /**
     * @param $params
     * @return jqshow_sessions
     */
    protected function get_session($params): jqshow_sessions {
        return jqshow_sessions::get_record($params);
    }

    /**
     * @param int $sid
     * @param int $cmid
     * @param int $jqid
     * @return void
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_provisional_ranking(int $sid, int $cmid, int $jqid): array {
        global $PAGE;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $users = enrol_get_course_users($course->id, true);
        $session = jqshow_sessions::get_record(['id' => $sid]);
        $students = [];
        $context = context_module::instance($cmid);
        $PAGE->set_context($context);
        foreach ($users as $user) {
            if (!has_capability('mod/jqshow:startsession', $context, $user) &&
                info_module::is_user_visible($cm, $user->id, false)) {
                $userpicture = new user_picture($user);
                $userpicture->size = 1;
                $student = new stdClass();
                $student->userimageurl = $userpicture->get_url($PAGE)->out(false);
                $student->userfullname = $user->firstname . ' ' . $user->lastname;
                $answers = jqshow_questions_responses::get_session_responses_for_user(
                    $user->id, $session->get('id'), $session->get('jqshowid')
                );
                $student->questionscore = 0;
                $student->userpoints = 0;
                foreach ($answers as $answer) {
                    switch ($answer->get('result')) {
                        case questions::SUCCESS:
                            ++$student->userpoints;
                            if ($answer->get('jqid') === $jqid) {
                                $student->questionscore++; // TODO get points of answer.
                            }
                            break;
                        case questions::FAILURE:
                            // TODO rest points of answer.
                            break;
                        case questions::PARTIALLY:
                            // TODO rest points of answer.
                            $student->userpoints += 0.5;
                            if ($answer->get('jqid') === $jqid) {
                                $student->questionscore += 0.5; // TODO get points of answer.
                            }
                            break;
                        default:
                            break;
                    }
                }
                $students[] = $student;
            }
        }
        usort($students, static fn($a, $b) => $b->userpoints <=> $a->userpoints);
        $position = 0;
        foreach ($students as $student) {
            $student->userposition = ++$position;
        }
        return $students;
    }
}
