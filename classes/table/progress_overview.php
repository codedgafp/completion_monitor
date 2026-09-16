<?php

declare(strict_types=1);

namespace block_completion_monitor\table;

use core_table\dynamic;
use core\output\checkbox_toggleall;
use core_table\local\filter\filterset;
use block_completion_monitor\service\completion_activities_service;
use block_completion_monitor\service\progress_overview_content_service;

defined('MOODLE_INTERNAL') || die;

/**
 * Class that displays the user progress overview table.
 *
 * @package block_completion_monitor
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_overview extends \table_sql implements dynamic
{
    /**
     * Course id.
     * @var int $courseid
     */
    protected int $courseid;

    /**
     * The course context.
     * @var \context $context
     */
    protected \context $context;

    /**
     * The progress bar html
     * @var string
     */
    protected string $progressbarhtml;

    /**
     * The base url page where the table is render.
     * @var \moodle_url $baseurl
     */
    public $baseurl;

    /**
     * Function that configures the table and must be called for it to be displayed.
     * 
     * The function's parameters are defined in the “sql_table::out()” function.
     * 
     * @param mixed $pagesize
     * @param mixed $useinitialsbar
     * @param mixed $downloadhelpbutton
     * @return void
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '')
    {
        global $OUTPUT;

        $headers = [];
        $columns = [];

        // Definition of the “Select all participants” checkbox (is_master = true)
        $mastercheckbox = $this->define_checkbox(
            true,
            'select-all-participants',
            'select-all-participants',
            get_string('selectall'),
            'sr-only',
            'm-1'
        );

        // $tablelayout = ["column" => "header"];
        $tablelayout = [
            "select" => $OUTPUT->render($mastercheckbox),
            "fullname" => get_string('fullname'),
        ];

        foreach (\core_user\fields::get_identity_fields($this->context) as $field) {
            $tablelayout = array_merge($tablelayout, [$field => \core_user\fields::get_display_name($field)]);
        }

        $tablelayout = array_merge($tablelayout, [
            "lastaccess" => get_string('table_header_lastaccess', 'block_completion_monitor'),
            "completion" => get_string('table_header_completion', 'block_completion_monitor'),
            "activityprogress" => get_string('table_activity_progress', 'block_completion_monitor'),
        ]);

        $this->define_table_layout($headers, $columns, $tablelayout);

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->define_header_column('fullname');

        $this->sortable(true, 'firstname');
        $this->sortable(true, 'email');
        $this->sortable(true, 'lastaccess');
        $this->sortable(true, 'completion');
        $this->no_sorting('select');

        $this->set_default_per_page(20);

        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);
    }

    public function col_select($data): string
    {
        global $OUTPUT;

        // Definition of the row checkbox (is_master = false)
        $checkbox = $this->define_checkbox(
            false,
            "user{$data->id}",
            "user{$data->id}",
            get_string('selectitem', 'moodle', fullname($data)),
            'accesshide',
            'usercheckbox m-1'
        );

        return $OUTPUT->render($checkbox);
    }

    public function col_lastaccess($data): string
    {
        if ($data->lastaccess) {
            return format_time(time() - $data->lastaccess);
        }

        return get_string('never');
    }

    public function col_completion($data): string
    {
        return $data->completion ? (string) $data->completion : "0";
    }

    public function col_activityprogress($data) {
        global $OUTPUT;

        $course = get_course($this->courseid);
        $service = new completion_activities_service($course);

        $userid = (int) $data->id;
        $modinfo = get_fast_modinfo($course, $userid);

        $activities = $service->get_activities_details($modinfo, $userid, true);
        $options["activities_details"] = array_map(fn(/** @var activity_details */ $activity) => $activity->buildrecord(), $activities);

        return $OUTPUT->render_from_template('block_completion_monitor/progress_overview/progress_bar', $options);
    }

    /**
     * Return checkbox_toggleall template.
     * 
     * @param bool $ismaster
     * @param string $id
     * @param string $name
     * @param string $label
     * @param string $labelclasses
     * @param string $classes
     * @param bool $checked
     * @return checkbox_toggleall
     */
    private function define_checkbox(bool $ismaster, string $id, string $name, string $label, string $labelclasses, string $classes, bool $checked = false): checkbox_toggleall
    {
        return new checkbox_toggleall('progress-overview-table', $ismaster, [
            'id' => $id,
            'name' => $name,
            'label' => $label,
            'labelclasses' => $labelclasses,
            'classes' => $classes,
            'checked' => $checked,
        ]);
    }

    /**
     * Fill header and columns for table layout.
     * 
     * @param array $headers
     * @param array $columns
     * @param array $tablelayout
     * @return void
     */
    private function define_table_layout(array &$headers, array &$columns, array $tablelayout): void
    {
        foreach ($tablelayout as $column => $header) {
            $headers[] = $header;
            $columns[] = $column;
        }
    }

    /**
     * Define the SQL query to run for the table's content.
     * 
     * The function's parameters are defined in the “sql_table::query_db()” function.
     * 
     * @param mixed $pagesize
     * @param mixed $useinitialsbar
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true): void
    {
        global $DB;

        $posearch = new progress_overview_search($this->courseid, $this->context, $this->filterset);

        // Get where and params from the name filter
        list($where, $params) = $this->get_sql_where();

        // Défine in out() function
        $sort = $this->get_sql_sort();

        $this->use_pages = true;

        $rawdata = $posearch->get_participants($where, $params, $sort, $this->get_page_start(), $this->get_page_size());

        $total = $rawdata->current()->fullcount ?? 0;
        $this->pagesize($pagesize, $total);

        $this->rawdata = [];
        foreach ($rawdata as $user) {
            $this->rawdata[$user->id] = $user;
        }
        $rawdata->close();

        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars(true);
        }
    }

    /**
     * Set filters and build table structure.
     *
     * @param filterset $filterset
     */
    public function set_filterset(filterset $filterset): void
    {
        $this->courseid = $filterset->get_filter(progress_overview_filterset::COURSEID)->current();
        $this->context = \context_course::instance($this->courseid, MUST_EXIST);

        parent::set_filterset($filterset);
    }

    /**
     * Guess the base url for the progress_overview table.
     */
    public function guess_base_url(): void
    {
        $this->baseurl = new \moodle_url('blocks/completion_monitor/progress_overview.php', ['courseid' => $this->courseid]);
    }

    /**
     * Check if the user has the capability to access this table.
     *
     * @return bool
     */
    public function has_capability(): bool
    {
        return has_capability('report/progress:view', $this->context);
    }

    /**
     * Get the context of the current table.
     *
     * Note: This function should not be called until after the filterset has been provided.
     *
     * @return \context
     */
    public function get_context(): \context
    {
        return $this->context;
    }

    public function wrap_html_start(): void
    {
        $contentservice = new progress_overview_content_service($this, $this->courseid);

        $checkallhtml = $contentservice->get_checkall_html();
        $selecthtml = $contentservice->get_bulkaction_html();
        $messagebutton = $contentservice->get_messagebutton_html();

        $or = \html_writer::tag("p", get_string('or', 'availability'), ["class" => "m-0"]);

        echo \html_writer::div($checkallhtml . $messagebutton . $or . $selecthtml, 'd-flex justify-content-end align-items-center mb-2');
    }
}
