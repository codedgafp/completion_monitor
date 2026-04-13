<?php
namespace block_completion_monitor;

use cache;
use block_completion_monitor\service\completion_monitor_service;

defined('MOODLE_INTERNAL') || die();

class observer
{
    public static function course_module_changed($event): void
    {

        $courseid = $event->courseid;
        $course = get_course($courseid);

        $service = new completion_monitor_service($course);

        $hascompletion = $service->activities_has_completion($courseid);

        if ($hascompletion) {
            $service->add_block_to_course();
        } else {
            $service->remove_block_from_course();
        }
    }

    public static function completion_updated(\core\event\course_module_completion_updated $event): void
    {
        $data = $event->get_data();

        $userid = $data['relateduserid'];
        $courseid = $data['courseid'];
        $course = get_course($courseid);

        $service = new completion_monitor_service($course);
        $coursecompletiondetails = $service->get_course_completion_details($userid);

        $cache = cache::make('block_completion_monitor', 'completion_percentage');
        $cache->set_many([
            "completion_percentage_{$userid}_{$courseid}" => $coursecompletiondetails['percentage'],
        ]);
    }

}
