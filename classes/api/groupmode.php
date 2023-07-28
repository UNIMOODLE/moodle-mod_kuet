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
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_jqshow\api;


use mod_jqshow\jqshow;

class groupmode {

    public const TEAM_GRADE_FIRST = 'first';
//    public const TEAM_GRADE_LAST = 'last';
//    public const TEAM_GRADE_AVERAGE = 'average';

    /**
     * @param int $grouping
     * @return array
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
     */
    public static function get_grouping_groups_name(int $groupingid) : array {
        $groups = groups_get_grouping_members($groupingid, 'gg.groupid');
        $names = [];
        foreach ($groups as $group) {
            $g = groups_get_group($group->groupid, 'name');
            $names[] = $g->name;
        }
        return $names;
    }
    /**
     * @param int $groupingid
     * @return array
     */
    public static function get_grouping_groups(int $groupingid) : array {
        $groups = groups_get_grouping_members($groupingid, 'gg.groupid');
        $data = [];
        foreach ($groups as $group) {
            $g = groups_get_group($group->groupid, 'id, name, courseid, picture');
            $data[] = $g;
        }
        return $data;
    }

    /**
     * @param int $groupingid
     * @return array
     */
    public static function get_grouping_userids(int $groupingid) : array {
        $groupmembers = groups_get_grouping_members($groupingid, 'u.id');
        return array_map(function($user) {
            return $user->id;
        }, $groupmembers);
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
            if ($gmember->id == $userid) {
                $groupid = $gmember->groupid;
                break;
            }
        }
        $userids = [];
        foreach ($allmembers as $gmember) {
            if ($gmember->groupid == $groupid) {
                $userids[] = $gmember->id;
            }
        }
        return $userids;
    }
    /**
     * @param int $courseid
     * @param int $groupingid
     */
    public static function check_all_users_in_groups(int $cmid, int $groupingid) {
        global $COURSE;
        $students = jqshow::get_students($cmid);
        $newstudents = array_map(function($user) {
            return $user->id;
        }, $students);

//        $groupmembers = groups_get_grouping_members($groupingid, 'u.id');
//        $newgrupmembers = array_map(function($user) {
//            return $user->id;
//        }, $groupmembers);
        $newgrupmembers = self::get_grouping_userids($groupingid);
        $diff = array_diff($newstudents, $newgrupmembers);
        if (!empty($diff)) {
            $data = new \stdClass();
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
}
