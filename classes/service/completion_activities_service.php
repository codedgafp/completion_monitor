<?php

namespace block_completion_monitor\service;

use cm_info;
use course_modinfo;
use moodle_database;
use block_completion_monitor\helper\progress;
use block_completion_monitor\model\activity_details;
use block_completion_monitor\repository\completion_monitor_repository;

class completion_activities_service
{
    use progress;

    /**
     * Activities Completion Course Monitoring repository
     * @var completion_monitor_repository
     */
    protected completion_monitor_repository $accmrepository;

    /**
     * Moodle Database
     * @var moodle_database
     */
    protected moodle_database $db;

    public function __construct(
        private \stdClass $course,
    ) {
        global $DB;

        $this->accmrepository = new completion_monitor_repository();
        $this->db = $DB;
    }

    /**
     * Return the course progress percentage
     * 
     * @param int $userid
     * @return array
     */
    public function get_course_completion_details(int $userid): array
    {
        // Get gradebook exclusions list for students in a course.
        $exclusions = $this->accmrepository->get_grade_exclusions($this->course->id, $userid);

        $activities = $this->get_filtered_activities($userid, $exclusions, true);

        $coursecompletionsdetails = [
            'percentage' => 0,
            'completions' => [],
        ];

        if (empty($activities))
            return $coursecompletionsdetails;

        // Finds submissions for a user in a course.
        $submissions = $this->accmrepository->get_user_course_submissions($this->course->id, $userid);

        $completions = self::get_progress_from_course_modules($this->course, $activities, $userid, $submissions);
        $coursecompletionsdetails['completions'] = $completions;

        $completecount = count(array_filter($activities, function (activity_details $activity) use ($completions) {
            return $completions[$activity->get_id()] == COMPLETION_COMPLETE || $completions[$activity->get_id()] == COMPLETION_COMPLETE_PASS;
        }));

        $progressvalue = $completecount == 0 ? 0 : $completecount / count($activities);
        $coursecompletionsdetails['percentage'] = (int) floor($progressvalue * 100);

        return $coursecompletionsdetails;
    }

    /**
     * Filters activities that a user cannot see due to grouping constraints
     *
     * @param int $userid
     * @param bool $onlyrequired
     * @return activity_details[]
     */
    public function get_filtered_activities(int $userid, array $exclusions, bool $onlyrequired = false): array
    {
        global $CFG;

        $filteredactivities = [];
        $coursecontext = \context_course::instance($this->course->id);
        $modinfo = get_fast_modinfo($this->course, $userid);

        $activities = $this->get_activities_details($modinfo);
        if ($onlyrequired) {
            $activities = array_filter($activities, fn(activity_details $activity) => $activity->get_required() == true);
        }

        $canviewhiddenactivities = has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid);

        foreach ($activities as /** @var activity_details */ $activity) {
            $coursemodule = $modinfo->cms[$activity->get_id()];

            if (!$coursemodule->visible && !$canviewhiddenactivities) {
                continue;
            }

            if (!empty($CFG->enableavailability)) {
                if (isset($coursemodule->available) && !$coursemodule->available && empty($coursemodule->availableinfo)) {
                    continue;
                }

                $canviewhiddenactivities
                    ? $activity->set_available(true)
                    : $activity->set_available($coursemodule->available);
            }

            if (in_array($activity->get_type() . '-' . $activity->get_instance() . '-' . $userid, $exclusions)) {
                continue;
            }

            $filteredactivities[] = $activity;
        }

        return $filteredactivities;
    }

    /**
     * Return details about the course activities.
     * @param course_modinfo|null $modinfo
     * @return activity_details[]
     */
    public function get_activities_details(course_modinfo $modinfo = null): array
    {
        global $USER;

        $coursecompletion = new \completion_info($this->course);
        $coursecompletionactivities = $coursecompletion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY);

        if ($modinfo === null)
            $modinfo = get_fast_modinfo($this->course, $USER->id);

        $sections = $modinfo->get_sections();
        $coursemodules = $modinfo->get_cms();
        $activities = [];

        $coursecompletioncriterialist = $this->db->get_records('course_completion_criteria', ['course' => $this->course->id]);

        $coursecontext = \context_course::instance($this->course->id);
        $canviewhiddenactivities = has_capability('moodle/course:viewhiddenactivities', $coursecontext, $USER->id);

        // Create activities list with completion set.
        foreach ($coursemodules as $cm) {
            $available = true;

            if (isset($cm->availability)) {
                $availability = json_decode($cm->availability);
                $available = $availability->op == '&'
                    ? !in_array(false, $availability->showc)
                    : $availability->show;
            }

            $hasnocompletion = $cm->completion === COMPLETION_TRACKING_NONE;
            $isvisible = $cm->visible;

            if ($hasnocompletion || !$canviewhiddenactivities && (!$available || !$isvisible))
                continue;

            if (isset($cm->availability) && $available) {
                $activities[] = $this->build_activity_details($cm, $coursecompletion, $coursecompletioncriterialist, $coursecompletionactivities, $sections);
                continue;
            }

            $activities[] = $this->build_activity_details($cm, $coursecompletion, $coursecompletioncriterialist, $coursecompletionactivities, $sections);
        }

        return $activities;
    }

    private function build_activity_details(
        cm_info $cm,
        object  $coursecompletion,
        array   $coursecompletioncriterialist,
        array   $coursecompletionactivities,
        array   $sections
    ): activity_details
    {
        $module = $cm->modname;
        $required = $this->is_activity_required(
            $coursecompletioncriterialist,
            $cm->id,
            $module,
            $coursecompletionactivities
        );

        $activitydetails = new activity_details($cm, $coursecompletion);
        $activitydetails->set_type($module);
        $activitydetails->set_modulename($cm->get_module_type_name());
        $activitydetails->set_position(array_search($cm->id, $sections[$cm->sectionnum]));
        $activitydetails->set_required($required);

        // Due to the Patch Edunao mentor: display scorm in a new tab or not.
        if ($module === 'scorm') {
            $this->set_opennewtab_for_scorm($activitydetails, $cm);
        }

        return $activitydetails;
    }

    /**
     * Check whether an activity is required based on the list of items to be validated for the training,
     * according to the “COMPLETION_CRITERIA_TYPE_ACTIVITY” criterion
     * 
     * @param array $coursecompletioncriterialist
     * @param int $cmid
     * @param string $module
     * @param array $coursecompletionactivities
     * @return bool
     */
    private function is_activity_required(array $coursecompletioncriterialist, int $cmid, string $module, array $coursecompletionactivities): bool
    {
        if ($coursecompletioncriterialist) {
            $coursecompletioncriteria = array_filter(
                $coursecompletioncriterialist,
                fn($criteria) => $criteria->module == $module && $criteria->moduleinstance == $cmid
            );
            $coursecompletioncriteriaid = current(array_map(fn($criteria) => $criteria->id, $coursecompletioncriteria));

            return !empty(array_filter($coursecompletionactivities, fn($criteria) => $criteria->id == $coursecompletioncriteriaid));
        }

        return false;
    }

    /**
     * If the scorm activity allow popup, then update the activity_details given object
     * 
     * @param activity_details $activitiesdetails
     * @param \cm_info $coursemodule
     * @return void
     */
    private function set_opennewtab_for_scorm(activity_details $activitiesdetails, \cm_info $coursemodule): void
    {
        if ($scorm = $this->accmrepository->get_scorm_by_coursemoduleid($coursemodule->id)) {
            $activitiesdetails->set_opennewtab($scorm->popup);
        }
    }

    /**
     * @return bool
     */
    public function should_display_block(): bool
    {
        global $USER;

        // Get gradebook exclusions list for students in a course.
        $exclusions = $this->accmrepository->get_grade_exclusions($this->course->id, $USER->id);

        $activities = $this->get_filtered_activities($USER->id, $exclusions);
        return !empty($activities);
    }
}
