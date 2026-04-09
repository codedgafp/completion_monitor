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
        $this->content->text = $renderer->render_block_with_controls($this);

        return $this->content;
    }
}
