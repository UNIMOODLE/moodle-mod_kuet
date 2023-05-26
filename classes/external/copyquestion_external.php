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

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jqshow\external;

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_jqshow\persistents\jqshow_questions;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class copyquestion_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function copyquestion_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'qid' => new external_value(PARAM_INT, 'question id')
            ]
        );
    }

    /**
     * @param int $qid
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function copyquestion(int $qid): array {
        self::validate_parameters(
            self::copyquestion_parameters(),
            ['qid' => $qid]
        );
        $copied = true;
        try {
            $sqp = new jqshow_questions($qid);
            $numquestionsinsession = $sqp::count_records(['sessionid' => $sqp->get('sessionid')]);
            $newquestion = new stdClass();
            $newquestion->questionid = $sqp->get('questionid');
            $newquestion->sessionid = $sqp->get('sessionid');
            $newquestion->jqshowid = $sqp->get('jqshowid');
            $newquestion->qorder = ($numquestionsinsession + 1);
            $newquestion->qtype = $sqp->get('qtype');
            $newquestion->timelimit = $sqp->get('timelimit');
            $newquestion->ignorecorrectanswer = $sqp->get('ignorecorrectanswer');
            $newquestion->isvalid = $sqp->get('isvalid');
            $newquestion->config = $sqp->get('config');
            $newq = new jqshow_questions(0, $newquestion);
            $newq->save();
        } catch (moodle_exception $e) {
            $copied = false;
        }

        return [
            'copied' => $copied
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function copyquestion_returns(): external_single_structure {
        return new external_single_structure(
            [
                'copied' => new external_value(PARAM_BOOL, 'false there was an error.'),
            ]
        );
    }
}
