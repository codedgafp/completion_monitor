<?php

defined('MOODLE_INTERNAL') || die();
use block_completion_monitor\service\add_block_service;
function xmldb_block_completion_monitor_install()
{
    if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
        mtrace('completion_monitor: installation – ajout des blocks');

        $service = new add_block_service();
        $service->sync_block();

        mtrace('completion_monitor: installation terminée');
    }
}
