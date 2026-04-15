<?php

/**
 * Block activities completion course monitoring.
 *
 * @package    block_activities_completion_course_monitoring
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



function block_completion_monitor_user_preferences()
{

    // Register a user preference for each course to track whether the block is open or closed
    // Matches: block_completion_monitor_{courseid}_opened
    $preferences['/^block_completion_monitor_\d+_opened$/'] = [
        'type' => PARAM_BOOL,
        'null' => NULL_ALLOWED,
        'default' => 0,
        'isregex' => true,
        'permissioncallback' => function ($user, $preferencename) {
            // Allow users to set their own comeback to section preference
            return \core_user::is_current_user($user);
        },
    ];

    return $preferences;
}
