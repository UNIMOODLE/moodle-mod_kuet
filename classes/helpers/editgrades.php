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
 * Manual grade editing helper (KUETEDUCAM-73)
 *
 * @package    mod_kuet
 * @author     3IPUNT <contacte@tresipunt.com>
 * @copyright  3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\helpers;

use coding_exception;
use dml_exception;
use mod_kuet\api\grade;
use mod_kuet\api\groupmode;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use pix_icon;
use stdClass;

/**
 * Manual grade editing helper class.
 */
class editgrades {
    /**
     * Build the editable list of a user's questions in the session (page 2).
     *
     * Only answered, evaluable questions that are not flagged with
     * ignorecorrectanswer are editable; the rest are shown read-only.
     *
     * @param int $kuetid
     * @param int $cmid
     * @param int $sid
     * @param int $userid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_questions_data(int $kuetid, int $cmid, int $sid, int $userid): array {
        global $DB;
        $questions = (new questions($kuetid, $cmid, $sid))->get_list();
        $data = [];
        foreach ($questions as $question) {
            $questiondb = $DB->get_record('question', ['id' => $question->get('questionid')], '*', MUST_EXIST);
            $response = kuet_questions_responses::get_record([
                'kuet' => $kuetid, 'session' => $sid, 'kid' => $question->get('id'), 'userid' => $userid,
            ]);
            $kquestion = new kuet_questions($question->get('id'));
            /** @var questions $typeclass */
            $typeclass = questions::get_question_class_by_string_type($question->get('qtype'));

            $row = new stdClass();
            $row->kid = $question->get('id');
            $row->questionid = $question->get('questionid');
            $row->position = $question->get('qorder');
            $row->name = format_string($questiondb->name);
            $row->qtype = $question->get('qtype');
            $icon = new pix_icon('icon', '', 'qtype_' . $question->get('qtype'), ['class' => 'icon']);
            $row->icon = $icon->export_for_pix();
            $row->defaultmark = grade::get_rounded_mark((float) $questiondb->defaultmark);

            $hasresponse = $response !== false;
            $isevaluable = $typeclass::is_evaluable();
            $isignored = (bool) $kquestion->get('ignorecorrectanswer');
            $row->editable = $hasresponse && $isevaluable && !$isignored;

            if ($hasresponse) {
                $row->result = (int) $response->get('result');
                $row->responsestr = get_string(grade::get_result_mark_type($response), 'mod_kuet');
                $row->mark = grade::get_rounded_mark(grade::get_simple_mark($response));
                $row->ismanual = $response->get('manualmark') !== null;
                $row->manualcomment = (string) $response->get('manualcomment');
            } else {
                $row->result = questions::NORESPONSE;
                $row->responsestr = get_string('noresponse', 'mod_kuet');
                $row->mark = '-';
                $row->ismanual = false;
                $row->manualcomment = '';
            }
            $data[] = $row;
        }
        return $data;
    }

    /**
     * Whether the session applies the podium time percentage to the grade.
     *
     * @param kuet_sessions $session
     * @return bool
     * @throws coding_exception
     */
    public static function session_applies_time_percentage(kuet_sessions $session): bool {
        return in_array(
            $session->get('sessionmode'),
            [sessions::PODIUM_MANUAL, sessions::PODIUM_PROGRAMMED],
            true
        );
    }

    /**
     * Group of a user within the session's grouping, with its members' names.
     *
     * Read-only lookup (no side effects) used to warn that a manual mark in a
     * group session is replicated to every member (KUETEDUCAM-73).
     *
     * @param kuet_sessions $session
     * @param int $userid
     * @return stdClass|null {name, members} or null if not a group session / no group
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_group_info(kuet_sessions $session, int $userid): ?stdClass {
        if (!$session->is_group_mode()) {
            return null;
        }
        foreach (groupmode::get_grouping_groups((int) $session->get('groupings')) as $group) {
            if (groups_is_member($group->id, $userid)) {
                $names = [];
                foreach (groups_get_members($group->id) as $member) {
                    $names[] = fullname($member);
                }
                $info = new stdClass();
                $info->name = format_string($group->name);
                $info->members = implode(', ', $names);
                return $info;
            }
        }
        return null;
    }
}
