<?php
namespace block_completion_monitor;

defined('MOODLE_INTERNAL') || die();

use block_completion_monitor\service\completion_monitor_service;

class observer {

    public static function course_module_changed($event) {

        $courseid = $event->courseid;
        $course = get_course($courseid);
        
        $service = new completion_monitor_service($course);

        $hascompletion = $service->activities_has_completion($courseid);

        if ($hascompletion) {
            $service->add_block_to_course();
        }else {
            $service->remove_block_from_course();
        }
    }
}
