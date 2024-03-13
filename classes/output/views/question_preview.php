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
 * Question preview renderer
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kuet\output\views;

use coding_exception;
use dml_exception;
use dml_transaction_exception;
use JsonException;
use mod_kuet\models\questions;
use mod_kuet\persistents\kuet_questions;
use moodle_exception;
use ReflectionException;
use renderable;
use stdClass;
use templatable;
use renderer_base;

/**
 * Question preview renderable class
 */
class question_preview implements renderable, templatable {

    /**
     * @var int question id
     */
    protected int $qid;

    /**
     * @var int kuet question id
     */
    protected int $kid;

    /**
     * @var int course module id
     */
    protected int $cmid;

    /**
     * @var int kuet session id
     */
    protected int $sessionid;

    /**
     * @var int kuet module id
     */
    protected int $kuetid;

    /**
     * Constructor
     *
     * @param int $qid
     * @param int $kid
     * @param int $cmid
     * @param int $sessionid
     * @param int $kuetid
     */
    public function __construct(int $qid, int $kid, int $cmid, int $sessionid, int $kuetid) {
        $this->qid = $qid;
        $this->kid = $kid;
        $this->cmid = $cmid;
        $this->sessionid = $sessionid;
        $this->kuetid = $kuetid;
    }

    /**
     * Template exporter
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $question = new kuet_questions($this->kid);
        /** @var questions $type */
        $type = questions::get_question_class_by_string_type($question->get('qtype'));
        return $type::export_question(
            $question->get('id'),
            $this->cmid,
            $this->sessionid,
            $this->kuetid);
    }
}
