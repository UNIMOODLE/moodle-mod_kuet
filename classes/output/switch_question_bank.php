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
 * Question bank chooser of a session
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\output;

use cm_info;
use coding_exception;
use core_question\local\bank\question_bank_helper;
use moodle_exception;
use renderable;
use renderer_base;
use templatable;

/**
 * Lists the question banks a session can take its questions from
 *
 * The same lists core offers in \core_question\output\switch_question_bank - the
 * shared banks of the course, the recently used ones and the search over every
 * shared bank of the site - without the block that heads that one: core opens with
 * a link to the question bank of the activity itself, which only makes sense for an
 * activity that has one. A kuet does not, and that link sent its own course module
 * id back to the server as if it were a qbank, where
 * get_coursemodule_from_id('qbank', ...) ended in a dml_missing_record_exception
 * (KUET-042). It also said "quiz" to the teacher, in a plugin that is not one.
 *
 * Everything the panel needs afterwards is what core's template exposes, so the
 * markup of the other three blocks is kept as core has it: the links carry
 * data-newmodid and the search box is the select with id "searchbanks", which is
 * what questionspanel.js binds to.
 */
class switch_question_bank implements renderable, templatable {
    /**
     * Constructor
     *
     * @param int $kuetcmid Course module id of the kuet the bank is chosen for.
     * @param int $courseid
     * @param int $userid
     */
    public function __construct(
        /** @var int Course module id of the kuet */
        private readonly int $kuetcmid,
        /** @var int Course the kuet belongs to */
        private readonly int $courseid,
        /** @var int User the banks are listed for */
        private readonly int $userid
    ) {
    }

    /**
     * Data for the template
     *
     * @param renderer_base $output
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): array {
        [, $cm] = get_module_from_cmid($this->kuetcmid);
        $cminfo = cm_info::create($cm);

        // The two capabilities core asks for in its own chooser: either of them is
        // enough to take questions from a bank.
        $capabilities = ['moodle/question:useall', 'moodle/question:usemine'];
        $coursesharedbanks = question_bank_helper::get_activity_instances_with_shareable_questions(
            incourseids: [$this->courseid],
            havingcap: $capabilities,
            filtercontext: $cminfo->context,
        );
        $recentlyviewedbanks = question_bank_helper::get_recently_used_open_banks($this->userid, havingcap: $capabilities);

        return [
            // The context of the kuet, which is what the search field sends back so the
            // autocomplete of core can look for banks the user may use from here.
            'kuetcontextid' => $cminfo->context->id,
            'hascoursesharedbanks' => !empty($coursesharedbanks),
            'coursesharedbanks' => self::format_banks($coursesharedbanks),
            'hasrecentlyviewedbanks' => !empty($recentlyviewedbanks),
            'recentlyviewedbanks' => self::format_banks($recentlyviewedbanks),
        ];
    }

    /**
     * The banks as the template reads them, whichever shape the helper returned
     *
     * Moodle 5.2 changed question_bank_helper to hand back
     * \core_question\localankormatted_bank objects, which hold on to the cm_info
     * and only build the name, the modid and the coursenamebankname when asked with
     * get_formatted(); up to 5.1 the helper already returned those as a stdClass.
     * Handing a formatted_bank straight to mustache renders the list with an empty
     * entry - no name and data-newmodid="" - so no bank can be chosen (KUET-047).
     *
     * Asked of the object and not of the Moodle version, so the same code serves 5.0,
     * 5.1 and 5.2. It is what core does for itself in formatted_bank::format_banks().
     *
     * @param array $banks What question_bank_helper returned.
     * @return array
     */
    private static function format_banks(array $banks): array {
        return array_map(
            static fn($bank) => method_exists($bank, 'get_formatted') ? $bank->get_formatted() : $bank,
            $banks
        );
    }
}
