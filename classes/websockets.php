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

require_once(__DIR__ . '/../../../config.php');
global $CFG;
require_once(__DIR__ . '/websocketuser.php');
require_once($CFG->dirroot . '/lib/weblib.php');

abstract class websockets {
    protected $maxbuffersize;
    protected $master;
    protected $sockets = [];
    protected $users = [];
    protected $heldmessages = [];
    protected $interactive = true;
    protected $addr;
    protected $port;

    /**
     * @param $addr
     * @param $port
     * @param int $bufferlength
     * @throws coding_exception
     * @throws dml_exception
     */
    public function __construct($addr, $port, $bufferlength = 2048) {
        global $CFG;
        $this->addr = $addr;
        $this->port = $port;
        $this->maxbuffersize = $bufferlength;

        $certificateurl = '';
        $privatekeyurl = '';
        $syscontext = context_system::instance();
        $fs = get_file_storage();
        $certificatefiles = $fs->get_area_files($syscontext->id, 'jqshow', 'certificate_ssl', 0, 'filename', false);
        foreach ($certificatefiles as $file) {
            if ($file->get_filename() !== '.') {
                file_safe_save_content($file->get_content(), $CFG->localcachedir . '/jqshow/' . $file->get_filename());
                $certificateurl = $CFG->localcachedir . '/jqshow/' . $file->get_filename();
                break;
            }
        }
        $privatekeyfiles = $fs->get_area_files($syscontext->id, 'jqshow', 'privatekey_ssl', 0, 'filename', false);
        foreach ($privatekeyfiles as $file) {
            if ($file->get_filename() !== '.') {
                file_safe_save_content($file->get_content(), $CFG->localcachedir . '/jqshow/' . $file->get_filename());
                $privatekeyurl = $CFG->localcachedir . '/jqshow/' . $file->get_filename();
                break;
            }
        }

        // Certificate data.
        $context = stream_context_create();
        // Local_cert and local_pk must be in PEM format.
        stream_context_set_option($context, 'ssl', 'local_cert', $certificateurl);
        stream_context_set_option($context, 'ssl', 'local_pk', $privatekeyurl);
        stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
        stream_context_set_option($context, 'ssl', 'verify_peer', false);
        stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

        // Create the server socket.
        $this->master = stream_socket_server(
            "ssl://$addr:$port",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context);
        if ($this->master === false || $errno > 0) {
            throw new UnexpectedValueException("Main socket error ($errno): $errstr");
        }
        $this->sockets['m'] = $this->master;
        $this->stdout("Server started\nListening on: $addr:$port\nMaster socket: $this->master\n");
    }

    /**
     * @param stored_file $file
     * @return string
     */
    protected function get_filepath_for_file(stored_file $file): string {
        return sprintf(
            '%s%s%s%s',
            '',
            $file->get_filearea(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * @param $user
     * @param $message
     * @return mixed
     */
    abstract protected function process($user, $message); // Called immediately when the data is recieved.

    /**
     * @param $user
     * @return mixed
     */
    abstract protected function connected($user); // Called after the handshake response is sent to the client.

    /**
     * @param $user
     * @return mixed
     */
    abstract protected function closed($user); // Called after the connection is closed.

    /**
     * @param $user
     * @return void
     */
    protected function connecting($user) {
        // Override to handle a connecting user, after the instance of the User is created, but before
        // the handshake has completed.
    }

    /**
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
     * @return void
     */
    protected function tick() {
        // Override this for any process that should happen periodically.  Will happen at least once
        // per second, but possibly more often.
    }

    /**
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
            $header = pack('CCNN', $b1, 127, $length);
        }
        return $header . $text;
    }

    /**
     * @param $text
     * @return string
     */
    public function unmask($text) {
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
     * @return mixed
     */
    public function run() {
        while (true) {
            if (empty($this->sockets)) {
                $this->sockets['m'] = $this->master;
            }
            $read = $this->sockets;
            $write = $except = null;
            $this->tick_core();
            $this->tick();
            stream_select($read, $write, $except, 10);
            if (in_array($this->master, $read, true)) {
                $client = @stream_socket_accept($this->master, 20);
                if (!$client) {
                    continue;
                }
                $ip = stream_socket_get_name( $client, true );
                $this->stdout("Connection attempt from $ip");
                stream_set_blocking($client, true);
                /* if (!stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER)) {
                    $this->stderr('Error enabling TLS encryption on the connection.');
                    fclose($client);
                    continue;
                }
                $this->stderr('Enabled TLS encryption.');*/
                $headers = fread($client, 1500);
                $this->handshake($client, $headers);
                stream_set_blocking($client, false);

                $foundsocket = array_search($this->master, $read, true);
                unset($read[$foundsocket]);

                $this->stdout("Handshake $ip");

                if ($client < 0) {
                    $this->stderr("Failed: socket_accept()");
                    continue;
                }

                $this->connect($client, $ip);
                $this->stdout("Client connected. $client\n");
            }

            foreach ($read as $socket) {
                $ip = stream_socket_get_name( $socket, true );
                $buffer = stream_get_contents($socket);
                // TODO review detect disconnect for min buffer lenght.
                if ($buffer === false || strlen($buffer) <= 8) {
                    if ($this->unmask($buffer) !== '') { // Necessary to stabilise connections, review.
                        $this->disconnect($socket);
                        $this->stdout("Client disconnected. TCP connection lost: " . $socket);
                        @fclose($socket);
                        $foundsocket = array_search($socket, $this->sockets, true);
                        unset($this->sockets[$foundsocket]);
                    }
                }
                $unmasked = $this->unmask($buffer);
                if ($unmasked !== "") {
                    $user = $this->get_user_by_socket($socket);
                    if ($user !== null) {
                        if (!$user->handshake) {
                            $tmp = str_replace("\r", '', $buffer);
                            if (strpos($tmp, "\n\n") === false ) {
                                // If the client has not finished sending the header, then wait before sending our upgrade response.
                                continue;
                            }
                            $this->handshake($user, $buffer);
                        } else {
                            $this->process($user, $unmasked);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $socket
     * @param $triggerclosed
     * @param $sockerrno
     * @return void
     */
    protected function disconnect($socket, $triggerclosed = true, $sockerrno = null) {
        $disconnecteduser = $this->get_user_by_socket($socket);
        if ($disconnecteduser !== null) {
            unset($this->users[$disconnecteduser->usersocketid]);
            if (array_key_exists($disconnecteduser->usersocketid, $this->sockets)) {
                unset($this->sockets[$disconnecteduser->usersocketid]);
            }
            if (!is_null($sockerrno)) {
                $this->stdout($sockerrno);
            }
            if ($triggerclosed) {
                $this->stdout("Client disconnected. ".$disconnecteduser->socket);
                $this->closed($disconnecteduser);
                stream_socket_shutdown($disconnecteduser->socket, STREAM_SHUT_RDWR);
            } else {
                $message = $this->frame('', $disconnecteduser, 'close');
                fwrite($disconnecteduser->socket, $message, strlen($message));
            }
        }
    }

    /**
     * @param $client
     * @param $rcvd
     * @return void
     */
    protected function handshake($client, $rcvd) {
        $headers = [];
        $lines = preg_split("/\r\n/", $rcvd);
        foreach ($lines as $line) {
            $line = rtrim($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }
        if (isset($headers['Sec-WebSocket-Key'])) {
            $seckey = $headers['Sec-WebSocket-Key'];
            $secaccept = base64_encode(pack('H*', sha1($seckey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
            // Handshaking header.
            $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "WebSocket-Origin: $this->addr\r\n" .
                "WebSocket-Location: wss://$this->addr:$this->port\r\n".
                "Sec-WebSocket-Version: 13\r\n" .
                "Sec-WebSocket-Accept:$secaccept\r\n\r\n";
            fwrite($client, $upgrade);
            $this->connected($client);
        }
    }

    /**
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
     * @param $origin
     * @return true
     */
    protected function check_origin($origin) {
        return true; // Override and return false if the origin is not one that you would expect.
    }

    /**
     * @param $protocol
     * @return true
     */
    protected function check_web_soc_protocol($protocol) {
        return true; // Override and return false if a protocol is not found that you would expect.
    }

    /**
     * @param $extensions
     * @return true
     */
    protected function check_web_soc_extensions($extensions) {
        return true; // Override and return false if an extension is not found that you would expect.
    }

    /**
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
     * @param $extensions
     * @return string
     */
    protected function process_extensions($extensions) {
        return ''; // Return either "Sec-WebSocket-Extensions: SelectedExtensions\r\n" or return an empty string.
    }

    /**
     * @param $socket
     * @return mixed|null
     */
    protected function get_user_by_socket($socket) {
        foreach ($this->users as $user) {
            if ($user->socket === $socket) {
                return $user;
            }
        }
        return null;
    }

    /**
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
     * @param $message
     * @return void
     */
    public function stdout($message) {
        if ($this->interactive) {
            echo "$message\n";
        }
    }

    /**
     * @param $message
     * @return void
     */
    public function stderr($message) {
        if ($this->interactive) {
            debugging("$message\n");
        }
    }

    /**
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
     * @param $headers
     * @return int
     */
    protected function calc_offset($headers) {
        $offset = 2;
        if ($headers['hasmask']) {
            $offset += 4;
        }
        if ($headers['length'] > 65535) {
            $offset += 8;
        } else if ($headers['length'] > 125) {
            $offset += 2;
        }
        return $offset;
    }

    /**
     * TODO Review logic of this method to take advantage of what can, and eliminate, no longer used.
     * @param $message
     * @param $user
     * @return false|int|mixed|string
     */
    protected function deframe($message, $user) {
        $headers = $this->extract_headers($message);
        $pongreply = false;
        $willclose = false;
        switch($headers['opcode']) {
            case 0:
            case 1:
            case 10:
            case 2:
                break;
            case 8:
                // TODO: close the connection.
                $user->hasSentClose = true;
                return "";
            case 9:
                $pongreply = true;
                break;
            default:
                $willclose = true;
                break;
        }

        if ($this->check_rsv_bits($headers, $user)) {
            return false;
        }

        if ($willclose) {
            // TODO: fail the connection.
            return false;
        }

        $payload = $user->partialMessage . $this->extract_payload($message, $headers);

        if ($pongreply) {
            $reply = $this->frame($payload, $user, 'pong');
            stream_socket_sendto($user->socket, $reply);
            return false;
        }
        if ($headers['length'] > strlen($this->apply_mask($headers, $payload))) {
            $user->handlingPartialPacket = true;
            $user->partialBuffer = $message;
            return false;
        }

        $payload = $this->apply_mask($headers, $payload);

        if ($headers['fin']) {
            $user->partialMessage = "";
            return $payload;
        }
        $user->partialMessage = $payload;
        return false;
    }

    /**
     * @param $message
     * @return array
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
     * @param $headers
     * @param $user
     * @return bool
     */
    protected function check_rsv_bits($headers, $user) {
        // Override this method if you are using an extension where the RSV bits are used.
        return ord($headers['rsv1']) + ord($headers['rsv2']) + ord($headers['rsv3']) > 0;
    }

    /**
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
     * @param $headers
     * @return void
     */
    protected function print_headers($headers) {
        foreach ($headers as $key => $value) {
            if ($key === 'length' || $key === 'opcode') {
                debugging("\t[$key] => $value\n\n");
            } else {
                debugging("\t[$key] => ".$this->str_to_hex($value)."\n");
            }

        }
        echo ")\n";
    }
}
