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
class custom_view extends \core_question\local\bank\view {
    /**
     * Default page size for the modal.
     */
    public const DEFAULT_PAGE_SIZE = 20;
    /**
     * @var string
     */
    public $component = 'mod_kuet';
    /**
     * @var string
     */
    public $callback = 'kuet_question_data';

    /**
     * {@inheritDoc}
     */
    public function __construct($contexts, $pageurl, $course, $cm = null, $params = [], $extraparams = []) {
        // The core can instantiate custom views from fragment requests. Authorise here too.
        $destination = \mod_kuet\helpers\modcontext::from_cmid((int)($extraparams['kuetcmid'] ?? 0));
        require_capability('mod/kuet:managesessions', $destination);
        \mod_kuet\helpers\modcontext::require_session_in_cm(
            (int)($extraparams['sessionid'] ?? 0),
            $destination->instanceid
        );
        \mod_kuet\question\bank_provider::require_bank($cm->id);
        $this->pagesize = self::DEFAULT_PAGE_SIZE;
        parent::__construct($contexts, $pageurl, $course, $cm, $params, $extraparams);
    }

    /**
     * {@inheritDoc}
     */
    protected function init_column_manager(): void {
        $this->columnmanager = new \core_question\local\bank\column_manager_base();
    }

    /**
     * {@inheritDoc}
     */
    protected function init_question_actions(): void {
        $this->questionactions = [];
    }

    /**
     * {@inheritDoc}
     */
    protected function init_bulk_actions(): void {
        $this->bulkactions = [];
    }

    /**
     * {@inheritDoc}
     */
    protected function get_question_bank_plugins(): array {
        $columns = [];
        foreach (
            [checkbox_column::class, 'qbank_viewquestiontype\\question_type_column',
                name_column::class, add_action_column::class, edit_action_column::class, preview_action_column::class] as $class
        ) {
            $name = substr($class, strrpos($class, '\\') + 1);
            $id = $class . \core_question\local\bank\column_base::ID_SEPARATOR . $name;
            $columns[$id] = $class::from_column_name($this, $name);
        }
        return $columns;
    }

    /**
     * {@inheritDoc}
     */
    protected function heading_column(): string {
        return name_column::class;
    }

    /**
     * {@inheritDoc}
     */
    protected function default_sort(): array {
        return ['mod_kuet__question\\bank\\name_column' => SORT_ASC];
    }

    /**
     * {@inheritDoc}
     */
    protected function get_plugin_controls(\context $context, int $categoryid): string {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    protected function display_question_bank_header(): void {
    }

    /**
     * {@inheritDoc}
     */
    protected function create_new_question_form($category, $canadd): void {
    }


    /**
     * Mandatory source and ownership constraints cannot be bypassed by OR/NOT filters.
     */
    protected function build_query(): void {
        global $USER;
        [$fields, $joins] = $this->get_component_requirements($this->requiredcolumns);
        $sorts = [];
        foreach ($this->sort as $name => $order) {
            [$column, $subsort] = $this->parse_subsort($name);
            $sorts[] = $this->requiredcolumns[$column]->sort_expression($order == SORT_DESC, $subsort);
        }
        $this->sqlparams = ['kuetcontext' => $this->contexts->lowest()->id,
            'kuetready' => 'ready', 'kuetnewready' => 'ready'];
        $where = ['q.parent = 0', 'qc.contextid = :kuetcontext', 'qv.status = :kuetready',
            'NOT EXISTS (SELECT 1 FROM {question_versions} newer
              WHERE newer.questionbankentryid = qv.questionbankentryid
                AND newer.version > qv.version AND newer.status = :kuetnewready)'];
        if (!has_capability('moodle/question:useall', $this->contexts->lowest())) {
            $where[] = 'q.createdby = :kuetauthor';
            $this->sqlparams['kuetauthor'] = $USER->id;
        }
        $conditions = [];
        foreach ($this->searchconditions as $condition) {
            if ($condition->where()) {
                $conditions[] = '(' . $condition->where() . ')';
                $this->sqlparams = array_merge($this->sqlparams, $condition->params());
            }
        }
        if ($conditions) {
            $jointype = (int)($this->pagevars['jointype'] ?? \core\output\datafilter::JOINTYPE_ALL);
            $separator = $jointype === \core\output\datafilter::JOINTYPE_ALL ? ' AND ' : ' OR ';
            $not = $jointype === \core\output\datafilter::JOINTYPE_NONE ? 'NOT ' : '';
            $where[] = $not . '(' . implode($separator, $conditions) . ')';
        }
        $sql = ' FROM {question} q ' . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $where);
        $this->countsql = 'SELECT COUNT(1)' . $sql;
        $this->loadsql = 'SELECT ' . implode(', ', $fields) . $sql . ' ORDER BY ' . implode(', ', $sorts);
    }

    /**
     * Whether a row can be selected; the server validates again when adding.
     */
    public static function selectable($question): bool {
        return in_array($question->qtype, \mod_kuet\models\questions::TYPES, true) &&
            \question_bank::is_qtype_usable($question->qtype) && question_has_capability_on($question, 'use');
    }

    /**
     * Build the core edit URL with a return to KUET session configuration.
     *
     * @param int $questionid Question version ID.
     * @return moodle_url
     */
    public function question_edit_url(int $questionid): \moodle_url {
        $returnurl = new \moodle_url('/mod/kuet/sessions.php', [
            'cmid' => (int) $this->extraparams['kuetcmid'],
            'sid' => (int) $this->extraparams['sessionid'],
            'page' => 2,
        ]);
        return new \moodle_url('/question/bank/editquestion/question.php', [
            'id' => $questionid,
            'cmid' => $this->cm->id,
            'returnurl' => $returnurl->out_as_local_url(false),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    protected function display_bottom_controls(\context $catcontext): void {
        echo \html_writer::tag(
            'button',
            get_string('addselectedquestions', 'mod_kuet'),
            ['type' => 'submit', 'class' => 'btn btn-primary mt-2']
        );
    }

    /**
     * {@inheritDoc}
     */
    public function display(): void {
        echo \html_writer::start_div('questionbankwindow');
        $this->wanted_filters();
        $this->display_question_list();
        echo \html_writer::end_div();
    }
}
