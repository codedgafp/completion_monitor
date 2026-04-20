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

        $cache_percentage = cache::make('block_completion_monitor', 'completion_percentage');
        $cache_percentage->set_many([
            "completion_percentage_{$userid}_{$courseid}" => $coursecompletiondetails['percentage'],
        ]);
        $cache_activities_reset = cache::make('block_completion_monitor', 'activities_reset');
        $cache_activities_reset->set_many([
            "completion_reset_activities_{$userid}_{$courseid}" => true,
        ]);
    }

    public static function set_course_module_status_in_progress(\core\event\course_module_viewed $event): void
    {
        global $USER;

        $data = $event->get_data();
        $userid = $USER->id;
        $cmid = $data["contextinstanceid"];

        $course = get_course($data['courseid']);
        $service = new completion_monitor_service($course);

        $userpreferencename = $service->course_module_viewed_preference_name($userid, $cmid);
        $userpreferencedata = json_encode([
            "time_access" => time(),
            "user_id" => $userid,
            "course_module_id" => $cmid
        ]);

        set_user_preference($userpreferencename, $userpreferencedata);
    }
}
