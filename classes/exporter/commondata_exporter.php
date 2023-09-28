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

namespace mod_jqshow\exporter;

use context;

class commondata_exporter extends question_exporter {

    // TODO split in exports according to the type of question.

    /**
     * @return array[]
     */
    public static function define_properties(): array {
        return [
            'cmid' => [
                'type' => PARAM_INT,
            ],
            'sessionid' => [
                'type' => PARAM_TEXT,
            ],
            'jqshowid' => [
                'type' => PARAM_INT,
            ],
            'questionid' => [
                'type' => PARAM_INT,
            ],
            'jqid' => [
                'type' => PARAM_INT,
            ],
            'questiontext' => [
                'type' => PARAM_RAW,
                'optional' => true
            ],
            'showquestionfeedback' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'countdown' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'preview' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'programmedmode' => [
                'type' => PARAM_BOOL
            ],
            'question_index_string' => [
                'type' => PARAM_RAW,
                'optional' => true
            ],
            'numquestions' => [
                'type' => PARAM_INT,
                'optional' => true
            ],
            'sessionprogress' => [
                'type' => PARAM_INT,
                'optional' => true
            ],
            'hastime' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'seconds' => [
                'type' => PARAM_INT,
                'optional' => true
            ],
            'qtype' => [
                'type' => PARAM_RAW,
                'optional' => true
            ],
            'port' => [
                'type' => PARAM_RAW,
                'optional' => true
            ],
            'socketurl' => [
                'type' => PARAM_URL,
                'optional' => true
            ],
            'showstatistics' => [
                'type' => PARAM_BOOL,
                'optional' => true
            ],
            'feedbacks' => [
                'type' => feedback_exporter::read_properties_definition(),
                'optional' => true,
                'multiple' => true
            ],
            'ranking' => [
                'type' => PARAM_BOOL,
                'optional' => true,
            ],
            'isteacher' => [
                'type' => PARAM_BOOL,
                'optional' => true,
            ],
            'answered' => [
                'type' => PARAM_BOOL,
                'optional' => true,
            ],
            'hasfeedbacks' => [
                'type' => PARAM_BOOL,
                'optional' => true,
            ],
            'statment_feedback' => [
                'type' => PARAM_RAW,
                'optional' => true,
            ],
            'jsonresponse' => [
                'type' => PARAM_RAW,
                'optional' => true,
            ]
        ];
    }

    /**
     * @return string[]
     */
    protected static function define_related() {
        return array('context' => context::class);
    }
}
