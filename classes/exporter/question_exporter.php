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
 * Question data exporter
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\exporter;

use context;
use core\external\exporter;

/**
 *   Exporter to take a stdClass and prepare it for return by webservice, or as the context for a template.
 */
class question_exporter extends exporter {

    /**
     * Properties definition
     *
     * @return array
     */
    public static function define_properties(): array {
        return array_merge(
            commondata_exporter::define_properties(),
            multichoice_exporter::define_properties(),
            truefalse_exporter::define_properties(),
            matchquestion_exporter::define_properties(),
            shortanswer_exporter::define_properties(),
            numerical_exporter::define_properties(),
            calculated_exporter::define_properties(),
            description_exporter::define_properties(),
            ddwtos_exporter::define_properties(),
            endsession_exporter::define_properties()
        );
    }

    /**
     * Related context defnition
     *
     * @return string[]
     */
    protected static function define_related(): array {
        return ['context' => context::class];
    }
}
