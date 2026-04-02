<?php

namespace block_completion_monitor\service;

use block_completion_monitor\helper\progress;
use block_completion_monitor\repository\completion_monitor_repository;

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

    public function __construct(
        private \stdClass $course,
    ) {
        $this->accmrepository = new completion_monitor_repository();
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
            return ['percentage' => 0];
        }

        // Finds submissions for a user in a course.
        $submissions = $this->accmrepository->get_user_course_submissions($this->course->id, $userid);

        $completions = self::get_progress($this->course, $activities, $userid, $submissions);

        $completecount = count(array_filter($activities, function($activity) use ($completions) {
            return $completions[$activity['id']] == COMPLETION_COMPLETE || $completions[$activity['id']] == COMPLETION_COMPLETE_PASS;
        }));

        $progressvalue = $completecount == 0 ? 0 : $completecount / count($activities);

        return [
            'percentage' => (int)floor($progressvalue * 100),
            'completions' => $completions
        ];
    }

    /**
     * Filters activities that a user cannot see due to grouping constraints
     *
     * @param int $userid
     * @param array $exclusions
     * @param bool $onlyrequired
     * @return array
     */
    public function get_filtered_activities(int $userid, array $exclusions, bool $onlyrequired = false): array
    {
        global $USER, $CFG;

        $filteredactivities = [];
        $coursecontext = \context_course::instance($this->course->id);

        $modinfo = get_fast_modinfo($this->course, $USER->id);

        $activities = $this->get_activities_details($this->course);

        if ($onlyrequired) {
            $activities = array_filter($activities, fn($activity) => $activity["required"] == true);
        }

        $canviewhiddenactivities  = has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid);

        foreach ($activities as $activity) {
            $coursemodule = $modinfo->cms[$activity['id']];

            if (!$coursemodule->visible && !$canviewhiddenactivities) {
                continue;
            }

            if (!empty($CFG->enableavailability)) {
                if ($canviewhiddenactivities) {
                    $activity['available'] = true;
                } else {
                    if (isset($coursemodule->available) && !$coursemodule->available && empty($coursemodule->availableinfo)) {
                        continue;
                    }
                    $activity['available'] = $coursemodule->available;
                }
            }

            if (in_array($activity['type'] . '-' . $activity['instance'] . '-' . $userid, $exclusions)) {
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
     * @return array $activities
     */
    public function get_activities_details(\stdClass $course): array
    {
        global $USER, $DB;

        $coursecompletion = new \completion_info($course);
        $coursecompletionactivities = $coursecompletion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY);

        $modinfo = get_fast_modinfo($course, $USER->id);
        $sections = $modinfo->get_sections();

        $coursemodules = $modinfo->instances;
        $activities = [];

        $coursecompletioncriterialist = $DB->get_records('course_completion_criteria', ['course' => $course->id]);

        // Create activities list with completion set.
        foreach ($coursemodules as $module => $instances) {
            $modulename = get_string('pluginname', $module);

            foreach ($instances as $cm) {
                if ($cm->completion === COMPLETION_TRACKING_NONE) {
                    continue;
                }

                $required = false;

                if ($coursecompletioncriterialist) {
                    $cmid = $cm->id;

                    $coursecompletioncriteria = array_filter(
                        $coursecompletioncriterialist,
                        fn($criteria) => $criteria->module == $module && $criteria->moduleinstance == $cmid
                    );
                    $coursecompletioncriteriaid = current(array_map(fn($criteria) => $criteria->id, $coursecompletioncriteria));

                    if (array_filter($coursecompletionactivities, fn($criteria) => $criteria->id == $coursecompletioncriteriaid)) {
                        $required = true;
                    }
                }

                $activities[] = [
                    'type' => $module,
                    'modulename' => $modulename,
                    'id' => $cm->id,
                    'instance' => $cm->instance,
                    'name' => format_string($cm->name),
                    'expected' => $cm->completionexpected,
                    'section' => $cm->sectionnum,
                    'position' => array_search($cm->id, $sections[$cm->sectionnum]),
                    'url' => !is_null($cm->url) && method_exists($cm->url, 'out') ? $cm->url->out() : '',
                    'context' => $cm->context,
                    'icon' => $cm->get_icon_url(),
                    'available' => $cm->available,
                    'required' => $required
                ];
            }
        }

        return $activities;
    }
}
