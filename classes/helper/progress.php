<?php

namespace block_completion_monitor\helper;

defined('MOODLE_INTERNAL') || die();

trait progress
{
    /**
     * Checks the progress of the user's activities/resources.
     *
     * @param array $activities
     * @param int $userid
     * @param array $submissions
     * @return array
     */
    protected function get_progress_from_course_modules(\stdClass $course, array $activities, int $userid, array $submissions): array
    {
        $completions = [];

        $completioninfo = new \completion_info($course);
        $cm = new \stdClass();

        foreach ($activities as /** @var activity_details */ $activity) {
            $cm->id = $activity->get_id();
            $completion = $completioninfo->get_data($cm, true, $userid);
            $submission = $submissions["$userid-$cm->id"] ?? null;

            $completions[$cm->id] = $this->get_completion_state($completion, $submission);
        }

        return $completions;
    }

    protected function get_completion_state(\stdClass $completion, \stdClass $submission = null)
    {
        if ($completion->completionstate == COMPLETION_INCOMPLETE && $submission) {
            return ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        } else if ($completion->completionstate == COMPLETION_COMPLETE_FAIL && $submission && !$submission->graded) {
            return ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        } else {
            return $completion->completionstate;
        }
    }
}