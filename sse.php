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

$cachepercentage = cache::make('block_completion_monitor', 'completion_percentage');
$percentagekey = "completion_percentage_{$userid}_{$courseid}";

$cacheactivitiesreset = cache::make('block_completion_monitor', 'activities_reset');
$resetactivitieskey = "completion_reset_activities_{$userid}_{$courseid}";

$start = time();
$maxDuration = 20;
$events = [];

while ((time() - $start) < $maxDuration) {
    if (connection_aborted()) {
        break;
    }

    $events = [];

    $completionvalues = $cachepercentage->get_many([$percentagekey]);
    $percentage = $completionvalues[$percentagekey];
    $cacheactivitiesresetvalue = $cacheactivitiesreset->get_many([$resetactivitieskey]);
    $needactivitiesreset = $cacheactivitiesresetvalue[$resetactivitieskey];
    $templateContext = [];

    if ($percentage !== false || $needactivitiesreset !== false) {
        $completiondetailsmodel = new template_context($course);
        $templateContext = $completiondetailsmodel->get_template_context();
    }

    if ($percentage !== false && !empty($templateContext)) {
        $events[] = build_completion_percentage_event_data($percentage, $templateContext);
    }

    if ($needactivitiesreset !== false && !empty($templateContext)) {
        $events[] = build_activities_event_data($templateContext);
    }


    if (count($events) > 0) {
        $cachepercentage->delete_many([$percentagekey]);
        $cacheactivitiesreset->delete_many([$resetactivitieskey]);

        foreach ($events as $event) {
            echo $event;
        }
        echo "event: done\ndata: {}\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
        break;
    }

    sleep(1);
}

/**
 * Set the information for "completion_update" event
 * 
 * @param int $percentage
 * @param array $templateContext
 * @return string
 */
function build_completion_percentage_event_data(int $percentage, array $templateContext): string
{
    $data = json_encode([
        'completion_percentage' => $percentage,
        'completion_details' => $templateContext
    ]);

    return "event: completion_update\n"
        . "data: $data"
        . "\n\n";
}

/**
 * Set the information for "activities_update" event
 * 
 * @param array $templateContext
 * @return string
 */
function build_activities_event_data(array $templateContext): string
{
    $activities_details = $templateContext['activities_details'] ?? [];
    $activities_details = array_map(function($activity) {
        $activity['icon'] = $activity['icon'] ? $activity['icon']->out() : null;
        return $activity;
    }, $activities_details);

    $data = json_encode([
        'activities_details' => $activities_details
    ]);

    return "event: activities_update\n"
        . "data: $data"
        . "\n\n";
}
