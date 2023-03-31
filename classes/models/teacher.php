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
use mod_jqshow\jqshow;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_url;
use stdClass;

class teacher extends user {

    /**
     * @return Object
     * @throws coding_exception
     */
    public function export() : Object {
        $data = new stdClass();
        // Depending on parameter  the data returned is different.
        $tab = optional_param('tab', 'sessions', PARAM_RAW);
        switch ($tab) {
            case 'sessions':
                $data = $this->export_sessions();
                break;
            case 'reports':
                $data = $this->export_reports();
                break;
            default:
                echo "No existe ese caso";
        }

        return $data;
    }

    /**
     * @return Object
     */
    public function export_sessions() : Object {
        // TODO.
        $cmid = optional_param('id', 0, PARAM_INT);
        $jqshow = new jqshow($cmid);
        $actives = [];
        $inactives = [];
        /** @var jqshow_sessions[] $sessions */
        $sessions = $jqshow->get_sessions();
        foreach ($sessions as $session) {
            $ds = new stdClass();
            $ds->name = $session->get('name');
            if ($session->get('status')) {
                $actives[] = $ds;
            } else {
                $inactives[] = $ds;
            }
        }
        // Active sessions.
        $data = new stdClass();
        $data->issessionview = true;
        $data->activesessions = $actives;
        $data->endedsessions = $inactives;
        $data->createsessionurl = (new moodle_url('/mod/jqshow/sessions.php', ['id' => $cmid, 'page' => 1]))->out(false);
        return $data;
    }

    /**
     * @return Object
     */
    public function export_reports() : Object {
        // TODO.
        $data = new stdClass();
        return $data;
    }
}
