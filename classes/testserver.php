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
 * @package    mod_jqshow
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use mod_jqshow\websocketuser;

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/websockets.php');

class testserver extends websockets {

    /**
     * @param $user
     * @param $message
     * @return void
     * @throws JsonException
     */
    protected function process($user, $message) {
        $data = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        $responsetext = $this->get_response_from_action($user, $data['action'], $data);
        foreach ($this->users as $usersaved) {
            fwrite($usersaved->socket, $responsetext, strlen($responsetext));
        }
    }

    /**
     * @param $user
     * @return void
     * @throws JsonException
     */
    protected function connected($user) {
        $response = $this->mask(
            json_encode([
                'action' => 'newuser'
            ], JSON_THROW_ON_ERROR)
        );
        foreach ($this->users as $usersaved) {
            fwrite($usersaved->socket, $response, strlen($response));
        }
    }

    /**
     * @param $user
     * @return void
     * @throws JsonException
     */
    protected function closed($user) {
        $response = $this->mask(
            json_encode([
                'action' => 'userdisconnected'
            ], JSON_THROW_ON_ERROR));
        foreach ($this->users as $usersaved) {
            fwrite($usersaved->socket, $response, strlen($response));
        }
    }

    /**
     * @param websocketuser $user
     * @param string $useraction
     * @param array $data
     * @return string
     * @throws JsonException
     */
    protected function get_response_from_action(websocketuser $user, string $useraction, array $data): string {
        if ($useraction === 'shutdownTest') {
            foreach ($this->sockets as $socket) {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);
            }
            die();
        }
        return '';
    }
}

$port = get_config('jqshow', 'localport') !== false ? get_config('jqshow', 'localport') : '8080';
$server = new testserver("0.0.0.0", $port, 2048);

try {
    $server->run();
} catch (Exception $e) {
    $server->stdout($e->getMessage());
}

