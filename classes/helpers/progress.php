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
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet\helpers;

use coding_exception;
use context_module;
use core\invalid_persistent_exception;
use JsonException;
use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_sessions;
use mod_kuet\persistents\kuet_user_progress;
use moodle_exception;
use stdClass;

class progress {

    /**
     * @param int $jqshowid
     * @param int $sessionid
     * @param int $userid
     * @param int $cmid
     * @param int $currentquestionjqid
     * @return void
     * @throws JsonException
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function set_progress(
        int $jqshowid,
        int $sessionid,
        int $userid,
        int $cmid,
        int $currentquestionjqid
    ): void {

        $cmcontext = context_module::instance($cmid);
        $isteacher = has_capability('mod/kuet:managesessions', $cmcontext);
        if (!$isteacher) {
            $session = kuet_sessions::get_record(['id' => $sessionid] );
            switch ($session->get('sessionmode')) {
                case sessions::INACTIVE_PROGRAMMED:
                case sessions::PODIUM_PROGRAMMED:
                case sessions::RACE_PROGRAMMED:
                    $record = kuet_user_progress::get_session_progress_for_user(
                        $userid, $sessionid, $jqshowid
                    );
                    switch ([$record !== false, $session->get('randomquestions')]) {
                        case [false, 1]:  // New order of questions for one user.
                            $data = new stdClass();
                            $data->questionsorder = self::shuffle_order($jqshowid, $cmid, $sessionid);
                            if ($currentquestionjqid === 0) {
                                $firstquestion = explode(',', $data->questionsorder);
                                $data->currentquestion = reset($firstquestion);
                            } else {
                                $data->currentquestion = $currentquestionjqid;
                            }
                            break;
                        case [true, 1]:
                        case [true, 0]: // Order records already exist, so it is retained.
                            $data = json_decode($record->get('other'), false);
                            $data->currentquestion = $currentquestionjqid;
                            break;
                        case [false, 0]: // New order, but no need to randomise.
                            $order = (new questions($jqshowid, $cmid, $sessionid))->get_list();
                            $keys = '';
                            foreach ($order as $question) {
                                $keys .= $question->get('id') . ',';
                            }
                            $data = new stdClass();
                            $data->questionsorder = trim($keys, ',');
                            if ($currentquestionjqid === 0) {
                                $firstquestion = explode(',', $data->questionsorder);
                                $data->currentquestion = reset($firstquestion);
                            } else {
                                $data->currentquestion = $currentquestionjqid;
                            }
                            break;
                        default:
                            $data = new stdClass();
                            $data->currentquestion = $currentquestionjqid;
                            break;
                    }
                    kuet_user_progress::add_progress($jqshowid, $sessionid, $userid, json_encode($data, JSON_THROW_ON_ERROR));
                    break;
                case sessions::INACTIVE_MANUAL:
                case sessions::PODIUM_MANUAL:
                case sessions::RACE_MANUAL:
                default:
                    // Student progress in these modes is set manually by the teacher.
                    break;
            }
        }
    }

    /**
     * @param int $jqshowid
     * @param int $cmid
     * @param int $sessionid
     * @return string
     * @throws coding_exception
     */
    public static function shuffle_order(int $jqshowid, int $cmid, int $sessionid): string {
        $order = (new questions($jqshowid, $cmid, $sessionid))->get_list();
        shuffle($order);
        $neworder = '';
        foreach ($order as $question) {
            $neworder .= $question->get('id') . ',';
        }
        return trim($neworder, ',');
    }
}
