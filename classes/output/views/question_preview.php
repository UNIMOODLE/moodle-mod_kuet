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

namespace mod_jqshow\output\views;
use coding_exception;
use dml_exception;
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;
use moodle_exception;
use renderable;
use stdClass;
use templatable;
use renderer_base;
/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_preview implements renderable, templatable {

    protected int $qid;
    protected int $cmid;
    protected int $sessionid;
    protected int $jqshowid;
    protected string $type;

    /**
     * @param int $qid
     * @param $cmid
     * @param $sessionid
     * @param $jqshowid
     */
    public function __construct(int $qid, int $jqid, int $cmid, int $sessionid, int $jqshowid, string $type) {
        $this->qid = $qid;
        $this->jqid = $jqid;
        $this->cmid = $cmid;
        $this->sessionid = $sessionid;
        $this->jqshowid = $jqshowid;
        $this->type = $type;

    }

    /**
     * @param renderer_base $output
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB;
        switch ($this->type){
            case 'multichoice':
                $data = questions::export_multichoice($this->jqid, $this->cmid, $this->sessionid, $this->jqshowid, true);
                break;
            default:
                throw new moodle_exception('question_nosuitable', 'mod_jqshow');
        }
        return $data;
    }
}
