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
 * Grade manually updated event (KUETEDUCAM-73)
 *
 * @package    mod_kuet
 * @author     3IPUNT <contacte@tresipunt.com>
 * @copyright  3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\event;

use coding_exception;
use core\event\base;
use moodle_url;

/**
 * Event triggered when a teacher manually edits a question grade.
 */
class grade_manually_updated extends base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'kuet_questions_responses';
    }

    /**
     * Return localised event name.
     *
     * @return string
     * @throws coding_exception
     */
    public static function get_name(): string {
        return get_string('event_grade_manually_updated', 'mod_kuet');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' manually updated the grade of the response with id " .
            "'{$this->objectid}' for the user with id '{$this->relateduserid}' in the kuet question " .
            "with id '{$this->other['kid']}' (session '{$this->other['sessionid']}').";
    }

    /**
     * Get URL related to the action.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url('/mod/kuet/editgrades.php', [
            'cmid' => $this->contextinstanceid,
            'sid' => $this->other['sessionid'],
            'userid' => $this->relateduserid,
        ]);
    }

    /**
     * Validate the custom data.
     *
     * @return void
     * @throws coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['kid'])) {
            throw new coding_exception('The \'kid\' value must be set in other.');
        }
        if (!isset($this->other['sessionid'])) {
            throw new coding_exception('The \'sessionid\' value must be set in other.');
        }
        if (!isset($this->relateduserid)) {
            throw new coding_exception('The \'relateduserid\' must be set.');
        }
    }

    /**
     * Used for mapping events on restore.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'kuet_questions_responses', 'restore' => base::NOT_MAPPED];
    }
}
