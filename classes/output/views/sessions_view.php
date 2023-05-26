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

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jqshow\output\views;
use coding_exception;
use core\invalid_persistent_exception;
use mod_jqshow\models\sessions;
use moodle_exception;
use renderable;
use stdClass;
use templatable;
use renderer_base;

class sessions_view implements renderable, templatable {

    /** @var stdClass jqshow */
    protected stdClass $jqshow;

    /** @var int cmid */
    protected int $cmid;

    /**
     * sessions_view constructor.
     * @param stdClass $jqshow
     * @param int $cmid
     */
    public function __construct(stdClass $jqshow, int $cmid) {
        $this->jqshow = $jqshow;
        $this->cmid = $cmid;
    }

    /**
     * @param renderer_base $output
     * @return stdClass
     * @throws invalid_persistent_exception
     * @throws moodle_exception
     * @throws coding_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        return (new sessions($this->jqshow, $this->cmid))->export();
    }
}
