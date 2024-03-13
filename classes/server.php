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
 * Kuet websocket server
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use mod_kuet\websocketuser;

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/websockets.php');
require_once(__DIR__ . '/../lib.php');

/**
 * Kuet websocket server class
 */
class server extends websockets {
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
     * @var array session id group users
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
     * Check if user is connected
     *
     * @param $user
     * @return void
     */
    protected function connected($user) {
        // TODO log user connected. This function is called by handshake.
    }

    /**
     * Set socket connection
     *  This function is called after handshake, by websockets.php.
     *
     * @param $socket
     * @param $ip
     * @return void
     * @throws JsonException
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
     * Close group member connection
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
     * Close user connection
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
                    '<span style="color: red">' . get_string('userdisconnected', 'mod_kuet', $user->dataname) . '</span>',
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
        if ((count($this->sockets) === 0) || (count($this->users) === 0)) {
            // die(); // No one is connected to the socket. It closes and will be reopened by the first teacher who logs in.
        }
    }

    /**
     * Get response from action for teacher user
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
     * Get group id from a user
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
     * Get the response from an action for a group of users
     *
     * @param array $data
     * @return string
     * @throws JsonException
     */
    protected function get_response_from_action_for_group(array $data) : string {
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
     * Get the response from an action for a student user
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
     * Get the response from and action
     *
     * @param websocketuser $user // The user who sent the message.
     * @param string $useraction // The action it requires.
     * @param array $data // Body of the message.
     * @return string // Json enconde.
     * @throws JsonException|coding_exception
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
     * Set new user for the socket
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
     * Set new user group for the socket
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
     * Manage new teacher of the session id
     *
     * @param websocketuser $user
     * @param array $data
     * @return string
     * @throws JsonException
     * @throws coding_exception
     */
    private function manage_newteacher_for_sid(websocketuser $user, array $data): string {
        if (isset($this->teacher[$data['sid']]) && count($this->teacher[$data['sid']]) === 1) {
            // There can only be one teacher in each session to avoid conflicts of functionality.
            $response = $this->mask(
                kuet_encrypt($this->password, json_encode([
                        'action' => 'alreadyteacher',
                        'message' => get_string('alreadyteacher', 'mod_kuet')
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
     * Manage new student for the session id
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
     * Manage new group for session id
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

$port = get_config('kuet', 'localport');
$server = new server('0.0.0.0', $port, 2048);

try {
    $server->run();
} catch (Exception $e) {
    $server->stdout($e->getMessage());
    throw $e;
}
