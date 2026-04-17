<?php

namespace block_completion_monitor\model;

defined('MOODLE_INTERNAL') || die();

class percentage_circle extends model_manager
{
    private int $percentage;

    private int $radius;

    private float $circumference;

    private float $offset;

    public function __construct($course)
    {
        $this->percentage = $this->course_completion_details($course)['percentage'];
        $this->radius = 40;
        $this->circumference = (2 * M_PI * $this->radius);
        $this->offset = $this->circumference * (1 - $this->percentage / 100);
    }

    public function buildrecord(): array
    {
        return [
            "percentage"    => $this->percentage,
            "radius"        => $this->radius,
            "circumference" => $this->circumference,
            "offset"        => $this->offset,
        ];
    }
}
