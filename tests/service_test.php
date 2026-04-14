<?php

use block_completion_monitor\model\activity_details;
use block_completion_monitor\service\completion_monitor_service;
use block_completion_monitor\repository\completion_monitor_repository;

defined('MOODLE_INTERNAL');

global $CFG;
require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');

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
        $criterion = new completion_criteria_activity();
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
        $criterion = new completion_criteria_activity();
        $criterion->update_config($criteriadata);

        $filteractivities = $this->service->get_filtered_activities($USER->id, $exclusions);

        self::assertCount(1, $filteractivities);

        $exclusions[] = $filteractivities[0]->get_type() . '-' . $filteractivities[0]->get_instance() . '-' . $USER->id;

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
        $criterion = new completion_criteria_activity();
        $criterion->update_config($criteriadata);

        self::setUser($user1);

        $filteractivities = $this->service->get_filtered_activities($USER->id, $exclusions);

        self::assertCount(1, $filteractivities);

        self::setAdminUser();

        $filteractivities = $this->service->get_filtered_activities($USER->id, $exclusions);

        self::assertCount(2, $filteractivities);
    }

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
        $criterion = new completion_criteria_activity();
        $criterion->update_config($criteriadata);

        $completion = new completion_info($this->course);
        $completion->update_state($instance2cm, COMPLETION_COMPLETE, $student->id);

        $coursecompletionpercentage = $this->service->get_course_completion_details($student->id);

        self::assertEquals(50, $coursecompletionpercentage['percentage']);
    }

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
        $criterion = new completion_criteria_activity();
        $criterion->update_config($criteriadata);

        $activities = $this->service->get_activities_details($course);
        $requiredactivities = array_filter($activities, fn(activity_details $activity) => $activity->get_required() == true);

        self::assertCount(1, $requiredactivities);
        self::assertEquals($url2cm->id, current($requiredactivities)->get_id());
        self::assertEquals('url', current($requiredactivities)->get_type());
    }

    public function test_add_block_to_course()
    {
        $this->setAdminUser();


        $context = \context_course::instance($this->course->id);

        $exists = $this->repository->block_instance_exists($context->id);
        self::assertFalse($exists);

        $this->service->add_block_to_course();

        $exists = $this->repository->block_instance_exists($context->id);
        self::assertTrue($exists);
    }

    public function test_remove_block_from_course()
    {
        $this->setAdminUser();

        $context = \context_course::instance($this->course->id);

        $this->service->add_block_to_course();

        $exists = $this->repository->block_instance_exists($context->id);
        self::assertTrue($exists);

        $this->service->remove_block_from_course();

        $exists = $this->repository->block_instance_exists($context->id);
        self::assertFalse($exists);
    }

    public function test_get_activities_details_completion_conditions_ok()
    {
        $this->setAdminUser();

        $record = new stdClass();
        $record->course = $this->course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $record->visible = 1;
        $instance = $this->getDataGenerator()->create_module('quiz', $record);
        $instancecm = get_coursemodule_from_id('quiz', $instance->cmid);

        $criteriadata = (object) [
            'id' => $this->course->id,
            'criteria_activity' => [
            $instancecm->id => 1
            ]
        ];
        $criterion = new completion_criteria_activity();
        $criterion->update_config($criteriadata);   

        $activitiesdetails = $this->service->get_activities_details($this->course);
        $activitydetails = current($activitiesdetails);
        self::assertNotNull($activitydetails);
        $completionconditions = json_decode($activitydetails->get_completionconditions(), true);
        self::assertEquals([[
            'status' => 0,
            'description' => 'Mark complete'
        ]], $completionconditions);
        self::assertInstanceOf(activity_details::class, $activitydetails);
        self::assertEquals($instancecm->id, $activitydetails->get_id());
        self::assertEquals('quiz', $activitydetails->get_type());
    }

    public function test_get_activities_details_scorm_opennewtab_ok()
    {
        $this->setAdminUser();

        $record = new stdClass();
        $record->course = $this->course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $record->visible = 1;
        $record->popup = 1;
        $instance = $this->getDataGenerator()->create_module('scorm', $record);
        $instancecm = get_coursemodule_from_id('scorm', $instance->cmid);

        $criteriadata = (object) [
            'id' => $this->course->id,
            'criteria_activity' => [
            $instancecm->id => 1
            ]
        ];
        $criterion = new completion_criteria_activity();
        $criterion->update_config($criteriadata);   

        $activitiesdetails = $this->service->get_activities_details($this->course);
        $activitydetails = current($activitiesdetails);
        self::assertNotNull($activitydetails);
        self::assertTrue($activitydetails->get_opennewtab());
    }

    public function test_add_block_to_course_check_position()
    {
        global $DB, $CFG;

        $context = \context_course::instance($this->course->id);

        $exists = $this->repository->block_instance_exists($context->id);
        self::assertFalse($exists);

        $this->service->add_block_to_course();

        $exists = $this->repository->block_instance_exists($context->id);
        self::assertTrue($exists);

        $position = $CFG->blocktopregion ?? BLOCK_POS_LEFT;
        self::assertEquals($position, $DB->get_field('block_instances', 'defaultregion', ['parentcontextid' => $context->id]));
    }

    public function test_should_display_block()
    {
        global $USER;

        self::setAdminUser();


        $shoulddisplay = $this->service->should_display_block($this->course->id);
        self::assertFalse($shoulddisplay);

        $record = new stdClass();
        $record->course = $this->course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $record->visible = 1;
        $instance1 = $this->getDataGenerator()->create_module('url', $record);
        $instance1cm = get_coursemodule_from_id('url', $instance1->cmid);

        // create course completion, and add the url activity as criteria
        $criteriadata = (object) [
            'id' => $this->course->id,
            'criteria_activity' => [
                $instance1cm->id => 1
            ]
        ];
        $criterion = new completion_criteria_activity();
        $criterion->update_config($criteriadata);

        $shoulddisplay = $this->service->should_display_block($this->course->id);
        self::assertTrue($shoulddisplay);
    }

}