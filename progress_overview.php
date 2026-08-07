<?php

use core_table\local\filter\filter;
use core_table\local\filter\integer_filter;
use block_completion_monitor\output\progress_overview_content;
use block_completion_monitor\table\progress_overview;
use block_completion_monitor\table\progress_overview_filterset;

require_once('../../config.php');

// Get required params
$courseid = required_param('courseid', PARAM_INT);

// Config page
$PAGE->set_url('/blocks/completion_monitor/progress_overview.php', [
    'courseid' => $courseid,
]);

$course = get_course($courseid);

$context = context_course::instance($course->id);
$PAGE->set_context($context);

unset($courseid);

require_login($course);
require_capability('report/progress:view', $context);

$PAGE->set_title(get_string('page_header_title', 'block_completion_monitor', $course->shortname));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('page_title', 'block_completion_monitor'), $PAGE->url);
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();

// Setup table
$table = new progress_overview("progress-overview-$course->id");

// Setup filterset
$filterset = new progress_overview_filterset();

$filterset->add_filter(new integer_filter('courseid', filter::JOINTYPE_DEFAULT, [(int) $course->id]));

$table->set_filterset($filterset);

// Render template with table
$renderable = new progress_overview_content($table);
$data = $renderable->export_for_template($OUTPUT);

echo $OUTPUT->render_from_template('block_completion_monitor/progress_overview/index', $data);

echo $OUTPUT->footer();
