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
use mod_jqshow\models\sessions as sessionsmodel;
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
    public function execute() : void {
        // TODO provide for time zones for each user, and activate them if they are met, for each user. Great implementation.
        $jqshows = jqshow::get_records();
        $date = time();
        $a = new stdClass();
        foreach ($jqshows as $jqshow) {
            $sessions = jqshow_sessions::get_records(['jqshowid' => $jqshow->get('id')], 'startdate');
            $activesession = jqshow_sessions::get_record(['jqshowid' => $jqshow->get('id'),
                'status' => sessionsmodel::SESSION_STARTED]);
            if ($activesession !== false) {
                $activesessionmode = $activesession->get('sessionmode');
                if ($activesessionmode === sessionsmodel::INACTIVE_MANUAL ||
                    $activesessionmode === sessionsmodel::PODIUM_MANUAL ||
                    $activesessionmode === sessionsmodel::RACE_MANUAL) {
                    // A manual session is active, and will prevail over scheduled sessions until it ends in this jqshow.
                    $a->sessionid = $activesession->get('id');
                    $a->jqshowid = $activesession->get('jqshowid');
                    mtrace(get_string('sessionmanualactivated', 'mod_jqshow', $a));
                    continue;
                }
            }
            $activated = false;
            foreach ($sessions as $session) {
                if ($session->get('status') !== sessionsmodel::SESSION_FINISHED && $session->get('automaticstart') !== 0) {
                    if ($activesession === false &&
                        $session->get('startdate') <= $date &&
                        $session->get('enddate') > $date &&
                        $session->get('status') === sessionsmodel::SESSION_ACTIVE) {
                        // We start the session if it is in session.
                        (new jqshow_sessions($session->get('id')))->set('status', sessionsmodel::SESSION_STARTED)->update();
                        $activated = true;
                        $a->sessionid = $session->get('id');
                        $a->jqshowid = $session->get('jqshowid');
                        mtrace(get_string('sessionactivated', 'mod_jqshow', $a));
                    }
                    if ($session->get('enddate') <= $date) {
                        // We end the session if you have complied.
                        (new jqshow_sessions($session->get('id')))->set('status', sessionsmodel::SESSION_FINISHED)->update();
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
            $activesessions = jqshow_sessions::get_records(['jqshowid' => $jqshow->get('id'),
                'status' => sessionsmodel::SESSION_STARTED], 'timemodified');
            if (count($activesessions) > 1) {
                array_shift($activesessions);
                foreach ($activesessions as $activesession) {
                    (new jqshow_sessions($activesession->get('id')))->set('status', sessionsmodel::SESSION_FINISHED)->update();
                    $a->sessionid = $activesession->get('id');
                    $a->jqshowid = $activesession->get('jqshowid');
                    mtrace(get_string('sessionfinishedformoreone', 'mod_jqshow', $a));
                }
            }
        }
    }
}
