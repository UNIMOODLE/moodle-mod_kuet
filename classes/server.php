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

use mod_jqshow\persistents\jqshow_sessions;
use mod_jqshow\websocketuser;

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/websockets.php');

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class server extends websockets {

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
                'action' => 'newuser',
                'userid' => $user->id ?? '',
                'count' => count($this->users)
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
     * @throws dml_exception
     */
    protected function closed($user) {
        $response = $this->mask(
            json_encode([
                'action' => 'userdisconnected',
                'id' => $user->id,
                'message' => '<span style="color: red">El estudiante ' . $user->dataname . ' se ha desconectado</span>',
                'count' => count($this->users)
            ], JSON_THROW_ON_ERROR));
        foreach ($this->users as $usersaved) {
            fwrite($usersaved->socket, $response, strlen($response));
        }
        if ($user->isteacher) {
            // END session, close sockets and server.
            foreach ($this->sockets as $socket) {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);
            }
            jqshow_sessions::mark_session_finished((int)$user->sid);
            die();
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
        // Prepare data to be sent to client.
        switch ($useraction) {
            case 'newuser':
                $this->users[$user->id]->dataname = $data['name'];
                $this->users[$user->id]->dataid = $data['id'];
                $this->users[$user->id]->sid = $data['sid'];
                $this->users[$user->id]->cmid = $data['cmid'];
                $user->cmid = $data['cmid'];
                $user->sid = $data['sid'];
                if ($data['isteacher']) {
                    $user->isteacher = true;
                    $this->users[$user->id]->isteacher = true;
                }
                return $this->mask(
                    json_encode([
                        'action' => 'newuser',
                        'name' => $data['name'] ?? '',
                        'userid' => $user->id ?? '',
                        'message' => '<span style="color: green">El estudiante ' . $user->dataname . ' se ha conectado</span>',
                        'count' => count($this->users),
                    ], JSON_THROW_ON_ERROR)
                );
            case 'countusers':
                return $this->mask(
                    json_encode([
                        'action' => 'countusers',
                        'count' => count($this->users),
                    ], JSON_THROW_ON_ERROR)
                );
            case 'teacherSend':
                return $this->mask(
                    json_encode([
                        'action' => 'teacherSend',
                        'message' => '<span style="color: orange">El Profesor ha pulsado el botón</span>'
                    ], JSON_THROW_ON_ERROR)
                );
            case 'studentSend':
                return $this->mask(
                    json_encode([
                        'action' => 'studentSend',
                        'message' => '<span style="color: blue">El estudiante ' . $data['name'] . ' ha pulsado el botón</span>'
                    ], JSON_THROW_ON_ERROR)
                );
            case 'shutdownTest':
                foreach ($this->sockets as $socket) {
                    stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                    fclose($socket);
                }
                die();
            default:
                return '';
        }
    }
}

$server = new server("0.0.0.0", "8080", 2048);

try {
    $server->run();
} catch (Exception $e) {
    $server->stdout($e->getMessage());
}
