<?php

namespace block_completion_monitor\service;

use context_course;
use moodle_database;
use core\context\course;
use block_completion_monitor\helper\progress;
use block_completion_monitor\model\block_instance_record;
use block_completion_monitor\repository\completion_monitor_repository;

require_once($CFG->libdir . '/completionlib.php');

/**
 * Activities Completion Course Monitor service.
 * 
 * @package     block_completion_monitor
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_monitor_service
{
    use progress;

    /**
     * @var bool|course
     */
    private bool|course $contextcourse;

    /**
     * Completion Course Monitor repository
     * @var completion_monitor_repository
     */
    protected completion_monitor_repository $accmrepository;

    /**
     * Activities Completion Course Monitor repository
     * @var completion_activities_service
     */
    protected completion_activities_service $completionactivitiesservice;

    /**
     * Moodle Database
     * @var moodle_database
     */
    protected moodle_database $db;

    public function __construct(
        private \stdClass $course
    ) {
        global $DB;

        $this->contextcourse = context_course::instance($this->course->id);
        $this->accmrepository = new completion_monitor_repository();
        $this->completionactivitiesservice = new completion_activities_service($this->course);
        $this->db = $DB;
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
     * @param int $userid
     * @param int $cmid
     * @return bool
     */
    public function course_module_has_beed_viewed(int $userid, int $cmid): bool
    {
        $preferencename = $this->course_module_viewed_preference_name($userid, $cmid);
        return $this->db->record_exists('user_preferences', ['name' => $preferencename]);
    }

    /**
     * @return bool
     */
    public function should_display_block(): bool
    {
        global $USER;

        $isusercourseadmin = $this->is_user_course_manager($USER->id);

        if ($this->course->enablecompletion == COMPLETION_DISABLED) {
            return $isusercourseadmin;
        }

        if ($isusercourseadmin) {
            return true;
        }

        $exclusions = $this->accmrepository->get_grade_exclusions($this->course->id, $USER->id);
        $activities = $this->completionactivitiesservice->get_filtered_activities($USER->id, $exclusions);

        return !empty($activities); 
    }

    /**
     * @param int $userid
     */
    public function is_user_course_manager(int $userid)
    {
        return has_capability('moodle/course:manageactivities', $this->contextcourse, $userid);
    }
}
