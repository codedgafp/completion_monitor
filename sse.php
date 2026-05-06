<?php

use block_completion_monitor\model\template_context;
use block_completion_monitor\service\completion_activities_service;

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

$blockcompletioncached = cache::make('block_completion_monitor', 'block_completion_updated');

$percentagekey = "completion_percentage_{$userid}_{$courseid}";
$resetactivitieskey = "completion_reset_activities_{$userid}_{$courseid}";
$cachekeys = [$percentagekey, $resetactivitieskey];

$start = time();
$maxDuration = 20;
$events = [];

while ((time() - $start) < $maxDuration) {
    if (connection_aborted()) {
        break;
    }

    $events = [];

    $blockcompletioncachedvalues = $blockcompletioncached->get_many($cachekeys);

    $updatedpercentage = $blockcompletioncachedvalues[$percentagekey];
    $needactivitiesreset = $blockcompletioncachedvalues[$resetactivitieskey];

    $coursecompletionpercentage = -1;
    $templatecontext = [];

    if ($updatedpercentage !== false) {
        $service = new completion_activities_service($course);
        $coursecompletiondetails = $service->get_course_completion_details($userid);

        $coursecompletionpercentage = $coursecompletiondetails['percentage'];
    }

    if ($needactivitiesreset !== false) {
        $templatecontextmodel = new template_context($course);
        $templatecontext = $templatecontextmodel->get_template_context();
    }

    if ($coursecompletionpercentage >= 0 && !empty($templatecontext)) {
        $events[] = build_completion_percentage_event_data($coursecompletionpercentage, $templatecontext);
    }

    if (!empty($templatecontext)) {
        $events[] = build_activities_event_data($templatecontext);
    }

    if (count($events) > 0) {
        $blockcompletioncached->delete_many($cachekeys);

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
    $activities_details = array_map(function ($activity) {
        $activity['icon'] = $activity['icon'] ?->out();
        return $activity;
    }, $activities_details);

    $data = json_encode([
        'activities_details' => $activities_details
    ]);

    return "event: activities_update\n"
        . "data: $data"
        . "\n\n";
}
