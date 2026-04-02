<?php

global $CFG;

use block_completion_monitor\service\completion_monitor_service;
use block_completion_monitor\repository\completion_monitor_repository;

require_once($CFG->dirroot.'/completion/criteria/completion_criteria_activity.php');

defined('MOODLE_INTERNAL');

class block_completion_monitor_service_testcase extends advanced_testcase
{
    /**
     * Course object
     * @var stdClass
     */
    private \stdClass $course;

    /**
     * Activities Completion Course Monitoring service
     * @var completion_monitor_repository
     */
    private completion_monitor_repository $repository;

    /**
     * Activities Completion Course Monitoring service
     * @var completion_monitor_service
     */
    private completion_monitor_service $service;

    protected function setUp(): void
    {
        parent::setUp();

        self::resetAfterTest();
        set_config('enablecompletion', 1);

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course([
            'enablecompletion' => 1,
        ]);

        $this->service = new completion_monitor_service($this->course);
        $this->repository = new completion_monitor_repository();
    }

    /**
     * @covers $this->service->get_filtered_activities
     */
    public function test_get_filtered_activities()
    {
        global $USER;

        self::setAdminUser();

        $user1 = $this->getDataGenerator()->create_user();

        $exclusions = $this->repository->get_grade_exclusions($this->course->id, $USER->id);

        $filteractivities = $this->service->get_filtered_activities($USER->id, $exclusions);
        self::assertCount(0, $filteractivities);

        $record = new stdClass();
        $record->course = $this->course;
        $forum = $this->getDataGenerator()->create_module('forum', $record);
        $forumcm = get_coursemodule_from_id('forum', $forum->cmid);

        // Create course completion, and add the forum activity as criteria
        $criteriadata = (object) [
            'id' => $this->course->id,
            'criteria_activity' => [
                $forumcm->id => 1
            ]
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);

        $filteractivities = $this->service->get_filtered_activities($USER->id, $exclusions);
        self::assertCount(0, $filteractivities);

        $record = new stdClass();
        $record->course = $this->course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $url = $this->getDataGenerator()->create_module('url', $record);
        $urlcm = get_coursemodule_from_id('url', $url->cmid);

        $criteriadata = (object) [
            'id' => $this->course->id,
            'criteria_activity' => [
                $urlcm->id => 1
            ]
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);

        $filteractivities = $this->service->get_filtered_activities($USER->id, $exclusions);

        self::assertCount(1, $filteractivities);

        $exclusions[] = $filteractivities[0]['type'] . '-' . $filteractivities[0]['instance'] . '-' . $USER->id;

        $filteractivities = $this->service->get_filtered_activities($USER->id, $exclusions);

        self::assertCount(0, $filteractivities);

        $exclusions = $this->repository->get_grade_exclusions($this->course->id, $USER->id);

        $record = new stdClass();
        $record->course = $this->course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $record->visible = 0;
        $url2 = $this->getDataGenerator()->create_module('url', $record);
        $url2cm = get_coursemodule_from_id('url', $url2->cmid);

        $criteriadata = (object) [
            'id' => $this->course->id,
            'criteria_activity' => [
                $url2cm->id => 1
            ]
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);

        self::setUser($user1);

        $filteractivities = $this->service->get_filtered_activities($USER->id, $exclusions);

        self::assertCount(1, $filteractivities);

        self::setAdminUser();

        $filteractivities = $this->service->get_filtered_activities($USER->id, $exclusions);

        self::assertCount(2, $filteractivities);
    }

    /**
     * @covers $this->service->get_course_completion_details
     */
    public function test_get_course_completion_details()
    {
        self::setAdminUser();

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'participant');

        $record = new stdClass();
        $record->course = $this->course;
        $this->getDataGenerator()->create_module('forum', $record);

        $record = new stdClass();
        $record->course = $this->course;
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
            'id' => $this->course->id,
            'criteria_activity' => [
                $instance1cm->id => 1,
                $instance2cm->id => 1,
            ]
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);

        $completion = new completion_info($this->course);
        $completion->update_state($instance2cm, COMPLETION_COMPLETE, $student->id);

        $coursecompletionpercentage = $this->service->get_course_completion_details($student->id);

        self::assertEquals(50, $coursecompletionpercentage['percentage']);
    }

    /**
     * @covers $this->service->get_activities_details
     */
    public function test_completion_get_activities_details_ok()
    {
        $courserecord = new stdClass();
        $courserecord->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course($courserecord);

        $record = new stdClass();
        $record->course = $course;
        $this->getDataGenerator()->create_module('forum', $record);

        $activities = $this->service->get_activities_details($course);
        self::assertCount(0, $activities);

        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $this->getDataGenerator()->create_module('url', $record);

        $activities = $this->service->get_activities_details($course);
        self::assertCount(1, $activities);
    }

    /**
     * @covers $this->service->get_activities_details
     */
    public function test_completion_get_required_details_activities_ok()
    {
        $courserecord = new stdClass();
        $courserecord->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course($courserecord);

        $record = new stdClass();
        $record->course = $course;

        $this->getDataGenerator()->create_module('forum', $record);

        $activities = $this->service->get_activities_details($course);
        self::assertCount(0, $activities);

        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $this->getDataGenerator()->create_module('url', $record);

        $url2 = $this->getDataGenerator()->create_module('url', $record);
        $url2cm = get_coursemodule_from_id('url', $url2->cmid);

        // create course completion, and add the second url activity as criteria
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_activity' => [
                $url2cm->id => 1
            ]
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);

        $activities = $this->service->get_activities_details($course);
        $requiredactivities = array_filter($activities, fn($activity) => $activity["required"] == true);

        self::assertCount(1, $requiredactivities);
        self::assertEquals($url2cm->id, current($requiredactivities)['id']);
        self::assertEquals('url', current($requiredactivities)['type']);
    }
}