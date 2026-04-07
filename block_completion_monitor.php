<?php

use block_completion_monitor\service\completion_monitor_service;

defined('MOODLE_INTERNAL') || die();

class block_completion_monitor extends block_base
{
    public $blockopen;
    /**
     * Set the block title.
     *
     * @throws coding_exception
     */
    public function init()
    {
        $this->title = get_string('pluginname', 'block_completion_monitor');
    }

    public function instance_allow_config()
    {
        return true;
    }


    /**
     * Allows the block to load any JS it requires into the page.
     */
    public function get_required_javascript()
    {
        parent::get_required_javascript();

        $this->page->requires->strings_for_js([
            'showmore',
            'showless',
        ], 'block_completion_monitor');

        $this->page->requires->js_call_amd('block_completion_monitor/block_completion_monitor', 'init', ['courseid' => $this->page->course->id]);
    }

    public function get_content()
    {
        if ($this->content !== null) {
            return $this->content;
        }

        $renderer = $this->page->get_renderer('block_completion_monitor');

        $this->content = new \stdClass();

        $templatecontext = ["display_percentage" => $this->display_percentage()];
        if ($templatecontext["display_percentage"]) {
            $templatecontext = $this->set_template_context($templatecontext);
        }

        $this->content->text = $renderer->render_block_with_controls($this, $templatecontext);

        return $this->content;
    }

    private function display_percentage()
    {
        $service = new completion_monitor_service($this->page->course);
        $activities = $service->get_activities_details($this->page->course);

        return count(array_filter($activities, fn($activity) => $activity['required'] == true)) > 0;
    }

    private function set_template_context($context)
    {
        $coursecompletiondetails = $this->course_completion_details();

        $courseprogress_step = (object) [
            'activitiescompleted' => count(array_filter(
                $coursecompletiondetails['completions'],
                fn($completion) => $completion == COMPLETION_COMPLETE
            )),
            'activitiestocompleted' => count($coursecompletiondetails['completions'])
        ];
        $radius = 40;
        $circumference = (2 * M_PI * $radius);

        return array_merge($context, [
            'percentage_circle_data' => [
                'percentage' => $coursecompletiondetails['percentage'],
                'radius' => $radius,
                'circumference' => $circumference,
                'offset' => $circumference * (1 - $coursecompletiondetails['percentage'] / 100),
            ],
            'courseprogress_percentage' => get_string('courseprogress_percentage', 'block_completion_monitor', $coursecompletiondetails['percentage']),
            'courseprogress_step' => get_string('courseprogress_step', 'block_completion_monitor', $courseprogress_step),
        ]);
    }

    private function course_completion_details()
    {
        global $USER;

        $service = new completion_monitor_service($this->page->course);
        return $service->get_course_completion_details($USER->id);
    }
}
