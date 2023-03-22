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

require_once __DIR__ . '/websocketuser.php';

abstract class websockets {
    protected $maxBufferSize;
    protected $master;
    protected $sockets = [];
    protected $users = [];
    protected $heldMessages = [];
    protected $interactive = true;
    protected $addr;
    protected $port;

    /**
     * @param $addr
     * @param $port
     * @param $bufferLength
     */
    public function __construct($addr, $port, $bufferLength = 2048) {
        $this->addr = $addr;
        $this->port = $port;
        $this->maxBufferSize = $bufferLength;

        // Certificate data:
        $context = stream_context_create();
        // local_cert and local_pk must be in PEM format
        stream_context_set_option($context, 'ssl', 'local_cert', '/etc/letsencrypt/archive/unimoodle311pre.3ip.eu/cert1.pem'); // TODO config.
        stream_context_set_option($context, 'ssl', 'local_pk', '/etc/letsencrypt/archive/unimoodle311pre.3ip.eu/privkey1.pem'); // TODO config.
        // Pass Phrase (password) of private key
        stream_context_set_option($context, 'ssl', 'passphrase', '');
        stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
        stream_context_set_option($context, 'ssl', 'verify_peer', false);

        // Create the server socket
        $this->master = stream_socket_server("ssl://$addr:$port", $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $context);
        if ($this->master === false || $errno > 0) {
            throw new UnexpectedValueException("Main socket error ($errno): $errstr");
        }
        // stream_socket_enable_crypto($this->master, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER); // TODO review.

        $this->sockets['m'] = $this->master;
        $this->stdout("Server started\nListening on: $addr:$port\nMaster socket: " . $this->master);
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
            $holdingMessage = ['user' => $user, 'message' => $message];
            $this->heldMessages[] = $holdingMessage;
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
    protected function _tick() {
        // Core maintenance processes, such as retrying failed messages.
        foreach ($this->heldMessages as $key => $hm) {
            $found = false;
            foreach ($this->users as $currentUser) {
                if ($hm['user']->socket === $currentUser->socket) {
                    $found = true;
                    if ($currentUser->handshake) {
                        unset($this->heldMessages[$key]);
                        $this->send($currentUser, $hm['message']);
                    }
                }
            }
            if (!$found) {
                // If they're no longer in the list of connected users, drop the message.
                unset($this->heldMessages[$key]);
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
        if($length <= 125) {
            $header = pack('CC', $b1, $length);
        } else if($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } else {
            $header = pack('CCNN', $b1, 127, $length);
        }
        return $header . $text;
    }

    public function unmask($text) {
        $length = @ord($text[1]) & 127;
        if ($length === 126) {
            $masks = substr($text, 4, 4);
            $data = substr($text, 8);
        } else if($length === 127) {
            $masks = substr($text, 10, 4);
            $data = substr($text, 14);
        } else {
            $masks = substr($text, 2, 4);
            $data = substr($text, 6);
        }
        $text = "";
        for ($i = 0, $iMax = strlen($data); $i < $iMax; ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        return $text;
    }

    /**
     * @param $msg
     * @return void
     */
    public function send_message($msg) { // TODO prepare for one user.
        foreach($this->sockets as $key => $changed_socket){
            $this->stderr($msg);
            if ($key !== 'm') {
                fwrite($changed_socket, $msg);
            }
        }
    }

    /**
     * Main processing loop
     */
    public function run() {
        while(true) {
            if (empty($this->sockets)) {
                $this->sockets['m'] = $this->master;
            }
            $read = $this->sockets;
            $write = $except = null;
            $this->_tick();
            $this->tick();
            stream_select($read, $write, $except, 10);
            if (in_array($this->master, $read, true)) {
                $client = @stream_socket_accept($this->master, 20);
                if (!$client) {
                    continue;
                }
                // stream_copy_to_stream($client, $client); // TODO Review.
                $ip = stream_socket_get_name( $client, true );
                $this->stderr("Connection attempt from $ip\n");

                stream_set_blocking($client, true);
                $headers = fread($client, 1500);
                $this->handshake($client, $headers);
                stream_set_blocking($client, false);

                $found_socket = array_search($this->master, $read, true);
                unset($read[$found_socket]);

                $this->stderr("Handshake $ip\n");

                if ($client < 0) {
                    $this->stderr("Failed: socket_accept()");
                    continue;
                }

                $this->connect($client, $ip);
                $this->stdout("Client connected. " . $client);
            }

            foreach ($read as $socket) {
                $ip = stream_socket_get_name( $socket, true );
                $buffer = stream_get_contents($socket);
                if ($buffer === false || strlen($buffer) <= 8) { // TODO review detect disconnect for min buffer lenght.
                    $this->disconnect($socket);
                    $this->stderr("Client disconnected. TCP connection lost: " . $socket);
                    @fclose($socket);
                    $found_socket = array_search($socket, $this->sockets, true);
                    unset($this->sockets[$found_socket]);
                }
                $unmasked = $this->unmask($buffer);
                if ($unmasked !== "") {
                    $user = $this->getUserBySocket($socket);
                    if ($user !== null) {
                        if (!$user->handshake) {
                            $tmp = str_replace("\r", '', $buffer);
                            if (strpos($tmp, "\n\n") === false ) {
                                continue; // If the client has not finished sending the header, then wait before sending our upgrade response.
                            }
                            $this->handshake($user, $buffer);
                        } else {
                            $this->process($user, $unmasked);
                            echo "\nReceived a Message from $ip:\n\"$unmasked\" \n";
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $socket
     * @param $ip
     * @return void
     */
    protected function connect($socket, $ip) {
        $user = new websocketuser(uniqid('u', true), $socket, $ip);
        $this->users[$user->id] = $user;
        $this->sockets[$user->id] = $socket;
        $this->connecting($user);
    }

    /**
     * @param $socket
     * @param $triggerClosed
     * @param $sockErrNo
     * @return void
     */
    protected function disconnect($socket, $triggerClosed = true, $sockErrNo = null) {
        $disconnectedUser = $this->getUserBySocket($socket);
        if ($disconnectedUser !== null) {
            unset($this->users[$disconnectedUser->id]);
            if (array_key_exists($disconnectedUser->id, $this->sockets)) {
                unset($this->sockets[$disconnectedUser->id]);
            }
            if (!is_null($sockErrNo)) {
                socket_clear_error($socket);
            }
            if ($triggerClosed) {
                $this->stdout("Client disconnected. ".$disconnectedUser->socket);
                $this->closed($disconnectedUser);
                stream_socket_shutdown($disconnectedUser->socket, STREAM_SHUT_RDWR);
            }
            else {
                $message = $this->frame('', $disconnectedUser, 'close');
                fwrite($disconnectedUser->socket, $message, strlen($message));
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
        foreach($lines as $line) {
            $line = rtrim($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }
        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        // Handshaking header
        $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: $this->addr\r\n" .
            "WebSocket-Location: wss://$this->addr:$this->port\r\n".
            "Sec-WebSocket-Version: 13\r\n" .
            "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        fwrite($client, $upgrade);
        $this->connected($client);
    }

    /**
     * @param $hostName
     * @return true
     */
    protected function checkHost($hostName) {
        return true;
        /* Override and return false if the host is not one that you would expect.
        Ex: You only want to accept hosts from the my-domain.com domain,
        but you receive a host from malicious-site.com instead.*/
    }

    /**
     * @param $origin
     * @return true
     */
    protected function checkOrigin($origin) {
        return true; // Override and return false if the origin is not one that you would expect.
    }

    /**
     * @param $protocol
     * @return true
     */
    protected function checkWebsocProtocol($protocol) {
        return true; // Override and return false if a protocol is not found that you would expect.
    }

    /**
     * @param $extensions
     * @return true
     */
    protected function checkWebsocExtensions($extensions) {
        return true; // Override and return false if an extension is not found that you would expect.
    }

    /**
     * @param $protocol
     * @return string
     */
    protected function processProtocol($protocol) {
        return "";
        /* return either "Sec-WebSocket-Protocol: SelectedProtocolFromClientList\r\n" or return an empty string.
        The carriage return/newline combo must appear at the end of a non-empty string, and must not
        appear at the beginning of the string nor in an otherwise empty string, or it will be considered part of
        the response body, which will trigger an error in the client as it will not be formatted correctly.*/
    }

    /**
     * @param $extensions
     * @return string
     */
    protected function processExtensions($extensions) {
        return ""; // return either "Sec-WebSocket-Extensions: SelectedExtensions\r\n" or return an empty string.
    }

    /**
     * @param $socket
     * @return mixed|null
     */
    protected function getUserBySocket($socket) {
        foreach ($this->users as $user) {
            if ($user->socket === $socket) {
                return $user;
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
            error_log("$message\n");
        }
    }

    /**
     * @param $message
     * @return void
     */
    public function stderr($message) {
        if ($this->interactive) {
            error_log("$message\n");
        }
    }

    /**
     * @param $message
     * @param $user
     * @param $messageType
     * @param $messageContinues
     * @return string
     */
    protected function frame($message, $user, $messageType = 'text', $messageContinues = false) {
        switch ($messageType) {
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
        if ($messageContinues) {
            $user->sendingContinuous = true;
        } else {
            $b1 += 128;
            $user->sendingContinuous = false;
        }

        $length = strlen($message);
        $lengthField = "";
        if ($length < 126) {
            $b2 = $length;
        }
        elseif ($length < 65536) {
            $b2 = 126;
            $hexLength = dechex($length);
            //$this->stdout("Hex Length: $hexLength");
            if (strlen($hexLength)%2 == 1) {
                $hexLength = '0' . $hexLength;
            }
            $n = strlen($hexLength) - 2;

            for ($i = $n; $i >= 0; $i -= 2) {
                $lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
            }
            while (strlen($lengthField) < 2) {
                $lengthField = chr(0) . $lengthField;
            }
        } else {
            $b2 = 127;
            $hexLength = dechex($length);
            if (strlen($hexLength)%2 == 1) {
                $hexLength = '0' . $hexLength;
            }
            $n = strlen($hexLength) - 2;

            for ($i = $n; $i >= 0; $i -= 2) {
                $lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
            }
            while (strlen($lengthField) < 8) {
                $lengthField = chr(0) . $lengthField;
            }
        }

        return chr($b1) . chr($b2) . $lengthField . $message;
    }

    /**
     * @param $headers
     * @return int
     */
    protected function calcoffset($headers) {
        $offset = 2;
        if ($headers['hasmask']) {
            $offset += 4;
        }
        if ($headers['length'] > 65535) {
            $offset += 8;
        } elseif ($headers['length'] > 125) {
            $offset += 2;
        }
        return $offset;
    }

    /**
     * @param $message
     * @param $user
     * @return false|int|mixed|string
     */
    protected function deframe($message, &$user) {
        $headers = $this->extractHeaders($message);
        $pongReply = false;
        $willClose = false;
        switch($headers['opcode']) {
            case 0:
            case 1:
            case 10:
            case 2:
                break;
            case 8:
                // todo: close the connection
                $user->hasSentClose = true;
                return "";
            case 9:
                $pongReply = true;
                break;
            default:
                //$this->disconnect($user); // todo: fail connection
                $willClose = true;
                break;
        }

        /* Deal by split_packet() as now deframe() do only one frame at a time.
        if ($user->handlingPartialPacket) {
          $message = $user->partialBuffer . $message;
          $user->handlingPartialPacket = false;
          return $this->deframe($message, $user);
        }
        */

        if ($this->checkRSVBits($headers,$user)) {
            return false;
        }

        if ($willClose) {
            // todo: fail the connection
            return false;
        }

        $payload = $user->partialMessage . $this->extractPayload($message,$headers);

        if ($pongReply) {
            $reply = $this->frame($payload,$user,'pong');
            // socket_write($user->socket,$reply,strlen($reply));
            stream_socket_sendto($user->socket, $reply);
            return false;
        }
        if ($headers['length'] > strlen($this->applyMask($headers,$payload))) {
            $user->handlingPartialPacket = true;
            $user->partialBuffer = $message;
            return false;
        }

        $payload = $this->applyMask($headers,$payload);

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
    protected function extractHeaders($message) {
        $header = array('fin'     => $message[0] & chr(128),
            'rsv1'    => $message[0] & chr(64),
            'rsv2'    => $message[0] & chr(32),
            'rsv3'    => $message[0] & chr(16),
            'opcode'  => ord($message[0]) & 15,
            'hasmask' => $message[1] & chr(128),
            'length'  => 0,
            'mask'    => "");
        $header['length'] = (ord($message[1]) >= 128) ? ord($message[1]) - 128 : ord($message[1]);

        if ($header['length'] == 126) {
            if ($header['hasmask']) {
                $header['mask'] = $message[4] . $message[5] . $message[6] . $message[7];
            }
            $header['length'] = ord($message[2]) * 256
                + ord($message[3]);
        }
        elseif ($header['length'] == 127) {
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
        }
        elseif ($header['hasmask']) {
            $header['mask'] = $message[2] . $message[3] . $message[4] . $message[5];
        }
        //echo $this->strtohex($message);
        //$this->printHeaders($header);
        return $header;
    }

    /**
     * @param $message
     * @param $headers
     * @return false|string
     */
    protected function extractPayload($message, $headers) {
        $offset = 2;
        if ($headers['hasmask']) {
            $offset += 4;
        }
        if ($headers['length'] > 65535) {
            $offset += 8;
        }
        elseif ($headers['length'] > 125) {
            $offset += 2;
        }
        return substr($message,$offset);
    }

    /**
     * @param $headers
     * @param $payload
     * @return int|mixed
     */
    protected function applyMask($headers, $payload) {
        $effectiveMask = "";
        if ($headers['hasmask']) {
            $mask = $headers['mask'];
        }
        else {
            return $payload;
        }

        while (strlen($effectiveMask) < strlen($payload)) {
            $effectiveMask .= $mask;
        }
        while (strlen($effectiveMask) > strlen($payload)) {
            $effectiveMask = substr($effectiveMask,0,-1);
        }
        return $effectiveMask ^ $payload;
    }

    /**
     * @param $headers
     * @param $user
     * @return bool
     */
    protected function checkRSVBits($headers, $user) { // override this method if you are using an extension where the RSV bits are used.
        if (ord($headers['rsv1']) + ord($headers['rsv2']) + ord($headers['rsv3']) > 0) {
            //$this->disconnect($user); // todo: fail connection
            return true;
        }
        return false;
    }

    /**
     * @param $str
     * @return string
     */
    protected function strtohex($str) {
        $strout = "";
        for ($i = 0, $iMax = strlen($str); $i < $iMax; $i++) {
            $strout .= (ord($str[$i])<16) ? "0" . dechex(ord($str[$i])) : dechex(ord($str[$i]));
            $strout .= " ";
            if ($i%32 == 7) {
                $strout .= ": ";
            }
            if ($i%32 == 15) {
                $strout .= ": ";
            }
            if ($i%32 == 23) {
                $strout .= ": ";
            }
            if ($i%32 == 31) {
                $strout .= "\n";
            }
        }
        return $strout . "\n";
    }

    /**
     * @param $headers
     * @return void
     */
    protected function printHeaders($headers) {
        error_log("Array\n(\n");
        foreach ($headers as $key => $value) {
            if ($key == 'length' || $key == 'opcode') {
                error_log("\t[$key] => $value\n\n");
            }
            else {
                error_log("\t[$key] => ".$this->strtohex($value)."\n");
            }

        }
        echo ")\n";
    }

}
