<?php

use block_completion_monitor\helper\progress;
use block_completion_monitor\service\completion_monitor_service;
use block_completion_monitor\repository\completion_monitor_repository;

defined('MOODLE_INTERNAL');

global $CFG;
require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');

class block_completion_monitor_helper_testcase extends advanced_testcase
{
    use progress;

    /**
     * Activities Completion Course Monitoring repository
     * @var completion_monitor_repository
     */
    private completion_monitor_repository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        self::resetAfterTest();
        set_config('enablecompletion', 1);

        $this->repository = new completion_monitor_repository();
    }

    public function test_completion_monitor_get_progress()
    {
        $courserecord = new stdClass();
        $courserecord->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course($courserecord);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'participant');

        $record = new stdClass();
        $record->course = $course;
        $this->getDataGenerator()->create_module('forum', $record);

        $record = new stdClass();
        $record->course = $course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $record->visible = 1;
        $instance1 = $this->getDataGenerator()->create_module('url', $record);
        $instance1cm = get_coursemodule_from_id('url', $instance1->cmid);

        $instance2 = $this->getDataGenerator()->create_module('url', $record);
        $instance2cm = get_coursemodule_from_id('url', $instance2->cmid);

        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_activity' => [
                $instance1cm->id => 1,
                $instance2cm->id => 1,
            ]
        ];
        $criterion = new completion_criteria_activity();
        $criterion->update_config($criteriadata);

        $completion = new \completion_info($course);
        $completion->update_state($instance2cm, COMPLETION_COMPLETE, $student->id);

        $service = new completion_monitor_service($course);

        $exclusions = $this->repository->get_grade_exclusions($course->id, $student->id);
        $activities = $service->get_filtered_activities($student->id, $exclusions);
        $submissions = $this->repository->get_user_course_submissions($course->id, $student->id);
        $completions = self::get_progress($course, $activities, $student->id, $submissions);

        self::assertCount(2, $completions);
        self::assertArrayHasKey($instance1->cmid, $completions);
        self::assertEquals(0, $completions[$instance1->cmid]);
        self::assertArrayHasKey($instance2->cmid, $completions);
        self::assertEquals('1', $completions[$instance2->cmid]);

        self::resetAllData();
    }
}