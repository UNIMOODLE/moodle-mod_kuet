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

namespace mod_kuet;

use mod_kuet\output\switch_question_bank;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/questionbank_scenario_trait.php');

/**
 * Question bank chooser test
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_kuet\output\switch_question_bank
 */
final class switch_question_bank_test extends \advanced_testcase {
    use questionbank_scenario_trait;

    /**
     * The chooser never offers the kuet itself as a question bank.
     *
     * Regression for KUET-042: the chooser of core opens with a link to the question
     * bank of the activity it is rendered for, carrying that course module id in
     * data-newmodid. A kuet has no bank of its own, so choosing it called the external
     * with a cmid that is not a qbank and the panel died in a
     * dml_missing_record_exception.
     *
     * @return void
     */
    public function test_the_chooser_does_not_offer_the_kuet_itself(): void {
        global $PAGE;

        $this->create_bank_question($this->teacher, 'Own question');
        self::setUser($this->teacher);

        $data = (new switch_question_bank(
            $this->kuet->cmid,
            $this->course->id,
            $this->teacher->id
        ))->export_for_template($PAGE->get_renderer('core'));

        $offered = array_merge(
            array_column($data['coursesharedbanks'], 'modid'),
            array_column($data['recentlyviewedbanks'], 'modid')
        );
        $this->assertNotContains(
            (int) $this->kuet->cmid,
            array_map('intval', $offered),
            'The chooser offers the kuet as if it were a question bank'
        );
        $this->assertArrayNotHasKey('quizcmid', $data);
        $this->assertArrayNotHasKey('quizname', $data);
    }

    /**
     * The banks of the course are offered, and the search field knows where it is asked from.
     *
     * @return void
     */
    public function test_the_chooser_offers_the_banks_of_the_course(): void {
        global $PAGE;

        $this->create_bank_question($this->teacher, 'Own question');
        self::setUser($this->teacher);

        $data = (new switch_question_bank(
            $this->kuet->cmid,
            $this->course->id,
            $this->teacher->id
        ))->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hascoursesharedbanks']);
        $this->assertContains(
            (int) $this->qbank->cmid,
            array_map('intval', array_column($data['coursesharedbanks'], 'modid')),
            'The bank of the course is not offered'
        );
        // What the autocomplete of core sends back when it searches the shared banks
        // of the site, so it has to be the context the question is being added from.
        $this->assertEquals(\context_module::instance($this->kuet->cmid)->id, $data['kuetcontextid']);
    }

    /**
     * The chooser renders, and what it renders is the plugin's own template.
     *
     * The fragment the panel calls goes through $OUTPUT->render(), which resolves the
     * template from the name of this class: if mod_kuet/switch_question_bank were not
     * there, the modal would come back with a fatal error instead of the chooser.
     *
     * @return void
     */
    public function test_the_chooser_renders_the_template_of_the_plugin(): void {
        global $PAGE;

        $this->create_bank_question($this->teacher, 'Own question');
        self::setUser($this->teacher);
        $PAGE->set_url('/mod/kuet/view.php', ['id' => $this->kuet->cmid]);

        $html = $PAGE->get_renderer('mod_kuet')->render(new switch_question_bank(
            $this->kuet->cmid,
            $this->course->id,
            $this->teacher->id
        ));

        $this->assertStringContainsString('id="searchbanks"', $html);
        $this->assertStringContainsString('data-newmodid="' . $this->qbank->cmid . '"', $html);
        $this->assertStringNotContainsString('data-newmodid="' . $this->kuet->cmid . '"', $html);
    }
}
