<?php

namespace block_completion_monitor\model;

use block_completion_monitor\service\completion_activities_service;

defined('MOODLE_INTERNAL') || die();

abstract class model_manager
{
    protected function course_completion_details($course): array
    {
        global $USER;

        $service = new completion_activities_service($course);
        return $service->get_course_completion_details($USER->id);
    }
}