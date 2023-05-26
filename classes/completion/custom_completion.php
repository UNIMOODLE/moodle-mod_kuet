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

namespace mod_jqshow\completion;

use coding_exception;
use core_completion\activity_custom_completion;
use dml_exception;
use moodle_exception;

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);
        $jqshowid = $this->cm->instance;

        if (!$DB->get_record('jqshow', ['id' => $jqshowid])) {
            throw new moodle_exception('Unable to find jqshow with id ' . $jqshowid);
        }
        // TODO: get status.
        return COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completionanswerall',
        ];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     * @throws coding_exception
     */
    public function get_custom_rule_descriptions(): array {
        $completionanswerall = $this->cm->customdata['customcompletionrules']['completionanswerall'] ?? 0;
        return [
            'completionanswerall' => get_string('completiondetail:answerall', 'jqshow', $completionanswerall),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionanswerall',
            'completionusegrade',
        ];
    }
}
