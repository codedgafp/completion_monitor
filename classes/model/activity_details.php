<?php

namespace block_completion_monitor\model;

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

    public function __construct(\cm_info $cm)
    {
        $this->id = $cm->id;
        $this->instance = $cm->instance;
        $this->name = format_string($cm->name);
        $this->expected = $cm->completionexpected;
        $this->section = $cm->sectionnum;
        $this->url = !is_null($cm->url) && method_exists($cm->url, 'out') ? $cm->url->out() : '';
        $this->context = $cm->context;
        $this->icon = $cm->get_icon_url();
        $this->available = $cm->available;
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
}