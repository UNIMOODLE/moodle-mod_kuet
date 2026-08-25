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

use mod_kuet\models\questions;
use mod_kuet\models\sessions;
use mod_kuet\persistents\kuet_questions;

/**
 * Version policy and immutable execution snapshots for session questions.
 * @package mod_kuet
 * @copyright 2026 KUET contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class version_resolver {
    /**
     * Reference area shared by runtime, upgrade, backup and restore.
     */
    public const AREA = 'session_question';

    /**
     * Whether this session has already been used, including legacy data.
     */
    public static function locked(int $sid): bool {
        global $DB;
        $session = $DB->get_record('kuet_sessions', ['id' => $sid], '*', MUST_EXIST);
        return !empty($session->questionslocked) ||
            in_array((int)$session->status, [sessions::SESSION_STARTED, sessions::SESSION_FINISHED], true) ||
            $DB->record_exists('kuet_questions_responses', ['session' => $sid]) ||
            $DB->record_exists('kuet_user_progress', ['session' => $sid]);
    }

    /**
     * Require a session whose question structure is still editable.
     */
    public static function require_editable(int $sid): void {
        if (self::locked($sid)) {
            throw new \moodle_exception('sessionquestionslocked', 'mod_kuet');
        }
    }

    /**
     * Fetch the canonical reference for a position.
     */
    public static function reference(int $kid) {
        global $DB;
        return $DB->get_record('question_references', [
            'component' => 'mod_kuet', 'questionarea' => self::AREA, 'itemid' => $kid,
        ]);
    }

    /**
     * Create a fixed reference without changing legacy question IDs. Missing questions remain repairable.
     */
    public static function ensure_reference(\stdClass $slot): void {
        global $DB;
        if (self::reference($slot->id)) {
            return;
        }
        $version = $DB->get_record('question_versions', ['questionid' => $slot->questionid]);
        if (!$version) {
            return;
        }
        $cm = get_coursemodule_from_instance('kuet', $slot->kuetid, 0, false, MUST_EXIST);
        $DB->insert_record('question_references', (object)[
            'usingcontextid' => \context_module::instance($cm->id)->id,
            'component' => 'mod_kuet', 'questionarea' => self::AREA, 'itemid' => $slot->id,
            'questionbankentryid' => $version->questionbankentryid, 'version' => $version->version,
        ]);
    }

    /**
     * Resolve a reference; fixed hidden versions remain usable for existing activities.
     */
    public static function resolve(\stdClass $slot): \stdClass {
        global $DB;
        if (self::locked($slot->sessionid)) {
            return bank_provider::question($slot->questionid);
        }
        $reference = self::reference($slot->id);
        if (!$reference) {
            self::ensure_reference($slot);
            $reference = self::reference($slot->id);
            if (!$reference) {
                throw new \moodle_exception('questionnotusable', 'mod_kuet');
            }
        }
        $params = ['entry' => $reference->questionbankentryid];
        $where = 'questionbankentryid = :entry';
        if ($reference->version === null) {
            $where .= ' AND status = :status';
            $params['status'] = 'ready';
        } else {
            $where .= ' AND version = :version';
            $params['version'] = $reference->version;
        }
        $versions = $DB->get_records_select('question_versions', $where, $params, 'version DESC', '*', 0, 1);
        if (!$versions) {
            throw new \moodle_exception('questionnotusable', 'mod_kuet');
        }
        $question = bank_provider::question(reset($versions)->questionid);
        if ($question->status === 'draft' || !in_array($question->qtype, questions::TYPES, true)) {
            throw new \moodle_exception('questionnotusable', 'mod_kuet');
        }
        return $question;
    }

    /**
     * Refresh a draft position only when explicitly editing it, invalidating version-dependent configuration.
     */
    public static function refresh(kuet_questions $slot): void {
        $guard = new mutation($slot->get('kuetid'));
        try {
            self::require_editable($slot->get('sessionid'));
            $question = self::resolve($slot->to_record());
            bank_provider::require_question($question->id);
            if ((int)$question->id !== (int)$slot->get('questionid')) {
                $slot->set('questionid', $question->id);
                $slot->set('qtype', $question->qtype);
                $slot->set('config', '');
                $slot->set('isvalid', 0);
                $slot->update();
            }
            $guard->finish();
        } catch (\Throwable $e) {
            $guard->abort($e);
        }
    }

    /**
     * Set the requested policy after an authorised edit. Null means latest ready.
     */
    public static function set_policy(kuet_questions $slot, ?int $version): void {
        $guard = new mutation($slot->get('kuetid'));
        try {
            global $DB;
            self::require_editable($slot->get('sessionid'));
            bank_provider::require_question($slot->get('questionid'));
            self::ensure_reference($slot->to_record());
            $reference = self::reference($slot->get('id'));
            if (
                $version !== null &&
                !$DB->record_exists('question_versions', [
                    'questionbankentryid' => $reference->questionbankentryid,
                    'version' => $version,
                    'status' => 'ready',
                ])
            ) {
                throw new \moodle_exception('questionnotusable', 'mod_kuet');
            }
            $reference->version = $version;
            $DB->update_record('question_references', $reference);
            $question = self::resolve($slot->to_record());
            bank_provider::require_question($question->id);
            if ((int) $question->id !== (int) $slot->get('questionid')) {
                $slot->set('questionid', $question->id);
                $slot->set('qtype', $question->qtype);
                $slot->set('config', '');
                $slot->set('isvalid', 0);
                $slot->update();
            }
            $guard->finish();
        } catch (\Throwable $e) {
            $guard->abort($e);
        }
    }

    /**
     * Validate all positions before the session is locked. No permissions required for students or cron.
     */
    public static function prepare_start(int $sid): void {
        global $DB;
        if (self::locked($sid)) {
            return;
        }
        $slots = $DB->get_records('kuet_questions', ['sessionid' => $sid]);
        foreach ($slots as $slot) {
            $question = self::resolve($slot);
            if ((int)$question->id !== (int)$slot->questionid) {
                // A teacher must review the new version before it can be used.
                throw new \moodle_exception('questionversionchanged', 'mod_kuet');
            }
            if (!\question_bank::is_qtype_usable($question->qtype)) {
                throw new \moodle_exception('questionnotusable', 'mod_kuet');
            }
        }
    }

    /**
     * Remove only references owned by these KUET positions.
     */
    public static function delete_references(array $ids): void {
        global $DB;
        if (!$ids) {
            return;
        }
        [$sql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $params['component'] = 'mod_kuet';
        $params['area'] = self::AREA;
        $DB->delete_records_select(
            'question_references',
            "component = :component AND questionarea = :area AND itemid $sql",
            $params
        );
    }

    /**
     * Copy the policy to a newly created position, not the old reference ID.
     */
    public static function copy_reference(int $oldid, int $newid): void {
        global $DB;
        $old = self::reference($oldid);
        $new = self::reference($newid);
        if ($old && $new) {
            $new->questionbankentryid = $old->questionbankentryid;
            $new->version = $old->version;
            $DB->update_record('question_references', $new);
        }
    }
}
