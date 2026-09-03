<?php

namespace block_completion_monitor\repository;

/**
 * Activities Completion Course Monitoring - Progress overview page repository.
 * 
 * @package     block_completion_monitor
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class progress_overview_repository
{
    public function __construct(
        protected ?\moodle_database $db = null
    ) {
        global $DB;

        $this->db = $DB;
    }

    /**
     * Pre-processed users to be download
     * 
     * @param \context $context
     * @param array $userids
     * @param \stdClass $userfields
     * @param int $courseid
     * @return \moodle_recordset
     */
    public function get_users_to_download(\context $context, array $userids, \stdClass $userfields, int $courseid): \moodle_recordset
    {
        // Ensure users are enrolled in this course context, further limiting them by selected userids.
        [$enrolledsql, $enrolledparams] = get_enrolled_sql($context);
        [$useridsql, $useridparams] = $this->db->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');
        [$userordersql, $userorderparams] = users_order_by_sql('u', null, $context);

        $params = array_merge($userfields->params, $enrolledparams, $useridparams, $userorderparams);

        $sql = "SELECT u.firstname,
                    u.lastname {$userfields->selects},
                    " . $this->get_timeaccess_date_sql("ul.timeaccess") . " AS lastaccess,
                    coalesce(uc.completion, 0)
                FROM {user} u
                {$userfields->joins}
                INNER JOIN ({$enrolledsql}) je ON je.id = u.id
                LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid)
                LEFT JOIN {user_completion} uc ON (uc.userid = u.id AND uc.courseid = :courseid2)
                WHERE u.id {$useridsql}
                ORDER BY {$userordersql}";
        $params = array_merge($params, ["courseid" => $courseid, "courseid2" => $courseid]);

        return $this->db->get_recordset_sql($sql, $params);
    }

    /**
     * Get the timeaccess adapt to the database type
     * 
     * @param string $column
     * @throws \coding_exception
     * @return string
     */
    private function get_timeaccess_date_sql(string $column): string
    {
        global $CFG, $USER;

        $timezone = \core_date::get_user_timezone($USER);

        switch ($CFG->dbtype) {

            case 'mysqli':
            case 'mariadb':
                return "CONVERT_TZ(FROM_UNIXTIME($column), '+00:00', " . $this->mysqli_style_offset_or_named($timezone) . ")";

            case 'pgsql':
                return "TO_TIMESTAMP($column) AT TIME ZONE '$timezone'";

            default:
                throw new \coding_exception('Type de base de données non géré : ' . $CFG->dbtype);
        }
    }

    private function mysqli_style_offset_or_named(string $timezone): string
    {
        return "'$timezone'";
    }
}
