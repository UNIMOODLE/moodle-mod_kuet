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
class addquestions_external_test extends advanced_testcase {

    public function test_add_questions() :void {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('jqshow', ['course' => $course->id]);
        $this->sessionmock['jqshowid'] = $jqshow->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_jqshow');

        // Only a user with capability can add questions.
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);

        // Create session.
        $sessionmock = [
            'name' => 'Session Test',
            'jqshowid' => $jqshow->id,
            'anonymousanswer' => 0,
            'sessionmode' => \mod_jqshow\models\sessions::PODIUM_MANUAL,
            'sgrade' => 0,
            'countdown' => 0,
            'showgraderanking' => 0,
            'randomquestions' => 0,
            'randomanswers' => 0,
            'showfeedback' => 0,
            'showfinalgrade' => 0,
            'startdate' => 1680534000,
            'enddate' => 1683133200,
            'automaticstart' => 0,
            'timemode' => 0,
            'sessiontime' => 0,
            'questiontime' => 10,
            'groupings' => 0,
            'status' => 1,
            'sessionid' => 0,
            'submitbutton' => 0,
            'showgraderanking' => 0,
        ];
        $createdsid = $generator->create_session($jqshow, (object) $sessionmock);

        // Create questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        $essayq = $questiongenerator->create_question('essay', null, ['category' => $cat->id]); // Not accepted on jqshow.

        // Compatible questions.
        $data = \mod_jqshow\external\addquestions_external::add_questions([
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => 'shortanswer'],
            ['questionid' => $numq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => 'numerical']
        ]);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('added', $data);
        $this->assertTrue($data['added']);

        // Not compatible question.
        $data = \mod_jqshow\external\addquestions_external::add_questions([
            ['questionid' => $essayq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => 'essay']
        ]);
        $total = mod_jqshow\persistents\jqshow_questions::count_records(['sessionid' => $createdsid, 'jqshowid' => $jqshow->id]);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('added', $data);
        $this->assertTrue($data['added']);

        // Revisamos a ver si realmente se ha añadido.
        $this->assertEquals(2, $total);
    }
}
