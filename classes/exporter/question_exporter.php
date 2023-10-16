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

namespace mod_jqshow\exporter;

use context;
use core\external\exporter;

class question_exporter extends exporter {

    /**
     * @return array[]
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
     * @return string[]
     */
    protected static function define_related() {
        return array('context' => context::class);
    }
}
