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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_kuet\question\bank;
/**
 * Selection-only question bank for KUET sessions.
 * @package mod_kuet
 * @copyright 2026 KUET contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class name_column extends \core_question\local\bank\column_base {
    /**
     * {@inheritDoc}
     */
    public function get_name(): string {
        return 'kuetquestionname';
    }
    /**
     * {@inheritDoc}
     */
    public function get_title(): string {
        return get_string('questionname', 'question');
    }
    /**
     * {@inheritDoc}
     */
    public function is_sortable() {
        return 'q.name';
    }
    /**
     * {@inheritDoc}
     */
    public function get_required_fields(): array {
        return ['q.name'];
    }
    /**
     * {@inheritDoc}
     */
    protected function display_content($question, $rowclasses): void {
        echo \html_writer::tag('label', format_string($question->name), ['for' => 'checkq' . $question->id]);
        if (!custom_view::selectable($question)) {
            echo \html_writer::div(get_string('unsupportedquestiontype', 'mod_kuet'), 'text-muted small');
        }
    }
}
