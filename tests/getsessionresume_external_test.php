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
use mod_jqshow\models\questions;
use mod_jqshow\models\sessions;

/**
 *
 * @package     mod_jqshow
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @category   test
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class getsessionresume_external_test extends advanced_testcase {

    public function test_getsessionresume() {
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $jqshow = self::getDataGenerator()->create_module('jqshow', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_jqshow');
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);

        $sessionmock1 = [
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
            'startdate' => 0,
            'enddate' => 0,
            'automaticstart' => 0,
            'timemode' => 1,
            'sessiontime' => 720,
            'questiontime' => 10,
            'groupings' => 0,
            'status' => sessions::SESSION_ACTIVE,
            'sessionid' => 0,
            'showgraderanking' => 0,
        ];
        $sessionmock2 = $sessionmock1;
        $sessionmock2['name'] = 'Session Test 2';
        $sessionmock2['sessionmode'] = sessions::INACTIVE_PROGRAMMED;
        $sessionmock2['automaticstart'] = 1;
        $sessionmock2['startdate'] = 1697035302;

        // Create sessions.
        $session1id = $generator->create_session($jqshow, (object) $sessionmock1);
        $sessionmock1['sessionid'] = $session1id;
        $session2id = $generator->create_session($jqshow, (object) $sessionmock2);
        $sessionmock2['sessionid'] = $session2id;

        // Create questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question(questions::SHORTANSWER, null, array('category' => $cat->id));
        $nq = $questiongenerator->create_question(questions::NUMERICAL, null, array('category' => $cat->id));

        // Add questions.
        \mod_jqshow\external\addquestions_external::add_questions([
            ['questionid' => $saq->id, 'sessionid' => $session1id, 'jqshowid' => $jqshow->id, 'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $session1id, 'jqshowid' => $jqshow->id, 'qtype' => questions::NUMERICAL]
        ]);
        \mod_jqshow\external\addquestions_external::add_questions([
            ['questionid' => $saq->id, 'sessionid' => $session2id, 'jqshowid' => $jqshow->id, 'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $session2id, 'jqshowid' => $jqshow->id, 'qtype' => questions::NUMERICAL]
        ]);

        $data1 = \mod_jqshow\external\getsessionresume_external::getsessionresume($sessionmock1['sessionid'], $jqshow->cmid);
        $data2 = \mod_jqshow\external\getsessionresume_external::getsessionresume($sessionmock2['sessionid'], $jqshow->cmid);

        $this->assertIsArray($data1);
        $this->assertArrayHasKey('config', $data1);

        $this->assertIsArray($data1['config']);
        $this->assertEquals(11, count($data1['config']));

        $this->assertArrayHasKey('iconconfig', $data1['config'][0]);
        $this->assertArrayHasKey('configname', $data1['config'][0]);
        $this->assertArrayHasKey('configvalue', $data1['config'][0]);
        $this->assertEquals('name', $data1['config'][0]['iconconfig']);
        $this->assertEquals(get_string('session_name', 'mod_jqshow'), $data1['config'][0]['configname']);
        $this->assertEquals($sessionmock1['name'], $data1['config'][0]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data1['config'][1]);
        $this->assertArrayHasKey('configname', $data1['config'][1]);
        $this->assertArrayHasKey('configvalue', $data1['config'][1]);
        $this->assertEquals('anonymise', $data1['config'][1]['iconconfig']);
        $this->assertEquals(get_string('anonymousanswer', 'mod_jqshow'), $data1['config'][1]['configname']);
        $this->assertEquals(get_string('no'), $data1['config'][1]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data1['config'][2]);
        $this->assertArrayHasKey('configname', $data1['config'][2]);
        $this->assertArrayHasKey('configvalue', $data1['config'][2]);
        $this->assertEquals('sessionmode', $data1['config'][2]['iconconfig']);
        $this->assertEquals(get_string('sessionmode', 'mod_jqshow'), $data1['config'][2]['configname']);
        $this->assertEquals(get_string($sessionmock1['sessionmode'], 'mod_jqshow'), $data1['config'][2]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data1['config'][3]);
        $this->assertArrayHasKey('configname', $data1['config'][3]);
        $this->assertArrayHasKey('configvalue', $data1['config'][3]);
        $this->assertEquals('countdown', $data1['config'][3]['iconconfig']);
        $this->assertEquals(get_string('countdown', 'mod_jqshow'), $data1['config'][3]['configname']);
        $this->assertEquals(get_string('no'), $data1['config'][3]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data1['config'][4]);
        $this->assertArrayHasKey('configname', $data1['config'][4]);
        $this->assertArrayHasKey('configvalue', $data1['config'][4]);
        $this->assertEquals('showgraderanking', $data1['config'][4]['iconconfig']);
        $this->assertEquals(get_string('showgraderanking', 'mod_jqshow'), $data1['config'][4]['configname']);
        $this->assertEquals(get_string('no'), $data1['config'][4]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data1['config'][5]);
        $this->assertArrayHasKey('configname', $data1['config'][5]);
        $this->assertArrayHasKey('configvalue', $data1['config'][5]);
        $this->assertEquals('randomquestions', $data1['config'][5]['iconconfig']);
        $this->assertEquals(get_string('randomquestions', 'mod_jqshow'), $data1['config'][5]['configname']);
        $this->assertEquals(get_string('no'), $data1['config'][5]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data1['config'][6]);
        $this->assertArrayHasKey('configname', $data1['config'][6]);
        $this->assertArrayHasKey('configvalue', $data1['config'][6]);
        $this->assertEquals('randomanswers', $data1['config'][6]['iconconfig']);
        $this->assertEquals(get_string('randomanswers', 'mod_jqshow'), $data1['config'][6]['configname']);
        $this->assertEquals(get_string('no'), $data1['config'][6]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data1['config'][7]);
        $this->assertArrayHasKey('configname', $data1['config'][7]);
        $this->assertArrayHasKey('configvalue', $data1['config'][7]);
        $this->assertEquals('showfeedback', $data1['config'][7]['iconconfig']);
        $this->assertEquals(get_string('showfeedback', 'mod_jqshow'), $data1['config'][7]['configname']);
        $this->assertEquals(get_string('no'), $data1['config'][7]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data1['config'][8]);
        $this->assertArrayHasKey('configname', $data1['config'][8]);
        $this->assertArrayHasKey('configvalue', $data1['config'][8]);
        $this->assertEquals('showfinalgrade', $data1['config'][8]['iconconfig']);
        $this->assertEquals(get_string('showfinalgrade', 'mod_jqshow'), $data1['config'][8]['configname']);
        $this->assertEquals(get_string('no'), $data1['config'][8]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data1['config'][9]);
        $this->assertArrayHasKey('configname', $data1['config'][9]);
        $this->assertArrayHasKey('configvalue', $data1['config'][9]);
        $this->assertEquals('automaticstart', $data1['config'][9]['iconconfig']);
        $this->assertEquals(get_string('automaticstart', 'mod_jqshow'), $data1['config'][9]['configname']);
        $this->assertEquals(get_string('no'), $data1['config'][9]['configvalue']);

        $numquestion = 2;
        $timeperquestion = round((int)$sessionmock1['sessiontime'] / $numquestion);
        $timemodestring = get_string(
                'session_time_resume', 'mod_jqshow', userdate($sessionmock1['sessiontime'], '%Mm %Ss')
            ) . '<br>' .
            get_string('question_time', 'mod_jqshow') . ': ' .
            $timeperquestion . 's';

        $this->assertArrayHasKey('iconconfig', $data1['config'][10]);
        $this->assertArrayHasKey('configname', $data1['config'][10]);
        $this->assertArrayHasKey('configvalue', $data1['config'][10]);
        $this->assertEquals('timelimit', $data1['config'][10]['iconconfig']);
        $this->assertEquals(get_string('timemode', 'mod_jqshow'), $data1['config'][10]['configname']);
        $this->assertEquals($timemodestring, $data1['config'][10]['configvalue']);

        // SESION 2.
        $this->assertIsArray($data2);
        $this->assertArrayHasKey('config', $data2);
        $this->assertIsArray($data2['config']);

        $this->assertEquals(11, count($data2['config']));

        $this->assertArrayHasKey('iconconfig', $data2['config'][0]);
        $this->assertArrayHasKey('configname', $data2['config'][0]);
        $this->assertArrayHasKey('configvalue', $data2['config'][0]);
        $this->assertEquals('name', $data2['config'][0]['iconconfig']);
        $this->assertEquals(get_string('session_name', 'mod_jqshow'), $data2['config'][0]['configname']);
        $this->assertEquals($sessionmock2['name'], $data2['config'][0]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data2['config'][1]);
        $this->assertArrayHasKey('configname', $data2['config'][1]);
        $this->assertArrayHasKey('configvalue', $data2['config'][1]);
        $this->assertEquals('anonymise', $data2['config'][1]['iconconfig']);
        $this->assertEquals(get_string('anonymousanswer', 'mod_jqshow'), $data2['config'][1]['configname']);
        $this->assertEquals(get_string('no'), $data2['config'][1]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data2['config'][2]);
        $this->assertArrayHasKey('configname', $data2['config'][2]);
        $this->assertArrayHasKey('configvalue', $data2['config'][2]);
        $this->assertEquals('sessionmode', $data2['config'][2]['iconconfig']);
        $this->assertEquals(get_string('sessionmode', 'mod_jqshow'), $data2['config'][2]['configname']);
        $this->assertEquals(get_string($sessionmock2['sessionmode'], 'mod_jqshow'), $data2['config'][2]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data2['config'][3]);
        $this->assertArrayHasKey('configname', $data2['config'][3]);
        $this->assertArrayHasKey('configvalue', $data2['config'][3]);
        $this->assertEquals('countdown', $data2['config'][3]['iconconfig']);
        $this->assertEquals(get_string('countdown', 'mod_jqshow'), $data2['config'][3]['configname']);
        $this->assertEquals(get_string('no'), $data2['config'][3]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data2['config'][4]);
        $this->assertArrayHasKey('configname', $data2['config'][4]);
        $this->assertArrayHasKey('configvalue', $data2['config'][4]);
        $this->assertEquals('randomquestions', $data2['config'][4]['iconconfig']);
        $this->assertEquals(get_string('randomquestions', 'mod_jqshow'), $data2['config'][4]['configname']);
        $this->assertEquals(get_string('no'), $data2['config'][4]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data2['config'][5]);
        $this->assertArrayHasKey('configname', $data2['config'][5]);
        $this->assertArrayHasKey('configvalue', $data2['config'][5]);
        $this->assertEquals('randomanswers', $data2['config'][5]['iconconfig']);
        $this->assertEquals(get_string('randomanswers', 'mod_jqshow'), $data2['config'][5]['configname']);
        $this->assertEquals(get_string('no'), $data2['config'][5]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data2['config'][6]);
        $this->assertArrayHasKey('configname', $data2['config'][6]);
        $this->assertArrayHasKey('configvalue', $data2['config'][6]);
        $this->assertEquals('showfeedback', $data2['config'][6]['iconconfig']);
        $this->assertEquals(get_string('showfeedback', 'mod_jqshow'), $data2['config'][6]['configname']);
        $this->assertEquals(get_string('no'), $data2['config'][6]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data2['config'][7]);
        $this->assertArrayHasKey('configname', $data2['config'][7]);
        $this->assertArrayHasKey('configvalue', $data2['config'][7]);
        $this->assertEquals('showfinalgrade', $data2['config'][7]['iconconfig']);
        $this->assertEquals(get_string('showfinalgrade', 'mod_jqshow'), $data2['config'][7]['configname']);
        $this->assertEquals(get_string('no'), $data2['config'][7]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data2['config'][8]);
        $this->assertArrayHasKey('configname', $data2['config'][8]);
        $this->assertArrayHasKey('configvalue', $data2['config'][8]);
        $this->assertEquals('startdate', $data2['config'][8]['iconconfig']);
        $this->assertEquals(get_string('startdate', 'mod_jqshow'), $data2['config'][8]['configname']);
        $this->assertEquals(userdate($sessionmock2['startdate'], get_string('strftimedatetimeshort', 'core_langconfig')),
            $data2['config'][8]['configvalue']);

        $this->assertArrayHasKey('iconconfig', $data2['config'][9]);
        $this->assertArrayHasKey('configname', $data2['config'][9]);
        $this->assertArrayHasKey('configvalue', $data2['config'][9]);
        $this->assertEquals('automaticstart', $data2['config'][9]['iconconfig']);
        $this->assertEquals(get_string('automaticstart', 'mod_jqshow'), $data2['config'][9]['configname']);
        $this->assertEquals(get_string('yes'), $data2['config'][9]['configvalue']);

        $numquestion = 2;
        $timeperquestion = round((int)$sessionmock2['sessiontime'] / $numquestion);
        $timemodestring = get_string(
                'session_time_resume', 'mod_jqshow', userdate($sessionmock2['sessiontime'], '%Mm %Ss')
            ) . '<br>' .
            get_string('question_time', 'mod_jqshow') . ': ' .
            $timeperquestion . 's';

        $this->assertArrayHasKey('iconconfig', $data2['config'][10]);
        $this->assertArrayHasKey('configname', $data2['config'][10]);
        $this->assertArrayHasKey('configvalue', $data2['config'][10]);
        $this->assertEquals('timelimit', $data2['config'][10]['iconconfig']);
        $this->assertEquals(get_string('timemode', 'mod_jqshow'), $data2['config'][10]['configname']);
        $this->assertEquals($timemodestring, $data2['config'][10]['configvalue']);

    }
}
