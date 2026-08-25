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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_kuet\question;

use core_question\local\bank\question_bank_helper;
use core_question\local\bank\question_version_status;
use mod_kuet\models\questions;

/**
 * Shared bank discovery and source permissions, common to UI and web services.
 * @package mod_kuet
 * @copyright 2026 KUET contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bank_provider {
    /**
     * Capabilities allowing selection, rather than administration, of a bank.
     */
    public const CAPS = ['moodle/question:useall', 'moodle/question:usemine'];

    /**
     * Return authorised shared banks, with this course first.
     */
    public static function banks(int $courseid): array {
        $local = question_bank_helper::get_activity_instances_with_shareable_questions(
            incourseids: [$courseid],
            havingcap: self::CAPS
        );
        $other = question_bank_helper::get_activity_instances_with_shareable_questions(
            notincourseids: [$courseid],
            havingcap: self::CAPS
        );
        return array_map(static function ($bank) {
            return method_exists($bank, 'get_formatted') ? $bank->get_formatted() : $bank;
        }, array_merge($local, $other));
    }

    /**
     * Validate an explicitly selected bank without exposing private activity banks.
     */
    public static function require_bank(int $cmid): \cm_info {
        global $DB;
        $cm = \cm_info::create(get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST));
        if (!in_array($cm->modname, question_bank_helper::get_activity_types_with_shareable_questions(), true)) {
            throw new \moodle_exception('banknotavailable', 'mod_kuet');
        }
        if (
            $cm->modname === 'qbank' && $DB->get_field('qbank', 'type', ['id' => $cm->instance]) ===
                question_bank_helper::TYPE_PREVIEW
        ) {
            throw new \moodle_exception('banknotavailable', 'mod_kuet');
        }
        if ($cm->deletioninprogress || !has_any_capability(self::CAPS, $cm->context)) {
            throw new \moodle_exception('banknotavailable', 'mod_kuet');
        }
        return $cm;
    }

    /**
     * Metadata also supplies the origin and author required by question permissions.
     */
    public static function question(int $questionid): \stdClass {
        global $DB;
        return $DB->get_record_sql('SELECT q.*, qv.version, qv.status, qv.id AS versionid,
                       qv.questionbankentryid, qc.id AS categoryid, qc.contextid, qbe.idnumber
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE q.id = :id', ['id' => $questionid], MUST_EXIST);
    }

    /**
     * Reject forged IDs, drafts, incompatible types and insufficient source permissions.
     */
    public static function require_question(int $questionid): \stdClass {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $question = self::question($questionid);
        $context = \context::instance_by_id($question->contextid);
        if ($context->contextlevel !== CONTEXT_MODULE) {
            throw new \moodle_exception('banknotavailable', 'mod_kuet');
        }
        self::require_bank($context->instanceid);
        question_require_capability_on($question, 'use');
        if (
            $question->parent || $question->status !== question_version_status::QUESTION_STATUS_READY ||
                !in_array($question->qtype, questions::TYPES, true) ||
                !\question_bank::is_qtype_usable($question->qtype)
        ) {
            throw new \moodle_exception('questionnotusable', 'mod_kuet');
        }
        return $question;
    }
}
