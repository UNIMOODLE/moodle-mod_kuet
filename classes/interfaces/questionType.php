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
 * Question type interface
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet\interfaces;

use mod_kuet\persistents\kuet_questions;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use question_definition;
use stdClass;

/**
 * Question type interface
 */
interface questionType {

    /**
     * Get simple makr
     *
     * @param stdClass $useranswer
     * @param kuet_questions_responses $response
     * @return float
     */
    public static function get_simple_mark(stdClass $useranswer,  kuet_questions_responses $response): float;


    /**
     * Get ranking for a question
     *
     * @param stdClass $participant
     * @param kuet_questions_responses $response
     * @param array $answers
     * @param kuet_sessions $session
     * @param kuet_questions $question
     * @return stdClass
     */
    public static function get_ranking_for_question(stdClass $participant,
                                                    kuet_questions_responses $response,
                                                    array $answers,
                                                    kuet_sessions $session,
                                                    kuet_questions $question): stdClass;

    /**
     * Get question statistics
     *
     * @param question_definition $question
     * @param kuet_questions_responses[] $responses
     * @return mixed
     */
    public static function get_question_statistics(question_definition $question, array $responses): array;

    /**
     * Get question report
     *
     * @param kuet_sessions $session
     * @param question_definition $questiondata
     * @param stdClass $data
     * @param int $kid
     * @return mixed
     */
    public static function get_question_report(kuet_sessions $session,
                                               question_definition $questiondata,
                                               stdClass $data,
                                               int $kid): stdClass;

    /**
     * Export question
     *
     * @param int $kid
     * @param int $cmid
     * @param int $sessionid
     * @param int $kuetid
     * @param bool $preview
     * @return mixed
     */
    public static function export_question(int $kid, int $cmid, int $sessionid, int $kuetid, bool $preview): object;

    /**
     * Export question response
     *
     * @param stdClass $data
     * @param string $response
     * @param int $result
     * @return mixed
     */
    public static function export_question_response(stdClass $data, string $response, int $result): stdClass;

    /**
     * Question response
     *
     * @param int $cmid
     * @param int $kid
     * @param int $questionid
     * @param int $sessionid
     * @param int $kuetid
     * @param string $statmentfeedback
     * @param int $userid
     * @param int $timeleft
     * @param array $custom
     * @return void
     */
    public static function question_response(
        int $cmid,
        int $kid,
        int $questionid,
        int $sessionid,
        int $kuetid,
        string $statmentfeedback,
        int $userid,
        int $timeleft,
        array $custom
    ): void;
}

