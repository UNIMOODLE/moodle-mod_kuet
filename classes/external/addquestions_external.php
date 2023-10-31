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
 * @package    mod_jqshow
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jqshow\external;

use coding_exception;
use core\invalid_persistent_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\persistents\jqshow_questions;
use moodle_exception;
use mod_jqshow\models\questions;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class addquestions_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function add_questions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questions' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'questionid' => new external_value(PARAM_INT, 'question id'),
                        'sessionid' => new external_value(PARAM_INT, 'sessionid'),
                        'jqshowid' => new external_value(PARAM_INT, 'jqshowid'),
                        'qtype' => new external_value(PARAM_RAW, 'sessionid')
                    ]
                ), 'List of session questions', VALUE_DEFAULT, []
            )
        ]);
    }

    /**
     * @param array $questions
     * @return array
     * @throws moodle_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     */
    public static function add_questions(array $questions): array {
        self::validate_parameters(
            self::add_questions_parameters(),
            ['questions' => $questions]
        );

        $added = true;
        foreach ($questions as $question) {
            if (!in_array($question['qtype'], questions::TYPES, true)) {
                continue;
            }
            $result = jqshow_questions::add_question($question['questionid'], $question['sessionid'],
                $question['jqshowid'], $question['qtype']);
            if (false === $result) {
                $added = false;
            }
        }
        return [
            'added' => $added
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function add_questions_returns(): external_single_structure {
        return new external_single_structure(
            [
                'added' => new external_value(PARAM_BOOL, 'false there was an error.'),
            ]
        );
    }
}
