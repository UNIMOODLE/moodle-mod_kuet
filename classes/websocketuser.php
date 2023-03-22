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
declare(strict_types=1);
namespace mod_jqshow;

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class websocketuser {

    public $socket;
    public $id;
    public $ip;
    public $headers = [];
    public $dataname; // Moodle Username.
    public $dataid; // Moodle userid.
    public $isteacher;
    public $handshake = false;

    /**
     * @param $id
     * @param $socket
     * @param $ip
     */
    public function __construct($id, $socket, $ip) {
        $this->id = $id;
        $this->socket = $socket;
        $this->ip = $ip;
        $this->handshake = true;
    }
}

