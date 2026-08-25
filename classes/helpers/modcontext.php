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
 * Module context helper
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\helpers;

use coding_exception;
use context_module;
use dml_exception;
use moodle_exception;

/**
 * Resolves the module context an entity belongs to
 *
 * Web service functions must authorise against the context of the kuet the
 * entity actually lives in. Several of them only receive an entity id, so the
 * context has to be derived from it — and when a function does receive a cmid
 * alongside an entity id, the two have to be checked against each other, or
 * the capability is verified on a module the entity does not belong to and the
 * check is worthless.
 */
class modcontext {
    /**
     * Context of a kuet instance
     *
     * @param int $kuetid
     * @return context_module
     * @throws moodle_exception
     */
    public static function from_kuet(int $kuetid): context_module {
        $cm = get_coursemodule_from_instance('kuet', $kuetid, 0, false, MUST_EXIST);
        return context_module::instance($cm->id);
    }

    /**
     * Context of the kuet a session belongs to
     *
     * @param int $sessionid
     * @return context_module
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function from_session(int $sessionid): context_module {
        global $DB;
        $kuetid = $DB->get_field('kuet_sessions', 'kuetid', ['id' => $sessionid], MUST_EXIST);
        return self::from_kuet((int) $kuetid);
    }

    /**
     * Context of the kuet a session question belongs to
     *
     * @param int $kid Id in kuet_questions.
     * @return context_module
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function from_question(int $kid): context_module {
        global $DB;
        $kuetid = $DB->get_field('kuet_questions', 'kuetid', ['id' => $kid], MUST_EXIST);
        return self::from_kuet((int) $kuetid);
    }

    /**
     * Context of a course module, checking it really is a kuet
     *
     * @param int $cmid
     * @return context_module
     * @throws moodle_exception
     */
    public static function from_cmid(int $cmid): context_module {
        $cm = get_coursemodule_from_id('kuet', $cmid, 0, false, MUST_EXIST);
        return context_module::instance($cm->id);
    }

    /**
     * Check that a session belongs to the kuet of the given course module
     *
     * Without this, passing a valid cmid of a kuet you can access together
     * with the session id of one you cannot is enough to defeat the capability
     * check.
     *
     * @param int $sessionid
     * @param int $cmid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function require_session_in_cm(int $sessionid, int $cmid): void {
        global $DB;
        $cm = get_coursemodule_from_id('kuet', $cmid, 0, false, MUST_EXIST);
        $kuetid = $DB->get_field('kuet_sessions', 'kuetid', ['id' => $sessionid], MUST_EXIST);
        if ((int) $kuetid !== (int) $cm->instance) {
            throw new moodle_exception('invalidcoursemodule', 'error');
        }
    }

    /**
     * Check that a session belongs to the given kuet instance
     *
     * @param int $sessionid
     * @param int $kuetid
     * @return void
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function require_session_in_kuet(int $sessionid, int $kuetid): void {
        global $DB;
        $found = $DB->get_field('kuet_sessions', 'kuetid', ['id' => $sessionid], MUST_EXIST);
        if ((int) $found !== $kuetid) {
            throw new moodle_exception('invalidrecord', 'error');
        }
    }

    /**
     * Check that a kuet instance is the one of the given course module
     *
     * @param int $kuetid
     * @param int $cmid
     * @return void
     * @throws moodle_exception
     */
    public static function require_kuet_in_cm(int $kuetid, int $cmid): void {
        $cm = get_coursemodule_from_id('kuet', $cmid, 0, false, MUST_EXIST);
        if ($kuetid !== (int) $cm->instance) {
            throw new moodle_exception('invalidcoursemodule', 'error');
        }
    }

    /**
     * Check that a session question belongs to the given session
     *
     * @param int $kid Id in kuet_questions.
     * @param int $sessionid
     * @return void
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function require_question_in_session(int $kid, int $sessionid): void {
        global $DB;
        $found = $DB->get_field('kuet_questions', 'sessionid', ['id' => $kid], MUST_EXIST);
        if ((int) $found !== $sessionid) {
            throw new moodle_exception('invalidrecord', 'error');
        }
    }

    /**
     * Check that a session question belongs to the kuet of the given course module
     *
     * @param int $kid Id in kuet_questions.
     * @param int $cmid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function require_question_in_cm(int $kid, int $cmid): void {
        global $DB;
        $cm = get_coursemodule_from_id('kuet', $cmid, 0, false, MUST_EXIST);
        $kuetid = $DB->get_field('kuet_questions', 'kuetid', ['id' => $kid], MUST_EXIST);
        if ((int) $kuetid !== (int) $cm->instance) {
            throw new moodle_exception('invalidcoursemodule', 'error');
        }
    }
}
