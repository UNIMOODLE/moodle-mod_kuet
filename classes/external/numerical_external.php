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
 * Numerical question type API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <juanpablo.decastro@uva.es>
 * @author     Juan Pablo de Castro  <juan.pablo.de.castro@gmail.com>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

use coding_exception;
use mod_kuet\helpers\modcontext;
use context_module;
use core\invalid_persistent_exception;
use dml_exception;
use dml_transaction_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use JsonException;
use mod_kuet\models\numerical;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use qtype_numerical_question;
use question_bank;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/question/engine/bank.php');

/**
 * Numerical question type class
 */
class numerical_external extends external_api {
    /**
     * Numerical question type parameters validation
     *
     * @return external_function_parameters
     */
    public static function numerical_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'responsenum' => new external_value(PARAM_RAW, 'User response text'),
                'unit' => new external_value(PARAM_RAW, 'Unit for response, optional depending on configuration'),
                'multiplier' => new external_value(PARAM_RAW, 'Multiplier of unit'),
                'sessionid' => new external_value(PARAM_INT, 'id of session'),
                'kuetid' => new external_value(PARAM_INT, 'id of kuet'),
                'cmid' => new external_value(PARAM_INT, 'id of cm'),
                'questionid' => new external_value(PARAM_INT, 'id of question'),
                'kid' => new external_value(PARAM_INT, 'id of question in kuet_questions'),
                'timeleft' => new external_value(PARAM_INT, 'Time left of question, if question has time, else 0.'),
                'preview' => new external_value(PARAM_BOOL, 'preview or not for grade'),
            ]
        );
    }

    /**
     * Numerical question type
     *
     * @param string $responsenum
     * @param string $unit
     * @param string $multiplier
     * @param int $sessionid
     * @param int $kuetid
     * @param int $cmid
     * @param int $questionid
     * @param int $kid
     * @param int $timeleft
     * @param bool $preview
     * @return array
     * @throws JsonException
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     */
    public static function numerical(
        string $responsenum,
        string $unit,
        string $multiplier,
        int $sessionid,
        int $kuetid,
        int $cmid,
        int $questionid,
        int $kid,
        int $timeleft,
        bool $preview
    ): array {
        self::validate_parameters(
            self::numerical_parameters(),
            [
                'responsenum' => $responsenum,
                'unit' => $unit,
                'multiplier' => $multiplier,
                'sessionid' => $sessionid,
                'kuetid' => $kuetid,
                'cmid' => $cmid,
                'questionid' => $questionid,
                'kid' => $kid,
                'timeleft' => $timeleft,
                'preview' => $preview,
            ]
        );

        $context = modcontext::from_cmid($cmid);
        self::validate_context($context);
        require_capability('mod/kuet:view', $context);
        modcontext::require_session_in_cm($sessionid, $cmid);

        return numerical::answer(
            $responsenum,
            $unit,
            $multiplier,
            $sessionid,
            $kuetid,
            $cmid,
            $questionid,
            $kid,
            $timeleft,
            $preview
        );
    }

    /**
     * Numerical question type return
     *
     * @return external_single_structure
     */
    public static function numerical_returns(): external_single_structure {
        return new external_single_structure(
            [
                'reply_status' => new external_value(PARAM_BOOL, 'Status of reply'),
                'result' => new external_value(PARAM_INT, 'Result of reply'),
                'hasfeedbacks' => new external_value(PARAM_BOOL, 'Has feedback'),
                'statment_feedback' => new external_value(PARAM_RAW, 'HTML statment feedback', VALUE_OPTIONAL),
                'answer_feedback' => new external_value(PARAM_RAW, 'HTML answer feedback', VALUE_OPTIONAL),
                'possibleanswers' => new external_value(PARAM_RAW, 'HTML answer feedback', VALUE_OPTIONAL),
                'numericalresponse' => new external_value(PARAM_RAW, 'User text response', VALUE_OPTIONAL),
                'programmedmode' => new external_value(PARAM_BOOL, 'Program mode for controls'),
                'preview' => new external_value(PARAM_BOOL, 'Question preview'),
            ]
        );
    }
}
