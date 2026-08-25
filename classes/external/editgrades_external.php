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
 * Manual grade editing web service (KUETEDUCAM-73)
 *
 * @package    mod_kuet
 * @author     3IPUNT <contacte@tresipunt.com>
 * @copyright  3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

use coding_exception;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use dml_exception;
use invalid_parameter_exception;
use mod_kuet\api\grade;
use mod_kuet\event\grade_manually_updated;
use mod_kuet\models\questions;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use moodle_exception;

/**
 * Manual grade editing external class.
 */
class editgrades_external extends external_api {
    /**
     * Set manual grade parameters.
     *
     * @return external_function_parameters
     */
    public static function setmanualgrade_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'sid' => new external_value(PARAM_INT, 'Session id'),
            'kid' => new external_value(PARAM_INT, 'Question id in the session (kuet_questions.id)'),
            'userid' => new external_value(PARAM_INT, 'User id whose response is edited'),
            'mark' => new external_value(PARAM_FLOAT, 'New manual mark for the question'),
            'comment' => new external_value(PARAM_TEXT, 'Mandatory justification for the change'),
        ]);
    }

    /**
     * Set a manual grade on a response and recalculate.
     *
     * @param int $cmid
     * @param int $sid
     * @param int $kid
     * @param int $userid
     * @param float $mark
     * @param string $comment
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function setmanualgrade(
        int $cmid,
        int $sid,
        int $kid,
        int $userid,
        float $mark,
        string $comment
    ): array {
        global $DB;
        $params = self::validate_parameters(self::setmanualgrade_parameters(), [
            'cmid' => $cmid, 'sid' => $sid, 'kid' => $kid, 'userid' => $userid, 'mark' => $mark, 'comment' => $comment,
        ]);

        $cm = get_coursemodule_from_id('kuet', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/kuet:editgrades', $context);

        // The comment is mandatory.
        $comment = trim($params['comment']);
        if ($comment === '') {
            throw new moodle_exception('editgrades_commentrequired', 'mod_kuet');
        }

        // The override must hang off an existing response.
        $response = kuet_questions_responses::get_record([
            'kuet' => $cm->instance, 'session' => $params['sid'], 'kid' => $params['kid'], 'userid' => $params['userid'],
        ]);
        if ($response === false) {
            throw new moodle_exception('editgrades_noteditableerror', 'mod_kuet');
        }

        // Only evaluable, non-ignored questions can be edited.
        $kquestion = new kuet_questions($params['kid']);
        /** @var questions $typeclass */
        $typeclass = questions::get_question_class_by_string_type($kquestion->get('qtype'));
        if (!$typeclass::is_evaluable() || (bool) $kquestion->get('ignorecorrectanswer')) {
            throw new moodle_exception('editgrades_noteditableerror', 'mod_kuet');
        }

        // The mark must be within the question range.
        $defaultmark = (float) $DB->get_field('question', 'defaultmark', ['id' => $response->get('questionid')]);
        $mark = (float) $params['mark'];
        if ($mark < 0 || $mark > $defaultmark) {
            throw new moodle_exception('editgrades_markrange', 'mod_kuet', '', grade::get_rounded_mark($defaultmark));
        }

        grade::set_response_manual_mark($response, $mark, $comment);

        // Audit log: leave a record of the manual change (KUETEDUCAM-73).
        grade_manually_updated::create([
            'context' => $context,
            'objectid' => $response->get('id'),
            'relateduserid' => $params['userid'],
            'other' => ['kid' => $params['kid'], 'sessionid' => $params['sid']],
        ])->trigger();

        // Reload to report the effective values back to the UI.
        $saved = kuet_questions_responses::get_record([
            'kuet' => $cm->instance, 'session' => $params['sid'], 'kid' => $params['kid'], 'userid' => $params['userid'],
        ]);
        return [
            'success' => true,
            'mark' => grade::get_rounded_mark((float) $saved->get('manualmark')),
            'resultstr' => get_string(grade::get_result_mark_type($saved), 'mod_kuet'),
            'ismanual' => $saved->get('manualmark') !== null,
            'sessiongrade' => grade::get_rounded_mark(
                grade::get_normalized_session_grade($params['userid'], $params['sid'], (int) $cm->instance)
            ),
        ];
    }

    /**
     * Set manual grade returns.
     *
     * @return external_single_structure
     */
    public static function setmanualgrade_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the grade was saved'),
            'mark' => new external_value(PARAM_FLOAT, 'Saved manual mark'),
            'resultstr' => new external_value(PARAM_TEXT, 'New result status string'),
            'ismanual' => new external_value(PARAM_BOOL, 'Whether the mark is a manual override'),
            'sessiongrade' => new external_value(PARAM_FLOAT, 'Recalculated session grade for the user'),
        ]);
    }
}
