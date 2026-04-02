<?php

use block_completion_monitor\repository\completion_monitor_repository;

defined('MOODLE_INTERNAL');

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

    /**
     * @covers $this->repository->get_grade_exclusions
     */
    public function test_grade_exclusions_ok()
    {
        self::setAdminUser();

        $coursedata = new \stdClass();
        $coursedata->category = 1;
        $coursedata->shortname = 'Course1';
        $coursedata->fullname = 'Course1';

        $course = create_course($coursedata);

        self::assertCount(0, $this->repository->get_grade_exclusions($course->id));
    }

    /**
     * @covers $this->repository->get_user_course_submissions
     */
    public function test_get_user_course_submissions() {
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
            'courseid' => $course->id, 'itemtype' => 'mod',
            'itemmodule' => 'assign', 'iteminstance' => $instance->id, 'outcomeid' => null,
        ]);
        $item->gradepass = 50;
        $item->update();

        $assign = new \mod_assign_testable_assign(\context_module::instance($cm->id), $cm, $course);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'formateur');

        // Student 1 submits to the activity and gets graded correct.
        $this->submit_for_student($student, $assign);
        $this->grade_student($student, $assign, $teacher, 75, 0);

        // User has assign submission.
        $usersubmissions = local_mentor_core_completion_get_user_course_submissions($course->id, $student->id);
        self::assertCount(1, $usersubmissions);

        self::resetAllData();
    }
}
