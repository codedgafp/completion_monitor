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
    protected function get_progress(\stdClass $course, array $activities, int $userid, array $submissions): array
    {
        $completions = [];

        $completioninfo = new \completion_info($course);
        $cm = new \stdClass();

        foreach ($activities as $activity) {
            $cm->id = $activity['id'];
            $completion = $completioninfo->get_data($cm, true, $userid);
            $submission = $submissions["$userid-$cm->id"] ?? null;

            if ($completion->completionstate == COMPLETION_INCOMPLETE && $submission) {
                $completions[$cm->id] = 'submitted';
            } else if ($completion->completionstate == COMPLETION_COMPLETE_FAIL && $submission && !$submission->graded) {
                $completions[$cm->id] = 'submitted';
            } else {
                $completions[$cm->id] = $completion->completionstate;
            }
        }

        return $completions;
    }
}