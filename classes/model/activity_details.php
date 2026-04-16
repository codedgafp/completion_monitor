<?php

namespace block_completion_monitor\model;

use block_completion_monitor\helper\progress;
use block_completion_monitor\service\completion_monitor_service;
use block_completion_monitor\repository\completion_monitor_repository;
use core_completion\cm_completion_details;

defined('MOODLE_INTERNAL') || die();

class activity_details
{
    use progress;

    private const string COMPLETED = 'completed';

    private const string INPROGRESS = 'in_progress';

    private const string NOTSTARTED = 'not_started';

    private const string LOCKED = 'locked';

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

    private ?string $status = null;

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
        $this->status = $this->get_completion_state_by_activity_id($course, $cm);
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

    /**
     * Return the completion status of a course module
     * 
     * @param \stdClass $course
     * @param \cm_info $cm
     * @return string
     */
    private function get_completion_state_by_activity_id(\stdClass $course, \cm_info $cm): string
    {
        global $USER, $DB;

        $service = new completion_monitor_service($course);

        $userid = $USER->id;
        $completioninfo = new \completion_info($course);
        $completion = $completioninfo->get_data($cm, true, $userid);

        if (!$cm->uservisible) {
            return self::LOCKED;
        }

        if ($service->course_module_has_beed_viewed($userid, $cm->id)) {
            $repository = new completion_monitor_repository();
            // Finds submissions for a user in a course.
            $submissions = $repository->get_user_course_submissions($course->id, $userid);
            $submission = $submissions["$userid-$cm->id"] ?? null;

            $completionstate = $this->get_completion_state($completion, $submission);

            return $this->completion_state_map($completionstate);
        }

        return self::NOTSTARTED;
    }

    /**
     * Map Moodle's completion statuses to those of the completion_monitor block
     * 
     * @param int $state
     * @return string
     */
    private function completion_state_map(int $state): string
    {
        switch ($state) {
            case COMPLETION_INCOMPLETE:
            case COMPLETION_COMPLETE_FAIL:
                $completionstate = self::INPROGRESS;
                break;

            case COMPLETION_COMPLETE:
            case COMPLETION_COMPLETE_PASS:
                $completionstate = self::COMPLETED;
                break;

            default:
                $completionstate = self::NOTSTARTED;
                break;
        }

        return $completionstate;
    }
}
