<?php

namespace block_completion_monitor\output;

use core\output\renderable;
use core\output\templatable;
use core\output\renderer_base;
use block_completion_monitor\table\progress_overview;
use block_completion_monitor\service\progress_overview_content_service;

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
        protected \context $context,
        protected int $courseid
    ) {}

    public function export_for_template(renderer_base $output): array|\stdClass
    {
        global $PAGE;

        $contentservice = new progress_overview_content_service($this->table, $this->courseid);

        $this->table->define_baseurl($PAGE->url);

        $data = new \stdClass();

        $data->tablehtml = $this->get_table_html();

        $data->tableuniqueid = $this->table->uniqueid;
        $data->filterhtml = $contentservice->get_filter_html($output, $this->context);
        $data->countusers = $contentservice->get_count_users_html();
        $data->hiddeninputshtml = $contentservice->get_hidden_inputs_html();

        return $data;
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
