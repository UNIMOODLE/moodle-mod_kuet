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
 * Get provisional ranking API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

use coding_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Get provisional ranking class
 */
class getprovisionalranking_external extends external_api {
    /**
     * Get provisional ranking parameters validation
     *
     * @return external_function_parameters
     */
    public static function getprovisionalranking_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'sid' => new external_value(PARAM_INT, 'sessionid id'),
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'kid' => new external_value(PARAM_INT, 'Question id for kuet_questions'),
            ]
        );
    }

    /**
     * Get provisional ranking
     *
     * @param int $sid
     * @param int $cmid
     * @param int $kid
     * @return true[]
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function getprovisionalranking(int $sid, int $cmid, int $kid): array {
        self::validate_parameters(
            self::getprovisionalranking_parameters(),
            ['sid' => $sid, 'cmid' => $cmid, 'kid' => $kid]
        );
        $session = kuet_sessions::get_record(['id' => $sid]);
        $questions = new questions($session->get('kuetid'), $cmid, $sid);
        return [
            'provisionalranking' => sessions::get_provisional_ranking($sid, $cmid, $kid),
            'kid' => $kid,
            'sessionid' => $sid,
            'cmid' => $cmid,
            'kuetid' => $session->get('kuetid'),
            'numquestions' => $questions->get_num_questions(),
            'ranking' => true,
        ];
    }

    /**
     * Get provisional ranking returns
     *
     * @return external_single_structure
     */
    public static function getprovisionalranking_returns(): external_single_structure {
        return new external_single_structure([
            'provisionalranking' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'userimageurl' => new external_value(PARAM_RAW, 'Url for user image'),
                        'userposition' => new external_value(PARAM_INT, 'User position depending on the points'),
                        'userfullname'   => new external_value(PARAM_RAW, 'Name of user'),
                        'questionscore' => new external_value(PARAM_FLOAT, 'Num of partially correct answers'),
                        'userpoints' => new external_value(PARAM_FLOAT, 'Total points of user'),
                    ], ''
                ), ''
            ),
            'kid' => new external_value(PARAM_INT, 'kuet_question id'),
            'sessionid' => new external_value(PARAM_INT, 'kuet_session id'),
            'cmid' => new external_value(PARAM_INT, 'course module id'),
            'kuetid' => new external_value(PARAM_INT, 'kuet id'),
            'numquestions' => new external_value(PARAM_INT, 'Number of questions for teacher panel'),
            'ranking' => new external_value(PARAM_BOOL, 'Is a ranking, for control panel context'),
        ]);
    }
}
