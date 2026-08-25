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
 * CLI script to renormalise kuet session grades to the per-session maximum.
 *
 * Recalculates and pushes to the gradebook the grades of existing kuet
 * activities so they reflect the kuet.sessiongrademax setting (KUETEDUCAM-67).
 * Intended to be run after upgrading, where renormalising cannot happen because
 * the gradebook update needs get_fast_modinfo(), forbidden during an upgrade.
 *
 * @package    mod_kuet
 * @author     3IPUNT <contacte@tresipunt.com>
 * @copyright  3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_kuet\api\grade;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
global $CFG, $DB;

require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/kuet/lib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'all' => false,
        'kuetid' => 0,
        'courseid' => 0,
    ],
    [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || (!$options['all'] && empty($options['kuetid']) && empty($options['courseid']))) {
    $help = <<<EOT
Renormalise kuet session grades to the per-session maximum (sessiongrademax).

Recalculates the stored session grades and the activity grade for the selected
kuet instances and pushes the result to the gradebook. Run it after upgrading
to apply the new per-session maximum to grades recorded before the upgrade.

Options:
 -h, --help        Print out this help.
     --all         Process every kuet instance on the site.
     --kuetid=N    Process only the kuet instance with this id.
     --courseid=N  Process every kuet instance in this course.

Example:
 \$ php mod/kuet/cli/recalculate_grades.php --all
 \$ php mod/kuet/cli/recalculate_grades.php --kuetid=42
EOT;
    cli_writeln($help);
    exit(0);
}

// Resolve the target instances.
if (!empty($options['kuetid'])) {
    $kuets = $DB->get_records('kuet', ['id' => (int) $options['kuetid']]);
} else if (!empty($options['courseid'])) {
    $kuets = $DB->get_records('kuet', ['course' => (int) $options['courseid']]);
} else {
    $kuets = $DB->get_records('kuet');
}

if (empty($kuets)) {
    cli_writeln('No kuet instances found for the given criteria.');
    exit(0);
}

cli_heading('Recalculating kuet grades (' . count($kuets) . ' instance(s))');

$processed = 0;
$skipped = 0;
foreach ($kuets as $kuet) {
    $cm = get_coursemodule_from_instance('kuet', $kuet->id);
    if (!$cm) {
        cli_writeln("- kuet id {$kuet->id}: no course module found, skipped.");
        $skipped++;
        continue;
    }
    grade::recalculate_mod_mark((int) $cm->id, (int) $kuet->id);
    cli_writeln("- kuet id {$kuet->id} (cmid {$cm->id}): recalculated.");
    $processed++;
}

cli_writeln("Done. Recalculated: {$processed}. Skipped: {$skipped}.");
exit(0);
