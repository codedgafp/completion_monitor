<?php

namespace block_completion_monitor\service;

use block_completion_monitor\output\progress_overview_filter;
use block_completion_monitor\table\progress_overview;

/**
 * Activities Completion Course Monitor - Service for progress overview page content render.
 * 
 * @package     block_completion_monitor
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class progress_overview_content_service
{
    public function __construct(
        protected progress_overview $table,

        protected ?int $courseid = null
    ) {}

    /**
     * Render check all button html
     * 
     * @return string
     */
    public function get_checkall_html(): string
    {
        $checkallhtml = "";

        if ($this->table->get_page_size() < $this->table->totalrows) {
            $label = get_string('selectalluserswithcount', 'moodle', $this->table->totalrows);
            $checkallhtml = \html_writer::empty_tag('input', [
                'type' => 'button',
                'id' => 'checkall',
                'class' => 'btn btn-secondary',
                'value' => $label,
                'data-target-page-size' => TABLE_SHOW_ALL_PAGE_SIZE,
            ]);
        }

        return $checkallhtml;
    }

    /**
     * Render bulk action select html
     * 
     * @return string
     */
    public function get_bulkaction_html(): string
    {
        $params = ['operation' => 'download_progress_users'];
        $downloadoptions = [];
        $displaylist = [];
        $selectactionparams = [
            'id' => 'formactionid',
            'class' => 'ms-2',
            'data-action' => 'toggle',
            'data-togglegroup' => 'progress-overview-table',
            'data-toggle' => 'action',
            'disabled' => true
        ];

        $formats = \core_plugin_manager::instance()->get_plugins_of_type('dataformat');
        foreach ($formats as $format) {
            if ($format->is_enabled()) {
                $params = ['operation' => 'download_progress_users', 'dataformat' => $format->name];
                $url = new \moodle_url('bulkchange.php', $params);
                $downloadoptions[$url->out(false)] = get_string('dataformat', $format->component);
            }
        }

        if (!empty($downloadoptions)) {
            $displaylist[] = [get_string('downloadas', 'table') => $downloadoptions];
        }

        $selecthtml = \html_writer::select($displaylist, 'formaction', '', ['' => 'choosedots'], $selectactionparams);

        return \html_writer::tag('div', $selecthtml);
    }

    /**
     * Render send message button html
     * 
     * @return string
     */
    public function get_messagebutton_html(): string
    {
        $attributes = [
            'type' => 'button',
            'id' => 'send-message-button',
            'class' => 'btn btn-primary mx-2',
            'data-action' => 'toggle',
            'data-toggle' => 'action',
            'data-togglegroup' => 'progress-overview-table',
            'data-courseid' => $this->courseid,
            'disabled' => 'disabled',
        ];

        $icon = \html_writer::tag('i', '', [
            'class' => 'icon fa-solid fa-paper-plane fa-fw mr-1',
            'aria-hidden' => 'true',
        ]);

        $label = get_string('sendmessage_button', 'block_completion_monitor');

        return \html_writer::tag('button', $icon . $label, $attributes);
    }

    /**
     * Render filter html
     * 
     * @param \renderer_base $output
     * @param \context $context
     * @return bool|string
     */
    public function get_filter_html(\renderer_base $output, \context $context): string|bool
    {
        $filter = new progress_overview_filter($context, $this->table->uniqueid);

        return $output->render_from_template(
            'block_completion_monitor/progress_overview/progress_overview_filter',
            $filter->export_for_template($output)
        );
    }

    /**
     * Render count users
     * 
     * @return string
     */
    public function get_count_users_html(): string
    {
        $countusersfound = get_string('countparticipantsfound', 'core_user', $this->table->totalrows);

        return \html_writer::tag('p', $countusersfound, ['data-region' => 'progress-overview-count']);      
    }

    /**
     * Render hidden input html
     * 
     * @return string
     */
    public function get_hidden_inputs_html(): string
    {
        global $PAGE;

        $courseidhtml = "<input type='hidden' name='courseid' value='$this->courseid' />";
        $sesskeyhtml = "<input type='hidden' name='sesskey' value='" . sesskey() . "' />";
        $returntohtml = "<input type='hidden' name='returnto' value='" . s($PAGE->url->out(false)) . "' />";

        return \html_writer::tag('div', $courseidhtml . $sesskeyhtml . $returntohtml);
    }
}
