<?php

namespace block_completion_monitor\service;

use block_completion_monitor\helper\progress;
use block_completion_monitor\repository\completion_monitor_repository;

defined('MOODLE_INTERNAL') || die();

class progress_export_service
{
    use progress;

    private const COMPLETED = 'completed';

    private const INPROGRESS = 'in_progress';

    private const NOTSTARTED = 'not_started';

    private const LOCKED = 'locked';

    private const HIDE = 'hide';

    private const USER_CHUNK = 200;

    private array $completions = [];

    private array $viewed = [];

    private array $submissions = [];

    private array $canviewhidden = [];

    private array $cms = [];

    private \context $coursecontext;

    private \moodle_database $db;

    /**
     * @param \stdClass $course
     * @param int[] $cmids
     * @param int[] $userids
     */
    public function __construct(
        private \stdClass $course,
        private array $cmids,
        array $userids
    ) {
        global $DB;

        $this->db = $DB;
        $this->coursecontext = \context_course::instance($course->id);

        $modinfo = get_fast_modinfo($course);
        foreach ($this->cmids as $cmid) {
            if (isset($modinfo->cms[$cmid])) {
                $this->cms[$cmid] = $modinfo->cms[$cmid];
            }
        }

        $this->submissions = (new completion_monitor_repository())->get_user_course_submissions((int) $course->id);

        $userids = array_values(array_unique(array_map('intval', $userids)));

        $this->load_completions($userids);
        $this->load_viewed($userids);
    }

    /**
     * @param int $cmid
     * @param int $userid
     * @return string
     */
    public function get_status(int $cmid, int $userid): string
    {
        if (!isset($this->cms[$cmid])) {
            return self::NOTSTARTED;
        }

        $cm = $this->cms[$cmid];

        if ((int) $cm->visible === 0) {
            return self::HIDE;
        }

        if (isset($cm->availability)) {
            $availability = json_decode($cm->availability);
            $available = $availability->op == '&'
                ? !in_array(false, $availability->showc)
                : $availability->show;

            return $available ? self::LOCKED : ($this->can_view_hidden($userid) ? self::LOCKED : self::HIDE);
        }

        $completion = (object) ['completionstate' => $this->completions[$userid][$cmid] ?? COMPLETION_INCOMPLETE];
        $submission = $this->submissions["$userid-$cmid"] ?? null;
        $completionstate = $this->get_completion_state($completion, $submission);

        $viewed = isset($this->viewed[$userid][$cmid]);

        return !$viewed && !$completionstate ? self::NOTSTARTED : $this->completion_state_map($completionstate);
    }

    /**
     * @param int[] $userids
     * @return void
     */
    private function load_completions(array $userids): void
    {
        if (empty($userids) || empty($this->cmids)) {
            return;
        }

        [$cmsql, $cmparams] = $this->db->get_in_or_equal($this->cmids, SQL_PARAMS_NAMED, 'cm');

        foreach (array_chunk($userids, self::USER_CHUNK) as $chunk) {
            [$usersql, $userparams] = $this->db->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'usr');

            $records = $this->db->get_recordset_sql(
                "SELECT id, userid, coursemoduleid, completionstate
                   FROM {course_modules_completion}
                  WHERE userid $usersql AND coursemoduleid $cmsql",
                array_merge($userparams, $cmparams)
            );

            foreach ($records as $record) {
                $this->completions[(int) $record->userid][(int) $record->coursemoduleid] = (int) $record->completionstate;
            }

            $records->close();
        }
    }

    /**
     * @param int[] $userids
     * @return void
     */
    private function load_viewed(array $userids): void
    {
        if (empty($userids) || empty($this->cmids)) {
            return;
        }

        foreach (array_chunk($userids, self::USER_CHUNK) as $chunk) {
            $names = [];

            foreach ($chunk as $userid) {
                foreach ($this->cmids as $cmid) {
                    $names["course_module_viewed_{$userid}_{$cmid}"] = [$userid, $cmid];
                }
            }

            [$insql, $params] = $this->db->get_in_or_equal(array_keys($names), SQL_PARAMS_NAMED, 'pref');
            $found = $this->db->get_fieldset_select('user_preferences', 'name', "name $insql", $params);

            foreach ($found as $name) {
                if (isset($names[$name])) {
                    [$userid, $cmid] = $names[$name];
                    $this->viewed[$userid][$cmid] = true;
                }
            }
        }
    }

    /**
     * @param int $userid
     * @return bool
     */
    private function can_view_hidden(int $userid): bool
    {
        if (!isset($this->canviewhidden[$userid])) {
            $this->canviewhidden[$userid] = has_capability(
                'moodle/course:viewhiddenactivities',
                $this->coursecontext,
                $userid
            );
        }

        return $this->canviewhidden[$userid];
    }

    /**
     * @param int|string|null $state
     * @return string
     */
    private function completion_state_map(int|string|null $state): string
    {
        switch ($state) {
            case COMPLETION_INCOMPLETE:
            case COMPLETION_COMPLETE_FAIL:
            case ASSIGN_SUBMISSION_STATUS_SUBMITTED:
                return self::INPROGRESS;

            case COMPLETION_COMPLETE:
            case COMPLETION_COMPLETE_PASS:
                return self::COMPLETED;

            default:
                return self::NOTSTARTED;
        }
    }
}
