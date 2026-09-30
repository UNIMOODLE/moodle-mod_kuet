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
 * Question bank references helper
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\helpers;

use dml_exception;
use moodle_exception;

/**
 * Keeps the question_references table of core in step with the questions of a session
 *
 * Until Moodle 4.0 an activity pointed at a question by its id, and that is what
 * kuet_questions.questionid still is. Core replaced it with question_references, a
 * row per usage that names the question bank entry instead of the question, and
 * from then on that row is also what tells a backup which questions it has to
 * carry: backup_question_dbops::calculate_question_categories(), the step that
 * decides it, only looks at the question_bank_entry annotations a reference
 * produces. An activity without references only gets the questions whose bank
 * happens to live inside what is being copied, so in Moodle 5 - where banks are
 * activities of their own, shared between courses - the copy of a kuet on its own
 * arrived without a single question (KUET-041).
 *
 * The reference is kept as a mirror of questionid, pinned to the version that
 * questionid is, and questionid stays the field the plugin reads. That is enough
 * for the copy to carry the questions and for the restore to resolve them, and it
 * leaves what a session shows untouched.
 */
class question_references {
    /**
     * @var string Component the references of this plugin are recorded under
     */
    public const COMPONENT = 'mod_kuet';

    /**
     * @var string Area within the component: a question added to a session
     */
    public const QUESTIONAREA = 'question';

    /**
     * Record the reference of a session question, or bring the one it has up to date
     *
     * @param int $kid Id in kuet_questions.
     * @param int $kuetid
     * @param int $questionid
     * @return void
     * @throws dml_exception
     */
    public static function set(int $kid, int $kuetid, int $questionid): void {
        global $DB;

        // The version row carries the bank entry, so a question that is not in a
        // bank - a preview of one being edited, say - cannot be referenced.
        $version = $DB->get_record(
            'question_versions',
            ['questionid' => $questionid],
            'questionbankentryid, version'
        );
        if (!$version) {
            return;
        }

        try {
            $context = modcontext::from_kuet($kuetid);
        } catch (moodle_exception $e) {
            // Without a course module there is no context to own the reference.
            return;
        }

        $data = (object) [
            'usingcontextid' => $context->id,
            'component' => self::COMPONENT,
            'questionarea' => self::QUESTIONAREA,
            'itemid' => $kid,
            'questionbankentryid' => $version->questionbankentryid,
            // The session keeps the version it was built with, which is the one
            // questionid names. "Always latest", what mod_quiz stores, would change
            // a session that has already been played if the question is edited.
            'version' => $version->version,
        ];

        $reference = $DB->get_record('question_references', [
            'component' => self::COMPONENT,
            'questionarea' => self::QUESTIONAREA,
            'itemid' => $kid,
        ]);
        if ($reference) {
            $data->id = $reference->id;
            $DB->update_record('question_references', $data);
            return;
        }

        $DB->insert_record('question_references', $data);
    }

    /**
     * Drop the reference of a session question
     *
     * @param int $kid Id in kuet_questions.
     * @return void
     * @throws dml_exception
     */
    public static function remove(int $kid): void {
        global $DB;

        $DB->delete_records('question_references', [
            'component' => self::COMPONENT,
            'questionarea' => self::QUESTIONAREA,
            'itemid' => $kid,
        ]);
    }

    /**
     * Drop the references of every question of a session
     *
     * For the paths that delete the rows of kuet_questions in one statement, where
     * the persistent of each question is never built and its hooks do not run.
     *
     * @param int $sessionid
     * @return void
     * @throws dml_exception
     */
    public static function remove_for_session(int $sessionid): void {
        global $DB;

        self::remove_many($DB->get_fieldset('kuet_questions', 'id', ['sessionid' => $sessionid]));
    }

    /**
     * Drop the references of every question of a kuet
     *
     * @param int $kuetid
     * @return void
     * @throws dml_exception
     */
    public static function remove_for_kuet(int $kuetid): void {
        global $DB;

        self::remove_many($DB->get_fieldset('kuet_questions', 'id', ['kuetid' => $kuetid]));
    }

    /**
     * Drop the references of a set of session questions
     *
     * @param array $kids Ids in kuet_questions.
     * @return void
     * @throws dml_exception
     */
    private static function remove_many(array $kids): void {
        global $DB;

        if (empty($kids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($kids, SQL_PARAMS_NAMED, 'kid');
        $params['component'] = self::COMPONENT;
        $params['questionarea'] = self::QUESTIONAREA;
        $DB->delete_records_select(
            'question_references',
            "component = :component AND questionarea = :questionarea AND itemid {$insql}",
            $params
        );
    }
}
