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
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
declare(strict_types=1);
namespace mod_kuet\completion;
use coding_exception;
use core_completion\activity_custom_completion;
use dml_exception;
use mod_kuet\persistents\kuet_questions_responses;
use mod_kuet\persistents\kuet_sessions;
use moodle_exception;

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
        $userid = $this->userid;

        if (!$DB->get_record('kuet', ['id' => $jqshowid])) {
            throw new moodle_exception('kuetnotexist', 'mod_kuet', '',
                [], get_string('kuetnotexist', 'mod_kuet', $jqshowid));
        }

        $numsessions = kuet_sessions::count_records(['jqshowid' => $jqshowid]);
        if ($numsessions === 0) {
            return COMPLETION_INCOMPLETE;
        }
        $hasparticipate = false;
        if (kuet_questions_responses::count_records(['kuet' => $jqshowid, 'userid' => $userid]) > 0) {
            $hasparticipate = true;
        }
        return $hasparticipate ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
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
            'completionanswerall' => get_string('completiondetail:answerall', 'kuet', $completionanswerall),
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
            'completionpassgrade',
        ];
    }
}
