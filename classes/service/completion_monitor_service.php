<?php

namespace block_completion_monitor\service;

use moodle_database;
use block_completion_monitor\helper\progress;
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
     * @return bool
     */
    public function activities_has_completion(): bool
    {
        return $this->accmrepository->activity_has_completion($this->course->id);
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
     * @param int $userid
     * @param int $cmid
     * @return string
     */
    public function course_module_viewed_preference_name(int $userid, int $cmid): string
    {
        return "course_module_viewed_" . $userid . "_" . $cmid;
    }

    /**
     * @return string
     */
    public function block_completion_monitor_opened_preference_name(): string
    {
        return "block_completion_monitor_" . $this->course->id . "_opened";
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
