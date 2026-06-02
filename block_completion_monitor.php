<?php

use block_completion_monitor\service\completion_activities_service;

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

    public function hide_header()
    {
        return true;
    }

    /**
     * Allows the block to load any JS it requires into the page.
     */
    public function get_required_javascript()
    {
        parent::get_required_javascript();

        global $USER;

        $this->page->requires->strings_for_js([
            'showmore',
            'showless',
        ], 'block_completion_monitor');

        $this->page->requires->js_call_amd('block_completion_monitor/block_completion_monitor', 'init', ['courseid' => $this->page->course->id, 'userid' => $USER->id]);
        $this->page->requires->js_call_amd('block_completion_monitor/completion_monitor_dynamic', 'init', ['userid' => $USER->id, 'courseid' => $this->page->course->id]);
    }

    public function get_content()
    {
        $this->content = new \stdClass();
        $this->content->text = '';
        // Check if the block should be displayed for the course.
        $service = new completion_activities_service($this->page->course);
        if (!$service->should_display_block()) {
            return $this->content;
        }

        /** @var block_completion_monitor\output\renderer */
        $renderer = $this->page->get_renderer('block_completion_monitor');

        $this->content = new \stdClass();
        $this->content->text = $renderer->render_block_with_controls($this);

        return $this->content;
    }
}
