<?php
namespace block_completion_monitor\output;

use plugin_renderer_base;
use context_course;
use moodle_url;

class renderer extends plugin_renderer_base {
    
    private function can_access_report() {
        global $COURSE;

        $context = \context_course::instance($COURSE->id);
        
        return  has_capability('report/progress:view', $context);

    }


    public function render_block(): string {
    global $COURSE;
        $reporturl = $this->can_access_report() ? new moodle_url(
            '/report/progress/index.php',
            ['course' => $COURSE->id]
        ): '' ;

        
        return $this->render_from_template(
            'block_completion_monitor/block',
            ["reporturl" => $reporturl,
            "can_access_report" => $this->can_access_report()
            ]
        );
    }
}