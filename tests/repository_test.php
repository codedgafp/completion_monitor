<?php

use block_completion_monitor\repository\completion_monitor_repository;

defined('MOODLE_INTERNAL');

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/fixtures/testable_assign.php');

class block_completion_monitor_repository_testcase extends advanced_testcase
{
    /**
     * Activities Completion Course Monitoring repository
     * @var completion_monitor_repository
     */
    private completion_monitor_repository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetAfterTest(true);
        set_config('enablecompletion', 1);

        $this->repository = new completion_monitor_repository();
    }

    /**
     * Submit an assignment.
     * Pinched from mod/assign/tests/generator.php and modified.
     *
     * @param \stdClass $student
     * @param assign $assign
     */
    private function submit_for_student(\stdClass $student, assign $assign): void
    {
        $this->setUser($student);

        $sink = $this->redirectMessages();

        $assign->save_submission((object) [
            'userid' => $student->id,
            'onlinetext_editor' => [
                'itemid' => file_get_unused_draft_itemid(),
                'text' => 'Text',
                'format' => FORMAT_HTML,
            ],
        ], $notices);

        $assign->submit_for_grading((object) [
            'userid' => $student->id,
        ], []);

        $sink->close();
    }

    /**
     * Award a grade to a submission.
     * Pinched from mod/assign/tests/generator.php and modified.
     *
     * @param \stdClass $student
     * @param assign $assign
     * @param \stdClass $teacher
     * @param int $grade
     * @param int $attempt
     */
    private function grade_student(\stdClass $student, assign $assign, \stdClass $teacher, int $grade, int $attempt): void
    {
        global $DB;

        $this->setUser($teacher);

        $sql = "UPDATE {assign_submission}
                SET timecreated = timecreated - 1, timemodified = timemodified - 1
                WHERE userid = :userid
                ";
        $params['userid'] = $student->id;

        try {
            $DB->execute($sql, $params);
        } catch (\dml_exception $e) {
            self::fail($e->getMessage());
        }

        $assign->testable_apply_grade_to_user((object) ['grade' => $grade], $student->id, $attempt);
    }

    public function test_grade_exclusions_ok()
    {
        self::setAdminUser();

        $coursedata = new \stdClass();
        $coursedata->category = 1;
        $coursedata->shortname = 'Course1';
        $coursedata->fullname = 'Course1';

        $course = $this->getDataGenerator()->create_course($coursedata);

        self::assertCount(0, $this->repository->get_grade_exclusions($course->id));
    }

    public function test_get_user_course_submissions()
    {
        self::setAdminUser();

        // Create course.
        $courserecord = new stdClass();
        $courserecord->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course($courserecord);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'participant');

        $record = new stdClass();
        $record->course = $course;
        $this->getDataGenerator()->create_module('forum', $record);

        $usersubmissions = $this->repository->get_user_course_submissions($course->id, $student->id);
        self::assertCount(0, $usersubmissions);

        // Create a mod with completion enabled.
        $instance = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
            'maxattempts' => -1,
            'attemptreopenmethod' => 'untilpass',
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,      // The student must receive a grade to complete.
            'completionexpected' => time() - DAYSECS,
            'teamsubmission' => 0,
        ]);
        $cm = get_coursemodule_from_id('assign', $instance->cmid);

        // Set the passing grade.
        $item = \grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $instance->id,
            'outcomeid' => null,
        ]);
        $item->gradepass = 50;
        $item->update();

        $assign = new \mod_assign_testable_assign(\context_module::instance($cm->id), $cm, $course);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'formateur');

        // Student 1 submits to the activity and gets graded correct.
        $this->submit_for_student($student, $assign);
        $this->grade_student($student, $assign, $teacher, 75, 0);

        // User has assign submission.
        $usersubmissions = $this->repository->get_user_course_submissions($course->id, $student->id);
        self::assertCount(1, $usersubmissions);

        self::resetAllData();
    }

    /**
     * Test get_user_course_completion
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_user_course_completion
     */
    public function test_get_user_course_completion() {
        global $DB;

        $this->resetAfterTest(true);

        $DB->delete_records('user_completion');

        $userid = 10;
        $courseid = 20;

        self::assertFalse($this->repository->get_user_course_completion($userid, $courseid));

        $old = new \stdClass();
        $old->userid = $userid;
        $old->courseid = $courseid;
        $old->completion = 0;
        $old->lastupdate = time() - 100;
        $DB->insert_record('user_completion', $old);

        $result = $this->repository->get_user_course_completion($userid, $courseid);

        $this->assertNotFalse($result);
        $this->assertEquals(0, $result->completion);

        $recent = new \stdClass();
        $recent->id = $result->id;
        $recent->completion = 75;
        $recent->lastupdate = time();
        $DB->update_record('user_completion', $recent);

        $result = $this->repository->get_user_course_completion($userid, $courseid);

        $this->assertNotFalse($result);
        $this->assertEquals(75, $result->completion);

        self::resetAllData();
    }

    /**
     * Test get_user_course_completion return false when no record exist
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_user_course_completion
     */
    public function test_returns_false_when_no_record() {
        $result = $this->repository->get_user_course_completion(1, 1);

        $this->assertFalse($result);
    }
}
