<?php
## MEN-1350: Remove activities_completion_course_monitoring custom lang string.

defined('MOODLE_INTERNAL') || die();

global $DB;

$component = $DB->get_record('tool_customlang_components', ['name' => 'block_completion_monitor']);
if ($component) {
    $DB->delete_records('tool_customlang', [
        'componentid' => $component->id,
        'stringid'    => 'activities_completion_course_monitoring:addinstance',
    ]);
}
