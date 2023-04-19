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

namespace mod_jqshow\models;

use mod_jqshow\persistents\jqshow_questions;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class questions {

    const MULTIPLE_CHOICE = 'multichoice';
    const TYPES = [self::MULTIPLE_CHOICE];
    protected int $jqshowid;
    protected int $cmid;
    protected int $sid;
    /** @var jqshow_questions[] list */
    protected array $list;

    public function __construct(int $jqshowid, int $cmid, int $sid) {
        $this->jqshowid = $jqshowid;
        $this->cmid = $cmid;
        $this->sid = $sid;
    }

    public function set_list() {
        $this->list = jqshow_questions::get_records(['sessionid' => $this->sid, 'jqshowid' => $this->jqshowid], 'qorder', 'ASC');
    }

    /**
     * @return jqshow_questions[]
     */
    public function get_list(): array {
        if (empty($this->list)) {
            $this->set_list();
        }
        return $this->list;
    }

    /**
     * @return int
     */
    public function get_num_questions(): int {
        return jqshow_questions::count_records(['sessionid' => $this->sid, 'jqshowid' => $this->jqshowid]);
    }
}
