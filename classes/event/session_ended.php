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
 * Kuet Session ended event
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet\event;
use coding_exception;
use context_module;
use moodle_exception;
use moodle_url;
use core\event\base;

/**
 * Session ended event class
 */
class session_ended extends base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'kuet_sessions';
    }

    /**
     * Return localised event name.
     *
     * @return string
     * @throws coding_exception
     */
    public static function get_name(): string {
        return get_string('sessionended', 'mod_kuet');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     * @throws coding_exception
     */
    public function get_description(): string {
        return get_string('sessionended_desc', 'mod_kuet');
    }

    /**
     * Get URL related to the action
     *
     * @return moodle_url
     * @throws moodle_exception
     */
    public function get_url(): moodle_url {
        $cmcontext = context_module::instance($this->contextinstanceid);
        return new moodle_url('mod/kuet/sessions.php', ['sid' => $this->objectid,
            'cmid' => $cmcontext->instanceid]);
    }

    /**
     * Used for maping events on restore
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'kuet_sessions', 'restore' => 'kuet_sessions'];
    }
}
