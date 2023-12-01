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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos

/**
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\helpers;

use coding_exception;
use context_module;
use dml_exception;
use dml_transaction_exception;
use JsonException;
use mod_kuet\api\grade;
use mod_kuet\api\groupmode;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use moodle_url;
use pix_icon;
use question_bank;
use stdClass;
use user_picture;

class reports {

    public const QUESTION_REPORT = 'questionreport';
    public const SESSION_QUESTIONS_REPORT = 'sessionquestionsreport';
    public const SESSION_RANKING_REPORT = 'sessionrankingreport';
    public const GROUP_SESSION_RANKING_REPORT = 'groupsessionrankingreport';
    public const USER_REPORT = 'userreport';
    public const GROUP_QUESTION_REPORT = 'groupquestionreport';
    public const GROUP_REPORT = 'groupreport';
    /**
     * @param int $kuetid
     * @param int $cmid
     * @param int $sid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_questions_data_for_teacher_report(int $kuetid, int $cmid, int $sid): array {
        $session = new kuet_sessions($sid);
        $questions = (new questions($kuetid, $cmid, $sid))->get_list();
        $questionsdata = [];

        foreach ($questions as $question) {
            if ($session->is_group_mode()) {
                $data = self::get_questions_data_for_teacher_report_groups($question, $kuetid, $cmid, $session);
            } else {
                $data = self::get_questions_data_for_teacher_report_individual($question, $kuetid, $cmid, $session);
            }

            $questionsdata[] = $data;
        }
        return $questionsdata;
    }

    /**
     * @param kuet_questions $question
     * @param int $kuetid
     * @param int $cmid
     * @param kuet_sessions $session
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_questions_data_for_teacher_report_groups(
        kuet_questions $question, int $kuetid, int $cmid, kuet_sessions $session) : stdClass {
        global $DB;
        $groupmembers = groupmode::get_one_member_of_each_grouping_group($session->get('groupings'));
        $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
        $data = new stdClass();
        $data->questionnid = $question->get('id');
        $data->position = $question->get('qorder');
        $data->name = $questiondb->name;
        $data->type = $question->get('qtype');
        $icon = new pix_icon('icon', '', 'qtype_' . $question->get('qtype'), [
            'class' => 'icon',
            'title' => $question->get('qtype')
        ]);
        $data->icon = $icon->export_for_pix();
        $data->success = 0;
        $data->failures = 0;
        $data->partyally = 0;
        foreach ($groupmembers as $groupmember) {
            $data->success += kuet_questions_responses::count_records([
                'kuet' => $kuetid,
                'session' => $session->get('id'),
                'kid' => $question->get('id'),
                'result' => questions::SUCCESS,
                'userid' => $groupmember
            ]);
            $data->failures += kuet_questions_responses::count_records([
                'kuet' => $kuetid,
                'session' => $session->get('id'),
                'kid' => $question->get('id'),
                'result' => questions::FAILURE,
                'userid' => $groupmember
            ]);
            $data->partyally += kuet_questions_responses::count_records([
                'kuet' => $kuetid,
                'session' => $session->get('id'),
                'kid' => $question->get('id'),
                'result' => questions::PARTIALLY,
                'userid' => $groupmember
            ]);
        }

        $data->noresponse = count($groupmembers) - ($data->success + $data->failures + $data->partyally);
        $data->time = self::get_time_string($session, $question);
        /** @var questions $type */
        $type = questions::get_question_class_by_string_type($question->get('qtype'));
        if ($type::is_evaluable()) {
            $data->isevaluable = true;
        }
        $data->questionreporturl = (new moodle_url('/mod/kuet/reports.php',
            ['cmid' => $cmid, 'sid' => $session->get('id'), 'kid' => $question->get('id')]
        ))->out(false);
        return $data;
    }

    /**
     * @param kuet_questions $question
     * @param int $kuetid
     * @param int $cmid
     * @param kuet_sessions $session
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_questions_data_for_teacher_report_individual(
        kuet_questions $question, int $kuetid, int $cmid, kuet_sessions $session
    ) : stdClass {
        global $DB;
        $kuet = new kuet($kuetid);
        $users = enrol_get_course_users($kuet->get('course'), true);
        $cmcontext = context_module::instance($cmid);
        foreach ($users as $key => $user) {
            if (has_capability('mod/kuet:startsession', $cmcontext, $user)) {
                unset($users[$key]);
            }
        }
        $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
        $data = new stdClass();
        $data->questionnid = $question->get('id');
        $data->position = $question->get('qorder');
        $data->name = $questiondb->name;
        $data->type = $question->get('qtype');
        $icon = new pix_icon('icon', '', 'qtype_' . $question->get('qtype'), [
            'class' => 'icon',
            'title' => $question->get('qtype')
        ]);
        $data->icon = $icon->export_for_pix();
        $data->success = kuet_questions_responses::count_records([
            'kuet' => $kuetid,
            'session' => $session->get('id'),
            'kid' => $question->get('id'),
            'result' => questions::SUCCESS
        ]);
        $data->failures = kuet_questions_responses::count_records([
            'kuet' => $kuetid,
            'session' => $session->get('id'),
            'kid' => $question->get('id'),
            'result' => questions::FAILURE
        ]);
        $data->partyally = kuet_questions_responses::count_records([
            'kuet' => $kuetid,
            'session' => $session->get('id'),
            'kid' => $question->get('id'),
            'result' => questions::PARTIALLY
        ]);
        $data->noresponse = count($users) - ($data->success + $data->failures + $data->partyally);
        $data->time = self::get_time_string($session, $question);
        /** @var questions $type */
        $type = questions::get_question_class_by_string_type($question->get('qtype'));
        if ($type::is_evaluable()) {
            $data->isevaluable = true;
        }
        $data->questionreporturl = (new moodle_url('/mod/kuet/reports.php',
            ['cmid' => $cmid, 'sid' => $session->get('id'), 'kid' => $question->get('id')]
        ))->out(false);
        return $data;
    }
    /**
     * @param int $cmid
     * @param int $sid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_ranking_for_teacher_report(int $cmid, int $sid): array {
        $session = new kuet_sessions($sid);
        if ($session->is_group_mode()) {
            $results = self::get_groups_ranking_for_teacher_report($cmid, $sid);
        } else {
            $results = self::get_individual_ranking_for_teacher_report($cmid, $sid);
        }
        return $results;
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_individual_ranking_for_teacher_report(int $cmid, int $sid): array {
        global $DB, $USER;

        $session = new kuet_sessions($sid);
        $cmcontext = context_module::instance($cmid);
        $results = sessions::get_session_results($sid, $cmid);
        foreach ($results as $user) {
            $userdata = $DB->get_record('user', ['id' => $user->userid]);
            if ($userdata !== false) {
                $user = self::add_userdata($userdata, $user, $user->userid, 200);
                $user->viewreporturl = (new moodle_url('/mod/kuet/reports.php',
                    ['cmid' => $cmid, 'sid' => $sid, 'userid' => $user->userid]))->out(false);
                if ($session->get('anonymousanswer') === 1
                    && !has_capability('mod/kuet:viewanonymousanswers', $cmcontext, $USER)) {
                    unset($user->viewreporturl);
                }
            }
        }
        return $results;
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_groups_ranking_for_teacher_report(int $cmid, int $sid): array {

        global $USER;
        $results = sessions::get_group_session_results($sid, $cmid);
        $session = new kuet_sessions($sid);
        $cmcontext = context_module::instance($cmid);
        foreach ($results as $group) {
            $group->sid = $sid;
            $groupdata = groups_get_group($group->id);
            $group = self::add_groupdata($groupdata, $group, 200);
            $group->viewreporturl = (new moodle_url('/mod/kuet/reports.php',
                    ['cmid' => $cmid, 'sid' => $sid, 'groupid' => $group->id]))->out(false);
            if ($session->get('anonymousanswer') === 1
                && !has_capability('mod/kuet:viewanonymousanswers', $cmcontext, $USER)) {
                unset($group->viewreporturl);
            }
        }
        return $results;
    }

    /**
     * @param int $kuetid
     * @param int $cmid
     * @param int $sid
     * @param int $userid
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_questions_data_for_user_report(int $kuetid, int $cmid, int $sid, int $userid): array {
        global $DB;
        $session = new kuet_sessions($sid);
        $questions = (new questions($kuetid, $cmid, $sid))->get_list();
        $questionsdata = [];
        foreach ($questions as $question) {
            $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
            $response = kuet_questions_responses::get_record([
                'kuet' => $kuetid,
                'session' => $sid,
                'kid' => $question->get('id'),
                'userid' => $userid,
            ]);
            $data = new stdClass();
            $data->questionnid = $question->get('id');
            $data->position = $question->get('qorder');
            $data->name = $questiondb->name;
            $data->type = $question->get('qtype');
            $icon = new pix_icon('icon', '', 'qtype_' . $question->get('qtype'), [
                'class' => 'icon',
                'title' => $question->get('qtype')
            ]);
            $data->icon = $icon->export_for_pix();
            $questiontimestr = self::get_time_string($session, $question);
            if ($response === false) {
                $data->response = 'noresponse';
                $data->responsestr = get_string('noresponse', 'mod_kuet');
                $data->time = $questiontimestr . ' / ' . $questiontimestr; // Or 0?
            } else {
                $data->response = grade::get_result_mark_type($response);
                $data->responsestr = get_string($data->response, 'mod_kuet');
                $data->time = self::get_user_time_in_question($session, $question, $response);
                /** @var questions $type */
                $type = questions::get_question_class_by_string_type($question->get('qtype'));
                $data->score = round($type::get_simple_mark(json_decode(base64_decode($response->get('response'))), $response), 2);
            }
            $data->cmid = $cmid;
            $data->sessionid = $sid;
            $data->userid = $userid;
            $questionsdata[] = $data;
        }
        return $questionsdata;
    }

    /**
     * @param kuet_sessions $session
     * @param kuet_questions $question
     * @param kuet_questions_responses $response
     * @return string
     * @throws JsonException
     * @throws coding_exception
     */
    public static function get_user_time_in_question(
        kuet_sessions $session, kuet_questions $question, kuet_questions_responses $response
    ): string {
        $responsedata = json_decode(base64_decode($response->get('response')), false);
        $usertimelast = $responsedata->timeleft;
        switch ($session->get('timemode')) {
            case sessions::NO_TIME:
            default:
                $timestring = '-';
                break;
            case sessions::SESSION_TIME:
                $numquestion = kuet_questions::count_records(
                    ['sessionid' => $session->get('id'), 'kuetid' => $session->get('kuetid')]
                );
                $questiontime = round((int)$session->get('sessiontime') / $numquestion);
                $usertime = ($questiontime - $usertimelast) !== 0 ? ($questiontime - $usertimelast) : 1;
                $timestring = $usertime . 's / ' . $questiontime . 's';
                break;
            case sessions::QUESTION_TIME:
                $questiontime = ($question->get('timelimit') > 0) ? $question->get('timelimit') : $session->get('questiontime');
                $usertime = ($questiontime - $usertimelast) !== 0 ? ($questiontime - $usertimelast) : 1;
                $timestring = $usertime . 's / ' . $questiontime . 's';
                break;
        }
        return $timestring;
    }

    /**
     * @param kuet_sessions $session
     * @param kuet_questions $question
     * @return string
     * @throws coding_exception
     */
    public static function get_time_string(kuet_sessions $session, kuet_questions $question): string {
        switch ($session->get('timemode')) {
            case sessions::NO_TIME:
            default:
                return ($question->get('timelimit') > 0) ? $question->get('timelimit') . 's' : '-';
            case sessions::SESSION_TIME:
                $numquestion = kuet_questions::count_records(
                    ['sessionid' => $session->get('id'), 'kuetid' => $session->get('kuetid')]
                );
                $timeperquestion = round((int)$session->get('sessiontime') / $numquestion);
                return ($timeperquestion > 0) ? $timeperquestion . 's' : '-';
            case sessions::QUESTION_TIME:
                return ($question->get('timelimit') > 0) ? $question->get('timelimit') . 's' : $session->get('questiontime') . 's';
        }
    }

    /**
     * @param int $kuetid
     * @param int $cmid
     * @param int $sid
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_session_report(int $kuetid, int $cmid, int $sid): stdClass {

        $data = new stdClass();
        $data->kuetid = $kuetid;
        $data->cmid = $cmid;
        $data->sessionreport = true;
        $session = new kuet_sessions($sid);
        $mode = $session->get('sessionmode');
        $data->sessionname = $session->get('name');
        $data->config = sessions::get_session_config($sid, $cmid);
        $data->sessionquestions = self::get_questions_data_for_teacher_report($kuetid, $cmid, $sid);
        $rankingusers = $session->is_group_mode() ? 'rankinggroups' : 'rankingusers';
        $data->hasranking = true;
        $data->$rankingusers = self::get_ranking_for_teacher_report($cmid, $sid);

        if ($mode !== sessions::INACTIVE_PROGRAMMED && $mode !== sessions::INACTIVE_MANUAL) {
            $data->showfinalranking = true;
            if ($session->is_group_mode()) {
                $data->firstuserimageurl = $data->rankinggroups[0]->groupimage;
                $data->firstuserfullname = $data->rankinggroups[0]->groupname;
                $data->firstuserpoints = $data->rankinggroups[0]->grouppoints;
                $data->seconduserimageurl = $data->rankinggroups[1]->groupimage;
                $data->seconduserfullname = $data->rankinggroups[1]->groupname;
                $data->seconduserpoints = $data->rankinggroups[1]->grouppoints;
                $data->thirduserimageurl = $data->rankinggroups[2]->groupimage;
                $data->thirduserfullname = $data->rankinggroups[2]->groupname;
                $data->thirduserpoints = $data->rankinggroups[2]->grouppoints;
            } else {
                $data->firstuserimageurl = $data->rankingusers[0]->userimage;
                $data->firstuserfullname = $data->rankingusers[0]->userfullname;
                $data->firstuserpoints = $data->rankingusers[0]->userpoints;
                $data->seconduserimageurl = $data->rankingusers[1]->userimage;
                $data->seconduserfullname = $data->rankingusers[1]->userfullname;
                $data->seconduserpoints = $data->rankingusers[1]->userpoints;
                $data->thirduserimageurl = $data->rankingusers[2]->userimage;
                $data->thirduserfullname = $data->rankingusers[2]->userfullname;
                $data->thirduserpoints = $data->rankingusers[2]->userpoints;
            }
        }
        $params =  ['cmid' => $cmid, 'sid' => $sid, 'name' => self::SESSION_QUESTIONS_REPORT];
        $data->downloadsessionquestionreport = self::get_downloadhtml($params);
        if ($session->is_group_mode()) {
            $params['name'] =  self::GROUP_SESSION_RANKING_REPORT;
            $data->downloadsessionrankingreport = self::get_downloadhtml($params);
        } else {
            $params['name'] =  self::SESSION_RANKING_REPORT;
        }
        $data->downloadsessionrankingreport = self::get_downloadhtml($params);
        return $data;
    }

    /**
     * @param array $urlparams
     * @return string
     * @throws coding_exception
     */
    private static function  get_downloadhtml(array $urlparams) : string {
        global $OUTPUT;

        $urlbase = new moodle_url('/mod/kuet/dwn_report.php');
        return $OUTPUT->download_dataformat_selector(get_string('downloadas', 'table'),
            $urlbase, 'download', $urlparams);
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @param int $kid
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_question_report(int $cmid, int $sid, int $kid): stdClass {
        $session = new kuet_sessions($sid);
        if ($session->is_group_mode()) {
            $data = self::get_group_question_report($cmid, $sid, $kid);
            $data->groupmode = 1;
        } else {
            $data = self::get_individual_question_report($cmid, $sid, $kid);
        }
        return $data;
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @param int $kid
     * @return stdClass
     * @throws JsonException
     * @throws dml_transaction_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_individual_question_report(int $cmid, int $sid, int $kid): stdClass {
        global $DB, $USER;
        $session = new kuet_sessions($sid);
        $question = new kuet_questions($kid);
        $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
        $data = new stdClass();
        $data->questionreport = true;
        $data->sessionid = $sid;
        $data->kid = $kid;
        $data->cmid = $cmid;
        $data->kuetid = $question->get('kuetid');
        $data->questionnid = $question->get('id');
        $data->position = $question->get('qorder');
        $data->type = $question->get('qtype');
        $questiondata = question_bank::load_question($questiondb->id);
        $data->questiontext = questions::get_text(
            $cmid, $questiondata->questiontext, $questiondata->questiontextformat, $questiondata->id, $questiondata, 'questiontext'
        );
        $data->questiontextformat = $questiondata->questiontextformat;
        $data->backurl = (new moodle_url('/mod/kuet/reports.php', ['cmid' => $cmid, 'sid' => $sid]))->out(false);
        /** @var questions $type */
        $type = questions::get_question_class_by_string_type($data->type);
        $data = $type::get_question_report($session, $questiondata, $data, $kid);

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $cmcontext = context_module::instance($cmid);
        $users = enrol_get_course_users($course->id, true);
        foreach ($users as $key => $user) {
            if (has_capability('mod/kuet:startsession', $cmcontext, $user)) {
                unset($users[$key]);
            }
        }
        $data->numusers = count($users);
        $data->numcorrect = kuet_questions_responses::count_records(
            ['kuet' => $data->kuetid, 'session' => $sid, 'kid' => $kid, 'result' => questions::SUCCESS]
        );
        $data->numincorrect = kuet_questions_responses::count_records(
            ['kuet' => $data->kuetid, 'session' => $sid, 'kid' => $kid, 'result' => questions::FAILURE]
        );
        $data->numpartial = kuet_questions_responses::count_records(
            ['kuet' => $data->kuetid, 'session' => $sid, 'kid' => $kid, 'result' => questions::PARTIALLY]
        );
        $data->numnoresponse = $data->numusers - ($data->numcorrect + $data->numincorrect + $data->numpartial);
        $data->percent_correct = round(($data->numcorrect / $data->numusers) * 100, 2);
        $data->percent_incorrect = round(($data->numincorrect / $data->numusers) * 100, 2);
        $data->percent_partially = round(($data->numpartial / $data->numusers) * 100, 2);
        $data->percent_noresponse = round(($data->numnoresponse / $data->numusers) * 100, 2);
        if ($session->get('anonymousanswer') === 1) {
            if (has_capability('mod/kuet:viewanonymousanswers', $cmcontext, $USER)) {
                $data->hasranking = true;
                $data->questionranking =
                    self::get_ranking_for_question($users, $data->answers, $session, $question, $cmid, $sid, $kid);
            }
        } else {
            $data->hasranking = true;
            if (!isset($data->answers)) {
                $data->answers = [];
            }
            $data->questionranking =
                self::get_ranking_for_question($users, $data->answers, $session, $question, $cmid, $sid, $kid);
        }
        $params = ['cmid' => $cmid, 'sid' => $sid, 'name' => self::QUESTION_REPORT, 'kid' => $kid];
        $data->downloadquestionreport = self::get_downloadhtml($params);
        return $data;
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @param int $kid
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function get_group_question_report(int $cmid, int $sid, int $kid): stdClass {
        global $DB, $USER;
        $session = new kuet_sessions($sid);
        $question = new kuet_questions($kid);
        $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
        $data = new stdClass();
        $data->questionreport = true;
        $data->sessionid = $sid;
        $data->kid = $kid;
        $data->cmid = $cmid;
        $data->kuetid = $question->get('kuetid');
        $data->questionnid = $question->get('id');
        $data->position = $question->get('qorder');
        $data->type = $question->get('qtype');
        $questiondata = question_bank::load_question($questiondb->id);
        $data->questiontext = questions::get_text(
            $cmid, $questiondata->questiontext, $questiondata->questiontextformat, $questiondata->id, $questiondata, 'questiontext'
        );
        $data->backurl = (new moodle_url('/mod/kuet/reports.php', ['cmid' => $cmid, 'sid' => $sid]))->out(false);
        /** @var questions $type */
        $type = questions::get_question_class_by_string_type($data->type);
        $data = $type::get_question_report($session, $questiondata, $data, $kid);

        $cmcontext = context_module::instance($cmid);
        $groups = groupmode::get_grouping_groups($session->get('groupings'));
        $data->numgroups = count($groups);
        $gselectedmembers = groupmode::get_one_member_of_each_grouping_group($session->get('groupings'));
        // Num correct.
        $numcorrect = 0;
        $numincorrect = 0;
        $numpartial = 0;
        foreach ($gselectedmembers as $gselectedmember) {
            $numcorrect += kuet_questions_responses::count_records(
                ['kuet' => $data->kuetid, 'session' => $sid, 'kid' => $kid, 'result' => questions::SUCCESS,
                    'userid' => $gselectedmember]
            );
            $numincorrect += kuet_questions_responses::count_records(
                ['kuet' => $data->kuetid, 'session' => $sid, 'kid' => $kid, 'result' => questions::FAILURE,
                    'userid' => $gselectedmember]
            );
            $numpartial += kuet_questions_responses::count_records(
                ['kuet' => $data->kuetid, 'session' => $sid, 'kid' => $kid, 'result' => questions::PARTIALLY,
                    'userid' => $gselectedmember]
            );
        }
        $data->numcorrect = $numcorrect;
        $data->numincorrect = $numincorrect;
        $data->numpartial = $numpartial;
        $data->numnoresponse = $data->numgroups - ($data->numcorrect + $data->numincorrect + $data->numpartial);

        $data->percent_correct = round(($data->numcorrect / $data->numgroups) * 100, 2);
        $data->percent_incorrect = round(($data->numincorrect / $data->numgroups) * 100, 2);
        $data->percent_partially = round(($data->numpartial / $data->numgroups) * 100, 2);
        $data->percent_noresponse = round(($data->numnoresponse / $data->numgroups) * 100, 2);
        if (is_null($data->answers)) {
            $data->hasnoanswers = true;
            return $data;
        }
        if ($session->get('anonymousanswer') === 1) {
            if (has_capability('mod/kuet:viewanonymousanswers', $cmcontext, $USER)) {
                $data->hasranking = true;
                $data->groupmode = true;
                $data->questiongroupranking =
                    self::get_group_ranking_for_question($groups, $data->answers, $session, $question, $cmid, $sid, $kid);
            }
        } else {
            $data->hasranking = true;
            $data->questiongroupranking =
                self::get_group_ranking_for_question($groups, $data->answers, $session, $question, $cmid, $sid, $kid);
        }
        $params = ['cmid' => $cmid, 'sid' => $sid, 'kid' => $kid, 'name' => self::GROUP_QUESTION_REPORT];
        $data->downloadquestionreport = self::get_downloadhtml($params);
        return $data;
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @param int $userid
     * @param context_module $cmcontext
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_user_report(int $cmid, int $sid, int $userid, context_module $cmcontext): stdClass {
        global $USER, $DB;
        $data = new stdClass();
        $data->cmid = $cmid;
        $session = new kuet_sessions($sid);
        $data->kuetid = $session->get('kuetid');
        if ($session->get('anonymousanswer') === 1 && !has_capability('mod/kuet:viewanonymousanswers', $cmcontext, $USER)) {
            throw new moodle_exception('anonymousanswers', 'mod_kuet', '',
                [], get_string('anonymousanswers', 'mod_kuet'));
        }
        $data->userreport = true;
        $data->sessionname = $session->get('name');
        $userdata = $DB->get_record('user', ['id' => $userid]);
        $data = self::add_userdata($userdata, $data, $userid);
        $data->backurl = (new moodle_url('/mod/kuet/reports.php', ['cmid' => $cmid, 'sid' => $sid]))->out(false);
        $data->config = sessions::get_session_config($sid, $cmid);
        $data->sessionquestions =
            self::get_questions_data_for_user_report($data->kuetid, $cmid, $sid, $userid);
        $data->numquestions = count($data->sessionquestions);
        $data->noresponse = 0;
        $data->success = 0;
        $data->partially = 0;
        $data->failures = 0;
        $data->noevaluable = 0;
        foreach ($data->sessionquestions as $question) {
            switch ($question->response) {
                case 'incorrect':
                    $data->failures++;
                    break;
                case 'partially':
                    $data->partially++;
                    break;
                case 'success':
                    $data->success++;
                    break;
                case 'noresponse':
                    $data->noresponse++;
                    break;
                case 'noevaluable':
                    $data->noevaluable++;
                    break;
                default:
                    break;
            }
        }
        $params = ['cmid' => $cmid, 'sid' => $sid, 'userid' => $userid, 'name' => self::USER_REPORT];
        $data->downloaduserreport = self::get_downloadhtml($params);
        $data->usersessiongrade = round(grade::get_session_grade($userid, $sid, $session->get('kuetid')), 2);
        return $data;
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @param int $groupid
     * @param context_module $cmcontext
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_group_report(int $cmid, int $sid, int $groupid, context_module $cmcontext): stdClass {
        global $USER;

        $data = new stdClass();
        $data->cmid = $cmid;
        $data->sid = $sid;
        $session = new kuet_sessions($sid);
        $data->kuetid = $session->get('kuetid');
        if ($session->get('anonymousanswer') === 1 && !has_capability('mod/kuet:viewanonymousanswers', $cmcontext, $USER)) {
            throw new moodle_exception('anonymousanswers', 'mod_kuet', '',
                [], get_string('anonymousanswers', 'mod_kuet'));
        }
        $data->groupreport = true;
        $data->sessionname = $session->get('name');
        $gmembers = groupmode::get_group_members($groupid);
        if (!empty($gmembers)) {
            $gmember = reset($gmembers);
        }
        $groupdata = groups_get_group($groupid);
        $data = self::add_groupdata($groupdata, $data);
        $data->backurl = (new moodle_url('/mod/kuet/reports.php', ['cmid' => $cmid, 'sid' => $sid]))->out(false);
        $data->config = sessions::get_session_config($sid, $cmid);
        $data->sessionquestions =
            self::get_questions_data_for_user_report($data->kuetid, $cmid, $sid, $gmember->id);
        $data->numquestions = count($data->sessionquestions);
        $data->noresponse = 0;
        $data->success = 0;
        $data->partially = 0;
        $data->failures = 0;
        $data->noevaluable = 0;
        foreach ($data->sessionquestions as $question) {
            switch ($question->response) {
                case 'incorrect':
                    $data->failures++;
                    break;
                case 'partially':
                    $data->partially++;
                    break;
                case 'success':
                    $data->success++;
                    break;
                case 'noresponse':
                    $data->noresponse++;
                    break;
                case 'noevaluable':
                    $data->noevaluable++;
                    break;
                default:
                    break;
            }
        }
        $params = ['cmid' => $cmid, 'sid' => $sid, 'groupid' => $groupid, 'name' => self::GROUP_REPORT];
        $data->downloaduserreport = self::get_downloadhtml($params);
        return $data;
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_student_report(int $cmid, int $sid): stdClass {
        global $USER, $DB;
        $data = new stdClass();
        $data->cmid = $cmid;
        $session = new kuet_sessions($sid);
        $data->kuetid = $session->get('kuetid');
        $data->userreport = true;
        $data->groupmode = $session->is_group_mode();
        $data->sessionname = $session->get('name');
        $userdata = $DB->get_record('user', ['id' => $USER->id]);
        $data = self::add_userdata($userdata, $data, $USER->id);
        $data->backurl = (new moodle_url('/mod/kuet/reports.php', ['cmid' => $cmid]))->out(false);
        $data->sessionquestions =
            self::get_questions_data_for_user_report($data->kuetid, $cmid, $sid, $USER->id);
        $data->numquestions = count($data->sessionquestions);
        $data->noresponse = 0;
        $data->success = 0;
        $data->partially = 0;
        $data->failures = 0;
        $data->noevaluable = 0;

        foreach ($data->sessionquestions as $question) {
            switch ($question->response) {
                case 'incorrect':
                    $data->failures++;
                    break;
                case 'partially':
                    $data->partially++;
                    break;
                case 'success':
                    $data->success++;
                    break;
                case 'noresponse':
                    $data->noresponse++;
                    break;
                case 'noevaluable':
                    $data->noevaluable++;
                    break;
                default:
                    break;
            }
        }
        $params = ['cmid' => $cmid, 'sid' => $sid, 'userid' => $USER->id, 'name' => self::USER_REPORT];
        $data->downloaduserreport = self::get_downloadhtml($params);
        $data->usersessiongrade = round(grade::get_session_grade($USER->id, $sid, $session->get('kuetid')), 2);
        return $data;
    }

    /**
     * @param stdClass $userdata
     * @param stdClass $data
     * @param int $userid
     * @param int $imagesize
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    private static function add_userdata(stdClass $userdata, stdClass $data, int $userid, int $imagesize = 1): stdClass {
        global $PAGE;
        $userpicture = new user_picture($userdata);
        $userpicture->size = $imagesize;
        $data->userimage = $userpicture->get_url($PAGE)->out(false);
        $data->userfullname = $userdata->firstname . ' ' . $userdata->lastname;
        $data->userprofileurl = (new moodle_url('/user/profile.php', ['id' => $userid]))->out(false);
        return $data;
    }

    /**
     * @param stdClass $groupdata
     * @param stdClass $data
     * @param int $imagesize
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     */
    private static function add_groupdata(stdClass $groupdata, stdClass $data, int $imagesize = 1): stdClass {
        $data->groupimage = groupmode::get_group_image($groupdata, $data->sid, $imagesize);
        $data->groupname = $groupdata->name;
        $data->groupurl = '';
        return $data;
    }

    /**
     * @param array $users
     * @param array $answers
     * @param kuet_sessions $session
     * @param kuet_questions $question
     * @param int $cmid
     * @param int $sid
     * @param int $kid
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_ranking_for_question(
        array $users, array $answers, kuet_sessions $session, kuet_questions $question, int $cmid, int $sid, int $kid
    ): array {
        global $DB;
        $context = context_module::instance($cmid);
        $results = [];
        foreach ($users as $user) {
            $userdata = $DB->get_record('user', ['id' => $user->id]);
            $user->participantid = $user->id;
            if ($userdata !== false && !has_capability('mod/kuet:startsession', $context, $userdata)) {
                $user = self::add_userdata($userdata, $user, $user->id);
                $user->viewreporturl = (new moodle_url('/mod/kuet/reports.php',
                    ['cmid' => $cmid, 'sid' => $sid, 'userid' => $userdata->id]))->out(false);
                $response = kuet_questions_responses::get_record(['userid' => $userdata->id, 'session' => $sid, 'kid' => $kid]);
                if ($response !== false) {
                    $other = json_decode(base64_decode($response->get('response')), false);
                    /** @var questions $type */
                    $type = questions::get_question_class_by_string_type($other->type);
                    $user = $type::get_ranking_for_question($user, $response, $answers, $session, $question);
                } else {
                    $questiontimestr = self::get_time_string($session, $question);
                    $user->response = 'noresponse';
                    $user->responsestr = get_string('noresponse', 'mod_kuet');
                    $user->userpoints = 0;
                    $user->answertext = '-';
                    $user->score_moment = 0;
                    $user->time = $questiontimestr . ' / ' . $questiontimestr; // Or 0?
                }
                $results[] = $user;
            }
        }
        // Reorder by points.
        usort($results, static fn($a, $b) => $b->score_moment <=> $a->score_moment);
        $position = 0;
        foreach ($results as $result) {
            $result->userposition = ++$position;
        }
        return $results;
    }

    /**
     * @param array $groups
     * @param array $answers
     * @param kuet_sessions $session
     * @param kuet_questions $question
     * @param int $cmid
     * @param int $sid
     * @param int $kid
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_group_ranking_for_question(
        array $groups, array $answers, kuet_sessions $session, kuet_questions $question, int $cmid, int $sid, int $kid
    ): array {

        $results = [];
        foreach ($groups as $group) {
            $group->sid = $sid;
            $group = self::add_groupdata($group, $group);
            $group->viewreporturl = (new moodle_url('/mod/kuet/reports.php',
                ['cmid' => $cmid, 'sid' => $sid, 'groupid' => $group->id]))->out(false);
            $gmembers = groupmode::get_group_members($group->id);
            $gmember = reset($gmembers);
            $group->participantid = $gmember->id;
            $response = kuet_questions_responses::get_record(['userid' => $gmember->id, 'session' => $sid, 'kid' => $kid]);
            if ($response !== false) {
                $other = json_decode(base64_decode($response->get('response')), false);
                /** @var questions $type */
                $type = questions::get_question_class_by_string_type($other->type);
                $group = $type::get_ranking_for_question($group, $response, $answers, $session, $question);
            } else {
                $questiontimestr = self::get_time_string($session, $question);
                $group->response = 'noresponse';
                $group->responsestr = get_string('noresponse', 'mod_kuet');
                $group->grouppoints = 0;
                $group->answertext = '-';
                $group->score_moment = 0;
                $group->time = $questiontimestr . ' / ' . $questiontimestr; // Or 0?
            }
            $results[] = $group;
        }
        return $results;
    }
}
