<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_completion_monitor_install() {
    global $DB;

    $courses = get_courses();
    
    foreach ($courses as $course) {
        if ($course->id == SITEID) continue;

        $context = context_course::instance($course->id);

        $exists = $DB->record_exists('block_instances', [
            'blockname'       => 'completion_monitor',
            'parentcontextid' => $context->id
        ]);

        if (!$exists) {
            $record = new stdClass();
            $record->blockname          = 'completion_monitor';
            $record->parentcontextid    = $context->id;
            $record->showinsubcontexts  = 0;
            $record->pagetypepattern    = 'course-view-*';
            $record->subpagepattern     = null;
            $record->defaultregion      = 'top-block';
            $record->defaultweight      = 1;
            $record->timecreated        = time();
            $record->timemodified       = time();

            $DB->insert_record('block_instances', $record);
        }
    }

    return true;
}