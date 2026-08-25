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

/**
 * Read-only inventory before activating the question bank migration.
 *
 * @package mod_kuet
 * @copyright 2026 KUET contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['help' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised));
}
if ($options['help']) {
    cli_writeln('Read-only JSON inventory: php mod/kuet/cli/question_bank_diagnostics.php');
    exit(0);
}
$diagnostics = [
    'installedversion' => get_config('mod_kuet', 'version'),
    'questionslockedfield' => $DB->get_manager()->field_exists('kuet_sessions', 'questionslocked'),
    'sessionpositions' => $DB->count_records('kuet_questions'),
    'missingquestionversions' => $DB->count_records_sql('SELECT COUNT(1)
        FROM {kuet_questions} kq LEFT JOIN {question_versions} qv ON qv.questionid = kq.questionid
        WHERE qv.id IS NULL'),
    'positionswithoutreference' => $DB->count_records_sql("SELECT COUNT(1)
        FROM {kuet_questions} kq LEFT JOIN {question_references} qr ON qr.itemid = kq.id
         AND qr.component = 'mod_kuet' AND qr.questionarea = 'session_question'
        WHERE qr.id IS NULL"),
    'legacycategories' => $DB->count_records_sql('SELECT COUNT(1)
        FROM {question_categories} qc JOIN {context} ctx ON ctx.id = qc.contextid
        WHERE ctx.contextlevel IN (?, ?, ?, ?)', [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE, CONTEXT_USER]),
    'historicalsessions' => $DB->count_records_select(
        'kuet_sessions',
        'status IN (0, 2) OR id IN (SELECT session FROM {kuet_questions_responses})
         OR id IN (SELECT session FROM {kuet_user_progress})'
    ),
];
// Count relevant pending core tasks without exposing their custom data or user information.
$tasks = $DB->get_records('task_adhoc', [], '', 'id, classname');
$diagnostics['pendingquestionbanktasks'] = 0;
foreach ($tasks as $task) {
    if (str_contains($task->classname, 'question') || str_contains($task->classname, 'qbank')) {
        $diagnostics['pendingquestionbanktasks']++;
    }
}
cli_writeln(json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
