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
 * Test WebSocket server report renderer.
 *
 * @package    mod_kuet
 * @copyright  2025 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     PLANIFICACIÓN DE ENTORNOS TECNOLÓGICOS, S.L. <admon@pentec.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\output\views;

use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Test WebSocket server report renderable class.
 */
class test_report implements renderable, templatable {
    /**
     * @var string Socket URL
     */
    public string $socketurl;

    /**
     * @var string Port
     */
    public string $port;

    /**
     * Constructor
     *
     * @param string $socketurl
     * @param string $port
     * @throws coding_exception
     */
    public function __construct(string $socketurl, string $port) {
        $this->socketurl = $socketurl;
        $this->port = $port;
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws JsonException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->socketurl = $this->socketurl;
        $data->port = $this->port;
        return $data;
    }
}
