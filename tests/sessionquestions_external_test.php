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
class sessionquestions_external_test extends advanced_testcase {

    public function test_sessionquestions() {
        global $DB;
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
            'startdate' => 0,
            'enddate' => 0,
            'automaticstart' => 0,
            'timemode' => sessions::QUESTION_TIME,
            'sessiontime' => 0,
            'questiontime' => 10,
            'groupings' => 0,
            'status' => \mod_jqshow\models\sessions::SESSION_ACTIVE,
            'sessionid' => 0,
            'submitbutton' => 0,
            'showgraderanking' => 0,
        ];
        $createdsid = $generator->create_session($jqshow, (object) $sessionmock);

        // Create questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question(questions::SHORTANSWER, null, array('category' => $cat->id));
        $nq = $questiongenerator->create_question(questions::NUMERICAL, null, array('category' => $cat->id));
        $tfq = $questiongenerator->create_question(questions::TRUE_FALSE, null, array('category' => $cat->id));
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, array('category' => $cat->id));
        $ddwtosq = $questiongenerator->create_question(questions::DDWTOS, null, array('category' => $cat->id));
        $dq = $questiongenerator->create_question(questions::DESCRIPTION, null, array('category' => $cat->id));

        // Add questions to a session.
        $questions = [
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::NUMERICAL],
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::TRUE_FALSE],
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::MULTICHOICE],
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DDWTOS],
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DESCRIPTION],
        ];
        $generator->add_questions_to_session($questions);
        $data = \mod_jqshow\external\sessionquestions_external::sessionquestions($jqshow->id, $jqshow->cmid, $createdsid);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('jqshowid', $data);
        $this->assertArrayHasKey('cmid', $data);
        $this->assertArrayHasKey('sid', $data);
        $this->assertArrayHasKey('sessionquestions', $data);
        $this->assertEquals($jqshow->id, $data['jqshowid']);
        $this->assertEquals($jqshow->cmid, $data['cmid']);
        $this->assertEquals($createdsid, $data['sid']);
        $this->assertIsArray($data['sessionquestions']);
        // Question 1.
        $this->assertIsObject($data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('jqshowid', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('questionnid', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('position', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('name', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('type', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('isvalid', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('time', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('version', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('managesessions', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('question_preview_url', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('editquestionurl', $data['sessionquestions'][0]);
        $this->assertEquals($createdsid, $data['sessionquestions'][0]->{'sid'});
        $this->assertEquals($jqshow->cmid, $data['sessionquestions'][0]->{'cmid'});
        $this->assertEquals($jqshow->id, $data['sessionquestions'][0]->{'jqshowid'});
        $qbs = $DB->get_record('question', ['id' => $saq->id], '*', MUST_EXIST);
        $jsaq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::SHORTANSWER]);
        $this->assertEquals($jsaq->get('id'), $data['sessionquestions'][0]->{'questionnid'});
        $this->assertEquals(1, $data['sessionquestions'][0]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][0]->{'name'});
        $this->assertEquals(questions::SHORTANSWER, $data['sessionquestions'][0]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][0]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][0]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][0]->{'managesessions'});
        $args = [
            'id' => $jqshow->cmid,
            'jqid' => $jsaq->get('id'),
            'sid' => $createdsid,
            'jqsid' => $jqshow->id,
            'cid' => $jqshow->course,
        ];
        $this->assertEquals((new moodle_url('/mod/jqshow/preview.php', $args))->out(false),
            $data['sessionquestions'][0]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/jqshow/editquestion.php', $args))->out(false),
            $data['sessionquestions'][0]->{'editquestionurl'});

        // Question 2.
        $this->assertIsObject($data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('jqshowid', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('questionnid', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('position', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('name', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('type', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('isvalid', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('time', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('version', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('managesessions', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('question_preview_url', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('editquestionurl', $data['sessionquestions'][1]);
        $this->assertEquals($createdsid, $data['sessionquestions'][1]->{'sid'});
        $this->assertEquals($jqshow->cmid, $data['sessionquestions'][1]->{'cmid'});
        $this->assertEquals($jqshow->id, $data['sessionquestions'][1]->{'jqshowid'});
        $qbs = $DB->get_record('question', ['id' => $nq->id], '*', MUST_EXIST);
        $jnq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::NUMERICAL]);
        $this->assertEquals($jnq->get('id'), $data['sessionquestions'][1]->{'questionnid'});
        $this->assertEquals(2, $data['sessionquestions'][1]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][1]->{'name'});
        $this->assertEquals(questions::NUMERICAL, $data['sessionquestions'][1]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][1]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][1]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][1]->{'managesessions'});
        $args = [
            'id' => $jqshow->cmid,
            'jqid' => $jnq->get('id'),
            'sid' => $createdsid,
            'jqsid' => $jqshow->id,
            'cid' => $jqshow->course,
        ];
        $this->assertEquals((new moodle_url('/mod/jqshow/preview.php', $args))->out(false),
            $data['sessionquestions'][1]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/jqshow/editquestion.php', $args))->out(false),
            $data['sessionquestions'][1]->{'editquestionurl'});

        // Question 3.
        $this->assertIsObject($data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('jqshowid', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('questionnid', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('position', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('name', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('type', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('isvalid', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('time', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('version', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('managesessions', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('question_preview_url', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('editquestionurl', $data['sessionquestions'][2]);
        $this->assertEquals($createdsid, $data['sessionquestions'][2]->{'sid'});
        $this->assertEquals($jqshow->cmid, $data['sessionquestions'][2]->{'cmid'});
        $this->assertEquals($jqshow->id, $data['sessionquestions'][2]->{'jqshowid'});
        $qbs = $DB->get_record('question', ['id' => $tfq->id], '*', MUST_EXIST);
        $jtfq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::TRUE_FALSE]);
        $this->assertEquals($jtfq->get('id'), $data['sessionquestions'][2]->{'questionnid'});
        $this->assertEquals(3, $data['sessionquestions'][2]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][2]->{'name'});
        $this->assertEquals(questions::TRUE_FALSE, $data['sessionquestions'][2]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][2]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][2]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][2]->{'managesessions'});
        $args = [
            'id' => $jqshow->cmid,
            'jqid' => $jtfq->get('id'),
            'sid' => $createdsid,
            'jqsid' => $jqshow->id,
            'cid' => $jqshow->course,
        ];
        $this->assertEquals((new moodle_url('/mod/jqshow/preview.php', $args))->out(false),
            $data['sessionquestions'][2]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/jqshow/editquestion.php', $args))->out(false),
            $data['sessionquestions'][2]->{'editquestionurl'});

        // Question 4.
        $this->assertIsObject($data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('jqshowid', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('questionnid', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('position', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('name', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('type', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('isvalid', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('time', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('version', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('managesessions', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('question_preview_url', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('editquestionurl', $data['sessionquestions'][3]);
        $this->assertEquals($createdsid, $data['sessionquestions'][3]->{'sid'});
        $this->assertEquals($jqshow->cmid, $data['sessionquestions'][3]->{'cmid'});
        $this->assertEquals($jqshow->id, $data['sessionquestions'][3]->{'jqshowid'});
        $qbs = $DB->get_record('question', ['id' => $mcq->id], '*', MUST_EXIST);
        $jmcq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::MULTICHOICE]);
        $this->assertEquals($jmcq->get('id'), $data['sessionquestions'][3]->{'questionnid'});
        $this->assertEquals(4, $data['sessionquestions'][3]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][3]->{'name'});
        $this->assertEquals(questions::MULTICHOICE, $data['sessionquestions'][3]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][3]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][3]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][3]->{'managesessions'});
        $args = [
            'id' => $jqshow->cmid,
            'jqid' => $jmcq->get('id'),
            'sid' => $createdsid,
            'jqsid' => $jqshow->id,
            'cid' => $jqshow->course,
        ];
        $this->assertEquals((new moodle_url('/mod/jqshow/preview.php', $args))->out(false),
            $data['sessionquestions'][3]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/jqshow/editquestion.php', $args))->out(false),
            $data['sessionquestions'][3]->{'editquestionurl'});

        // Question 5.
        $this->assertIsObject($data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('jqshowid', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('questionnid', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('position', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('name', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('type', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('isvalid', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('time', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('version', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('managesessions', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('question_preview_url', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('editquestionurl', $data['sessionquestions'][4]);
        $this->assertEquals($createdsid, $data['sessionquestions'][4]->{'sid'});
        $this->assertEquals($jqshow->cmid, $data['sessionquestions'][4]->{'cmid'});
        $this->assertEquals($jqshow->id, $data['sessionquestions'][4]->{'jqshowid'});
        $qbs = $DB->get_record('question', ['id' => $ddwtosq->id], '*', MUST_EXIST);
        $jsddwtosq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DDWTOS]);
        $this->assertEquals($jsddwtosq->get('id'), $data['sessionquestions'][4]->{'questionnid'});
        $this->assertEquals(5, $data['sessionquestions'][4]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][4]->{'name'});
        $this->assertEquals(questions::DDWTOS, $data['sessionquestions'][4]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][4]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][4]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][4]->{'managesessions'});
        $args = [
            'id' => $jqshow->cmid,
            'jqid' => $jsddwtosq->get('id'),
            'sid' => $createdsid,
            'jqsid' => $jqshow->id,
            'cid' => $jqshow->course,
        ];
        $this->assertEquals((new moodle_url('/mod/jqshow/preview.php', $args))->out(false),
            $data['sessionquestions'][4]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/jqshow/editquestion.php', $args))->out(false),
            $data['sessionquestions'][4]->{'editquestionurl'});

        // Question 6.
        $this->assertIsObject($data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('jqshowid', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('questionnid', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('position', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('name', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('type', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('isvalid', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('time', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('version', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('managesessions', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('question_preview_url', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('editquestionurl', $data['sessionquestions'][5]);
        $this->assertEquals($createdsid, $data['sessionquestions'][5]->{'sid'});
        $this->assertEquals($jqshow->cmid, $data['sessionquestions'][5]->{'cmid'});
        $this->assertEquals($jqshow->id, $data['sessionquestions'][5]->{'jqshowid'});
        $qbs = $DB->get_record('question', ['id' => $dq->id], '*', MUST_EXIST);
        $jdq = \mod_jqshow\persistents\jqshow_questions::get_record(
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'jqshowid' => $jqshow->id, 'qtype' => questions::DESCRIPTION]);
        $this->assertEquals($jdq->get('id'), $data['sessionquestions'][5]->{'questionnid'});
        $this->assertEquals(6, $data['sessionquestions'][5]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][5]->{'name'});
        $this->assertEquals(questions::DESCRIPTION, $data['sessionquestions'][5]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][5]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][5]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][5]->{'managesessions'});
        $args = [
            'id' => $jqshow->cmid,
            'jqid' => $jdq->get('id'),
            'sid' => $createdsid,
            'jqsid' => $jqshow->id,
            'cid' => $jqshow->course,
        ];
        $this->assertEquals((new moodle_url('/mod/jqshow/preview.php', $args))->out(false),
            $data['sessionquestions'][5]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/jqshow/editquestion.php', $args))->out(false),
            $data['sessionquestions'][5]->{'editquestionurl'});


    }
}
