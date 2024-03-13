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
 * CLI version of websocket server
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
declare(strict_types=1);

/**
 * CLI version of websocket server
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
     * @var string password
     */
    protected $password = 'elktkktagqes';

    /**
     * Process message
     *
     * @param $user
     * @param $message
     * @return void
     * @throws JsonException
     * @throws coding_exception
     */
    protected function process($user, $message) {
        // Sends a message to all users on the socket belonging to the same "sid" session.
        $data = json_decode(
            mb_convert_encoding($message, 'UTF-8', 'UTF-8'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        if (isset($data['oft']) && $data['oft'] === true) {
            // Only for teacher.
            $responsetext = $this->get_response_from_action_for_teacher($user, $data['action'], $data);
            if ($responsetext !== '' && isset($this->sidusers[$data['sid']])) {
                foreach ($this->teacher[$data['sid']] as $teacher) {
                    fwrite($teacher->socket, $responsetext, strlen($responsetext));
                }
            }
        } else if (isset($data['ofs']) && $data['ofs'] === true) {
            // Only for student.
            $responsetext = $this->get_response_from_action_for_student($user, $data['action'], $data);
            if ($responsetext !== '') {
                foreach ($this->sockets as $key => $socket) {
                    if ($key === $data['usersocketid']) {
                        fwrite($socket, $responsetext, strlen($responsetext));
                        break;
                    }
                }
            }
        } else if (isset($data['ofg']) && $data['ofg'] === true) {
            // Only for groups.
            $responsetext = $this->get_response_from_action_for_group($data);
            $groupid = $this->get_groupid_from_a_member((int) $data['sid'], (int) $data['userid']);
            if ($responsetext !== '' && $groupid) {
                $socketgroups = $this->sidgroups[$data['sid']];
                foreach ($socketgroups[$groupid]->users as $usergroup) {
                    foreach ($this->sockets as $key => $socket) {
                        if ($key === $usergroup->usersocketid) {
                            fwrite($socket, $responsetext, strlen($responsetext));
                            break;
                        }
                    }
                }
            }
        } else { // All users in this sid.
            $responsetext = $this->get_response_from_action($user, $data['action'], $data);
            if ($responsetext !== '' && isset($this->sidusers[$data['sid']])) {
                foreach ($this->sidusers[$data['sid']] as $usersaved) {
                    fwrite($usersaved->socket, $responsetext, strlen($responsetext));
                }
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
        // TODO log user connected. This function is called by handshake.
    }

    /**
     * Connect socket
     *
     * @param $socket
     * @param $ip
     * @return void
     * @throws JsonException
     * This function is called after handshake, by websockets.php.
     */
    protected function connect($socket, $ip) {
        $user = new websocketuser(uniqid('u', true), $socket, $ip);
        // Add the user to the list of all users on the socket.
        $this->users[$user->usersocketid] = $user;
        $this->sockets[$user->usersocketid] = $socket;
        /* inactivity time for SSL client https://bugs.php.net/bug.php?id=70939
        $sock = socket_import_stream ($socket);
        socket_set_option($sock, SOL_SOCKET, SO_KEEPALIVE, 1);*/
        $response = $this->mask(
            kuet_encrypt($this->password, json_encode([
                'action' => 'connect',
                'usersocketid' => $user->usersocketid
            ], JSON_THROW_ON_ERROR))
        );
        // We return the usersocketid only to the new user so that responds by identifying with newuser.
        fwrite($user->socket, $response, strlen($response));
        $this->connecting($user);
    }
    /**
     * Close group member connection to socket
     *
     * @param $user
     * @return void
     * @throws JsonException
     * @throws coding_exception
     */
    protected function close_groupmember($user) {
        $groupmemberdisconected = false;
        $groupdisconected = false;
        $groupid = 0;
        $groupname = '';
        $numgroups = 0;
        if (array_key_exists($user->usersocketid, $this->sidgroupusers)) {
            $groupmemberdisconected = true;
            $groupid = $this->sidgroupusers[$user->usersocketid];
            $groupname = $this->sidgroups[$user->sid][$groupid]->groupname;
            $numusers = count($this->sidgroups[$user->sid][$groupid]->users);
            $numgroups = count($this->sidgroups[$user->sid]);
            unset($this->sidgroups[$user->sid][$groupid]->users[$user->usersocketid], $this->sidgroupusers[$user->usersocketid]);
            --$numusers;
            if ($numusers === 0) {
                unset($this->sidgroups[$user->sid][$groupid]);
                --$numgroups;
                $groupdisconected = true;
            }
        }
        if ($groupdisconected) {
            $groupresponse = $this->mask(
                kuet_encrypt($this->password, json_encode([
                    'action' => 'groupdisconnected',
                    'usersocketid' => $user->usersocketid,
                    'groupid' => $groupid,
                    'message' =>
                        '<span style="color: red">' . $groupname . ' disconnected </span>',
                    'count' => $numgroups
                ], JSON_THROW_ON_ERROR)));
            if (isset($this->sidusers[$user->sid])) {
                foreach ($this->sidusers[$user->sid] as $usersaved) {
                    fwrite($usersaved->socket, $groupresponse, strlen($groupresponse));
                }
            }
        } else if ($groupmemberdisconected) {
            $groupresponse = $this->mask(
                kuet_encrypt($this->password, json_encode([
                    'action' => 'groupmemberdisconnected',
                    'usersocketid' => $user->usersocketid,
                    'groupid' => $groupid,
                    'message' =>
                        '<span style="color: red"> Group member ' . $user->dataname . ' has been disconnected. </span>',
                    'count' => $numusers
                ], JSON_THROW_ON_ERROR)));
            if (isset($this->sidusers[$user->sid])) {
                foreach ($this->sidusers[$user->sid] as $usersaved) {
                    fwrite($usersaved->socket, $groupresponse, strlen($groupresponse));
                }
            }
        }
    }

    /**
     * Closed connection routine
     *
     * @param $user
     * @return void
     * @throws JsonException
     * @throws coding_exception
     */
    protected function closed($user) {
        unset($this->sidusers[$user->sid][$user->usersocketid],
            $this->students[$user->sid][$user->usersocketid]);
        // Group mode.
        $this->close_groupmember($user);
        $response = $this->mask(
            kuet_encrypt($this->password, json_encode([
                'action' => 'userdisconnected',
                'usersocketid' => $user->usersocketid,
                'message' =>
                    '<span style="color: red">' . "User $user->dataname has been disconnected."  . '</span>',
                'count' => isset($this->students[$user->sid]) ? count($this->students[$user->sid]) : 0
            ], JSON_THROW_ON_ERROR)));
        if (isset($this->sidusers[$user->sid])) {
            foreach ($this->sidusers[$user->sid] as $usersaved) {
                fwrite($usersaved->socket, $response, strlen($response));
            }
        }
        if ($user->isteacher) {
            unset($this->teacher[$user->sid]);
            if (isset($this->sidusers[$user->sid])) {
                foreach ($this->sidusers[$user->sid] as $socket) {
                    $this->disconnect($socket->socket);
                    fclose($socket->socket);
                    unset($this->students[$user->sid], $this->sidusers[$user->sid]);
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
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'studentQuestionEnd',
                            'onlyforteacher' => true,
                            'context' => $data,
                            'message' => 'El alumno ' . $data['userid'] . ' ha contestado una pregunta' // TODO delete.
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'ImproviseStudentTag':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'ImproviseStudentTag',
                            'onlyforteacher' => true,
                            'improvisereply' => $data['improvisereply'],
                            'userid' => $data['userid'],
                            'message' => ''
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'StudentVotedTag':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'StudentVotedTag',
                            'onlyforteacher' => true,
                            'votedtag' => $data['votedtag'],
                            'userid' => $data['userid'],
                            'message' => ''
                        ], JSON_THROW_ON_ERROR)
                    ));
            default:
                return '';
        }
    }
    /**
     * Get group id for a member
     *
     * @param int $sid
     * @param int $userid
     * @return int
     */
    protected function get_groupid_from_a_member(int $sid, int $userid) : int {
        $groupid = 0;
        if (!array_key_exists($sid, $this->sidgroups)) {
            return $groupid;
        }
        foreach ($this->sidgroups[$sid] as $sidgroup) {
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
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'alreadyAnswered',
                            'userid' => $data['userid'],
                            'kid' => $data['kid'],
                        ], JSON_THROW_ON_ERROR)
                    ));
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
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'question',
                            'context' => $data['context'],
                        ], JSON_THROW_ON_ERROR)
                    ));
            default:
                return '';
        }
    }

    /**
     * Get response from action
     *
     * @param websocketuser $user // The user who sent the message.
     * @param string $useraction // The action it requires.
     * @param array $data // Body of the message.
     * @return string // Json enconde.
     * @throws JsonException
     */
    protected function get_response_from_action(websocketuser $user, string $useraction, array $data): string {
        // Prepare data to be sent to client.
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
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'countusers',
                            'count' => count($this->students[$data['sid']]),
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'question':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'question',
                            'context' => $data['context'],
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'ranking':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'ranking',
                            'context' => $data['context'],
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'endSession':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'endSession',
                            'context' => $data['context'],
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'teacherQuestionEnd':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'teacherQuestionEnd',
                            'kid' => $data['kid'],
                            'statistics' => $data['statistics']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'pauseQuestion':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'pauseQuestion',
                            'kid' => $data['kid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'playQuestion':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'playQuestion',
                            'kid' => $data['kid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'showAnswers':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'showAnswers',
                            'kid' => $data['kid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'hideAnswers':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'hideAnswers',
                            'kid' => $data['kid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'showStatistics':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'showStatistics',
                            'kid' => $data['kid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'hideStatistics':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'hideStatistics',
                            'kid' => $data['kid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'showFeedback':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'showFeedback',
                            'kid' => $data['kid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'hideFeedback':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'hideFeedback',
                            'kid' => $data['kid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'improvising':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'improvising',
                            'kid' => $data['kid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'closeImprovise':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'closeImprovise',
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'improvised':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'improvised',
                            'improvisestatement' => $data['improvisestatement'],
                            'improvisereply' => $data['improvisereply'],
                            'cmid' => $data['cmid'],
                            'sessionid' => $data['sid'],
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'printNewTag':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'printNewTag',
                            'tags' => $data['tags']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'initVote':
                return $this->mask(
                    kuet_encrypt($this->password, json_encode([
                            'action' => 'initVote'
                        ], JSON_THROW_ON_ERROR)
                    ));
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
        if (!array_key_exists($data['sid'], $this->sidgroups)) {
            $this->sidgroups[$data['sid']] = [];
        }
        if (!array_key_exists($data['groupid'], $this->sidgroups[$data['sid']])) {
            $this->sidgroups[$data['sid']][$data['groupid']] = new stdClass();
            $this->sidgroups[$data['sid']][$data['groupid']]->users = [];
        }
        $this->sidgroups[$data['sid']][$data['groupid']]->groupid = $data['groupid'];
        $this->sidgroups[$data['sid']][$data['groupid']]->groupname = $data['name'];
        $this->sidgroups[$data['sid']][$data['groupid']]->grouppicture = $data['pic'];
        $this->sidgroups[$data['sid']][$data['groupid']]->sid = $data['sid'];
        $this->sidgroups[$data['sid']][$data['groupid']]->cmid = $data['cmid'];
        if (!array_key_exists($data['usersocketid'], $this->sidgroups[$data['sid']][$data['groupid']]->users)) {
            $this->sidgroups[$data['sid']][$data['groupid']]->users[$user->usersocketid] = new stdClass();
            $this->sidgroups[$data['sid']][$data['groupid']]->users[$user->usersocketid]->usersocketid = $data['usersocketid'];
            $this->sidgroups[$data['sid']][$data['groupid']]->users[$user->usersocketid]->userid = $data['userid'];
            $this->sidgroupusers[$data['usersocketid']] = $data['groupid'];
        }
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
        if (isset($this->teacher[$data['sid']]) && count($this->teacher[$data['sid']]) === 1) {
            // There can only be one teacher in each session to avoid conflicts of functionality.
            $response = $this->mask(
                kuet_encrypt($this->password, json_encode([
                        'action' => 'alreadyteacher',
                        'message' => 'There is already a teacher imparting this session, so you cannot connect. Please wait for the current session to end before you can enter.'
                    ], JSON_THROW_ON_ERROR)
                ));
            $usersocket = $this->get_socket_by_user($user);
            fwrite($usersocket, $response, strlen($response));
            $this->disconnect($this->users[$user->usersocketid]->socket);
            fclose($usersocket);
            unset($this->users[$user->usersocketid]);
            return '';
        }
        $user->isteacher = true;
        $this->users[$user->usersocketid]->isteacher = true;
        $this->teacher[$data['sid']][$user->usersocketid] = $this->users[$user->usersocketid];
        $this->sidusers[$data['sid']][$user->usersocketid] = $this->users[$user->usersocketid];
        return $this->mask(
            kuet_encrypt($this->password, json_encode([
                'action' => 'newteacher',
                'name' => $data['name'] ?? '',
                'userid' => $user->id ?? '',
                'message' => '<span style="color: green">The teacher ' . $user->dataname . ' has connected</span>',
                'count' => isset($this->sidusers[$data['sid']]) ? count($this->sidusers[$data['sid']]) : 0,
            ], JSON_THROW_ON_ERROR))
        );
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
        $duplicateresolve = false;
        if (isset($this->students[$data['sid']])) {
            foreach ($this->students[$data['sid']] as $usersocketid => $studentsid) {
                if ($studentsid->userid === $data['userid']) {
                    // There can only be one same user in each session to avoid conflicts of functionality.
                    foreach ($this->sockets as $key => $socket) {
                        if ($key === $usersocketid) {
                            $this->disconnect($socket);
                            fclose($socket);
                            unset($this->users[$usersocketid]);
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
        $this->sidusers[$data['sid']][$user->usersocketid] = $this->users[$user->usersocketid];
        $this->students[$data['sid']][$user->usersocketid] = $this->users[$user->usersocketid];
        $studentsdata = [];
        foreach ($this->students[$data['sid']] as $key => $student) {
            $studentsdata[$key]['picture'] = $student->picture;
            $studentsdata[$key]['usersocketid'] = $student->usersocketid;
            $studentsdata[$key]['name'] = $student->dataname;
        }
        return $this->mask(
            kuet_encrypt($this->password, json_encode([
                'action' => 'newuser',
                'usersocketid' => $user->usersocketid,
                'students' => array_values($studentsdata),
                'count' => count($this->students[$data['sid']])
            ], JSON_THROW_ON_ERROR)
        ));
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
        $this->users[$user->usersocketid]->isteacher = false;
        $this->sidusers[$data['sid']][$user->usersocketid] = $this->users[$user->usersocketid];
        $this->students[$data['sid']][$user->usersocketid] = $this->users[$user->usersocketid];

        $groupsdata = [];
        foreach ($this->sidgroups[$data['sid']] as $key => $group) {
            $groupsdata[$key]['groupid'] = $group->groupid;
            $groupsdata[$key]['picture'] = $group->grouppicture;
            $groupsdata[$key]['usersocketid'] = $data['usersocketid'];
            $groupsdata[$key]['name'] = $group->groupname;
            $groupsdata[$key]['numgroupusers'] = count($group->users);
        }
        return $this->mask(
            kuet_encrypt($this->password, json_encode([
                    'action' => 'newgroup',
                    'usersocketid' => $user->usersocketid,
                    'groups' => array_values($groupsdata),
                    'count' => count($this->sidgroups[$data['sid']])
                ], JSON_THROW_ON_ERROR)
            ));
    }
}

/**
 * Websocket class
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
     * @var array held message
     */
    protected $heldmessages = [];
    /**
     * @var bool interactive
     */
    protected $interactive = true;
    /**
     * @var address
     */
    protected $addr;
    /**
     * @var int port
     */
    protected $port;

    /**
     * Constructor
     *
     * @param $addr
     * @param int $bufferlength
     * @throws Exception
     */
    public function __construct($addr, $bufferlength = 2048) {
        global $_SERVER;
        $this->addr = $addr;
        if (PHP_SAPI !== 'cli') {
            throw new Exception('This application must be run on the command line.');
        }

        if (isset($_SERVER['argv'][1]) && isset($_SERVER['argv'][2]) && isset($_SERVER['argv'][3])) {
            [$script, $port, $certificate, $privatekey] = $_SERVER['argv'];
        } else {
            $this->executeform();
            echo "\033[32m". PHP_EOL .
                'Socket is running in the background. You can see the process running in the process list of your server.' .
                "\033[0m" . PHP_EOL;
            die();
        }

        $this->port = (int)$port;
        $this->maxbuffersize = $bufferlength;
        // Certificate data.
        $context = stream_context_create();
        // Local_cert and local_pk must be in PEM format.
        stream_context_set_option($context, 'ssl', 'local_cert', $certificate);
        stream_context_set_option($context, 'ssl', 'local_pk', $privatekey);
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
        $this->stdout(PHP_EOL . "\033[37m" .
            "Server started" . PHP_EOL . "Listening on: $addr:$port " . PHP_EOL . "Master socket: $this->master" .
            "\033[0m");

    }

    /**
     * Execute config form
     *
     * @return void
     */
    private function executeform() {
        echo "\033[34m". "Enter the port number for external connections." . PHP_EOL .
            "This port must be open, and must be provided to the Moodle platforms to be connected: " . "\033[0m" . PHP_EOL;
        $port = readline("");
        if (is_numeric($port)) {
            // $connection = @fsockopen('localhost', (int)$port); TODO system to check ports.
            if ($port !== '') {
                readline_add_history($port);
                echo "\033[35m". PHP_EOL . "Enter the path where the server certificate is located." . PHP_EOL .
                    "The file must have .crt or .pem extension file of a valid SSL certificate for the server." . PHP_EOL .
                    "This file may already be generated on the same server as this script:" . "\033[0m" . PHP_EOL;
                $certificate = readline("");
                if (@file_exists($certificate) &&
                    (pathinfo($certificate)['extension'] === 'crt' || pathinfo($certificate)['extension'] === 'pem')) {
                    readline_add_history($certificate);
                    echo "\033[33m". PHP_EOL . "Enter the path where the Private Key file is located." . PHP_EOL .
                        "The file must have .key or .pem extension file of a valid SSL Private Key for the server." . PHP_EOL .
                        "This file may already be generated on the same server as this script:" . "\033[0m" . PHP_EOL;
                    $privatekey = readline( "");
                    if (@file_exists($privatekey) &&
                        (pathinfo($privatekey)['extension'] === 'crt' || pathinfo($privatekey)['extension'] === 'pem')) {
                        readline_add_history($privatekey);
                        $reference = $port . ' ' . $certificate . ' ' . $privatekey;
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
                        echo "\033[31m" . $privatekey . ' It is not a valid private key. Rerun the script' . "\033[0m" . PHP_EOL;
                        exit();
                    }
                } else {
                    echo "\033[31m" . $certificate . ' It is not a valid certificate. Rerun the script' . "\033[0m" . PHP_EOL;
                    exit();
                }
            } else {
                echo "\033[31m" . $port .
                    ' port is not responding, or the fsockopen method could not check it.' .
                    "\033[0m" . PHP_EOL;
                exit();
            }
        } else {
            echo "\033[31m" . $port . ' Not a valid port. Rerun the script' . "\033[0m" . PHP_EOL;
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
            $header = pack('CCNN', $b1, 127, $length);
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
     * Run websocket process
     *
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
                $this->stdout("\033[34m" . "Connection attempt from $ip" . "\033[0m");
                stream_set_blocking($client, true);
                $headers = fread($client, 1500);
                $this->handshake($client, $headers);
                stream_set_blocking($client, false);

                $foundsocket = array_search($this->master, $read, true);
                unset($read[$foundsocket]);

                $this->stdout("\033[33m" . "Handshake $ip" . "\033[0m");

                if ($client < 0) {
                    $this->stdout("\033[31m" . "Failed: socket_accept()" . "\033[0m");
                    continue;
                }

                $this->connect($client, $ip);
                $this->stdout("\033[32m" . "Client connected. $client" . "\033[0m");
            }

            foreach ($read as $socket) {
                $ip = stream_socket_get_name( $socket, true );
                $buffer = stream_get_contents($socket);
                // TODO review detect disconnect for min buffer lenght.
                if ($buffer === false || strlen($buffer) <= 8) {
                    if ($this->unmask($buffer) !== '') { // Necessary to stabilise connections, review.
                        $this->disconnect($socket);
                        $this->stdout("\033[1;30m" . "Client disconnected. TCP connection lost: " . $socket . "\033[0m");
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
            if (array_key_exists($disconnecteduser->usersocketid, $this->sockets)) {
                unset($this->sockets[$disconnecteduser->usersocketid]);
            }
            if (!is_null($sockerrno)) {
                $this->stdout("\033[31m" . $sockerrno . "\033[0m");
            }
            if ($triggerclosed) {
                $this->stdout("\033[1;30m" . "Client disconnected. ".$disconnecteduser->socket . "\033[0m");
                $this->closed($disconnecteduser);
                stream_socket_shutdown($disconnecteduser->socket, STREAM_SHUT_RDWR);
            } else {
                $message = $this->frame('', $disconnecteduser, 'close');
                fwrite($disconnecteduser->socket, $message, strlen($message));
            }
        }
    }

    /**
     * Handshake process
     *
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
            if ($user->socket === $socket) {
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
                $lengthfield = unimoodleservercli . phpchr(hexdec(substr($hexlength, $i, 2))) . $lengthfield;
            }
            while (strlen($lengthfield) < 2) {
                $lengthfield = unimoodleservercli . phpchr(0) . $lengthfield;
            }
        } else {
            $b2 = 127;
            $hexlength = dechex($length);
            if (strlen($hexlength) % 2 === 1) {
                $hexlength = '0' . $hexlength;
            }
            $n = strlen($hexlength) - 2;

            for ($i = $n; $i >= 0; $i -= 2) {
                $lengthfield = unimoodleservercli . phpchr(hexdec(substr($hexlength, $i, 2))) . $lengthfield;
            }
            while (strlen($lengthfield) < 8) {
                $lengthfield = unimoodleservercli . phpchr(0) . $lengthfield;
            }
        }

        return unimoodleservercli . phpchr($b1) . chr($b2) . $lengthfield . $message;
    }

    /**
     * Extract message headers
     *
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
                debugging("\t[$key] => ".$this->str_to_hex($value)."\n");
            }

        }
        echo ")\n";
    }
}

/**
 * Websocket user class
 */
class websocketuser {
    /**
     * @var socket
     */
    public $socket;
    /**
     * @var socket user id
     */
    public $usersocketid;
    /**
     * @var ip
     */
    public $ip;
    /**
     * @var array headers
     */
    public $headers = [];
    /**
     * @var moodle username
     */
    public $dataname; // Moodle Username.
    /**
     * @var is teacher flag
     */
    public $isteacher;
    /**
     * @var bool handshake flag
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

$server = new unimoodleservercli('0.0.0.0', 2048);
try {
    $server->run();
} catch (Exception $e) {
    $server->stdout("\033[31m" . $e->getMessage() . "\033[0m");
    // throw $e;
}