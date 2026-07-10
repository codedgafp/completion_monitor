<?php

/**
 * Plugin events and observers
 *
 * @package    block_completion_monitor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
$observers = [
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback'  => '\block_completion_monitor\observer::completion_updated',
    ],
    [
        'eventname' => '\core\event\course_module_viewed',
        'callback'  => '\block_completion_monitor\observer::set_course_module_status_in_progress',
    ],
];
