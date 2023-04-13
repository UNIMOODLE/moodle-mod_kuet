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
use core_php_time_limit;
use dml_exception;
use mod_jqshow\external\sessionquestions_external;
use mod_jqshow\forms\sessionform;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use pix_icon;
use qbank_managecategories\helper;
use stdClass;

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
     *
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
            0 => get_string('anonymiseresponses', 'mod_jqshow'),
            1 => get_string('anonymiseallresponses', 'mod_jqshow'),
            2 => get_string('noanonymiseresponses', 'mod_jqshow'),
        ];
        $advancemodechoices = [
            'manual' => 'Manual',
            'programmed' => 'Programado'
        ];
        $gamemodechoices = [
            'inactive' => 'Inactivo',
            'race' => 'Carrera',
            'podium' => 'Podio'
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
            'gamemode' => $gamemodechoices,
            'advancemode' => $advancemodechoices,
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
            'allowguests' => $session->get('allowguests'),
            'advancemode' => $session->get('advancemode'),
            'gamemode' => $session->get('gamemode'),
            'countdown' => $session->get('countdown'),
            'randomquestions' => $session->get('randomquestions'),
            'randomanswers' => $session->get('randomanswers'),
            'showfeedback' => $session->get('showfeedback'),
            'showfinalgrade' => $session->get('showfinalgrade'),
            'startdate' => $session->get('startdate'),
            'enddate' => $session->get('enddate'),
            'automaticstart' => $session->get('automaticstart'),
            'activetimelimit' => $session->get('activetimelimit'),
            'timelimit' => $session->get('timelimit')
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
     */
    public function export_session_resume(): Object {
        $data = new stdClass();
        $data->ispage3 = true;
        $data->name = 'export_session_resume';
        return $data;
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
        }
        $id = $data->sessionid ?? 0;
        $update = false;
        if (!empty($id)) {
            $update = true;
            $data->{'id'} = $id;
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
}
