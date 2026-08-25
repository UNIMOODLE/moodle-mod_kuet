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
 * Session questions API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

use coding_exception;
use mod_kuet\helpers\modcontext;
use context_module;
use dml_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use moodle_url;
use pix_icon;
use stdClass;



/**
 * Session questions class
 */
class sessionquestions_external extends external_api {
    /**
     * Session questions parameters validation
     *
     * @return external_function_parameters
     */
    public static function sessionquestions_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'kuetid' => new external_value(PARAM_INT, 'kuetid'),
                'cmid' => new external_value(PARAM_INT, 'cmid for course module'),
                'sid' => new external_value(PARAM_INT, 'sid for session kuet'),
            ]
        );
    }

    /**
     * Session questions
     *
     * @param int $kuetid
     * @param int $cmid
     * @param int $sid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function sessionquestions(int $kuetid, int $cmid, int $sid): array {
        self::validate_parameters(
            self::sessionquestions_parameters(),
            ['kuetid' => $kuetid, 'cmid' => $cmid, 'sid' => $sid]
        );

        $context = modcontext::from_cmid($cmid);
        self::validate_context($context);
        require_capability('mod/kuet:managesessions', $context);
        modcontext::require_session_in_cm($sid, $cmid);
        $allquestions = (new questions($kuetid, $cmid, $sid))->get_list();
        $questiondata = [];
        foreach ($allquestions as $question) {
            $questiondata[] = questions::export_session_question($question, $cmid);
        }
        return ['kuetid' => $kuetid, 'cmid' => $cmid, 'sid' => $sid, 'sessionquestions' => $questiondata];
    }

    /**
     * Session questions returns
     *
     * @return external_single_structure
     */
    public static function sessionquestions_returns(): external_single_structure {
        return new external_single_structure([
            'sid' => new external_value(PARAM_INT, 'Session id'),
            'cmid' => new external_value(PARAM_INT, 'Course Module id'),
            'kuetid' => new external_value(PARAM_INT, 'Kuet id'),
            'sessionquestions' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'sid' => new external_value(PARAM_INT, 'Session id'),
                        'cmid' => new external_value(PARAM_INT, 'Course Module id'),
                        'kuetid' => new external_value(PARAM_INT, 'Kuet id'),
                        'questionnid' => new external_value(PARAM_INT, 'Question id'),
                        'position' => new external_value(PARAM_INT, 'Question order'),
                        'name' => new external_value(PARAM_RAW, 'Name of question'),
                        'icon' => new external_single_structure([
                            'key' => new external_value(PARAM_RAW, 'Key of icon'),
                            'component' => new external_value(PARAM_RAW, 'Component of icon'),
                            'title' => new external_value(PARAM_RAW, 'Title of icon'),
                        ], ''),
                        'type' => new external_value(PARAM_RAW, 'Question type'),
                        'isvalid' => new external_value(PARAM_RAW, 'Is question valid or missing config'),
                        'time' => new external_value(PARAM_RAW, 'Time of question'),
                        'version' => new external_value(PARAM_RAW, 'Question version'),
                        'managesessions' => new external_value(PARAM_BOOL, 'Capability'),
                        'question_preview_url' => new external_value(PARAM_URL, 'Url for preview'),
                        'editquestionurl' => new external_value(PARAM_URL, 'Url for edit question'),
                    ],
                    ''
                ),
                ''
            ),
        ]);
    }
}
