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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * Common data exporter
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_kuet\exporter;
use context;

/**
 *   Exporter to take a stdClass and prepare it for return by webservice, or as the context for a template.
 */
class commondata_exporter extends question_exporter {

    /**
     * Properties definition
     *
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
            'kuetid' => [
                'type' => PARAM_INT,
            ],
            'questionid' => [
                'type' => PARAM_INT,
            ],
            'kid' => [
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
     * Related context definition
     *
     * @return string[]
     */
    protected static function define_related() : array {
        return array('context' => context::class);
    }
}
