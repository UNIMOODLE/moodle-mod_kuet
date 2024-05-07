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
 * Group mode routines
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\api;
use cache;
use cache_application;
use coding_exception;
use core\invalid_persistent_exception;
use dml_exception;
use invalid_parameter_exception;
use mod_kuet\kuet;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;
use stdClass;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once("$CFG->dirroot/group/lib.php");

/**
 * Group mode class
 */
class groupmode {

    /**
     * Get group image
     *
     * @param stdClass $groupdata
     * @param int $sid
     * @param int $imagesize
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_group_image(stdClass $groupdata, int $sid, int $imagesize = 1): string {

        $grouppicture = get_group_picture_url($groupdata, $groupdata->courseid);
        if (!is_null($grouppicture)) {
            $grouppicture->size = $imagesize;
            $image = $grouppicture->out(false);
        } else {
            // Get from cache.
            $cache = cache::make('mod_kuet', 'groupimages');
            $cachekey = 'groupid_' . $groupdata->id . '_sid_' . $sid;
            $cachedata = $cache->get($cachekey);
            if (!$cachedata) {
                $image = self::set_group_image_for_session($sid, $cache);
                if (!empty($image)) {
                    $cache->set($cachekey, $image);
                }
            } else {
                $image = $cachedata;
            }
        }
        return $image;
    }

    /**
     * Set group image for the session
     *
     * @param int $sid
     * @param cache_application $cache
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function set_group_image_for_session(int $sid, cache_application $cache): string {

        $session = new kuet_sessions($sid);
        $groupingid = $session->get('groupings');
        $groups = self::get_grouping_groups($groupingid);
        $images = [];
        foreach ($groups as $group) {
            $cachekey = 'groupid_' . $group->id . '_sid_' . $sid;
            $cachedata = $cache->get($cachekey);
            $findme   = 'pattern_';
            $pos = strpos($cachedata, $findme);
            if ($pos === false) {
                continue;
            }
            $images[] = $cachedata;
        }
        if (count($images) >= 7) {
            $name = '';
        } else {
            for ($i = 1; $i < 8; $i++) {
                $name   = '/mod/kuet/pix/pattern_0' . $i . '.png';
                if (!in_array($name, $images)) {
                    break;
                }
            }
        }

        return $name;
    }

    /**
     * Get one member of each groups grouping
     *
     * @param int $grouping
     * @return array
     * @throws dml_exception
     */
    public static function get_one_member_of_each_grouping_group(int $grouping): array {
        $gmembers = [];
        $groups = self::get_grouping_groups($grouping);
        foreach ($groups as $group) {
            $members = self::get_group_members($group->id);
            if (!empty($members)) {
                $gmembers[] = reset($members)->id;
            }
        }
        return $gmembers;
    }

    /**
     * Get group members
     *
     * @param int $groupid
     * @return array
     */
    public static function get_group_members(int $groupid): array {
        $members = groups_get_members($groupid, 'u.id');
        if (!$members) {
            return [];
        }
        return $members;
    }

    /**
     * Get the name of each groupings group
     *
     * @param int $groupingid
     * @return array
     * @throws dml_exception
     */
    public static function get_grouping_groups_name(int $groupingid): array {
        $groups = groups_get_grouping_members($groupingid, 'u.id,gg.groupid');
        $names = [];
        $groupids = [];
        foreach ($groups as $group) {
            if (!in_array($group->groupid, $groupids)) {
                $g = groups_get_group($group->groupid, 'name');
                $names[] = $g->name;
                $groupids[] = $group->groupid;
            }
        }
        return $names;
    }

    /**
     * Get the groupings groups
     *
     * @param int $groupingid
     * @return array
     * @throws dml_exception
     */
    public static function get_grouping_groups(int $groupingid): array {
        $groups = groups_get_grouping_members($groupingid, 'u.id,gg.groupid');
        $data = [];
        if (!$groups) {
            return $data;
        }
        $groupids = [];
        foreach ($groups as $group) {
            if (!in_array($group->groupid, $groupids)) {
                $g = groups_get_group($group->groupid, 'id, name, courseid, picture');
                $data[] = $g;
                $groupids[] = $group->groupid;
            }
        }
        return $data;
    }

    /**
     * Get the user ids from the grouping
     *
     * @param int $groupingid
     * @return array
     */
    public static function get_grouping_userids(int $groupingid): array {
        $groupmembers = groups_get_grouping_members($groupingid, 'u.id');
        return array_map(static function($user) {
            return $user->id;
        }, $groupmembers);
    }

    /**
     * Get the users from the grouping
     *
     * @param int $groupingid
     * @return array
     */
    public static function get_grouping_users(int $groupingid): array {
        return groups_get_grouping_members($groupingid, 'u.id');
    }

    /**
     * Get the members of a group from a grouping by user id
     *
     * @param int $groupingid
     * @param int $userid
     * @return array
     */
    public static function get_grouping_group_members_by_userid(int $groupingid, int $userid): array {
        $allmembers = groups_get_grouping_members($groupingid, 'u.id, gg.groupid');
        $groupid = 0;
        foreach ($allmembers as $gmember) {
            if ((int)$gmember->id === $userid) {
                $groupid = $gmember->groupid;
                break;
            }
        }
        $userids = [];
        foreach ($allmembers as $gmember) {
            if ((int)$gmember->groupid === (int)$groupid) {
                $userids[] = $gmember->id;
            }
        }
        return $userids;
    }

    /**
     * Check if all users are in a group
     *
     * @param int $cmid
     * @param int $groupingid
     * @return void
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function check_all_users_in_groups(int $cmid, int $groupingid): void {

        $students = kuet::get_enrolled_students_in_course(0, $cmid);
        $studentsids = array_keys($students);
        $groupmembers = self::get_grouping_userids($groupingid);
        $diff = array_diff($studentsids, $groupmembers);
        if (!empty($diff)) {
            $data = new stdClass();
            $data->name = get_string('fakegroup', 'mod_kuet', random_string(5));
            $data->description = get_string('fakegroupdescription', 'mod_kuet');
            $kuet = \mod_kuet\persistents\kuet::get_kuet_from_cmid($cmid);
            $data->courseid = $kuet->get('course');
            $groupid = groups_create_group($data);
            foreach ($diff as $userid) {
                groups_add_member($groupid, (int) $userid);
            }
            groups_assign_grouping($groupingid, $groupid);
        }
    }

    /**
     * Get the group of a user in a session
     *
     * @param int $userid
     * @param kuet_sessions $sessions
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     */
    public static function get_user_group(int $userid, kuet_sessions $sessions): stdClass {
        $groups = self::get_grouping_groups($sessions->get('groupings'));
        if (empty($groups)) {
            sessions::set_session_status_error($sessions, 'groupingremoved');
        }
        $groupselected = new stdClass();
        foreach ($groups as $group) {
            if (groups_is_member($group->id, $userid)) {
                $groupselected = $group;
                break;
            }
        }
        if (!isset($groupselected->id)) {
            sessions::set_session_status_error($sessions, 'groupremoved');
        }
        return $groupselected;
    }

    /**
     * Get the members of a group from a grouping
     *
     * @param int $groupingid
     * @return array
     * @throws dml_exception
     */
    public static function get_grouping_group_members(int $groupingid): array {
        $members = [];
        $groups = self::get_grouping_groups($groupingid);
        foreach ($groups as $group) {
            $groupmembers = groups_get_members($group->id, 'u.id,u.username');
            foreach ($groupmembers as $groupmember) {
                $members[] = $groupmember;
            }
        }
        return $members;
    }
}
