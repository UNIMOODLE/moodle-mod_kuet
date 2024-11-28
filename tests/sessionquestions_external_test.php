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
        $this->assertObjectHasProperty('sid', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('cmid', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('kuetid', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('questionnid', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('position', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('name', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('type', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('isvalid', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('time', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('version', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('managesessions', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('question_preview_url', $data['sessionquestions'][0]);
        $this->assertObjectHasProperty('editquestionurl', $data['sessionquestions'][0]);
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
        $this->assertObjectHasProperty('sid', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('cmid', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('kuetid', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('questionnid', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('position', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('name', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('type', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('isvalid', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('time', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('version', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('managesessions', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('question_preview_url', $data['sessionquestions'][1]);
        $this->assertObjectHasProperty('editquestionurl', $data['sessionquestions'][1]);
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
        $this->assertObjectHasProperty('sid', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('cmid', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('kuetid', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('questionnid', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('position', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('name', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('type', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('isvalid', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('time', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('version', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('managesessions', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('question_preview_url', $data['sessionquestions'][2]);
        $this->assertObjectHasProperty('editquestionurl', $data['sessionquestions'][2]);
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
        $this->assertObjectHasProperty('sid', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('cmid', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('kuetid', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('questionnid', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('position', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('name', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('type', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('isvalid', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('time', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('version', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('managesessions', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('question_preview_url', $data['sessionquestions'][3]);
        $this->assertObjectHasProperty('editquestionurl', $data['sessionquestions'][3]);
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
        $this->assertObjectHasProperty('sid', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('cmid', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('kuetid', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('questionnid', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('position', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('name', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('type', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('isvalid', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('time', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('version', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('managesessions', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('question_preview_url', $data['sessionquestions'][4]);
        $this->assertObjectHasProperty('editquestionurl', $data['sessionquestions'][4]);
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
        $this->assertObjectHasProperty('sid', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('cmid', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('kuetid', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('questionnid', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('position', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('name', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('type', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('isvalid', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('time', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('version', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('managesessions', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('question_preview_url', $data['sessionquestions'][5]);
        $this->assertObjectHasProperty('editquestionurl', $data['sessionquestions'][5]);
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
        $this->assertObjectHasProperty('sid', $datasaq);
        $this->assertObjectHasProperty('cmid', $datasaq);
        $this->assertObjectHasProperty('kuetid', $datasaq);
        $this->assertObjectHasProperty('questionnid', $datasaq);
        $this->assertObjectHasProperty('position', $datasaq);
        $this->assertObjectHasProperty('name', $datasaq);
        $this->assertObjectHasProperty('type', $datasaq);
        $this->assertObjectHasProperty('isvalid', $datasaq);
        $this->assertObjectHasProperty('time', $datasaq);
        $this->assertObjectHasProperty('version', $datasaq);
        $this->assertObjectHasProperty('managesessions', $datasaq);
        $this->assertObjectHasProperty('question_preview_url', $datasaq);
        $this->assertObjectHasProperty('editquestionurl', $datasaq);
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
        $this->assertObjectHasProperty('sid', $datanq);
        $this->assertObjectHasProperty('cmid', $datanq);
        $this->assertObjectHasProperty('kuetid', $datanq);
        $this->assertObjectHasProperty('questionnid', $datanq);
        $this->assertObjectHasProperty('position', $datanq);
        $this->assertObjectHasProperty('name', $datanq);
        $this->assertObjectHasProperty('type', $datanq);
        $this->assertObjectHasProperty('isvalid', $datanq);
        $this->assertObjectHasProperty('time', $datanq);
        $this->assertObjectHasProperty('version', $datanq);
        $this->assertObjectHasProperty('managesessions', $datanq);
        $this->assertObjectHasProperty('question_preview_url', $datanq);
        $this->assertObjectHasProperty('editquestionurl', $datanq);
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
        $this->assertObjectHasProperty('sid', $datatfq);
        $this->assertObjectHasProperty('cmid', $datatfq);
        $this->assertObjectHasProperty('kuetid', $datatfq);
        $this->assertObjectHasProperty('questionnid', $datatfq);
        $this->assertObjectHasProperty('position', $datatfq);
        $this->assertObjectHasProperty('name', $datatfq);
        $this->assertObjectHasProperty('type', $datatfq);
        $this->assertObjectHasProperty('isvalid', $datatfq);
        $this->assertObjectHasProperty('time', $datatfq);
        $this->assertObjectHasProperty('version', $datatfq);
        $this->assertObjectHasProperty('managesessions', $datatfq);
        $this->assertObjectHasProperty('question_preview_url', $datatfq);
        $this->assertObjectHasProperty('editquestionurl', $datatfq);
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
        $this->assertObjectHasProperty('sid', $datamcq);
        $this->assertObjectHasProperty('cmid', $datamcq);
        $this->assertObjectHasProperty('kuetid', $datamcq);
        $this->assertObjectHasProperty('questionnid', $datamcq);
        $this->assertObjectHasProperty('position', $datamcq);
        $this->assertObjectHasProperty('name', $datamcq);
        $this->assertObjectHasProperty('type', $datamcq);
        $this->assertObjectHasProperty('isvalid', $datamcq);
        $this->assertObjectHasProperty('time', $datamcq);
        $this->assertObjectHasProperty('version', $datamcq);
        $this->assertObjectHasProperty('managesessions', $datamcq);
        $this->assertObjectHasProperty('question_preview_url', $datamcq);
        $this->assertObjectHasProperty('editquestionurl', $datamcq);
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
        $this->assertObjectHasProperty('sid', $dataddwtosq);
        $this->assertObjectHasProperty('cmid', $dataddwtosq);
        $this->assertObjectHasProperty('kuetid', $dataddwtosq);
        $this->assertObjectHasProperty('questionnid', $dataddwtosq);
        $this->assertObjectHasProperty('position', $dataddwtosq);
        $this->assertObjectHasProperty('name', $dataddwtosq);
        $this->assertObjectHasProperty('type', $dataddwtosq);
        $this->assertObjectHasProperty('isvalid', $dataddwtosq);
        $this->assertObjectHasProperty('time', $dataddwtosq);
        $this->assertObjectHasProperty('version', $dataddwtosq);
        $this->assertObjectHasProperty('managesessions', $dataddwtosq);
        $this->assertObjectHasProperty('question_preview_url', $dataddwtosq);
        $this->assertObjectHasProperty('editquestionurl', $dataddwtosq);
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
        $this->assertObjectHasProperty('sid', $datadq);
        $this->assertObjectHasProperty('cmid', $datadq);
        $this->assertObjectHasProperty('kuetid', $datadq);
        $this->assertObjectHasProperty('questionnid', $datadq);
        $this->assertObjectHasProperty('position', $datadq);
        $this->assertObjectHasProperty('name', $datadq);
        $this->assertObjectHasProperty('type', $datadq);
        $this->assertObjectHasProperty('isvalid', $datadq);
        $this->assertObjectHasProperty('time', $datadq);
        $this->assertObjectHasProperty('version', $datadq);
        $this->assertObjectHasProperty('managesessions', $datadq);
        $this->assertObjectHasProperty('question_preview_url', $datadq);
        $this->assertObjectHasProperty('editquestionurl', $datadq);
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
