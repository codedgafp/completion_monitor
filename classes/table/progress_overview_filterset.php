<?php

declare(strict_types=1);

namespace block_completion_monitor\table;

use core_table\local\filter\filterset;
use core_table\local\filter\integer_filter;

/**
 * User progress overview table filterset.
 *
 * @package block_completion_monitor
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_overview_filterset extends filterset {
    /**
     * Get the required filters.
     * 
     * @return array{courseid: string}
     */
    public function get_required_filters(): array
    {
        return [
            'courseid' => integer_filter::class,
        ];
    }
}
