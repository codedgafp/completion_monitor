<?php

namespace block_completion_monitor;

use cache;
use stdClass;
use block_completion_monitor\service\completion_monitor_service;
use block_completion_monitor\service\completion_activities_service;

defined('MOODLE_INTERNAL') || die();

class observer
{
    public static function completion_updated(\core\event\course_module_completion_updated $event): void
    {
        $data = $event->get_data();

        $userid = $data['relateduserid'];
        $courseid = $data['courseid'];

        $cachepercentage = cache::make('block_completion_monitor', 'block_completion_updated');
        $cachepercentage->set_many([
            "completion_percentage_{$userid}_{$courseid}" => true,
            "completion_reset_activities_{$userid}_{$courseid}" => true,
        ]);
    }

    public static function set_course_module_status_in_progress(\core\event\course_module_viewed $event): void
    {
        $data = $event->get_data();

        $userid = $data['userid'];
        $courseid = $data['courseid'];
        $cmid = $data["contextinstanceid"];

        $course = get_course($courseid);
        $service = new completion_monitor_service($course);

        $userpreferencename = $service->course_module_viewed_preference_name($userid, $cmid);

        if (get_user_preferences($userpreferencename, null, $userid)) {
            return;
        }

        $userpreferencedata = json_encode([
            "time_access" => time(),
            "user_id" => $userid,
            "course_module_id" => $cmid
        ]);
        set_user_preference($userpreferencename, $userpreferencedata);

        $cacheactivitiesreset = cache::make('block_completion_monitor', 'block_completion_updated');
        $cacheactivitiesreset->set("completion_reset_activities_{$userid}_{$courseid}", true);
    }

    /**
     * When a completion is updated, set the user_completion processed value to 0
     * 
     * @param \core\event\course_module_completion_updated $event
     * @return void
     */
    public static function make_completion_to_processed(\core\event\course_module_completion_updated $event): void
    {
        global $DB;

        $data = $event->get_data();
        $userid = $data['relateduserid'];
        $courseid = $data['courseid'];

        $usercompletion = $DB->get_record('user_completion', ['userid' => $userid, 'courseid' => $courseid]);

        $completionservice = new completion_activities_service(get_course($courseid));
        $usercoursecompletion = $completionservice->get_course_completion_details($userid)["percentage"];

        if ($usercompletion) {
            $usercompletion->completion = $usercoursecompletion;
            $usercompletion->lastupdate = time();
            $usercompletion->processed = 0;

            $DB->update_record('user_completion', $usercompletion);
        } else {
            $usercompletion = new stdClass();
            $usercompletion->userid = $userid;
            $usercompletion->courseid = $courseid;
            $usercompletion->completion = $usercoursecompletion;
            $usercompletion->lastupdate = time();
            $usercompletion->processed = 0;

            $DB->insert_record('user_completion', $usercompletion);
        }
    }
}
