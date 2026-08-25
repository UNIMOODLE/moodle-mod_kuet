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
 * Add questiones API
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
use core\invalid_persistent_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use mod_kuet\persistents\kuet_questions;
use moodle_exception;
use mod_kuet\models\questions;




/**
 * Add questions class
 */
class addquestions_external extends external_api {
    /**
     * Add questions parameters validation
     *
     * @return external_function_parameters
     */
    public static function add_questions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questions' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'questionid' => new external_value(PARAM_INT, 'question id'),
                        'sessionid' => new external_value(PARAM_INT, 'sessionid'),
                        'kuetid' => new external_value(PARAM_INT, 'kuetid'),
                        'qtype' => new external_value(PARAM_RAW, 'Legacy type; ignored by the server', VALUE_DEFAULT, ''),
                        'uselatest' => new external_value(PARAM_BOOL, 'Use latest ready version', VALUE_DEFAULT, false),
                    ]
                ),
                'List of session questions',
                VALUE_DEFAULT,
                []
            ),
            'requestid' => new external_value(PARAM_ALPHANUMEXT, 'Retry token', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Add questions
     *
     * @param array $questions
     * @return array
     * @throws moodle_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     */
    public static function add_questions(array $questions, string $requestid = ''): array {
        global $DB, $SESSION;
        $params = self::validate_parameters(self::add_questions_parameters(),
            ['questions' => $questions, 'requestid' => $requestid]);
        $questions = $params['questions'];
        if (!$questions) {
            return ['added' => true];
        }
        $kuetid = (int)$questions[0]['kuetid'];
        $sid = (int)$questions[0]['sessionid'];
        $context = modcontext::from_kuet($kuetid);
        self::validate_context($context);
        require_capability('mod/kuet:managesessions', $context);
        modcontext::require_session_in_kuet($sid, $kuetid);
        $guard = new \mod_kuet\question\mutation($kuetid);
        try {
            $fingerprint = hash('sha256', json_encode($questions));
            $key = $kuetid . ':' . $sid . ':' . $params['requestid'];
            if ($params['requestid'] !== '' && isset($SESSION->kuetquestionrequests[$key])) {
                if ($SESSION->kuetquestionrequests[$key] !== $fingerprint) {
                    throw new invalid_parameter_exception('Request token already used with different questions.');
                }
                $guard->finish();
                return ['added' => true];
            }
            \mod_kuet\question\version_resolver::require_editable($sid);
            // Validate the entire batch before inserting any position.
            $resolved = [];
            foreach ($questions as $question) {
                if ((int)$question['kuetid'] !== $kuetid || (int)$question['sessionid'] !== $sid) {
                    throw new invalid_parameter_exception('All questions must target the same session.');
                }
                $resolved[] = \mod_kuet\question\bank_provider::require_question($question['questionid']);
            }
            $order = (int)$DB->get_field_sql('SELECT MAX(qorder) FROM {kuet_questions} WHERE sessionid = ?', [$sid]);
            foreach ($questions as $index => $input) {
                $question = $resolved[$index];
                $slot = new kuet_questions(0, (object)[
                    'questionid' => $question->id, 'sessionid' => $sid, 'kuetid' => $kuetid,
                    'qorder' => ++$order, 'qtype' => $question->qtype, 'timelimit' => 0,
                    'ignorecorrectanswer' => 0, 'isvalid' => 0, 'config' => '',
                ]);
                $slot->create();
                if ($input['uselatest']) {
                    \mod_kuet\question\version_resolver::set_policy($slot, null);
                }
            }
            $guard->finish();
            if ($params['requestid'] !== '') {
                $SESSION->kuetquestionrequests[$key] = $fingerprint;
                $SESSION->kuetquestionrequests = array_slice($SESSION->kuetquestionrequests, -100, null, true);
            }
            return ['added' => true];
        } catch (\Throwable $e) {
            $guard->abort($e);
        }
    }
    /**
     * Add questions return
     *
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
