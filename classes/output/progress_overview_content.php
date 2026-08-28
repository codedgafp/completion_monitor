<?php

namespace block_completion_monitor\output;

use stdClass;
use core\output\renderable;
use core\output\templatable;
use core\output\renderer_base;
use block_completion_monitor\table\progress_overview;

/**
 * Renderable for the table.
 *
 * @package block_completion_monitor
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_overview_content implements renderable, templatable
{
    public function __construct(
        protected progress_overview $table,
        protected \context $context
    ) {}

    public function export_for_template(renderer_base $output): array|stdClass
    {
        global $PAGE;

        $this->table->define_baseurl($PAGE->url);

        $data = new stdClass();
        $data->filterhtml = $this->get_filter_html($output);
        $data->tablehtml = $this->get_table_html();

        return $data;
    }

    /**
     * Render filter html
     * 
     * @param renderer_base $output
     * @return bool|string
     */
    private function get_filter_html(renderer_base $output): string|bool
    {
        $filter = new progress_overview_filter($this->context, $this->table->uniqueid);
        return $output->render_from_template(
            'block_completion_monitor/progress_overview/progress_overview_filter',
            $filter->export_for_template($output)
        );
    }

    /**
     * Render table html
     * 
     * @return bool|string
     */
    private function get_table_html(): string|bool
    {
        ob_start();

        $this->table->out(20, true);

        return ob_get_clean();
    }
}
