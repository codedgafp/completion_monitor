<?php

declare(strict_types=1);

namespace block_completion_monitor\table;

use core_table\local\filter\filterset;
use core_table\local\filter\string_filter;
use core_table\local\filter\integer_filter;

/**
 * User progress overview table filterset.
 *
 * @package block_completion_monitor
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_overview_filterset extends filterset {
    public const string COURSEID = 'courseid';
    public const string KEYWORDS = 'keywords';
    public const string ROLES = 'roles';
    public const string GROUPS = 'groups';
    public const string GROUPINGS = 'groupings';

    /**
     * Get the required filters.
     * 
     * @return array{courseid: string}
     */
    public function get_required_filters(): array
    {
        return [
            self::COURSEID => integer_filter::class,
        ];
    }

    /**
     * Get the optional filters.
     *
     * @return array
     */
    public function get_optional_filters(): array {
        return [
            self::KEYWORDS => string_filter::class,
            self::ROLES => integer_filter::class,
            self::GROUPS => integer_filter::class,
            self::GROUPINGS => integer_filter::class,
        ];
    }
}
