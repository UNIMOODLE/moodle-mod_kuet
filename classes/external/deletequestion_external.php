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
 * Delete question API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_kuet\persistents\kuet_questions;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Delete question class
 */
class deletequestion_external extends external_api {

    /**
     * Delete question paremeters validation
     *
     * @return external_function_parameters
     */
    public static function deletequestion_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'sid' => new external_value(PARAM_INT, 'session id'),
                'qid' => new external_value(PARAM_INT, 'question id')
            ]
        );
    }

    /**
     * Delete questio
     *
     * @param int $sid
     * @param int $qid
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function deletequestion(int $sid, int $qid): array {
        self::validate_parameters(
            self::deletequestion_parameters(),
            ['sid' => $sid, 'qid' => $qid]
        );

        try {
            $sqp = new kuet_questions($qid);
            $deletedorder = $sqp->get('qorder');
            $deleted = $sqp->delete();
            // Reorder the rest of the questions.
            /** @var kuet_questions[] $questionstoreorder */
            $questionstoreorder = $sqp::get_session_questions_to_reorder($sid, $deletedorder);
            if (!empty($questionstoreorder)) {
                foreach ($questionstoreorder as $question) {
                    $question->set('qorder', $deletedorder);
                    $question->update();
                    $deletedorder++;
                }
            }
        } catch (moodle_exception $e) {
            $deleted = false;
        }

        return [
            'deleted' => $deleted
        ];
    }

    /**
     * Delete question returns
     *
     * @return external_single_structure
     */
    public static function deletequestion_returns(): external_single_structure {
        return new external_single_structure(
            [
                'deleted' => new external_value(PARAM_BOOL, 'false there was an error.'),
            ]
        );
    }
}
