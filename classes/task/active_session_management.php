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
 * Active session management task
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\task;

use coding_exception;
use core\invalid_persistent_exception;
use core\task\scheduled_task;
use lang_string;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet;
use mod_kuet\persistents\kuet_sessions;
use stdClass;

/**
 * active session management task class
 */
class active_session_management extends scheduled_task {
    /**
     * Get name
     *
     * @return lang_string|string
     * @throws coding_exception
     */
    public function get_name(): string {
        return get_string('activesessionmanagement', 'mod_kuet');
    }

    /**
     * Execute task
     *
     * @return void
     * @throws invalid_persistent_exception
     * @throws coding_exception
     */
    public function execute(): void {
        // 3IP provide for time zones for each user, and activate them if they are met, for each user. Great implementation.
        $kuets = kuet::get_records();
        $date = time();
        $a = new stdClass();
        foreach ($kuets as $kuet) {
            $sessions = kuet_sessions::get_records(['kuetid' => $kuet->get('id')], 'startdate');
            $sessionstarted = kuet_sessions::get_record(['kuetid' => $kuet->get('id'),
                'status' => sessions::SESSION_STARTED]);
            if ($sessionstarted !== false) {
                $sessionstartedmode = $sessionstarted->get('sessionmode');
                if (
                    $sessionstartedmode === sessions::INACTIVE_MANUAL ||
                    $sessionstartedmode === sessions::PODIUM_MANUAL ||
                    $sessionstartedmode === sessions::RACE_MANUAL
                ) {
                    // A manual session is active, and will prevail over scheduled sessions until it ends in this kuet.
                    $a->sessionid = $sessionstarted->get('id');
                    $a->kuetid = $sessionstarted->get('kuetid');
                    mtrace(get_string('sessionmanualactivated', 'mod_kuet', $a));
                    continue;
                }
            }
            $activated = false;
            foreach ($sessions as $session) {
                if ($session->get('status') === sessions::SESSION_CREATING) {
                    continue;
                }
                if ($session->get('status') !== sessions::SESSION_FINISHED && $session->get('automaticstart') !== 0) {
                    if (
                        $sessionstarted === false &&
                        $session->get('startdate') <= $date &&
                        $session->get('enddate') > $date &&
                        $session->get('status') === sessions::SESSION_ACTIVE
                    ) {
                        // We start the session if it is in session.
                        (new kuet_sessions($session->get('id')))->set('status', sessions::SESSION_STARTED)->update();
                        $activated = true;
                        $a->sessionid = $session->get('id');
                        $a->kuetid = $session->get('kuetid');
                        mtrace(get_string('sessionactivated', 'mod_kuet', $a));
                    }
                    if (
                        $session->get('enddate') <= $date &&
                        ($session->get('status') === sessions::SESSION_STARTED ||
                            $session->get('status') === sessions::SESSION_ACTIVE)
                    ) {
                        // We end the session if you have complied.
                        (new kuet_sessions($session->get('id')))->set('status', sessions::SESSION_FINISHED)->update();
                        (new kuet_sessions($session->get('id')))->set('enddate', time())->update();
                        $a->sessionid = $session->get('id');
                        $a->kuetid = $session->get('kuetid');
                        mtrace(get_string('sessionfinished', 'mod_kuet', $a));
                    }
                }
                if ($activated === true) {
                    // The nearest session has been activated, so other sessions are ignored to avoid conflicts.
                    break;
                }
            }
            $sessionsstarted = kuet_sessions::get_records(['kuetid' => $kuet->get('id'),
                'status' => sessions::SESSION_STARTED], 'timemodified');
            if (count($sessionsstarted) > 1) {
                array_shift($sessionsstarted);
                foreach ($sessionsstarted as $sessionstarted) {
                    (new kuet_sessions($sessionstarted->get('id')))->set('status', sessions::SESSION_FINISHED)->update();
                    $a->sessionid = $sessionstarted->get('id');
                    $a->kuetid = $sessionstarted->get('kuetid');
                    mtrace(get_string('sessionfinishedformoreone', 'mod_kuet', $a));
                }
            }
        }
    }
}
