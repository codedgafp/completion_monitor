<?php

namespace block_completion_monitor\model;

use core_completion\cm_completion_details;

defined('MOODLE_INTERNAL') || die();

class activity_details
{
    private ?string $type = null;

    private ?string $modulename = null;

    private ?int $id = null;

    private ?int $instance = null;

    private ?string $name = null;

    private ?int $expected = null;

    private ?int $section = null;

    private ?int $position = null;

    private ?string $url = null;

    private ?\context $context = null;

    private ?\core\url $icon = null;

    private ?bool $available = null;

    private ?bool $required = null;

    private ?string $completionconditions = null;

    private bool $opennewtab = true;

    public function __construct(\cm_info $cm, \completion_info $completioninfo = null)
    {
        if ($completioninfo == null) {
            $course = get_course($cm->course);
            $completioninfo = new \completion_info($course);
        }

        $this->id = $cm->id;
        $this->instance = $cm->instance;
        $this->name = format_string($cm->name);
        $this->expected = $cm->completionexpected;
        $this->section = $cm->sectionnum;
        $this->url = !is_null($cm->url) && method_exists($cm->url, 'out') ? $cm->url->out() : '';
        $this->context = $cm->context;
        $this->icon = $cm->get_icon_url();
        $this->available = $cm->available;
        $this->completionconditions = json_encode($this->get_activity_completion_conditions($cm, $completioninfo));
    }

    public function get_type(): ?string
    {
        return $this->type;
    }
    public function set_type(string $type): void
    {
        $this->type = $type;
    }

    public function set_modulename(string $modulename): void
    {
        $this->modulename = $modulename;
    }

    public function get_id(): ?int
    {
        return $this->id;
    }

    public function get_instance(): ?int
    {
        return $this->instance;
    }

    public function set_position(int $position): void
    {
        $this->position = $position;
    }

    public function get_available(): ?bool
    {
        return $this->available;
    }
    public function set_available(bool $available): void
    {
        $this->available = $available;
    }

    public function get_required(): ?bool
    {
        return $this->required;
    }
    public function set_required(bool $required): void
    {
        $this->required = $required;
    }

    public function get_completionconditions(): ?string
    {
        return $this->completionconditions;
    }

    public function set_opennewtab(bool $opennewtab): void
    {
        $this->opennewtab = $opennewtab;
    }

    public function get_opennewtab(): ?bool
    {
        return $this->opennewtab;
    }

    /**
     * @param \cm_info $coursemodule
     * @param \completion_info $completioninfo
     * @return array
     */
    private function get_activity_completion_conditions(\cm_info $coursemodule, \completion_info $completioninfo): array
    {
        global $USER;
        $conditions = [];

        if ($coursemodule->completion == COMPLETION_TRACKING_MANUAL) {
            $completiondata = $completioninfo->get_data($coursemodule, false, $USER->id);
            $conditions = [
                [
                    "status" => (int) ($completiondata->completionstate == COMPLETION_COMPLETE),
                    "description" => get_string('markcomplete', 'completion')
                ]
            ];
        } else {
            $cmcompletion = cm_completion_details::get_instance($coursemodule, $USER->id);
            $cmcompletiondetails = $cmcompletion->get_details();
            $conditions = array_values(array_filter($cmcompletiondetails));
        }

        return $conditions;
    }
}
