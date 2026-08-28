<?php

namespace block_completion_monitor\table;

use context;
use core_user;
use context_helper;
use moodle_database;
use moodle_recordset;
use core_user\fields;
use core_table\local\filter\filter;
use core_table\local\filter\filterset;
use block_completion_monitor\repository\completion_monitor_repository;

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
        protected context $context,
        protected filterset $filterset,

        protected ?array $userfields = null,
        protected ?string $userfieldselect = null,
        protected ?string $userfieldjoins = null,
        protected ?array $userfieldparams = null,
        protected ?array $userfieldmappings = null,

        protected ?string $inneruseralias = null,
        protected ?string $subqueryalias = null,
        protected ?array $queryparams = [],

        protected ?completion_monitor_repository $repository = null,

        protected ?moodle_database $db = null
    ) {
        global $DB;

        [
            'selects' => $this->userfieldselect,
            'joins' => $this->userfieldjoins,
            'params' => $this->userfieldparams,
            'mappings' => $this->userfieldmappings
        ] = (array) fields::for_identity(null)->with_userpic()->get_sql('u', true);

        if (!empty($this->userfieldparams)) {
            $this->add_to_queryparams($this->userfieldparams);
        }

        $this->userfields = fields::get_identity_fields($this->context);

        $this->repository = new completion_monitor_repository();

        $this->db = $DB;
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
        $where = [];

        // Main query
        $select = $this->get_participants_select();
        $join = $this->get_participants_join();

        // Inner query
        $innerselect = $this->get_participants_innerselect();
        $innerjoin = $this->get_participants_innerjoin();
        $innerwhere = $this->get_participants_innerwhere();

        // Apply any role filtering.
        if ($this->filterset->has_filter(progress_overview_filterset::KEYWORDS)) {
            $this->get_filter_sql($join, $where, progress_overview_filterset::KEYWORDS);
        }

        // Apply any role filtering.
        if ($this->filterset->has_filter(progress_overview_filterset::ROLES)) {
            $this->get_filter_sql($join, $where, progress_overview_filterset::ROLES);
        }

        // Apply any group filtering.
        if ($this->filterset->has_filter(progress_overview_filterset::GROUPS)) {
            $this->get_filter_sql($join, $where, progress_overview_filterset::GROUPS);
        }

        // Apply any grouping filtering.
        if ($this->filterset->has_filter(progress_overview_filterset::GROUPINGS)) {
            $this->get_filter_sql($join, $where, progress_overview_filterset::GROUPINGS);
        }

        $sql = "$select
                FROM (
                    $innerselect
                    $innerjoin
                    $innerwhere
                ) $this->subqueryalias
                $join
                ";

        if ($additionalwhere) {
            $where[] = $additionalwhere;
        }

        $this->add_where_to_query($sql, $where);

        $this->add_to_queryparams($additionalparams);

        return $DB->get_counted_recordset_sql($sql, 'fullcount', $sort, $this->queryparams, $limitfrom, $limitnum);
    }

    /**
     * Target the function depending on the filter name and fill the
     * "join", "where", and "params" variable.
     * 
     * @param string $join
     * @param array $where
     * @param string $filterkeyword
     * @return void
     */
    private function get_filter_sql(string &$join, array &$where, string $filterkeyword)
    {
        $filter = $this->filterset->get_filter($filterkeyword);

        if ($filter->get_filter_values()) {
            $defaultfilters = [
                progress_overview_filterset::KEYWORDS,
                progress_overview_filterset::ROLES,
            ];
            $joinfilters = [
                progress_overview_filterset::GROUPS,
                progress_overview_filterset::GROUPINGS,
            ];

            $function = "get_" . $filterkeyword . "_sql";

            if (in_array($filterkeyword, $defaultfilters)) {
                [
                    'where' => $returnwhere,
                    'params' => $returnparams,
                ] = $this->$function($filter);
            }

            if (in_array($filterkeyword, $joinfilters)) {
                [
                    'joins' => $returnjoin,
                    'where' => $returnwhere,
                    'params' => $returnparams,
                ] = $this->$function($filter);
            }

            if (isset($returnjoin) && !empty($returnjoin)) {
                $join .= $returnjoin;
            }

            if (isset($returnwhere) && !empty($returnwhere)) {
                $where[] = $returnwhere;
            }

            if (isset($returnparams) && !empty($returnparams)) {
                $this->add_to_queryparams($returnparams);
            }
        }
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
     * @param array $where
     * @return void
     */
    private function add_where_to_query(string &$query, array $where): void
    {
        $wherearray = array_filter($where);

        if (!empty($wherearray)) {
            $wherenot = '';

            switch ($this->filterset->get_join_type()) {
                case $this->filterset::JOINTYPE_ALL:
                    $wheresjoin = ' AND ';
                    break;

                case $this->filterset::JOINTYPE_NONE:
                    $wherenot = ' NOT ';
                    $wheresjoin = ' AND NOT ';

                    // Some of the $where conditions may begin with `NOT` which results in `AND NOT NOT ...`.
                    // To prevent this from breaking on Oracle the inner WHERE clause is wrapped in brackets, making it
                    // `AND NOT (NOT ...)` which is valid in all DBs.
                    $wherearray = array_map(function ($where) {
                        return "({$where})";
                    }, $wherearray);
                    break;

                default:
                    // Default to 'Any' jointype.
                    $wheresjoin = ' OR ';
                    break;
            }

            $wheres = implode($wheresjoin, $wherearray);
            $query .= "WHERE $wherenot $wheres";
        }
    }

    private function add_to_queryparams(array $params): void
    {
        $this->queryparams = array_merge($this->queryparams, $params);
    }

    /**
     * Prepare SQL where clause and associated parameters for any keyword searches being performed.
     * 
     * @param mixed $filter
     * @return array[]|array{params: array, where: string}
     */
    private function get_keywords_sql(?filter $filter = null): array
    {
        global $DB, $USER;

        $sql = [
            "where" => "",
            "params" => [],
        ];

        if ($filter === null) {
            return $sql;
        }

        $jointype = $filter->get_join_type();
        // None join types in both filter row and filterset require additional 'not null' handling for accurate keywords matches.
        $notjoin = false;

        // Determine how to match values in the query.
        switch ($jointype) {
            case $filter::JOINTYPE_ALL:
                $wherejoin = ' AND ';
                break;
            case $filter::JOINTYPE_NONE:
                $wherejoin = ' AND NOT ';
                $notjoin = true;
                break;
            default:
                // Default to 'ANY' jointype.
                $wherejoin = ' OR ';
                break;
        }

        // Handle filterset "none" join type.
        if ($this->filterset->get_join_type() === $this->filterset::JOINTYPE_NONE) {
            $notjoin = true;
        }

        $keywords = $filter->get_filter_values();

        $canviewfullnames = has_capability('moodle/site:viewfullnames', $this->context);

        foreach ($keywords as $index => $keyword) {
            $searchkey1 = 'search' . $index . '1';
            $searchkey2 = 'search' . $index . '2';
            $searchkey3 = 'search' . $index . '3';
            $searchkey4 = 'search' . $index . '4';
            $searchkey5 = 'search' . $index . '5';
            $searchkey6 = 'search' . $index . '6';
            $searchkey7 = 'search' . $index . '7';

            $conditions = [];

            // Search by fullname.
            [$fullname, $fullnameparams] = fields::get_sql_fullname('u', $canviewfullnames);
            $conditions[] = $DB->sql_like($fullname, ':' . $searchkey1, false, false);
            $sql["params"] = array_merge($sql["params"], $fullnameparams);

            // Search by email.
            $email = $DB->sql_like('email', ':' . $searchkey2, false, false);

            if ($notjoin) {
                $email = "(email IS NOT NULL AND {$email})";
            }

            if (!in_array('email', $this->userfields)) {
                $maildisplay = 'maildisplay' . $index;
                $userid1 = 'userid' . $index . '1';
                // Prevent users who hide their email address from being found by others
                // who aren't allowed to see hidden email addresses.
                $email = "(" . $email . " AND (" .
                    "u.maildisplay <> :$maildisplay " .
                    "OR u.id = :$userid1" . // Users can always find themselves.
                    "))";
                $sql["params"][$maildisplay] = core_user::MAILDISPLAY_HIDE;
                $sql["params"][$userid1] = $USER->id;
            }

            $conditions[] = $email;

            // Search by idnumber.
            $idnumber = $DB->sql_like('idnumber', ':' . $searchkey3, false, false);

            if ($notjoin) {
                $idnumber = "(idnumber IS NOT NULL AND  {$idnumber})";
            }

            if (!in_array('idnumber', $this->userfields)) {
                $userid2 = 'userid' . $index . '2';
                // Users who aren't allowed to see idnumbers should at most find themselves
                // when searching for an idnumber.
                $idnumber = "(" . $idnumber . " AND u.id = :$userid2)";
                $sql["params"][$userid2] = $USER->id;
            }

            $conditions[] = $idnumber;

            // Search all user identify fields.
            $extrasearchfields = fields::get_identity_fields(null);
            foreach ($extrasearchfields as $fieldindex => $extrasearchfield) {
                if (in_array($extrasearchfield, ['email', 'idnumber', 'country'])) {
                    // Already covered above.
                    continue;
                }
                // The param must be short (max 32 characters) so don't include field name.
                $param = $searchkey3 . '_ident' . $fieldindex;
                $fieldsql = $this->userfieldmappings[$extrasearchfield];
                $condition = $DB->sql_like($fieldsql, ':' . $param, false, false);
                $sql["params"][$param] = "%$keyword%";

                if ($notjoin) {
                    $condition = "($fieldsql IS NOT NULL AND {$condition})";
                }

                if (!in_array($extrasearchfield, $this->userfields)) {
                    // User cannot see this field, but allow match if their own account.
                    $userid3 = 'userid' . $index . '3_ident' . $fieldindex;
                    $condition = "(" . $condition . " AND u.id = :$userid3)";
                    $sql["params"][$userid3] = $USER->id;
                }
                $conditions[] = $condition;
            }

            // Search by middlename.
            $middlename = $DB->sql_like('middlename', ':' . $searchkey4, false, false);

            if ($notjoin) {
                $middlename = "(middlename IS NOT NULL AND {$middlename})";
            }

            $conditions[] = $middlename;

            // Search by alternatename.
            $alternatename = $DB->sql_like('alternatename', ':' . $searchkey5, false, false);

            if ($notjoin) {
                $alternatename = "(alternatename IS NOT NULL AND {$alternatename})";
            }

            $conditions[] = $alternatename;

            // Search by firstnamephonetic.
            $firstnamephonetic = $DB->sql_like('firstnamephonetic', ':' . $searchkey6, false, false);

            if ($notjoin) {
                $firstnamephonetic = "(firstnamephonetic IS NOT NULL AND {$firstnamephonetic})";
            }

            $conditions[] = $firstnamephonetic;

            // Search by lastnamephonetic.
            $lastnamephonetic = $DB->sql_like('lastnamephonetic', ':' . $searchkey7, false, false);

            if ($notjoin) {
                $lastnamephonetic = "(lastnamephonetic IS NOT NULL AND {$lastnamephonetic})";
            }

            $conditions[] = $lastnamephonetic;

            if (!empty($sql["where"])) {
                $sql["where"] .= $wherejoin;
            } else if ($jointype === $filter::JOINTYPE_NONE) {
                // Join type 'None' requires the WHERE to begin with NOT.
                $sql["where"] .= ' NOT ';
            }

            $sql["where"] .= "(" . implode(" OR ", $conditions) . ") ";
            $sql["params"][$searchkey1] = "%$keyword%";
            $sql["params"][$searchkey2] = "%$keyword%";
            $sql["params"][$searchkey3] = "%$keyword%";
            $sql["params"][$searchkey4] = "%$keyword%";
            $sql["params"][$searchkey5] = "%$keyword%";
            $sql["params"][$searchkey6] = "%$keyword%";
            $sql["params"][$searchkey7] = "%$keyword%";
        }

        return $sql;
    }

    /**
     * Prepare SQL where clause and associated parameters for any roles filtering being performed.
     * 
     * @param filter|null $filter
     * @return array{params: array, where: string}
     */
    protected function get_roles_sql(filter $filter): array
    {
        $where = '';
        $params = [];

        $roleids = $filter->get_filter_values();
        $jointype = $filter->get_join_type();

        // Determine how to match values in the query.
        $matchinsql = 'IN';
        switch ($jointype) {
            case $filter::JOINTYPE_ALL:
                $wherejoin = ' AND ';
                break;
            case $filter::JOINTYPE_NONE:
                $wherejoin = ' AND NOT ';
                $matchinsql = 'NOT IN';
                break;
            default:
                // Default to 'Any' jointype.
                $wherejoin = ' OR ';
                break;
        }

        // We want to query both the current context and parent contexts.
        $rolecontextids = $this->context->get_parent_context_ids(true);

        // Get users without any role, if needed.
        if (($withoutkey = array_search(-1, $roleids)) !== false) {
            $isjointypenone = $jointype === $filter::JOINTYPE_NONE;

            $this->setup_sql_users_without_roles($where, $params, $roleids, $rolecontextids, $isjointypenone, $withoutkey, $wherejoin);
        }

        // Get users with specified roles, if needed.
        if (!empty($roleids)) {
            if ($filter::JOINTYPE_ALL === $jointype) {
                // All case - need one WHERE per filtered role.
                $this->setup_sql_all_selected_roles($where, $params, $roleids, $rolecontextids, $wherejoin);
            } else {
                // None / Any cases - need one WHERE to cover all filtered roles.
                $this->setup_sql_none_any_roles($where, $params, $rolecontextids, $roleids, $matchinsql);
            }
        }

        return [
            'where' => $where,
            'params' => $params,
        ];
    }

    /**
     * Fill "where" and "params" when the query look for users without roles.
     * 
     * @param string $where
     * @param array $params
     * @param mixed $roleids
     * @param array $rolecontextids
     * @param bool $isjointypenone
     * @param bool $withoutkey
     * @param string $wherejoin
     * @return void
     */
    private function setup_sql_users_without_roles(string &$where, array &$params, ?array &$roleids, array $rolecontextids, bool $isjointypenone, bool $withoutkey, string $wherejoin): void
    {
        list($relatedctxsql1, $norolectxparams) = $this->db->get_in_or_equal($rolecontextids, SQL_PARAMS_NAMED, 'relatedctx');

        $jointype = $isjointypenone ? "IN" : "NOT IN";
        $where .= "(u.id $jointype (SELECT userid FROM {role_assignments} WHERE contextid {$relatedctxsql1}))";

        $params = array_merge($params, $norolectxparams);

        if ($withoutkey !== false) {
            unset($roleids[$withoutkey]);
        }

        // Join if any roles will be included.
        if (!empty($roleids)) {
            // The NOT case is replaced with AND to prevent a double negative.
            $where .= $isjointypenone ? ' AND ' : $wherejoin;
        }
    }

    /**
     * Fill "where" and "params" when the query look for users with all selected roles.
     * 
     * @param string $where
     * @param array $params
     * @param array $roleids
     * @param array $rolecontextids
     * @param string $wherejoin
     * @return void
     */
    private function setup_sql_all_selected_roles(string &$where, array &$params, array $roleids, array $rolecontextids, string $wherejoin): void
    {
        $numroles = count($roleids);
        $rolecount = 1;

        foreach ($roleids as $roleid) {
            list($relatedctxsql, $relctxparams) = $this->db->get_in_or_equal($rolecontextids, SQL_PARAMS_NAMED, 'relatedctx');
            list($roleidssql, $roleidparams) = $this->db->get_in_or_equal($roleid, SQL_PARAMS_NAMED, 'roleids');

            $where .= "(u.id IN (
                            SELECT userid
                            FROM {role_assignments}
                            WHERE roleid {$roleidssql}
                            AND contextid {$relatedctxsql})
                        )";

            if ($rolecount < $numroles) {
                $where .= $wherejoin;
                $rolecount++;
            }

            $params = array_merge($params, $roleidparams, $relctxparams);
        }
    }

    /**
     * Fill "where" and "params" when the query look for users with none or any selected roles.
     * Depend on the "matchinsql" variable.
     * 
     * @param string $where
     * @param array $params
     * @param array $rolecontextids
     * @param array $roleids
     * @param string $matchinsql
     * @return void
     */
    private function setup_sql_none_any_roles(string &$where, array &$params, array $rolecontextids, array $roleids, string $matchinsql): void
    {
        list($relatedctxsql, $relctxparams) = $this->db->get_in_or_equal($rolecontextids, SQL_PARAMS_NAMED, 'relatedctx');
        list($roleidssql, $roleidsparams) = $this->db->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'roleids');

        $where .= "(u.id {$matchinsql} (
                        SELECT userid
                        FROM {role_assignments}
                        WHERE roleid {$roleidssql}
                        AND contextid {$relatedctxsql})
                    )";

        $params = array_merge($params, $roleidsparams, $relctxparams);
    }

    /**
     * Prepare SQL where clause and associated parameters for any groups filtering being performed.
     * 
     * @param filter|null $filter
     * @throws \coding_exception
     * @return array[]|array{joins: string, params: array, where: string}
     */
    protected function get_groups_sql(?filter $filter = null): array
    {
        $sql = [
            "joins" => "",
            "where" => "",
            "params" => [],
        ];

        if ($filter === null) {
            return $sql;
        }

        switch ($filter->get_join_type()) {
            case filterset::JOINTYPE_NONE:
                $groupsjointype = GROUPS_JOIN_NONE;
                break;
            case filterset::JOINTYPE_ANY:
                $groupsjointype = GROUPS_JOIN_ANY;
                break;
            case filterset::JOINTYPE_ALL:
                $groupsjointype = GROUPS_JOIN_ALL;
                break;
            default:
                throw new \coding_exception('unanticipated groups join type');
        }

        $groupids = $filter->get_filter_values();

        if ($groupids) {
            $groupsjoin = groups_get_members_join($groupids, 'u.id', $this->context, $groupsjointype);

            $sql["joins"] = "\n$groupsjoin->joins";
            $sql["where"] = "({$groupsjoin->wheres})";
            $sql["params"] = $groupsjoin->params;
        }

        return $sql;
    }

    /**
     * Prepare SQL where clause and associated parameters for any groupings filtering being performed.
     * 
     * @param filter|null $filter
     * @throws \coding_exception
     * @return array[]|array{joins: string, params: array, where: string}
     */
    protected function get_groupings_sql(?filter $filter = null): array
    {
        global $DB;

        $sql = [
            "joins" => "",
            "where" => "",
            "params" => [],
        ];

        if ($filter === null) {
            return $sql;
        }

        if ($filter->get_join_type() !== filterset::JOINTYPE_ANY) {
            throw new \coding_exception('unanticipated groupings join type');
        }

        $groupids = $this->repository->get_groups_from_grouping($filter->get_filter_values(), $this->courseid);

        if ($groupids) {
            $groupsjoin = groups_get_members_join($groupids, 'u.id', $this->context, GROUPS_JOIN_ANY);
            $sql["joins"] = "\n$groupsjoin->joins";
            $sql["where"] = "({$groupsjoin->wheres})";
            $sql["params"] = $groupsjoin->params;
        }

        return $sql;
    }
}
