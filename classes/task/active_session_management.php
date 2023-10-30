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

namespace mod_jqshow\task;

use coding_exception;
use core\invalid_persistent_exception;
use core\task\scheduled_task;
use lang_string;
use mod_jqshow\models\sessions;
use mod_jqshow\persistents\jqshow;
use mod_jqshow\persistents\jqshow_sessions;
use stdClass;

class active_session_management extends scheduled_task {

    /**
     * @return lang_string|string
     * @throws coding_exception
     */
    public function get_name(): string {
        return get_string('activesessionmanagement', 'mod_jqshow');
    }

    /**
     * @return void
     * @throws invalid_persistent_exception
     * @throws coding_exception
     */
    public function execute(): void {
        // TODO provide for time zones for each user, and activate them if they are met, for each user. Great implementation.
        $jqshows = jqshow::get_records();
        $date = time();
        $a = new stdClass();
        foreach ($jqshows as $jqshow) {
            $sessions = jqshow_sessions::get_records(['jqshowid' => $jqshow->get('id')], 'startdate');
            $sessionstarted = jqshow_sessions::get_record(['jqshowid' => $jqshow->get('id'),
                'status' => sessions::SESSION_STARTED]);
            if ($sessionstarted !== false) {
                $sessionstartedmode = $sessionstarted->get('sessionmode');
                if ($sessionstartedmode === sessions::INACTIVE_MANUAL ||
                    $sessionstartedmode === sessions::PODIUM_MANUAL ||
                    $sessionstartedmode === sessions::RACE_MANUAL) {
                    // A manual session is active, and will prevail over scheduled sessions until it ends in this jqshow.
                    $a->sessionid = $sessionstarted->get('id');
                    $a->jqshowid = $sessionstarted->get('jqshowid');
                    mtrace(get_string('sessionmanualactivated', 'mod_jqshow', $a));
                    continue;
                }
            }
            $activated = false;
            foreach ($sessions as $session) {
                if ($session->get('status') === sessions::SESSION_CREATING) {
                    continue;
                }
                if ($session->get('status') !== sessions::SESSION_FINISHED && $session->get('automaticstart') !== 0) {
                    if ($sessionstarted === false &&
                        $session->get('startdate') <= $date &&
                        $session->get('enddate') > $date &&
                        $session->get('status') === sessions::SESSION_ACTIVE) {
                        // We start the session if it is in session.
                        (new jqshow_sessions($session->get('id')))->set('status', sessions::SESSION_STARTED)->update();
                        $activated = true;
                        $a->sessionid = $session->get('id');
                        $a->jqshowid = $session->get('jqshowid');
                        mtrace(get_string('sessionactivated', 'mod_jqshow', $a));
                    }
                    if ($session->get('enddate') <= $date &&
                        ($session->get('status') === sessions::SESSION_STARTED ||
                            $session->get('status') === sessions::SESSION_ACTIVE)) {
                        // We end the session if you have complied.
                        (new jqshow_sessions($session->get('id')))->set('status', sessions::SESSION_FINISHED)->update();
                        (new jqshow_sessions($session->get('id')))->set('enddate', time())->update();
                        $a->sessionid = $session->get('id');
                        $a->jqshowid = $session->get('jqshowid');
                        mtrace(get_string('sessionfinished', 'mod_jqshow', $a));
                    }
                }
                if ($activated === true) {
                    // The nearest session has been activated, so other sessions are ignored to avoid conflicts.
                    break;
                }
            }
            $sessionsstarted = jqshow_sessions::get_records(['jqshowid' => $jqshow->get('id'),
                'status' => sessions::SESSION_STARTED], 'timemodified');
            if (count($sessionsstarted) > 1) {
                array_shift($sessionsstarted);
                foreach ($sessionsstarted as $sessionstarted) {
                    (new jqshow_sessions($sessionstarted->get('id')))->set('status', sessions::SESSION_FINISHED)->update();
                    $a->sessionid = $sessionstarted->get('id');
                    $a->jqshowid = $sessionstarted->get('jqshowid');
                    mtrace(get_string('sessionfinishedformoreone', 'mod_jqshow', $a));
                }
            }
        }
    }
}
