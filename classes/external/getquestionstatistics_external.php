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
 * Get question statistics API
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\external;

use coding_exception;
use dml_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use JsonException;
use mod_kuet\api\groupmode;
use mod_kuet\models\questions;
use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use question_bank;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/question/engine/bank.php');

/**
 * Get question statistics class
 */
class getquestionstatistics_external extends external_api {

    /**
     * Get question statistics parameter validation
     *
     * @return external_function_parameters
     */
    public static function getquestionstatistics_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'sid' => new external_value(PARAM_INT, 'session id'),
                'kid' => new external_value(PARAM_INT, 'Id for kuet_questions'),
            ]
        );
    }

    /**
     * Get question statistics
     *
     * @param int $sid
     * @param int $kid
     * @return array|array[]
     * @throws dml_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public static function getquestionstatistics(int $sid, int $kid): array {
        self::validate_parameters(
            self::getquestionstatistics_parameters(),
            ['sid' => $sid, 'kid' => $kid]
        );
        $kuetquestion = kuet_questions::get_question_by_kid($kid);
        $question = question_bank::load_question($kuetquestion->get('questionid'));
        $statistics = [];
        $session = new kuet_sessions($sid);
        $responses = kuet_questions_responses::get_question_responses($sid, $kuetquestion->get('kuetid'), $kid);
        if ($session->is_group_mode()) {
            $members = groupmode::get_one_member_of_each_grouping_group($session->get('groupings'));

            $groupresponses = [];
            foreach ($responses as $response) {
                if (in_array($response->get('userid'), $members)) {
                    $groupresponses[] = $response;
                }
            }
            $responses = $groupresponses;
        }

        try {
            /** @var questions $type */
            $type = questions::get_question_class_by_string_type($kuetquestion->get('qtype'));
            $statistics = $type::get_question_statistics($question, $responses);
        } catch (moodle_exception $exception) {
            return ['statistics' => $statistics];
        }
        return ['statistics' => $statistics];
    }

    /**
     * Get question statistics return
     *
     * @return external_single_structure
     */
    public static function getquestionstatistics_returns(): external_single_structure {
        return new external_single_structure(
            [
                'statistics' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'answerid' => new external_value(PARAM_INT, 'Answer id', VALUE_OPTIONAL),
                            'numberofreplies' => new external_value(PARAM_INT, 'Number of replies', VALUE_OPTIONAL),
                            'correct' => new external_value(PARAM_FLOAT, 'number of correct answers', VALUE_OPTIONAL),
                            'failure' => new external_value(PARAM_FLOAT, 'number of failures answers', VALUE_OPTIONAL),
                            'partially' => new external_value(PARAM_FLOAT, 'number of failures answers', VALUE_OPTIONAL),
                            'noresponse' => new external_value(PARAM_FLOAT, 'number of noresponse answers', VALUE_OPTIONAL),
                            'invalid' => new external_value(PARAM_FLOAT, 'number of invalid answers', VALUE_OPTIONAL),
                        ], 'Number of replies for one answer.'
                    ), 'List of answers with number of replies.', VALUE_OPTIONAL),
            ]
        );
    }
}
