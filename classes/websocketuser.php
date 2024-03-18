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
 * Websocket user
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
declare(strict_types=1);
namespace mod_kuet;

/**
 * Websocket user class
 */
class websocketuser {

    /**
     * @var socket
     */
    public $socket;
    /**
     * @var int user socket id
     */
    public $usersocketid;
    /**
     * @var string ip
     */
    public $ip;
    /**
     * @var array headers
     */
    public $headers = [];
    /**
     * @var string moodle username
     */
    public $dataname; // Moodle Username.
    /**
     * @var string user picture
     */
    public $picture;
    /**
     * @var bool is teacher flag
     */
    public $isteacher;
    /**
     * @var bool handshake
     */
    public $handshake = false;
    /**
     * @var int course module id
     */
    public $cmid;
    /**
     * @var int session id
     */
    public $sid;
    /**
     * @var int user id
     */
    public $userid;

    /**
     * Constructor
     *
     * @param $id
     * @param $socket
     * @param $ip
     */
    public function __construct($id, $socket, $ip) {
        $this->usersocketid = $id;
        $this->socket = $socket;
        $this->ip = $ip;
        $this->handshake = true;
    }

    /**
     *  Update user data
     *
     * @param $data
     * @return void
     */
    public function update_user($data) {
        $this->cmid = $data['cmid'];
        $this->sid = $data['sid'];
    }
}

