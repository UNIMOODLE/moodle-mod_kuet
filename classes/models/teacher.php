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
 * Teacher model
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\models;

use coding_exception;
use context_module;
use DateInterval;
use DateTimeImmutable;
use dml_exception;
use mod_kuet\helpers\sessions as sessionshelper;
use mod_kuet\kuet;
use mod_kuet\models\sessions as sessionsmodel;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * teacher model class
 */
class teacher extends user {

    /**
     * Export
     *
     * @param int $cmid
     * @return Object
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export(int $cmid): Object {
        return $this->export_sessions($cmid);
    }

    /**
     * Export session
     *
     * @param int $cmid
     * @return Object|stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function export_sessions(int $cmid): Object {
        $kuet = new kuet($cmid);
        $actives = [];
        $inactives = [];
        $sessions = $kuet->get_sessions();
        $coursemodulecontext = context_module::instance($cmid);
        $managesessions = has_capability('mod/kuet:managesessions', $coursemodulecontext);
        $initsession = has_capability('mod/kuet:startsession', $coursemodulecontext);
        foreach ($sessions as $session) {
            $ds = sessionshelper::get_data_session($session, $cmid, $managesessions, $initsession);
            if ((int)$session->get('status') !== sessionsmodel::SESSION_FINISHED) {
                $actives[] = $ds;
            } else {
                $inactives[] = $ds;
            }
        }
        $actives = $this->get_sessions_conflicts($actives);
        $data = new stdClass();
        $data->activesessions = $actives;
        $data->endedsessions = $inactives;
        $data->courseid = $kuet->course->id;
        $data->kuetid = $kuet->cm->instance;
        $data->cmid = $cmid;
        $data->createsessionurl = (new moodle_url('/mod/kuet/sessions.php', ['cmid' => $cmid, 'page' => 1]))->out(false);
        $qrcode = generate_kuet_qrcode((new moodle_url('/mod/kuet/view.php', ['id' => $cmid]))->out(false));
        $data->hasqrcodeimage = $qrcode !== '';
        $data->urlqrcode = $data->hasqrcodeimage === true ? $qrcode : '';
        $data->hasactivesession = kuet_sessions::get_active_session_id(($kuet->get_kuet())->id) !== 0;
        return $data;
    }

    /**
     * Get sessions conflicts
     *
     * @param array $sessions
     * @return array
     */
    private function get_sessions_conflicts(array $sessions): array {
        usort($sessions, static function($a, $b) {
            if (isset($a->startdate, $b->startdate)) {
                return $a->startdate <=> $b->startdate;
            }
        });
        foreach ($sessions as $session1) {
            foreach ($sessions as $session2) {
                $session1->automaticstart = $session1->automaticstart ?? 0;
                $session2->automaticstart = $session2->automaticstart ?? 0;
                if (($session1->automaticstart && $session2->automaticstart) && ($session1->sessionid !== $session2->sessionid)) {
                    if ($session1->startdate <= $session2->startdate && $session1->enddate >= $session2->startdate) {
                        $session2->hasconflict = true;
                        $session2->initsession = false;
                    }
                    if ($session2->startdate <= $session1->startdate && $session2->enddate >= $session1->startdate) {
                        $session1->hasconflict = true;
                        $session1->initsession = false;
                    }
                }
            }
        }
        return $sessions;
    }
}
