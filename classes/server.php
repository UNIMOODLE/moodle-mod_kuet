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

/**
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use mod_jqshow\websocketuser;

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/websockets.php');
require_once(__DIR__ . '/../lib.php');

class server extends websockets {

    protected $students = [];
    protected $teacher = [];
    protected $sidusers = [];
    protected $sidgroups = [];
    protected $password = 'elktkktagqes';

    /**
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
     * @param $user
     * @return void
     */
    protected function connected($user) {
        // TODO log user connected. This function is called by handshake.
    }

    /**
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

        $response = $this->mask(
            encrypt($this->password, json_encode([
                'action' => 'connect',
                'usersocketid' => $user->usersocketid
            ], JSON_THROW_ON_ERROR))
        );
        // We return the usersocketid only to the new user so that responds by identifying with newuser.
        fwrite($user->socket, $response, strlen($response));

        $this->connecting($user);
    }

    /**
     * @param $user
     * @return void
     * @throws JsonException
     * @throws coding_exception
     */
    protected function closed($user) {
        unset($this->sidusers[$user->sid][$user->usersocketid],
            $this->students[$user->sid][$user->usersocketid]);
        $response = $this->mask(
            encrypt($this->password, json_encode([
                'action' => 'userdisconnected',
                'usersocketid' => $user->usersocketid,
                'message' =>
                    '<span style="color: red">' . get_string('userdisconnected', 'mod_jqshow', $user->dataname) . '</span>',
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
                    // jqshow_sessions::mark_session_finished((int)$user->sid);
                }
            }
        }
        if ((count($this->sockets) === 0) || (count($this->users) === 0)) {
            // die(); // No one is connected to the socket. It closes and will be reopened by the first teacher who logs in.
        }
    }

    /**
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
                    encrypt($this->password, json_encode([
                            'action' => 'studentQuestionEnd',
                            'onlyforteacher' => true,
                            'context' => $data,
                            'message' => 'El alumno ' . $data['userid'] . ' ha contestado una pregunta' // TODO delete.
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'ImproviseStudentTag':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'ImproviseStudentTag',
                            'onlyforteacher' => true,
                            'improvisereply' => $data['improvisereply'],
                            'userid' => $data['userid'],
                            'message' => 'El alumno ' . $data['userid'] . ' ha contestado una pregunta improvisada con la palabra: ' . $data['improvisereply'] // TODO delete.
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'StudentVotedTag':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'StudentVotedTag',
                            'onlyforteacher' => true,
                            'votedtag' => $data['votedtag'],
                            'userid' => $data['userid'],
                            'message' => 'El alumno ' . $data['userid'] . ' ha votado una pregunta improvisada con la palabra: ' . $data['votedtag'] // TODO delete.
                        ], JSON_THROW_ON_ERROR)
                    ));
            default:
                return '';
        }
    }

    /**
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
                    encrypt($this->password, json_encode([
                            'action' => 'question',
                            'context' => $data['context'],
                        ], JSON_THROW_ON_ERROR)
                    ));
            default:
                return '';
        }
    }

    /**
     * @param websocketuser $user // The user who sent the message.
     * @param string $useraction // The action it requires.
     * @param array $data // Body of the message.
     * @return string // Json enconde.
     * @throws JsonException
     * @throws coding_exception
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
                    encrypt($this->password, json_encode([
                            'action' => 'countusers',
                            'count' => count($this->students[$data['sid']]),
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'question':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'question',
                            'context' => $data['context'],
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'ranking':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'ranking',
                            'context' => $data['context'],
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'endSession':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'endSession',
                            'context' => $data['context'],
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'teacherQuestionEnd':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'teacherQuestionEnd',
                            'jqid' => $data['jqid'],
                            'statistics' => $data['statistics']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'pauseQuestion':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'pauseQuestion',
                            'jqid' => $data['jqid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'playQuestion':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'playQuestion',
                            'jqid' => $data['jqid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'showAnswers':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'showAnswers',
                            'jqid' => $data['jqid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'hideAnswers':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'hideAnswers',
                            'jqid' => $data['jqid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'showStatistics':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'showStatistics',
                            'jqid' => $data['jqid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'hideStatistics':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'hideStatistics',
                            'jqid' => $data['jqid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'showFeedback':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'showFeedback',
                            'jqid' => $data['jqid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'hideFeedback':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'hideFeedback',
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'improvising':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'improvising',
                            'jqid' => $data['jqid']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'closeImprovise':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'closeImprovise',
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'improvised':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'improvised',
                            'improvisestatement' => $data['improvisestatement'],
                            'improvisereply' => $data['improvisereply'],
                            'cmid' => $data['cmid'],
                            'sessionid' => $data['sid'],
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'printNewTag':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'printNewTag',
                            'tags' => $data['tags']
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'initVote':
                return $this->mask(
                    encrypt($this->password, json_encode([
                            'action' => 'initVote'
                        ], JSON_THROW_ON_ERROR)
                    ));
            case 'shutdownTest':
            default:
                return '';
        }
    }

    /**
     * @param websocketuser $user
     * @param array $data
     * @return void
     */
    private function newuser(websocketuser $user, array $data) {
        $this->users[$user->usersocketid]->dataname = $data['name'];
        $this->users[$user->usersocketid]->picture = $data['pic'];
        $this->users[$user->usersocketid]->userid = $data['userid'];
        $this->users[$user->usersocketid]->usersocketid = $data['usersocketid'];
        $this->users[$user->usersocketid]->sid = $data['sid'];
        $this->users[$user->usersocketid]->cmid = $data['cmid'];
        $user->update_user($data);
    }

    /**
     * @param array $data
     * @return void
     */
    private function newgroup(websocketuser $user, array $data) {
        $this->sidgroups[$data['sid']][$data['groupid']]->groupid = $data['groupid'];
        $this->sidgroups[$data['sid']][$data['groupid']]->groupname = $data['name'];
        $this->sidgroups[$data['sid']][$data['groupid']]->grouppicture = $data['pic'];
        $this->sidgroups[$data['sid']][$data['groupid']]->sid = $data['sid'];
        $this->sidgroups[$data['sid']][$data['groupid']]->cmid = $data['cmid'];
        $this->sidgroups[$data['sid']][$data['groupid']]->users[$user->usersocketid]->usersocketid = $data['usersocketid'];
        $this->sidgroups[$data['sid']][$data['groupid']]->users[$user->usersocketid]->userid = $data['userid'];
    }
    /**
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
                encrypt($this->password, json_encode([
                        'action' => 'alreadyteacher',
                        'message' => get_string('alreadyteacher', 'mod_jqshow')
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
            encrypt($this->password, json_encode([
                'action' => 'newteacher',
                'name' => $data['name'] ?? '',
                'userid' => $user->id ?? '',
                'message' => '<span style="color: green">El profesor ' . $user->dataname . ' se ha conectado</span>',
                'count' => isset($this->sidusers[$data['sid']]) ? count($this->sidusers[$data['sid']]) : 0,
            ], JSON_THROW_ON_ERROR))
        );
    }

    /**
     * @param websocketuser $user
     * @param array $data
     * @return string
     * @throws JsonException
     */
    private function manage_newstudent_for_sid(websocketuser $user, array $data): string {
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
            encrypt($this->password, json_encode([
                'action' => 'newuser',
                'usersocketid' => $user->usersocketid,
                'students' => array_values($studentsdata),
                'count' => count($this->students[$data['sid']])
            ], JSON_THROW_ON_ERROR)
        ));
    }

    /**
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
            encrypt($this->password, json_encode([
                    'action' => 'newgroup',
                    'usersocketid' => $user->usersocketid,
                    'groups' => array_values($groupsdata),
                    'count' => count($this->sidgroups[$data['sid']])
                ], JSON_THROW_ON_ERROR)
            ));
    }
}

$port = get_config('jqshow', 'localport');
$server = new server('0.0.0.0', $port, 2048);

try {
    $server->run();
} catch (Exception $e) {
    $server->stdout($e->getMessage());
    throw $e;
}
