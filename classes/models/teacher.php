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

namespace mod_jqshow\models;

use coding_exception;
use context_course;
use context_module;
use dml_exception;
use Endroid\QrCode\Exception\InvalidWriterException;
use mod_jqshow\helpers\sessions as sessionshelper;
use mod_jqshow\jqshow;
use mod_jqshow\models\sessions as sessionsmodel;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use moodle_url;
use RuntimeException;
use stdClass;

class teacher extends user {

    /**
     * @param $cmid
     * @return Object
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export($cmid) : Object {
        return $this->export_sessions($cmid);
    }

    /**
     * @param $cmid
     * @return Object
     * @throws InvalidWriterException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function export_sessions($cmid) : Object {
        global $CFG;
        $jqshow = new jqshow($cmid);
        $actives = [];
        $inactives = [];
        $sessions = $jqshow->get_sessions();
        $coursemodulecontext = context_module::instance($cmid);
        $managesessions = has_capability('mod/jqshow:managesessions', $coursemodulecontext);
        $initsession = has_capability('mod/jqshow:startsession', $coursemodulecontext);
        foreach ($sessions as $session) {
            $ds = sessionshelper::get_data_session($session, $cmid, $managesessions, $initsession);
            if ((int)$session->get('status') !== sessionsmodel::SESSION_FINISHED) {
                $actives[] = $ds;
            } else {
                $inactives[] = $ds;
            }
        }
        if (!file_exists($CFG->localcachedir . '/mod_jqshow/qrfile_' . $cmid . '.svg')) {
            generate_jqshow_qrcode((new moodle_url('/mod/jqshow/view.php', ['id' => $cmid]))->out(false), $cmid);
        }
        $actives = $this->get_sessions_conflicts($actives);
        $data = new stdClass();
        $data->activesessions = $actives;
        $data->endedsessions = $inactives;
        $data->courseid = $jqshow->course->id;
        $data->jqshowid = $jqshow->cm->instance;
        $data->cmid = $cmid;
        $data->createsessionurl = (new moodle_url('/mod/jqshow/sessions.php', ['cmid' => $cmid, 'page' => 1]))->out(false);
        $data->hasqrcodeimage = file_exists($CFG->localcachedir . '/mod_jqshow/qrfile_' . $cmid . '.svg');
        $data->urlqrcode = $data->hasqrcodeimage === true ? file_get_contents( $CFG->localcachedir . '/mod_jqshow/qrfile_' . $cmid . '.svg') : '';
        $data->hasactivesession = jqshow_sessions::get_active_session_id(($jqshow->get_jqshow())->id) !== 0;
        return $data;
    }

    /**
     * @param array $sessions
     * @return array
     */
    private function get_sessions_conflicts(array $sessions): array {
        $timestamps = [];// Avoid a notice.
        foreach ($sessions as $key => $session) {
            $timestamps[$key] = $session->startdate ?? 0;
        }
        array_multisort($timestamps, SORT_ASC, $sessions);
        foreach ($sessions as $session1) {
            foreach ($sessions as $session2) {
                $session1->automaticstart = $session1->automaticstart ?? 0;
                $session2->automaticstart = $session2->automaticstart ?? 0;
                if (($session1->automaticstart && $session2->automaticstart) && ($session1->sessionid !== $session2->sessionid)) {
                    if ($session1->startdate < $session2->startdate && $session1->enddate > $session2->startdate) {
                        $session2->hasconflict = true;
                        $session2->initsession = false;
                    }
                    if ($session2->startdate < $session1->startdate && $session2->enddate > $session1->startdate) {
                        $session1->hasconflict = true;
                        $session1->initsession = false;
                    }
                }
            }
        }
        return $sessions;
    }
}
