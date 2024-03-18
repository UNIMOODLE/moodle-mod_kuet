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
 * Reorder questions API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

use coding_exception;
use core\invalid_persistent_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_kuet\persistents\kuet_questions;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Reorder questions class
 */
class reorderquestions_external extends external_api {

    /**
     * Reorder questions parameters validation
     *
     * @return external_function_parameters
     */
    public static function reorderquestions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questions' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'qid' => new external_value(PARAM_INT, 'question id'),
                        'qorder' => new external_value(PARAM_INT, 'new question order'),
                    ]
                ), 'List of questions qith the new order.', VALUE_DEFAULT, []
            ),
        ]);
    }

    /**
     * Reorder questions
     *
     * @param array $questions
     * @return array
     * @throws moodle_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     */
    public static function reorderquestions(array $questions): array {
        self::validate_parameters(
            self::reorderquestions_parameters(),
            ['questions' => $questions]
        );

        $added = true;
        foreach ($questions as $question) {
            $result = kuet_questions::reorder_question($question['qid'], $question['qorder']);
            if (false === $result) {
                $added = false;
            }
        }

        return [
            'added' => $added
        ];
    }

    /**
     * Reorder questions return
     *
     * @return external_single_structure
     */
    public static function reorderquestions_returns(): external_single_structure {
        return new external_single_structure(
            [
                'added' => new external_value(PARAM_BOOL, 'false there was an error.'),
            ]
        );
    }
}
