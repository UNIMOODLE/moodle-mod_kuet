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
 * Action for editing the source question in its question bank.
 *
 * @package mod_kuet
 * @copyright 2026 KUET contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_action_column extends \core_question\local\bank\column_base {
    /**
     * {@inheritDoc}
     */
    public function get_name(): string {
        return 'editquestion';
    }

    /**
     * {@inheritDoc}
     */
    public function get_title(): string {
        return get_string('editbankquestion', 'mod_kuet');
    }

    /**
     * {@inheritDoc}
     */
    protected function display_content($question, $rowclasses): void {
        if (question_has_capability_on($question, 'edit') && \question_bank::is_qtype_installed($question->qtype)) {
            $label = get_string('editbankquestion', 'mod_kuet');
            echo \html_writer::link(
                $this->qbank->question_edit_url((int) $question->id),
                get_string('edit'),
                ['class' => 'btn btn-secondary btn-sm', 'title' => $label, 'aria-label' => $label]
            );
        }
    }
}
