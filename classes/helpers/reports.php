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

namespace mod_jqshow\helpers;

use coding_exception;
use context_module;
use dml_exception;
use dml_transaction_exception;
use JsonException;
use mod_jqshow\api\groupmode;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow;
use mod_jqshow\persistents\jqshow_questions;
use mod_jqshow\persistents\jqshow_questions_responses;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\questions\match;
use mod_jqshow\questions\multichoice;
use mod_jqshow\questions\numerical;
use mod_jqshow\questions\shortanswer;
use mod_jqshow\questions\truefalse;
use moodle_exception;
use moodle_url;
use question_bank;
use stdClass;
use user_picture;

class reports {

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @param int $sid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_questions_data_for_teacher_report(int $jqshowid, int $cmid, int $sid): array {
        $session = new jqshow_sessions($sid);
        $questions = (new questions($jqshowid, $cmid, $sid))->get_list();
        $questionsdata = [];

        foreach ($questions as $question) {
            if ($session->is_group_mode()) {
                $data = self::get_questions_data_for_teacher_report_groups($question, $jqshowid, $cmid, $session); // TODO grosups.
            } else {
                $data = self::get_questions_data_for_teacher_report_individual($question, $jqshowid, $cmid, $session);
            }

            $questionsdata[] = $data;
        }
        return $questionsdata;
    }

    /**
     * @param jqshow_questions $question
     * @param int $jqshowid
     * @param int $cmid
     * @param jqshow_sessions $session
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_questions_data_for_teacher_report_groups(jqshow_questions $question, int $jqshowid, int $cmid, jqshow_sessions $session) {
        global $DB;

        $groupmembers = groupmode::get_one_member_of_each_grouping_group($session->get('groupings'));
        $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
        $data = new stdClass();
        $data->questionnid = $question->get('id');
        $data->position = $question->get('qorder');
        $data->name = $questiondb->name;
        $data->type = $question->get('qtype');
        $data->success = 0;
        $data->failures = 0;
        $data->partyally = 0;
        foreach ($groupmembers as $groupmember) {
            $data->success += jqshow_questions_responses::count_records([
                'jqshow' => $jqshowid,
                'session' => $session->get('id'),
                'jqid' => $question->get('id'),
                'result' => questions::SUCCESS,
                'userid' => $groupmember
            ]);
            $data->failures += jqshow_questions_responses::count_records([
                'jqshow' => $jqshowid,
                'session' => $session->get('id'),
                'jqid' => $question->get('id'),
                'result' => questions::FAILURE,
                'userid' => $groupmember
            ]);
            $data->partyally += jqshow_questions_responses::count_records([
                'jqshow' => $jqshowid,
                'session' => $session->get('id'),
                'jqid' => $question->get('id'),
                'result' => questions::PARTIALLY,
                'userid' => $groupmember
            ]);
        }

        $data->noresponse = count($groupmembers) - ($data->success + $data->failures + $data->partyally);
        $data->time = self::get_time_string($session, $question);
        $data->questionreporturl = (new moodle_url('/mod/jqshow/reports.php',
            ['cmid' => $cmid, 'sid' => $session->get('id'), 'jqid' => $question->get('id')]
        ))->out(false);
        return $data;
    }

    /**
     * @param jqshow_questions $question
     * @param int $jqshowid
     * @param int $cmid
     * @param jqshow_sessions $session
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_questions_data_for_teacher_report_individual(jqshow_questions $question, int $jqshowid, int $cmid, jqshow_sessions $session) {
        global $DB;

        $jqshow = new jqshow($jqshowid);
        $users = enrol_get_course_users($jqshow->get('course'), true);
        $cmcontext = context_module::instance($cmid);
        foreach ($users as $key => $user) {
            if (has_capability('mod/jqshow:startsession', $cmcontext, $user)) {
                unset($users[$key]);
            }
        }
        $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
        $data = new stdClass();
        $data->questionnid = $question->get('id');
        $data->position = $question->get('qorder');
        $data->name = $questiondb->name;
        $data->type = $question->get('qtype');
        $data->success = jqshow_questions_responses::count_records([
            'jqshow' => $jqshowid,
            'session' => $session->get('id'),
            'jqid' => $question->get('id'),
            'result' => questions::SUCCESS
        ]);
        $data->failures = jqshow_questions_responses::count_records([
            'jqshow' => $jqshowid,
            'session' => $session->get('id'),
            'jqid' => $question->get('id'),
            'result' => questions::FAILURE
        ]);
        $data->partyally = jqshow_questions_responses::count_records([
            'jqshow' => $jqshowid,
            'session' => $session->get('id'),
            'jqid' => $question->get('id'),
            'result' => questions::PARTIALLY
        ]);
        $data->noresponse = count($users) - ($data->success + $data->failures + $data->partyally);
        $data->time = self::get_time_string($session, $question);
        $data->questionreporturl = (new moodle_url('/mod/jqshow/reports.php',
            ['cmid' => $cmid, 'sid' => $session->get('id'), 'jqid' => $question->get('id')]
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
        $session = new jqshow_sessions($sid);
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
        global $DB;

        $results = sessions::get_session_results($sid, $cmid);
        foreach ($results as $user) {
            $userdata = $DB->get_record('user', ['id' => $user->userid]);
            if ($userdata !== false) {
                $user = self::add_userdata($userdata, $user, $user->userid, 200);
                $user->viewreporturl = (new moodle_url('/mod/jqshow/reports.php',
                    ['cmid' => $cmid, 'sid' => $sid, 'userid' => $user->userid]))->out(false);
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

        $results = sessions::get_group_session_results($sid, $cmid);
        foreach ($results as $group) {
            $group->sid = $sid;
            $groupdata = groups_get_group($group->id);
            $group = self::add_groupdata($groupdata, $group, 200);
            $group->viewreporturl = (new moodle_url('/mod/jqshow/reports.php',
                    ['cmid' => $cmid, 'sid' => $sid, 'groupid' => $group->id]))->out(false);
        }
        return $results;
    }

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @param int $sid
     * @param int $userid
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_questions_data_for_user_report(int $jqshowid, int $cmid, int $sid, int $userid): array {
        global $DB;
        $session = new jqshow_sessions($sid);
        $questions = (new questions($jqshowid, $cmid, $sid))->get_list();
        $questionsdata = [];
        foreach ($questions as $question) {
            $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
            $response = jqshow_questions_responses::get_record([
                'jqshow' => $jqshowid,
                'session' => $sid,
                'jqid' => $question->get('id'),
                'userid' => $userid,
            ]);
            $data = new stdClass();
            $data->questionnid = $question->get('id');
            $data->position = $question->get('qorder');
            $data->name = $questiondb->name;
            $data->type = $question->get('qtype');
            $questiontimestr = self::get_time_string($session, $question);
            if ($response === false) {
                $data->response = 'noresponse';
                $data->responsestr = get_string('noresponse', 'mod_jqshow');
                $data->time = $questiontimestr . ' / ' . $questiontimestr; // Or 0?
            } else {
                switch ($response->get('result')) {
                    case questions::FAILURE:
                        $data->response = 'failure';
                        $data->responsestr = get_string('failure', 'mod_jqshow');
                        break;
                    case questions::SUCCESS:
                        $data->response = 'success';
                        $data->responsestr = get_string('success', 'mod_jqshow');
                        break;
                    case questions::PARTIALLY:
                        $data->response = 'partially';
                        $data->responsestr = get_string('partially_correct', 'mod_jqshow');
                        break;
                    case questions::NORESPONSE:
                    default:
                        $data->response = 'noresponse';
                        $data->responsestr = get_string('noresponse', 'mod_jqshow');
                        break;
                    case questions::NOTEVALUABLE:
                        $data->response = 'noevaluable';
                        $data->responsestr = get_string('noevaluable', 'mod_jqshow');
                        break;
                    case questions::INVALID:
                        $data->response = 'invalid';
                        $data->responsestr = get_string('invalid', 'mod_jqshow');
                        break;
                }
                $data->time = self::get_user_time_in_question($session, $question, $response);
            }
            $data->cmid = $cmid;
            $data->sessionid = $sid;
            $data->userid = $userid;
            $questionsdata[] = $data;
        }
        return $questionsdata;
    }

    /**
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @param jqshow_questions_responses $response
     * @return string
     * @throws JsonException
     * @throws coding_exception
     */
    public static function get_user_time_in_question(
        jqshow_sessions $session, jqshow_questions $question, jqshow_questions_responses $response
    ): string {
        $responsedata = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
        $usertimelast = $responsedata->timeleft;
        switch ($session->get('timemode')) {
            case sessions::NO_TIME:
            default:
                $timestring = '-';
                break;
            case sessions::SESSION_TIME:
                $numquestion = jqshow_questions::count_records(
                    ['sessionid' => $session->get('id'), 'jqshowid' => $session->get('jqshowid')]
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
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @return string
     * @throws coding_exception
     */
    public static function get_time_string(jqshow_sessions $session, jqshow_questions $question): string {
        switch ($session->get('timemode')) {
            case sessions::NO_TIME:
            default:
                return ($question->get('timelimit') > 0) ? $question->get('timelimit') . 's' : '-';
            case sessions::SESSION_TIME:
                $numquestion = jqshow_questions::count_records(
                    ['sessionid' => $session->get('id'), 'jqshowid' => $session->get('jqshowid')]
                );
                $timeperquestion = round((int)$session->get('sessiontime') / $numquestion);
                return ($timeperquestion > 0) ? $timeperquestion . 's' : '-';
            case sessions::QUESTION_TIME:
                return ($question->get('timelimit') > 0) ? $question->get('timelimit') . 's' : $session->get('questiontime') . 's';
        }
    }

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @param int $sid
     * @param context_module $cmcontext
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_session_report(int $jqshowid, int $cmid, int $sid, context_module $cmcontext): stdClass {
        global $USER;
        $data = new stdClass();
        $data->jqshowid = $jqshowid;
        $data->cmid = $cmid;
        $data->sessionreport = true;
        $session = new jqshow_sessions($sid);
        $mode = $session->get('sessionmode');
        $data->sessionname = $session->get('name');
        $data->config = sessions::get_session_config($sid, $cmid);
        $data->sessionquestions = self::get_questions_data_for_teacher_report($jqshowid, $cmid, $sid);
        $rankingusers = $session->is_group_mode() ? 'rankinggroups' : 'rankingusers';
        if ($session->get('anonymousanswer') === 1) {
            if (has_capability('mod/jqshow:viewanonymousanswers', $cmcontext, $USER)) {
                $data->hasranking = true;
                $data->$rankingusers = self::get_ranking_for_teacher_report($cmid, $sid);
            }
        } else {
            $data->hasranking = true;
            $data->$rankingusers = self::get_ranking_for_teacher_report($cmid, $sid);
        }
        if ($mode !== sessions::INACTIVE_PROGRAMMED && $mode !== sessions::INACTIVE_MANUAL) {
            if ($session->is_group_mode()) {
                $data->showfinalranking = true;
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
                $data->showfinalranking = true;
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
        return $data;
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @param int $jqid
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_question_report(int $cmid, int $sid, int $jqid): stdClass {
        $session = new jqshow_sessions($sid);
        if ($session->is_group_mode()) {
            $data = self::get_group_question_report($cmid, $sid, $jqid);
            $data->groupmode = 1;
        } else {
            $data = self::get_individual_question_report($cmid, $sid, $jqid);
        }
        return $data;
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @param int $jqid
     * @return stdClass
     * @throws JsonException
     * @throws dml_transaction_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_individual_question_report(int $cmid, int $sid, int $jqid): stdClass {
        global $DB, $USER;
        $session = new jqshow_sessions($sid);
        $question = new jqshow_questions($jqid);
        $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
        $data = new stdClass();
        $data->questionreport = true;
        $data->sessionid = $sid;
        $data->jqid = $jqid;
        $data->cmid = $cmid;
        $data->jqshowid = $question->get('jqshowid');
        $data->questionnid = $question->get('id');
        $data->position = $question->get('qorder');
        $data->type = $question->get('qtype');
        $questiondata = question_bank::load_question($questiondb->id);
        $data->questiontext = questions::get_text(
            $cmid, $questiondata->questiontext, $questiondata->questiontextformat, $questiondata->id, $questiondata, 'questiontext'
        );
        $data->backurl = (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $cmid, 'sid' => $sid]))->out(false);
        switch ($data->type) {
            case questions::MULTICHOICE:
                $data = multichoice::get_question_report($session, $questiondata, $data, $jqid);
                break;
            case questions::MATCH:
                $data = match::get_question_report($session, $questiondata, $data, $jqid);
                break;
            case questions::TRUE_FALSE:
                $data = truefalse::get_question_report($session, $questiondata, $data, $jqid);
                break;
            case questions::SHORTANSWER:
                $data = shortanswer::get_question_report($session, $questiondata, $data, $jqid);
                break;
            default:
                throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                    [], get_string('question_nosuitable', 'mod_jqshow'));
        }
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $cmcontext = context_module::instance($cmid);
        $users = enrol_get_course_users($course->id, true);
        foreach ($users as $key => $user) {
            if (has_capability('mod/jqshow:startsession', $cmcontext, $user)) {
                unset($users[$key]);
            }
        }
        $data->numusers = count($users);
        $data->numcorrect = jqshow_questions_responses::count_records(
            ['jqshow' => $data->jqshowid, 'session' => $sid, 'jqid' => $jqid, 'result' => questions::SUCCESS]
        );
        $data->numincorrect = jqshow_questions_responses::count_records(
            ['jqshow' => $data->jqshowid, 'session' => $sid, 'jqid' => $jqid, 'result' => questions::FAILURE]
        );
        $data->numpartial = jqshow_questions_responses::count_records(
            ['jqshow' => $data->jqshowid, 'session' => $sid, 'jqid' => $jqid, 'result' => questions::PARTIALLY]
        );
        $data->numnoresponse = $data->numusers - ($data->numcorrect + $data->numincorrect + $data->numpartial);
        $data->percent_correct = round(($data->numcorrect / $data->numusers) * 100, 2);
        $data->percent_incorrect = round(($data->numincorrect / $data->numusers) * 100, 2);
        $data->percent_partially = round(($data->numpartial / $data->numusers) * 100, 2);
        $data->percent_noresponse = round(($data->numnoresponse / $data->numusers) * 100, 2);
        if ($session->get('anonymousanswer') === 1) {
            if (has_capability('mod/jqshow:viewanonymousanswers', $cmcontext, $USER)) {
                $data->hasranking = true;
                $data->questionranking =
                    self::get_ranking_for_question($users, $data->answers, $session, $question, $cmid, $sid, $jqid);
            }
        } else {
            $data->hasranking = true;
            $data->questionranking =
                self::get_ranking_for_question($users, $data->answers, $session, $question, $cmid, $sid, $jqid);
        }
        return $data;
    }

    /**
     * @param int $cmid
     * @param int $sid
     * @param int $jqid
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public static function get_group_question_report(int $cmid, int $sid, int $jqid): stdClass {
        global $DB, $USER;
        $session = new jqshow_sessions($sid);
        $question = new jqshow_questions($jqid);
        $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
        $data = new stdClass();
        $data->questionreport = true;
        $data->sessionid = $sid;
        $data->jqid = $jqid;
        $data->cmid = $cmid;
        $data->jqshowid = $question->get('jqshowid');
        $data->questionnid = $question->get('id');
        $data->position = $question->get('qorder');
        $data->type = $question->get('qtype');
        $questiondata = question_bank::load_question($questiondb->id);
        $data->questiontext = questions::get_text(
            $cmid, $questiondata->questiontext, $questiondata->questiontextformat, $questiondata->id, $questiondata, 'questiontext'
        );
        $data->backurl = (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $cmid, 'sid' => $sid]))->out(false);

        switch ($data->type) {
            case questions::MULTICHOICE:
                $data = multichoice::get_question_report($session, $questiondata, $data, $jqid);
                break;
            case questions::MATCH:
                $data = match::get_question_report($session, $questiondata, $data, $jqid);
                break;
            case questions::TRUE_FALSE:
                $data = truefalse::get_question_report($session, $questiondata, $data, $jqid);
                break;
            case questions::SHORTANSWER:
                $data = shortanswer::get_question_report($session, $questiondata, $data, $jqid);
                break;
            case questions::NUMERICAL:
                // TODO.
                break;
            default:
                break;
        }
        $cmcontext = context_module::instance($cmid);
        $groups = groupmode::get_grouping_groups($session->get('groupings'));
        $data->numgroups = count($groups);
        $gselectedmembers = groupmode::get_one_member_of_each_grouping_group($session->get('groupings'));
        // Num correct.
        $numcorrect = 0;
        $numincorrect = 0;
        $numpartial = 0;
        foreach ($gselectedmembers as $gselectedmember) {
            $numcorrect += jqshow_questions_responses::count_records(
                ['jqshow' => $data->jqshowid, 'session' => $sid, 'jqid' => $jqid, 'result' => questions::SUCCESS,
                    'userid' => $gselectedmember]
            );
            $numincorrect += jqshow_questions_responses::count_records(
                ['jqshow' => $data->jqshowid, 'session' => $sid, 'jqid' => $jqid, 'result' => questions::FAILURE,
                    'userid' => $gselectedmember]
            );
            $numpartial += jqshow_questions_responses::count_records(
                ['jqshow' => $data->jqshowid, 'session' => $sid, 'jqid' => $jqid, 'result' => questions::PARTIALLY,
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
        if ($session->get('anonymousanswer') === 1) {
            if (has_capability('mod/jqshow:viewanonymousanswers', $cmcontext, $USER)) {
                $data->hasranking = true;
                $data->groupmode = true;
                $data->questiongroupranking =
                    self::get_group_ranking_for_question($groups, $data->answers, $session, $question, $cmid, $sid, $jqid);
            }
        } else {
            $data->hasranking = true;
            $data->questiongroupranking =
                self::get_group_ranking_for_question($groups, $data->answers, $session, $question, $cmid, $sid, $jqid);
        }
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
        $session = new jqshow_sessions($sid);
        $data->jqshowid = $session->get('jqshowid');
        if ($session->get('anonymousanswer') === 1 && !has_capability('mod/jqshow:viewanonymousanswers', $cmcontext, $USER)) {
            throw new moodle_exception('anonymousanswers', 'mod_jqshow', '',
                [], get_string('anonymousanswers', 'mod_jqshow'));
        }
        $data->userreport = true;
        $data->sessionname = $session->get('name');
        $userdata = $DB->get_record('user', ['id' => $userid]);
        $data = self::add_userdata($userdata, $data, $userid);
        $data->backurl = (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $cmid, 'sid' => $sid]))->out(false);
        $data->config = sessions::get_session_config($sid, $cmid);
        $data->sessionquestions =
            self::get_questions_data_for_user_report($data->jqshowid, $cmid, $sid, $userid);
        $data->numquestions = count($data->sessionquestions);
        $data->noresponse = 0;
        $data->success = 0;
        $data->partially = 0;
        $data->failures = 0;
        $data->noevaluable = 0;
        foreach ($data->sessionquestions as $question) {
            switch ($question->response) {
                case 'failure':
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
        $session = new jqshow_sessions($sid);
        $data->jqshowid = $session->get('jqshowid');
        if ($session->get('anonymousanswer') === 1 && !has_capability('mod/jqshow:viewanonymousanswers', $cmcontext, $USER)) {
            throw new moodle_exception('anonymousanswers', 'mod_jqshow', '',
                [], get_string('anonymousanswers', 'mod_jqshow'));
        }
        $data->groupreport = true;
        $data->sessionname = $session->get('name');
        $gmembers = groupmode::get_group_members($groupid);
        $gmember = reset($gmembers);
        $groupdata = groups_get_group($groupid);
        $data = self::add_groupdata($groupdata, $data);
        $data->backurl = (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $cmid, 'sid' => $sid]))->out(false);
        $data->config = sessions::get_session_config($sid, $cmid);
        $data->sessionquestions =
            self::get_questions_data_for_user_report($data->jqshowid, $cmid, $sid, $gmember->id);
        $data->numquestions = count($data->sessionquestions);
        $data->noresponse = 0;
        $data->success = 0;
        $data->partially = 0;
        $data->failures = 0;
        $data->noevaluable = 0;
        foreach ($data->sessionquestions as $question) {
            switch ($question->response) {
                case 'failure':
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
        $session = new jqshow_sessions($sid);
        $data->jqshowid = $session->get('jqshowid');
        $data->userreport = true;
        $data->groupmode = $session->is_group_mode();
        $data->sessionname = $session->get('name');
        $userdata = $DB->get_record('user', ['id' => $USER->id]);
        $data = self::add_userdata($userdata, $data, $USER->id);
        $data->backurl = (new moodle_url('/mod/jqshow/reports.php', ['cmid' => $cmid]))->out(false);
        $data->sessionquestions =
            self::get_questions_data_for_user_report($data->jqshowid, $cmid, $sid, $USER->id);
        $data->numquestions = count($data->sessionquestions);
        $data->noresponse = 0;
        $data->success = 0;
        $data->partially = 0;
        $data->failures = 0;
        $data->noevaluable = 0;
        foreach ($data->sessionquestions as $question) {
            switch ($question->response) {
                case 'failure':
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
     * @param int $groupid
     * @param int $imagesize
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
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
     * @param jqshow_sessions $session
     * @param jqshow_questions $question
     * @param int $cmid
     * @param int $sid
     * @param int $jqid
     * @return array
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_ranking_for_question(
        array $users, array $answers, jqshow_sessions $session, jqshow_questions $question, int $cmid, int $sid, int $jqid
    ): array {
        global $DB;
        $context = context_module::instance($cmid);
        $results = [];
        foreach ($users as $user) {
            $userdata = $DB->get_record('user', ['id' => $user->id]);
            $user->participantid = $user->id;
            if ($userdata !== false && !has_capability('mod/jqshow:startsession', $context, $userdata)) {
                $user = self::add_userdata($userdata, $user, $user->id);
                $user->viewreporturl = (new moodle_url('/mod/jqshow/reports.php',
                    ['cmid' => $cmid, 'sid' => $sid, 'userid' => $userdata->id]))->out(false);
                $response = jqshow_questions_responses::get_record(['userid' => $userdata->id, 'session' => $sid, 'jqid' => $jqid]);
                if ($response !== false) {
                    $other = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
                    switch ($other->type) {
                        case questions::MULTICHOICE:
                            $user = multichoice::get_ranking_for_question($user, $response, $answers, $session, $question);
                            break;
                        case questions::MATCH:
                            $user = match::get_ranking_for_question($user, $response, $session, $question);
                            break;
                        case questions::TRUE_FALSE:
                            $user = truefalse::get_ranking_for_question($user, $response, $answers, $session, $question);
                            break;
                        case questions::SHORTANSWER:
                            $user = shortanswer::get_ranking_for_question($user, $response, $answers, $session, $question);
                            break;
                        case questions::NUMERICAL:
                            $user = numerical::get_ranking_for_question($user, $response, $answers, $session, $question);
                            break;
                        default:
                            throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                                [], get_string('question_nosuitable', 'mod_jqshow'));
                    }
                } else {
                    $questiontimestr = self::get_time_string($session, $question);
                    $user->response = 'noresponse';
                    $user->responsestr = get_string('noresponse', 'mod_jqshow');
                    $user->userpoints = 0;
                    $user->answertext = '-';
                    $user->score_moment = 0;
                    $user->time = $questiontimestr . ' / ' . $questiontimestr; // Or 0?
                }
                $results[] = $user;
            }
        }
        return $results;
    }
    public static function get_group_ranking_for_question(
        array $groups, array $answers, jqshow_sessions $session, jqshow_questions $question, int $cmid, int $sid, int $jqid
    ): array {

        $results = [];
        foreach ($groups as $group) {
            $group->sid = $sid;
            $group = self::add_groupdata($group, $group);
            $group->viewreporturl = (new moodle_url('/mod/jqshow/reports.php',
                ['cmid' => $cmid, 'sid' => $sid, 'groupid' => $group->id]))->out(false);
            $gmembers = groupmode::get_group_members($group->id);
            $gmember = reset($gmembers);
            $group->participantid = $gmember->id;
            $response = jqshow_questions_responses::get_record(['userid' => $gmember->id, 'session' => $sid, 'jqid' => $jqid]);
            if ($response !== false) {
                $other = json_decode($response->get('response'), false, 512, JSON_THROW_ON_ERROR);
                switch ($other->type) {
                    case questions::MULTICHOICE:
                        $group = multichoice::get_ranking_for_question($group, $response, $answers, $session, $question);
                        break;
                    case questions::MATCH:
                        // TODO.
                        break;
                    case questions::TRUE_FALSE:
                        $group = truefalse::get_ranking_for_question($group, $response, $answers, $session, $question);
                        break;
                    default:
                        throw new moodle_exception('question_nosuitable', 'mod_jqshow', '',
                            [], get_string('question_nosuitable', 'mod_jqshow'));
                }
            } else {
                $questiontimestr = self::get_time_string($session, $question);
                $group->response = 'noresponse';
                $group->responsestr = get_string('noresponse', 'mod_jqshow');
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
