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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * Sessions model
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet\models;

use cm_info;
use coding_exception;
use context_module;
use core\invalid_persistent_exception;
use core_availability\info_module;
use core_php_time_limit;
use dml_exception;
use Exception;
use invalid_parameter_exception;
use mod_kuet\api\grade;
use mod_kuet\api\groupmode;
use mod_kuet\external\getfinalranking_external;
use mod_kuet\external\sessionquestions_external;
use mod_kuet\external\sessionstatus_external;
use mod_kuet\forms\sessionform;
use mod_kuet\persistents\kuet;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use mod_kuet\persistents\kuet_user_progress;
use moodle_exception;
use moodle_url;
use pix_icon;
use qbank_managecategories\helper;
use stdClass;
use user_picture;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/question/editlib.php');

/**
 * Sessions model class
 */
class sessions {

    /** @var stdClass $kuet */
    protected stdClass $kuet;

    /** @var int cmid */
    protected int $cmid;

    /** @var kuet_sessions[] list */
    protected array $list;

    // Session modes.

    /**
     * @const string inactive manual
     */
     public const INACTIVE_MANUAL = 'inactive_manual';
    /**
     * @const string inactive programmed
     */
    public const INACTIVE_PROGRAMMED = 'inactive_programmed';
    /**
     * @const string podium manual
     */
    public const PODIUM_MANUAL = 'podium_manual';
    /**
     * @const string podium programmed
     */
    public const PODIUM_PROGRAMMED = 'podium_programmed';
    /**
     * @const string race manual
     */
    public const RACE_MANUAL = 'race_manual';

    /**
     * @const string race programmed
     */
    public const RACE_PROGRAMMED = 'race_programmed';

    // Anonymous response.
    /**
     * @const int not anonymous answed
     */
    public const ANONYMOUS_ANSWERS_NO = 0;
    /**
     * @const int anonymous answed
     */
    public const ANONYMOUS_ANSWERS = 1;

    // Time mode.
    /**
     * @const int no time
     */
    public const NO_TIME = 0;
    /**
     * @const int session time
     */
    public const SESSION_TIME = 1;
    /**
     * @const int question time
     */
    public const QUESTION_TIME = 2;

    // Grade methods.
    /**
     * @const int grade method disabled
     */
    public const GM_DISABLED = 0;

    // Status.
    /**
     * @const int session finished
     */
    public const SESSION_FINISHED = 0;
    /**
     * @const int session active
     */
    public const SESSION_ACTIVE = 1;
    /**
     * @const int session started
     */
    public const SESSION_STARTED = 2;
    /**
     * @const int session creating
     */
    public const SESSION_CREATING = 3;
    /**
     * @const int session error
     */
    public const SESSION_ERROR = 4;

    /**
     * sessions constructor.
     *
     * @param stdClass $kuet
     * @param int $cmid
     */
    public function __construct(stdClass $kuet, int $cmid) {
        $this->kuet = $kuet;
        $this->cmid = $cmid;
    }

    /**
     * Set sessions lisst
     *
     * @return void
     */
    public function set_list() : void {
        $this->list = kuet_sessions::get_records(['kuetid' => $this->kuet->id]);
    }

    /**
     * get sessions list
     *
     * @return kuet_sessions[]
     */
    public function get_list(): array {
        if (empty($this->list)) {
            $this->set_list();
        }
        return $this->list;
    }
    /**
     * Export form
     *
     * @return Object
     * @throws moodle_exception
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public function export_form(): Object {
        $sid = optional_param('sid', 0, PARAM_INT);    // Session id.
        $anonymousanswerchoices = [
            self::ANONYMOUS_ANSWERS_NO => get_string('noanonymiseresponses', 'mod_kuet'),
            self::ANONYMOUS_ANSWERS => get_string('anonymiseresponses', 'mod_kuet')
        ];
        if (get_config('kuet', 'sockettype') !== 'nosocket') {
            $sessionmodechoices = [
                self::INACTIVE_MANUAL => get_string('inactive_manual', 'mod_kuet'),
                self::INACTIVE_PROGRAMMED => get_string('inactive_programmed', 'mod_kuet'),
                self::PODIUM_MANUAL => get_string('podium_manual', 'mod_kuet'),
                self::PODIUM_PROGRAMMED => get_string('podium_programmed', 'mod_kuet'),
                self::RACE_MANUAL => get_string('race_manual', 'mod_kuet'),
                self::RACE_PROGRAMMED => get_string('race_programmed', 'mod_kuet'),
            ];
        } else {
            $sessionmodechoices = [
                self::INACTIVE_PROGRAMMED => get_string('inactive_programmed', 'mod_kuet'),
                self::PODIUM_PROGRAMMED => get_string('podium_programmed', 'mod_kuet'),
                self::RACE_PROGRAMMED => get_string('race_programmed', 'mod_kuet'),
            ];
        }
        $timemode = [
            self::NO_TIME => get_string('no_time', 'mod_kuet'),
            self::SESSION_TIME => get_string('session_time', 'mod_kuet'),
            self::QUESTION_TIME => get_string('question_time', 'mod_kuet'),
        ];
        $groupingsselect = [];
        $data = get_course_and_cm_from_cmid($this->cmid);
        /** @var  stdClass $course */
        $course = $data[0];
        /** @var cm_info $cm */
        $cm = $data[1];
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
            'kuetid' => $this->kuet->id,
            'sessionmodechoices' => $sessionmodechoices,
            'timemode' => $timemode,
            'anonymousanswerchoices' => $anonymousanswerchoices,
            'groupingsselect' => $groupingsselect,
            'groupingsselected' => $groupingsselect,
            'showsgrade' => $this->kuet->grademethod,
        ];

        $action = new moodle_url('/mod/kuet/sessions.php', ['cmid' => $this->cmid, 'sid' => $sid, 'page' => 1]);
        $mform = new sessionform($action->out(false), $customdata);

        if ($mform->is_cancelled()) {
            $url = new moodle_url('/mod/kuet/view.php', ['id' => $this->cmid]);
            redirect($url);
        } else if ($fromform = $mform->get_data()) {
            $sid = self::save_session($fromform);
            $url = new moodle_url('/mod/kuet/sessions.php', ['cmid' => $this->cmid, 'sid' => $sid,  'page' => 2]);
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
     * Get form data
     *
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
            'sgrade' => $session->get('sgrade'),
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
            'questiontime' => $session->get('questiontime'),
            'groupings' => $session->get('groupings'),
        ];
    }

    /**
     * Export session questions
     *
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
        $data->kuetid = $this->kuet->id;
        [$data->currentcategory, $data->questionbank_categories] = $this->get_questionbank_select();
        $course = $DB->get_record_sql("
                    SELECT c.*
                      FROM {course_modules} cm
                      JOIN {course} c ON c.id = cm.course
                     WHERE cm.id = ?", [$this->cmid], MUST_EXIST);
        $data->questionbank_url = (new moodle_url('/question/edit.php', ['courseid' => $course->id]))->out(false);
        $data->questions = $this->get_questions_for_category($data->currentcategory);
        $allquestions = (new questions($data->kuetid, $data->cmid, $data->sid))->get_list();
        $questiondata = [];
        foreach ($allquestions as $question) {
            $questiondata[] = sessionquestions_external::export_question($question, $this->cmid);
        }
        $data->sessionquestions = $questiondata;
        $data->resumeurl =
            (new moodle_url('/mod/kuet/sessions.php', ['cmid' => $data->cmid, 'sid' => $data->sid, 'page' => 3]))->out(false);
        $data->formurl =
            (new moodle_url('/mod/kuet/sessions.php', ['cmid' => $data->cmid, 'sid' => $data->sid, 'page' => 1]))->out(false);
        return $data;
    }

    /**
     * Get questions for a category
     *
     * @param string $category
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_questions_for_category(string $category): array {
        global $DB;
        core_php_time_limit::raise(300);
        $categories = [];
        $context = context_module::instance($this->cmid);
        $contexts = $context->get_parent_contexts();
        $contexts[$context->id] = $context;
        $pcontexts = [];
        foreach ($contexts as $context) {
            $pcontexts[] = $context->id;
        }
        $contextslist = implode(', ', $pcontexts);
        $categoriesofcontext = helper::get_categories_for_contexts($contextslist, 'parent, sortorder, name ASC', true);
        [$realcategory, $contextcategory] = explode(',', $category);
        foreach ($categoriesofcontext as $categoryobject) {
            if ((int)$realcategory === (int)$categoryobject->id ||
                ($categoryobject->parent === $realcategory && $categoryobject->contextid === $contextcategory)
            ) {
                $categories[] = $categoryobject->id . ',' . $categoryobject->contextid;
                foreach ($categoriesofcontext as $sencond) {
                    if ($sencond->parent === $categoryobject->id && $sencond->contextid === $contextcategory) {
                        $categories[] = $sencond->id . ',' . $sencond->contextid;
                    }
                }
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
                'title' => $question->qtype
            ]);
            $question->icon = $icon->export_for_pix();
            $question->issuitable = in_array($question->qtype, questions::TYPES, true);
            $question->questionpreview =
                (new moodle_url('/question/bank/previewquestion/preview.php', ['id' => $key]))->out(false);
            $question->questionedit =
                (new moodle_url('/question/bank/editquestion/question.php', ['id' => $key, 'cmid' => $this->cmid]))->out(false);
            $questions[$key] = (array)$question;
        }
        return array_values($questions);
    }

    /**
     * Select questions from question bank
     *
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
     * Expor session resume
     *
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
        $data->kuetid = $this->kuet->id;
        $data->config = self::get_session_config($data->sid, $data->cmid);
        $allquestions = (new questions($data->kuetid, $data->cmid, $data->sid))->get_list();
        $questiondata = [];
        foreach ($allquestions as $question) {
            $questiondata[] = sessionquestions_external::export_question($question, $this->cmid);
        }
        $data->sessionquestions = $questiondata;
        $data->addquestions =
            (new moodle_url('/mod/kuet/sessions.php', ['cmid' => $data->cmid, 'sid' => $data->sid, 'page' => 2]))->out(false);
        $data->sessionsurl =
            (new moodle_url('/mod/kuet/view.php', ['id' => $data->cmid]))->out(false);
        return $data;
    }

    /**
     * Get session configuration
     *
     * @param int $sid
     * @param int $cmid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_session_config(int $sid, int $cmid): array {
        $sessiondata = new kuet_sessions($sid);
        $data = [];
        $data[] = [
            'iconconfig' => 'name',
            'configname' => get_string('session_name', 'mod_kuet'),
            'configvalue' => $sessiondata->get('name')
        ];

        $data[] = [
            'iconconfig' => 'anonymise',
            'configname' => get_string('anonymousanswer', 'mod_kuet'),
            'configvalue' => $sessiondata->get('anonymousanswer') === 1 ? get_string('yes') : get_string('no')
        ];

        $data[] = [
            'iconconfig' => 'sessionmode',
            'configname' => get_string('sessionmode', 'mod_kuet'),
            'configvalue' => get_string($sessiondata->get('sessionmode'), 'mod_kuet')
        ];

        if ($sessiondata->is_group_mode()) {
            groupmode::check_all_users_in_groups($cmid, $sessiondata->get('groupings'));
            $names = groupmode::get_grouping_groups_name($sessiondata->get('groupings'));
            $data[] = [
                'iconconfig' => 'groups',
                'configname' => get_string('groupmode', 'mod_kuet'),
                'configvalue' => implode(',', $names)
            ];
        }

        $data[] = [
            'iconconfig' => 'countdown',
            'configname' => get_string('countdown', 'mod_kuet'),
            'configvalue' => $sessiondata->get('countdown') === 1 ? get_string('yes') : get_string('no')
        ];

        if (in_array($sessiondata->get('sessionmode'), [self::PODIUM_MANUAL, self::PODIUM_PROGRAMMED], true)) {
            $data[] = [
                'iconconfig' => 'showgraderanking',
                'configname' => get_string('showgraderanking', 'mod_kuet'),
                'configvalue' => $sessiondata->get('showgraderanking') === 1 ? get_string('yes') : get_string('no')
            ];
        }

        $data[] = [
            'iconconfig' => 'randomquestions',
            'configname' => get_string('randomquestions', 'mod_kuet'),
            'configvalue' => $sessiondata->get('randomquestions') === 1 ? get_string('yes') : get_string('no')
        ];

        $data[] = [
            'iconconfig' => 'randomanswers',
            'configname' => get_string('randomanswers', 'mod_kuet'),
            'configvalue' => $sessiondata->get('randomanswers') === 1 ? get_string('yes') : get_string('no')
        ];

        $data[] = [
            'iconconfig' => 'showfeedback',
            'configname' => get_string('showfeedback', 'mod_kuet'),
            'configvalue' => $sessiondata->get('showfeedback') === 1 ? get_string('yes') : get_string('no')
        ];

        $data[] = [
            'iconconfig' => 'showfinalgrade',
            'configname' => get_string('showfinalgrade', 'mod_kuet'),
            'configvalue' => $sessiondata->get('showfinalgrade') === 1 ? get_string('yes') : get_string('no')
        ];

        if ($sessiondata->get('startdate') !== 0) {
            $data[] = [
                'iconconfig' => 'startdate',
                'configname' => get_string('startdate', 'mod_kuet'),
                'configvalue' => userdate($sessiondata->get('startdate'), get_string('strftimedatetimeshort', 'core_langconfig'))
            ];
        }

        if ($sessiondata->get('enddate') !== 0) {
            $data[] = [
                'iconconfig' => 'enddate',
                'configname' => get_string('enddate', 'mod_kuet'),
                'configvalue' => userdate($sessiondata->get('enddate'), get_string('strftimedatetimeshort', 'core_langconfig'))
            ];
        }

        $data[] = [
            'iconconfig' => 'automaticstart',
            'configname' => get_string('automaticstart', 'mod_kuet'),
            'configvalue' => $sessiondata->get('automaticstart') === 1 ? get_string('yes') : get_string('no')
        ];

        switch ($sessiondata->get('timemode')) {
            case self::NO_TIME:
            default:
                $timemodestring = get_string('no_time', 'mod_kuet');
                break;
            case self::SESSION_TIME:
                $numquestion = kuet_questions::count_records(
                    ['sessionid' => $sessiondata->get('id'), 'kuetid' => $sessiondata->get('kuetid')]
                );
                $timemodestring = get_string('no_time', 'mod_kuet');
                if ($numquestion !== 0) {
                    $timeperquestion = round((int)$sessiondata->get('sessiontime') / $numquestion);
                    $timemodestring = get_string(
                            'session_time_resume', 'mod_kuet', userdate($sessiondata->get('sessiontime'), '%Mm %Ss')
                        ) . '<br>' .
                        get_string('question_time', 'mod_kuet') . ': ' .
                        $timeperquestion . 's';
                }
                break;
            case self::QUESTION_TIME:
                $totaltime =
                    (new questions($sessiondata->get('kuetid'), $cmid, $sessiondata->get('id')))->get_sum_questions_times();
                $timemodestring = get_string('question_time', 'mod_kuet') . '<br>' .
                get_string('session_time_resume', 'mod_kuet', userdate($totaltime, '%Mm %Ss'));
                break;
        }
        $data[] = [
            'iconconfig' => 'timelimit',
            'configname' => get_string('timemode', 'mod_kuet'),
            'configvalue' => $timemodestring
        ];

        return $data;
    }

    /**
     * Get session results
     *
     * @param int $sid
     * @param int $cmid
     * @return array
     * @throws moodle_exception
     * @throws Exception
     */
    public static function get_session_results(int $sid, int $cmid): array {
        global $PAGE;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $users = enrol_get_course_users($course->id, true);
        $session = kuet_sessions::get_record(['id' => $sid]);
        $questions = (new questions($session->get('kuetid'), $cmid, $sid))->get_list();
        $students = [];
        $context = context_module::instance($cmid);
        foreach ($users as $user) {
            if (!has_capability('mod/kuet:startsession', $context, $user) &&
                info_module::is_user_visible($cm, $user->id, false)) {
                $correctanswers = kuet_questions_responses::count_records(['kuet' => $session->get('kuetid'),
                    'session' => $sid, 'userid' => $user->id, 'result' => questions::SUCCESS]);
                $incorrectanswers = kuet_questions_responses::count_records(['kuet' => $session->get('kuetid'),
                    'session' => $sid, 'userid' => $user->id, 'result' => questions::FAILURE]);
                $partially = kuet_questions_responses::count_records(['kuet' => $session->get('kuetid'),
                    'session' => $sid, 'userid' => $user->id, 'result' => questions::PARTIALLY]);
                $userpoints = grade::get_session_grade($user->id, $session->get('id'), $session->get('kuetid'));
                $userpicture = new user_picture($user);
                $userpicture->size = 1;
                $student = new stdClass();
                $student->userimageurl = $userpicture->get_url($PAGE)->out(false);
                $student->id = $user->id;
                $student->userid = $user->id;
                $student->userfullname = $user->firstname . ' ' . $user->lastname;
                $student->userprofileurl =
                    (new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $course->id]))->out(false);
                if ($session->get('anonymousanswer')) {
                    $student->userfullname = '**********';
                    $student->userprofileurl = '';
                }
                $student->correctanswers = $correctanswers;
                $student->incorrectanswers = $incorrectanswers;
                $student->partially = $partially;
                $student->notanswers = count($questions) - ($correctanswers + $incorrectanswers + $partially);
                $student->userpoints = grade::get_rounded_mark($userpoints);
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
     * Get session group results
     *
     * @param int $sid
     * @param int $cmid
     * @return array
     * @throws moodle_exception
     * @throws Exception
     */
    public static function get_group_session_results(int $sid, int $cmid): array {

        $session = kuet_sessions::get_record(['id' => $sid]);
        $groupings = $session->get('groupings');
        if ($groupings === false || $groupings === null) {
            return [];
        }
        $groups = groupmode::get_grouping_groups($groupings);
        $questions = (new questions($session->get('kuetid'), $cmid, $sid))->get_list();
        $sessiongroups = [];
        foreach ($groups as $group) {
                $gmembers = groups_get_members($group->id, 'u.id');
                $groupmember = reset($gmembers);
                $correctanswers = kuet_questions_responses::count_records(['kuet' => $session->get('kuetid'),
                    'session' => $sid, 'userid' => $groupmember->id, 'result' => questions::SUCCESS]);
                $incorrectanswers = kuet_questions_responses::count_records(['kuet' => $session->get('kuetid'),
                    'session' => $sid, 'userid' => $groupmember->id, 'result' => questions::FAILURE]);
                $partially = kuet_questions_responses::count_records(['kuet' => $session->get('kuetid'),
                    'session' => $sid, 'userid' => $groupmember->id, 'result' => questions::PARTIALLY]);
                $userpoints = grade::get_session_grade($groupmember->id, $session->get('id'), $session->get('kuetid'));
                $sessiongroup = new stdClass();
                $sessiongroup->id = $group->id;
                $sessiongroup->groupname = $group->name;
                $sessiongroup->groupimageurl = groupmode::get_group_image($group, $sid);
                if ($session->get('anonymousanswer')) {
                    $sessiongroup->groupname = '**********';
                    $sessiongroup->groupimageurl = '';
                }
                $sessiongroup->correctanswers = $correctanswers;
                $sessiongroup->incorrectanswers = $incorrectanswers;
                $sessiongroup->partially = $partially;
                $sessiongroup->notanswers = count($questions) - ($correctanswers + $incorrectanswers + $partially);
                $sessiongroup->grouppoints = grade::get_rounded_mark($userpoints);
                $sessiongroups[] = $sessiongroup;
        }
        usort($sessiongroups, static fn($a, $b) => $b->grouppoints <=> $a->grouppoints);
        $position = 0;
        foreach ($sessiongroups as $sessiongroup) {
            $sessiongroup->groupposition = ++$position;
        }
        return $sessiongroups;
    }

    /**
     * Breakdown responses for race mode
     *
     * @param array $userresults
     * @param int $sid
     * @param int $cmid
     * @param int $kuetid
     * @return array
     * @throws coding_exception
     */
    public static function breakdown_responses_for_race(array $userresults, int $sid, int $cmid, int $kuetid): array {
        $questions = (new questions($kuetid, $cmid, $sid))->get_list();
        $questionsdata = [];
        foreach ($questions as $key => $question) {
            $questionsdata[$key] = new stdClass();
            $questionsdata[$key]->questionnum = $key + 1;
            $questionsdata[$key]->studentsresponse = [];
            foreach ($userresults as $user) {
                $userresponse = kuet_questions_responses::get_question_response_for_user($user->id, $sid, $question->get('id'));
                $studentresponse = new stdClass();
                $studentresponse->userid = $user->id;
                if ($userresponse !== false) {
                    $studentresponse->response = $userresponse;
                    switch ($userresponse->get('result')) {
                        case questions::FAILURE:
                            $studentresponse->responseclass = 'fail';
                            $studentresponse->responsetext = get_string('incorrect', 'mod_kuet');
                            break;
                        case questions::SUCCESS:
                            $studentresponse->responseclass = 'success';
                            $studentresponse->responsetext = get_string('correct', 'mod_kuet');
                            break;
                        case questions::PARTIALLY:
                            $studentresponse->responseclass = 'partially';
                            $studentresponse->responsetext = get_string('partially_correct', 'mod_kuet');
                            break;
                        case questions::NORESPONSE:
                        default:
                            $studentresponse->responseclass = 'noresponse';
                            $studentresponse->responsetext = get_string('noresponse', 'mod_kuet');
                            break;
                        case questions::NOTEVALUABLE:
                            $studentresponse->responseclass = 'noevaluable';
                            $studentresponse->responsetext = get_string('noevaluable', 'mod_kuet');
                            break;
                        case questions::INVALID:
                            $studentresponse->responseclass = 'invalid';
                            $studentresponse->responsetext = get_string('invalid', 'mod_kuet');
                            break;
                    }
                } else {
                    $studentresponse->responseclass = 'noresponse';
                }
                $questionsdata[$key]->studentsresponse[] = $studentresponse;
            }
        }

        return array_values($questionsdata);
    }

    /**
     * Breakdown responses for race group mode
     *
     * @param array $groupresults
     * @param int $sid
     * @param int $cmid
     * @param int $kuetid
     * @return array
     * @throws coding_exception
     */
    public static function breakdown_responses_for_race_groups(array $groupresults, int $sid, int $cmid, int $kuetid): array {
        $questions = (new questions($kuetid, $cmid, $sid))->get_list();
        $questionsdata = [];
        foreach ($questions as $key => $question) {
            $questionsdata[$key] = new stdClass();
            $questionsdata[$key]->questionnum = $key + 1;
            $questionsdata[$key]->studentsresponse = [];
            foreach ($groupresults as $groupresult) {
                $members = groupmode::get_group_members($groupresult->id);
                $member = reset($members);
                $userresponse = kuet_questions_responses::get_question_response_for_user($member->id, $sid, $question->get('id'));
                $questionsdata[$key]->studentsresponse[$member->id] = new stdClass();
                $questionsdata[$key]->studentsresponse[$member->id]->userid = $member->id;
                if ($userresponse !== false) {
                    $questionsdata[$key]->studentsresponse[$member->id]->response = $userresponse;
                    switch ($userresponse->get('result')) {
                        case questions::FAILURE:
                            $questionsdata[$key]->studentsresponse[$member->id]->responseclass = 'fail';
                            break;
                        case questions::SUCCESS:
                            $questionsdata[$key]->studentsresponse[$member->id]->responseclass = 'success';
                            break;
                        case questions::PARTIALLY:
                            $questionsdata[$key]->studentsresponse[$member->id]->responseclass = 'partially';
                            break;
                        case questions::NORESPONSE:
                        default:
                            $questionsdata[$key]->studentsresponse[$member->id]->responseclass = 'noresponse';
                            break;
                        case questions::NOTEVALUABLE:
                            $questionsdata[$key]->studentsresponse[$member->id]->responseclass = 'noevaluable';
                            break;
                        case questions::INVALID:
                            $questionsdata[$key]->studentsresponse[$member->id]->responseclass = 'invalid';
                            break;
                    }
                } else {
                    $questionsdata[$key]->studentsresponse[$member->id]->responseclass = 'noresponse';
                }
                $questionsdata[$key]->studentsresponse = array_values($questionsdata[$key]->studentsresponse);
            }
        }
        return array_values($questionsdata);
    }

    /**
     * Export object
     *
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
     * Save session
     *
     * @param object $data
     * @return int
     * @throws coding_exception
     * @throws invalid_persistent_exception
     */
    public static function save_session(object $data): int {
        $id = $data->sessionid ?? 0;
        $update = false;
        if (!empty($id)) {
            $update = true;
            $data->{'id'} = $id;
        }
        if (!isset($data->sgrade)) {
            $data->sgrade = 0;
        }
        if (!isset($data->countdown)) {
            $data->countdown = 0;
        }
        if (!isset($data->showfeedback)) {
            $data->showfeedback = 0;
        }
        if (!isset($data->showgraderanking)) {
            $data->showgraderanking = 0;
        }
        if (!isset($data->showfinalgrade)) {
            $data->showfinalgrade = 0;
        }
        if (!isset($data->showfinalgrade)) {
            $data->showfinalgrade = 0;
        }
        if (!isset($data->randomquestions)) {
            $data->randomquestions = 0;
        }
        if (!isset($data->randomanswers)) {
            $data->randomanswers = 0;
        }
        if (!isset($data->automaticstart) || $data->automaticstart === 0) {
            $data->startdate = 0;
            $data->enddate = 0;
        }
        $session = new kuet_sessions($id, $data);
        if ($update) {
            $session->update();
        } else {
            $persistent = $session->create();
            $id = $persistent->get('id');
        }
        return $id;
    }

    /**
     * Get session
     *
     * @param $params
     * @return kuet_sessions
     */
    protected function get_session($params): kuet_sessions {
        return kuet_sessions::get_record($params);
    }

    /**
     * Get provisional ranking
     *
     * @param int $sid
     * @param int $cmid
     * @param int $kid
     * @return void
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_provisional_ranking(int $sid, int $cmid, int $kid): array {
        global $PAGE;

        $context = context_module::instance($cmid);
        $PAGE->set_context($context);
        $session = kuet_sessions::get_record(['id' => $sid]);

        if ($session->is_group_mode()) {
            $students = self::get_provisional_ranking_group($session, $kid);
        } else {
            $students = self::get_provisional_ranking_individual($session, $cmid, $kid, $context);
        }
        return $students;
    }

    /**
     * Get individual provisional ranking
     *
     * @param kuet_sessions $session
     * @param int $cmid
     * @param int $kid
     * @param context_module $context
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_provisional_ranking_individual(kuet_sessions $session, int $cmid, int $kid,
                                                              context_module $context): array {
        global $PAGE;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $users = enrol_get_course_users($course->id, true);
        $students = [];
        $sid = $session->get('id');
        foreach ($users as $user) {
            if (!has_capability('mod/kuet:startsession', $context, $user) &&
                info_module::is_user_visible($cm, $user->id, false)) {
                $userpicture = new user_picture($user);
                $userpicture->size = 1;
                $student = new stdClass();
                $student->userimageurl = $userpicture->get_url($PAGE)->out(false);
                $student->userfullname = $user->firstname . ' ' . $user->lastname;
                if ($session->get('anonymousanswer')) {
                    $student->userimageurl = '';
                    $student->userfullname = '**********';
                }
                $userpoints = grade::get_session_grade($user->id, $sid, $session->get('kuetid'));
                $kresponse = kuet_questions_responses::get_record(['kid' => $kid,
                    'kuet' => $session->get('kuetid'), 'session' => $sid, 'userid' => (int) $user->id]);
                $qpoints = (!$kresponse) ? 0 : grade::get_simple_mark($kresponse);
                $student->userpoints = grade::get_rounded_mark($userpoints);
                $student->questionscore = grade::get_rounded_mark($qpoints);
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
     * Get group provisional ranking
     *
     * @param kuet_sessions $session
     * @param int $kid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_provisional_ranking_group(kuet_sessions $session, int $kid): array {

        $groups = groupmode::get_grouping_groups($session->get('groupings'));
        $students = [];
        $sid = $session->get('id');
        foreach ($groups as $group) {
            $gmembers = groups_get_members($group->id, 'u.id');
            $groupmember = reset($gmembers);
            $groupimage = groupmode::get_group_image($group, $session->get('id'));
            $student = new stdClass();
            $student->userimageurl = $groupimage;
            $student->userfullname = $group->name;
            if ($session->get('anonymousanswer')) {
                $student->userimageurl = '';
                $student->userfullname = '**********';
            }
            $userpoints = grade::get_session_grade($groupmember->id, $sid, $session->get('kuetid'));
            $kresponse = kuet_questions_responses::get_record(['kid' => $kid,
                'kuet' => $session->get('kuetid'), 'session' => $sid, 'userid' => (int) $groupmember->id]);
            $qpoints = (!$kresponse) ? 0 : grade::get_simple_mark($kresponse);
            $student->userpoints = grade::get_rounded_mark($userpoints);
            $student->questionscore = grade::get_rounded_mark($qpoints);
            $students[] = $student;
        }
        usort($students, static fn($a, $b) => $b->userpoints <=> $a->userpoints);
        $position = 0;
        foreach ($students as $student) {
            $student->userposition = ++$position;
        }
        return $students;
    }

    /**
     * Get final ranking
     *
     * @param int $sid
     * @param int $cmid
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_final_ranking(int $sid, int $cmid): array {
        global $PAGE;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $session = kuet_sessions::get_record(['id' => $sid]);
        $context = context_module::instance($cmid);
        $PAGE->set_context($context);

        if ($session->is_group_mode()) {
            $data = self::get_final_group_ranking($session);
        } else {
            $data = self::get_final_individual_ranking($session, $cm, $course->id, $context);
        }
        return $data;
    }

    /**
     * Get individual final ranking
     *
     * @param kuet_sessions $session
     * @param cm_info $cm
     * @param int $courseid
     * @param context_module $context
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private static function get_final_individual_ranking(kuet_sessions $session, cm_info $cm, int $courseid,
                                                         context_module $context): array {
        global $PAGE;
        $users = enrol_get_course_users($courseid, true);
        $students = [];
        foreach ($users as $user) {
            if (!has_capability('mod/kuet:startsession', $context, $user) &&
                info_module::is_user_visible($cm, $user->id, false)) {
                $userpicture = new user_picture($user);
                $userpicture->size = 200;
                $student = new stdClass();
                $student->userimageurl = $userpicture->get_url($PAGE)->out(false);
                $student->userfullname = $user->firstname . ' ' . $user->lastname;
                if ($session->get('anonymousanswer')) {
                    $student->userimageurl = '';
                    $student->userfullname = '**********';
                }
                $userpoints = grade::get_session_grade($user->id, $session->get('id'), $session->get('kuetid'));
                $student->userpoints = grade::get_rounded_mark($userpoints);
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
     * Get group final ranking
     *
     * @param kuet_sessions $session
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private static function get_final_group_ranking(kuet_sessions $session): array {
        $groups = groupmode::get_grouping_groups($session->get('groupings'));
        $data = [];
        foreach ($groups as $group) {
            $gmembers = groups_get_members($group->id, 'u.id');
            $groupmember = reset($gmembers);
            $groupimage = groupmode::get_group_image($group, $session->get('id'));
            $sessiongroup = new stdClass();
            $sessiongroup->userimageurl = $groupimage;
            $sessiongroup->userfullname = $group->name;
            if ($session->get('anonymousanswer')) {
                $sessiongroup->userimageurl = '';
                $sessiongroup->userfullname = '**********';
            }
            $userpoints = grade::get_session_grade($groupmember->id, $session->get('id'), $session->get('kuetid'));
            $sessiongroup->userpoints = grade::get_rounded_mark($userpoints);
            $data[] = $sessiongroup;
        }
        usort($data, static fn($a, $b) => $b->userpoints <=> $a->userpoints);
        $position = 0;
        foreach ($data as $student) {
            $student->userposition = ++$position;
        }
        return $data;
    }

    /**
     * Export end session
     *
     * @param int $cmid
     * @param int $sessionid
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function export_endsession(int $cmid, int $sessionid): object {
        global $USER;
        $session = new kuet_sessions($sessionid);
        $contextmodule = context_module::instance($cmid);
        $kuet = new kuet($session->get('kuetid'));
        $data = new stdClass();
        $data->cmid = $cmid;
        $data->sessionid = $sessionid;
        $data->kuetid = $session->get('kuetid');
        $data->courselink = (new moodle_url('/course/view.php', ['id' => $kuet->get('course')]))->out(false);
        $params = ['cmid' => $cmid, 'sid' => $sessionid];
        if (!$session->is_group_mode() && !has_capability('mod/kuet:startsession', $contextmodule, $USER->id)) {
            $params['userid'] = $USER->id;
        } else if ($session->is_group_mode() && !has_capability('mod/kuet:startsession', $contextmodule, $USER->id)) {
            $group = groupmode::get_user_group($USER->id, $session);
            if (isset($group->id)) {
                $params['groupid'] = $group->id;
            }
        }
        $data->reportlink = (new moodle_url('/mod/kuet/reports.php', $params))->out(false);
        switch ($session->get('sessionmode')) {
            case self::INACTIVE_PROGRAMMED:
            case self::INACTIVE_MANUAL:
                $data = self::get_normal_endsession($data);
                break;
            case self::PODIUM_PROGRAMMED:
            case self::PODIUM_MANUAL:
            case self::RACE_MANUAL:
            case self::RACE_PROGRAMMED:
                if ((int)$session->get('showfinalgrade') === 0) {
                    $data = self::get_normal_endsession($data);
                } else {
                    $data = (object)getfinalranking_external::getfinalranking($sessionid, $cmid);
                    $data = self::get_normal_endsession($data);
                    $data->endsession = true;
                    $data->ranking = true;
                    $data->isteacher = has_capability('mod/kuet:startsession', $contextmodule);
                }
                break;
            default:
                throw new moodle_exception('incorrect_sessionmode', 'mod_kuet', '',
                    [], get_string('incorrect_sessionmode', 'mod_kuet'));
        }
        return $data;
    }

    /**
     * Get normal end session
     *
     * @param stdClass $data
     * @return stdClass
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    private static function get_normal_endsession(stdClass $data): stdClass {
        global $OUTPUT;
        $data->questionid = 0;
        $data->kid = 0;
        $data->question_index_string = '';
        $data->endsessionimage = $OUTPUT->image_url('f/end_session', 'mod_kuet')->out(false);
        $data->qtype = 'endsession';
        $data->endsession = true;
        return $data;
    }

    /**
     * Set session error status
     *
     * @param kuet_sessions $sessions
     * @param string $errorcode
     * @return mixed
     * @throws invalid_parameter_exception
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function set_session_status_error(kuet_sessions  $sessions, string $errorcode) {
        // Change status.
        sessionstatus_external::sessionstatus($sessions->get('id'), self::SESSION_ERROR);
        // Remove all the answers of this session.
        $kquestions = kuet_questions::get_records(['sessionid' => $sessions->get('id')]);
        foreach ($kquestions as $kquestion) {
            kuet_questions_responses::delete_question_responses($sessions->get('kuetid'), $sessions->get('id'), $kquestion->get('id'));
        }
        kuet_user_progress::delete_session_user_progress($sessions->get('id'));
        $kuetinfo = get_course_and_cm_from_instance($sessions->get('kuetid'), 'kuet');
        $course = $kuetinfo[0];
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        throw new moodle_exception($errorcode, 'mod_kuet', $url->out(false));
    }
}
