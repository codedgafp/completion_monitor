<?php

namespace block_completion_monitor\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_completion\cm_completion_details;
use context_course;

defined('MOODLE_INTERNAL') || die();

class get_completion_conditions extends external_api
{
    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id du cours'),
            'userid'   => new external_value(PARAM_INT, 'Id de l\'utilisateur'),
            'cmid'     => new external_value(PARAM_INT, 'Id du module de cours'),
        ]);
    }

    /**
     * @param int $courseid
     * @param int $userid
     * @param int $cmid
     * @return array
     */
    public static function execute(int $courseid, int $userid, int $cmid): array
    {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid'   => $userid,
            'cmid'     => $cmid,
        ]);

        $course  = get_course($params['courseid']);
        $context = context_course::instance($course->id);

        require_login($course, false);
        self::validate_context($context);
        require_capability('report/progress:view', $context);

        $modinfo = get_fast_modinfo($course);

        if (!isset($modinfo->cms[$params['cmid']])) {
            return ['conditions' => []];
        }

        $cm = $modinfo->cms[$params['cmid']];
        $conditions = [];

        if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
            $completioninfo = new \completion_info($course);
            $completiondata = $completioninfo->get_data($cm, false, $params['userid']);

            $conditions[] = [
                'status'      => (bool) ($completiondata->completionstate == COMPLETION_COMPLETE),
                'description' => get_string('markcomplete', 'completion'),
            ];

            return ['conditions' => $conditions];
        }

        $details = cm_completion_details::get_instance($cm, $params['userid'])->get_details();

        foreach ($details as $detail) {
            $conditions[] = [
                'status'      => (bool) $detail->status,
                'description' => (string) $detail->description,
            ];
        }

        return ['conditions' => $conditions];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'conditions' => new external_multiple_structure(
                new external_single_structure([
                    'status'      => new external_value(PARAM_BOOL, 'Condition remplie'),
                    'description' => new external_value(PARAM_RAW, 'Libellé de la condition'),
                ])
            ),
        ]);
    }
}
