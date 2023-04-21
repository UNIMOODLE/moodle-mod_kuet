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

namespace mod_jqshow\external;

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

class multichoice_external extends external_api {

    public static function multichoice_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'answerid' => new external_value(PARAM_INT, 'answer id'),
                'sessionid' => new external_value(PARAM_INT, 'id of session'),
                'jqshowid' => new external_value(PARAM_INT, 'id of jqshow'),
                'cmid' => new external_value(PARAM_INT, 'id of cm'),
                'preview' => new external_value(PARAM_BOOL, 'preview or not for grade'),
            ]
        );
    }

    /**
     * @param int $answerid
     * @param int $sessionid
     * @param int $jqshowid
     * @param int $cmid
     * @param bool $preview
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function multichoice(int $answerid, int $sessionid, int $jqshowid, int $cmid, bool $preview): array {
        global $USER;
        self::validate_parameters(
            self::multichoice_parameters(),
            ['answerid' => $answerid, 'sessionid' => $sessionid, 'jqshowid' => $jqshowid, 'cmid' => $cmid, 'preview' => $preview]
        );
        return [
            'reply_status' => true,
            'statment_feedback' => 'Integer quis elit commodo, mollis lacus eget, aliquet est. Integer posuere, est in cursus pulvinar, nulla lorem aliquet odio, ut accumsan nulla justo sed nisi. Phasellus pellentesque, ante a congue consectetur, magna lorem sagittis risus, sed volutpat velit nisl vel justo. <img src="https://i.insider.com/5f6ce9a1c4049200115cb797?width=1136&format=jpeg">',
            'answer_feedback' => 'Suspendisse eu neque et felis imperdiet porttitor. Mauris luctus malesuada est quis consectetur. Nam sed aliquam eros. Nunc eu lacinia mauris. Vestibulum pulvinar est vitae dui sodales laoreet in nec arcu. Mauris efficitur, nisl porttitor commodo rhoncus, leo ante egestas ipsum, eget venenatis odio sem non neque. Vestibulum finibus molestie risus, vitae rhoncus nunc porttitor ut. Donec lacinia justo ac nulla venenatis cursus. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Ut vestibulum ante a diam placerat tincidunt. Proin pellentesque nec odio quis molestie. Suspendisse potenti. Sed sagittis, erat egestas malesuada convallis, magna ex tincidunt nisi, sit amet facilisis diam dolor quis mi. Fusce scelerisque ligula in gravida malesuada. <img src="https://www.hyundaimotorgroup.com/image/upload/asset_library/MDA00000000000005779/259cfef1c1cb43d4897296e7b747993c.jpg">',
        ];
    }

    public static function multichoice_returns(): external_single_structure {
        return new external_single_structure(
            [
                'reply_status' => new external_value(PARAM_BOOL, 'Status of reply'),
                'statment_feedback' => new external_value(PARAM_RAW, 'HTML statment feedback', VALUE_OPTIONAL),
                'answer_feedback' => new external_value(PARAM_RAW, 'HTML answer feedback', VALUE_OPTIONAL),
            ]
        );
    }

}
