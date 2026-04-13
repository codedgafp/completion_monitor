<?php

use block_completion_monitor\model\template_context;

require_once(__DIR__ . '/../../config.php');

require_login();

$userid = required_param('userid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);

$context = context_course::instance($courseid);
$PAGE->set_context($context);

\core\session\manager::write_close();

if ($userid != $USER->id) {
    require_capability('blocks/completion_monitor:viewothers', context_system::instance());
}

// Headers SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$cache = cache::make('block_completion_monitor', 'completion_percentage');
$percentagekey = "completion_percentage_{$userid}_{$courseid}";

$start = time();
$maxDuration = 20;

$flush = false;

while ((time() - $start) < $maxDuration) {
    if (connection_aborted()) {
        break;
    }

    $completionvalues = $cache->get_many([$percentagekey]);
    $percentage = $completionvalues[$percentagekey];

    if ($percentage !== false) {
        flush_completion_percentage_event_data($course, $percentage);
        $flush = true;
        break;
    }

    if ($flush) {
        $cache->delete_many([$percentagekey]);

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();

        $flush = false;
    }

    sleep(1);
}

/**
 * Set the information for "completion_update" event
 * 
 * @param stdClass $course
 * @param int $percentage
 * @return void
 */
function flush_completion_percentage_event_data(\stdClass $course, int $percentage)
{
    $completiondetailsmodel = new template_context($course);

    $data = json_encode([
        'completion_percentage' => $percentage,
        'completion_details' => $completiondetailsmodel->get_template_context()
    ]);

    echo "event: completion_update\n";
    echo "data: $data";
    echo "\n\n";
}
