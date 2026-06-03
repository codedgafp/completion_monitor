<?php

namespace block_completion_monitor\model;

use block_completion_monitor\service\completion_monitor_service;
use block_completion_monitor\service\completion_activities_service;

defined('MOODLE_INTERNAL') || die();

class template_context extends model_manager
{
    private bool $display_percentage;

    private ?array $percentage_circle_data = null;

    private ?string $courseprogress_percentage = null;

    private ?string $courseprogress_step = null;

    private ?array $activitiesdetails = null;

    public function __construct(\stdClass $course)
    {
        global $USER;

        $completionactivitiesservice = new completion_activities_service($course);

        $activities = $completionactivitiesservice->get_activities_details();

        $this->display_percentage = $this->display_percentage($activities);

        if ($this->display_percentage) {
            $coursecompletiondetails = $this->course_completion_details($course);

            $percentagecircle = new percentage_circle($course);
            $this->percentage_circle_data = $percentagecircle->buildrecord();
            $this->courseprogress_percentage = get_string('courseprogress_percentage', 'block_completion_monitor', $coursecompletiondetails['percentage']);
            $this->courseprogress_step = get_string('courseprogress_step', 'block_completion_monitor', $this->course_progress_step_details($coursecompletiondetails['completions']));
        }

        $this->activitiesdetails = array_map(fn(/** @var activity_details */ $activity) => $activity->buildrecord(), $activities);
    }

    public function get_template_context(): ?array
    {
        return [
            'display_percentage'        => $this->display_percentage,
            'percentage_circle_data'    => $this->percentage_circle_data,
            'courseprogress_percentage' => $this->courseprogress_percentage,
            'courseprogress_step'       => $this->courseprogress_step,
            'activities_details'        => $this->activitiesdetails,
            'uniqid'                    => uniqid()
        ];
    }

    /**
     * If an activity in required, then display percentage
     * 
     * @param activity_details[] $activities
     * @return bool
     */
    private function display_percentage(array $activities): bool
    {
        $requiredactivities = array_filter($activities, fn(activity_details $activity) => $activity->get_required() == true);

        return count($requiredactivities) > 0;
    }

    /**
     * @param array $completions
     * @return object
     */
    private function course_progress_step_details(array $completions): \stdClass
    {
        $completionscompleted = array_filter($completions, fn($completion) => $completion == COMPLETION_COMPLETE);

        return (object) [
            'activitiescompleted' => count($completionscompleted),
            'activitiestocompleted' => count($completions)
        ];
    }
}
