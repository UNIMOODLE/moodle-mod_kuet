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
 * Get question bank panel part class file
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

use coding_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use context_module;
use invalid_parameter_exception;
use required_capability_exception;
use mod_kuet\helpers\modcontext;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_sessions;

/**
 * Get question bank panel part class
 */
class getquestionbank_external extends external_api
{
    /**
     * Get active session parameters validation
     *
     * @return external_function_parameters
     */
    public static function getquestionbank_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'questionbankcmid' => new external_value(PARAM_INT, 'questionbank id'),
                'kuetid' => new external_value(PARAM_INT, 'kuet id'),
                'cmid' => new external_value(PARAM_INT, 'cm id'),
                'sid' => new external_value(PARAM_INT, 'session id'),
            ]
        );
    }

    /**
     * Get question bank panel
     *
     * @param int $questionbankcmid
     * @param int $kuetid
     * @param int $cmid
     * @param int $sid
     * @return array
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public static function getquestionbank(int $questionbankcmid, int $kuetid, int $cmid, int $sid): array {
        global $DB;
        self::validate_parameters(
            self::getquestionbank_parameters(),
            [
                'questionbankcmid' => $questionbankcmid,
                'kuetid' => $kuetid,
                'cmid' => $cmid,
                'sid' => $sid,
            ]
        );

        $context = modcontext::from_cmid($cmid);
        self::validate_context($context);
        require_capability('mod/kuet:managesessions', $context);
        modcontext::require_kuet_in_cm($kuetid, $cmid);
        modcontext::require_session_in_cm($sid, $cmid);

        // The question bank lives in a different course module, with a context of its
        // own: being able to manage this kuet does not entitle the user to the
        // questions of a bank they cannot use.
        $bankcm = get_coursemodule_from_id('qbank', $questionbankcmid, 0, false, MUST_EXIST);
        $bankcontext = context_module::instance($bankcm->id);
        // Also validate_context() on the bank, not just a capability check: it runs
        // require_login() for the bank's course and module, which is what rejects a bank
        // in a course the caller cannot access, or one that is hidden from them.
        self::validate_context($bankcontext);
        // Same capabilities core requires to offer a bank in its own bank switcher
        // (question\output\switch_question_bank): either of the two is enough to
        // consult it. Which questions may actually be used is a per-question matter
        // that core resolves with question_has_capability_on($question, 'use').
        if (!has_any_capability(['moodle/question:useall', 'moodle/question:usemine'], $bankcontext)) {
            throw new required_capability_exception($bankcontext, 'moodle/question:useall', 'nopermissions', '');
        }

        $kuet = $DB->get_record('kuet', ['id' => $kuetid], '*', MUST_EXIST);
        $session = new sessions($kuet, $cmid, $questionbankcmid);
        $questions = $session->export_session_questionbank_panel($sid, $cmid);
        return [
            'questions' => $questions,
        ];
    }

    /**
     * Get active session returns
     *
     * @return external_single_structure
     */
    public static function getquestionbank_returns(): external_single_structure {
        return new external_single_structure(
            [
                'questions' =>
                    new external_single_structure(
                        [
                            'questionbankcmid' => new external_value(PARAM_INT, 'questionbankcmid'),
                            'hasquestionbankname' => new external_value(PARAM_BOOL, 'hasquestionbankname', VALUE_OPTIONAL),
                            'questionbankname' => new external_value(PARAM_RAW, 'questionbankname', VALUE_OPTIONAL),
                            'currentcategory' => new external_value(PARAM_RAW, 'currentcategory'),
                            'questionbank_categories' => new external_value(PARAM_RAW, 'questionbank_categories'),
                            'ispage2' => new external_value(PARAM_INT, 'ispage2'),
                            'sid' => new external_value(PARAM_INT, 'sid'),
                            'cmid' => new external_value(PARAM_INT, 'cmid'),
                            'contextid' => new external_value(PARAM_INT, 'contextid'),
                            'kuetid' => new external_value(PARAM_INT, 'kuetid'),
                            'questionbank_url' => new external_value(PARAM_RAW, 'questionbank_url'),
                            'questions' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'status' => new external_value(PARAM_RAW, 'status'),
                                        'categoryid' => new external_value(PARAM_INT, 'categoryid'),
                                        'version' => new external_value(PARAM_INT, 'version'),
                                        'versionid' => new external_value(PARAM_INT, 'versionid'),
                                        'questionbankentryid' => new external_value(PARAM_INT, 'questionbankentryid'),
                                        'id' => new external_value(PARAM_INT, 'id'),
                                        'qtype' => new external_value(PARAM_RAW, 'qtype'),
                                        'name' => new external_value(PARAM_RAW, 'name'),
                                        'idnumber' => new external_value(PARAM_RAW, 'idnumber', VALUE_OPTIONAL),
                                        'contextid' => new external_value(PARAM_INT, 'contextid'),
                                        'icon' => new external_single_structure(
                                            [
                                                    'key' => new external_value(PARAM_RAW, 'key'),
                                                    'component' => new external_value(PARAM_RAW, 'component'),
                                                    'title' => new external_value(PARAM_RAW, 'title'),
                                                ],
                                            ''
                                        ),
                                        'issuitable' => new external_value(PARAM_BOOL, 'issuitable'),
                                        'questionpreview' => new external_value(PARAM_RAW, 'questionpreview url'),
                                        'questionedit' => new external_value(PARAM_RAW, 'questionedit url'),
                                    ],
                                    ''
                                ),
                                ''
                            ),
                            'sessionquestions' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'questionnid' => new external_value(PARAM_INT, 'questionnid'),
                                        'position' => new external_value(PARAM_INT, 'position'),
                                        'name' => new external_value(PARAM_RAW, 'name'),
                                        'type' => new external_value(PARAM_RAW, 'type'),
                                        'icon' => new external_single_structure(
                                            [
                                                        'key' => new external_value(PARAM_RAW, 'key'),
                                                        'component' => new external_value(PARAM_RAW, 'component'),
                                                        'title' => new external_value(PARAM_RAW, 'title'),
                                                ],
                                            ''
                                        ),
                                        'sid' => new external_value(PARAM_INT, 'sid'),
                                        'cmid' => new external_value(PARAM_INT, 'cmid'),
                                        'kuetid' => new external_value(PARAM_INT, 'kuetid'),
                                        'isvalid' => new external_value(PARAM_INT, 'isvalid'),
                                        'time' => new external_value(PARAM_RAW, 'time'),
                                        'issuitable' => new external_value(PARAM_INT, 'issuitable'),
                                        'version' => new external_value(PARAM_INT, 'version'),
                                        'managesessions' => new external_value(PARAM_INT, 'managesessions cap'),
                                        'question_preview_url' => new external_value(PARAM_RAW, 'question_preview_url'),
                                        'editquestionurl' => new external_value(PARAM_RAW, 'editquestionurl'),
                                    ],
                                    ''
                                ),
                                ''
                            ),
                            'resumeurl' => new external_value(PARAM_RAW, 'resumeurl'),
                            'formurl' => new external_value(PARAM_RAW, 'formurl'),
                        ],
                        'Question bank panel'
                    ),
            ]
        );
    }
}
