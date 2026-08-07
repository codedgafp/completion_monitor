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
        protected progress_overview $table
    ) {}

    public function export_for_template(renderer_base $output): array|stdClass
    {
        global $PAGE;

        $data = new stdClass();

        $this->table->define_baseurl($PAGE->url);

        ob_start();

        $this->table->out(20, true);
        $tablehtml = ob_get_clean();

        $data->tablehtml = $tablehtml;

        return $data;
    }
}