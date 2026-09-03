<?php

use block_completion_monitor\service\progress_overview_service;

/**
 * Wrapper script redirecting user operations to correct destination.
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package core_user
 */

require_once("../../config.php");
require_once($CFG->dirroot . '/course/lib.php');

$formactionurl = required_param('formaction', PARAM_URL);
$courseid = required_param('courseid', PARAM_INT);

$PAGE->set_url('/blocks/completion_monitor/action_redir.php', ['formaction' => $formactionurl, 'courseid' => $courseid]);
list($formaction) = explode('?', $formactionurl, 2);

$actions = ['bulkchange.php'];

if (array_search($formaction, $actions) === false) {
    throw new \moodle_exception('unknownuseraction');
}

if (!confirm_sesskey()) {
    throw new \moodle_exception('confirmsesskeybad');
}

if ($formaction == 'bulkchange.php') {
    $context = context_course::instance($courseid);
    $PAGE->set_context($context);

    $url = new moodle_url($formactionurl);
    $operationname = $url->param('operation');

    $default = new moodle_url('/blocks/completion_monitor/progress_overview.php', ['courseid' => $courseid]);
    $returnurl = new moodle_url(optional_param('returnto', $default, PARAM_LOCALURL));

    $dataformat = $url->param('dataformat');

    if ($post = data_submitted()) {
        foreach ($post as $k => $v) {
            if (preg_match('/^user(\d+)$/', $k, $m)) {
                $userids[] = $m[1];
            }
        }
    }

    if (isset($userids) && empty($userids)) {
        redirect($returnurl, get_string('noselectedusers', 'bulkusers'));
    }

    $service = new progress_overview_service($courseid, $context);

    if ($operationname == 'download_progress_users') {
        $service->download_progress_overview_users($dataformat, $userids);
    }
} else {
    throw new coding_exception('invalidaction');
}
