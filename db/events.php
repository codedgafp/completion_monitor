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
        'eventname' => '\core\event\course_module_created',
        'callback'  => '\block_completion_monitor\observer::course_module_changed',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback'  => '\block_completion_monitor\observer::course_module_changed',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback'  => '\block_completion_monitor\observer::course_module_changed',
    ]
];
