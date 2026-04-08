<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_completion_monitor_install()
{
    $courses = get_courses();

    foreach ($courses as $course) {
        if ($course->id == SITEID) continue;
            $service = new \block_completion_monitor\service\completion_monitor_service($course);
            $service->add_block_to_course();       
    }

    return true;
}
