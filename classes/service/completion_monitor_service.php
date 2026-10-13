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

    /**
     * Viewed course modules already read during this request, keyed by user id.
     * @var array<int, array<int, bool>>
     */
    private static array $viewedcache = [];

    /**
     * Empty the request level cache. Intended for unit tests.
     *
     * @return void
     */
    public static function reset_caches(): void
    {
        self::$viewedcache = [];
    }

    public function __construct(
        private ?\stdClass $course = null
    ) {
        global $DB;
        $this->db = $DB;

        $this->accmrepository = new completion_monitor_repository();

        if ($course !== null) {
            $this->contextcourse = context_course::instance($this->course->id);
            $this->completionactivitiesservice = new completion_activities_service($this->course);
        }
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
        return isset($this->get_viewed_course_modules($userid)[$cmid]);
    }

    /**
     * @param int $userid
     * @return array<int, bool>
     */
    private function get_viewed_course_modules(int $userid): array
    {
        if (isset(self::$viewedcache[$userid])) {
            return self::$viewedcache[$userid];
        }

        self::$viewedcache[$userid] = [];

        $names = [];
        foreach (get_fast_modinfo($this->course)->get_cms() as $cm) {
            $names[$this->course_module_viewed_preference_name($userid, (int) $cm->id)] = (int) $cm->id;
        }

        if (empty($names)) {
            return self::$viewedcache[$userid];
        }

        [$insql, $params] = $this->db->get_in_or_equal(array_keys($names), SQL_PARAMS_NAMED);
        $found = $this->db->get_fieldset_select('user_preferences', 'name', "name $insql", $params);

        foreach ($found as $name) {
            if (isset($names[$name])) {
                self::$viewedcache[$userid][$names[$name]] = true;
            }
        }

        return self::$viewedcache[$userid];
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

    public function delete_duplicates_users_completion(array $duplicates)
    {
        foreach ($duplicates as $usercompletion) {
            $duplicatesrow = $this->db->get_records_select(
                'user_completion',
                "userid = :userid AND courseid = :courseid",
                [
                    'userid'    => $usercompletion->userid,
                    'courseid'  => $usercompletion->courseid
                ],
                'lastupdate ASC',
                'id',
            );

            array_shift($duplicatesrow);

            foreach ($duplicatesrow as $duplicate) {
                $this->db->delete_records('user_completion', ["id" => $duplicate->id]);
            }
        }
    }
}
