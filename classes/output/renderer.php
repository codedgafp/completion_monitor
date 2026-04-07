<?php
namespace block_completion_monitor\output;

use plugin_renderer_base;
use moodle_url;

use block_contents;

class renderer extends plugin_renderer_base
{

    private function can_access_report()
    {
        global $COURSE;

        $context = \context_course::instance($COURSE->id);

        return has_capability('report/progress:view', $context);
    }

    public function render_block(array $templatecontext): string
    {
        global $COURSE;

        $reporturl = $this->can_access_report() ? new moodle_url(
            '/report/progress/index.php',
            ['course' => $COURSE->id]
        ) : '';

        return $this->render_from_template(
            'block_completion_monitor/block',
            [
                "reporturl" => $reporturl,
                "can_access_report" => $this->can_access_report(),
                ...$templatecontext
            ]
        );
    }

    /**
     * Summary of render_block_with_controls
     * @param \block_base $accm_block
     * @return string
     */
    public function render_block_with_controls(\block_base $accm_block, array $templatecontext): string
    {
        global $PAGE, $OUTPUT;

        $contenttext = $this->render_block($templatecontext);

        $bc = new block_contents();

        $bc->blockinstanceid = $accm_block->instance->id;
        $bc->attributes['id'] = 'inst' . $accm_block->instance->id;
        $bc->attributes['class'] = 'block_' . $accm_block->instance->blockname . ' block';
        $bc->attributes['data-block'] = $accm_block->instance->blockname;

        $bc->content = $contenttext;

        if ($PAGE->user_is_editing() && $accm_block->instance_can_be_edited()) {
            $bc->controls = $PAGE->blocks->edit_controls($accm_block);
            $bc->controls = array_filter(
                $bc->controls,
                fn($c) => !str_contains($c->attributes['class'] ?? '', 'editing_move')
            );
        }

        return $OUTPUT->block($bc, BLOCK_POS_TOP);
    }
}
