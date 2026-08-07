<?php

namespace block_completion_monitor\table;

use context_helper;
use moodle_recordset;
use core_user\fields;

defined('MOODLE_INTERNAL') || die;

/**
 * Class used to fetch participants based on a filterset for the
 * progress_overview table.
 *
 * @package block_completion_monitor
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_overview_search
{
    public function __construct(
        protected int $courseid,

        protected ?string $userfieldselect = null,
        protected ?string $userfieldjoins = null,
        protected ?array $userfieldparams = null,
        protected ?array $userfieldmappings = null,

        protected ?string $inneruseralias = null,
        protected ?string $subqueryalias = null,
        protected ?array $queryparams = []
    ) {
        [
            'selects' => $this->userfieldselect,
            'joins' => $this->userfieldjoins,
            'params' => $this->userfieldparams,
            'mappings' => $this->userfieldmappings
        ] = (array) fields::for_identity(null)->with_userpic()->get_sql('u', true);

        if (!empty($this->userfieldparams)) {
            $this->add_to_queryparams($this->userfieldparams);
        }
    }

    /**
     * Main function that contains the SQL query
     * 
     * @param string $additionalwhere
     * @param array $additionalparams
     * @param string $sort
     * @param int $limitfrom
     * @param int $limitnum
     * @return moodle_recordset
     */
    public function get_participants(string $additionalwhere, array $additionalparams, string $sort, int $limitfrom, int $limitnum): moodle_recordset
    {
        global $DB;

        $this->subqueryalias = "targetusers";

        // Main query
        $select = $this->get_participants_select();
        $join = $this->get_participants_join();

        // Inner query
        $innerselect = $this->get_participants_innerselect();
        $innerjoin = $this->get_participants_innerjoin();
        $innerwhere = $this->get_participants_innerwhere();

        $sql = "$select
                FROM (
                    $innerselect
                    $innerjoin
                    $innerwhere
                ) $this->subqueryalias
                $join
                ";

        $this->add_where_to_query($sql, $additionalwhere);

        $this->add_to_queryparams($additionalparams);

        return $DB->get_counted_recordset_sql($sql, 'fullcount', $sort, $this->queryparams, $limitfrom, $limitnum);
    }

    private function get_participants_select(): string
    {
        $select = "SELECT ul.timeaccess as lastaccess, uc.completion";

        $select .= $this->userfieldselect;
        $select .= ', ' . context_helper::get_preload_record_columns_sql('ctx');

        return $select;
    }

    private function get_participants_innerselect(): string
    {
        $this->inneruseralias = "udistinct";

        $select = "SELECT DISTINCT {$this->inneruseralias}.id
            FROM {user} {$this->inneruseralias}";

        return $select;
    }

    private function get_participants_innerjoin(): string
    {
        $innerjoins[] = "INNER JOIN {user_enrolments} ue ON ue.userid = {$this->inneruseralias}.id";
        $innerjoins[] = "INNER JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid1";

        $this->add_to_queryparams(["courseid1" => $this->courseid]);

        $innerjoinsstring = implode("\n", $innerjoins);

        return $innerjoinsstring;
    }

    private function get_participants_innerwhere(): string
    {
        $where = "WHERE {$this->inneruseralias}.deleted = 0 AND {$this->inneruseralias}.id <> :siteguest";

        $this->add_to_queryparams(["siteguest" => SITEID]);

        return $where;
    }

    private function get_participants_join(): string
    {
        $join = ["INNER JOIN {user} u ON u.id = {$this->subqueryalias}.id"];

        if (!empty($this->userfieldjoins)) {
            $join[] = $this->userfieldjoins;
        }

        $join[] = "LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid2)";
        $join[] = "LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)";
        $join[] = "LEFT JOIN {user_completion} uc ON (uc.userid = u.id AND uc.courseid = :courseid3)";

        $params = [
            "courseid2" => $this->courseid,
            "courseid3" => $this->courseid,
            "contextlevel" => CONTEXT_USER,
        ];
        $this->add_to_queryparams($params);

        $joinsstring = implode("\n", $join);

        return $joinsstring;
    }

    /**
     * TODO: function to improve with the filterset (MEN-1426)
     *
     * @param string $query
     * @param string $where
     * @return void
     */
    private function add_where_to_query(string &$query, string $where): void
    {
        if ($where) {
            $query .= "WHERE $where";
        }
    }

    private function add_to_queryparams(array $params): void
    {
        $this->queryparams = array_merge($this->queryparams, $params);
    }
}
