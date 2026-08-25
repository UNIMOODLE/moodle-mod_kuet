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
 * CLI version of websocket server
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
declare(strict_types=1);
// Define unimoodleservercli constant.
define('UNIMOOODLESERVERCLI', value: "UNIMOODLEKUET");
// @phpcs:disable PHP0420
// @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
// @phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
/**
 * CLI version of websocket server.
 */
class unimoodleservercli extends websockets {
    /**
     * @var array students
     */
    protected $students = [];
    /**
     * @var array teacher
     */
    protected $teacher = [];
    /**
     * @var array session id users
     */
    protected $sidusers = [];
    /**
     * @var array session id groups
     */
    protected $sidgroups = [];
    /**
     * @var array session id users by group
     */
    protected $sidgroupusers = [];
    /**
     * Manual session state per scope key.
     *
     * Shape per scope: ['currentkid' => int, 'lastkid' => int].
     * `currentkid` = 0 means no question has been launched yet (or session ended).
     * `lastkid`    = 0 means the last question is not yet known by the server.
     *
     * @var array
     */
    protected $manualstate = [];
    /**
     * @var string password
     */
    protected $password = 'elktkktagqes';

    /**
     * Normalize a host-like value from Origin/Host headers.
     *
     * @param string|null $value
     * @return string
     */
    protected function normalize_scope_host(?string $value): string {
        $value = strtolower(trim((string)$value));
        if ($value === '') {
            return 'default';
        }

        $host = parse_url($value, PHP_URL_HOST);
        if ($host === false || $host === null || $host === '') {
            $host = parse_url('//' . ltrim($value, '/'), PHP_URL_HOST);
        }
        if ($host === false || $host === null || $host === '') {
            $host = preg_replace('/:\d+$/', '', $value);
        }

        $host = strtolower(trim((string)$host));
        return $host !== '' ? $host : 'default';
    }

    /**
     * Resolve the tenant host for a websocket connection.
     *
     * @param array $headers
     * @return string
     */
    protected function resolve_scope_host(array $headers): string {
        $originhost = $this->normalize_scope_host($headers['Origin'] ?? '');
        if ($originhost !== 'default' && $originhost !== 'null') {
            return $originhost;
        }

        return $this->normalize_scope_host($headers['Host'] ?? '');
    }

    /**
     * Build the in-memory scope key for a tenant/session pair.
     *
     * @param string $scopehost
     * @param int $sid
     * @return string
     */
    protected function get_scope_key(string $scopehost, int $sid): string {
        return $scopehost . '|' . $sid;
    }

    /**
     * Get the scope host already assigned to a websocket user.
     *
     * @param websocketuser $user
     * @return string
     */
    protected function get_user_scope_host(websocketuser $user): string {
        return $user->scopehost !== '' ? $user->scopehost : 'default';
    }

    /**
     * Get the scope key for a connected user.
     *
     * @param websocketuser $user
     * @return string
     */
    protected function get_user_scope_key(websocketuser $user): string {
        return $this->get_scope_key($this->get_user_scope_host($user), (int)$user->sid);
    }

    /**
     * Get the scope key for an inbound message, using the user's tenant host.
     *
     * @param websocketuser $user
     * @param array $data
     * @return string
     */
    protected function get_scope_key_from_data(websocketuser $user, array $data): string {
        $sid = isset($data['sid']) ? (int)$data['sid'] : (int)$user->sid;
        return $this->get_scope_key($this->get_user_scope_host($user), $sid);
    }

    /**
     * Run Unimoodleservercli protocol forever.
     *
     * @return mixed
     */
    public function run() {
        while (true) {
            try {
                // Check if the master socket is still valid and purge not valid sockets.
                if (!is_resource($this->master) || get_resource_type($this->master) !== 'stream') {
                    $this->stdout(self::red_text("Master socket is not a valid resource. Recreating.", false));
                    $this->create_master_socket();
                }
                // Add the master socket to the list of sockets to read from.
                $this->sockets['m'] = $this->master;

                $read = $this->sockets;
                $write = $except = null;
                $this->tick_core();
                $this->tick();
                if ($this->verboselog) {
                    $this->stdout(self::blue_text("Waiting for messages on $this->addr:$this->port", false));
                }
                stream_select($read, $write, $except, seconds: null);
                if (in_array($this->master, $read, true)) {
                    $client = @stream_socket_accept($this->master, 20);
                    if (!$client || $client === null || $client === null) {
                        continue;
                    }
                    $ip = stream_socket_get_name($client, true);
                    if (!$ip) {
                        continue;
                    }
                    $this->stdout(self::blue_text("Connection attempt from $ip", false));
                    $this->stdout(self::blue_text("----- Client " . get_resource_id($client), false));
                    if ($this->handshake($client)) {
                        // Show stats.
                        $this->stdout(self::white_text("Total users: " . count($this->users), false));
                        $this->stdout(self::white_text("Groups: " . count($this->sidgroups), false));
                        $this->stdout(self::white_text("Held messages: " . count($this->heldmessages), false));
                    } else {
                        $this->stdout(self::red_text("Handshake failed for $ip", false));
                        continue;
                    }
                    // Delete master socket from the read array to avoid processing messages bellow.
                    // This is necessary to avoid processing the master socket as a client socket.
                    $foundsocket = array_search($this->master, $read, true);
                    unset($read[$foundsocket]);

                    if ($client < 0) {
                        $this->stdout(self::red_text("Failed: socket_accept()", false));
                        continue;
                    }
                }
                foreach ($read as $socket) {
                    $ip = stream_socket_get_name($socket, true);
                    $usersocket = $this->get_user_by_socket($socket);

                    if ($usersocket === null) {
                        $this->stdout(self::red_text("Unknown user for socket $socket", false));
                        $this->disconnect($socket, true, "Unknown user for socket $socket");
                        continue;
                    }

                    // 1. Leer los bytes crudos del socket
                    $newdata = stream_get_contents($socket);

                    if ($newdata === false || $newdata === '') {
                        if (feof($socket)) {
                            $this->disconnect($socket, true, "Socket closed by remote peer $ip");
                        }
                        continue;
                    }

                    // 2. Acumular en el búfer del usuario
                    if (!isset($this->socketbuffers[$usersocket->usersocketid])) {
                        $this->socketbuffers[$usersocket->usersocketid] = '';
                    }
                    $this->socketbuffers[$usersocket->usersocketid] .= $newdata;

                    // 3. Extraer frames completos del búfer (mientras existan)
                    while ($frame = $this->shift_complete_frame_from_buffer($usersocket->usersocketid)) {
                        $firstbyte = ord($frame[0]);
                        $opcode = $firstbyte & 0x0F;
                        $isfinal = ($firstbyte & 0x80) === 0x80;

                        // Detectar si es un frame de cierre.
                        if ($opcode === 0x8) {
                            $this->disconnect($socket, true, "Disconnect message received from $ip");
                            break; // Salir del while para este usuario.
                        }

                        // RSV1-3 sin extensión negociada (RFC 6455 §5.2), p. ej. compresión
                        // permessage-deflate activada por un intermediario: cerrar de forma
                        // visible en lugar de descartar el mensaje en silencio.
                        if (($firstbyte & 0x70) !== 0) {
                            $this->stdout(self::red_text("RSV bits set without negotiated extension from $ip", false));
                            $this->disconnect($socket, true, "Unsupported compressed/RSV frame received from $ip");
                            break;
                        }

                        // PING de control (RFC 6455 §5.5.2): responder PONG con el mismo payload.
                        if ($opcode === 0x9) {
                            $this->send_pong_frame($usersocket, $this->unmask($frame));
                            continue;
                        }

                        // PONG de control (RFC 6455 §5.5.3): ignorar.
                        if ($opcode === 0xA) {
                            continue;
                        }

                        // 4. Desenmascarar el frame completo
                        $unmasked = $this->unmask($frame);

                        // Mensajes fragmentados (RFC 6455 §5.4): acumular fragmentos hasta el
                        // frame con FIN. Los navegadores actuales no fragmentan al enviar,
                        // pero un proxy intermedio sí puede hacerlo.
                        if (!$isfinal) {
                            $previous = $opcode === 0x0 ? ($this->fragmentbuffers[$usersocket->usersocketid] ?? '') : '';
                            $this->fragmentbuffers[$usersocket->usersocketid] = $previous . $unmasked;
                            continue;
                        }
                        if ($opcode === 0x0) {
                            $unmasked = ($this->fragmentbuffers[$usersocket->usersocketid] ?? '') . $unmasked;
                            unset($this->fragmentbuffers[$usersocket->usersocketid]);
                        }

                        if ($unmasked === '') {
                            continue; // Frame vacío.
                        }

                        // 5. Procesar el mensaje (JSON o comandos)
                        $isjson = $this->check_json($unmasked);

                        if ($isjson === true) {
                            $this->process($usersocket, $unmasked);
                        } else {
                            if ($unmasked == 'ping') {
                                $this->send_pong_frame($usersocket, 'pong');
                            } else if ($unmasked == 'diag') {
                                $memoryusageinmb = round(memory_get_usage() / 1024 / 1024, 2);
                                $msg = json_encode([
                                        'action' => 'diag',
                                        'sockets' => count($this->sockets),
                                        'memoryusage' => $memoryusageinmb . ' MB',
                                ], JSON_THROW_ON_ERROR);
                                $this->send_masked([$usersocket], $msg, false);
                            } else {
                                // Si llegamos aquí con basura, es porque el unmask falló o el mensaje no es JSON.
                                $this->stdout(self::red_text("Invalid message from $ip. Length: " . strlen($unmasked), false));
                                $this->disconnect($socket, true, 'Invalid message received');
                                break;
                            }
                        }
                    }
                }// End of foreach read sockets.
            } catch (Exception | Error | TypeError $e) {
                $this->stdout(self::red_text("FATAL Error: " . $e->getMessage(), false));
                // If the socket is not the master, then disconnect it.
                $this->stdout(self::red_text("Disconnecting socket due to error: " . $e->getMessage(), false));

                if (isset($socket) && is_resource($socket) && $socket !== $this->master) {
                    $this->disconnect($socket, true, $e->getMessage());
                }
            }
        }
    }

    /**
     * Read complete websocket messages from a socket using a per-user buffer.
     *
     * @param websocketuser $user
     * @param resource $socket
     * @param string $ip
     * @return array
     */
    protected function read_socket_messages(websocketuser $user, $socket, string $ip): array {
        $buffer = stream_get_contents($socket);
        if ($buffer === false || $buffer === '') {
            if (feof($socket)) {
                $this->disconnect($socket, true, "Socket closed by remote peer $ip");
            }
            return [];
        }

        if ($this->is_close_frame($buffer)) {
            $this->disconnect($socket, true, "Disconnect message received from $ip");
            return [];
        }

        $unmasked = $this->unmask($buffer);
        if ($unmasked === '') {
            return [];
        }

        if ($unmasked === "\x03\xe8" || $unmasked === "\x03\xe9") {
            $this->disconnect($socket, true, "Disconnect payload received from $ip");
            return [];
        }

        return [$unmasked];
    }

    /**
     * Detect a websocket close control frame.
     *
     * @param string $buffer
     * @return bool
     */
    protected function is_close_frame(string $buffer): bool {
        if (strlen($buffer) < 2) {
            return false;
        }

        return (ord($buffer[0]) & 0x0F) === 8;
    }

    /**
     * Extract a complete websocket frame from the buffered bytes for a user.
     *
     * @param string $usersocketid
     * @return string|null
     */
    protected function shift_complete_frame_from_buffer(string $usersocketid): ?string {
        if (!isset($this->socketbuffers[$usersocketid])) {
            return null;
        }

        $buffer = $this->socketbuffers[$usersocketid];
        $framelength = $this->get_frame_length($buffer);
        if ($framelength === null || strlen($buffer) < $framelength) {
            return null;
        }

        $frame = substr($buffer, 0, $framelength);
        $remaining = substr($buffer, $framelength);
        if ($remaining === '') {
            unset($this->socketbuffers[$usersocketid]);
        } else {
            $this->socketbuffers[$usersocketid] = $remaining;
        }

        return $frame;
    }

    /**
     * Return the total number of bytes required for a complete websocket frame.
     *
     * @param string $buffer
     * @return int|null
     */
    protected function get_frame_length(string $buffer): ?int {
        if (strlen($buffer) < 2) {
            return null;
        }

        $length = ord($buffer[1]) & 127;
        $offset = 2;
        if ($length === 126) {
            if (strlen($buffer) < 4) {
                return null;
            }
            $length = (ord($buffer[2]) << 8) + ord($buffer[3]);
            $offset = 4;
        } else if ($length === 127) {
            if (strlen($buffer) < 10) {
                return null;
            }
            $length = 0;
            for ($i = 2; $i < 10; $i++) {
                $length = ($length * 256) + ord($buffer[$i]);
            }
            $offset = 10;
        }

        if ((ord($buffer[1]) & 128) === 128) {
            $offset += 4;
        }

        return $offset + $length;
    }

    /**
     * Reply to a websocket ping control frame.
     *
     * @param websocketuser $user
     * @param string $payload
     * @return void
     */
    protected function send_pong_frame(websocketuser $user, string $payload): void {
        if (!is_resource($user->socket)) {
            return;
        }
        $frame = chr(0x8A) . chr(strlen($payload)) . $payload;
        fwrite($user->socket, $frame, strlen($frame));
    }
    /**
     * Process message
     *
     * @param $user
     * @param $message
     * @return void
     * @throws JsonException
     */
    protected function process($user, $message) {
        // Sends a message to all users on the socket belonging to the same "sid" session.
        $data = json_decode(
            mb_convert_encoding($message, 'UTF-8', 'UTF-8'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $scopekey = $this->get_scope_key_from_data($user, $data);
        if (isset($data['oft']) && $data['oft'] === true) {
            // Only for teacher.
            $responsetext = $this->get_response_from_action_for_teacher($user, $data['action'], $data);
            if ($responsetext !== '' && isset($this->sidusers[$scopekey])) {
                $this->send_masked($this->sidusers[$scopekey], $responsetext);
            }
        } else if (isset($data['ofs']) && $data['ofs'] === true) {
            // Only for student.
            $responsetext = $this->get_response_from_action_for_student($user, $data['action'], $data);
            if ($responsetext !== '') {
                $usersocket = $this->get_user_by_socket($data['usersocketid']);
                $this->send_masked([$usersocket], $responsetext);
            }
        } else if (isset($data['ofg']) && $data['ofg'] === true) {
            // Only for groups.
            $responsetext = $this->get_response_from_action_for_group($data);
            $groupid = $this->get_groupid_from_a_member($scopekey, (int)$data['userid']);
            if ($responsetext !== '' && $groupid) {
                $socketgroups = $this->sidgroups[$scopekey];
                foreach ($socketgroups[$groupid]->users as $usergroup) {
                    $socketuser = $this->get_user_by_socket($usergroup->usersocketid);
                    if ($socketuser !== null && is_resource($socketuser->socket)) {
                        $this->send_masked([$socketuser], $responsetext);
                    }
                }
            }
        } else { // All users in this sid.
            $responsetext = $this->get_response_from_action($user, $data['action'], $data);
            if ($responsetext !== '' && isset($this->sidusers[$scopekey])) {
                $this->send_masked($this->sidusers[$scopekey], $responsetext);
            }
        }
    }

    /**
     * Check connected user
     *
     * @param $user
     * @return void
     */
    protected function connected($user) {
        // 3IP log user connected. This function is called by handshake.
    }

    /**
     * Connect socket
     *
     * @param $socket
     * @param $ip
     * @return void
     * @throws JsonException
     */
    protected function connect($socket, $ip, array $headers = []) {
        $user = new websocketuser(uniqid('u', true), $socket, $ip);
        $user->headers = $headers;
        $user->host = $headers['Host'] ?? '';
        $user->origin = $headers['Origin'] ?? '';
        $user->scopehost = $this->resolve_scope_host($headers);
        // Add the user to the list of all users on the socket.
        $this->users[$user->usersocketid] = $user;
        $this->sockets[$user->usersocketid] = $socket;
        /* inactivity time for SSL client https://bugs.php.net/bug.php?id=70939
        $sock = socket_import_stream ($socket);
        socket_set_option($sock, SOL_SOCKET, SO_KEEPALIVE, 1);*/
        // We return the usersocketid only to the new user so that responds by identifying with newuser.
        $this->send_masked([$user], json_encode([
                'action' => 'connect',
                'usersocketid' => $user->usersocketid,
            ], JSON_THROW_ON_ERROR));

        $this->connecting($user);
    }
    /**
     * Send message using mask function.
     * It optionally uses a password.
     * @param array[websocketuser] $usersockets
     * @param string $message
     * @param bool $encrypt
     */
    protected function send_masked($usersockets, $message, $encrypt = true) {
        if ($encrypt) {
            $message = kuet_encrypt($this->password, $message);
        }
        $maskedmessage = $this->mask($message);

        foreach ($usersockets as $usersocket) {
            fwrite($usersocket->socket, $maskedmessage, strlen($maskedmessage));
        }
    }
    /**
     * Close group member connection to socket
     *
     * @param $user
     * @return void
     * @throws JsonException
     */
    protected function close_groupmember($user) {
        $groupmemberdisconected = false;
        $groupdisconected = false;
        $groupid = 0;
        $groupname = '';
        $numgroups = 0;
        $scopekey = $this->get_user_scope_key($user);
        if (array_key_exists($user->usersocketid, $this->sidgroupusers)) {
            $groupmemberdisconected = true;
            $groupid = $this->sidgroupusers[$user->usersocketid];
            $groupname = $this->sidgroups[$scopekey][$groupid]->groupname;
            $numusers = count($this->sidgroups[$scopekey][$groupid]->users);
            $numgroups = count($this->sidgroups[$scopekey]);
            unset($this->sidgroups[$scopekey][$groupid]->users[$user->usersocketid], $this->sidgroupusers[$user->usersocketid]);
            --$numusers;
            if ($numusers === 0) {
                unset($this->sidgroups[$scopekey][$groupid]);
                --$numgroups;
                $groupdisconected = true;
            }
        }
        if ($groupdisconected) {
            $groupresponse = json_encode(
                [
                'action' => 'groupdisconnected',
                'usersocketid' => $user->usersocketid,
                'groupid' => $groupid,
                'message' =>
                    '<span style="color: red">' . $groupname . ' disconnected </span>',
                'count' => $numgroups,
                ],
                JSON_THROW_ON_ERROR
            );
            if (isset($this->sidusers[$scopekey])) {
                $this->send_masked($this->sidusers[$scopekey], $groupresponse);
            }
        } else if ($groupmemberdisconected) {
            $groupresponse = json_encode(
                [
                'action' => 'groupmemberdisconnected',
                'usersocketid' => $user->usersocketid,
                'groupid' => $groupid,
                'message' =>
                    '<span style="color: red"> Group member ' . $user->dataname . ' has been disconnected. </span>',
                'count' => $numusers,
                ],
                JSON_THROW_ON_ERROR
            );
            if (isset($this->sidusers[$scopekey])) {
                $this->send_masked($this->sidusers[$scopekey], $groupresponse);
            }
        }
    }

    /**
     * Closed connection routine
     *
     * @param $user
     * @return void
     * @throws JsonException
     */
    protected function closed($user) {
        $scopekey = $this->get_user_scope_key($user);
        unset(
            $this->sidusers[$scopekey][$user->usersocketid],
            $this->students[$scopekey][$user->usersocketid]
        );
        // Group mode.
        $this->close_groupmember($user);
        $response = json_encode(
            [
            'action' => 'userdisconnected',
            'usersocketid' => $user->usersocketid,
            'message' =>
                '<span style="color: red">' . "User $user->dataname has been disconnected."  . '</span>',
            'count' => isset($this->students[$scopekey]) ? count($this->students[$scopekey]) : 0,
            ],
            JSON_THROW_ON_ERROR
        );
        if (isset($this->sidusers[$scopekey])) {
            $this->send_masked($this->sidusers[$scopekey], $response);
        }
        if ($user->isteacher) {
            unset($this->teacher[$scopekey]);
            if (isset($this->sidusers[$scopekey])) {
                foreach ($this->sidusers[$scopekey] as $socket) {
                    $this->disconnect($socket->socket);
                    unset($this->students[$scopekey], $this->sidusers[$scopekey]);
                }
            }
        }
    }

    /**
     * Get response from action for teacher
     *
     * @param websocketuser $user
     * @param string $useraction
     * @param array $data
     * @return string
     * @throws JsonException
     */
    protected function get_response_from_action_for_teacher(websocketuser $user, string $useraction, array $data): string {
        switch ($useraction) {
            case 'studentQuestionEnd':
                return json_encode([
                            'action' => 'studentQuestionEnd',
                            'onlyforteacher' => true,
                            'context' => $data,
                            'message' => 'El alumno ' . $data['userid'] . ' ha contestado una pregunta', // 3IP delete.
                        ], JSON_THROW_ON_ERROR);
            case 'ImproviseStudentTag':
                return json_encode([
                            'action' => 'ImproviseStudentTag',
                            'onlyforteacher' => true,
                            'improvisereply' => $data['improvisereply'],
                            'userid' => $data['userid'],
                            'message' => '',
                        ], JSON_THROW_ON_ERROR);
            case 'StudentVotedTag':
                return json_encode([
                            'action' => 'StudentVotedTag',
                            'onlyforteacher' => true,
                            'votedtag' => $data['votedtag'],
                            'userid' => $data['userid'],
                            'message' => '',
                        ], JSON_THROW_ON_ERROR);
            default:
                return '';
        }
    }

    /**
     * Get group id for a member
     *
     * @param string $scopekey
     * @param int $userid
     * @return int
     */
    protected function get_groupid_from_a_member(string $scopekey, int $userid): int {
        $groupid = 0;
        if (!array_key_exists($scopekey, $this->sidgroups)) {
            return $groupid;
        }
        foreach ($this->sidgroups[$scopekey] as $sidgroup) {
            foreach ($sidgroup->users as $member) {
                if ((int)$member->userid === $userid) {
                    $groupid = $sidgroup->groupid;
                    return $groupid;
                }
            }
        }
        return $groupid;
    }

    /**
     * Get response from action for a group
     *
     * @param array $data
     * @return string
     * @throws JsonException
     */
    protected function get_response_from_action_for_group(array $data): string {
        switch ($data['action']) {
            case 'alreadyAnswered':
                return json_encode([
                            'action' => 'alreadyAnswered',
                            'userid' => $data['userid'],
                            'kid' => $data['kid'],
                        ], JSON_THROW_ON_ERROR);
            default:
                return '';
        }
    }

    /**
     * Get response from action for student user
     *
     * @param websocketuser $user
     * @param string $useraction
     * @param array $data
     * @return string
     * @throws JsonException
     */
    protected function get_response_from_action_for_student(websocketuser $user, string $useraction, array $data): string {
        switch ($useraction) {
            case 'normalizeUser':
                // KUETEDUCAM-70: do not auto-push the current question to late joiners.
                // Late joiners must stay in the waiting room (seeing inprogress/lastquestion
                // message) and be incorporated only on the next 'question' broadcast or the
                // 'endSession' broadcast. The teacher still emits 'normalizeUser' on every
                // newuser event; we intentionally drop it server-side.
                return '';
            default:
                return '';
        }
    }

    /**
     * Get response from action
     *
     * @param websocketuser $user
     * @param string $useraction
     * @param array $data
     * @return string
     * @throws JsonException
     */
    protected function get_response_from_action(websocketuser $user, string $useraction, array $data): string {
        // Prepare data to be sent to client.
        $scopekey = $this->get_scope_key_from_data($user, $data);
        switch ($useraction) {
            case 'newgroup':
                $this->newuser($user, $data);
                $this->newgroup($user, $data);
                return $this->manage_newgroup_for_sid($user, $data);
            case 'newuser':
                $this->newuser($user, $data);
                if (isset($data['isteacher']) && $data['isteacher'] === true) {
                    return $this->manage_newteacher_for_sid($user, $data);
                }
                return $this->manage_newstudent_for_sid($user, $data);
            case 'countusers':
                return json_encode([
                            'action' => 'countusers',
                            'count' => count($this->students[$scopekey] ?? []),
                        ], JSON_THROW_ON_ERROR);
            case 'question':
                $kid = (int)($data['context']['kid'] ?? 0);
                if (!isset($this->manualstate[$scopekey])) {
                    $this->manualstate[$scopekey] = ['currentkid' => 0, 'lastkid' => 0];
                }
                $this->manualstate[$scopekey]['currentkid'] = $kid;
                return json_encode([
                            'action' => 'question',
                            'context' => $data['context'],
                        ], JSON_THROW_ON_ERROR);
            case 'ranking':
                return json_encode([
                            'action' => 'ranking',
                            'context' => $data['context'],
                        ], JSON_THROW_ON_ERROR);
            case 'endSession':
                $this->manualstate[$scopekey] = ['currentkid' => 0, 'lastkid' => 0];
                return json_encode([
                            'action' => 'endSession',
                            'context' => $data['context'],
                        ], JSON_THROW_ON_ERROR);
            case 'lastquestion':
                // Teacher tells us which kid is the last question of the session.
                // Not broadcast: only used to compute waitingRoomState for late joiners.
                if (!isset($this->manualstate[$scopekey])) {
                    $this->manualstate[$scopekey] = ['currentkid' => 0, 'lastkid' => 0];
                }
                $this->manualstate[$scopekey]['lastkid'] = (int)($data['kid'] ?? 0);
                return '';
            case 'teacherQuestionEnd':
                return json_encode([
                            'action' => 'teacherQuestionEnd',
                            'kid' => $data['kid'],
                            'statistics' => $data['statistics'],
                        ], JSON_THROW_ON_ERROR);
            case 'pauseQuestion':
                return json_encode([
                            'action' => 'pauseQuestion',
                            'kid' => $data['kid'],
                        ], JSON_THROW_ON_ERROR);
            case 'playQuestion':
                return json_encode([
                            'action' => 'playQuestion',
                            'kid' => $data['kid'],
                        ], JSON_THROW_ON_ERROR);
            case 'showAnswers':
                return json_encode([
                            'action' => 'showAnswers',
                            'kid' => $data['kid'],
                        ], JSON_THROW_ON_ERROR);
            case 'hideAnswers':
                return json_encode([
                            'action' => 'hideAnswers',
                            'kid' => $data['kid'],
                        ], JSON_THROW_ON_ERROR);
            case 'showStatistics':
                return json_encode([
                            'action' => 'showStatistics',
                            'kid' => $data['kid'],
                        ], JSON_THROW_ON_ERROR);
            case 'hideStatistics':
                return json_encode([
                            'action' => 'hideStatistics',
                            'kid' => $data['kid'],
                        ], JSON_THROW_ON_ERROR);
            case 'showFeedback':
                return json_encode([
                            'action' => 'showFeedback',
                            'kid' => $data['kid'],
                        ], JSON_THROW_ON_ERROR);
            case 'hideFeedback':
                return  json_encode([
                            'action' => 'hideFeedback',
                            'kid' => $data['kid'],
                        ], JSON_THROW_ON_ERROR);
            case 'improvising':
                return json_encode([
                            'action' => 'improvising',
                            'kid' => $data['kid'],
                        ], JSON_THROW_ON_ERROR);
            case 'closeImprovise':
                return json_encode([
                            'action' => 'closeImprovise',
                        ], JSON_THROW_ON_ERROR);
            case 'improvised':
                return json_encode([
                            'action' => 'improvised',
                            'improvisestatement' => $data['improvisestatement'],
                            'improvisereply' => $data['improvisereply'],
                            'cmid' => $data['cmid'],
                            'sessionid' => $data['sid'],
                        ], JSON_THROW_ON_ERROR);
            case 'printNewTag':
                return json_encode([
                            'action' => 'printNewTag',
                            'tags' => $data['tags'],
                        ], JSON_THROW_ON_ERROR);
            case 'initVote':
                return json_encode([
                            'action' => 'initVote',
                        ], JSON_THROW_ON_ERROR);
            case 'shutdownTest':
            default:
                return '';
        }
    }

    /**
     * Set new user for the websocket
     *
     * @param websocketuser $user
     * @param array $data
     * @return void
     */
    private function newuser(websocketuser $user, array $data): void {
        // If reported usersocketid does not march stored usersocketid, then throw error. Possible attack.
        if ($data['usersocketid'] !== $user->usersocketid) {
            throw new Exception('Reported usersocketid does not match stored usersocketid. Possible attack.');
        }
        $this->users[$user->usersocketid]->dataname = $data['name'];
        $this->users[$user->usersocketid]->picture = $data['pic'];
        $this->users[$user->usersocketid]->userid = $data['userid'];
        $this->users[$user->usersocketid]->usersocketid = $data['usersocketid'];
        $this->users[$user->usersocketid]->sid = $data['sid'];
        $this->users[$user->usersocketid]->cmid = $data['cmid'];
        $user->update_user($data);
    }

    /**
     * Set new group for websocket
     *
     * @param websocketuser $user
     * @param array $data
     * @return void
     */
    private function newgroup(websocketuser $user, array $data): void {
        $scopekey = $this->get_scope_key_from_data($user, $data);
        if (!array_key_exists($scopekey, $this->sidgroups)) {
            $this->sidgroups[$scopekey] = [];
        }
        if (!array_key_exists($data['groupid'], $this->sidgroups[$scopekey])) {
            $this->sidgroups[$scopekey][$data['groupid']] = new stdClass();
            $this->sidgroups[$scopekey][$data['groupid']]->users = [];
        }
        $this->sidgroups[$scopekey][$data['groupid']]->groupid = $data['groupid'];
        $this->sidgroups[$scopekey][$data['groupid']]->groupname = $data['name'];
        $this->sidgroups[$scopekey][$data['groupid']]->grouppicture = $data['pic'];
        $this->sidgroups[$scopekey][$data['groupid']]->sid = $data['sid'];
        $this->sidgroups[$scopekey][$data['groupid']]->cmid = $data['cmid'];
        if (!array_key_exists($data['usersocketid'], $this->sidgroups[$scopekey][$data['groupid']]->users)) {
            $this->sidgroups[$scopekey][$data['groupid']]->users[$user->usersocketid] = new stdClass();
            $this->sidgroups[$scopekey][$data['groupid']]->users[$user->usersocketid]->usersocketid = $data['usersocketid'];
            $this->sidgroups[$scopekey][$data['groupid']]->users[$user->usersocketid]->userid = $data['userid'];
            $this->sidgroupusers[$data['usersocketid']] = $data['groupid'];
        }
    }

    /**
     * Resolve the waiting-room state a late-joining student should see.
     *
     * @param string $scopekey
     * @return string One of 'noquestion' | 'inprogress' | 'lastquestion'.
     */
    private function get_waiting_room_state_for_scope(string $scopekey): string {
        if (!isset($this->manualstate[$scopekey])) {
            return 'noquestion';
        }
        $state = $this->manualstate[$scopekey];
        if ((int)$state['currentkid'] === 0) {
            return 'noquestion';
        }
        if ((int)$state['lastkid'] !== 0 && (int)$state['currentkid'] === (int)$state['lastkid']) {
            return 'lastquestion';
        }
        return 'inprogress';
    }

    /**
     * Privately send the current waiting-room state to a single user.
     *
     * @param websocketuser $user
     * @param string $scopekey
     * @return void
     * @throws JsonException
     */
    private function send_waiting_room_state_to_user(websocketuser $user, string $scopekey): void {
        $payload = json_encode([
            'action' => 'waitingRoomState',
            'state' => $this->get_waiting_room_state_for_scope($scopekey),
        ], JSON_THROW_ON_ERROR);
        $this->send_masked([$user], $payload);
    }

    /**
     * Manage new teacher user for session id
     *
     * @param websocketuser $user
     * @param array $data
     * @return string
     * @throws JsonException
     */
    private function manage_newteacher_for_sid(websocketuser $user, array $data): string {
        $scopekey = $this->get_scope_key_from_data($user, $data);
        if (isset($this->teacher[$scopekey]) && count($this->teacher[$scopekey]) === 1) {
            // There can only be one teacher in each session to avoid conflicts of functionality.
            $response = json_encode([
                        'action' => 'alreadyteacher',
                        'message' => 'There is already a teacher controlling this session, so you cannot connect.' .
                            'Please wait for the current session to end before you can enter.',
                    ], JSON_THROW_ON_ERROR);
            $socketuser = $this->users[$user->usersocketid] ?? null;
            if ($socketuser !== null) {
                $this->send_masked([$socketuser], $response);
                $this->disconnect($socketuser->socket);
            }
            return '';
        }
        $user->isteacher = true;
        $this->users[$user->usersocketid]->isteacher = true;
        $this->teacher[$scopekey][$user->usersocketid] = $this->users[$user->usersocketid];
        $this->sidusers[$scopekey][$user->usersocketid] = $this->users[$user->usersocketid];
        return json_encode([
                'action' => 'newteacher',
                'name' => $data['name'] ?? '',
                'userid' => $user->id ?? '',
                'message' => '<span style="color: green">The teacher ' . $user->dataname . ' has connected</span>',
                'count' => isset($this->sidusers[$scopekey]) ? count($this->sidusers[$scopekey]) : 0,
            ], JSON_THROW_ON_ERROR);
    }

    /**
     * Manage new student user for session id
     *
     * @param websocketuser $user
     * @param array $data
     * @return string
     * @throws JsonException
     */
    private function manage_newstudent_for_sid(websocketuser $user, array $data): string {
        $scopekey = $this->get_scope_key_from_data($user, $data);
        $duplicateresolve = false;
        if (isset($this->students[$scopekey])) {
            foreach ($this->students[$scopekey] as $usersocketid => $studentsid) {
                if ($studentsid->userid === $data['userid']) {
                    // There can only be one same user in each session to avoid conflicts of functionality.
                    foreach ($this->sockets as $key => $socket) {
                        if ($key === $usersocketid) {
                            $this->disconnect($socket);
                            $duplicateresolve = true;
                            break;
                        }
                    }
                }
                if ($duplicateresolve === true) {
                    break;
                }
            }
        }
        $this->users[$user->usersocketid]->isteacher = false;
        $this->sidusers[$scopekey][$user->usersocketid] = $this->users[$user->usersocketid];
        $this->students[$scopekey][$user->usersocketid] = $this->users[$user->usersocketid];
        $studentsdata = [];
        foreach ($this->students[$scopekey] as $key => $student) {
            $studentsdata[$key]['picture'] = $student->picture;
            $studentsdata[$key]['usersocketid'] = $student->usersocketid;
            $studentsdata[$key]['name'] = $student->dataname;
        }
        $this->send_waiting_room_state_to_user($user, $scopekey);
        return json_encode([
                'action' => 'newuser',
                'usersocketid' => $user->usersocketid,
                'students' => array_values($studentsdata),
                'count' => count($this->students[$scopekey]),
            ], JSON_THROW_ON_ERROR);
    }

    /**
     * Manage new users group for session id
     *
     * @param websocketuser $user
     * @param array $data
     * @return string
     * @throws JsonException
     */
    private function manage_newgroup_for_sid(websocketuser $user, array $data): string {
        $scopekey = $this->get_scope_key_from_data($user, $data);
        $this->users[$user->usersocketid]->isteacher = false;
        $this->sidusers[$scopekey][$user->usersocketid] = $this->users[$user->usersocketid];
        $this->students[$scopekey][$user->usersocketid] = $this->users[$user->usersocketid];

        $groupsdata = [];
        foreach ($this->sidgroups[$scopekey] as $key => $group) {
            $groupsdata[$key]['groupid'] = $group->groupid;
            $groupsdata[$key]['picture'] = $group->grouppicture;
            $groupsdata[$key]['usersocketid'] = $data['usersocketid'];
            $groupsdata[$key]['name'] = $group->groupname;
            $groupsdata[$key]['numgroupusers'] = count($group->users);
        }
        $this->send_waiting_room_state_to_user($user, $scopekey);
        return json_encode([
                    'action' => 'newgroup',
                    'usersocketid' => $user->usersocketid,
                    'groups' => array_values($groupsdata),
                    'count' => count($this->sidgroups[$scopekey]),
                ], JSON_THROW_ON_ERROR);
    }
}

/**
 * Websocket class.
 *
 */
abstract class websockets {
    /**
     * @var int max buffer size
     */
    protected $maxbuffersize;
    /**
     * @var false|resource master
     */
    protected $master;
    /**
     * @var array sockets
     */
    protected $sockets = [];
    /**
     * @var array users
     */
    protected $users = [];
    /**
     * @var array buffered bytes indexed by usersocketid
     */
    protected $socketbuffers = [];
    /**
     * @var array fragmented websocket payloads indexed by usersocketid
     */
    protected $fragmentbuffers = [];
    /**
     * @var array held message
     */
    protected $heldmessages = [];
    /**
     * @var bool interactive
     */
    protected $interactive = true;
    /**
     * @var string IP address
     */
    protected $addr;
    /**
     * @var int port
     */
    protected $port;
    /**
     * @var string certificate file
     */
    protected $certificate;
    /**
     * @var string private key file
     */
    protected $privatekey;
    /**
     * @var bool use ssl
     */
    protected $usessl = false;
    /**
     * @var string network transport protocol
     */
    protected $transport;
    /**
     * @var bool verboselog
     */
    protected $verboselog = false;
    /**
     * SSL transport protocol
     */
    const SECURE_TRANSPORT = 'ssl';
    /**
     * No ssl transport protocol
     */
    const INSECURE_TRANSPORT = 'tcp';
    /**
     * @var array ANSI color codes
     */
    private static array $colors = [
        'red' => '31',
        'green' => '32',
        'yellow' => '33',
        'blue' => '34',
        'magenta' => '35',
        'cyan' => '36',
        'white' => '37',
    ];
    /**
     * Constructor
     *
     * @param $addr
     * @param $bufferlength
     * @throws Exception
     */
    public function __construct($addr, $bufferlength = 16000) {
        global $_SERVER;

        // Check minimal prerequisites for run this server.
        $this->check_prerequisites();

        $this->addr = $addr;
        $usessl = false;
        if (PHP_SAPI !== 'cli') {
            throw new Exception('This application must be run on the command line.');
        }
        // Get from command line arguments:
        // * Port number.
        // * Certificate file (optional).
        // * Private key file (optional).
        // * Buffer length (optional).
        // * Verbose mode (optional).
        // Parse command line arguments.
        // unimoodleservercli.php port [-c certificatefile -p privatekeyfile] [-b bufferlength] [-v].
        if (isset($_SERVER['argv'][1]) && is_numeric($_SERVER['argv'][1])) {
            $port = $_SERVER['argv'][1];
            unset($_SERVER['argv'][1]);
            $_SERVER['argv'] = array_values($_SERVER['argv']);
        }
                // If the port is not set, then show the interactive form and execute the server.
        if (!isset($port) || !is_numeric($port)) {
            echo self::white_text(
                'USAGE: unimoodleservercli.php port [-c certificatefile -p privatekeyfile -b bufferlength] [-v]',
                false
            ) . PHP_EOL;
            $this->executeform();
            echo self::green_text(PHP_EOL .
                'Socket is running in the background. You can see the process running in the process list of your server.');
            die();
        }
        $verbosepos = array_search('-v', $_SERVER['argv'], true);
        if ($verbosepos !== false) {
            $this->verboselog = true;
            unset($_SERVER['argv'][$verbosepos]);
            $_SERVER['argv'] = array_values($_SERVER['argv']);
        }
        $certificate = '';
        $privatekey = '';
        $bufferlength = 16000; // Default buffer length.
        // Check if buffer length is set.
        $bufferlengthpos = array_search('-b', $_SERVER['argv'], true);
        if (
            $bufferlengthpos !== false && isset($_SERVER['argv'][$bufferlengthpos + 1])
            && is_numeric($_SERVER['argv'][$bufferlengthpos + 1])
        ) {
            $bufferlength = (int)$_SERVER['argv'][$bufferlengthpos + 1];
            unset($_SERVER['argv'][$bufferlengthpos], $_SERVER['argv'][$bufferlengthpos + 1]);
            $_SERVER['argv'] = array_values($_SERVER['argv']);
        }
        // Check if certificate file is set.
        $certificatepos = array_search('-c', $_SERVER['argv'], true);
        if (
            $certificatepos !== false
            && isset($_SERVER['argv'][$certificatepos + 1])
            && is_file($_SERVER['argv'][$certificatepos + 1])
        ) {
            $certificate = $_SERVER['argv'][$certificatepos + 1];
            unset($_SERVER['argv'][$certificatepos], $_SERVER['argv'][$certificatepos + 1]);
            $_SERVER['argv'] = array_values($_SERVER['argv']);
        }
        // Check if private key file is set.
        $privatekeypos = array_search('-p', $_SERVER['argv'], true);
        if (
            $privatekeypos !== false
            && isset($_SERVER['argv'][$privatekeypos + 1])
            && is_file($_SERVER['argv'][$privatekeypos + 1])
        ) {
            $privatekey = $_SERVER['argv'][$privatekeypos + 1];
            unset($_SERVER['argv'][$privatekeypos], $_SERVER['argv'][$privatekeypos + 1]);
            $_SERVER['argv'] = array_values($_SERVER['argv']);
        }
        // Need both or none of certificate and private key.
        if (($certificate !== '' && $privatekey === '') || ($certificate === '' && $privatekey !== '')) {
            echo self::red_text(
                'You must set both certificate and private key files or none of them. ' .
                'Use -c certificatefile and -p privatekeyfile options with valid accessible files.'
            );
            die();
        }
        // If the certificate and private key are set, then set ssl mode.
        if (isset($certificate) && is_file($certificate)) {
            $usessl = true;
        }
        // If there are arguments left, then they are unknown options.
        if (count($_SERVER['argv']) > 1) {
            echo self::red_text(
                'Unknown options: ' . implode(' ', $_SERVER['argv']) . PHP_EOL .
                'Use -c certificatefile and -p privatekeyfile options for SSL mode.' . PHP_EOL .
                'Use -b bufferlength option to set buffer length.' . PHP_EOL .
                'Use -v option to enable verbose mode.'
            );
            die();
        }

        $this->port = (int)$port;
        $this->maxbuffersize = $bufferlength;
        $this->certificate = $certificate;
        $this->privatekey = $privatekey;
        $this->usessl = $usessl;

        $this->create_master_socket();
    }
    /**
     * Create master socket.
     */
    protected function create_master_socket() {
        // Create the server socket.
        $context = stream_context_create();
        $transport = self::INSECURE_TRANSPORT;
        if ($this->usessl === true) {
            // Local_cert and local_pk must be in PEM format.
            stream_context_set_option($context, 'ssl', 'local_cert', $this->certificate);
            stream_context_set_option($context, 'ssl', 'local_pk', $this->privatekey);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
            $transport = self::SECURE_TRANSPORT;
        }
        $this->master = stream_socket_server(
            "$transport://" . (string)$this->addr . ":{$this->port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );
        if ($this->master === false || $errno > 0) {
            throw new UnexpectedValueException("Main socket error ($errno): $errstr");
        }

        $this->sockets['m'] = $this->master;
        $this->stdout(
            self::white_text(
                "Server started" . PHP_EOL . "Listening on: $this->addr:$this->port " . PHP_EOL .
                "Master socket: " . (is_resource($this->master) ? (string)$this->master : 'invalid') . PHP_EOL .
                "SSL: " . ($transport === self::SECURE_TRANSPORT ? 'enabled' : 'disabled'),
                false
            )
        );
    }
    /**
     * Set ANSI color to passed text.
     *
     * @param string $text  Text.
     * @param string $color Color name (red, green, yellow, etc.).
     * @param bool $addeol Add PHP_EOL at the end of the text.
     * @return string Colored text.
     */
    private static function color_text(string $text, string $color, bool $addeol = true): string {
        $colorcode = self::$colors[$color] ?? '37'; // Default: white.
        return "\033[{$colorcode}m{$text}\033[0m" . (($addeol === true) ? PHP_EOL : '');
    }

    /**
     * Set red color to a passed text.
     *
     * @param  string $text Text
     * @param bool $addeol Add PHP_EOL at the end of the text.
     * @return string Colored text
     */
    protected static function red_text(string $text, bool $addeol = true): string {
        return self::color_text($text, 'red', $addeol);
    }

    /**
     * Set green color to a passed text.
     *
     * @param  string $text Text
     * @param bool $addeol Add PHP_EOL at the end of the text.
     * @return string Colored text
     */
    protected static function green_text(string $text, bool $addeol = true): string {
        return self::color_text($text, 'green', $addeol);
    }

    /**
     * Set yellow color to a passed text.
     *
     * @param  string $text Text
     * @param bool $addeol Add PHP_EOL at the end of the text.
     * @return string Colored text
     */
    protected static function yellow_text(string $text, bool $addeol = true): string {
        return self::color_text($text, 'yellow', $addeol);
    }

    /**
     * Set blue color to a passed text.
     *
     * @param  string $text Text
     * @param bool $addeol Add PHP_EOL at the end of the text.
     * @return string Colored text
     */
    protected static function blue_text(string $text, bool $addeol = true): string {
        return self::color_text($text, 'blue', $addeol);
    }

    /**
     * Set magenta color to a passed text.
     *
     * @param  string $text Text
     * @param bool $addeol Add PHP_EOL at the end of the text.
     * @return string Colored text
     */
    protected static function magenta_text(string $text, bool $addeol = true): string {
        return self::color_text($text, 'magenta', $addeol);
    }

    /**
     * Set white color to a passed text.
     *
     * @param  string $text Text
     * @param bool $addeol Add PHP_EOL at the end of the text.
     * @return string Colored text
     */
    protected static function white_text(string $text, bool $addeol = true): string {
        return self::color_text($text, 'white', $addeol);
    }

    /**
     * Check transport protocols.
     *
     * @return void
     */
    private function check_prerequisites(): void {
        $supportedtransports = stream_get_transports();
        if (!in_array(self::INSECURE_TRANSPORT, $supportedtransports)) {
            echo self::red_text(
                strtoupper(self::INSECURE_TRANSPORT) .
                ' network transport protocol is not supported by your server. Contact with your system administrator'
            );
            exit();
        }

        if (!in_array(self::SECURE_TRANSPORT, $supportedtransports)) {
            echo self::red_text(
                strtoupper(self::SECURE_TRANSPORT) .
                ' network transport protocol is not supported by your server. Contact with your system administrator'
            );
            exit();
        }
    }

    /**
     * Execute config form
     *
     * @return void
     */
    private function executeform() {
        echo self::blue_text("Enter the port number for external connections." . PHP_EOL .
            "This port must be open, and must be provided to the Moodle platforms to be connected: ");
        $port = readline("");
        if (is_numeric($port)) {
            // A port availability check could go here, for example with
            // fsockopen() against localhost on the configured port.
            if ($port !== '') {
                readline_add_history($port);
                echo self::yellow_text('Do you want to use SSL certificates? (y/n):');
                $usessl = readline("");
                if (in_array(strtolower($usessl), ['s', 'si', 'sí', 'y', 'yes'])) {
                    readline_add_history($usessl);
                    echo self::magenta_text(PHP_EOL . "Enter the path where the server certificate is located." . PHP_EOL .
                        "The file must have .crt or .pem extension file of a valid SSL certificate for the server." . PHP_EOL .
                        "This file may already be generated on the same server as this script:");
                    $certificate = readline("");
                    if (
                        @file_exists($certificate) && array_key_exists('extension', pathinfo($certificate)) &&
                        (pathinfo($certificate)['extension'] === 'crt' || pathinfo($certificate)['extension'] === 'pem')
                    ) {
                        readline_add_history($certificate);
                        echo self::yellow_text(PHP_EOL . "Enter the path where the Private Key file is located." . PHP_EOL .
                            "The file must have .key or .pem extension file of a valid SSL Private Key for the server." . PHP_EOL .
                            "This file may already be generated on the same server as this script:");
                        $privatekey = readline("");
                        if (
                            @file_exists($privatekey) && array_key_exists('extension', pathinfo($privatekey)) &&
                            (pathinfo($privatekey)['extension'] === 'key' || pathinfo($privatekey)['extension'] === 'pem')
                        ) {
                            readline_add_history($privatekey);
                            $reference = $port . ' -c ' . $certificate . ' -p ' . $privatekey;
                            switch (strtolower(PHP_OS_FAMILY)) {
                                case "windows":
                                    pclose(popen("start /B php unimoodleservercli.php $reference", "r"));
                                    break;
                                case "linux":
                                    exec("php unimoodleservercli.php $reference > /dev/null &");
                                    break;
                                default:
                                    echo "Unsupported OS" . strtolower(PHP_OS_FAMILY);
                                    die();
                            }
                        } else {
                            echo self::red_text($privatekey . ' It is not a valid private key. Rerun the script');
                            exit();
                        }
                    } else {
                        echo self::red_text($certificate . ' It is not a valid certificate. Rerun the script');
                        exit();
                    }
                }
                $reference = $port;
                switch (strtolower(PHP_OS_FAMILY)) {
                    case "windows":
                        pclose(popen("start /B php unimoodleservercli.php $reference", "r"));
                        break;
                    case "linux":
                        exec("php unimoodleservercli.php $reference > /dev/null &");
                        break;
                    default:
                        echo "Unsupported OS" . strtolower(PHP_OS_FAMILY);
                        die();
                }
            } else {
                echo self::red_text($port . ' port is not responding, or the fsockopen method could not check it.');
                exit();
            }
        } else {
            echo self::red_text($port . ' Not a valid port. Rerun the script');
            exit();
        }
    }

    /**
     * Process user message received
     *
     * @param $user
     * @param $message
     * @return mixed
     */
    abstract protected function process($user, $message); // Called immediately when the data is recieved.

    /**
     * Connect user to socket
     *
     * @param $user
     * @return mixed
     */
    abstract protected function connected($user); // Called after the handshake response is sent to the client.

    /**
     * Close user connection to socket
     *
     * @param $user
     * @return mixed
     */
    abstract protected function closed($user); // Called after the connection is closed.

    /**
     * Connect user to socket
     *
     * @param $user
     * @return void
     */
    protected function connecting($user) {
        // Override to handle a connecting user, after the instance of the User is created, but before
        // the handshake has completed.
    }

    /**
     * Send message to user through socket
     *
     * @param $user
     * @param $message
     * @return void
     */
    protected function send($user, $message) {
        if ($user->handshake) {
            $message = $this->frame($message, $user);
            fwrite($user->socket, $message, strlen($message));
        } else {
            // User has not yet performed their handshake.  Store for sending later.
            $holdingmessage = ['user' => $user, 'message' => $message];
            $this->heldmessages[] = $holdingmessage;
        }
    }

    /**
     * Send message to socket
     *
     * @param $msg
     * @return void
     */
    public function send_message($msg) {
        foreach ($this->sockets as $key => $changedsocket) {
            if ($key !== 'm') {
                fwrite($changedsocket, $msg);
            }
        }
    }

    /**
     * Sentinel
     *
     * @return void
     */
    protected function tick() {
        // Override this for any process that should happen periodically.  Will happen at least once
        // per second, but possibly more often.
    }

    /**
     * Core sentinel
     *
     * @return void
     */
    protected function tick_core() {
        // Core maintenance processes, such as retrying failed messages.
        foreach ($this->heldmessages as $key => $hm) {
            $found = false;
            foreach ($this->users as $currentuser) {
                if ($hm['user']->socket === $currentuser->socket) {
                    $found = true;
                    if ($currentuser->handshake) {
                        unset($this->heldmessages[$key]);
                        $this->send($currentuser, $hm['message']);
                    }
                }
            }
            if (!$found) {
                // If they're no longer in the list of connected users, drop the message.
                unset($this->heldmessages[$key]);
            }
        }
    }

    /**
     * Masking data algorithm
     *
     * @param $text
     * @return string
     */
    public function mask($text) {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);
        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } else if ($length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } else {
            // Longitud extendida de 64 bits big-endian (RFC 6455 §5.2).
            $header = pack('CCJ', $b1, 127, $length);
        }
        return $header . $text;
    }

    /**
     * Unmasking data algorithm
     *
     * @param $text
     * @return string
     */
    public function unmask($text) {
        if (empty($text) || strlen($text) < 2) {
            return '';
        }
        $length = @ord($text[1]) & 127;
        if ($length === 126) {
            $masks = substr($text, 4, 4);
            $data = substr($text, 8);
        } else if ($length === 127) {
            $masks = substr($text, 10, 4);
            $data = substr($text, 14);
        } else {
            $masks = substr($text, 2, 4);
            $data = substr($text, 6);
        }
        $text = '';
        if ($data !== false) {
            $imax = strlen($data);
            for ($i = 0; $i < $imax; ++$i) {
                $text .= $data[$i] ^ $masks[$i % 4];
            }
        }
        return $text;
    }

    /**
     * Read the socket stream line by line and return the GET line and the headers:
     * Sec-Websocket-Key
     * Connection
     * Upgrade
     * Host
     * Origin
     * @param $clientsocket
     * @return array(string)
     */
    protected function read_headers_from_socket($clientsocket) {
        $headers = [];
        // Set the socket to blocking mode.
        stream_set_blocking($clientsocket, true);
        $line = fgets($clientsocket, $this->maxbuffersize);
        if ($line === false) {
            throw new RuntimeException("Failed to read from socket.");
        }
        // Check if the first line is a valid GET request.
        if (strpos($line, 'GET') !== 0) {
            $this->stdout(self::red_text("Invalid GET request: $line", false));
            $this->disconnect($clientsocket);
            return [];
        }
        $headers['GET'] = $line;
        // Read the headers until we find an empty line.
        while (($line = fgets($clientsocket, $this->maxbuffersize)) !== false && trim($line) !== '') {
            // Check if the line is a valid header of types: Sec-WebSocket-Key, Connection, Upgrade, Host, Origin.
            // if (preg_match('/^(Sec-WebSocket-Key|Connection|Upgrade|Host|Origin): (.+)$/', $line, $matches)) {
            // Sec-WebSocket-Extensions has to be accepted here too.
            $headerpattern = '/^(Sec-WebSocket-Key|Connection|Upgrade|Host|Origin|Sec-WebSocket-Extensions): (.+)$/';
            if (preg_match($headerpattern, $line, $matches)) {
                $headers[$matches[1]] = trim($matches[2]);
            }
        }
        // Set the socket to non-blocking mode.
        stream_set_blocking($clientsocket, false);
        return $headers;
    }
    /**
     * Check validity of json string.
     * @param string $string
     * @return bool
     */
    protected function check_json(string $string): bool {
        if (strpos($string, '{') !== 0) {
            return false;
        }
        if (substr($string, -1) !== '}') {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Disconnect from socket
     *
     * @param $socket
     * @param $triggerclosed
     * @param $sockerrno
     * @return void
     */
    protected function disconnect($socket, $triggerclosed = true, $sockerrno = null) {
        $disconnecteduser = $this->get_user_by_socket($socket);
        if ($disconnecteduser !== null) {
            unset($this->users[$disconnecteduser->usersocketid]);
            unset($this->sockets[$disconnecteduser->usersocketid]);
            unset($this->socketbuffers[$disconnecteduser->usersocketid], $this->fragmentbuffers[$disconnecteduser->usersocketid]);

            if (array_key_exists($disconnecteduser->usersocketid, $this->sockets)) {
                unset($this->sockets[$disconnecteduser->usersocketid]);
            }
            if (!is_null($sockerrno)) {
                $this->stdout(self::red_text($sockerrno, false));
            }
            if ($triggerclosed) {
                $user = $disconnecteduser->ip ?? 'unknown';
                $this->stdout("\033[1;30m" . "Client $user disconnected. " . $disconnecteduser->socket . "\033[0m");
                $this->closed($disconnecteduser);
                if (is_resource($disconnecteduser->socket)) {
                    stream_socket_shutdown($disconnecteduser->socket, STREAM_SHUT_RDWR);
                }
            } else {
                $this->send_masked([$disconnecteduser], 'close');
            }
        }
        // Close the socket.
        if (is_resource($socket)) {
            fclose($socket);
        }
    }

    /**
     * Handshake process
     *
     * @param $clientsocket
     * @return boolean
     */
    protected function handshake($clientsocket) {

        // Read the socket keeping only first line and Sec-Websocket-Key header.
        // This is necessary to avoid DoS attacks with large headers.
        $headers = $this->read_headers_from_socket($clientsocket);
        $ip = stream_socket_get_name($clientsocket, true);
        if (!$this->check_host($headers['Host'] ?? '')) {
            $this->stdout(self::red_text("Rejected host during handshake from $ip", false));
            $this->disconnect($clientsocket);
            return false;
        }
        if (!$this->check_origin($headers['Origin'] ?? '')) {
            $this->stdout(self::red_text("Rejected origin during handshake from $ip", false));
            $this->disconnect($clientsocket);
            return false;
        }
        // Check if request is OK.
        if (strpos($headers['GET'] ?? '', 'HTTP/1.1') === false && strpos($headers['GET'] ?? '', 'HTTP/1.0 101') === false) {
            $this->stdout(self::red_text("Bad headers from $ip." .
                            ($this->transport !== self::SECURE_TRANSPORT ? 'SSL disabled. Client is attempting WSS connection?' :
                            'Headers: ' . $headers), false));
            $this->disconnect($clientsocket);
            return false;
        }

        if (isset($headers['Sec-WebSocket-Key'])) {
            $seckey = $headers['Sec-WebSocket-Key'];
            $secaccept = base64_encode(pack('H*', sha1($seckey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
            // Log clients asking for compression, and do not offer the extension back.
            if (
                !empty($headers['Sec-WebSocket-Extensions']) &&
                    strpos($headers['Sec-WebSocket-Extensions'], 'permessage-deflate') !== false
            ) {
                $this->stdout(self::red_text(
                    "Client $ip requested permessage-deflate compression — rejecting (not supported).",
                    false
                ));
            }
            // The response leaves Sec-WebSocket-Extensions out, so Firefox does not turn
            // compression on.
            $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                    "Upgrade: websocket\r\n" .
                    "Connection: Upgrade\r\n" .
                    "WebSocket-Origin: $this->addr\r\n" .
                    "WebSocket-Location: wss://$this->addr:$this->port\r\n" .
                    "Sec-WebSocket-Version: 13\r\n" .
                    "Sec-WebSocket-Accept:$secaccept\r\n\r\n";
            fwrite($clientsocket, $upgrade);
            $this->connected($clientsocket);
            $this->connect($clientsocket, $ip, $headers);
            $this->stdout(self::green_text("Client connected. $clientsocket from $ip", false));
            return true;
        } else {
            $this->stdout(self::red_text("Handshake failed. No Sec-WebSocket-Key header found.", false));
            $this->disconnect($clientsocket);
            return false;
        }
    }

    /**
     * check hostname
     *
     * @param $hostname
     * @return true
     */
    protected function check_host($hostname) {
        return true;
        /* Override and return false if the host is not one that you would expect.
        Ex: You only want to accept hosts from the my-domain.com domain,
        but you receive a host from malicious-site.com instead.*/
    }

    /**
     * Check origin
     *
     * @param $origin
     * @return true
     */
    protected function check_origin($origin) {
        return true; // Override and return false if the origin is not one that you would expect.
    }

    /**
     * Check websocket protocol
     *
     * @param $protocol
     * @return true
     */
    protected function check_web_soc_protocol($protocol) {
        return true; // Override and return false if a protocol is not found that you would expect.
    }

    /**
     * Check websocket extensions
     *
     * @param $extensions
     * @return true
     */
    protected function check_web_soc_extensions($extensions) {
        return true; // Override and return false if an extension is not found that you would expect.
    }

    /**
     * Check process protocol
     *
     * @param $protocol
     * @return string
     */
    protected function process_protocol($protocol) {
        return '';
        /* return either "Sec-WebSocket-Protocol: SelectedProtocolFromClientList\r\n" or return an empty string.
        The carriage return/newline combo must appear at the end of a non-empty string, and must not
        appear at the beginning of the string nor in an otherwise empty string, or it will be considered part of
        the response body, which will trigger an error in the client as it will not be formatted correctly.*/
    }

    /**
     * Check process extensions
     *
     * @param $extensions
     * @return string
     */
    protected function process_extensions($extensions) {
        return ''; // Return either "Sec-WebSocket-Extensions: SelectedExtensions\r\n" or return an empty string.
    }

    /**
     * Get user by socket instance
     *
     * @param $socket
     * @return mixed|null
     */
    protected function get_user_by_socket($socket) {
        foreach ($this->users as $user) {
            if (is_string($socket) && $user->usersocketid === $socket) {
                // If the socket is a string, it is the socket id.
                return $user;
            } else if (is_resource($socket) && $user->socket === $socket) {
                // If the socket is a resource, compare it directly.
                return $user;
            } else if ($user->socket === $socket) {
                // If the socket is a closed resource, compare it directly.
                return $user;
            }
        }
        return null;
    }

    /**
     * Get socket by user instance
     *
     * @param $user
     * @return mixed|null
     */
    protected function get_socket_by_user($user) {
        foreach ($this->sockets as $key => $socket) {
            if ($key === $user->usersocketid) {
                return $socket;
            }
        }
        return null;
    }

    /**
     * Standard out
     *
     * @param $message
     * @return void
     */
    public function stdout($message) {
        if ($this->interactive) {
            echo "$message" . " - " . "\033[1;30m" . date("D d M Y H:i:s") . "\033[0m" . PHP_EOL;
        }
    }

    /**
     * Standard error
     *
     * @param $message
     * @return void
     */
    public function stderr($message) {
        if ($this->interactive) {
            echo "$message" . " - " . "\033[1;30m" . date("D d M Y H:i:s") . "\033[0m" . PHP_EOL;
        }
    }

    /**
     * Frame
     *
     * @param $message
     * @param $user
     * @param $messagetype
     * @param $messagecontinues
     * @return string
     */
    protected function frame($message, $user, $messagetype = 'text', $messagecontinues = false) {
        switch ($messagetype) {
            case 'continuous':
                $b1 = 0;
                break;
            case 'text':
                $b1 = ($user->sendingContinuous) ? 0 : 1;
                break;
            case 'binary':
                $b1 = ($user->sendingContinuous) ? 0 : 2;
                break;
            case 'close':
                $b1 = 8;
                break;
            case 'ping':
                $b1 = 9;
                break;
            case 'pong':
                $b1 = 10;
                break;
        }
        if ($messagecontinues) {
            $user->sendingContinuous = true;
        } else {
            $b1 += 128;
            $user->sendingContinuous = false;
        }

        $length = strlen($message);
        $lengthfield = '';
        if ($length < 126) {
            $b2 = $length;
        } else if ($length < 65536) {
            $b2 = 126;
            $hexlength = dechex($length);
            if (strlen($hexlength) % 2 === 1) {
                $hexlength = '0' . $hexlength;
            }
            $n = strlen($hexlength) - 2;

            for ($i = $n; $i >= 0; $i -= 2) {
                $lengthfield = chr(hexdec(substr($hexlength, $i, 2))) . $lengthfield;
            }
            while (strlen($lengthfield) < 2) {
                $lengthfield = chr(0) . $lengthfield;
            }
        } else {
            $b2 = 127;
            $hexlength = dechex($length);
            if (strlen($hexlength) % 2 === 1) {
                $hexlength = '0' . $hexlength;
            }
            $n = strlen($hexlength) - 2;

            for ($i = $n; $i >= 0; $i -= 2) {
                $lengthfield = chr(hexdec(substr($hexlength, $i, 2))) . $lengthfield;
            }
            while (strlen($lengthfield) < 8) {
                $lengthfield = chr(0) . $lengthfield;
            }
        }

        return chr($b1) . chr($b2) . $lengthfield . $message;
    }

    /**
     * Extract message headers
     *
     * @param $message
     * @return int[]
     */
    protected function extract_headers($message) {
        $header = ['fin'     => $message[0] & chr(128),
            'rsv1'    => $message[0] & chr(64),
            'rsv2'    => $message[0] & chr(32),
            'rsv3'    => $message[0] & chr(16),
            'opcode'  => ord($message[0]) & 15,
            'hasmask' => $message[1] & chr(128),
            'length'  => 0,
            'mask'    => ''];
        $header['length'] = (ord($message[1]) >= 128) ? ord($message[1]) - 128 : ord($message[1]);

        if ($header['length'] === 126) {
            if ($header['hasmask']) {
                $header['mask'] = $message[4] . $message[5] . $message[6] . $message[7];
            }
            $header['length'] = ord($message[2]) * 256
                + ord($message[3]);
        } else if ($header['length'] === 127) {
            if ($header['hasmask']) {
                $header['mask'] = $message[10] . $message[11] . $message[12] . $message[13];
            }
            $header['length'] = ord($message[2]) * 65536 * 65536 * 65536 * 256
                + ord($message[3]) * 65536 * 65536 * 65536
                + ord($message[4]) * 65536 * 65536 * 256
                + ord($message[5]) * 65536 * 65536
                + ord($message[6]) * 65536 * 256
                + ord($message[7]) * 65536
                + ord($message[8]) * 256
                + ord($message[9]);
        } else if ($header['hasmask']) {
            $header['mask'] = $message[2] . $message[3] . $message[4] . $message[5];
        }
        return $header;
    }

    /**
     * Extract payload
     *
     * @param $message
     * @param $headers
     * @return false|string
     */
    protected function extract_payload($message, $headers) {
        $offset = 2;
        if ($headers['hasmask']) {
            $offset += 4;
        }
        if ($headers['length'] > 65535) {
            $offset += 8;
        } else if ($headers['length'] > 125) {
            $offset += 2;
        }
        return substr($message, $offset);
    }

    /**
     * Apply mask
     *
     * @param $headers
     * @param $payload
     * @return int|mixed
     */
    protected function apply_mask($headers, $payload) {
        $effectivemask = '';
        if ($headers['hasmask']) {
            $mask = $headers['mask'];
        } else {
            return $payload;
        }

        while (strlen($effectivemask) < strlen($payload)) {
            $effectivemask .= $mask;
        }
        while (strlen($effectivemask) > strlen($payload)) {
            $effectivemask = substr($effectivemask, 0, -1);
        }
        return $effectivemask ^ $payload;
    }

    /**
     * Check RSV bits
     *
     * @param $headers
     * @param $user
     * @return bool
     */
    protected function check_rsv_bits($headers, $user) {
        // Override this method if you are using an extension where the RSV bits are used.
        return ord($headers['rsv1']) + ord($headers['rsv2']) + ord($headers['rsv3']) > 0;
    }

    /**
     * String to hexadecimal
     *
     * @param $str
     * @return string
     */
    protected function str_to_hex($str) {
        $strout = "";
        for ($i = 0, $imax = strlen($str); $i < $imax; $i++) {
            $strout .= (ord($str[$i]) < 16) ? "0" . dechex(ord($str[$i])) : dechex(ord($str[$i]));
            $strout .= " ";
            if ($i % 32 == 7) {
                $strout .= ": ";
            }
            if ($i % 32 == 15) {
                $strout .= ": ";
            }
            if ($i % 32 == 23) {
                $strout .= ": ";
            }
            if ($i % 32 == 31) {
                $strout .= "\n";
            }
        }
        return $strout . "\n";
    }

    /**
     * Print headers
     *
     * @param $headers
     * @return void
     */
    protected function print_headers($headers) {
        foreach ($headers as $key => $value) {
            if ($key === 'length' || $key === 'opcode') {
                debugging("\t[$key] => $value\n\n");
            } else {
                debugging("\t[$key] => " . $this->str_to_hex($value) . "\n");
            }
        }
        echo ")\n";
    }
}

/**
 * Websocket user class
 */
class websocketuser {
    /** @var resource socket     */
    public $socket;
    /** @var string user id     */
    public $usersocketid;
    /** @var string IP address     */
    public $ip;
    /**
     * @var array headers
     */
    public $headers = [];
    /** @var string host header     */
    public $host = '';
    /** @var string origin header     */
    public $origin = '';
    /** @var string normalized tenant host     */
    public $scopehost = 'default';
    /** @var string username     */
    public $dataname; // Moodle Username.
    /** @var bool is teacher flag     */
    public $isteacher;
    /** @var bool handshake flag     */
    public $handshake = false;
    /** @var int course module id     */
    public $cmid;
    /** @var int session id     */
    public $sid;
    /** @var int user id     */
    public $userid;

    /** @var string picture     */
    public $picture = '';
    /** @var bool If true, then the next message sent will be a continuous message.    */
    public $sendingcontinuous = false;
    /**
     * Constructor
     *
     * @param $id socket id in the server.
     * @param resource $socket OS resource for the socket.
     * @param string $ip IP address.
     */
    public function __construct($id, $socket, $ip) {
        $this->usersocketid = $id;
        $this->socket = $socket;
        $this->ip = $ip;
        $this->handshake = true;
    }

    /**
     * Update user
     *
     * @param $data
     * @return void
     */
    public function update_user($data) {
        $this->cmid = $data['cmid'];
        $this->sid = $data['sid'];
    }
}

/**
 * Kuet encrypt algorithm
 *
 * @param $password
 * @param $text
 * @return string|null
 */
function kuet_encrypt($password, $text) {
    $base64 = base64_encode($text);
    $arr = str_split($base64);
    $arrpass = str_split($password);
    $lastpassletter = 0;
    $encrypted = '';
    foreach ($arr as $value) {
        $letter = $value;
        $passwordletter = $arrpass[$lastpassletter];
        $temp = get_letter_from_alphabet_for_letter($passwordletter, $letter);
        if ($temp !== null) {
            $encrypted .= $temp;
        } else {
            return null;
        }
        if ($lastpassletter === (count($arrpass) - 1)) {
            $lastpassletter = 0;
        } else {
            $lastpassletter++;
        }
    }
    return $encrypted;
}

/**
 * Kuet encrypt aux function
 *
 * @param $letter
 * @param $lettertochange
 * @return mixed|null
 */
function get_letter_from_alphabet_for_letter($letter, $lettertochange) {
    $abc = 'abcdefghijklmnopqrstuvwxyz0123456789=ABCDEFGHIJKLMNOPQRSTUVWXYZ/+-*';
    $posletter = strpos($abc, $letter);
    if ($posletter === false) {
        return null;
    }
    $poslettertochange = strpos($abc, $lettertochange);
    if ($poslettertochange === false) {
        return null;
    }
    $part1 = substr($abc, $posletter, strlen($abc));
    $part2 = substr($abc, 0, $posletter);
    $newabc = $part1 . $part2;
    $temp = str_split($newabc);
    return $temp[$poslettertochange];
}

$server = new unimoodleservercli(addr: '0.0.0.0', bufferlength: 8192);
try {
    $server->run();
} catch (Exception $e) {
    $server->stdout("\033[31m" . $e->getMessage() . "\033[0m");
}
