<?php

require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$PAGE->set_url('/blocks/completion_monitor/progress_overview.php', [
    'courseid' => $courseid,
]);

$course = get_course($courseid);

$context = context_course::instance($course->id);
$PAGE->set_context($context);

unset($courseid);

require_login($course);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_title(get_string('page_header_title', 'block_completion_monitor', $course->shortname));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('page_title', 'block_completion_monitor'), $PAGE->url);
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('block_completion_monitor/progress_overview/index', $context);

echo $OUTPUT->footer();
