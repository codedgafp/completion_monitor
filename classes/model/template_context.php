<?php

namespace block_completion_monitor\model;

use block_completion_monitor\service\completion_monitor_service;

defined('MOODLE_INTERNAL') || die();

class template_context extends model_manager
{
    private bool $display_percentage;

    private ?percentage_circle $percentage_circle_data = null;

    private ?string $courseprogress_percentage = null;

    private ?string $courseprogress_step = null;

    public function __construct(\stdClass $course)
    {
        $this->display_percentage = $this->display_percentage($course);

        if ($this->display_percentage) {
            $coursecompletiondetails = $this->course_completion_details($course);

            $this->percentage_circle_data = new percentage_circle($course);
            $this->courseprogress_percentage = get_string('courseprogress_percentage', 'block_completion_monitor', $coursecompletiondetails['percentage']);
            $this->courseprogress_step = get_string('courseprogress_step', 'block_completion_monitor', $this->course_progress_step_details($coursecompletiondetails['completions']));
        }
    }

    public function get_template_context(): ?array
    {
        return [
            'display_percentage' => $this->display_percentage,
            'percentage_circle_data' => $this->percentage_circle_data,
            'courseprogress_percentage' => $this->courseprogress_percentage,
            'courseprogress_step' => $this->courseprogress_step
        ];
    }

    private function display_percentage(\stdClass $course): bool
    {
        $service = new completion_monitor_service($course);
        $activities = $service->get_activities_details($course);
        $requiredactivities = array_filter($activities, fn(activity_details $activity) => $activity->get_required() == true);

        return count($requiredactivities) > 0;
    }

    private function course_progress_step_details(array $completions): \stdClass
    {
        $completionscompleted = array_filter($completions, fn($completion) => $completion == COMPLETION_COMPLETE);

        return (object) [
            'activitiescompleted' => count($completionscompleted),
            'activitiestocompleted' => count($completions)
        ];
    }
}
