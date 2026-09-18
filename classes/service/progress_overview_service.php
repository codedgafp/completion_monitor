<?php

namespace block_completion_monitor\service;

use block_completion_monitor\repository\progress_overview_repository;

/**
 * Activities Completion Course Monitor - Progress overview page service.
 * 
 * @package     block_completion_monitor
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class progress_overview_service
{
    /**
     * Moodle course object.
     * @var \stdClass
     */
    protected \stdClass $course;

    public function __construct(
        protected int $courseid,
        protected \context $context,

        protected ?\moodle_database $db = null,
        protected ?progress_overview_repository $repository = null
    ) {
        global $DB;

        $this->db = $DB;
        $this->repository = new progress_overview_repository();
        $this->course = get_course($courseid);
    }

    /**
     * Download into selected file format users from the progress overview page.
     * 
     * @param mixed $dataformat
     * @param array $userids
     * @return void
     */
    public function download_progress_overview_users($dataformat, array $userids): void
    {
        // Check permissions.
        $pagecontext = ($this->courseid == SITEID) ? \context_system::instance() : $this->context;

        if (course_can_view_participants($pagecontext)) {
            $plugins = \core_plugin_manager::instance()->get_plugins_of_type('dataformat');

            if (isset($plugins[$dataformat]) && $plugins[$dataformat]->is_enabled()) {
                $columnnames = [
                    'firstname' => get_string('firstname'),
                    'lastname' => get_string('lastname'),
                ];

                // Retrieve all identity fields required for users.
                $userfieldsapi = \core_user\fields::for_identity($this->context);
                $userfields = $userfieldsapi->get_sql('u', true);

                $identityfields = array_keys($userfields->mappings);
                foreach ($identityfields as $field) {
                    $columnnames[$field] = \core_user\fields::get_display_name($field);
                }

                $columnnames['lastaccess'] = get_string('table_header_lastaccess', 'block_completion_monitor');
                $columnnames['completion'] = get_string('table_header_completion', 'block_completion_monitor');

                $completionactivitiesservice = new completion_activities_service($this->course);
                $activities = $completionactivitiesservice->get_activities_details();
                foreach ($activities as $index => $activity) {
                    $columnnames["progress_activity_$index"] = get_string('export_header_activity_progress', 'block_completion_monitor', $activity->get_name());
                }

                $userstodownload = $this->repository->get_users_to_download($this->context, $userids, $userfields, $this->courseid);

                // Provide callback to pre-process all records ensuring user identity fields are escaped if HTML supported.
                \core\dataformat::download_data(
                    'courseid_' . $this->courseid . '_progress_overview',
                    $dataformat,
                    $columnnames,
                    $userstodownload,
                    function (\stdClass $record, bool $supportshtml) use ($identityfields, $completionactivitiesservice): \stdClass {
                        if ($supportshtml) {
                            foreach ($identityfields as $identityfield) {
                                $record->{$identityfield} = s($record->{$identityfield});
                            }
                        }

                        $modinfo = get_fast_modinfo($this->course, $record->userid);
                        $activities = $completionactivitiesservice->get_activities_details($modinfo, $record->userid, true);

                        foreach ($activities as $index => $activity) {
                            $indexname = "progress_activity_$index";
                            $record->$indexname = get_string($activity->get_status(), 'block_completion_monitor');
                        }

                        unset($record->userid);

                        return $record;
                    }
                );

                $userstodownload->close();
            }
        }
    }
}
