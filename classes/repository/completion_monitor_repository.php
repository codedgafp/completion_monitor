<?php

namespace block_completion_monitor\repository;


/**
 * Activities Completion Course Monitoring repository.
 * 
 * @package     block_completion_monitor
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_monitor_repository
{
    /**
     * Moodle database interface
     * @var \moodle_database
     */
    protected \moodle_database $db;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
    }

    /**
     * Finds gradebook exclusions for students in a course
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public function get_grade_exclusions(int $courseid, int $userid = null): array
    {
        $sqlconcat = $this->db->sql_concat('i.itemmodule', "'-'", 'i.iteminstance', "'-'", 'g.userid');

        $sql = "SELECT g.id, $sqlconcat as exclusion
                FROM {grade_grades} g, {grade_items} i
                WHERE i.courseid = :courseid
                    AND i.id = g.itemid
                    AND g.excluded <> 0";
        $params = ['courseid' => $courseid];

        if (!is_null($userid)) {
            $sql .= " AND g.userid = :userid";
            $params['userid'] = $userid;
        }

        $results = $this->db->get_records_sql($sql, $params);

        $exclusions = [];
        foreach ($results as $value) {
            $exclusions[] = $value->exclusion;
        }

        return $exclusions;
    }

    /**
     * Finds submissions for a user in a course
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public function get_user_course_submissions(int $courseid, int $userid = 0): array
    {
        global $DB;

        $submissionsvalues = [];
        $submissionslist = [];

        $submissionsvalues = [...$this->get_assign_individual_submission($courseid, $userid)];
        $submissionsvalues = [...$this->get_assign_group_submission($courseid, $userid)];
        $submissionsvalues = [...$this->get_workshop_submission($courseid, $userid)];
        $submissionsvalues = [...$this->get_quiz_attempts($courseid, $userid)];
        $submissionsvalues = [...$this->get_quiz_attempts_grading_methods($courseid, $userid)];

        foreach ($submissionsvalues as $id => $obj) {
            $submissionslist[$id] = $obj;
        }

        ksort($submissionslist);

        return $submissionslist;
    }

    /**
     * Assignments with individual submission, or groups requiring a submission per user,
     * or ungrouped users in a group submission situation.
     * 
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    private function get_assign_individual_submission(int $courseid, int $userid = null): array
    {
        $assignwhere = '';
        $params['courseid'] = $courseid;
        if ($userid) {
            $assignwhere = ' AND s.userid = :userid';
            $params['userid'] = $userid;
        }

        $sqlconcat = $this->db->sql_concat('s.userid', "'-'", 'c.id');
        $sql = "SELECT $sqlconcat AS id, s.userid,
                    c.id AS cmid,
                    MAX(CASE WHEN ag.grade IS NULL OR ag.grade = -1 THEN 0 ELSE 1 END) AS graded
                FROM {assign_submission} s
                INNER JOIN {assign} a ON s.assignment = a.id
                INNER JOIN {course_modules} c ON c.instance = a.id
                INNER JOIN {modules} m ON m.name = 'assign' AND m.id = c.module
                LEFT JOIN {assign_grades} ag ON ag.assignment = s.assignment
                    AND ag.attemptnumber = s.attemptnumber
                    AND ag.userid = s.userid
                WHERE s.latest = 1
                    AND s.status = 'submitted'
                    AND a.course = :courseid
                    AND (
                        a.teamsubmission = 0 OR
                        (a.teamsubmission <> 0 AND a.requireallteammemberssubmit <> 0 AND s.groupid = 0) OR
                        (a.teamsubmission <> 0 AND a.preventsubmissionnotingroup = 0 AND s.groupid = 0)
                    )
                    $assignwhere
                GROUP BY s.userid, c.id
                ";

        return $this->db->get_records_sql($sql, $params);
    }

    /**
     * Assignments with groups requiring only one submission per group.
     * 
     * @param mixed $userid
     * @return array
     */
    private function get_assign_group_submission(int $courseid, int $userid = null): array
    {
        $assignwhere = '';
        $params['courseid'] = $courseid;
        if ($userid) {
            $assignwhere = 'AND s.userid = :userid';
            $params['userid'] = $userid;
        }

        $sqlconcat = $this->db->sql_concat('s.userid', "'-'", 'c.id');
        $sql = "SELECT $sqlconcat AS id,
                    s.userid, c.id AS cmid,
                    MAX(CASE WHEN ag.grade IS NULL OR ag.grade = -1 THEN 0 ELSE 1 END) AS graded
                FROM {assign_submission} gs
                INNER JOIN {assign} a ON gs.assignment = a.id
                INNER JOIN {course_modules} c ON c.instance = a.id
                INNER JOIN {modules} m ON m.name = 'assign' AND m.id = c.module
                INNER JOIN {groups_members} s ON s.groupid = gs.groupid
                LEFT JOIN {assign_grades} ag ON ag.assignment = gs.assignment
                    AND ag.attemptnumber = gs.attemptnumber
                    AND ag.userid = s.userid
                WHERE gs.latest = 1
                    AND gs.status = 'submitted'
                    AND gs.userid = 0
                    AND a.course = :courseid
                    AND (a.teamsubmission <> 0 AND a.requireallteammemberssubmit = 0)
                    $assignwhere
                GROUP BY s.userid, c.id";

        return $this->db->get_records_sql($sql, $params);
    }

    /**
     * Submission by workshop
     * 
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    private function get_workshop_submission(int $courseid, int $userid = null): array
    {
        $workshopwhere = '';
        $params['courseid'] = $courseid;
        if ($userid) {
            $workshopwhere = 'AND s.authorid = :userid';
            $params['userid'] = $userid;
        }

        $sqlconcat = $this->db->sql_concat('s.authorid', "'-'", 'c.id');
        $sql = "SELECT $sqlconcat AS id,
                    s.authorid AS userid, c.id AS cmid,
                    1 AS graded
                FROM {workshop_submissions} s, {workshop} w, {modules} m, {course_modules} c
                WHERE s.workshopid = w.id
                AND w.course = :courseid
                AND m.name = 'workshop'
                AND m.id = c.module
                AND c.instance = w.id
                $workshopwhere
                GROUP BY s.authorid, c.id";

        return $this->db->get_records_sql($sql, $params);
    }

    /**
     * Number of quiz attempts
     * 
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    private function get_quiz_attempts(int $courseid, int $userid = null): array
    {
        $quizwhere = '';
        $params = [
            'courseid' => $courseid,
            'gmfirst' => 3,
            'gmlast' => 4,
        ];

        if ($userid) {
            $quizwhere = 'AND qa.userid = :userid';
            $params['userid'] = $userid;
        }

        $sqlconcat = $this->db->sql_concat('qa.userid', "'-'", 'c.id');
        $sql = "SELECT $sqlconcat AS id,
                qa.userid, c.id AS cmid,
                (CASE WHEN qa.sumgrades IS NULL THEN 0 ELSE 1 END) AS graded
            FROM {quiz_attempts} qa
            INNER JOIN {quiz} q ON q.id = qa.quiz
            INNER JOIN {course_modules} c ON c.instance = q.id
            INNER JOIN {modules} m ON m.name = 'quiz' AND m.id = c.module
            WHERE qa.state = 'finished'
            AND q.course = :courseid
            AND qa.attempt = (
                SELECT CASE WHEN q.grademethod = :gmfirst THEN MIN(qa1.attempt)
                            WHEN q.grademethod = :gmlast THEN MAX(qa1.attempt) END
                FROM {quiz_attempts} qa1
                WHERE qa1.quiz = qa.quiz
                AND qa1.userid = qa.userid
                AND qa1.state = 'finished'
            )
            $quizwhere";

        return $this->db->get_records_sql($sql, $params);
    }

    /**
     * Number of quiz attempts depend of grading method max and average
     * 
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    private function get_quiz_attempts_grading_methods(int $courseid, int $userid = null): array
    {
        $quizwhere = '';
        $params = [
            'courseid' => $courseid,
            'gmmax' => 1,
            'gmavg' => 2,
        ];

        if ($userid) {
            $quizwhere = 'AND qa.userid = :userid';
            $params['userid'] = $userid;
        }

        $sqlconcat = $this->db->sql_concat('qa.userid', "'-'", 'c.id');
        $sql = "SELECT $sqlconcat AS id,
                    qa.userid, c.id AS cmid,
                    MIN(CASE WHEN qa.sumgrades IS NULL THEN 0 ELSE 1 END) AS graded
                FROM {quiz_attempts} qa
                INNER JOIN {quiz} q ON q.id = qa.quiz
                INNER JOIN {course_modules} c ON c.instance = q.id
                INNER JOIN {modules} m ON m.name = 'quiz' AND m.id = c.module
                WHERE (q.grademethod = :gmmax OR q.grademethod = :gmavg)
                    AND qa.state = 'finished'
                    AND q.course = :courseid
                    $quizwhere
                GROUP BY qa.userid, c.id";

        return $this->db->get_records_sql($sql, $params);
    }

    public function activity_has_completion(int $courseId): bool
    {
        return $this->db->record_exists_select(
            'course_modules',
            'course = :course AND completion <> :none',
            [
                'course' => $courseId,
                'none' => COMPLETION_TRACKING_NONE
            ]
        );
    }

    public function block_instance_exists(int $contextId): bool
    {
        return  $this->db->record_exists('block_instances', [
            'blockname' => 'completion_monitor',
            'parentcontextid' => $contextId
        ]);
    }

}
