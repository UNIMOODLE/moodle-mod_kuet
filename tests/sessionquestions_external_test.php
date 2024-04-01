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
use mod_kuet\models\questions;
use mod_kuet\models\sessions;

/**
 * Session questions test
 *
 * @package     mod_kuet
 * @author      3&Punt <tresipunt.com>
 * @author      2023 Tomás Zafra <jmtomas@tresipunt.com> | Elena Barrios <elena@tresipunt.com>
 * @category   test
 * @copyright   3iPunt <https://www.tresipunt.com/>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Session questions test class
 */
class sessionquestions_external_test extends advanced_testcase {
    /**
     * Session questions test
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function test_sessionquestions() {
        global $DB;
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');

        // Only a user with capability can add questions.
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);
        // Create session.
        $sessionmock = [
            'name' => 'Session Test',
            'kuetid' => $kuet->id,
            'anonymousanswer' => 0,
            'sessionmode' => \mod_kuet\models\sessions::PODIUM_MANUAL,
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
            'status' => \mod_kuet\models\sessions::SESSION_ACTIVE,
            'sessionid' => 0,
            'submitbutton' => 0,
        ];
        $createdsid = $generator->create_session($kuet, (object) $sessionmock);

        // Create questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question(questions::SHORTANSWER, null, ['category' => $cat->id]);
        $nq = $questiongenerator->create_question(questions::NUMERICAL, null, ['category' => $cat->id]);
        $tfq = $questiongenerator->create_question(questions::TRUE_FALSE, null, ['category' => $cat->id]);
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, ['category' => $cat->id]);
        $ddwtosq = $questiongenerator->create_question(questions::DDWTOS, null, ['category' => $cat->id]);
        $dq = $questiongenerator->create_question(questions::DESCRIPTION, null, ['category' => $cat->id]);

        // Add questions to a session.
        $questions = [
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL],
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE],
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DDWTOS],
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DESCRIPTION],
        ];
        $generator->add_questions_to_session($questions);
        $data = \mod_kuet\external\sessionquestions_external::sessionquestions($kuet->id, $kuet->cmid, $createdsid);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('kuetid', $data);
        $this->assertArrayHasKey('cmid', $data);
        $this->assertArrayHasKey('sid', $data);
        $this->assertArrayHasKey('sessionquestions', $data);
        $this->assertEquals($kuet->id, $data['kuetid']);
        $this->assertEquals($kuet->cmid, $data['cmid']);
        $this->assertEquals($createdsid, $data['sid']);
        $this->assertIsArray($data['sessionquestions']);
        // Question 1.
        $this->assertIsObject($data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][0]);
        $this->assertObjectHasAttribute('kuetid', $data['sessionquestions'][0]);
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
        $this->assertEquals($kuet->cmid, $data['sessionquestions'][0]->{'cmid'});
        $this->assertEquals($kuet->id, $data['sessionquestions'][0]->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $saq->id], '*', MUST_EXIST);
        $jsaq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::SHORTANSWER]);
        $this->assertEquals($jsaq->get('id'), $data['sessionquestions'][0]->{'questionnid'});
        $this->assertEquals(1, $data['sessionquestions'][0]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][0]->{'name'});
        $this->assertEquals(questions::SHORTANSWER, $data['sessionquestions'][0]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][0]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][0]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][0]->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jsaq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $data['sessionquestions'][0]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $data['sessionquestions'][0]->{'editquestionurl'});

        // Question 2.
        $this->assertIsObject($data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][1]);
        $this->assertObjectHasAttribute('kuetid', $data['sessionquestions'][1]);
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
        $this->assertEquals($kuet->cmid, $data['sessionquestions'][1]->{'cmid'});
        $this->assertEquals($kuet->id, $data['sessionquestions'][1]->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $nq->id], '*', MUST_EXIST);
        $jnq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL]);
        $this->assertEquals($jnq->get('id'), $data['sessionquestions'][1]->{'questionnid'});
        $this->assertEquals(2, $data['sessionquestions'][1]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][1]->{'name'});
        $this->assertEquals(questions::NUMERICAL, $data['sessionquestions'][1]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][1]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][1]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][1]->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jnq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $data['sessionquestions'][1]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $data['sessionquestions'][1]->{'editquestionurl'});

        // Question 3.
        $this->assertIsObject($data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][2]);
        $this->assertObjectHasAttribute('kuetid', $data['sessionquestions'][2]);
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
        $this->assertEquals($kuet->cmid, $data['sessionquestions'][2]->{'cmid'});
        $this->assertEquals($kuet->id, $data['sessionquestions'][2]->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $tfq->id], '*', MUST_EXIST);
        $jtfq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE]);
        $this->assertEquals($jtfq->get('id'), $data['sessionquestions'][2]->{'questionnid'});
        $this->assertEquals(3, $data['sessionquestions'][2]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][2]->{'name'});
        $this->assertEquals(questions::TRUE_FALSE, $data['sessionquestions'][2]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][2]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][2]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][2]->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jtfq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $data['sessionquestions'][2]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $data['sessionquestions'][2]->{'editquestionurl'});

        // Question 4.
        $this->assertIsObject($data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][3]);
        $this->assertObjectHasAttribute('kuetid', $data['sessionquestions'][3]);
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
        $this->assertEquals($kuet->cmid, $data['sessionquestions'][3]->{'cmid'});
        $this->assertEquals($kuet->id, $data['sessionquestions'][3]->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $mcq->id], '*', MUST_EXIST);
        $jmcq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]);
        $this->assertEquals($jmcq->get('id'), $data['sessionquestions'][3]->{'questionnid'});
        $this->assertEquals(4, $data['sessionquestions'][3]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][3]->{'name'});
        $this->assertEquals(questions::MULTICHOICE, $data['sessionquestions'][3]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][3]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][3]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][3]->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jmcq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $data['sessionquestions'][3]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $data['sessionquestions'][3]->{'editquestionurl'});

        // Question 5.
        $this->assertIsObject($data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][4]);
        $this->assertObjectHasAttribute('kuetid', $data['sessionquestions'][4]);
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
        $this->assertEquals($kuet->cmid, $data['sessionquestions'][4]->{'cmid'});
        $this->assertEquals($kuet->id, $data['sessionquestions'][4]->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $ddwtosq->id], '*', MUST_EXIST);
        $jsddwtosq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DDWTOS]);
        $this->assertEquals($jsddwtosq->get('id'), $data['sessionquestions'][4]->{'questionnid'});
        $this->assertEquals(5, $data['sessionquestions'][4]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][4]->{'name'});
        $this->assertEquals(questions::DDWTOS, $data['sessionquestions'][4]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][4]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][4]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][4]->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jsddwtosq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $data['sessionquestions'][4]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $data['sessionquestions'][4]->{'editquestionurl'});

        // Question 6.
        $this->assertIsObject($data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('sid', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('cmid', $data['sessionquestions'][5]);
        $this->assertObjectHasAttribute('kuetid', $data['sessionquestions'][5]);
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
        $this->assertEquals($kuet->cmid, $data['sessionquestions'][5]->{'cmid'});
        $this->assertEquals($kuet->id, $data['sessionquestions'][5]->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $dq->id], '*', MUST_EXIST);
        $jdq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DESCRIPTION]);
        $this->assertEquals($jdq->get('id'), $data['sessionquestions'][5]->{'questionnid'});
        $this->assertEquals(6, $data['sessionquestions'][5]->{'position'});
        $this->assertEquals($qbs->name, $data['sessionquestions'][5]->{'name'});
        $this->assertEquals(questions::DESCRIPTION, $data['sessionquestions'][5]->{'type'});
        $this->assertEquals(0, $data['sessionquestions'][5]->{'isvalid'});
        $this->assertEquals('10s', $data['sessionquestions'][5]->{'time'});
        $this->assertEquals(true, $data['sessionquestions'][5]->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jdq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $data['sessionquestions'][5]->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $data['sessionquestions'][5]->{'editquestionurl'});
    }

    /**
     * Export question test
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_export_question() {
        global $DB;
        $this->resetAfterTest(true);
        $course = self::getDataGenerator()->create_course();
        $kuet = self::getDataGenerator()->create_module('kuet', ['course' => $course->id]);
        $this->sessionmock['kuetid'] = $kuet->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');

        // Only a user with capability can add questions.
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'teacher');
        self::setUser($teacher);
        // Create session.
        $sessionmock = [
            'name' => 'Session Test',
            'kuetid' => $kuet->id,
            'anonymousanswer' => 0,
            'sessionmode' => \mod_kuet\models\sessions::PODIUM_MANUAL,
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
            'status' => \mod_kuet\models\sessions::SESSION_ACTIVE,
            'sessionid' => 0,
            'submitbutton' => 0,
        ];
        $createdsid = $generator->create_session($kuet, (object) $sessionmock);

        // Create questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question(questions::SHORTANSWER, null, ['category' => $cat->id]);
        $nq = $questiongenerator->create_question(questions::NUMERICAL, null, ['category' => $cat->id]);
        $tfq = $questiongenerator->create_question(questions::TRUE_FALSE, null, ['category' => $cat->id]);
        $mcq = $questiongenerator->create_question(questions::MULTICHOICE, null, ['category' => $cat->id]);
        $ddwtosq = $questiongenerator->create_question(questions::DDWTOS, null, ['category' => $cat->id]);
        $dq = $questiongenerator->create_question(questions::DESCRIPTION, null, ['category' => $cat->id]);

        // Add questions to a session.
        $questions = [
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::SHORTANSWER],
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL],
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE],
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE],
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DDWTOS],
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DESCRIPTION],
        ];
        $generator->add_questions_to_session($questions);

        // Question 1.
        $jsaq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::SHORTANSWER]);
        $datasaq = \mod_kuet\external\sessionquestions_external::export_question($jsaq, $kuet->cmid);
        $this->assertIsObject($datasaq);
        $this->assertObjectHasAttribute('sid', $datasaq);
        $this->assertObjectHasAttribute('cmid', $datasaq);
        $this->assertObjectHasAttribute('kuetid', $datasaq);
        $this->assertObjectHasAttribute('questionnid', $datasaq);
        $this->assertObjectHasAttribute('position', $datasaq);
        $this->assertObjectHasAttribute('name', $datasaq);
        $this->assertObjectHasAttribute('type', $datasaq);
        $this->assertObjectHasAttribute('isvalid', $datasaq);
        $this->assertObjectHasAttribute('time', $datasaq);
        $this->assertObjectHasAttribute('version', $datasaq);
        $this->assertObjectHasAttribute('managesessions', $datasaq);
        $this->assertObjectHasAttribute('question_preview_url', $datasaq);
        $this->assertObjectHasAttribute('editquestionurl', $datasaq);
        $this->assertEquals($createdsid, $datasaq->{'sid'});
        $this->assertEquals($kuet->cmid, $datasaq->{'cmid'});
        $this->assertEquals($kuet->id, $datasaq->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $saq->id], '*', MUST_EXIST);
        $jsaq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $saq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::SHORTANSWER]);
        $this->assertEquals($jsaq->get('id'), $datasaq->{'questionnid'});
        $this->assertEquals(1, $datasaq->{'position'});
        $this->assertEquals($qbs->name, $datasaq->{'name'});
        $this->assertEquals(questions::SHORTANSWER, $datasaq->{'type'});
        $this->assertEquals(0, $datasaq->{'isvalid'});
        $this->assertEquals('10s', $datasaq->{'time'});
        $this->assertEquals(true, $datasaq->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jsaq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $datasaq->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $datasaq->{'editquestionurl'});

        // Question 2.
        $jnq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL]);
        $datanq = \mod_kuet\external\sessionquestions_external::export_question($jnq, $kuet->cmid);
        $this->assertIsObject($datanq);
        $this->assertObjectHasAttribute('sid', $datanq);
        $this->assertObjectHasAttribute('cmid', $datanq);
        $this->assertObjectHasAttribute('kuetid', $datanq);
        $this->assertObjectHasAttribute('questionnid', $datanq);
        $this->assertObjectHasAttribute('position', $datanq);
        $this->assertObjectHasAttribute('name', $datanq);
        $this->assertObjectHasAttribute('type', $datanq);
        $this->assertObjectHasAttribute('isvalid', $datanq);
        $this->assertObjectHasAttribute('time', $datanq);
        $this->assertObjectHasAttribute('version', $datanq);
        $this->assertObjectHasAttribute('managesessions', $datanq);
        $this->assertObjectHasAttribute('question_preview_url', $datanq);
        $this->assertObjectHasAttribute('editquestionurl', $datanq);
        $this->assertEquals($createdsid, $datanq->{'sid'});
        $this->assertEquals($kuet->cmid, $datanq->{'cmid'});
        $this->assertEquals($kuet->id, $datanq->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $nq->id], '*', MUST_EXIST);
        $jnq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $nq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::NUMERICAL]);
        $this->assertEquals($jnq->get('id'), $datanq->{'questionnid'});
        $this->assertEquals(2, $datanq->{'position'});
        $this->assertEquals($qbs->name, $datanq->{'name'});
        $this->assertEquals(questions::NUMERICAL, $datanq->{'type'});
        $this->assertEquals(0, $datanq->{'isvalid'});
        $this->assertEquals('10s', $datanq->{'time'});
        $this->assertEquals(true, $datanq->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jnq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $datanq->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $datanq->{'editquestionurl'});

        // Question 3.
        $jtfq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE]);
        $datatfq = \mod_kuet\external\sessionquestions_external::export_question($jtfq, $kuet->cmid);
        $this->assertIsObject($datatfq);
        $this->assertObjectHasAttribute('sid', $datatfq);
        $this->assertObjectHasAttribute('cmid', $datatfq);
        $this->assertObjectHasAttribute('kuetid', $datatfq);
        $this->assertObjectHasAttribute('questionnid', $datatfq);
        $this->assertObjectHasAttribute('position', $datatfq);
        $this->assertObjectHasAttribute('name', $datatfq);
        $this->assertObjectHasAttribute('type', $datatfq);
        $this->assertObjectHasAttribute('isvalid', $datatfq);
        $this->assertObjectHasAttribute('time', $datatfq);
        $this->assertObjectHasAttribute('version', $datatfq);
        $this->assertObjectHasAttribute('managesessions', $datatfq);
        $this->assertObjectHasAttribute('question_preview_url', $datatfq);
        $this->assertObjectHasAttribute('editquestionurl', $datatfq);
        $this->assertEquals($createdsid, $datatfq->{'sid'});
        $this->assertEquals($kuet->cmid, $datatfq->{'cmid'});
        $this->assertEquals($kuet->id, $datatfq->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $tfq->id], '*', MUST_EXIST);
        $jtfq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $tfq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::TRUE_FALSE]);
        $this->assertEquals($jtfq->get('id'), $datatfq->{'questionnid'});
        $this->assertEquals(3, $datatfq->{'position'});
        $this->assertEquals($qbs->name, $datatfq->{'name'});
        $this->assertEquals(questions::TRUE_FALSE, $datatfq->{'type'});
        $this->assertEquals(0, $datatfq->{'isvalid'});
        $this->assertEquals('10s', $datatfq->{'time'});
        $this->assertEquals(true, $datatfq->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jtfq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $datatfq->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $datatfq->{'editquestionurl'});

        // Question 4.
        $jmcq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]);
        $datamcq = \mod_kuet\external\sessionquestions_external::export_question($jmcq, $kuet->cmid);
        $this->assertIsObject($datamcq);
        $this->assertObjectHasAttribute('sid', $datamcq);
        $this->assertObjectHasAttribute('cmid', $datamcq);
        $this->assertObjectHasAttribute('kuetid', $datamcq);
        $this->assertObjectHasAttribute('questionnid', $datamcq);
        $this->assertObjectHasAttribute('position', $datamcq);
        $this->assertObjectHasAttribute('name', $datamcq);
        $this->assertObjectHasAttribute('type', $datamcq);
        $this->assertObjectHasAttribute('isvalid', $datamcq);
        $this->assertObjectHasAttribute('time', $datamcq);
        $this->assertObjectHasAttribute('version', $datamcq);
        $this->assertObjectHasAttribute('managesessions', $datamcq);
        $this->assertObjectHasAttribute('question_preview_url', $datamcq);
        $this->assertObjectHasAttribute('editquestionurl', $datamcq);
        $this->assertEquals($createdsid, $datamcq->{'sid'});
        $this->assertEquals($kuet->cmid, $datamcq->{'cmid'});
        $this->assertEquals($kuet->id, $datamcq->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $mcq->id], '*', MUST_EXIST);
        $jmcq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $mcq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::MULTICHOICE]);
        $this->assertEquals($jmcq->get('id'), $datamcq->{'questionnid'});
        $this->assertEquals(4, $datamcq->{'position'});
        $this->assertEquals($qbs->name, $datamcq->{'name'});
        $this->assertEquals(questions::MULTICHOICE, $datamcq->{'type'});
        $this->assertEquals(0, $datamcq->{'isvalid'});
        $this->assertEquals('10s', $datamcq->{'time'});
        $this->assertEquals(true, $datamcq->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jmcq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $datamcq->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $datamcq->{'editquestionurl'});

        // Question 5.
        $jddwtosq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DDWTOS]);
        $dataddwtosq = \mod_kuet\external\sessionquestions_external::export_question($jddwtosq, $kuet->cmid);
        $this->assertIsObject($dataddwtosq);
        $this->assertObjectHasAttribute('sid', $dataddwtosq);
        $this->assertObjectHasAttribute('cmid', $dataddwtosq);
        $this->assertObjectHasAttribute('kuetid', $dataddwtosq);
        $this->assertObjectHasAttribute('questionnid', $dataddwtosq);
        $this->assertObjectHasAttribute('position', $dataddwtosq);
        $this->assertObjectHasAttribute('name', $dataddwtosq);
        $this->assertObjectHasAttribute('type', $dataddwtosq);
        $this->assertObjectHasAttribute('isvalid', $dataddwtosq);
        $this->assertObjectHasAttribute('time', $dataddwtosq);
        $this->assertObjectHasAttribute('version', $dataddwtosq);
        $this->assertObjectHasAttribute('managesessions', $dataddwtosq);
        $this->assertObjectHasAttribute('question_preview_url', $dataddwtosq);
        $this->assertObjectHasAttribute('editquestionurl', $dataddwtosq);
        $this->assertEquals($createdsid, $dataddwtosq->{'sid'});
        $this->assertEquals($kuet->cmid, $dataddwtosq->{'cmid'});
        $this->assertEquals($kuet->id, $dataddwtosq->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $ddwtosq->id], '*', MUST_EXIST);
        $jsddwtosq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $ddwtosq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DDWTOS]);
        $this->assertEquals($jsddwtosq->get('id'), $dataddwtosq->{'questionnid'});
        $this->assertEquals(5, $dataddwtosq->{'position'});
        $this->assertEquals($qbs->name, $dataddwtosq->{'name'});
        $this->assertEquals(questions::DDWTOS, $dataddwtosq->{'type'});
        $this->assertEquals(0, $dataddwtosq->{'isvalid'});
        $this->assertEquals('10s', $dataddwtosq->{'time'});
        $this->assertEquals(true, $dataddwtosq->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jsddwtosq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $dataddwtosq->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $dataddwtosq->{'editquestionurl'});

        // Question 6.
        $jdq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DESCRIPTION]);
        $datadq = \mod_kuet\external\sessionquestions_external::export_question($jdq, $kuet->cmid);
        $this->assertIsObject($datadq);
        $this->assertObjectHasAttribute('sid', $datadq);
        $this->assertObjectHasAttribute('cmid', $datadq);
        $this->assertObjectHasAttribute('kuetid', $datadq);
        $this->assertObjectHasAttribute('questionnid', $datadq);
        $this->assertObjectHasAttribute('position', $datadq);
        $this->assertObjectHasAttribute('name', $datadq);
        $this->assertObjectHasAttribute('type', $datadq);
        $this->assertObjectHasAttribute('isvalid', $datadq);
        $this->assertObjectHasAttribute('time', $datadq);
        $this->assertObjectHasAttribute('version', $datadq);
        $this->assertObjectHasAttribute('managesessions', $datadq);
        $this->assertObjectHasAttribute('question_preview_url', $datadq);
        $this->assertObjectHasAttribute('editquestionurl', $datadq);
        $this->assertEquals($createdsid, $datadq->{'sid'});
        $this->assertEquals($kuet->cmid, $datadq->{'cmid'});
        $this->assertEquals($kuet->id, $datadq->{'kuetid'});
        $qbs = $DB->get_record('question', ['id' => $dq->id], '*', MUST_EXIST);
        $jdq = \mod_kuet\persistents\kuet_questions::get_record(
            ['questionid' => $dq->id, 'sessionid' => $createdsid, 'kuetid' => $kuet->id, 'qtype' => questions::DESCRIPTION]);
        $this->assertEquals($jdq->get('id'), $datadq->{'questionnid'});
        $this->assertEquals(6, $datadq->{'position'});
        $this->assertEquals($qbs->name, $datadq->{'name'});
        $this->assertEquals(questions::DESCRIPTION, $datadq->{'type'});
        $this->assertEquals(0, $datadq->{'isvalid'});
        $this->assertEquals('10s', $datadq->{'time'});
        $this->assertEquals(true, $datadq->{'managesessions'});
        $args = [
            'id' => $kuet->cmid,
            'kid' => $jdq->get('id'),
            'sid' => $createdsid,
            'ksid' => $kuet->id,
            'cid' => $kuet->course,
        ];
        $this->assertEquals((new moodle_url('/mod/kuet/preview.php', $args))->out(false),
            $datadq->{'question_preview_url'});
        $this->assertEquals((new moodle_url('/mod/kuet/editquestion.php', $args))->out(false),
            $datadq->{'editquestionurl'});
    }
}
