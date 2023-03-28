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

use mod_jqshow\websocketuser;

define('CLI_SCRIPT', true);
require_once __DIR__ . '/../../../config.php';

require_once __DIR__ . '/websockets.php';

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class testserver extends websockets {

    // protected $maxBufferSize = 1048576; // 1MB

    /**
     * @param $addr
     * @param $port
     * @param $bufferLength
     * @throws coding_exception
     * @throws dml_exception
     */
    public function __construct($addr, $port, $bufferLength) {
        parent::__construct($addr, $port, $bufferLength);
    }

    /**
     * @param $user
     * @param $message
     * @return void
     * @throws JsonException
     */
    protected function process($user, $message) {
        $data = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        $response_text = $this->get_response_from_action($user, $data['action'], $data);
        foreach ($this->users as $usersaved) {
            fwrite($usersaved->socket, $response_text, strlen($response_text));
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
     * @param string $user_action
     * @param array $data
     * @return string
     * @throws JsonException
     */
    protected function get_response_from_action(websocketuser $user, string $user_action, array $data): string {
        if ($user_action === 'shutdownTest') {
            foreach ($this->sockets as $socket) {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);
            }
            die();
        }
        return '';
    }
}

$port = get_config('jqshow', 'port') !== false ? get_config('jqshow', 'port') : '8080';
$server= new testserver("0.0.0.0", $port, 2048);


try {
    $server->run();
}
catch (Exception $e) {
    $server->stdout($e->getMessage());
}

