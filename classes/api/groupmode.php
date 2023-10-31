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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos

/**
 *
 * @package    mod_jqshow
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jqshow\api;
use cache;
use cache_application;
use coding_exception;
use dml_exception;
use mod_jqshow\jqshow;
use mod_jqshow\persistents\jqshow_sessions;
use moodle_exception;
use stdClass;
global $CFG;
require_once("$CFG->dirroot/group/lib.php");
class groupmode {

    /**
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
            $cache = cache::make('mod_jqshow', 'groupimages');
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
     * @param int $sid
     * @param cache_application $cache
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function set_group_image_for_session(int $sid, cache_application $cache) : string {

        $session = new jqshow_sessions($sid);
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
            for($i = 1; $i<8; $i++) {
                $name   = '/mod/jqshow/pix/pattern_0' . $i . '.png';
                if (!in_array($name, $images)) {
                    break;
                }
            }
        }

        return $name;
    }
    /**
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
     * @param int $groupid
     * @return array
     */
    public static function get_group_members(int $groupid) : array {
        $members = groups_get_members($groupid, 'u.id');
        if (!$members) {
            return [];
        }
        return $members;
    }

    /**
     * @param int $groupingid
     * @return array
     * @throws dml_exception
     */
    public static function get_grouping_groups_name(int $groupingid) : array {
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
     * @param int $groupingid
     * @return array
     * @throws dml_exception
     */
    public static function get_grouping_groups(int $groupingid) : array {
        $groups = groups_get_grouping_members($groupingid, 'u.id,gg.groupid');
        $data = [];
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
     * @param int $groupingid
     * @return array
     */
    public static function get_grouping_userids(int $groupingid) : array {
        $groupmembers = groups_get_grouping_members($groupingid, 'u.id');
        return array_map(static function($user) {
            return $user->id;
        }, $groupmembers);
    }
    /**
     * @param int $groupingid
     * @return array
     */
    public static function get_grouping_users(int $groupingid) : array {
        return groups_get_grouping_members($groupingid, 'u.id');
    }

    /**
     * @param int $groupingid
     * @param int $userid
     * @return array
     */
    public static function get_grouping_group_members_by_userid(int $groupingid, int $userid) : array {
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
     * @param int $cmid
     * @param int $groupingid
     * @return void
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function check_all_users_in_groups(int $cmid, int $groupingid) : void {
        global $COURSE;
        $students = jqshow::get_enrolled_students_in_course(0, $cmid);
        $studentsids = array_keys($students);
        $groupmembers = self::get_grouping_userids($groupingid);
        $diff = array_diff($studentsids, $groupmembers);
        if (!empty($diff)) {
            $data = new stdClass();
            $data->name = get_string('fakegroup', 'mod_jqshow', random_string(5));
            $data->description = get_string('fakegroupdescription', 'mod_jqshow');
            $data->courseid = $COURSE->id;
            $groupid = groups_create_group($data);
            foreach ($diff as $userid) {
                groups_add_member($groupid, (int) $userid);
            }
            groups_assign_grouping($groupingid, $groupid);
        }
    }

    /**
     * @param int $userid
     * @param int $groupingid
     * @return mixed|null
     * @throws dml_exception
     */
    public static function get_user_group(int $userid, int $groupingid) : stdClass {
        $groups = self::get_grouping_groups($groupingid);
        $groupselected = null;
        foreach ($groups as $group) {
            if (groups_is_member($group->id, $userid)) {
                $groupselected = $group;
                break;
            }
        }
        return $groupselected;
    }
}
