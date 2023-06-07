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

namespace mod_jqshow\helpers;

use coding_exception;
use core\invalid_persistent_exception;
use JsonException;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\persistents\jqshow_user_progress;
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
    ) {
        $session = jqshow_sessions::get_record(['id' => $sessionid] );
        switch ($session->get('sessionmode')) {
            case sessions::INACTIVE_PROGRAMMED:
            case sessions::PODIUM_PROGRAMMED:
            case sessions::RACE_PROGRAMMED:
                $record = jqshow_user_progress::get_session_progress_for_user(
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
                        $neworder = '';
                        foreach ($order as $question) {
                            $neworder .= $question->get('id') . ',';
                        }
                        $neworder = trim($neworder, ',');
                        $data = new stdClass();
                        $data->questionsorder = $neworder;
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
                jqshow_user_progress::add_progress($jqshowid, $sessionid, $userid, json_encode($data, JSON_THROW_ON_ERROR));
                break;
            case sessions::INACTIVE_MANUAL:
            case sessions::PODIUM_MANUAL:
            case sessions::RACE_MANUAL:
            default:
                // Student progress in these modes is set manually by the teacher.
                break;
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
