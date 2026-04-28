<?php

namespace block_completion_monitor\service;

use moodle_database;
use block_completion_monitor\helper\progress;
use block_completion_monitor\model\activity_details;
use block_completion_monitor\model\block_instance_record;
use block_completion_monitor\repository\completion_monitor_repository;

require_once($CFG->libdir . '/completionlib.php');

/**
 * Activities Completion Course Monitoring service.
 * 
 * @package     block_completion_monitor
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_monitor_service
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
        if (empty($activities)) {
            return [
                'percentage' => 0,
                'completions' => []
            ];
        }

        // Finds submissions for a user in a course.
        $submissions = $this->accmrepository->get_user_course_submissions($this->course->id, $userid);

        $completions = self::get_progress_from_course_modules($this->course, $activities, $userid, $submissions);

        $completecount = count(array_filter($activities, function (activity_details $activity) use ($completions) {
            return $completions[$activity->get_id()] == COMPLETION_COMPLETE || $completions[$activity->get_id()] == COMPLETION_COMPLETE_PASS;
        }));

        $progressvalue = $completecount == 0 ? 0 : $completecount / count($activities);

        return [
            'percentage' => (int) floor($progressvalue * 100),
            'completions' => $completions
        ];
    }

    /**
     * Filters activities that a user cannot see due to grouping constraints
     *
     * @param int $userid
     * @param array $exclusions
     * @param bool $onlyrequired
     * @return activity_details[]
     */
    public function get_filtered_activities(int $userid, array $exclusions, bool $onlyrequired = false): array
    {
        global $USER, $CFG;

        $filteredactivities = [];
        $coursecontext = \context_course::instance($this->course->id);

        $modinfo = get_fast_modinfo($this->course, $USER->id);

        $activities = $this->get_activities_details($this->course);

        if ($onlyrequired) {
            $activities = array_filter($activities, fn(activity_details $activity) => $activity->get_required() == true);
        }
        $canviewhiddenactivities = has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid);

        foreach ($activities as
        /** @var activity_details */
        $activity) {
            $coursemodule = $modinfo->cms[$activity->get_id()];

            if (!$coursemodule->visible && !$canviewhiddenactivities && !$coursemodule->is_visible_on_course_page()) {
                continue;
            }

            if (!empty($CFG->enableavailability)) {
                if ($canviewhiddenactivities) {
                    $activity->set_available(true);
                } else {
                    if (isset($coursemodule->available) && !$coursemodule->available && empty($coursemodule->availableinfo)) {
                        continue;
                    }
                    $activity->set_available($coursemodule->available);
                }
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
     *
     * @param \stdClass $course
     * @return activity_details[] $activities
     **/
    public function get_activities_details(\stdClass $course): array
    {
        global $USER;

        $coursecompletion = new \completion_info($course);
        $coursecompletionactivities = $coursecompletion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY);

        $modinfo = get_fast_modinfo($course, $USER->id);
        $sections = $modinfo->get_sections();

        $coursemodules = $modinfo->get_cms();
        $activities = [];

        $coursecompletioncriterialist = $this->db->get_records('course_completion_criteria', ['course' => $course->id]);

        // Create activities list with completion set.
        foreach ($coursemodules as $cm) {
            if ($cm->completion === COMPLETION_TRACKING_NONE || !$cm->is_visible_on_course_page()) {
                continue;
            }

            $module = $cm->modname;
            $modulename = $cm->get_module_type_name();

            $required = $this->is_activity_required($coursecompletioncriterialist, $cm->id, $module, $coursecompletionactivities);

            $activitydetails = new activity_details($cm);
            $activitydetails->set_type($module);
            $activitydetails->set_modulename($modulename);
            $activitydetails->set_position(array_search($cm->id, $sections[$cm->sectionnum]));
            $activitydetails->set_required($required);

            //Due to the Patch Edunao mentor: display scorm in a new tab or not.
            if ($cm->name === 'scorm') {
                $this->set_opennewtab_for_scorm($activitydetails, $cm);
            }

            $activities[] = $activitydetails;
        }

        return $activities;
    }

    /**
     * @param int $courseid
     * @return bool
     */
    public function activities_has_completion(int $courseid): bool
    {
        return $this->accmrepository->activity_has_completion($courseid);
    }

    /**
     * @return void
     */
    public function add_block_to_course(): void
    {
        $context = \context_course::instance($this->course->id);

        $exists = $this->accmrepository->block_instance_exists($context->id);

        if (!$exists) {
            $blockRecord = new block_instance_record(
                blockname: 'completion_monitor',
                parentcontextid: $context->id,
                pagetypepattern: 'course-view-*'
            );

            $this->db->insert_record('block_instances', $blockRecord->buildrecord());
        }
    }

    /**
     * @return void
     */
    public function remove_block_from_course(): void
    {
        $context = \context_course::instance($this->course->id);
        $exists = $this->accmrepository->block_instance_exists($context->id);

        if ($exists) {
            $this->db->delete_records('block_instances', [
                'blockname' => 'completion_monitor',
                'parentcontextid' => $context->id
            ]);
        }
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
        $scorm = $this->accmrepository->get_scorm_by_coursemoduleid($coursemodule->id);
        if (!empty($scorm) && $scorm->popup == 0) {
            $activitiesdetails->set_opennewtab($scorm->popup == 1);
        }
    }

    public function should_display_block(int $courseId): bool
    {
        global $USER;
        $exclusions = $this->accmrepository->get_grade_exclusions($courseId, $USER->id);

        $activities = $this->get_filtered_activities($USER->id, $exclusions);
        return !empty($activities);
    }

    /**
     * @param int $userid
     * @param int $cmid
     * @return string
     */
    public function course_module_viewed_preference_name(int $userid, int $cmid): string
    {
        return "course_module_viewed_" . $userid . "_" . $cmid;
    }

    /**
     * @param int $courseid
     * @return string
     */
    public function block_completion_monitor_opened_preference_name(int $courseid): string
    {
        return "block_completion_monitor_" . $courseid . "_opened";
    }

    /**
     * @param int $userid
     * @param int $cmid
     * @return bool
     */
    public function course_module_has_beed_viewed(int $userid, int $cmid): bool
    {
        $preferencename = $this->course_module_viewed_preference_name($userid, $cmid);
        return $this->db->record_exists('user_preferences', ['name' => $preferencename]);
    }
}
